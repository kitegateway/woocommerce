<?php
/**
 * Add custom fields to the Kitegateway checkout description and handle validation.
 *
 * @package Kitegateway Woocommerce
 */
add_filter( 'woocommerce_gateway_description', 'kitegateway_description_fields', 20, 2 );
add_action( 'woocommerce_checkout_process', 'kitegateway_description_fields_validation' );
add_action( 'woocommerce_checkout_update_order_meta', 'checkout_update_order_meta', 10, 1 );

/**
 * Add custom fields to the Kitegateway payment gateway description.
 *
 * @param string $description The default description.
 * @param string $payment_id The payment gateway ID.
 * @return string Updated description with fields.
 */
function kitegateway_description_fields( $description, $payment_id ) {
    if ( 'kitegateway' !== $payment_id ) {
        return $description;
    }

    ob_start();

    echo '<div style="display: block; width:300px; height:auto;">';
    
    wp_nonce_field( 'kitegateway_checkout_nonce', 'kitegateway_checkout_nonce' );

    woocommerce_form_field(
        'card_number',
        array(
            'type'        => 'text',
            'label'       => __( 'Card Number', 'kitegateway-for-woocommerce' ),
            'class'       => array( 'form-row', 'form-row-wide' ),
            'required'    => true,
            'maxlength'   => 19,
            'custom_attributes' => array(
                'autocomplete' => 'cc-number',
                'pattern'      => '[0-9]{13,19}',
            ),
        )
    );

    woocommerce_form_field(
        'cvv',
        array(
            'type'        => 'text',
            'label'       => __( 'CVV', 'kitegateway-for-woocommerce' ),
            'class'       => array( 'form-row', 'form-row-wide', 'kitegateway-cvv' ),
            'required'    => true,
            'maxlength'   => 4,
            'custom_attributes' => array(
                'autocomplete' => 'cc-csc',
                'pattern'      => '[0-9]{3,4}',
            ),
        )
    );

    // A single combined expiry field (MM / YYYY) is used instead of two
    // separate Month/Year inputs. Browsers and password managers key their
    // saved-card autofill on a single field with autocomplete="cc-exp";
    // splitting it into cc-exp-month/cc-exp-year defeats that detection on
    // most browsers and also caused the visible placeholder ("MM"/"YYYY")
    // to visually clash with typed or autofilled values. expiry_month and
    // expiry_year are still submitted as hidden fields, kept in sync by JS,
    // so the existing server-side processing is unchanged.
    woocommerce_form_field(
        'kitegateway_expiry',
        array(
            'type'        => 'text',
            'label'       => __( 'Expiry Date', 'kitegateway-for-woocommerce' ),
            'class'       => array( 'form-row', 'form-row-wide' ),
            'required'    => true,
            'maxlength'   => 7,
            'custom_attributes' => array(
                'autocomplete' => 'cc-exp',
                'inputmode'    => 'numeric',
                'placeholder'  => 'MM / YYYY',
                'id'           => 'kitegateway_expiry',
            ),
        )
    );

    echo '<input type="hidden" name="expiry_month" id="kitegateway_expiry_month" value="" />';
    echo '<input type="hidden" name="expiry_year" id="kitegateway_expiry_year" value="" />';

    echo '</div>';

    $description .= ob_get_clean();
    $description .= kitegateway_expiry_split_script();

    return $description;
}

/**
 * Inline script that keeps the combined MM / YYYY expiry field in sync
 * with the hidden expiry_month / expiry_year fields the backend expects.
 * Listens for both manual typing and browser/password-manager autofill
 * (which sets the value programmatically and only fires an `input`
 * event, not `keyup`), so saved-card autofill is picked up correctly.
 *
 * @return string Script tag markup.
 */
