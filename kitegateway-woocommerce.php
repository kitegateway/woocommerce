<?php
/**
 * Plugin Name: Kitegateway for WooCommerce
 * Plugin URI: https://kitegateway.com
 * Author: Kitegateway Developers
 * Author URI: https://github.com/kitegateway/woocommerce
 * Description: A fast and secure gateway for accepting digital payments in WooCommerce.
 * Version: 1.1.0
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

define( 'KITEGATEWAY_PLUGIN_FILE', __FILE__ );
define( 'KITEGATEWAY_PLUGIN_VERSION', '1.1.0' );

add_action( 'plugins_loaded', 'kitegateway_payment_init', 11 );
add_filter( 'woocommerce_currencies', 'kitegateway_add_ugx_currencies' );
add_filter( 'woocommerce_currency_symbol', 'kitegateway_add_ugx_currencies_symbol', 10, 2 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_kitegateway_payment_gateway' );

/**
 * Declare compatibility with WooCommerce features (HPOS, Blocks).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Register the Kitegateway payment method with the WooCommerce
 * Checkout block (Cart & Checkout Blocks), so it is no longer
 * limited to the legacy [woocommerce_checkout] shortcode checkout.
 */
add_action(
	'woocommerce_blocks_payment_method_type_registration',
	function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/blocks/class-wc-kitegateway-blocks-support.php';
		$payment_method_registry->register( new WC_Kitegateway_Blocks_Support() );
	}
);

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
