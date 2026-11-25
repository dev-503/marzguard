<?php
/**
 * Notifier: queues and sends order notifications.
 *
 * @package Hamidreza
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends order notifications via Bale.
 */
class DOT_Bale_Notifier {

	/**
	 * Cron / Action Scheduler hook.
	 */
	const HOOK = 'hron_send_notification';

	/**
	 * Max send attempts (initial + retries).
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Whether hooks were registered.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * سفارشی که در حال ارسال اعلان آن هستیم (برای افزودن دکمه‌های شیشه‌ای).
	 *
	 * @var int
	 */
	private $ctx_order_id = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( self::$hooked ) {
			return;
		}

		self::$hooked = true;

		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_queue' ), 10, 4 );
		add_action( self::HOOK, array( $this, 'process' ), 10, 3 );
	}

	/**
	 * Queue a notification when an order status changes.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Previous status slug.
	 * @param string   $new_status New status slug.
	 * @param WC_Order $order      Order object.
	 * @return void
	 */
	public function maybe_queue( $order_id, $old_status, $new_status, $order ) {
		unset( $order );

		$order_id   = absint( $order_id );
		$new_status = sanitize_key( $new_status );
		$old_status = sanitize_key( $old_status );

		if ( $order_id && '' !== $old_status && $old_status !== $new_status ) {
			delete_transient( 'hron_lock_' . $order_id . '_' . $old_status );
		}

		if ( ! $order_id || '' === $new_status || ! $this->should_notify( $order_id, $new_status ) ) {
			return;
		}

		$lock = 'hron_lock_' . $order_id . '_' . $new_status;
		if ( get_transient( $lock ) ) {
			return;
		}
		set_transient( $lock, 1, 10 * MINUTE_IN_SECONDS );

		$this->enqueue( $order_id, $new_status, 1 );
	}

	/**
	 * صف‌کردن اعلان برای وضعیتِ فعلیِ سفارش (مسیر «ایجاد سفارش»).
	 *
	 * دقیقاً مثل مسیر «تغییر وضعیت» عمل می‌کند: فقط اگر وضعیت فعلی جزو وضعیت‌های
	 * انتخاب‌شده باشد، و با قفل مشترک سفارش+وضعیت تا یک سفارش دو بار ارسال نشود.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function queue_current_status( $order_id ) {
		$order_id = absint( $order_id );
		$order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$status = sanitize_key( $order->get_status() );
		if ( '' === $status ) {
			return false;
		}
		if ( ! $this->should_notify( $order_id, $status ) ) {
			// فقط وقتی تنظیمات کامل است دلیل را ثبت کن تا در «گزارش ارسال‌ها» دیده شود
			if ( DOT_Bale_Settings::is_ready() ) {
				DOT_Bale_Log::add( array(
					'order_id' => $order->get_order_number(),
					'platform' => 'bale',
					'status'   => 'skipped',
					'message'  => sprintf(
						'سفارش جدید ثبت شد ولی وضعیت «%s» در لیست «وضعیت‌هایی که اعلان می‌گیرند» تیک نخورده است. هر وقت به یکی از وضعیت‌های انتخاب‌شده رسید، پیام ارسال می‌شود.',
						wc_get_order_status_name( $status )
					),
				) );
			}
			return false;
		}

		$lock = 'hron_lock_' . $order_id . '_' . $status;
		if ( get_transient( $lock ) ) {
			return false;
		}
		set_transient( $lock, 1, 10 * MINUTE_IN_SECONDS );

		$this->enqueue( $order_id, $status, 1 );
		return true;
	}

	/**
	 * Process a queued notification.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status slug.
	 * @param int    $attempt    Attempt number.
	 * @return void
	 */
	public function process( $order_id, $new_status, $attempt = 1 ) {
		$order_id   = absint( $order_id );
		$new_status = sanitize_key( $new_status );
		$attempt    = max( 1, absint( $attempt ) );

		if ( ! $order_id || '' === $new_status || ! $this->should_notify( $order_id, $new_status ) ) {
			return;
		}

		if ( ! function_exists( 'wc_get_order' ) || ! class_exists( 'WC_Order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $new_status !== $order->get_status() ) {
			return;
		}

		$options = DOT_Bale_Settings::get();
		$built   = $this->build_message( $order, $new_status, $options );
		$this->ctx_order_id = $order_id;
		$result             = $this->deliver_all( $built, $options );
		$this->ctx_order_id = 0;

		if ( ! empty( $result['ok'] ) ) {
			/**
			 * Fires after order notifications are delivered successfully.
			 *
			 * @param int    $order_id   Order ID.
			 * @param string $new_status Order status slug without wc- prefix.
			 * @param array  $result     Delivery summary.
			 */
			do_action( 'hron_notification_sent', $order_id, $new_status, $result );
			return;
		}

		if ( $attempt < self::MAX_ATTEMPTS ) {
			$this->enqueue( $order_id, $new_status, $attempt + 1, 60 * $attempt );
			return;
		}

		$order->add_order_note(
			__( 'Order notification could not be delivered to Bale.', 'hamidreza' )
		);

		/**
		 * Fires after all delivery attempts fail.
		 *
		 * @param int    $order_id   Order ID.
		 * @param string $new_status Order status slug without wc- prefix.
		 * @param array  $result     Delivery summary.
		 */
		do_action( 'hron_notification_failed', $order_id, $new_status, $result );
	}

	/**
	 * Build a preview message using the latest order or sample data.
	 *
	 * @param array $options Settings array.
	 * @return array { markdown: string, plain: string }
	 */
	public function build_preview( $options ) {
		$order = null;

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'objects',
				)
			);

			if ( ! empty( $orders[0] ) && $orders[0] instanceof WC_Order ) {
				$order = $orders[0];
			}
		}

		if ( $order instanceof WC_Order ) {
			return $this->build_message( $order, $order->get_status(), $options );
		}

		return $this->build_sample_message( $options );
	}

	/**
	 * Send a message using explicit credentials (settings page test).
	 *
	 * @param string $platform  bale.
	 * @param string $bot_token Bot token.
	 * @param string $chat_id   Chat ID.
	 * @param string $markdown  Markdown body.
	 * @param string $plain     Plain fallback.
	 * @param string $button_url Optional inline button URL.
	 * @return array { ok: bool, message: string }
	 */
	public function send_test( $platform, $bot_token, $chat_id, $markdown, $plain, $button_url = '' ) {
		$url = $this->api_url( $platform, $bot_token );

		$res = $this->post_message( $url, $chat_id, $markdown, 'Markdown', $button_url, $platform );
		if ( ! $res['ok'] ) {
			$res = $this->post_message( $url, $chat_id, $plain, '', $button_url, $platform );
		}

		DOT_Bale_Log::add(
			array(
				'order_id' => __( 'Test', 'hamidreza' ),
				'platform' => $platform,
				'status'   => ! empty( $res['ok'] ) ? 'success' : 'failed',
				'message'  => ! empty( $res['ok'] )
					? __( 'Test message delivered successfully.', 'hamidreza' )
					: ( ! empty( $res['message'] ) ? $res['message'] : __( 'Test failed.', 'hamidreza' ) ),
			)
		);

		return $res;
	}

	/**
	 * Whether this status should trigger a notification.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status Status slug without wc- prefix.
	 * @return bool
	 */
	private function should_notify( $order_id, $new_status ) {
		$options = DOT_Bale_Settings::get();

		if ( ! DOT_Bale_Settings::is_ready( $options ) ) {
			return false;
		}

		$targets = isset( $options['statuses'] ) ? (array) $options['statuses'] : array();
		$notify  = in_array( 'wc-' . $new_status, $targets, true );

		/**
		 * Filter whether an order status change should trigger a notification.
		 *
		 * @param bool   $notify     Whether to notify.
		 * @param int    $order_id   Order ID.
		 * @param string $new_status Order status slug without wc- prefix.
		 * @param array  $options    Plugin settings.
		 */
		return (bool) apply_filters( 'hron_should_notify', $notify, $order_id, $new_status, $options );
	}

	/**
	 * Enqueue a background send.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status Status slug.
	 * @param int    $attempt    Attempt number.
	 * @param int    $delay      Delay in seconds.
	 * @return void
	 */
	private function enqueue( $order_id, $new_status, $attempt, $delay = 0 ) {
		$args = array( $order_id, $new_status, $attempt );

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK, $args, 'hron' ) ) {
			return;
		}

		if ( ! function_exists( 'as_has_scheduled_action' ) && wp_next_scheduled( self::HOOK, $args ) ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) && $delay <= 0 ) {
			as_enqueue_async_action( self::HOOK, $args, 'hron' );
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) && $delay > 0 ) {
			as_schedule_single_action( time() + $delay, self::HOOK, $args, 'hron' );
			return;
		}

		wp_schedule_single_event( time() + $delay, self::HOOK, $args );
	}

	/**
	 * Build Markdown and plain text bodies.
	 *
	 * @param WC_Order $order      Order.
	 * @param string   $new_status Status slug.
	 * @param array    $options    Plugin settings.
	 * @return array { markdown: string, plain: string, button_url: string }
	 */
	public function build_message( $order, $new_status, $options = null ) {
		if ( null === $options ) {
			$options = DOT_Bale_Settings::get();
		}

		$context = $this->build_context( $order, $new_status, $options );
		$template = isset( $options['message_template'] ) ? trim( (string) $options['message_template'] ) : '';

		if ( '' !== $template ) {
			$built = array(
				'plain'      => $this->apply_template( $template, $context, 'plain' ),
				'markdown'   => $this->apply_template( $template, $context, 'markdown' ),
				'button_url' => $this->button_url( $context, $options ),
			);
		} else {
			$built = $this->build_default_message( $context, $options );
		}

		$built['order_number'] = $order->get_order_number();

		/**
		 * Filter the built notification message before delivery.
		 *
		 * @param array    $built      { markdown: string, plain: string, button_url: string }.
		 * @param WC_Order $order      Order object.
		 * @param string   $new_status Order status slug without wc- prefix.
		 * @param array    $options    Plugin settings.
		 */
		return apply_filters( 'hron_notification_message', $built, $order, $new_status, $options );
	}

	/**
	 * Collect order data used in templates and default messages.
	 *
	 * @param WC_Order $order      Order.
	 * @param string   $new_status Status slug.
	 * @param array    $options    Plugin settings.
	 * @return array
	 */
	private function build_context( $order, $new_status, $options ) {
		$customer = trim( (string) $order->get_formatted_billing_full_name() );
		if ( '' === $customer ) {
			$customer = __( 'Guest', 'hamidreza' );
		}

		$phone   = (string) $order->get_billing_phone();
		$total   = wp_strip_all_tags( (string) $order->get_formatted_order_total() );
		$status  = wc_get_order_status_name( $new_status );
		$payment = (string) $order->get_payment_method_title();
		$number  = $order->get_order_number();
		$created = $order->get_date_created();
		$date    = $created ? $created->date_i18n( 'Y-m-d H:i' ) : '';

		$date_jalali      = '';
		$date_time_jalali = '';
		if ( $created instanceof WC_DateTime ) {
			$date_jalali      = DOT_Bale_Jalali::format_datetime( $created, false, true );
			$date_time_jalali = DOT_Bale_Jalali::format_datetime( $created, true, true );
		}

		$edit    = $order->get_edit_order_url();
		$address = $order->get_formatted_shipping_address();

		if ( '' === trim( wp_strip_all_tags( (string) $address ) ) ) {
			$address = $order->get_formatted_billing_address();
		}

		$address_plain = preg_replace( '/<br\s*\/?>/i', "\n", (string) $address );
		$address_plain = trim( wp_strip_all_tags( $address_plain ) );

		$items_plain = $this->format_items( $order, $options, 'plain' );
		$items_md    = $this->format_items( $order, $options, 'markdown' );

		$customer_note = trim( (string) $order->get_customer_note() );
		$coupon        = $this->format_coupons( $order );
		$shipping_cost = $this->format_shipping_total( $order );
		$site_name     = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
		$site_url      = $this->format_site_display_url( home_url( '/' ) );

		$shipping_method = (string) $order->get_shipping_method();
		$customer_email  = (string) $order->get_billing_email();
		$items_count     = (string) $order->get_item_count();

		$ship_city = $order->get_shipping_city();
		$bill_city = $order->get_billing_city();
		$city      = ! empty( $ship_city ) ? $ship_city : ( ! empty( $bill_city ) ? $bill_city : '-' );

		$ship_state = $order->get_shipping_state();
		$bill_state = $order->get_billing_state();
		$province   = ! empty( $ship_state ) ? $ship_state : ( ! empty( $bill_state ) ? $bill_state : '-' );

		return array(
			'order_number'     => $number,
			'status'           => $status,
			'customer'         => $customer,
			'phone'            => '' !== $phone ? $phone : '-',
			'payment'          => $payment,
			'total'            => $total,
			'date'             => $date,
			'date_jalali'      => $date_jalali,
			'date_time_jalali' => $date_time_jalali,
			'customer_email'   => '' !== $customer_email ? $customer_email : '-',
			'shipping_method'  => '' !== $shipping_method ? $shipping_method : '-',
			'items_count'      => $items_count,
			'city'             => $city,
			'province'         => $province,
			'items'            => $items_plain,
			'items_md'         => $items_md,
			'address'          => $address_plain,
			'customer_note'    => $customer_note,
			'coupon'           => $coupon,
			'shipping'         => $shipping_cost,
			'order_url'        => $edit ? $edit : '',
			'site_name'        => $site_name,
			'site_url'         => $site_url,
		);
	}

	/**
	 * Build the default message layout (pre-1.4.0 behavior, with optional extras).
	 *
	 * @param array $context Order context.
	 * @param array $options Plugin settings.
	 * @return array
	 */
	private function build_default_message( $context, $options ) {
		$plain_lines = array(
			__( 'WooCommerce Order Update', 'hamidreza' ),
			'',
			sprintf(
				/* translators: %s: order number */
				__( 'Order: #%s', 'hamidreza' ),
				$context['order_number']
			),
			sprintf(
				/* translators: %s: order status */
				__( 'Status: %s', 'hamidreza' ),
				$context['status']
			),
			sprintf(
				/* translators: %s: customer name */
				__( 'Customer: %s', 'hamidreza' ),
				$context['customer']
			),
			sprintf(
				/* translators: %s: phone number */
				__( 'Phone: %s', 'hamidreza' ),
				$context['phone']
			),
		);

		if ( '' !== $context['payment'] ) {
			$plain_lines[] = sprintf(
				/* translators: %s: payment method */
				__( 'Payment: %s', 'hamidreza' ),
				$context['payment']
			);
		}

		$plain_lines[] = sprintf(
			/* translators: %s: order total */
			__( 'Total: %s', 'hamidreza' ),
			$context['total']
		);

		if ( ! empty( $options['include_shipping'] ) && '' !== $context['shipping'] ) {
			$plain_lines[] = sprintf(
				/* translators: %s: shipping total */
				__( 'Shipping: %s', 'hamidreza' ),
				$context['shipping']
			);
		}

		if ( ! empty( $options['include_coupon'] ) && '' !== $context['coupon'] ) {
			$plain_lines[] = sprintf(
				/* translators: %s: coupon codes */
				__( 'Coupon: %s', 'hamidreza' ),
				$context['coupon']
			);
		}

		if ( $context['date'] ) {
			$plain_lines[] = sprintf(
				/* translators: %s: order date */
				__( 'Date: %s', 'hamidreza' ),
				$context['date']
			);
		}

		if ( ! empty( $options['include_customer_note'] ) && '' !== $context['customer_note'] ) {
			$plain_lines[] = sprintf(
				/* translators: %s: customer order note */
				__( 'Customer note: %s', 'hamidreza' ),
				$context['customer_note']
			);
		}

		$plain_lines[] = '';
		$plain_lines[] = __( 'Items:', 'hamidreza' );
		$plain_lines   = array_merge( $plain_lines, explode( "\n", $context['items'] ) );

		if ( '' !== $context['address'] ) {
			$plain_lines[] = '';
			$plain_lines[] = __( 'Shipping address:', 'hamidreza' );
			$plain_lines   = array_merge( $plain_lines, explode( "\n", $context['address'] ) );
		}

		$use_button = '' !== $context['order_url'];

		if ( '' !== $context['order_url'] ) {
			$plain_lines[] = '';
			$plain_lines[] = $context['order_url'];
		}

		$md_lines   = array();
		$md_lines[] = '*' . $this->escape_markdown( __( 'WooCommerce Order Update', 'hamidreza' ) ) . '*';
		$md_lines[] = '';
		$md_lines[] = '*' . $this->escape_markdown( __( 'Order:', 'hamidreza' ) ) . '* #' . $this->escape_markdown( $context['order_number'] );
		$md_lines[] = '*' . $this->escape_markdown( __( 'Status:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['status'] );
		$md_lines[] = '*' . $this->escape_markdown( __( 'Customer:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['customer'] );
		$md_lines[] = '*' . $this->escape_markdown( __( 'Phone:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['phone'] );

		if ( '' !== $context['payment'] ) {
			$md_lines[] = '*' . $this->escape_markdown( __( 'Payment:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['payment'] );
		}

		$md_lines[] = '*' . $this->escape_markdown( __( 'Total:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['total'] );

		if ( ! empty( $options['include_shipping'] ) && '' !== $context['shipping'] ) {
			$md_lines[] = '*' . $this->escape_markdown( __( 'Shipping:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['shipping'] );
		}

		if ( ! empty( $options['include_coupon'] ) && '' !== $context['coupon'] ) {
			$md_lines[] = '*' . $this->escape_markdown( __( 'Coupon:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['coupon'] );
		}

		if ( $context['date'] ) {
			$md_lines[] = '*' . $this->escape_markdown( __( 'Date:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['date'] );
		}

		if ( ! empty( $options['include_customer_note'] ) && '' !== $context['customer_note'] ) {
			$md_lines[] = '*' . $this->escape_markdown( __( 'Customer note:', 'hamidreza' ) ) . '* ' . $this->escape_markdown( $context['customer_note'] );
		}

		$md_lines[] = '';
		$md_lines[] = '*' . $this->escape_markdown( __( 'Items:', 'hamidreza' ) ) . '*';
		$md_lines   = array_merge( $md_lines, explode( "\n", $context['items_md'] ) );

		if ( '' !== $context['address'] ) {
			$md_lines[] = '';
			$md_lines[] = '*' . $this->escape_markdown( __( 'Shipping address:', 'hamidreza' ) ) . '*';
			foreach ( explode( "\n", $context['address'] ) as $address_line ) {
				$md_lines[] = $this->escape_markdown( $address_line );
			}
		}

		list( $plain_lines, $md_lines ) = $this->insert_site_header_after_title( $plain_lines, $md_lines, $context );

		return array(
			'markdown'   => implode( "\n", $md_lines ),
			'plain'      => implode( "\n", $plain_lines ),
			'button_url' => $use_button ? $context['order_url'] : '',
		);
	}

	/**
	 * Build a sample preview when no orders exist yet.
	 *
	 * @param array $options Plugin settings.
	 * @return array
	 */
	private function build_sample_message( $options ) {
		$orders_url = admin_url( 'edit.php?post_type=shop_order' );
		if (
			class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
		) {
			$orders_url = admin_url( 'admin.php?page=wc-orders' );
		}

		$context = array(
			'order_number'     => '1234',
			'status'           => wc_get_order_status_name( 'processing' ),
			'customer'         => __( 'Sample Customer', 'hamidreza' ),
			'phone'            => '09123456789',
			'payment'          => __( 'Cash on delivery', 'hamidreza' ),
			'total'            => '250,000',
			'date'             => wp_date( 'Y-m-d H:i' ),
			'date_jalali'      => DOT_Bale_Jalali::persian_digits( '1403/08/05' ),
			'date_time_jalali' => DOT_Bale_Jalali::persian_digits( '1403/08/05 14:30' ),
			'customer_email'   => 'customer@example.com',
			'shipping_method'  => __( 'Express Courier', 'hamidreza' ),
			'items_count'      => '2',
			'city'             => __( 'Tehran', 'hamidreza' ),
			'province'         => __( 'Tehran', 'hamidreza' ),
			'items'            => '• ' . __( 'Sample product', 'hamidreza' ) . ' × 1',
			'items_md'         => '• ' . $this->escape_markdown( __( 'Sample product', 'hamidreza' ) ) . ' × 1',
			'address'          => __( 'Sample Street 1', 'hamidreza' ) . "\n" . __( 'Sample City', 'hamidreza' ),
			'customer_note'    => __( 'Please call before delivery.', 'hamidreza' ),
			'coupon'           => 'WELCOME10',
			'shipping'         => '25,000',
			'order_url'        => $orders_url,
			'site_name'        => wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
			'site_url'         => $this->format_site_display_url( home_url( '/' ) ),
		);

		$template = isset( $options['message_template'] ) ? trim( (string) $options['message_template'] ) : '';

		if ( '' !== $template ) {
			return array(
				'plain'      => $this->apply_template( $template, $context, 'plain' ),
				'markdown'   => $this->apply_template( $template, $context, 'markdown' ),
				'button_url' => $this->button_url( $context, $options ),
			);
		}

		return $this->build_default_message( $context, $options );
	}

	/**
	 * Default message as an editable placeholder template.
	 *
	 * Mirrors the automatic layout (title, single-line site header, order
	 * fields, items, address) so it can sit directly in the custom template
	 * editor as a starting point. Optional lines are included only when the
	 * matching option is enabled, so saving the prefilled editor does not
	 * silently drop fields the admin had turned on. Payment and date lines
	 * are always present; they render empty on orders missing those values.
	 *
	 * @param array|null $options Optional settings array.
	 * @return string
	 */
	public static function default_template_text( $options = null ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$lines = array(
			__( 'WooCommerce Order Update', 'hamidreza' ),
			'{site_name} {site_url}',
			'',
			sprintf(
				/* translators: %s: order number placeholder */
				__( 'Order: #%s', 'hamidreza' ),
				'{order_number}'
			),
			sprintf(
				/* translators: %s: order status placeholder */
				__( 'Status: %s', 'hamidreza' ),
				'{status}'
			),
			sprintf(
				/* translators: %s: customer name placeholder */
				__( 'Customer: %s', 'hamidreza' ),
				'{customer}'
			),
			sprintf(
				/* translators: %s: phone number placeholder */
				__( 'Phone: %s', 'hamidreza' ),
				'{phone}'
			),
			sprintf(
				/* translators: %s: payment method placeholder */
				__( 'Payment: %s', 'hamidreza' ),
				'{payment}'
			),
			sprintf(
				/* translators: %s: order total placeholder */
				__( 'Total: %s', 'hamidreza' ),
				'{total}'
			),
		);

		if ( ! empty( $options['include_shipping'] ) ) {
			$lines[] = sprintf(
				/* translators: %s: shipping total placeholder */
				__( 'Shipping: %s', 'hamidreza' ),
				'{shipping}'
			);
		}

		if ( ! empty( $options['include_coupon'] ) ) {
			$lines[] = sprintf(
				/* translators: %s: coupon codes placeholder */
				__( 'Coupon: %s', 'hamidreza' ),
				'{coupon}'
			);
		}

		$lines[] = sprintf(
			/* translators: %s: order date placeholder */
			__( 'Date: %s', 'hamidreza' ),
			'{date}'
		);

		if ( ! empty( $options['include_customer_note'] ) ) {
			$lines[] = sprintf(
				/* translators: %s: customer order note placeholder */
				__( 'Customer note: %s', 'hamidreza' ),
				'{customer_note}'
			);
		}

		$lines[] = '';
		$lines[] = __( 'Items:', 'hamidreza' );
		$lines[] = '{items}';
		$lines[] = '';
		$lines[] = __( 'Shipping address:', 'hamidreza' );
		$lines[] = '{address}';

		return implode( "\n", $lines );
	}

	/**
	 * Replace template placeholders.
	 *
	 * @param string $template Template text.
	 * @param array  $context  Order context.
	 * @param string $format   plain|markdown.
	 * @return string
	 */
	private function apply_template( $template, $context, $format ) {
		$items = 'markdown' === $format ? $context['items_md'] : $context['items'];

		$replacements = array(
			'{order_number}'     => $this->format_template_value( $context['order_number'], $format ),
			'{status}'           => $this->format_template_value( $context['status'], $format ),
			'{customer}'         => $this->format_template_value( $context['customer'], $format ),
			'{phone}'            => $this->format_template_value( $context['phone'], $format ),
			'{payment}'          => $this->format_template_value( $context['payment'], $format ),
			'{total}'            => $this->format_template_value( $context['total'], $format ),
			'{date}'             => $this->format_template_value( $context['date'], $format ),
			'{date_jalali}'      => $this->format_template_value( isset( $context['date_jalali'] ) ? $context['date_jalali'] : '', $format ),
			'{date_time_jalali}' => $this->format_template_value( isset( $context['date_time_jalali'] ) ? $context['date_time_jalali'] : '', $format ),
			'{customer_email}'   => $this->format_template_value( isset( $context['customer_email'] ) ? $context['customer_email'] : '', $format ),
			'{shipping_method}'  => $this->format_template_value( isset( $context['shipping_method'] ) ? $context['shipping_method'] : '', $format ),
			'{items_count}'      => $this->format_template_value( isset( $context['items_count'] ) ? $context['items_count'] : '', $format ),
			'{city}'             => $this->format_template_value( isset( $context['city'] ) ? $context['city'] : '', $format ),
			'{province}'         => $this->format_template_value( isset( $context['province'] ) ? $context['province'] : '', $format ),
			'{items}'            => $items,
			'{address}'          => $this->format_template_value( $context['address'], $format, true ),
			'{customer_note}'    => $this->format_template_value( $context['customer_note'], $format ),
			'{coupon}'           => $this->format_template_value( $context['coupon'], $format ),
			'{shipping}'         => $this->format_template_value( $context['shipping'], $format ),
			'{order_url}'        => $context['order_url'],
			'{site_name}'        => $this->format_template_value( $context['site_name'], $format ),
			'{site_url}'         => $context['site_url'],
		);

		// strtr() replaces in a single pass, so placeholders typed by customers (e.g. in notes) are not expanded.
		return strtr( $template, $replacements );
	}

	/**
	 * Format a single template value.
	 *
	 * @param string $value     Raw value.
	 * @param string $format    plain|markdown.
	 * @param bool   $multiline Whether value may contain line breaks.
	 * @return string
	 */
	private function format_template_value( $value, $format, $multiline = false ) {
		$value = (string) $value;

		if ( 'markdown' !== $format ) {
			return $value;
		}

		if ( $multiline ) {
			$lines = explode( "\n", $value );
			return implode( "\n", array_map( array( $this, 'escape_markdown' ), $lines ) );
		}

		return $this->escape_markdown( $value );
	}

	/**
	 * Resolve inline button URL from context and settings.
	 *
	 * @param array $context Order context.
	 * @param array $options Plugin settings.
	 * @return string
	 */
	private function button_url( $context, $options ) {
		unset( $options );

		if ( '' === $context['order_url'] ) {
			return '';
		}

		return $context['order_url'];
	}

	/**
	 * Format a site URL for display in messages (host only, e.g. elaart.ir).
	 *
	 * @param string $url Site URL.
	 * @return string
	 */
	private function format_site_display_url( $url ) {
		$url = (string) $url;

		if ( '' === $url ) {
			return '';
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( is_string( $host ) && '' !== $host ) {
			return $host;
		}

		return untrailingslashit( preg_replace( '#^https?://#i', '', $url ) );
	}

	/**
	 * Insert site name + URL as a single line under the message title.
	 *
	 * Single line, site name first then URL. Nothing is forced:
	 * if one part is empty only the other is shown, if both are
	 * empty the message is left untouched.
	 *
	 * @param array $plain_lines Plain text lines.
	 * @param array $md_lines    Markdown lines.
	 * @param array $context     Order context.
	 * @return array[] Updated plain and markdown line arrays.
	 */
	private function insert_site_header_after_title( $plain_lines, $md_lines, $context ) {
		$site_name = isset( $context['site_name'] ) ? trim( (string) $context['site_name'] ) : '';
		$site_url  = isset( $context['site_url'] ) ? trim( (string) $context['site_url'] ) : '';

		if ( '' === $site_name && '' === $site_url ) {
			return array( $plain_lines, $md_lines );
		}

		$plain_parts = array();
		if ( '' !== $site_name ) {
			$plain_parts[] = $site_name;
		}
		if ( '' !== $site_url ) {
			$plain_parts[] = $site_url;
		}
		$plain_line = implode( ' ', $plain_parts );

		$md_parts = array();
		if ( '' !== $site_name ) {
			$md_parts[] = '*' . $this->escape_markdown( $site_name ) . '*';
		}
		if ( '' !== $site_url ) {
			$md_parts[] = $this->escape_markdown( $site_url );
		}
		$md_line = implode( ' ', $md_parts );

		if ( empty( $plain_lines ) || empty( $md_lines ) ) {
			return array( $plain_lines, $md_lines );
		}

		array_splice( $plain_lines, 1, 0, array( $plain_line ) );
		array_splice( $md_lines, 1, 0, array( $md_line ) );

		return array( $plain_lines, $md_lines );
	}

	/**
	 * Format order line items.
	 *
	 * @param WC_Order $order   Order.
	 * @param array    $options Settings.
	 * @param string   $format  plain|markdown.
	 * @return string
	 */
	private function format_items( $order, $options, $format ) {
		$lines = array();

		foreach ( $order->get_items() as $item ) {
			$name = $item->get_name();

			if ( ! empty( $options['include_sku'] ) && is_callable( array( $item, 'get_product' ) ) ) {
				$product = $item->get_product();
				if ( $product && $product->get_sku() ) {
					$name .= ' (' . sprintf(
						/* translators: %s: product SKU */
						__( 'SKU: %s', 'hamidreza' ),
						$product->get_sku()
					) . ')';
				}
			}

			if ( 'markdown' === $format ) {
				$lines[] = '• ' . $this->escape_markdown( $name ) . ' × ' . absint( $item->get_quantity() );
			} else {
				$lines[] = sprintf(
					/* translators: 1: product name, 2: quantity */
					__( '• %1$s × %2$d', 'hamidreza' ),
					$name,
					$item->get_quantity()
				);
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Collect coupon codes used on the order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function format_coupons( $order ) {
		if ( ! is_callable( array( $order, 'get_coupon_codes' ) ) ) {
			return '';
		}

		$codes = $order->get_coupon_codes();

		return ! empty( $codes ) ? implode( ', ', $codes ) : '';
	}

	/**
	 * Format shipping total for the order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function format_shipping_total( $order ) {
		$total = (float) $order->get_shipping_total();

		if ( $total <= 0 ) {
			return '';
		}

		return wp_strip_all_tags( wc_price( $total ) );
	}

	/**
	 * Escape Bale legacy Markdown special characters.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function escape_markdown( $text ) {
		return str_replace(
			array( '\\', '_', '*', '[', ']', '`' ),
			array( '\\\\', '\\_', '\\*', '\\[', '\\]', '\\`' ),
			(string) $text
		);
	}

	/**
	 * Deliver to all configured platforms and chat IDs.
	 *
	 * @param array $built   Message payload.
	 * @param array $options Settings.
	 * @return array { ok: bool, message: string, results: array[] }
	 */
	private function deliver_all( $built, $options ) {
		$platforms = DOT_Bale_Settings::get_delivery_platforms( $options );
		$results   = array();
		$failures  = array();

		if ( empty( $platforms ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Bot token and chat ID are required.', 'hamidreza' ),
				'results' => array(),
			);
		}

		$button_url = isset( $built['button_url'] ) ? (string) $built['button_url'] : '';

		foreach ( $platforms as $platform_config ) {
			$chat_ids = DOT_Bale_Settings::get_platform_chat_ids( $platform_config['platform'], $options );

			if ( empty( $chat_ids ) ) {
				$results[] = array(
					'platform' => $platform_config['platform'],
					'chat_id'  => '',
					'ok'       => false,
					'message'  => __( 'Chat ID is required for this platform.', 'hamidreza' ),
				);
				$failures[] = end( $results );
				continue;
			}

			foreach ( $chat_ids as $chat_id ) {
				$dedupe_key = 'hron_sent_' . md5( wp_json_encode( array( $platform_config, $chat_id, $built['plain'] ) ) );
				if ( get_transient( $dedupe_key ) ) {
					$results[] = array(
						'platform' => $platform_config['platform'],
						'chat_id'  => $chat_id,
						'ok'       => true,
						'message'  => '',
						'skipped'  => true,
					);
					continue;
				}

				$result = $this->deliver_to(
					$built['markdown'],
					$built['plain'],
					$platform_config['platform'],
					$platform_config['bot_token'],
					$chat_id,
					$button_url
				);

				$result['platform'] = $platform_config['platform'];
				$result['chat_id']  = $chat_id;
				$results[]          = $result;

				DOT_Bale_Log::add(
					array(
						'order_id' => ! empty( $built['order_number'] ) ? $built['order_number'] : '-',
						'platform' => $platform_config['platform'],
						'status'   => ! empty( $result['ok'] ) ? 'success' : 'failed',
						'message'  => ! empty( $result['ok'] )
							? sprintf(
								/* translators: %s: chat ID */
								__( 'Delivered to chat %s', 'hamidreza' ),
								$chat_id
							)
							: ( ! empty( $result['message'] ) ? $result['message'] : __( 'Delivery failed', 'hamidreza' ) ),
					)
				);

				if ( ! empty( $result['ok'] ) ) {
					set_transient( $dedupe_key, 1, 15 * MINUTE_IN_SECONDS );
				} else {
					$failures[] = $result;
				}
			}
		}

		$ok = empty( $failures );

		return array(
			'ok'      => $ok,
			'message' => $ok ? '' : ( ! empty( $failures[0]['message'] ) ? $failures[0]['message'] : __( 'Delivery failed', 'hamidreza' ) ),
			'results' => $results,
		);
	}

	/**
	 * Deliver to a single chat on one platform.
	 *
	 * @param string $markdown   Markdown body.
	 * @param string $plain      Plain body.
	 * @param string $platform   bale.
	 * @param string $bot_token  Bot token.
	 * @param string $chat_id    Chat ID.
	 * @param string $button_url Optional inline button URL.
	 * @return array { ok: bool, message: string }
	 */
	private function deliver_to( $markdown, $plain, $platform, $bot_token, $chat_id, $button_url = '' ) {
		if ( '' === $bot_token || '' === $chat_id ) {
			return array(
				'ok'      => false,
				'message' => __( 'Bot token and chat ID are required.', 'hamidreza' ),
			);
		}

		$url = $this->api_url( $platform, $bot_token );

		$first = $this->post_message( $url, $chat_id, $markdown, 'Markdown', $button_url, $platform );
		if ( $first['ok'] ) {
			return $first;
		}

		return $this->post_message( $url, $chat_id, $plain, '', $button_url, $platform );
	}

	/**
	 * Build sendMessage endpoint.
	 *
	 * @param string $platform bale.
	 * @param string $token    Bot token.
	 * @return string
	 */
	private function api_url( $platform, $token ) {
		return 'https://tapi.bale.ai/bot' . $token . '/sendMessage';
	}

	/**
	 * POST sendMessage.
	 *
	 * @param string $url        Endpoint.
	 * @param string $chat_id    Chat ID.
	 * @param string $text       Message text.
	 * @param string $parse      Parse mode or empty.
	 * @param string $button_url Optional inline button URL.
	 * @param string $platform   Optional platform name (bale).
	 * @return array { ok: bool, message: string }
	 */
	private function post_message( $url, $chat_id, $text, $parse, $button_url = '', $platform = 'bale' ) {
		$options = DOT_Bale_Settings::get();

		$body = array(
			'chat_id' => $chat_id,
			'text'    => $text,
		);

		if ( '' !== $parse ) {
			$body['parse_mode'] = $parse;
		}

		// Bale: disable link previews for URLs in message text (core.telegram.org/bots/api#sendmessage).
		$body['disable_web_page_preview'] = true;

		// Silent notification.
		if ( ! empty( $options['silent_notification'] ) ) {
			$body['disable_notification'] = true;
		}

		if ( '' !== $button_url ) {
			$body['reply_markup'] = wp_json_encode(
				array(
					'inline_keyboard' => array(
						array(
							array(
								'text' => __( 'Open order', 'hamidreza' ),
								'url'  => esc_url_raw( $button_url ),
							),
						),
					),
				)
			);
		}

		// دکمه‌های شیشه‌ای (باز کردن سفارش / پردازش شده / تحویل پست) روی اعلان‌های سفارش
		if ( $this->ctx_order_id && class_exists( 'DOT_Bale_Actions' ) ) {
			$ctx_order = wc_get_order( $this->ctx_order_id );
			if ( $ctx_order instanceof WC_Order ) {
				$body['reply_markup'] = wp_json_encode( DOT_Bale_Actions::keyboard( $ctx_order ) );
			}
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		$ok      = $code >= 200 && $code < 300 && ! empty( $decoded['ok'] );
		$api_msg = '';

		if ( is_array( $decoded ) && ! empty( $decoded['description'] ) ) {
			$api_msg = (string) $decoded['description'];
		} elseif ( $code < 200 || $code >= 300 ) {
			$api_msg = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Unexpected API response (%d).', 'hamidreza' ),
				$code
			);
		}

		return array(
			'ok'      => $ok,
			'message' => $api_msg,
		);
	}
}
