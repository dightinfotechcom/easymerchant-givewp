<?php

/**
 * Plugin Name:  		EasyMerchant GiveWP
 * Plugin URI:        	https://easymerchant.io/
 * Description:        	Adds the Easymerchant.io payment gateway to the available GiveWP payment methods.
 * Version:            	1.0.2
 * Requires at least:   4.9
 * Requires PHP:        5.6
 * Author:            	EasyMerchant
 * Author URI:        	https://easymerchant.io/
 * Text Domain:        	easymerchant-givewp
 *
 * @package             Give
 * 
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants.
if (!defined('EASYMERCHANT_FOR_GIVE_VERSION')) {
    define('EASYMERCHANT_FOR_GIVE_VERSION', '1.0.2');
}

easymerchant_givewp_includes();

/**
 * Register Section for Payment Gateway Settings.
 * @param array $sections List of payment gateway sections.
 * @since 1.0.0
 * @return array
 */

function easymerchant_givewp_register_payment_gateway_sections($sections)
{
    // `easymerchant-settings` is the name/slug of the payment gateway section.
    $sections['easymerchant-settings'] = __('EasyMerchant', 'easymerchant-givewp');
    return $sections;
}
add_filter('give_get_sections_gateways', 'easymerchant_givewp_register_payment_gateway_sections');

/**
 * Register Admin Settings.
 * @param array $settings List of admin settings.
 * @since 1.0.0
 * @return array
 */

function easymerchant_givewp_register_payment_gateway_setting_fields($settings)
{
    switch (give_get_current_setting_section()) {
        case 'easymerchant-settings':
            $settings = array(
                array(
                    'id'   => 'give_title_easymerchant',
                    'type' => 'title',
                ),
            );
            $settings[] = array(
                'name' => __('Publishable Key', 'easymerchant-for-give'),
                'desc' => __('Enter your Publishable Key, found in your easymerchant Dashboard.', 'easymerchant-givewp'),
                'id'   => 'easymerchant_publishable_key',
                'type' => 'text',
            );
            $settings[] = [
                'name'          => esc_html__('Checkout Heading', 'easymerchant-for-give'),
                'desc'          => esc_html__('This is the main heading within the modal checkout. Typically, this is the name of your organization, cause, or website.', 'easymerchant-for-give'),
                'id'            => 'easymerchant_checkout_name',
                'wrapper_class' => 'easymerchant-checkout-field ',
                'default'       => get_bloginfo('name'),
                'type'          => 'text',
            ];
            $settings[] = array(
                'id'   => 'give_title_easymerchant',
                'type' => 'sectionend',
            );
            break;
    } // End switch().
    return $settings;
}
// change the easymerchant_for_give prefix to avoid collisions with other functions.
add_filter('give_get_settings_gateways', 'easymerchant_givewp_register_payment_gateway_setting_fields');

function easymerchant_givewp_includes()
{
    easymerchant_givewp_include_admin_files();
    // Load files which are necessary for front as well as admin end.
    require_once plugin_dir_path(__FILE__) . 'includes/easymerchant-givewp-helpers.php';
    // Bailout, if any of the Easymerchant gateways are not active.
    if (!easymerchant_givewp_supported_payment_methods()) {
        return;
    }
    easymerchant_givewp_include_frontend_files();
}

function easymerchant_givewp_include_admin_files()
{
    require_once plugin_dir_path(__FILE__) . 'includes/admin/admin-helpers.php';
}


function easymerchant_givewp_include_frontend_files()
{
    // Load files which are necessary for front as well as admin end.
    require_once plugin_dir_path(__FILE__) . 'includes/payment-methods/class-easymerchant-givewp-checkout.php';
    // require_once plugin_dir_path(__FILE__) . 'includes/easymerchant-for-give-scripts.php';

}

function give_get_donation_easymerchant_cc_info()
{
    // Sanitize the values submitted with donation form.
    $post_data = give_clean($_POST); // WPCS: input var ok, sanitization ok, CSRF ok.
    $cc_info                        = [];
    $cc_info['card_name']           = !empty($post_data['card_name']) ? $post_data['card_name'] : '';
    $cc_info['card_number_easy']    = !empty($post_data['card_number_easy']) ? $post_data['card_number_easy'] : $post_data['card_number'];
    $cc_info['card_number_easy']    = str_replace(' ', '', $cc_info['card_number_easy']);
    $cc_info['card_cvc']            = !empty($post_data['card_cvc']) ? $post_data['card_cvc'] : '';
    $cc_info['card_exp_month']      = !empty($post_data['card_exp_month']) ? $post_data['card_exp_month'] : '';
    $cc_info['card_exp_year']       = !empty($post_data['card_exp_year']) ? $post_data['card_exp_year'] : '';
    // Return cc info.
    return $cc_info;
}

