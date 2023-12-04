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
 */
error_log( 'Easymerchant: test' );
if( ! function_exists('em_log') ) {
	function em_log($message='') {
		if(is_array($message) || is_object($message)) {
			$message = print_r($message, true);
		}
		error_log( "\n" . "Easymerchant: " . $message, 3, "/Users/rajakannan/Herd/wpplayground/wp-content/debug.log" );
	}
}
require_once 'easymerchant-givewp/easymerchant-givewp.php';