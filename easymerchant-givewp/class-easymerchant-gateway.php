<?php

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\PaymentComplete;
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\PaymentGateway;

/**
 * @inheritDoc
 */
class EasyMerchantGateway extends PaymentGateway
{
    /**
     * @inheritDoc
     */
    public static function id(): string
    {
        return 'easymerchant-gateway';
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return self::id();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return __('EasyMerchant', 'easymerchant-givewp');
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodLabel(): string
    {
        return __('EasyMerchant', 'easymerchant-givewp');
    }

    /**
     * Display gateway fields for v2 donation forms
     */
    public function getLegacyFormFieldMarkup(int $formId, array $args): string
    {
        // Step 1: add any gateway fields to the form using html.  In order to retrieve this data later the name of the input must be inside the key gatewayData (name='gatewayData[input_name]').
        // Step 2: you can alternatively send this data to the $gatewayData param using the filter `givewp_create_payment_gateway_data_{gatewayId}`
        // return easymerchant_output_redirect_notice($formId, $args);
        $output1 = easymerchant_output_redirect_notice($formId, $args);

        // Call the second function
        $output2 = easymerchant_givewp_custom_credit_card_form($form_id);

        // Concatenate the results or use any other logic based on your requirements
        $result = $output1 . $output2;

        return $result;
    }

    /**
     * Register a js file to display gateway fields for v3 donation forms
     */
    public function enqueueScript(int $formId)
    {
        wp_enqueue_script('easymerchant-gateway-api', 'https://api.easymerchant.io/assets/checkout/easyMerchant.js', [], '1.0.0', true);
        wp_enqueue_script($this::id(), plugin_dir_url(__FILE__) . 'assets/js/easymerchant-gateway.js', ['easymerchant-gateway-api', 'react', 'wp-element', 'wp-i18n'], '1.0.0', true);
    }

    /**
     * Send form settings to the js gateway counterpart
     */
    public function formSettings(int $formId): array
    {
        return [
            // 'publishable_key' => 'Ow9CaBwjk23cHGakuhBDl5sj9'
            'publishable_key' => '280066215f64af12cee5765cb'
        ];
    }

    /**
     * @inheritDoc
     */
    public function createPayment(Donation $donation, $gatewayData): GatewayCommand
    {
        try {
            // Step 1: Validate any data passed from the gateway fields in $gatewayData.  Throw the PaymentGatewayException if the data is invalid.
            if (empty($gatewayData['easymerchant-charge-id'])) {
                throw new PaymentGatewayException(__('EasyMerchant Charge ID is required.', 'example-give' ) );
            }

            // Step 2: Create a payment with your gateway or use existing data.
            // $chargeId = give_clean($gatewayData['easymerchant-charge-id']);
             $chargeId = $this->makePaymentRequest(['transaction_id' => $gatewayData['easymerchant-charge-id']]);

            // Step 3: Return a command to complete the donation. You can alternatively return PaymentProcessing for gateways that require a webhook or similar to confirm that the payment is complete. PaymentProcessing will trigger a Payment Processing email notification, configurable in the settings.
            return new PaymentComplete($chargeId);
        } catch (Exception $e) {
            // Step 4: If an error occurs, you can update the donation status to something appropriate like failed, and finally throw the PaymentGatewayException for the framework to catch the message.
            $errorMessage = $e->getMessage();

            $donation->status = DonationStatus::FAILED();
            $donation->save();

            DonationNote::create([
                'donationId' => $donation->id,
                'content' => sprintf(esc_html__('Donation failed. Reason: %s', 'example-give'), $errorMessage)
            ]);

            throw new PaymentGatewayException($errorMessage);
        }
    }

    /**
     * @inerhitDoc
     */
    public function refundDonation(Donation $donation): PaymentRefunded
    {
        // Step 1: refund the donation with your gateway.
        // Step 2: return a command to complete the refund.
        return new PaymentRefunded();
    }

    private function makePaymentRequest(array $data): array
    {
        $cc_info                  = give_get_donation_easymerchant_cc_info();

        // Use the card details in this function calling from "give_get_donation_easymerchant_cc_info" function.
        $cc_holder              = $cc_info['card_name'];
        $cc_number              = $cc_info['card_number_easy'];
        $cardNumber             = str_replace(' ', '', $cc_number);
        $month                  = $cc_info['card_exp_month'];
        $year                   = $cc_info['card_exp_year'];
        $cc_cvc                 = $cc_info['card_cvc'];
        $currentDate       = date("m/d/Y");
        $originalValues         = ["day", "week", "month", "quarter", "year"]; // API support these terms
        $replacementValues      = ["daily", "weekly", "monthly", "quarterly", "yearly"]; //givewp support these terms
        if (isset($data['period'])) {
            $originalValue        = $data['period'];
            $key                  = array_search($originalValue, $originalValues);
            if ($key !== false) {
                $data['period'] = $replacementValues[$key];
            }
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://stage-api.stage-easymerchant.io/api/v1/charges/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'payment_mode'   => 'test',
                'amount'         => $data['amount'],
                'name'           => $data['user_info']['first_name'],
                'email'          => $data['user_email'],
                'description'    => 'test',
                'start_date'     => $currentDate,
                'currency'       => give_get_currency($form_id),
                'card_number'    => $cardNumber,
                'exp_month'      => $month,
                'exp_year'       => $year,
                'cvc'            => $cc_cvc,
                'cardholder_name' => $cc_holder,
                'payment_type'   => 'recurring',
                'interval'       => $data['period'],
                'allowed_cycles' => 12,
            ]),
            CURLOPT_HTTPHEADER => array(
                'X-Api-Key: doggiedaycareKxYeMhRl',
                'X-Api-Secret: doggiedaycareIBagbKnt',
                'Content-Type: application/json',
            ),
        ));
        $response = json_decode(curl_exec($curl), true);
        // Check for cURL errors
        if (curl_errno($curl)) {
            throw new Exception('cURL error: ' . curl_error($curl));
        }
        curl_close($curl);
        // Check if the charge ID is present in the response
        if (isset($response['charge_id'])) {
            $chargeId = $response['charge_id'];
            return $chargeId;
        } else {
            throw new Exception('Charge ID not found in the response.');
        }
        return array_merge([
            'success' => true,
            'transaction_id' => $chargeId,
            'subscription_id' => $response['subscription_id'],
        ], $data);
    }
}
