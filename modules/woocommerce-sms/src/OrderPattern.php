<?php

namespace PW\PWSMS;

defined( 'ABSPATH' ) || exit;

/**
 * Pattern-based (پترن) order status notifications.
 *
 * Adds a settings tab with one pattern template per WooCommerce order status.
 * When a pattern is configured, the buyer's order SMS body is replaced with the
 * pattern string before PWSMS shortcodes are expanded, so existing gateways
 * dispatch it to their native send_pattern_sms() implementation.
 */
class OrderPattern {

	const SECTION = 'sms_order_pattern_settings';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'pwoosms_settings_sections', array( $this, 'add_section' ) );
		add_filter( 'pwoosms_settings_fields', array( $this, 'add_fields' ) );
		add_filter( 'pwoosms_order_sms_body_before_replace', array( $this, 'use_pattern' ), 10, 5 );
	}

	/**
	 * Add the settings tab.
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public function add_section( $sections ) {
		$sections[] = array(
			'id'    => self::SECTION,
			'title' => 'پیامک پترن سفارش',
		);

		return $sections;
	}

	/**
	 * Add fields: a master switch plus one pattern per order status.
	 *
	 * @param array $fields Fields keyed by section id.
	 * @return array
	 */
	public function add_fields( $fields ) {
		$items = array(
			array(
				'name' => 'enable_order_pattern',
				'label' => 'فعال سازی پترن وضعیت سفارش',
				'type' => 'checkbox',
				'desc' => 'در صورت فعال بودن، پیامک مشتری برای وضعیتهایی که الگوی پترن آنها پر شده است، به صورت پترن ارسال میشود.',
			),
			array(
				'name' => 'order_pattern_help',
				'label' => '<h2>الگوی پترن براساس وضعیت سفارش</h2><p>در هر فیلد، خط اول شماره پترن و خطوط بعدی متغیرها به صورت <code>نام:{shortcode}</code> وارد شوند. متغیرها پیش از ارسال توسط افزونه پر میشوند. برای مثال:<br><code>pattern:12345<br>order_id:{order_id}<br>name:{b_first_name}<br>status:{status}</code><br>توجه: درگاه انتخابشده باید از ارسال پترن پشتیبانی کند.</p>',
				'type' => 'html',
			),
		);

		$statuses = PWSMS()->get_all_statuses();
		foreach ( (array) $statuses as $status => $label ) {
			$items[] = array(
				'name'    => 'pattern_' . $status,
				'label'   => 'پترن وضعیت ' . $label,
				'type'    => 'textarea',
				'desc'    => 'برای غیرفعال کردن این وضعیت، فیلد را خالی بگذارید.',
				'sanitize_callback' => 'sanitize_textarea_field',
			);
		}

		$fields[ self::SECTION ] = $items;

		return $fields;
	}

	/**
	 * Replace the buyer's plain-text body with the configured pattern template.
	 *
	 * The filter runs inside Helper::replace_short_codes(), so {shortcode}
	 * placeholders inside the pattern are expanded immediately afterwards.
	 *
	 * @param string $content     Message body before replacement.
	 * @param array  $tags_keys   Placeholder names.
	 * @param array  $tags_values Placeholder values.
	 * @param int    $order_id    Order ID.
	 * @param \WC_Order $order    Order object.
	 * @return string
	 */
	public function use_pattern( $content, $tags_keys = array(), $tags_values = array(), $order_id = 0, $order = null ) {
		// Read directly: the shared option cache may have been built before
		// this section was registered on admin_init.
		$options = (array) get_option( self::SECTION, array() );
		if ( empty( $options['enable_order_pattern'] ) ) {
			return $content;
		}

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_status' ) ) {
			return $content;
		}

		$status  = PWSMS()->modify_status( $order->get_status() );
		$pattern = trim( (string) ( $options['pattern_' . $status] ?? '' ) );

		if ( '' === $pattern || 0 !== stripos( $pattern, 'pattern:' ) ) {
			return $content;
		}

		if ( ! $this->gateway_supports_pattern() ) {
			return $content;
		}

		// Only touch the customer's order message — leave admin/vendor messages alone.
		$buyer = (array) get_option( 'sms_buyer_settings', array() );
		$plain = (string) ( $buyer['sms_body_' . $status] ?? '' );
		$manual = isset( $_POST['sms_order_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sms_order_text'] ) ) : '';
		if ( $content !== $plain && ( '' === $manual || $content !== $manual ) ) {
			return $content;
		}

		/**
		 * Filter the prepared pattern string before shortcodes are expanded.
		 *
		 * @param string $pattern Pattern template.
		 * @param \WC_Order $order Order object.
		 * @param string $status  Normalized status.
		 */
		return apply_filters( 'pwoosms_order_pattern_template', $pattern, $order, $status );
	}

	/**
	 * Avoid sending a raw "pattern:..." string through a gateway that cannot
	 * handle patterns.
	 *
	 * @return bool
	 */
	private function gateway_supports_pattern() {
		try {
			$gateway = \PW\PWSMS\Helper::get_sms_gateway();
		} catch ( \Throwable $e ) {
			return false;
		}

		$supported = method_exists( $gateway, 'send_pattern_sms' )
			|| $gateway instanceof \PW\PWSMS\Gateways\Features\SendPatternFeature
			|| $gateway instanceof \PW\PWSMS\Gateways\OneSMS;

		return (bool) apply_filters( 'pwoosms_order_pattern_gateway_supported', $supported, $gateway );
	}
}
