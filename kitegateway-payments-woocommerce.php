<?php
/**
 * Plugin Name: Kitegateway Woocommerce 
 * Plugin URI: https://kitegateway.com
 * Author: Kitegateway Developers
 * Author URI: https://kitegateway.com
 * Description: A Fast & Secure Gateway for acceptance of digital payments.
 * Version: 0.1.0
 * License: GPL2
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: kitegateway-payments-woo
 * 
 * Class WC_Gateway_Kitegateway file.
 *
 * @package WooCommerce\Kitegateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'kitegateway_payment_init', 11 );
add_filter( 'woocommerce_currencies', 'kitegateway_add_ugx_currencies' );
add_filter( 'woocommerce_currency_symbol', 'kitegateway_add_ugx_currencies_symbol', 10, 2 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_kitegateway_payment_gateway');

function kitegateway_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-payment-gateway-kitegateway.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/kitegateway-order-statuses.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/kitegateway-checkout-description-fields.php';
	}
}

function add_to_woo_kitegateway_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Kitegateway';
    return $gateways;
}

function kitegateway_add_ugx_currencies( $currencies ) {
	$currencies['UGX'] = __( 'Ugandan Shillings', 'kitegateway-payments-woo' );
	return $currencies;
}

function kitegateway_add_ugx_currencies_symbol( $currency_symbol, $currency ) {
	switch ( $currency ) {
		case 'UGX': 
			$currency_symbol = 'UGX'; 
		break;
	}
	return $currency_symbol;
}