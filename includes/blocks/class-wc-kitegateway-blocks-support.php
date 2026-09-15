<?php
/**
 * WooCommerce Blocks integration for Kitegateway.
 *
 * Registers Kitegateway as a payment method compatible with the
 * WooCommerce Checkout block (Cart & Checkout Blocks).
 *
 * @package Kitegateway\WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class WC_Kitegateway_Blocks_Support.
 *
 * Bridges the classic WC_Gateway_Kitegateway gateway into the
 * block-based Checkout so it is no longer restricted to the
 * legacy [woocommerce_checkout] shortcode checkout.
 */
final class WC_Kitegateway_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id (matches WC_Gateway_Kitegateway::$id).
	 *
	 * @var string
	 */
	protected $name = 'kitegateway';

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Kitegateway
	 */
	private $gateway;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_kitegateway_settings', array() );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
	}

	/**
	 * Returns whether this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be used for this payment method
	 * in the frontend context (customer facing checkout).
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = plugin_dir_path( KITEGATEWAY_PLUGIN_FILE ) . 'assets/blocks/kitegateway-blocks.asset.php';
		$version      = KITEGATEWAY_PLUGIN_VERSION;
		$dependencies = array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' );

		if ( file_exists( $asset_path ) ) {
			$asset = require $asset_path;
			$version      = isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
		}

		wp_register_script(
			'wc-kitegateway-blocks-integration',
			plugins_url( 'assets/blocks/kitegateway-blocks.js', KITEGATEWAY_PLUGIN_FILE ),
			$dependencies,
			$version,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-kitegateway-blocks-integration', 'kitegateway-for-woocommerce' );
		}

		return array( 'wc-kitegateway-blocks-integration' );
	}

	/**
	 * Returns an array of key => value pairs of data made available to the
	 * payment methods script.
	 *
	 * Note: the classic gateway's "description" option (exposed via
	 * WC_Gateway_Kitegateway::get_description()) contains raw HTML
	 * (card fields rendered via woocommerce_form_field(), injected
	 * through the woocommerce_gateway_description filter in
	 * includes/kitegateway-checkout-description-fields.php). That
	 * markup is meant for the classic, server-rendered checkout only.
	 * We deliberately do NOT pass it through here, since the block
	 * checkout renders this data as plain text/React children, not
	 * raw HTML. Instead the block-side fields are rendered as real
	 * React inputs (see assets/blocks/kitegateway-blocks.js) and we
	 * only expose the plain merchant-configured description plus a
	 * fresh nonce the fields can submit as payment_data.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'         => $this->gateway ? $this->gateway->get_title() : __( 'Kitegateway Woocommerce', 'kitegateway-for-woocommerce' ),
			'description'   => $this->gateway ? $this->gateway->get_option( 'description' ) : '',
			'icon'          => $this->gateway ? $this->gateway->icon : '',
			'supports'      => $this->gateway ? array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ) : array(),
			'checkoutNonce' => wp_create_nonce( 'kitegateway_checkout_nonce' ),
		);
	}
}