function kitegateway_expiry_split_script() {
    ob_start();
    ?>
    <script>
    ( function () {
        function splitExpiry() {
            var field = document.getElementById( 'kitegateway_expiry' );
            var monthField = document.getElementById( 'kitegateway_expiry_month' );
            var yearField = document.getElementById( 'kitegateway_expiry_year' );
            if ( ! field || ! monthField || ! yearField ) {
                return;
            }

            var raw = field.value.replace( /[^0-9]/g, '' );
            var month = raw.slice( 0, 2 );
            var year = raw.slice( 2, 6 );
            if ( year.length === 2 ) {
                // Some autofill implementations provide a 2-digit year.
                year = ( year < '70' ? '20' : '19' ) + year;
            }

            monthField.value = month;
            yearField.value = year;
        }

        function bind() {
            var field = document.getElementById( 'kitegateway_expiry' );
            if ( ! field ) {
                return;
            }
            // 'input' covers typing; also covers most browser autofill.
            field.addEventListener( 'input', splitExpiry );
            field.addEventListener( 'change', splitExpiry );
            // Autofill sometimes lands before listeners attach; re-check shortly after paint.
            setTimeout( splitExpiry, 300 );
            setTimeout( splitExpiry, 1000 );
            splitExpiry();
        }

        if ( document.readyState === 'loading' ) {
            document.addEventListener( 'DOMContentLoaded', bind );
        } else {
            bind();
        }
        // Re-bind after WooCommerce re-renders the checkout (e.g. on payment method switch).
        document.body.addEventListener( 'updated_checkout', bind );

        // Guard against a submit landing before the input/change listeners
        // or timers have a chance to run (e.g. immediately after autofill).
        document.body.addEventListener( 'click', function ( event ) {
            if ( event.target && event.target.closest && event.target.closest( '#place_order' ) ) {
                splitExpiry();
            }
        }, true );
        var checkoutForm = document.querySelector( 'form.woocommerce-checkout' );
        if ( checkoutForm ) {
            checkoutForm.addEventListener( 'submit', splitExpiry, true );
        }
    } )();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Validate Kitegateway checkout fields.
 */
function kitegateway_description_fields_validation() {
    if ( ! isset( $_POST['kitegateway_checkout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kitegateway_checkout_nonce'] ) ), 'kitegateway_checkout_nonce' ) ) {
        wc_add_notice( __( 'Security check failed. Please try again.', 'kitegateway-for-woocommerce' ), 'error' );
        return;
    }

    if ( isset( $_POST['payment_method'] ) && 'kitegateway' === sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) ) {
        // Sanitize inputs
        $card_number = isset( $_POST['card_number'] ) ? sanitize_text_field( wp_unslash( $_POST['card_number'] ) ) : '';
        $cvv = isset( $_POST['cvv'] ) ? sanitize_text_field( wp_unslash( $_POST['cvv'] ) ) : '';
        $expiry_month = isset( $_POST['expiry_month'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_month'] ) ) : '';
        $expiry_year = isset( $_POST['expiry_year'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_year'] ) ) : '';

        // Validate card number
        if ( empty( trim( $card_number ) ) ) {
            wc_add_notice( __( 'Please enter a valid card number.', 'kitegateway-for-woocommerce' ), 'error' );
        } elseif ( ! preg_match( '/^[0-9]{13,19}$/', $card_number ) ) {
            wc_add_notice( __( 'Card number must be 13 to 19 digits.', 'kitegateway-for-woocommerce' ), 'error' );
        }

        // Validate CVV
        if ( empty( trim( $cvv ) ) ) {
            wc_add_notice( __( 'Please enter a valid CVV.', 'kitegateway-for-woocommerce' ), 'error' );
        } elseif ( ! preg_match( '/^[0-9]{3,4}$/', $cvv ) ) {
            wc_add_notice( __( 'CVV must be 3 or 4 digits.', 'kitegateway-for-woocommerce' ), 'error' );
        }

        // Validate expiry month
        if ( empty( trim( $expiry_month ) ) ) {
            wc_add_notice( __( 'Please enter a valid expiry month.', 'kitegateway-for-woocommerce' ), 'error' );
        } elseif ( ! preg_match( '/^(0[1-9]|1[0-2])$/', $expiry_month ) ) {
            wc_add_notice( __( 'Expiry month must be between 01 and 12.', 'kitegateway-for-woocommerce' ), 'error' );
        }

        // Validate expiry year
        if ( empty( trim( $expiry_year ) ) ) {
            wc_add_notice( __( 'Please enter a valid expiry year.', 'kitegateway-for-woocommerce' ), 'error' );
        } elseif ( ! preg_match( '/^[0-9]{4}$/', $expiry_year ) ) {
            wc_add_notice( __( 'Expiry year must be a 4-digit number.', 'kitegateway-for-woocommerce' ), 'error' );
        } else {
            $current_year = (int) gmdate( 'Y' );
            $expiry_year = (int) $expiry_year;
            if ( $expiry_year < $current_year ) {
                wc_add_notice( __( 'Expiry year cannot be in the past.', 'kitegateway-for-woocommerce' ), 'error' );
            }
        }
    }
}

/**
 * Update order meta with sanitized card details.
 *
 * @param int $order_id The order ID.
 */
function checkout_update_order_meta( $order_id ) {
    // Verify nonce
    if ( ! isset( $_POST['kitegateway_checkout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kitegateway_checkout_nonce'] ) ), 'kitegateway_checkout_nonce' ) ) {
        return;
    }

    // Only process for Kitegateway payments
    if ( isset( $_POST['payment_method'] ) && 'kitegateway' === sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) ) {
        $card_number = isset( $_POST['card_number'] ) ? sanitize_text_field( wp_unslash( $_POST['card_number'] ) ) : '';
        $cvv = isset( $_POST['cvv'] ) ? sanitize_text_field( wp_unslash( $_POST['cvv'] ) ) : '';

        if ( ! empty( trim( $card_number ) ) ) {
            update_post_meta( $order_id, '_kitegateway_card_number', $card_number );
        }

        if ( ! empty( trim( $cvv ) ) ) {
            update_post_meta( $order_id, '_kitegateway_cvv', $cvv );
        }
    }
}
