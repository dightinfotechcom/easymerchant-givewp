<?php

/**
 * Easymerchant For Give Scripts
 *
 * @package    Give
 * @subpackage Easymerchant
 * @copyright  Copyright (c) 2019, GiveWP
 * @license    https://opensource.org/licenses/gpl-license GNU Public License
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Load Frontend javascript
 * @since 2.5.0
 * @return void
 */
function easymerchant_givewp_frontend_scripts()
{
	// Get publishable key.
	$publishable_key = easymerchant_givewp_get_publishable_key();
	if (!$publishable_key) {
		return;
	}
	// Set vars for AJAX.
	$easymerchant_vars = apply_filters(
		'easymerchant_givewp_global_parameters',
		[
			'zero_based_currency'          => give_is_zero_based_currency(),
			'zero_based_currencies_list'   => give_get_zero_based_currencies(),
			'sitename'                     => give_get_option('easymerchant_checkout_name'),
			'checkoutBtnTitle'             => esc_html__('Donate', 'easymerchant-givewp'),
			'publishable_key'              => $publishable_key,
			'give_version'                 => get_option('give_version'),
			'donate_button_text'           => esc_html__('Donate Now', 'easymerchant-givewp'),
			'float_labels'                 => give_is_float_labels_enabled(
				[
					'form_id' => get_the_ID(),
				]
			),
			'base_country'                 => give_get_option('base_country'),
			'preferred_locale'             => easymerchant_givewp_get_preferred_locale(),
		]
	);

	// Load third-party js when required gateways are active.
	if (apply_filters('easymerchant_givewp_js_loading_conditions', easymerchant_givewp_is_any_payment_method_active())) {
		$easymerchant_footer = give_is_setting_enabled(give_get_option('easymerchant_footer')) ? true : false;
		$easymerchant_footer = true;
		wp_register_script('easymerchant-js', 'https://api.easymerchant.io/assets/checkout/easyMerchant.js', [], GIVE_VERSION, $easymerchant_footer);
		wp_enqueue_script('easymerchant-js');
		wp_localize_script('easymerchant-js', 'easymerchant_givewp_vars', $easymerchant_vars);
	}

	wp_register_script('give-easymerchant-onpage-js', plugin_dir_url(__DIR__) . 'assets/js/easymerchant-givewp.js', ['easymerchant-js'], GIVE_VERSION);
	wp_enqueue_script('give-easymerchant-onpage-js');
}

add_action('wp_enqueue_scripts', 'easymerchant_givewp_frontend_scripts');

/**
 * WooCommerce checkout compatibility.
 *
 * @since 1.4.3
 *
 * @param bool $ret JS compatibility status.
 *
 * @return bool
 */
function easymerchant_givewp_woo_script_compatibility($ret)
{

	if (
		function_exists('is_checkout')
		&& is_checkout()
	) {
		return false;
	}

	return $ret;
}

add_filter('easymerchant_givewp_js_loading_conditions', 'easymerchant_givewp_woo_script_compatibility', 10, 1);


/**
 * EDD checkout compatibility.
 *
 * @since 1.4.6
 *
 * @param bool $ret JS compatibility status.
 *
 * @return bool
 */
function easymerchant_givewp_edd_script_compatibility($ret)
{

	if (
		function_exists('edd_is_checkout')
		&& edd_is_checkout()
	) {
		return false;
	}

	return $ret;
}

add_filter('easymerchant_givewp_js_loading_conditions', 'easymerchant_givewp_edd_script_compatibility', 10, 1);
