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

/**
 * @inheritDoc
 */
class EasyMerchantGatewaySubscriptionModule extends SubscriptionModule
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
            $hostedCheckout = give_clean($gatewayData['easymerchant-hosted-checkout']);

            // first check if hosted checkout is enabled
            if ($hostedCheckout){
                $chargeId = give_clean($gatewayData['easymerchant-charge-id']);
                // TODO: this would need to be implemented from the hosted checkout
                // some gateways will use the initial chargeID and continue creating the subscription on the server.
                // not sure how easy merchant handles this.
                $subscriptionId = give_clean($gatewayData['easymerchant-subscription-id']);

                if (empty($chargeId)) {
                    throw new PaymentGatewayException(__('EasyMerchant Charge ID is required.', 'example-give' ) );
                }

                if (empty($subscriptionId)) {
                    throw new PaymentGatewayException(__('EasyMerchant Subscription ID is required.', 'example-give' ) );
                }

                return new SubscriptionComplete($chargeId, $subscriptionId);
            }

            // if not using hosted checkout make the request with the gateway
            $response = $this->makePaymentRequest([
                'amount' => $donation->amount->formatToDecimal(),
                'name' => trim("$donation->firstName $donation->lastName"),
                'email' => $donation->email,
                'currency' => $subscription->amount->getCurrency(),
                'period' => $subscription->period->getValue()
            ]);

            if (empty($response['charge_id'])) {
                throw new PaymentGatewayException(__('EasyMerchant Charge ID is required.', 'example-give' ) );
            }

            if (empty($response['subscription_id'])) {
                throw new PaymentGatewayException(__('EasyMerchant Subscription ID is required.', 'example-give' ) );
            }

            return new SubscriptionComplete($response['charge_id'], $response['subscription_id']);
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
     *
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(Subscription $subscription)
    {
        try {
            // Step 1: cancel the subscription with your gateway.

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
                'payment_mode'   => 'auth_and_capture',
                'amount'         => $data['amount'],
                'name'           => $data['name'],
                'email'          => $data['email'],
                'description'    => 'test',
                'start_date'     => $currentDate,
                'currency'       => $data['currency'],
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

        return $response;
    }
}