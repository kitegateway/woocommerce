/**
 * Kitegateway payment method registration for the WooCommerce
 * Checkout block (Cart & Checkout Blocks).
 *
 * This is the minimal client-side registration required for a
 * classic WC_Payment_Gateway to appear as a selectable payment
 * method inside the block-based checkout.
 */
( function ( wp, wc ) {
	'use strict';

	var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting = wc.wcSettings.getSetting;
	var createElement = wp.element.createElement;
	var decodeEntities = wp.htmlEntities.decodeEntities;
	var __ = wp.i18n.__;

	var settings = getSetting( 'kitegateway_data', {} );

	var defaultLabel = __( 'Kitegateway Woocommerce', 'kitegateway-for-woocommerce' );
	var label = decodeEntities( settings.title || defaultLabel );

	var Content = function () {
		return createElement(
			'div',
			null,
			decodeEntities( settings.description || '' )
		);
	};

	var Label = function () {
		return createElement(
			'span',
			{ style: { display: 'flex', alignItems: 'center' } },
			label,
			settings.icon
				? createElement( 'img', {
						src: settings.icon,
						alt: label,
						style: { marginLeft: '8px', height: '20px' },
				  } )
				: null
		);
	};

	registerPaymentMethod( {
		name: 'kitegateway',
		label: createElement( Label ),
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )( window.wp, window.wc );
