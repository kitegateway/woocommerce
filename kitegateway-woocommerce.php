<?php
/**
 * Plugin Name: Kitegateway for WooCommerce
 * Plugin URI: https://kitegateway.com
 * Author: Kitegateway Developers
 * Author URI: https://github.com/kitegateway/woocommerce
 * Description: A fast and secure gateway for accepting digital payments in WooCommerce.
 * Version: 1.0.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kitegateway-for-woocommerce
 *
 * Class WC_Gateway_Kitegateway file.
 *
 * @package Kitegateway\WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

add_action( 'plugins_loaded', 'kitegateway_payment_init', 11 );
add_filter( 'woocommerce_currencies', 'kitegateway_add_ugx_currencies' );
add_filter( 'woocommerce_currency_symbol', 'kitegateway_add_ugx_currencies_symbol', 10, 2 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_kitegateway_payment_gateway' );

/**
 * Initialize the Kitegateway payment gateway.
 */
function kitegateway_payment_init() {
    if ( class_exists( 'WC_Payment_Gateway' ) ) {
        require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-payment-gateway-kitegateway.php';
        require_once plugin_dir_path( __FILE__ ) . '/includes/kitegateway-order-statuses.php';
        require_once plugin_dir_path( __FILE__ ) . '/includes/kitegateway-checkout-description-fields.php';
    }
}

/**
 * Add Kitegateway gateway to WooCommerce payment gateways.
 *
 * @param array $gateways List of payment gateways.
 * @return array Updated list of payment gateways.
 */
function add_to_woo_kitegateway_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Kitegateway';
    return $gateways;
}

/**
 * Add UGX currency to WooCommerce.
 *
 * @param array $currencies List of currencies.
 * @return array Updated list of currencies.
 */
function kitegateway_add_ugx_currencies( $currencies ) {
    $currencies['UGX'] = __( 'Ugandan Shillings', 'kitegateway-for-woocommerce' );
    return $currencies;
}

/**
 * Set UGX currency symbol.
 *
 * @param string $currency_symbol The currency symbol.
 * @param string $currency The currency code.
 * @return string Updated currency symbol.
 */
function kitegateway_add_ugx_currencies_symbol( $currency_symbol, $currency ) {
    switch ( $currency ) {
        case 'UGX':
            $currency_symbol = 'UGX';
            break;
    }
    return $currency_symbol;
}
