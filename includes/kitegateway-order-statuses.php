<?php
/**
 * Add new Invoiced status for WooCommerce
 *
 * @package Kitegateway Woocommerce
 */

add_action( 'init', 'register_my_new_order_statuses' );

/**
 * Register custom 'Invoiced' order status.
 */
function register_my_new_order_statuses() {
    register_post_status( 'wc-invoiced', array(
        'label'                     => _x( 'Invoiced', 'Order status', 'kitegateway-for-woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        // translators: %s is the number of orders in the Invoiced status.
        'label_count'               => _n_noop( 'Invoiced <span class="count">(%s)</span>', 'Invoiced <span class="count">(%s)</span>', 'kitegateway-for-woocommerce' ),
    ) );
}

add_filter( 'wc_order_statuses', 'my_new_wc_order_statuses' );

/**
 * Add 'Invoiced' status to WooCommerce order statuses.
 *
 * @param array $order_statuses Existing order statuses.
 * @return array Updated order statuses.
 */
function my_new_wc_order_statuses( $order_statuses ) {
    $order_statuses['wc-invoiced'] = _x( 'Invoiced', 'Order status', 'kitegateway-for-woocommerce' );
    return $order_statuses;
}

/**
 * Add bulk action to change order status to 'Invoiced'.
 */
function kitegateway_add_bulk_invoice_order_status() {
    global $post_type;

    if ( $post_type == 'shop_order' ) {
        ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery('<option>').val('mark_invoiced').text('<?php esc_html_e( 'Change status to invoiced', 'kitegateway-for-woocommerce' ); ?>').appendTo("select[name='action']");
                    jQuery('<option>').val('mark_invoiced').text('<?php esc_html_e( 'Change status to invoiced', 'kitegateway-for-woocommerce' ); ?>').appendTo("select[name='action2']");   
                });
            </script>
        <?php
    }
}

add_action( 'admin_footer', 'kitegateway_add_bulk_invoice_order_status' );