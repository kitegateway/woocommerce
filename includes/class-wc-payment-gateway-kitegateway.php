<?php
/**
 * Kitegateway Woocommerce.
 *
 * Provides a Kitegateway Woocommerce.
 *
 * @class       WC_Gateway_Kitegateway
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @author      tech@kitegateway.com
 * @package     WooCommerce/Classes/Payment
 */
class WC_Gateway_Kitegateway extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        // Setup general properties.
        $this->setup_properties();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings.
        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );
        $this->api_key            = $this->get_option( 'api_key' );
        $this->api_secret         = $this->get_option( 'api_secret' );
        $this->instructions       = $this->get_option( 'instructions' );
        $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
        $this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_api_kitegateway-webhook', array($this, 'handle_webhook'));
        add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

        // Customer Emails.
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

        // Enqueue scripts for admin settings.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // Check OpenSSL and send admin notice when accessing settings
        if ($this->is_accessing_settings()) {
            $this->check_openssl_for_settings();
        }
    }

    /**
     * Enqueue scripts and styles for the admin settings page.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_wc-settings' !== $hook ) {
            return;
        }

        // Only enqueue on the Kitegateway settings page.
        $screen = get_current_screen();
        if ( $screen && 'woocommerce_page_wc-settings' === $screen->id ) {
            // Verify nonce for GET request
            if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'kitegateway_admin_settings' ) ) {
                return;
            }
            if ( isset( $_GET['section'] ) && 'kitegateway' === sanitize_text_field( wp_unslash( $_GET['section'] ) ) ) {
                wp_enqueue_style(
                    'kitegateway-toggle',
                    plugins_url( '../assets/kitegateway-toggle.css', __FILE__ ),
                    array(),
                    '1.0.0'
                );
                wp_enqueue_script(
                    'kitegateway-toggle',
                    plugins_url( '../assets/kitegateway-toggle.js', __FILE__ ),
                    array( 'jquery' ),
                    '1.0.0',
                    true
                );
            }
        }
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties() {
        $this->id                 = 'kitegateway';
        $this->icon               = apply_filters( 'woocommerce_kitegateway_icon', plugins_url('../assets/mastercard.png', __FILE__ ) );
        $this->method_title       = __( 'Kitegateway Woocommerce', 'kitegateway-woocommerce' );
        $this->api_key            = __( 'Add API Key', 'kitegateway-woocommerce' );
        $this->api_secret         = __( 'Add Secret', 'kitegateway-woocommerce' );
        $this->method_description = __( 'Have your customers pay with Kitegateway Woocommerce.', 'kitegateway-woocommerce' );
        $this->has_fields         = false;
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'            => array(
                'title'       => __( 'Enable/Disable', 'kitegateway-woocommerce' ),
                'label'       => __( 'Enable Kitegateway Woocommerce', 'kitegateway-woocommerce' ),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'title'              => array(
                'title'       => __( 'Title', 'kitegateway-woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Kitegateway Mobile Payment method description that the customer will see on your checkout.', 'kitegateway-woocommerce' ),
                'default'     => __( 'Kitegateway Woocommerce', 'kitegateway-woocommerce' ),
                'desc_tip'    => true,
            ),
            'api_key'             => array(
                'title'       => __( 'API Key', 'kitegateway-woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Add your API key', 'kitegateway-woocommerce' ),
                'desc_tip'    => true,
            ),
            'api_secret'             => array(
                'title'       => __( 'API Secret', 'kitegateway-woocommerce' ),
                'type'        => 'password',
                'description' => __( 'Add your API Secret', 'kitegateway-woocommerce' ),
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'class' => 'kitegateway-api-secret',
                ),
            ),
            'description'        => array(
                'title'       => __( 'Description', 'kitegateway-woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'Kitegateway Mobile Payment method description that the customer will see on your website.', 'kitegateway-woocommerce' ),
                'default'     => __( 'Kitegateway Woocommerce before delivery.', 'kitegateway-woocommerce' ),
                'desc_tip'    => true,
            ),
            'instructions'       => array(
                'title'       => __( 'Instructions', 'kitegateway-woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'Instructions that will be added to the thank you page.', 'kitegateway-woocommerce' ),
                'default'     => __( 'Kitegateway Woocommerce before delivery.', 'kitegateway-woocommerce' ),
                'desc_tip'    => true,
            ),
            'enable_for_methods' => array(
                'title'             => __( 'Enable for shipping methods', 'kitegateway-woocommerce' ),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 400px;',
                'default'           => '',
                'description'       => __( 'If kitegateway is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'kitegateway-woocommerce' ),
                'options'           => $this->load_shipping_method_options(),
                'desc_tip'          => true,
                'custom_attributes' => array(
                    'data-placeholder' => __( 'Select shipping methods', 'kitegateway-woocommerce' ),
                ),
            ),
            'enable_for_virtual' => array(
                'title'   => __( 'Accept for virtual orders', 'kitegateway-woocommerce' ),
                'label'   => __( 'Accept kitegateway if the order is virtual', 'kitegateway-woocommerce' ),
                'type'    => 'checkbox',
                'default' => 'yes',
            ),
        );
    }

    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available() {
        $order          = null;
        $needs_shipping = false;

        // Test if shipping is needed first.
        if ( WC()->cart && WC()->cart->needs_shipping() ) {
            $needs_shipping = true;
        } elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
            $order_id = absint( get_query_var( 'order-pay' ) );
            $order    = wc_get_order( $order_id );

            // Test if order needs shipping.
            if ( 0 < count( $order->get_items() ) ) {
                foreach ( $order->get_items() as $item ) {
                    $_product = $item->get_product();
                    if ( $_product && $_product->needs_shipping() ) {
                        $needs_shipping = true;
                        break;
                    }
                }
            }
        }

        $needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

        // Virtual order, with virtual disabled.
        if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
            return false;
        }

        // Only apply if all packages are being shipped via chosen method, or order is virtual.
        if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
            $order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
            $chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

            if ( $order_shipping_items ) {
                $canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
            } else {
                $canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
            }

            if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
                return false;
            }
        }

        return parent::is_available();
    }

    /**
     * Checks to see whether or not the admin settings are being accessed by the current request.
     *
     * @return bool
     */
    private function is_accessing_settings() {
        if ( is_admin() ) {
            // phpcs:disable WordPress.Security.NonceVerification
            if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
                return false;
            }
            if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
                return false;
            }
            if ( ! isset( $_REQUEST['section'] ) || 'kitegateway' !== $_REQUEST['section'] ) {
                return false;
            }
            // phpcs:enable WordPress.Security.NonceVerification

            return true;
        }

        return false;
    }

    /**
     * Check OpenSSL availability when accessing settings and send admin notice if issues are found.
     */
    private function check_openssl_for_settings() {
        if (!extension_loaded('openssl')) {
            $message = 'The OpenSSL PHP extension is not enabled. Please contact your hosting provider to enable it for the Kitegateway Woocommerce to function correctly.';
            $this->send_admin_notice($message, true);
            return;
        }
        // Check public key file existence
        $file = plugins_url('../assets/kitegateway.public.key.pem', __FILE__);
        $keyContent = @file_get_contents($file);
        if ($keyContent === false) {
            $message = 'Unable to load public key file from ' . esc_url($file) . '. Please ensure the file exists and is readable.';
            $this->send_admin_notice($message, true);
            return;
        }

        // Validate public key
        $publicKey = openssl_get_publickey($keyContent);
        if ($publicKey === false) {
            $message = 'Invalid public key: ' . openssl_error_string() . '. Please verify the kitegateway.public.key.pem file.';
            $this->send_admin_notice($message, true);
        }
        if ($publicKey) {
            openssl_free_key($publicKey);
        }
    }

    /**
     * Send admin notice and email for critical errors.
     *
     * @param string $message Error message.
     * @param bool $send_email Whether to send an email to the admin.
     */
    private function send_admin_notice($message, $send_email = false) {
        // Store the notice in a transient to display in the admin area
        $transient_key = 'kitegateway_error_' . md5($message);
        set_transient($transient_key, $message, 3600); // Store for 1 hour

        // Hook into admin_notices to display the error
        add_action('admin_notices', function () use ($message, $transient_key) {
            if (current_user_can('manage_options') && get_transient($transient_key)) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>Kitegateway Plugin Error:</strong> <?php echo esc_html($message); ?></p>
                </div>
                <?php
                // Clear the transient after displaying
                delete_transient($transient_key);
            }
        });

        // Send email to admin if requested
        if ($send_email) {
            $to = get_option('admin_email');
            $subject = __('Kitegateway Plugin Critical Error', 'kitegateway-woocommerce');
            $body = __('An error occurred in the Kitegateway Woocommerce plugin:', 'kitegateway-woocommerce') . "\n\n" . $message . "\n\n" .
                    __('Please address this issue promptly to ensure payment processing functionality.', 'kitegateway-woocommerce');
            $headers = array('Content-Type: text/plain; charset=UTF-8');
            wp_mail($to, $subject, $body, $headers);
        }
    }

    /**
     * Loads all of the shipping method options for the enable_for_methods field.
     *
     * @return array
     */
    private function load_shipping_method_options() {
        // Since this is expensive, we only want to do it if we're actually on the settings page.
        if ( ! $this->is_accessing_settings() ) {
            return array();
        }

        $data_store = WC_Data_Store::load( 'shipping-zone' );
        $raw_zones  = $data_store->get_zones();

        foreach ( $raw_zones as $raw_zone ) {
            $zones[] = new WC_Shipping_Zone( $raw_zone );
        }

        $zones[] = new WC_Shipping_Zone( 0 );

        $options = array();
        foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

            $options[ $method->get_method_title() ] = array();

            // Translators: %1$s shipping method name.
            $options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any "%1$s" method', 'kitegateway-woocommerce' ), $method->get_method_title() );

            foreach ( $zones as $zone ) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

                    if ( $shipping_method_instance->id !== $method->id ) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf( __( '%1$s (#%2$s)', 'kitegateway-woocommerce' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf( __( '%1$s – %2$s', 'kitegateway-woocommerce' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'kitegateway-woocommerce' ), $option_instance_title );

                    $options[ $method->get_method_title() ][ $option_id ] = $option_title;
                }
            }
        }

        return $options;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
     * @return array $canonical_rate_ids    Rate IDs in a canonical format.
     */
    private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

        $canonical_rate_ids = array();

        foreach ( $order_shipping_items as $order_shipping_item ) {
            $canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
        }

        return $canonical_rate_ids;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
     * @return array $canonical_rate_ids  Rate IDs in a canonical format.
     */
    private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

        $shipping_packages  = WC()->shipping()->get_packages();
        $canonical_rate_ids = array();

        if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
            foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
                if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
                    $chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
                    $canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
                }
            }
        }

        return $canonical_rate_ids;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * @since  3.4.0
     *
     * @param array $rate_ids Rate ids to check.
     * @return boolean
     */
    private function get_matching_rates( $rate_ids ) {
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( $order->get_total() > 0 ) {
            return $this->kitegateway_payment_processing( $order );
        }
    }

    /**
     * Process payment with Kitegateway.
     *
     * @param WC_Order $order Order object.
     * @return array|string
     */
    private function kitegateway_payment_processing( $order ) {
        // Verify nonce
        if ( ! isset( $_POST['kitegateway_checkout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kitegateway_checkout_nonce'] ) ), 'kitegateway_checkout_nonce' ) ) {
            wc_add_notice( __( 'Payment error: Invalid request.', 'kitegateway-woocommerce' ), 'error' );
            return array(
                'result'   => 'failure',
                'messages' => __( 'Invalid request.', 'kitegateway-woocommerce' ),
            );
        }

        $auth_url = 'https://kitegateway.com/v1/auth/token';
        $auth_response = wp_remote_post(
            $auth_url,
            array(
                'method'      => 'POST',
                'timeout'     => 45,
                'headers' => array(
                    'Secret-Key' => $this->api_secret,
                ),
                'body'         => array(
                    'api_key'  => $this->api_key
                ),
            )
        );

        if ( is_wp_error( $auth_response ) ) {
            $auth_error_message = $auth_response->get_error_message();
            wc_get_logger()->error( 'Kitegateway Payment Error: ' . $auth_error_message, array( 'source' => 'kitegateway-woocommerce' ) );

            return "Something went wrong with auth: $auth_error_message";
        }

        if ( 200 !== wp_remote_retrieve_response_code( $auth_response ) ) {
            // $order->update_status( apply_filters( 'woocommerce_kitegateway_process_payment_order_status', $order->has_downloadable_item() ? 'wc-invoiced' : 'processing', $order ), __( 'Payments pending.', 'kitegateway-woocommerce' ) );
        }

        $auth_response_body = json_decode( wp_remote_retrieve_body( $auth_response ) );

        $auth_token = $auth_response_body->data->token;

        if ( 200 === wp_remote_retrieve_response_code( $auth_response ) ) {
            $total = intval( $order->get_total() );

            // Validate and sanitize POST data
            $card_number = isset( $_POST['card_number'] ) ? sanitize_text_field( wp_unslash( $_POST['card_number'] ) ) : '';
            $expiry_month = isset( $_POST['expiry_month'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_month'] ) ) : '';
            $expiry_year = isset( $_POST['expiry_year'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_year'] ) ) : '';
            $cvv = isset( $_POST['cvv'] ) ? sanitize_text_field( wp_unslash( $_POST['cvv'] ) ) : '';

            // Check for missing fields
            if ( empty( $card_number ) || empty( $expiry_month ) || empty( $expiry_year ) || empty( $cvv ) ) {
                wc_add_notice( __( 'Payment error: Missing card details.', 'kitegateway-woocommerce' ), 'error' );
                return array(
                    'result'   => 'failure',
                    'messages' => __( 'Missing card details.', 'kitegateway-woocommerce' ),
                );
            }

            $card_data = json_encode(array(
                'full_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'card_number' => $card_number,
                'expiry_month' => $expiry_month,
                'expiry_year' => substr($expiry_year, -2), // Convert to 2-digit year
                'cvv' => $cvv,
                'billing_address' => $order->get_billing_address_1(),
                'billing_city' => $order->get_billing_city(),
                'billing_zip' => "N/A",
                'billing_state' => $order->get_billing_state(),
                'billing_country' => $order->get_billing_country()
            ));


            $body = array(
                'currency'  => $order->get_currency(),
                'amount' => $total,
                'payment_method' => 'CARD',
                'phone_number' => $order->get_billing_phone(),
                'account_number' => $order->get_billing_phone(),
                'redirect_url' => add_query_arg( 'kitegateway_nonce', wp_create_nonce( 'kitegateway_thankyou' ), $this->get_return_url( $order ) ),
                'merchant_reference' => $order->get_id()."-".preg_replace('~\d~', '', wp_generate_uuid4(), 8),
                'narration' => 'Payment for order ' . $order->get_order_number(),
                'account_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'account_email' => $order->get_billing_email(),
                'encrypted_card' => $this->encrypt($card_data)
            );

            $collection_url = 'https://kitegateway.com/v1/collections/request-to-pay';
            $collection_response = wp_remote_post(
                $collection_url,
                array(
                    'method'      => 'POST',
                    'headers' => array(
                        'Authorization' => $auth_token
                    ),
                    'body' => $body
                )
            );

            if ( is_wp_error( $collection_response ) ) {
                $collection_error_message = $collection_response->get_error_message();
                wc_get_logger()->error( 'Kitegateway Payment Error: ' . $collection_error_message, array( 'source' => 'kitegateway-woocommerce' ) );
                return "Something went wrong with auth: $collection_error_message";
            }

            $collection_response_body = json_decode( wp_remote_retrieve_body( $collection_response ) );
            $response_code = wp_remote_retrieve_response_code( $collection_response );

            if ( $response_code !== 200 && $response_code !== 202 ) {
                $message = isset($collection_response_body->message) ? $collection_response_body->message : 'Unknown error occurred';
                $error = isset($collection_response_body->error) ? $collection_response_body->error : 'No error details provided';
                $code = isset($collection_response_body->code) ? $collection_response_body->code : 'N/A';

                wc_add_notice(
                    sprintf(
                        /* translators: %s: error message from Kitegateway API */
                        __( 'Payment failed: %s', 'kitegateway-woocommerce' ),
                        esc_html( $message )
                    ),
                    'error'
                );

                return array(
                    'result'   => 'failure',
                    'messages' => esc_html( $message ),
                    'code'     => $code,
                );
            }

            if ( $response_code === 200 || $response_code === 202 ) {
                $order->update_status( apply_filters( 'woocommerce_kitegateway_process_payment_order_status', $order->has_downloadable_item() ? 'wc-invoiced' : 'processing', $order ), __( 'Payments pending.', 'kitegateway-woocommerce' ) );

                $payment_url = $collection_response_body->data->payment_url;

                return array(
                    'result'	=> 'success',
                    'redirect'	=> $payment_url
                );
            }
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page($id) {
        if ( $this->instructions ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
        }

        // Verify nonce
        if ( ! isset( $_GET['kitegateway_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['kitegateway_nonce'] ) ), 'kitegateway_thankyou' ) ) {
            wc_get_logger()->error( 'Kitegateway Thankyou Error: Invalid nonce', array( 'source' => 'kitegateway-woocommerce' ) );
            wc_add_notice( __( 'Invalid payment confirmation data.', 'kitegateway-woocommerce' ), 'error' );
            return;
        }

        // Process query parameters
        $query_params = array(
            'transaction_id' => isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '',
            'merchant_reference' => isset( $_GET['merchant_reference'] ) ? sanitize_text_field( wp_unslash( $_GET['merchant_reference'] ) ) : '',
            'kitegateway_reference' => isset( $_GET['kitegateway_reference'] ) ? sanitize_text_field( wp_unslash( $_GET['kitegateway_reference'] ) ) : '',
            'order_id'       => isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0,
            'status'            => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
            'message'            => isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '',
            'signature'            => isset( $_GET['signature'] ) ? sanitize_text_field( wp_unslash( $_GET['signature'] ) ) : '',
        );

        // Validate query parameters
        if ( empty( $query_params['status'] ) ) {
            wc_get_logger()->debug(
                'Kitegateway Thankyou: Missing payment status in query parameters: ' . wp_json_encode( $query_params ),
                array( 'source' => 'kitegateway-woocommerce' )
            );
            return;
        }

        $order_ids = explode( "-", $query_params['merchant_reference'] );
        $order_id = $order_ids[0];

        wc_get_logger()->debug(
            'Kitegateway Thankyou: Order ID from query parameters: ' . $order_id,
            array( 'source' => 'kitegateway-woocommerce' )
        );

        wc_get_logger()->debug(
            'Kitegateway Thankyou: ID from query parameters: ' . $id,
            array( 'source' => 'kitegateway-woocommerce' )
        );

        // Verify order_id and key
        if ( $order_id != $id ) {
            wc_get_logger()->error(
                'Kitegateway Thankyou Error: Invalid order_id or key',
                array( 'source' => 'kitegateway-woocommerce' )
            );
            wc_add_notice( __( 'Invalid payment confirmation data.', 'kitegateway-woocommerce' ), 'error' );
            return;
        }

        // Process the payment status update
        $this->process_payment_status_update(
            $order_id,
            $query_params['status'],
            $query_params['kitegateway_reference'],
            $query_params['message'],
            'Redirect'
        );
    }

    /**
     * Change payment complete order status to completed for kitegateway orders.
     *
     * @since  3.1.0
     * @param  string         $status Current order status.
     * @param  int            $order_id Order ID.
     * @param  WC_Order|false $order Order object.
     * @return string
     */
    public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
        if ( $order && 'kitegateway' === $order->get_payment_method() ) {
            $status = 'completed';
        }
        return $status;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool     $sent_to_admin  Sent to admin.
     * @param bool     $plain_text Email format: plain text or HTML.
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
        }
    }

     /**
     * Handle webhook from Kitegateway.
     */
    public function handle_webhook() {
        // Get raw POST data
        $raw_data = file_get_contents('php://input');
        $payload = json_decode($raw_data, true);

         // Log webhook payload
        wc_get_logger()->debug(
            'Kitegateway Webhook Payload: ' . wp_json_encode( $payload ),
            array( 'source' => 'kitegateway-woocommerce' )
        );

        // Verify webhook signature using api_secret
        $signature = base64_decode(
            isset( $_SERVER['HTTP_KITEGATEWAY_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_KITEGATEWAY_SIGNATURE'] ) ) : ''
        );
    
        // Process payload
        $data = array(
            'transaction_id' => isset( $payload['id'] ) ? sanitize_text_field( $payload['id'] ) : '',
            'merchant_reference' => isset( $payload['merchant_reference'] ) ? sanitize_text_field( $payload['merchant_reference'] ) : '',
            'kitegateway_reference' => isset( $payload['kitegateway_reference'] ) ? sanitize_text_field( $payload['kitegateway_reference'] ) : '',
            'order_id'       => $order_id,
            'status'            => isset( $payload['transaction_status'] ) ? sanitize_text_field( $payload['transaction_status'] ) : '',
            'message'            => isset( $payload['message'] ) ? sanitize_text_field( $payload['message'] ) : ''
        );

        $file = plugins_url('../assets/kitegateway.public.key.pem', __FILE__); 
        $keyContent = file_get_contents($file);
        $publicKey = openssl_get_publickey($keyContent);
        $strPayload =  $data['transaction_id'].":".$data['merchant_reference'].":".$data['kitegateway_reference'].":".$data['status'].":".home_url('?wc-api=kitegateway-webhook');
    
        
        if(openssl_verify($strPayload, $signature, $publicKey, OPENSSL_ALGO_SHA512)) {
            wc_get_logger()->debug(
                'Kitegateway Webhook: Signature verified successfully',
                array( 'source' => 'kitegateway-woocommerce' )
            );
        } else {
            wc_get_logger()->error(
                'Kitegateway Webhook Error: Invalid signature',
                array( 'source' => 'kitegateway-woocommerce' )
            );
            wc_get_logger()->debug(
                'Kitegateway Webhook signature: ' . wp_json_encode( $signature ),
                array( 'source' => 'kitegateway-woocommerce' )
            );
            wc_get_logger()->debug(
                'Kitegateway Webhook Payload: ' . wp_json_encode( $strPayload ),
                array( 'source' => 'kitegateway-woocommerce' )
            );
            status_header(401);
            wp_send_json(array('message' => 'Invalid webhook signature'));
            exit;
        }

        $order_ids = explode("-", $payload['merchant_reference']);
        $order_id = $order_ids[0];

        // Validate payload
        if (!isset($order_id) || !isset($payload['transaction_status'])) {
            wc_get_logger()->error(
                'Kitegateway Webhook Error: Invalid payload',
                array('source' => 'kitegateway-woocommerce')
            );
            status_header(400);
            wp_send_json(array('message' => 'Invalid payload'));
            exit;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wc_get_logger()->error(
                sprintf(
                    /* translators: %1$s: source (Webhook or Redirect), %2$d: order ID */
                    __( 'Kitegateway %1$s Error: Order not found for ID %2$d', 'kitegateway-woocommerce' ),
                    'Webhook',
                    $order_id
                ),
                array('source' => 'kitegateway-woocommerce')
            );
            status_header(400);
            wp_send_json(array('message' => 'Invalid order'));
            exit;
        }

        // Process the payment status update
         $this->process_payment_status_update(
            $order_id,
            $data['status'],
            $data['kitegateway_reference'],
            $data['message'],
            'Webhook'
        );

        // Respond with success
        status_header(200);
        wp_send_json(array('message' => 'success'));
        exit;
    }

    /**
     * Process payment status update for webhook or redirect.
     *
     * @param int $order_id Order ID.
     * @param string $payment_status Payment status.
     * @param string $kitegateway_reference Transaction Reference.
     * @param string $message Error message or additional info.
     * @param string $source Source of the update (Webhook or Redirect).
     */
    private function process_payment_status_update($order_id, $payment_status, $kitegateway_reference, $message, $source) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_get_logger()->error(
                sprintf(
                    /* translators: %1$s: source (Webhook or Redirect), %2$d: order ID */
                    __( 'Kitegateway %1$s Error: Order not found for ID %2$d', 'kitegateway-woocommerce' ),
                    $source,
                    $order_id
                ),
                array('source' => 'kitegateway-woocommerce')
            );
            return;
        }

        // Update order status based on payment status
        switch (strtolower($payment_status)) {
            case 'COMPLETED':
            case 'COMPLETE':
            case 'success':
                if ($order->get_status() !== 'completed') {
                    $order->payment_complete($kitegateway_reference);
                    $order->add_order_note(
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect), %2$s: transaction reference */
                            __( 'Payment completed via Kitegateway (%1$s). Transaction Reference: %2$s', 'kitegateway-woocommerce' ),
                            $source,
                            $kitegateway_reference
                        )
                    );
                    wc_get_logger()->debug(
                        sprintf(
                            /* translators: %1$s: source, %2$d: order ID, %3$s: transaction reference */
                            __( 'Kitegateway %1$s: Order %2$d marked as completed. Transaction Reference: %3$s', 'kitegateway-woocommerce' ),
                            $source,
                            $order_id,
                            $kitegateway_reference
                        ),
                        array('source' => 'kitegateway-woocommerce')
                    );
                }
                break;
            case 'failed':
            case 'FAILED':
            case 'FAIL':
                if (!$order->has_status(array('failed', 'cancelled'))) {
                    $order->update_status(
                        'failed',
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect), %2$s: transaction reference, %3$s: error message */
                            __( 'Payment failed via Kitegateway (%1$s). Transaction Reference: %2$s. Message: %3$s', 'kitegateway-woocommerce' ),
                            $source,
                            $kitegateway_reference,
                            $message
                        )
                    );
                    $order->add_order_note(
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect), %2$s: transaction reference, %3$s: error message */
                            __( 'Payment failed via Kitegateway (%1$s). Transaction Reference: %2$s. Message: %3$s', 'kitegateway-woocommerce' ),
                            $source,
                            $kitegateway_reference,
                            $message
                        )
                    );
                    wc_get_logger()->debug(
                        sprintf(
                            /* translators: %1$s: source, %2$d: order ID */
                            __( 'Kitegateway %1$s: Order %2$d marked as failed', 'kitegateway-woocommerce' ),
                            $source,
                            $order_id
                        ),
                        array('source' => 'kitegateway-woocommerce')
                    );
                }
                break;
            case 'REFUNDED':
            case 'REFUND':
                if ($order->get_status() !== 'refunded') {
                    $order->update_status(
                        'refunded',
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect) */
                            __( 'Payment refunded via Kitegateway (%1$s).', 'kitegateway-woocommerce' ),
                            $source
                        )
                    );
                    $order->add_order_note(
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect), %2$s: transaction reference */
                            __( 'Payment refunded via Kitegateway (%1$s). Transaction Reference: %2$s', 'kitegateway-woocommerce' ),
                            $source,
                            $kitegateway_reference
                        )
                    );
                    wc_get_logger()->debug(
                        sprintf(
                            /* translators: %1$s: source, %2$d: order ID, %3$s: transaction reference */
                            __( 'Kitegateway %1$s: Order %2$d marked as refunded. Transaction Reference: %3$s', 'kitegateway-woocommerce' ),
                            $source,
                            $order_id,
                            $kitegateway_reference
                        ),
                        array('source' => 'kitegateway-woocommerce')
                    );
                }
                break;
            case 'PENDING':
                if (!$order->has_status(array('pending', 'on-hold'))) {
                    $order->update_status(
                        'on-hold',
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect), %2$s: transaction reference */
                            __( 'Payment pending via Kitegateway (%1$s). Transaction Reference: %2$s', 'kitegateway-woocommerce' ),
                            $source,
                            $kitegateway_reference
                        )
                    );
                    $order->add_order_note(
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect) */
                            __( 'Payment pending via Kitegateway (%1$s).', 'kitegateway-woocommerce' ),
                            $source
                        )
                    );
                    wc_get_logger()->debug(
                        sprintf(
                            /* translators: %1$s: source, %2$d: order ID */
                            __( 'Kitegateway %1$s: Order %2$d marked as on-hold', 'kitegateway-woocommerce' ),
                            $source,
                            $order_id
                        ),
                        array('source' => 'kitegateway-woocommerce')
                    );
                }
                break;
            case 'PROCESSING':
                if (!$order->has_status(array('pending', 'on-hold'))) {
                    $order->update_status(
                        'on-hold',
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect), %2$s: transaction reference */
                            __( 'Payment pending via Kitegateway (%1$s). Transaction Reference: %2$s', 'kitegateway-woocommerce' ),
                            $source,
                            $kitegateway_reference
                        )
                    );
                    $order->add_order_note(
                        sprintf(
                            /* translators: %1$s: source (Webhook or Redirect) */
                            __( 'Payment pending via Kitegateway (%1$s).', 'kitegateway-woocommerce' ),
                            $source
                        )
                    );
                    wc_get_logger()->debug(
                        sprintf(
                            /* translators: %1$s: source, %2$d: order ID */
                            __( 'Kitegateway %1$s: Order %2$d marked as on-hold', 'kitegateway-woocommerce' ),
                            $source,
                            $order_id
                        ),
                        array('source' => 'kitegateway-woocommerce')
                    );
                }
                break;    
            default:
                wc_get_logger()->error(
                    sprintf(
                        /* translators: %1$s: payment status, %2$d: order ID, %3$s: source */
                        __( 'Kitegateway %3$s Error: Unknown payment status: %1$s for order %2$d', 'kitegateway-woocommerce' ),
                        $payment_status,
                        $order_id,
                        $source
                    ),
                    array('source' => 'kitegateway-woocommerce')
                );
                if ($source === 'Redirect') {
                    wc_add_notice(
                        sprintf(
                            /* translators: %s: payment status */
                            __( 'Unknown payment status: %s', 'kitegateway-woocommerce' ),
                            esc_html( $payment_status )
                        ),
                        'error'
                    );
                }
                return;
        }
    }

    /**
     * Encrypt card data using RSA public key with OAEP padding or hybrid encryption for large data.
     *
     * @param string $card_string JSON-encoded card data.
     * @return string|null Base64-encoded encrypted data or null on failure.
     */
    public function encrypt($card_string) {
        // Initialize return value
        $ret = null;

        // Check if OpenSSL extension is loaded
        if (!extension_loaded('openssl')) {
            return $ret;
        }

        // Load public key
        $file = plugin_dir_path(__FILE__) . '../assets/kitegateway.public.key.pem';
        $keyContent = @file_get_contents($file);
        if ($keyContent === false) {
            return $ret;
        }

        // Validate public key
        $publicKey = openssl_get_publickey($keyContent);
        if ($publicKey === false) {
            return $ret;
        }

        // Get key details to verify size and log modulus
        $keyDetails = openssl_pkey_get_details($publicKey);
        if ($keyDetails === false || !isset($keyDetails['bits']) || $keyDetails['bits'] !== 2048) {
            openssl_free_key($publicKey);
            return $ret;
        }
        // Check data size (max 214 bytes for 2048-bit key with OAEP padding)
        $dataSize = strlen($card_string);
        if ($dataSize > 214) {
            // Hybrid encryption for large data
            $aes_key = openssl_random_pseudo_bytes(32); // 256-bit AES key
            $iv = openssl_random_pseudo_bytes(16); // 128-bit IV
            $encrypted_data = openssl_encrypt($card_string, 'AES-256-CBC', $aes_key, 0, $iv);
            if ($encrypted_data === false) {
                openssl_free_key($publicKey);
                return $ret;
            }

            if (!openssl_public_encrypt($aes_key, $encrypted_aes_key, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
                openssl_free_key($publicKey);
                return $ret;
            }

            openssl_free_key($publicKey);
            $ret = base64_encode($encrypted_data . '::' . base64_encode($iv) . '::' . base64_encode($encrypted_aes_key));
            if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $ret)) {
                return null;
            }
            return $ret;
        }

        // Standard RSA encryption with OAEP padding
        if (!openssl_public_encrypt($card_string, $result, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            openssl_free_key($publicKey);
            return $ret;
        }

        // Free the key resource
        openssl_free_key($publicKey);

        // Base64 encode the result and validate
        $ret = base64_encode($result);
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $ret)) {
            return null;
        }
        return $ret;
    }
}