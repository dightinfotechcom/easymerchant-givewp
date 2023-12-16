<?php

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\PaymentGateway;


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




    /**
     * @inheritDoc
     */
    public function createPayment(Donation $donation, $gatewayData): GatewayCommand
    {
        try {
            $response = $this->getEasymerchantACHPayment([
                'amount'         => $donation->amount->formatToDecimal(),
                'name'           => trim("$donation->firstName $donation->lastName"),
                'account_number' => $donation->account_number,
                'routing_number' => $donation->routing_number,
            ]);
            if (empty($response)) {
                throw new PaymentGatewayException(__('Something went wrong!', 'easymerchant-givewp'));
            }

            if (empty($response['status'])) {
                $message = empty($response['message']) ? 'Something went wrong!' : $response['message'];
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
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl . '/refunds',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    "charge_id" => $donation->gatewayTransactionId
                ]),
                CURLOPT_HTTPHEADER => array(
                    'X-Api-Key: ' . $apiKey,
                    'X-Api-Secret: ' . $apiSecretKey,
                    'Content-Type: application/json',
                ),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FAILONERROR => true,
            ));

            $response = json_decode(curl_exec($curl), true);

            // Check for errors in the response
            if (isset($response['success']) && $response['success']) {
                $donation->status = DonationStatus::REFUNDED();
                $donation->save();
                return new PaymentRefunded();
            } else {
                // Handle error appropriately
                throw new PaymentGatewayException('Refund failed: ' . json_encode($response));
            }
        } catch (\Exception $exception) {
            throw new PaymentGatewayException('Unable to refund. ' . $exception->getMessage(), $exception->getCode(), $exception);
        } finally {
            curl_close($curl);
        }  
    }

    private function getEasymerchantACHPayment(array $donation): array
    {
        $ach_info               = give_get_donation_easymerchant_ach_info();
        $accountNumber          = $ach_info['account_number'];
        $routingNumber          = $ach_info['routing_number'];
        $apiKey                 = easymerchant_givewp_get_api_key();
        $apiSecretKey           = easymerchant_givewp_get_api_secret_key();

        if (give_is_test_mode()) {
            // GiveWP is in test mode
            $apiUrl = 'https://stage-api.stage-easymerchant.io/api/v1';
        } else {
            // GiveWP is not in test mode
            $apiUrl = 'https://api.easymerchant.io/api/v1';
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl . '/ach/charge/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'amount'            => $donation['amount'],
                "name"              => $donation['name'],
                "description"       => "ACH Donation From GiveWP",
                "routing_number"    => $routingNumber,
                "account_number"    => $accountNumber,
                "account_type"      => "checking",
                "entry_class_code"  => "WEB",
            ]),
            CURLOPT_HTTPHEADER => array(
                'X-Api-Key: ' . $apiKey,
                'X-Api-Secret: ' . $apiSecretKey,
                'Content-Type: application/json',
            ),
        ));

        $response = json_decode(curl_exec($curl), true);
        // Check for cURL errors
        if (curl_errno($curl)) {
            throw new Exception('cURL error: ' . curl_error($curl));
        }
        curl_close($curl);
        return $response;
    }
}
