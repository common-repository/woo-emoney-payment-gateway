<?php
/**
 * Plugin Name: WooCommerce eMoney Payment Gateway
 * Plugin URI:  https://emoney.ge
 * Description: Accept payments in your WooCommerce shop using eMoney merchant gateway.
 * Version:     1.0.3
 * Author:      eMoney Georgia
 * Author URI:  https://blog.mycoins.ge
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: woo-emoney
 * WC requires at least: 3.0.0
 * WC tested up to: 4.5.2

 * Intellectual Property rights, and copyright, reserved by eMoney Georgia as allowed by law include,
 * but are not limited to, the working concept, function, and behavior of this software,
 * the logical code structure and expression as written.
 *
 * @package     WooCommerce eMoney Payment Gateway
 * @author      eMoney Georgia https://emoney.ge/
 * @copyright   Copyright (c) eMoney Georgia (support@emoney.ge)
 * @since       1.0.0
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require 'EmoneyMerchantProcessor.php';

/**
 * Create settings link in the plugins list page
 * 
 * @param array $links Existing links.
 */
function woo_emoney_settings( $links ) {
    $settings_link = '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=emoney' ).'">Settings</a>';
    $links = array_merge( [$settings_link], $links );
    return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'woo_emoney_settings' );

/**
 * Register the payment gateway.
 *
 * @since 1.0.0
 * @param array $gateways Payment gateways.
 */
function add_woo_gateway_emoney_class( $gateways ) {
    $gateways[] = 'WC_Gateway_eMoney';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'add_woo_gateway_emoney_class' );

/**
 * Initialize class on plugins_loaded.
 */
