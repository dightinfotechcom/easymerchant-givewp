<?php

/**
 * Class EasyMerchantWebhookHandler
 */
class EasyMerchantWebhookHandler
{

    /**
     * Initialize webhook handling
     */
    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register_webhook_endpoint'));
        add_action('rest_api_init', array(__CLASS__, 'handle_webhook_request'));
    }

    /**
     * Register the webhook endpoint
     */
    public static function register_webhook_endpoint()
    {
        register_rest_route('?webhook-give-listener', 'easymerchant', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_webhook_request'),
        ));
    }

    /**
     * Verify the webhook request
     */
    private static function verify_webhook_request($headers, $body, $api_key, $api_secret)
    {
        // Check if the necessary headers are present
        if (!isset($headers['X-EasyMerchant-Signature'], $headers['X-EasyMerchant-Timestamp'])) {
            return false;
        }

        // Get the provided timestamp and signature
        $timestamp = $headers['X-EasyMerchant-Timestamp'];
        $signature = $headers['X-EasyMerchant-Signature'];

        // Validate the timestamp to prevent replay attacks (adjust the time window as needed)
        $allowed_time_window = 300; // 5 minutes
        if (abs(time() - $timestamp) > $allowed_time_window) {
            return false;
        }

        // Get the raw request body
        $body = file_get_contents('php://input');

        // Generate the expected signature based on your API secret and the request body
        $expected_signature = hash_hmac('sha256', $body, $api_secret);

        // Compare the expected signature with the provided signature
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Handle incoming webhook requests
     */
    public static function handle_webhook_request()
    {
        // Get the raw request body
        $body = file_get_contents('php://input');

        // Verify the request (you may want to add more security checks)
        $headers = getallheaders();
        $api_key = easymerchant_givewp_get_api_key();
        $api_secret = easymerchant_givewp_get_api_secret_key();

        $valid_request = self::verify_webhook_request($headers, $body, $api_key, $api_secret);

        // if (!$valid_request) {
        //     http_response_code(401);
        //     exit('Unauthorized');
        // }

        // Process the webhook payload
        $data = json_decode($body, true);

        // Check if 'event' key exists in $data
        if (isset($data['event'])) {
            // Check the event type and handle accordingly
            if ($data['event'] === 'payment.success') {
                self::handle_successful_payment($data);
            } elseif ($data['event'] === 'payment.refunded') {
                self::handle_payment_refund($data);
            } elseif ($data['event'] === 'charge.updated') {
                self::handle_charge_updated($data);
            }

            // Send a success response to EasyMerchant
            status_header(200);
            exit('Webhook handled successfully');
        } else {
            // Handle the case when 'event' key is not present in $data
            status_header(400);
            exit('Invalid webhook payload. Missing "event" key.');
        }
    }

    /**
     * Handle successful payment webhook
     */
    private static function handle_successful_payment($data)
    {
        // Extract relevant information from the webhook payload
        $donation_id = $data['donation_id'];
        $amount = $data['amount'];
        $currency = $data['currency'];

        // Retrieve the donation record from your database based on the donation_id
        $donation = Donation::find($donation_id);

        // Verify that the donation record exists
        if (!$donation) {
            // Log an error or handle the situation where the donation record is not found
            error_log('Donation record not found for ID: ' . $donation_id);
            return;
        }

        // Check if the payment amount and currency match your expectations
        if ($donation->amount->formatToDecimal() != $amount || $donation->amount->getCurrency() != $currency) {
            // Log an error or handle the situation where the payment details don't match
            error_log('Payment details do not match expected values for donation ID: ' . $donation_id);
            return;
        }

        // Update the donation status to indicate a successful payment
        $donation->status = DonationStatus::COMPLETED();
        $donation->save();

        // Log the successful payment
        DonationNote::create([
            'donationId' => $donation->id,
            'content'    => sprintf(__('Payment successful. Amount: %s %s', 'easymerchant-givewp'), $amount, $currency),
        ]);
    }

    /**
     * Handle payment refund webhook
     */
    private static function handle_payment_refund($data)
    {
        // Extract relevant information from the webhook payload
        $donation_id = $data['donation_id'];
        $refund_id = $data['refund_id'];
        $amount_refunded = $data['amount_refunded'];
        $currency = $data['currency'];

        // Retrieve the donation record from your database based on the donation_id
        $donation = Donation::find($donation_id);

        // Verify that the donation record exists
        if (!$donation) {
            // Log an error or handle the situation where the donation record is not found
            error_log('Donation record not found for ID: ' . $donation_id);
            return;
        }

        // Check if the refund amount and currency match your expectations
        if ($donation->amount->formatToDecimal() != $amount_refunded || $donation->amount->getCurrency() != $currency) {
            // Log an error or handle the situation where the refund details don't match
            error_log('Refund details do not match expected values for donation ID: ' . $donation_id);
            return;
        }

        // Update the donation status to indicate a refunded payment
        $donation->status = DonationStatus::REFUNDED();
        $donation->save();

        // Log the refund details
        DonationNote::create([
            'donationId' => $donation->id,
            'content'    => sprintf(__('Payment refunded. Refund ID: %s Amount: %s %s', 'easymerchant-givewp'), $refund_id, $amount_refunded, $currency),
        ]);
    }

    /**
     * Handle charge updated webhook
     */
    private static function handle_charge_updated($data)
    {
        // Extract relevant information from the webhook payload
        $name = $data['name'];
        $amount = $data['amount'];
        $status = $data['status'];
        $reference_number = $data['reference_number'];
        // Determine the payment method (credit card or ACH) 
        $payment_method = isset($data['payment_method']) ? $data['payment_method'] : '';

        global $wpdb;

        $table_name = $wpdb->prefix . 'give_donationmeta';

        $transaction_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT donation_id FROM $table_name WHERE meta_key = '_give_payment_transaction_id' AND meta_value = %s",
                $reference_number
            )
        );

        if ($transaction_id) {
            // Update the relevant information in your system
            update_post_meta($transaction_id, '_give_payment_status', $status);
            update_post_meta($transaction_id, '_give_payment_method', $payment_method);

            // Log the charge update details
            error_log(sprintf(__('Charge updated. Name: %s Amount: %s Status: %s Reference Number: %s', 'easymerchant-givewp'), $name, $amount, $status, $reference_number));
        } else {
            // Log an error or handle the situation where the transaction ID is not found
            error_log('Transaction ID not found for reference number: ' . $reference_number);
        }
    }
}

// Initialize webhook handling
EasyMerchantWebhookHandler::init();
