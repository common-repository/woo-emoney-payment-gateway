<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for eMoney Merchant Gateway
 */
return array(
	'enabled' => array(
		'title'   => __( 'Enable/Disable', 'woo-emoney' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable eMoney', 'woo-emoney' ),
		'default' => 'yes',
	),
	'title' => array(
		'title'       => __( 'Title', 'woo-emoney' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woo-emoney' ),
		'default'     => __( 'eMoney', 'woo-emoney' ),
		'desc_tip'    => true,
	),
	'description' => array(
		'title'       => __( 'Description', 'woo-emoney' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'woo-emoney' ),
		'default'     => __( 'Pay via eMoney', 'woo-emoney' ),
	),
	'order_button_text' => array(
		'title'       => __('Order Button Text', 'woo-emoney'),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __('Choose what text should appear on an order button during checkout.', 'woo-emoney'),
		'default'     => __( 'Proceed to eMoney', 'woo-emoney' ),
	),
	'merchant' => array(
		'title'       => __( 'Merchant', 'woo-emoney' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'This controls the merchant name which the eMoney API sees.', 'woo-emoney' ),
		'default'     => __( 'TEST_MERCHANT', 'woo-emoney' ),
	),
	'secret_key' => array(
		'title'       => __( 'Secret Key', 'woo-emoney' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'This controls the secret key which the eMoney API requires.', 'woo-emoney' ),
	),
	'test_mode' => array(
		'title'       => __( 'Test Mode', 'woo-emoney' ),
		'type'        => 'checkbox',
		'desc_tip'    => true,
		'default'     => 'yes',
		'description' => __( 'This controls whether a test mode is enabled.', 'woo-emoney' ),
	),
	'debug' => array(
		'title'       => __( 'Debug Log', 'woo-emoney' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'woo-emoney' ),
		'default'     => 'no',
		'description' => sprintf( __( 'Log eMoney events, such as IPN requests, inside <code>%s</code>', 'woo-emoney' ), wc_get_log_file_path( 'emoney' ) ),
	),
	'ok_slug' => array(
		'title'       => __( 'Ok', 'woo-emoney' ),
		'type'        => 'text',
		'description' => sprintf( __( '<code>%1$s/wc-api/%2$s</code> - communicate this OK url to eMoney', 'woo-emoney' ), get_bloginfo( 'url' ), $this->get_option( 'ok_slug', 'emoney_merchant/ok' ) ),
		'default'     => __( 'emoney_merchant/ok', 'woo-emoney' ),
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'fail_slug' => array(
		'title'       => __( 'Fail', 'woo-emoney' ),
		'type'        => 'text',
		'description' => sprintf( __( '<code>%1$s/wc-api/%2$s</code> - communicate this FAIL url to eMoney', 'woo-emoney' ), get_bloginfo( 'url' ), $this->get_option( 'fail_slug', 'emoney_merchant/fail' ) ),
		'default'     => __( 'emoney_merchant/fail', 'woo-emoney' ),
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'cancel_slug' => array(
		'title'       => __( 'Cancel', 'woo-emoney' ),
		'type'        => 'text',
		'description' => sprintf( __( '<code>%1$s/wc-api/%2$s</code> - communicate this CANCEL url to eMoney', 'woo-emoney' ), get_bloginfo( 'url' ), $this->get_option( 'cancel_slug', 'emoney_merchant/cancel' ) ),
		'default'     => __( 'emoney_merchant/cancel', 'woo-emoney' ),
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'callback_slug' => array(
		'title'       => __( 'Callback', 'woo-emoney' ),
		'type'        => 'text',
		'description' => sprintf( __( '<code>%1$s/wc-api/%2$s</code> - communicate this CALLBACK url to eMoney', 'woo-emoney' ), get_bloginfo( 'url' ), $this->get_option( 'callback_slug', 'emoney_merchant/callback' ) ),
		'default'     => __( 'emoney_merchant/callback', 'woo-emoney' ),
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
);
