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

    woocommerce_form_field(
        'expiry_month',
        array(
            'type'        => 'text',
            'label'       => __( 'Expiry Month', 'kitegateway-for-woocommerce' ),
            'class'       => array( 'form-row', 'form-row-wide' ),
            'required'    => true,
            'maxlength'   => 2,
            'custom_attributes' => array(
                'autocomplete' => 'cc-exp-month',
                'pattern'      => '(0[1-9]|1[0-2])',
                'placeholder'  => 'MM',
            ),
        )
    );

    woocommerce_form_field(
        'expiry_year',
        array(
            'type'        => 'text',
            'label'       => __( 'Expiry Year', 'kitegateway-for-woocommerce' ),
            'class'       => array( 'form-row', 'form-row-wide' ),
            'required'    => true,
            'maxlength'   => 4,
            'custom_attributes' => array(
                'autocomplete' => 'cc-exp-year',
                'pattern'      => '[0-9]{4}',
                'placeholder'  => 'YYYY',
            ),
        )
    );

    echo '</div>';

    $description .= ob_get_clean();

    return $description;
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
