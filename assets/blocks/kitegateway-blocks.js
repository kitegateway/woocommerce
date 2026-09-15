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
		var inputRef = wp.element.useRef( null );

		// Keep a live ref to the latest props so the native listener below
		// never needs to re-bind on every keystroke (re-binding on every
		// value change was fighting normal typing and made the field look
		// like it never updated, i.e. the label/placeholder never seemed
		// to clear).
		var propsRef = wp.element.useRef( props );
		propsRef.current = props;

		// Native-level fallback for browser/password-manager autofill only.
		// Some browsers write the autofilled value straight into the DOM
		// without ever going through React's synthetic onChange. This effect
		// runs once per field (mount/unmount only) and always reads the
		// current value/onChange via propsRef, so it never interferes with
		// normal typing, which already goes through the input's own
		// onChange handler below.
		useEffect(
			function () {
				var node = inputRef.current;
				if ( ! node ) {
					return;
				}

				function syncFromNode( node ) {
					if ( node.value !== propsRef.current.value ) {
						propsRef.current.onChange( node.value );
					}
				}

				// 'change' (not 'input') is the event most browsers fire for
				// autofill; normal typing already flows through React's
				// onChange, so we do not also listen for 'input' here.
				function handleNativeChange( event ) {
					syncFromNode( event.target );
				}

				node.addEventListener( 'change', handleNativeChange );
				// Autofill can land slightly after mount with no event at all
				// in some browsers; re-check shortly after the field appears.
				var recheck = setTimeout( function () {
					syncFromNode( node );
				}, 500 );

				return function () {
					node.removeEventListener( 'change', handleNativeChange );
					clearTimeout( recheck );
				};
			},
			[]
		);

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
				ref: inputRef,
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

		var expiryState = useState( '' );
		var expiry = expiryState[ 0 ];
		var setExpiry = expiryState[ 1 ];

		var cvvState = useState( '' );
		var cvv = cvvState[ 0 ];
		var setCvv = cvvState[ 1 ];

		/**
		 * Splits the combined "MM / YYYY" (or any digits typed/autofilled
		 * into it) expiry value into month/year, tolerating a 2-digit year.
		 */
		function splitExpiry( value ) {
			var digits = ( value || '' ).replace( /[^0-9]/g, '' );
			var month = digits.slice( 0, 2 );
			var year = digits.slice( 2, 6 );
			if ( year.length === 2 ) {
				year = ( year < '70' ? '20' : '19' ) + year;
			}
			return { month: month, year: year };
		}

		useEffect(
			function () {
				var unsubscribe = onPaymentSetup( function () {
					var parsedExpiry = splitExpiry( expiry );
					var isCardValid = /^[0-9]{13,19}$/.test( cardNumber );
					var isMonthValid = /^(0[1-9]|1[0-2])$/.test( parsedExpiry.month );
					var isYearValid = /^[0-9]{4}$/.test( parsedExpiry.year );
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
								expiry_month: parsedExpiry.month,
								expiry_year: parsedExpiry.year,
								cvv: cvv,
							},
						},
					};
				} );

				return unsubscribe;
			},
			[ onPaymentSetup, cardNumber, expiry, cvv, emitResponse.responseTypes.SUCCESS, emitResponse.responseTypes.ERROR ]
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
					// Single combined expiry field: browsers/password managers key
					// saved-card autofill detection on autoComplete="cc-exp"; a
					// split month/year pair is not reliably recognised and also
					// caused the MM/YYYY placeholders to clash with entered values.
					createElement( Field, {
						id: 'kitegateway-expiry',
						label: __( 'Expiry Date', 'kitegateway-for-woocommerce' ),
						value: expiry,
						onChange: setExpiry,
						autoComplete: 'cc-exp',
						inputMode: 'numeric',
						maxLength: 7,
						placeholder: 'MM / YYYY',
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
