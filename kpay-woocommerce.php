<?php
/*
Plugin Name: Kpay Payment Gateway
Plugin URI: https://kpay.esicia.rw
Author: Esicia
Author URI: https://esicia.rw
Description: Local Payments Gateway for Rwanda.
Version: 0.1.0
License: GPL2
License URL: http://www.gnu.org/licenses/gpl-2.0.txt
text-domain: kpay-payments-woo

Class WC_Gateway_Kpay file.

@package WooCommerce\Kpay
*/
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
 */
function declare_cart_checkout_blocks_compatibility()
{
	// Check if the required class exists
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		// Declare compatibility for 'cart_checkout_blocks'
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

add_action('plugins_loaded', 'kpay_payment_init', 11);
add_filter('woocommerce_currencies', 'esicia_add_rwf_currencies');
add_filter('woocommerce_currency_symbol', 'esicia_add_rwf_currencies_symbol', 10, 2);
add_filter('woocommerce_payment_gateways', 'add_to_woo_kpay_payment_gateway');

function kpay_payment_init()
{
	if (class_exists('WC_Payment_Gateway')) {
		require_once plugin_dir_path(__FILE__) . '/includes/class-wc-payment-gateway-kpay.php';
		require_once plugin_dir_path(__FILE__) . '/includes/kpay-order-statuses.php';
		require_once plugin_dir_path(__FILE__) . '/includes/kpay-checkout-description-fields.php';
	}
}

function add_to_woo_kpay_payment_gateway($gateways)
{
	$gateways[] = 'WC_Gateway_Kpay';
	return $gateways;
}

function esicia_add_rwf_currencies($currencies)
{
	$currencies['RWF'] = __('Rwandan Francs', 'woocommerce');
	return $currencies;
}

function esicia_add_rwf_currencies_symbol($currency_symbol, $currency)
{
	switch ($currency) {
		case 'RF':
			$currency_symbol = 'RWF';
			break;
	}
	return $currency_symbol;
}

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action('woocommerce_blocks_loaded', 'kpay_register_order_approval_payment_method_type');
/**
 * Custom function to register a payment method type
 */
function kpay_register_order_approval_payment_method_type()
{
	// Check if the required class exists
	if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		return;
	}
	// Include the custom Blocks Checkout class
	require_once plugin_dir_path(__FILE__) . 'includes/class-kpay-woocommerce-block-checkout.php';
	// Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
			$payment_method_registry->register(new WC_Kpay_Blocks);
		}
	);
}