/**
 * Process Easymerchant checkout submission.
 * @param array $posted_data List of posted data.
 * @since  1.0.0
 * @access public
 * @return void
 */

function easymerchant_givewp_process_easymerchant_donation($posted_data)
{
    // Make sure we don't have any left over errors present.
    give_clear_errors();
    // Any errors?
    $errors = give_get_errors();
    // No errors, proceed.
    if (!$errors) {
        $form_id         = intval($posted_data['post_data']['give-form-id']);
        $price_id        = !empty($posted_data['post_data']['give-price-id']) ? $posted_data['post_data']['give-price-id'] : 0;
        $donation_amount = !empty($posted_data['price']) ? $posted_data['price'] : 0;

        // if Recurring checkbox is selected.
        if (isset($_POST['give-recurring-period'])) {


            $posted_data['period']    = give_get_meta($form_id, '_give_period', true);
            $posted_data['times']     = give_get_meta($form_id, '_give_times', true);
            $posted_data['frequency'] = give_get_meta($form_id, '_give_period_interval', true, 1);

            global $wpdb;

            $cc_info                  = give_get_donation_easymerchant_cc_info();

            // Use the card details in this function calling from "give_get_donation_easymerchant_cc_info" function.
            $cc_holder              = $cc_info['card_name'];
            $cc_number              = $cc_info['card_number_easy'];
            $cardNumber             = str_replace(' ', '', $cc_number);
            $month                  = $cc_info['card_exp_month'];
            $year                   = $cc_info['card_exp_year'];
            $cc_cvc                 = $cc_info['card_cvc'];


            $originalValues         = ["day", "week", "month", "quarter", "year"]; // API support these terms
            $replacementValues      = ["daily", "weekly", "monthly", "quarterly", "yearly"]; //givewp support these terms
            if (isset($posted_data['period'])) {
                $originalValue        = $posted_data['period'];
                $key                  = array_search($originalValue, $originalValues);
                if ($key !== false) {
                    $posted_data['period'] = $replacementValues[$key];
                }
            }
            $currentDate       = date("m/d/Y");

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
                    'amount'         => $donation_amount,
                    'name'           => $posted_data['user_info']['first_name'],
                    'email'          => $posted_data['user_email'],
                    'description'    => 'test',
                    'start_date'     => $currentDate,
                    'currency'       => give_get_currency($form_id),
                    'card_number'    => $cardNumber,
                    'exp_month'      => $month,
                    'exp_year'       => $year,
                    'cvc'            => $cc_cvc,
                    'cardholder_name' => $cc_holder,
                    'payment_type'   => 'recurring',
                    'interval'       => $posted_data['period'],
                    'allowed_cycles' => 12,
                ]),
                CURLOPT_HTTPHEADER => array(
                    'X-Api-Key: doggiedaycareKxYeMhRl',
                    'X-Api-Secret: doggiedaycareIBagbKnt',
                    'Content-Type: application/json',
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            echo $response;

            $customer       = json_decode($response, true);
            $subscriptionId = $customer->subscription_id;

            $donation_recurring_data = array(
                'price'           => $donation_amount,
                'give_form_title' => $posted_data['post_data']['give-form-title'],
                'give_form_id'    => $form_id,
                'give_price_id'   => $price_id,
                'date'            => $posted_data['date'],
                'user_email'      => $posted_data['user_email'],
                'purchase_key'    => $posted_data['purchase_key'],
                'currency'        => give_get_currency($form_id),
                'user_info'       => $posted_data['user_info'],
                'status'          => "pending",
                'gateway'         => 'easymerchant',
            );
            // Record the pending donation.
            $donation_recurring_id = give_insert_payment($donation_recurring_data);
        } else {
            require_once plugin_dir_path(__FILE__) . 'includes/easymerchant-for-give-scripts.php';
            $status = 'complete';

            // Setup the payment details for one time.
            $donation_data = array(
                'price'           => $donation_amount,
                'give_form_title' => $posted_data['post_data']['give-form-title'],
                'give_form_id'    => $form_id,
                'give_price_id'   => $price_id,
                'date'            => $posted_data['date'],
                'user_email'      => $posted_data['user_email'],
                'purchase_key'    => $posted_data['purchase_key'],
                'currency'        => give_get_currency($form_id),
                'user_info'       => $posted_data['user_info'],
                'status'          => $status,
                'gateway'         => 'easymerchant',
            );
        }
        $donation_id = give_insert_payment($donation_data);

        $scurrentDate          = date("Y-m-d H:i:s");
        switch ($originalValue) {
            case 'day':
                $expiryDate       = date("Y-m-d H:i:s", strtotime("+1 day", strtotime($scurrentDate)));
                break;
            case 'week':
                $expiryDate       = date("Y-m-d H:i:s", strtotime("+1 week", strtotime($scurrentDate)));
                break;
            case 'month':
                $expiryDate       = date("Y-m-d H:i:s", strtotime("+1 month", strtotime($scurrentDate)));
                break;
            case 'quarter':
                $expiryDate       = date("Y-m-d H:i:s", strtotime("+3 months", strtotime($scurrentDate)));
                break;
            case 'year':
                $expiryDate       = date("Y-m-d H:i:s", strtotime("+1 year", strtotime($scurrentDate)));
            default:
                $expiryDate       = date("Y-m-d H:i:s", strtotime("+1 month", strtotime($scurrentDate)));
                break;
        }


        if ($donation_recurring_id) {
            // Save Subscription into Database
            $table_name_insert    = 'wp_give_subscriptions';
            $wpdb->insert(
                $table_name_insert,
                array(
                    "customer_id"           => give_get_meta($donation_recurring_id, '_give_payment_donor_id', true),
                    "period"                => $originalValue,
                    "frequency"             => "1",
                    "initial_amount"        => $donation_amount,
                    "recurring_amount"      => $donation_amount,
                    "recurring_fee_amount"  => "0",
                    "bill_times"            => '0',
                    "transaction_id"        => "",
                    "parent_payment_id"     => $donation_recurring_id,
                    "payment_mode"          => "test",
                    "product_id"            => $form_id,
                    "created"               => $scurrentDate,
                    "expiration"            => $expiryDate,
                    "status"                => "pending",
                    "profile_id"            => $subscriptionId
                ),
                array(
                    '%s',  '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                )
            );
            give_send_to_success_page();
        }

        if (!$donation_id) {
            // Record Gateway Error as Pending Donation in Give is not created.
            give_record_gateway_error(
                __('Easymerchant Error', 'easymerchant-for-give'),
                sprintf(
                    __('Unable to create a pending donation with Give.', 'easymerchant-for-give')
                )
            );
            // Send user back to checkout.
            give_send_back_to_checkout('?payment-mode=easymerchant');
            return;
        }
        // Do the actual payment processing using the custom payment gateway API. To access the GiveWP settings, use give_get_option() 
        // as a reference, this pulls the API key entered above: give_get_option('easymerchant_for_give_easymerchant_api_key')
        give_send_to_success_page();

        // }
    } else {
        // Send user back to checkout.
        give_send_back_to_checkout('?payment-mode=easymerchant');
    } // End if().

}
// change the easymerchant_for_give prefix to avoid collisions with other functions.
add_action('give_gateway_easymerchant', 'easymerchant_givewp_process_easymerchant_donation');

