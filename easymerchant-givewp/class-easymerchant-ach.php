<?php

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Framework\PaymentGateways\Commands\PaymentProcessing;


class EasyMerchantACH extends PaymentGateway
{

    /**
     * @inheritDoc
     */
    public static function id(): string
    {
        return 'easymerchant-ach';
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
        return __('Easymerchant ACH', 'easymerchant-givewp');
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodLabel(): string
    {
        return __('Easymerchant ACH', 'easymerchant-givewp');
    }

    /**
     * @inheritDoc
     */
    public function getLegacyFormFieldMarkup(int $formId, array $args): string
    {
        return easymerchant_givewp_custom_ach_form($formId);
    }

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
            $response = $this->getEasymerchantACHPayment([
                'amount'         => $donation->amount->formatToDecimal(),
                'name'           => trim("$donation->firstName $donation->lastName"),
                'email'          => $donation->email,
                'currency'       => $donation->amount->getCurrency()->getCode(),
            ]);


            if (empty($response)) {
                throw new PaymentGatewayException(__('Response not returned!', 'easymerchant-givewp'));
            }

            if (empty($response['status'])) {
                $message = empty($response['message']) ? 'Payment Not Successful!' : $response['message'];
                throw new PaymentGatewayException(__($message, 'easymerchant-givewp'));
            }

            if (empty($response['charge_id'])) {
                throw new PaymentGatewayException(__('EasyMerchant Charge ID is required.', 'easymerchant-givewp'));
            }

            // Invoke the webhook handler for successful payment
            // EasyMerchantWebhookHandler::handle_successful_payment([
            //     'reference_number' => $response['charge_id'],
            //     'amount'           => $donation->amount->formatToDecimal(),
            //     'status'           => 'Paid',
            // ]);

            // Return the PaymentProcessing object
            return new PaymentProcessing($response['charge_id']);
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
                "charge_id" => $donation->gatewayTransactionId
            ]);
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
            return new PaymentRefunded();
            echo "<script>
            function goBackTimed() {
                setTimeout(() => {
                    window.history.go(-1);
                }, 3000);
            }
        </script>";
        } catch (\Exception $exception) {
            throw new PaymentGatewayException('Unable to refund. ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }
    private function getEasymerchantACHPayment(array $data): array
    {
        $ach_info               = give_get_donation_easymerchant_ach_info();
        $accountNumber          = $ach_info['account_number'];
        $routingNumber          = $ach_info['routing_number'];
        $accountType            = $ach_info['account_type'];
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
            "amount"            => $data['amount'],
            "name"              => $data['name'],
            'email'             => $data['email'],
            "description"       => "ACH Donation From GiveWP",
            "routing_number"    => $routingNumber,
            "account_number"    => $accountNumber,
            "account_type"      => $accountType,
            "entry_class_code"  => "WEB",
        ]);
        $response = wp_remote_post($apiUrl . '/ach/charge/', array(
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
