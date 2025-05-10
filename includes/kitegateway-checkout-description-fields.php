<?php

add_filter( 'woocommerce_gateway_description', 'kitegateway_description_fields', 20, 2 );
add_action( 'woocommerce_checkout_process', 'kitegateway_description_fields_validation' );
add_action( 'woocommerce_checkout_update_order_meta', 'checkout_update_order_meta', 10, 1 );
// add_action( 'woocommerce_admin_order_data_after_billing_address', 'order_data_after_billing_address', 10, 1 );
// add_action( 'woocommerce_order_item_meta_end', 'order_item_meta_end', 10, 3 );

function kitegateway_description_fields( $description, $payment_id ) {

    if ( 'kitegateway' !== $payment_id ) {
        return $description;
    }

    ob_start();

    echo '<div style="display: block; width:300px; height:auto;">';
    // echo '<img src="' . plugins_url('../assets/icon.png', __FILE__ ) . '">';


    woocommerce_form_field(
        'card_number',
        array(
            'type' => 'text',
            'label' =>__( 'Card Number', 'kitegateway-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );

    woocommerce_form_field(
        'cvv',
        array(
            'type' => 'text',
            'label' =>__( 'CVV', 'kitegateway-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );

    woocommerce_form_field(
        'expiry_month',
        array(
            'type' => 'text',
            'label' =>__( 'Expiry Month', 'kitegateway-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );

    woocommerce_form_field(
        'expiry_year',
        array(
            'type' => 'text',
            'label' =>__( 'Expiry Year', 'kitegateway-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );

    echo '</div>';

    $description .= ob_get_clean();

    return $description;
}

function kitegateway_description_fields_validation() {
    if( 'kitegateway' === $_POST['payment_method'] && ! isset( $_POST['card_number'] )  || empty( $_POST['card_number'] ) ) {
        wc_add_notice( 'Please enter a number that is to be billed', 'error' );
    }
}

function checkout_update_order_meta( $order_id ) {
    if( isset( $_POST['card_number'] ) || ! empty( $_POST['card_number'] ) ) {
        update_post_meta( $order_id, 'card_number', $_POST['card_number'] );
    }

    if( isset( $_POST['cvv'] ) || ! empty( $_POST['cvv'] ) ) {
        update_post_meta( $order_id, 'card_number', $_POST['card_number'] );
    }
}

/*function order_data_after_billing_address( $order ) {
    echo '<p><strong>' . __( 'Card Number:', 'kitegateway-payments-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'card_number', true ) . '</p>';
}*/

/*function order_item_meta_end( $item_id, $item, $order ) {
    echo '<p><strong>' . __( 'Card Number:', 'kitegateway-payments-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'card_number', true ) . '</p>';
}*/