function easymerchant_givewp_display_minimum_recurring_version_notice()
{
    if (
        defined('GIVE_RECURRING_PLUGIN_BASENAME') &&
        is_plugin_active(GIVE_RECURRING_PLUGIN_BASENAME)
    ) {
        if (
            version_compare(EASYMERCHANT_FOR_GIVE_VERSION, '2.0.6', '>=') &&
            version_compare(EASYMERCHANT_FOR_GIVE_VERSION, '2.1', '<') &&
            version_compare(GIVE_RECURRING_VERSION, '1.7', '<')
        ) {
            Give()->notices->register_notice(array(
                'id'          => 'easymerchant-for-give-require-minimum-recurring-version',
                'type'        => 'error',
                'dismissible' => false,
                'description' => __('Please update the <strong>GiveWP Recurring Donations</strong> add-on to version 1.7+ to be compatible with the latest version of the EasyMerchant payment gateway.', 'easymerchant-for-give'),
            ));
        } elseif (
            version_compare(EASYMERCHANT_FOR_GIVE_VERSION, '2.1', '>=') &&
            version_compare(GIVE_RECURRING_VERSION, '1.8', '<')
        ) {
            Give()->notices->register_notice(array(
                'id'          => 'easymerchant-for-give-require-minimum-recurring-version',
                'type'        => 'error',
                'dismissible' => false,
                'description' => __('Please update the <strong>GiveWP Recurring Donations</strong> add-on to version 1.8+ to be compatible with the latest version of the EasyMerchant payment gateway.', 'easymerchant-for-give'),
            ));
        }
    }
}
add_action('admin_notices', 'easymerchant_givewp_display_minimum_recurring_version_notice');

// Register the gateway with the givewp gateway api
add_action('givewp_register_payment_gateway', static function ($paymentGatewayRegister) {
    include 'class-easymerchant-gateway.php';
    $paymentGatewayRegister->registerGateway(EasyMerchantGateway::class);
});

// Register the gateways subscription module
 add_filter("givewp_gateway_easymerchant-gateway_subscription_module", static function () {
        include 'class-easymerchant-gateway-subscription-module.php';

        return EasyMerchantGatewaySubscriptionModule::class;
    }
);
