<?php

/**
 * Plugin Name:  		EasyMerchant GiveWP
 * Plugin URI:        	https://easymerchant.io/
 * Description:        	Adds the Easymerchant.io payment gateway to the available GiveWP payment methods.
 * Version:            	2.0.3
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
    define('EASYMERCHANT_FOR_GIVE_VERSION', '2.0.3');
}


easymerchant_givewp_includes();
add_action('init', 'webhook_file_callback');


function webhook_file_callback()
{
    if (isset($_GET['give-listener']) && $_GET['give-listener'] == 'easymerchant') {
        $rawRequestBody = file_get_contents("php://input");

        require_once 'easymerchant-webhook-handler.php';
        $webhook_handler = new EasyMerchantWebhookHandler(site_url() . '?give-listener=easymerchant');
        $webhook_handler->handle_webhook_request($rawRequestBody);
    }
}
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
                'name' => __('Publishable Key', 'easymerchant-givewp'),
                'desc' => __('Enter your Publishable Key, found in your easymerchant Dashboard.', 'easymerchant-givewp'),
                'id'   => 'easymerchant_publishable_key',
                'type' => 'text',
            );
            $settings[] = array(
                'name' => __('API Key', 'easymerchant-givewp'),
                'desc' => __('Enter your API Key, found in your easymerchant Dashboard.', 'easymerchant-givewp'),
                'id'   => 'easymerchant_api_key',
                'type' => 'text',
            );
            $settings[] = array(
                'name' => __('API Secret Key', 'easymerchant-givewp'),
                'desc' => __('Enter your API Secret Key, found in your easymerchant Dashboard.', 'easymerchant-givewp'),
                'id'   => 'easymerchant_api_secret_key',
                'type' => 'text',
            );
            $settings[] = array(
                'name' => __('Webhook Url', 'easymerchant-givewp'),
                'desc' => __('Copy this webhook Url and paste in your Easymerchant Dashboard', 'easymerchant-givewp'),
                'id'   => 'easymerchant_webhook_url',
                'type' => 'text',
                'default' => site_url('?give-listener=easymerchant', 'easymerchant-givewp'),
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
    }
    return $settings;
}
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
}

function easymerchant_givewp_include_admin_files()
{
    require_once plugin_dir_path(__FILE__) . 'includes/admin/admin-helpers.php';
}


function give_get_donation_easymerchant_cc_info()
{
    // Sanitize the values submitted with donation form.
    $post_data = give_clean($_POST); // WPCS: input var ok, sanitization ok, CSRF ok.
    $cc_info                        = [];
    $cc_info['card_name']           = !empty($post_data['card_name']) ? $post_data['card_name'] : '';
    $cc_info['card_number_easy']    = !empty($post_data['card_number_easy']) ? $post_data['card_number_easy'] : '';
    $cc_info['card_number_easy']    = str_replace(' ', '', $cc_info['card_number_easy']);
    $cc_info['card_cvc']            = !empty($post_data['card_cvc']) ? $post_data['card_cvc'] : '';
    $cc_info['card_exp_month']      = !empty($post_data['card_exp_month_0']) ? $post_data['card_exp_month_0'] : '';
    $cc_info['card_exp_year']       = !empty($post_data['card_exp_year']) ? $post_data['card_exp_year'] : '';
    // Return cc info.
    return $cc_info;
}

function give_get_donation_easymerchant_ach_info()
{
    $post_data  = give_clean($_POST);
    $ach_info   =  [];
    $ach_info['account_number'] = !empty($post_data['account_number']) ? $post_data['account_number'] : '';
    $ach_info['routing_number'] = !empty($post_data['routing_number']) ? $post_data['routing_number'] : '';
    $ach_info['account_type']   = !empty($post_data['account_type']) ? $post_data['account_type'] : '';
    return $ach_info;
}

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
                'description' => __('Please update the <strong>GiveWP Recurring Donations</strong> add-on to version 1.7+ to be compatible with the latest version of the EasyMerchant payment gateway.', 'easymerchant-givewp'),
            ));
        } elseif (
            version_compare(EASYMERCHANT_FOR_GIVE_VERSION, '2.1', '>=') &&
            version_compare(GIVE_RECURRING_VERSION, '1.8', '<')
        ) {
            Give()->notices->register_notice(array(
                'id'          => 'easymerchant-for-give-require-minimum-recurring-version',
                'type'        => 'error',
                'dismissible' => false,
                'description' => __('Please update the <strong>GiveWP Recurring Donations</strong> add-on to version 1.8+ to be compatible with the latest version of the EasyMerchant payment gateway.', 'easymerchant-givewp'),
            ));
        }
    }
}
add_action('admin_notices', 'easymerchant_givewp_display_minimum_recurring_version_notice');

// Register the gateway with the givewp gateway api
add_action('givewp_register_payment_gateway', static function ($paymentGatewayRegister) {
    include 'class-easymerchant-gateway.php';
    $paymentGatewayRegister->registerGateway(EasyMerchantGateway::class);
    include 'class-easymerchant-ach.php';
    $paymentGatewayRegister->registerGateway(EasyMerchantACH::class);
});

// Register the gateways subscription module
add_filter(
    "givewp_gateway_easymerchant-gateway_subscription_module",
    static function () {
        include 'class-easymerchant-gateway-subscription-module.php';

        return EasyMerchantGatewaySubscriptionModule::class;
    }
);

add_filter(
    "givewp_gateway_easymerchant-ach_subscription_module",
    static function () {
        include 'class-easymerchant-ach-subscription.php';
        return EasyMerchantACHGatewaySubscriptionModule::class;
    }
);
