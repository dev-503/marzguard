<?php
defined( 'ABSPATH' ) || exit;

/**
 * افزودن مشتریان سفارش‌ها (قدیمی و جدید) به کاربران سایت با نقش customer، بدون تکرار
 */
class DOT_Customer_Sync {

	const BATCH = 40;

	public static function init() {
		add_action( 'wp_ajax_dot_sync_batch', array( __CLASS__, 'ajax_batch' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_new_order' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_new_order_obj' ), 20, 1 );
	}

	public static function on_new_order( $order_id ) {
		if ( ! dot_settings()['auto_sync'] ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::process_order( $order );
		}
	}

	public static function on_new_order_obj( $order ) {
		if ( dot_settings()['auto_sync'] && $order instanceof WC_Order ) {
			self::process_order( $order );
		}
	}

	/** پردازش دسته‌ای از سفارش‌ها از طریق AJAX */
	public static function ajax_batch() {
		check_ajax_referer( 'dot_sync', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'create_users' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی کافی ندارید.' ), 403 );
		}

		$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;

		$args = array(
			'type'     => 'shop_order',
			'status'   => 'any',
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'limit'    => self::BATCH,
			'offset'   => $offset,
			'return'   => 'objects',
			'paginate' => true,
		);
		$res    = wc_get_orders( $args );
		$total  = (int) $res->total;
		$stats  = array( 'created' => 0, 'linked' => 0, 'existing' => 0, 'skipped' => 0 );

		foreach ( $res->orders as $order ) {
			$r = self::process_order( $order );
			if ( isset( $stats[ $r ] ) ) {
				$stats[ $r ]++;
			}
		}

		$done = $offset + count( $res->orders );
		wp_send_json_success( array(
			'total'  => $total,
			'next'   => $done,
			'done'   => $done >= $total || empty( $res->orders ),
			'stats'  => $stats,
		) );
	}

	/**
	 * @return string created|linked|existing|skipped
	 */
	public static function process_order( WC_Order $order ) {
		// سفارشی که قبلاً به کاربری متصل است
		$uid = (int) $order->get_customer_id();
		if ( $uid && get_userdata( $uid ) ) {
			return 'existing';
		}

		$email = sanitize_email( $order->get_billing_email() );
		$phone = dot_normalize_phone( $order->get_billing_phone() );

		$user = self::find_user( $email, $phone );
		if ( $user ) {
			$order->set_customer_id( $user->ID );
			$order->save();
			return 'linked';
		}

		if ( '' === $phone && '' === $email ) {
			return 'skipped';
		}

		$login = $phone ? $phone : sanitize_user( $email, true );
		if ( '' === $login ) {
			return 'skipped';
		}

		// نام کاربری از قبل وجود دارد (کاربر دیگری) → همان را متصل کن
		$by_login = get_user_by( 'login', $login );
		if ( $by_login ) {
			$order->set_customer_id( $by_login->ID );
			$order->save();
			return 'linked';
		}

		$first = $order->get_billing_first_name();
		$last  = $order->get_billing_last_name();
		$name  = trim( $first . ' ' . $last );

		$new_id = wp_insert_user( array(
			'user_login'   => $login,
			'user_pass'    => wp_generate_password( 20, true, true ),
			'user_email'   => $email,
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => '' !== $name ? $name : $login,
			'nickname'     => '' !== $name ? $name : $login,
			'role'         => 'customer',
		) );

		if ( is_wp_error( $new_id ) ) {
			return 'skipped';
		}

		update_user_meta( $new_id, 'billing_first_name', $first );
		update_user_meta( $new_id, 'billing_last_name', $last );
		update_user_meta( $new_id, 'billing_phone', $phone ? $phone : $order->get_billing_phone() );
		update_user_meta( $new_id, 'billing_email', $email );
		update_user_meta( $new_id, 'billing_country', $order->get_billing_country() );
		update_user_meta( $new_id, 'billing_state', $order->get_billing_state() );
		update_user_meta( $new_id, 'billing_city', $order->get_billing_city() );
		update_user_meta( $new_id, 'billing_address_1', $order->get_billing_address_1() );
		update_user_meta( $new_id, 'billing_postcode', $order->get_billing_postcode() );

		$order->set_customer_id( $new_id );
		$order->save();
		return 'created';
	}

	/** جست‌وجوی کاربر موجود با ایمیل یا شماره موبایل (همه‌ی شکل‌های ذخیره‌سازی) */
	protected static function find_user( $email, $phone ) {
		if ( $email ) {
			$u = get_user_by( 'email', $email );
			if ( $u ) {
				return $u;
			}
		}
		if ( $phone ) {
			$variants = dot_phone_variants( $phone );

			foreach ( $variants as $v ) {
				$u = get_user_by( 'login', $v );
				if ( $u ) {
					return $u;
				}
			}
			$found = get_users( array(
				'number'     => 1,
				'fields'     => 'all',
				'meta_query' => array(
					'relation' => 'OR',
					array( 'key' => 'billing_phone', 'value' => $variants, 'compare' => 'IN' ),
					array( 'key' => 'digits_phone_no', 'value' => $variants, 'compare' => 'IN' ),
					array( 'key' => 'mobile', 'value' => $variants, 'compare' => 'IN' ),
				),
			) );
			if ( $found ) {
				return $found[0];
			}
		}
		return null;
	}
}
