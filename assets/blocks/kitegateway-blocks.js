/**
 * Kitegateway payment method registration for the WooCommerce
 * Checkout block (Cart & Checkout Blocks).
 *
 * Renders real, controlled card fields (card number, expiry month,
 * expiry year, CVV) as React inputs and submits them, plus the
 * gateway's server-issued checkout nonce, as payment_data when the
 * checkout is placed. This mirrors the fields the classic gateway
 * renders via woocommerce_form_field() in
 * includes/kitegateway-checkout-description-fields.php, and relies
 * on WooCommerce's own legacy-gateway bridge (Store API's
 * Legacy::process_legacy_payment(), which copies payment_data into
 * $_POST before calling process_payment()) to reach the existing
 * WC_Gateway_Kitegateway::process_payment() unchanged.
 */
( function ( wp, wc ) {
	'use strict';

	var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting = wc.wcSettings.getSetting;
	var createElement = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var decodeEntities = wp.htmlEntities.decodeEntities;
	var __ = wp.i18n.__;

	var settings = getSetting( 'kitegateway_data', {} );

	var defaultLabel = __( 'Kitegateway Woocommerce', 'kitegateway-for-woocommerce' );
	var label = decodeEntities( settings.title || defaultLabel );

	/**
	 * Simple labelled text input, styled to match the surrounding
	 * checkout form rows.
	 */
	function Field( props ) {
		return createElement(
			'div',
			{ className: 'wc-block-components-text-input kitegateway-field', style: { marginBottom: '8px' } },
			createElement(
				'label',
				{ htmlFor: props.id, style: { display: 'block', marginBottom: '4px' } },
				props.label,
				props.required
					? createElement( 'span', { style: { color: '#cc1818' } }, ' *' )
					: null
			),
			createElement( 'input', {
				type: 'text',
				inputMode: props.inputMode || 'text',
				id: props.id,
				name: props.id,
				value: props.value,
				autoComplete: props.autoComplete,
				maxLength: props.maxLength,
				placeholder: props.placeholder,
				required: !! props.required,
				style: { width: '100%', padding: '8px', boxSizing: 'border-box' },
				onChange: function ( event ) {
					props.onChange( event.target.value );
				},
			} )
		);
	}

	/**
	 * Payment method content: the actual card fields shown when
	 * Kitegateway is the selected/active payment method.
	 */
	function KitegatewayContent( props ) {
		var eventRegistration = props.eventRegistration;
		var emitResponse = props.emitResponse;
		var onPaymentSetup = eventRegistration.onPaymentSetup;

		var cardState = useState( '' );
		var cardNumber = cardState[ 0 ];
		var setCardNumber = cardState[ 1 ];

		var monthState = useState( '' );
		var expiryMonth = monthState[ 0 ];
		var setExpiryMonth = monthState[ 1 ];

		var yearState = useState( '' );
		var expiryYear = yearState[ 0 ];
		var setExpiryYear = yearState[ 1 ];

		var cvvState = useState( '' );
		var cvv = cvvState[ 0 ];
		var setCvv = cvvState[ 1 ];

		useEffect(
			function () {
				var unsubscribe = onPaymentSetup( function () {
					var isCardValid = /^[0-9]{13,19}$/.test( cardNumber );
					var isMonthValid = /^(0[1-9]|1[0-2])$/.test( expiryMonth );
					var isYearValid = /^[0-9]{4}$/.test( expiryYear );
					var isCvvValid = /^[0-9]{3,4}$/.test( cvv );

					if ( ! isCardValid || ! isMonthValid || ! isYearValid || ! isCvvValid ) {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: __(
								'Please enter a valid card number, expiry date, and CVV.',
								'kitegateway-for-woocommerce'
							),
						};
					}

					return {
						type: emitResponse.responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								payment_method: 'kitegateway',
								kitegateway_checkout_nonce: settings.checkoutNonce || '',
								card_number: cardNumber,
								expiry_month: expiryMonth,
								expiry_year: expiryYear,
								cvv: cvv,
							},
						},
					};
				} );

				return unsubscribe;
			},
			[ onPaymentSetup, cardNumber, expiryMonth, expiryYear, cvv, emitResponse.responseTypes.SUCCESS, emitResponse.responseTypes.ERROR ]
		);

		return createElement(
			'div',
			{ className: 'kitegateway-blocks-fields' },
			settings.description
				? createElement( 'p', null, decodeEntities( settings.description ) )
				: null,
			createElement( Field, {
				id: 'kitegateway-card-number',
				label: __( 'Card Number', 'kitegateway-for-woocommerce' ),
				value: cardNumber,
				onChange: setCardNumber,
				autoComplete: 'cc-number',
				inputMode: 'numeric',
				maxLength: 19,
				required: true,
			} ),
			createElement(
				'div',
				{ style: { display: 'flex', gap: '8px' } },
				createElement(
					'div',
					{ style: { flex: 1 } },
					createElement( Field, {
						id: 'kitegateway-expiry-month',
						label: __( 'Expiry Month', 'kitegateway-for-woocommerce' ),
						value: expiryMonth,
						onChange: setExpiryMonth,
						autoComplete: 'cc-exp-month',
						inputMode: 'numeric',
						maxLength: 2,
						placeholder: 'MM',
						required: true,
					} )
				),
				createElement(
					'div',
					{ style: { flex: 1 } },
					createElement( Field, {
						id: 'kitegateway-expiry-year',
						label: __( 'Expiry Year', 'kitegateway-for-woocommerce' ),
						value: expiryYear,
						onChange: setExpiryYear,
						autoComplete: 'cc-exp-year',
						inputMode: 'numeric',
						maxLength: 4,
						placeholder: 'YYYY',
						required: true,
					} )
				),
				createElement(
					'div',
					{ style: { flex: 1 } },
					createElement( Field, {
						id: 'kitegateway-cvv',
						label: __( 'CVV', 'kitegateway-for-woocommerce' ),
						value: cvv,
						onChange: setCvv,
						autoComplete: 'cc-csc',
						inputMode: 'numeric',
						maxLength: 4,
						required: true,
					} )
				)
			)
		);
	}

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
		content: createElement( KitegatewayContent ),
		edit: createElement( KitegatewayContent ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )( window.wp, window.wc );