function init_woo_gateway_emoney() {

    /**
     * eMoney payment gateway class
     *
     * @class   WC_Gateway_eMoney
     * @extends WC_Payment_Gateway
     */
    class WC_Gateway_eMoney extends WC_Payment_Gateway {

        /**
         * @var boolean Enabled or disable logging
         * @static
         */
        public static $log_enabled = false;

        /**
         * @var boolean WC_Logger instance
         * @static
         */
        public static $log = false;

        /**
         * Constructor.
         */
        function __construct() {
            $this->id                 = 'emoney';
			$this->icon               = plugin_dir_url(__FILE__) . 'images/emoney-logo.png';
            $this->has_fields         = false;
            $this->method_title       = __( 'eMoney', 'woo-emoney' );
            $this->method_description = __( 'Accept payments in your WooCommerce shop using eMoney merchant gateway.', 'woo-emoney' );
            $this->supports           = array(
                'products',
            );

            $this->init_form_fields();
            $this->init_settings();

            $this->title              = $this->get_option( 'title' );
            $this->description        = $this->get_option( 'description' );
			$this->order_button_text  = $this->get_option( 'order_button_text' );
            $this->debug              = 'yes' === $this->get_option( 'debug', 'no' );

            self::$log_enabled = $this->debug;

            $ok_slug       = $this->get_option( 'ok_slug' );
            $fail_slug     = $this->get_option( 'fail_slug' );
            $cancel_slug   = $this->get_option( 'cancel_slug' );
            $callback_slug = $this->get_option( 'callback_slug' );

            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'order_details' ) );
            add_action( 'woocommerce_api_redirect_to_payment_form', array( $this, 'redirect_to_payment_form' ) );
            add_action( 'woocommerce_api_' . $ok_slug, array( $this, 'handle_response' ) );
            add_action( 'woocommerce_api_' . $ok_slug, array( $this, 'return_from_payment_form_ok' ) );
            add_action( 'woocommerce_api_' . $fail_slug, array( $this, 'handle_response' ) );
            add_action( 'woocommerce_api_' . $fail_slug, array( $this, 'return_from_payment_form_fail' ) );
            add_action( 'woocommerce_api_' . $cancel_slug, array( $this, 'handle_response' ) );
            add_action( 'woocommerce_api_' . $cancel_slug, array( $this, 'return_from_payment_form_cancel' ) );
            add_action( 'woocommerce_api_' . $callback_slug, array( $this, 'handle_response' ) );
            add_action( 'woocommerce_api_' . $callback_slug, array( $this, 'callback_from_emoney' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            $this->eMoney               = new EmoneyMerchantProcessor();
			// since Wordpress already encodes input values
            $this->eMoney->merchant     = wp_specialchars_decode($this->get_option( 'merchant' ));
            $this->eMoney->secret_key   = wp_specialchars_decode($this->get_option( 'secret_key' ));
            $this->eMoney->test_mode    = $this->get_option( 'test_mode' ) === 'yes' ? 1 : 0;
            $this->eMoney->success_url  = get_bloginfo( 'url' ) . '/wc-api/' . $ok_slug;
            $this->eMoney->error_url    = get_bloginfo( 'url' ) . '/wc-api/' . $fail_slug;
            $this->eMoney->cancel_url   = get_bloginfo( 'url' ) . '/wc-api/' . $cancel_slug;
            $this->eMoney->callback_url = get_bloginfo( 'url' ) . '/wc-api/' . $callback_slug;
        }

        /**
         * Create a log entry
         *
         * @param string $message
         * @uses  WC_Gateway_eMoney::$log_enabled
         * @uses  WC_Gateway_eMoney::$log
         * @static
         */
        public static function log( $message ) {
            if ( self::$log_enabled ) {
                if ( empty( self::$log ) ) {
                    self::$log = new WC_Logger();
                }
                self::$log->add( 'emoney', $message );
            }
        }

        /**
         * Initialise gateway settings
         */
        public function init_form_fields() {
            $this->form_fields = include( 'includes/gateway-settings.php' );
        }

        /**
         * Display notices in admin dashboard
         *
         * Check if required parameters are set.
         * Display errors notice if they are missing,
         * both of these parameters are required for correct functioning of the plugin.
         * Check happens only when plugin is enabled not to clutter admin interface.
         *
         * @return null|void
         */
        public function admin_notices() {
            if ( $this->enabled == 'no' ) {
                return;
            }

            // Check for required parameters
        }

        /**
         * Process the payment
         *
         * This runs on ajax call from checkout page, when user clicks pay button
         *
         * @param  integer $order_id
         * @uses   WC_Gateway_eMoney::get_payment_form_url()
         * @return array
         */
        public function process_payment( $order_id ) {
            $order    = wc_get_order( $order_id );
            $currency = $order->get_currency() ? $order->get_currency() : get_woocommerce_currency();
            $amount   = $order->get_total();

            // Special data transformation for eMoney API
            $this->eMoney->order_code  = $order->get_id();
            $this->eMoney->amount      = $amount * 100;
            $this->eMoney->currency    = $currency;
            $this->eMoney->description = sprintf( __( '%s - Order %s', 'woo-emoney' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_id() );
            $this->eMoney->language    = strtoupper( substr( get_bloginfo('language'), 0, -3 ) );

            // Log order details
            $this->log( sprintf( __( 'Info ~ Order id: %s - amount: %s (%s) %s (%s), language: %s.', 'woo-emoney' ), $order->get_id(), $amount, $this->eMoney->amount, $currency, $this->eMoney->currency, $this->eMoney->language ) );

            $redirect_url = $this->eMoney->get_redirect_url();

            $this->log( sprintf( __( 'Info ~ Order id: %s, redirecting user to eMoney merchant gateway', 'woo-emoney' ), $order->get_id() ) );

            $order->update_status( 'on-hold', __( 'Awaiting payment on eMoney merchant gateway', 'woo-emoney' ) );

            // Reduce stock levels, in case of failure admin should manually restock it
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'messages' => __( 'Success! redirecting to eMoney now ...', 'woo-emoney' ),
                // Redirect user to emoney payment form
                'redirect' => $redirect_url,
            );
        }

        /**
         * OK endpoint
         *
         * Landing page for customers returning from eMoney after payment
         * here we show customers that transaction has been successfully finished
         *
         */
        public function return_from_payment_form_ok() {
            $trans_id   = filter_var( $_GET['transactioncode'], FILTER_VALIDATE_INT );

            $order      = wc_get_order( filter_var( $_GET['ordercode'], FILTER_VALIDATE_INT ) );
            $this->log( sprintf( __( 'Success ~ Order id: %s -> transaction id: %s successfully finished', 'woo-emoney' ), $order->get_id(), $trans_id ) );

            // Save trans_id for reference
            update_post_meta( $order->get_id(), '_transaction_id', $trans_id );

            $order_note = __( 'eMoney transaction has successfully finished', 'woo-emoney' );
            wc_add_notice( $order_note, 'success' );

            // redirect to thank you
            wp_redirect( $this->get_return_url( $order ) );
            exit();
        }

        /**
         * Landing page for eMoney server callback after successful form submit on eMoney merchant gateway
         *
         * @uses WC_Gateway_eMoney::get_order_id_by_transaction_id()
         */
        public function callback_from_emoney() {
            $trans_id           = filter_var( $_GET['transactioncode'], FILTER_VALIDATE_INT );
            $order_id           = filter_var( $_GET['ordercode'], FILTER_VALIDATE_INT );
            $order              = wc_get_order( $order_id );

            // By default let's assume there's an error in parameters
            $result_code        = -3;
            $result_description = '';

            $saved_order_id     = $this->get_order_id_by_transaction_id( $trans_id );
            
            if ( ! $order ) {
                $result_description = sprintf( __( 'Error ~ could not find order associated with order id: %s', 'woo-emoney' ), $order_id );
            } else {
                if ( $saved_trans_id ) {
                    if ( wc_get_order( $saved_order_id ) !== $order_id ) {
                        // Order ids mistmatch
                        $result_description = sprintf( __( 'Error ~ order id (saved on this site) associated with transaction id: %s mismatches previously registered order id on eMoney side', 'woo-emoney' ), $trans_id );
                    } else if ( strcasecmp( $order->get_status(), $_GET['status'] ) !== 0 ) {
                        // Order status already set, duplicate request
                        $result_code        = 1;
                        $result_description = sprintf( __( 'Error ~ Order id: %s -> transaction id: %s Duplicate request from eMoney', 'woo-emoney' ), $order->get_id(), $trans_id );
                    }
                }
                else if ( strtolower( $_GET['status'] ) !== 'completed') {
                    // Error in parameters
                    $result_code        = -3;
                    $result_description = sprintf( __( 'Error ~ Order id: %s -> transaction id: %s Technical failure in eMoney system, could not finish transaction', 'woo-emoney' ), $order->get_id(), $trans_id );
                } else {
                    // Payment succeeded
                    $result_code        = 0;
                    $result_description = sprintf( __( 'Success ~ Order id: %s -> transaction id: %s payment completed successfully', 'woo-emoney' ), $order->get_id(), $trans_id );
                }
            }

            $this->log( $result_description );
            switch ( $result_code ) {
                case 0:
                    $order->update_status( 'completed', __( 'eMoney payment succeeded, the transaction has been finished', 'woo-emoney' ) );
                    update_post_meta( $order_id, '_transaction_id', $trans_id );
                    break;
                case -2:
                    $order->update_status( 'failed', __( 'Something went wrong, transaction can not be finished ', 'woo-emoney' ) );
                    break;
                case -3:
                    $order->update_status( 'failed', __( 'Technical failure in eMoney system, the site received invalid parameter(s)', 'woo-emoney' ) );
            }

            $result = $this->eMoney->get_response_for_emoney($result_code, $result_description, null);
            
            echo $result;
            die;
        }

        /**
         * FAIL endpoint
         *
         * Landing page for customers returning from eMoney after technical failure
         * this can be improved by logging logged in user details
         */
        public function return_from_payment_form_fail() {
            $order = wc_get_order( filter_var( $_GET['ordercode'], FILTER_VALIDATE_INT ) );

            // not marking order as failed, nor emptying cart at this stage, so that user will have ability to try paying again
            $this->log( sprintf( __( 'Error ~ Technical failure in eMoney system on order %s', 'woo-emoney' ), $order->get_id() ) );
            wp_die( 'Technical failure in eMoney system' );
        }

        /**
         * CANCEL endpoint
         *
         * Landing page for customers returning from eMoney after cancelling payment
         */
        public function return_from_payment_form_cancel() {
            $order      = wc_get_order( filter_var( $_GET['ordercode'], FILTER_VALIDATE_INT ) );

            $this->log( sprintf( __( 'Cancelled ~ payment with order id %s was cancelled on eMoney merchant gateway', 'woo-emoney' ), $order->get_id() ) );

            $order_note = __( 'payment was cancelled on eMoney merchant gateway', 'wooo-emoney' );

            wc_add_notice( $order_note, 'notice' );
            wp_redirect( wc_get_page_permalink( 'shop' ) );
            exit();
        }

        /**
         * Add gateway data to (edit) order page
         *
         * This is redundant because woo already displays _transaction_id in the order description
         * e.g. "Payment via {gateway} ({_transaction_id}). Customer IP: {ip}"
         * but we can use it for other things in the future.
         *
         * @param object $order
         */
        public function order_details( $order ) { ?>

            <div class="order_data_column">
                <h4><?php _e( 'eMoney' ); ?></h4>
                <?php

                    echo '<p><strong>' . __( 'Transaction id', 'woo-emoney' ) . ':</strong>' . get_post_meta( $order->get_id(), '_transaction_id', true ) . '</p>';

                ?>
            </div>

        <?php }

        /**
         * Get order id by transaction id
         *
         * @param  string $trans_id
         * @return string $order_id
         */
        public function get_order_id_by_transaction_id( $trans_id ) {
            global $wpdb;

            $meta = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT * FROM $wpdb->postmeta
                     WHERE meta_key = '_transaction_id'
                       AND meta_value = %s
                     LIMIT 1
                    ",
                    $trans_id
                )
            );

            if ( ! empty($meta) && is_array($meta) && isset($meta[0]) ) {
                $meta = $meta[0];
            }

            if ( is_object($meta) ) {
                return $meta->post_id;
            }

            return false;
        }

        /**
        * Redirects to home page if response is not sent from eMoney
        *
        */
        public function handle_response() {
            // request not from eMoney
            if ( $this->eMoney->verify_response() === false ) {
                $error = __( 'Invalid request parameters. Please try again later or contact support', 'woo-emoney' );
                wp_die( $error );
            }
        }
    }

}
add_action( 'plugins_loaded', 'init_woo_gateway_emoney' );
