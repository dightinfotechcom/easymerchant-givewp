<?php

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\SubscriptionComplete;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\SubscriptionModule;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use Give\Framework\PaymentGateways\Commands\SubscriptionProcessing;

/**
 * @inheritDoc
 */
class EasyMerchantACHGatewaySubscriptionModule extends SubscriptionModule
{
    /**
     * @inerhitDoc
     *
     * @throws Exception|PaymentGatewayException
     */
    public function createSubscription(
        Donation $donation,
        Subscription $subscription,
        $gatewayData
    ) {
        try {
            $response = $this->getEasymerchantACHPayment([
                'amount' => $donation->amount->formatToDecimal(),
                'name' => trim("$donation->firstName $donation->lastName"),
                'email' => $donation->email,
                'currency' => $subscription->amount->getCurrency(),
                'period' => $subscription->period->getValue()
            ]);

            if (empty($response['charge_id'])) {
                throw new PaymentGatewayException(__('EasyMerchant Charge ID is required.', 'easymerchant-givewp'));
            }

            if (empty($response['subscription_id'])) {
                throw new PaymentGatewayException(__('EasyMerchant Subscription ID is required.', 'easymerchant-givewp'));
            }
            // EasyMerchantWebhookHandler::handle_successful_subscription([
            //     'subscription_id' => $response['subscription_id'],
            //     'charge_id' => $response['charge_id'],
            // ]);
            return new SubscriptionProcessing($response['subscription_id'], $response['charge_id']);
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
     *
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(Subscription $subscription)
    {
        $apiKey                 = easymerchant_givewp_get_api_key();
        $apiSecretKey           = easymerchant_givewp_get_api_secret_key();
        if ($subscription->gatewayId != 'easymerchant-ach') return false;
        if (give_is_test_mode()) {
            // GiveWP is in test mode
            $apiUrl = 'https://stage-api.stage-easymerchant.io/api/v1';
        } else {

            $apiUrl = 'https://api.easymerchant.io/api/v1';
        }
        try {
            // Step 1: cancel the subscription with your gateway.
            $response = wp_remote_post($apiUrl . '/subscriptions/' . $subscription->gatewaySubscriptionId . '/cancel/', array(
                'method'    => 'POST',
                'headers'   => array(
                    'X-Api-Key'      => $apiKey,
                    'X-Api-Secret'   => $apiSecretKey,
                    'Content-Type'   => 'application/json',
                ),
                // 'body'               => $body,
            ));


            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            // Step 2: update the subscription status to cancelled.
            $subscription->status = SubscriptionStatus::CANCELLED();
            $subscription->save();
        } catch (\Exception $exception) {
            throw new PaymentGatewayException(
                sprintf(
                    'Unable to cancel subscription. %s',
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * @throws Exception
     */
    private function getEasymerchantACHPayment(array $data): array
    {
        $ach_info               = give_get_donation_easymerchant_ach_info();
        $accountNumber          = $ach_info['account_number'];
        $routingNumber          = $ach_info['routing_number'];
        $accountType            = $ach_info['account_type'];
        $apiKey                 = easymerchant_givewp_get_api_key();
        $apiSecretKey           = easymerchant_givewp_get_api_secret_key();
        $originalValues         = ["daily", "weekly", "monthly", "quarterly", "yearly"]; // API support these terms
        $replacementValues      = ["day", "week", "month", "quarter", "year"]; //givewp support these terms
        if (isset($data['period'])) {
            $originalValue        = $data['period'];
            $key                  = array_search($originalValue, $replacementValues);
            if ($key !== false) {
                $data['period'] = $originalValues[$key];
            }
        }
        if (give_is_test_mode()) {
            // GiveWP is in test mode
            $apiUrl = 'https://stage-api.stage-easymerchant.io/api/v1';
        } else {
            // GiveWP is not in test mode
            $apiUrl = 'https://api.easymerchant.io/api/v1';
        }

        $body = json_encode([
            'payment_mode'      => 'auth_and_capture',
            'amount'            => $data['amount'],
            'name'              => $data['name'],
            'email'             => $data['email'],
            'description'       => 'GiveWP donation',
            'currency'          => $data['currency'],
            'routing_number'    => $routingNumber,
            'account_type'      => $accountType,
            'account_number'    => $accountNumber,
            'payment_type'      => 'recurring',
            'entry_class_code'  => 'CCD',
            'interval'          => $data['period'],
            'allowed_cycles'    => 4,
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
