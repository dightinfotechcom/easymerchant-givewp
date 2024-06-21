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
use Give\Framework\Receipts\DonationReceipt;

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

    public function __invoke(DonationReceipt $receipt): DonationReceipt
    {

        $this->fillDonationDetails($receipt);
        return $receipt;
    }
    /**
     * Display gateway fields for v2 donation forms
     */
    public function getLegacyFormFieldMarkup(int $formId, array $args): string
    {

        // return easymerchant_output_redirect_notice($formId, $args);
        $output1 = easymerchant_output_redirect_notice($formId, $args);
        // Call the second function
        $output2 = easymerchant_givewp_custom_credit_card_form($formId);
        // Concatenate the results or use any other logic based on your requirements
        $result = $output1 . $output2;

        return $result;
    }

    /**
     * Send form settings to the js gateway counterpart
     */
    public function formSettings(int $formId): array
    {
        return [
            'publishable_key' => easymerchant_givewp_get_publishable_key()
        ];
    }

    /**
     * @inheritDoc
     */
    public function createPayment(Donation $donation, $gatewayData): GatewayCommand
    {
        try {
            $response = $this->makePaymentRequest([
                'amount'    => $donation->amount->formatToDecimal(),
                'name'      => trim("$donation->firstName $donation->lastName"),
                'email'     => $donation->email,
                'currency'  => $donation->amount->getCurrency()->getCode(),
            ]);

            if (empty($response)) {
                throw new PaymentGatewayException(__('Something went wrong!', 'easymerchant-givewp'));
            }

            if (empty($response['status'])) {
                $message = empty($response['message']) ? 'Payment not successful!' : $response['message'];
                throw new PaymentGatewayException(__($message, 'easymerchant-givewp'));
            }

            if (empty($response['charge_id'])) {
                throw new PaymentGatewayException(__('EasyMerchant Charge ID is required.', 'easymerchant-givewp'));
            }

            return new PaymentComplete($response['charge_id']);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            $donation->status = DonationStatus::FAILED();
            $donation->save();

            DonationNote::create([
                'donationId' => $donation->id,
                'content' => sprintf(esc_html__('Donation failed. Reason: %s', 'easymerchant-givewp'), $errorMessage)
            ]);
            throw new PaymentGatewayException($errorMessage);
        }
    }

    /**
     * @inerhitDoc
     */
    public function refundDonation(Donation $donation): PaymentRefunded
    {

        $apiKey                 = easymerchant_givewp_get_api_key();
        $apiSecretKey           = easymerchant_givewp_get_api_secret_key();
        if (give_is_test_mode()) {
            // GiveWP is in test mode
            $apiUrl = 'https://stage-api.stage-easymerchant.io/api/v1';
        } else {
            $apiUrl = 'https://api.easymerchant.io/api/v1';
        }

        try {

            $body = json_encode([
                "charge_id" => $donation->gatewayTransactionId,
                'amount'    => $donation->amount->formatToDecimal(),
            ]);

            $checkStatusApi = wp_remote_post($apiUrl . '/charges/' . $donation->gatewayTransactionId, array(
                'method'    => 'GET',
                'headers'   => array(
                    'X-Api-Key'     => $apiKey,
                    'X-Api-Secret'  => $apiSecretKey,
                    'Content-Type'  => 'application/json',
                )
            ));
            $checkPaidUnsetteled = wp_remote_retrieve_body($checkStatusApi);
            $checkStatus = json_decode($checkPaidUnsetteled, true);

            if ($checkStatus['data']['status'] === 'Paid') {
                $response = wp_remote_post($apiUrl . '/refunds/', array(
                    'method'    => 'POST',
                    'headers'   => array(
                        'X-Api-Key'      => $apiKey,
                        'X-Api-Secret'   => $apiSecretKey,
                        'Content-Type'   => 'application/json',
                    ),
                    'body'               => $body,
                ));
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
        
                    $donation->status = DonationStatus::REFUNDED();
                    $donation->save();
                    DonationNote::create([
                        'donationId' => $donation->id,
                        'content' => sprintf(esc_html__('Refund processed successfully. Reason: %s', 'easymerchant-givewp'), 'refunded by user')
                    ]);
                
            }
            print_r($response_data['message']);
            echo "<script>
                setTimeout(() => {
                    window.history.go(-1);
                }, 1000);
                </script>";
            die();
            return new PaymentRefunded();
        } catch (\Exception $exception) {
            throw new PaymentGatewayException('Unable to refund. ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }




    /**0
     * @throws Exception
     */
    private function makePaymentRequest(array $data): array
    {
        $cc_info                  = give_get_donation_easymerchant_cc_info();

        // Use the card details in this function calling from "give_get_donation_easymerchant_cc_info" function.
        $cc_holder              = $cc_info['card_name'];
        $cc_number              = $cc_info['card_number_easy'];
        $month                  = $cc_info['card_exp_month'];
        $year                   = $cc_info['card_exp_year'];
        $cc_cvc                 = $cc_info['card_cvc'];
        $apiKey                 = easymerchant_givewp_get_api_key();
        $apiSecretKey           = easymerchant_givewp_get_api_secret_key();

        if (give_is_test_mode()) {
            // GiveWP is in test mode
            $apiUrl = 'https://stage-api.stage-easymerchant.io/api/v1';
        } else {
            // GiveWP is not in test mode
            $apiUrl = 'https://api.easymerchant.io/api/v1';
        }


        $body = json_encode([
            'payment_mode'   => 'auth_and_capture',
            'amount'         => $data['amount'],
            'name'           => $data['name'],
            'email'          => $data['email'],
            'description'    => 'GiveWp Donation',
            'currency'       => $data['currency'],
            'card_number'    => $cc_number,
            'exp_month'      => $month,
            'exp_year'       => $year,
            'cvc'            => $cc_cvc,
            'cardholder_name' => $cc_holder,
        ]);

        $response = wp_remote_post($apiUrl . '/charges/', array(
            'method'    => 'POST',
            'headers'   => array(
                'X-Api-Key'      => $apiKey,
                'X-Api-Secret'   => $apiSecretKey,
                'Content-Type'   => 'application/json',
            ),
            'body'               => $body,
        ));


        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        return $response_data;
    }
}
