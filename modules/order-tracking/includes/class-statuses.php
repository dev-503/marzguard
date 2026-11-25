<?php
defined( 'ABSPATH' ) || exit;

/**
 * وضعیت‌های سفارشی + تکمیل خودکار سفارش ۳ روز پس از «تحویل به شرکت حمل»
 */
class DOT_Statuses {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_statuses' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_to_wc' ), 20 );
		add_filter( 'woocommerce_order_is_paid_statuses', array( __CLASS__, 'paid_statuses' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'bulk_actions' ), 999 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'bulk_actions' ), 999 );

		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );
		add_action( 'dot_auto_complete', array( __CLASS__, 'auto_complete' ) );
		add_action( 'dot_daily_check', array( __CLASS__, 'sweep' ) );
	}

	public static function register_statuses() {
		register_post_status( 'wc-' . DOT_ST_PROCESSED, array(
			'label'                     => 'پردازش شده',
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop( 'پردازش شده <span class="count">(%s)</span>', 'پردازش شده <span class="count">(%s)</span>' ),
		) );
		register_post_status( 'wc-' . DOT_ST_SHIPPED, array(
			'label'                     => 'تحویل به شرکت حمل',
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'تحویل به شرکت حمل <span class="count">(%s)</span>', 'تحویل به شرکت حمل <span class="count">(%s)</span>' ),
		) );
	}

	public static function add_to_wc( $statuses ) {
		$new = array();
		foreach ( $statuses as $key => $label ) {
			// اگر افزونه‌ی دیگری وضعیت «ارسال شده» ساخته بود، اسمش را عوض کن
			if ( in_array( $key, array( 'wc-shipped' ), true ) || in_array( trim( $label ), array( 'ارسال شده', 'Shipped', 'shipped' ), true ) ) {
				$label = 'تحویل به شرکت حمل';
			}
			if ( 'wc-processing' === $key ) {
				$label = 'در انتظار پردازش'; // مرحله ۱ (processing)
			}
			$new[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$new[ 'wc-' . DOT_ST_PROCESSED ] = 'پردازش شده';
				if ( ! isset( $statuses['wc-shipped'] ) ) {
					$new[ 'wc-' . DOT_ST_SHIPPED ] = 'تحویل به شرکت حمل';
				}
			}
		}
		// اگر processing وجود نداشت (حالت غیرمعمول)
		if ( ! isset( $new[ 'wc-' . DOT_ST_PROCESSED ] ) ) {
			$new[ 'wc-' . DOT_ST_PROCESSED ] = 'پردازش شده';
		}
		if ( ! isset( $new[ 'wc-' . DOT_ST_SHIPPED ] ) && ! isset( $statuses['wc-shipped'] ) ) {
			$new[ 'wc-' . DOT_ST_SHIPPED ] = 'تحویل به شرکت حمل';
		}
		return $new;
	}

	public static function paid_statuses( $statuses ) {
		$statuses[] = DOT_ST_PROCESSED;
		$statuses[] = DOT_ST_SHIPPED;
		$statuses[] = 'shipped';
		return $statuses;
	}

	/**
	 * کارهای دسته‌جمعی: افزودن وضعیت‌های جدید بدون تکرار.
	 * ووکامرس و افزونه‌های دیگر ممکن است همین وضعیت‌ها را قبلاً اضافه کرده باشند.
	 */
	public static function bulk_actions( $actions ) {
		$processed = 'mark_' . DOT_ST_PROCESSED;
		$shipped   = 'mark_' . dot_shipped_target();
		$label_p   = 'تغییر وضعیت به پردازش شده';
		$label_s   = 'تغییر وضعیت به تحویل به شرکت حمل';

		if ( isset( $actions['mark_processing'] ) ) {
			$actions['mark_processing'] = 'تغییر وضعیت به در انتظار پردازش';
		}

		// «ارسال شده» افزونه‌های دیگر همان مرحله ۳ است؛ اسمش را یکی کن
		foreach ( $actions as $key => $label ) {
			if ( 'mark_shipped' === $key || false !== mb_strpos( (string) $label, 'ارسال شده' ) ) {
				$actions[ $key ] = $label_s;
			}
		}

		if ( ! isset( $actions[ $processed ] ) ) {
			$actions[ $processed ] = $label_p;
		}
		if ( ! isset( $actions[ $shipped ] ) ) {
			$actions[ $shipped ] = $label_s;
		}

		// حذف موارد هم‌نام (فقط اولین مورد با هر عنوان می‌ماند؛ «کارهای دسته‌جمعی» و گزینه خالی دست نمی‌خورد)
		$seen   = array();
		$result = array();
		foreach ( $actions as $key => $label ) {
			$norm = is_string( $label ) ? trim( $label ) : '';
			if ( '' !== $key && '' !== $norm && 0 === strpos( (string) $key, 'mark_' ) && ! in_array( $key, array( 'mark_processing', $processed ), true ) ) {
				if ( isset( $seen[ $norm ] ) ) {
					continue;
				}
				$seen[ $norm ] = true;
			}
			$result[ $key ] = $label;
		}
		return $result;
	}

	/* ---------- تکمیل خودکار ---------- */

	public static function on_status_changed( $order_id, $from, $to, $order ) {
		$shipped = dot_shipped_slugs();

		if ( in_array( $to, $shipped, true ) ) {
			// زمان ورود به مرحله ۳ را ثبت و زمان‌بندی تکمیل خودکار را بساز
			$now = time();
			$order->update_meta_data( '_dot_shipped_status_at', $now );
			if ( ! $order->get_meta( '_dot_shipped_at' ) ) {
				$order->update_meta_data( '_dot_shipped_at', $now ); // تاریخ ارسال به صورت خودکار
			}
			$order->save_meta_data();

			$days = dot_settings()['days'];
			wp_clear_scheduled_hook( 'dot_auto_complete', array( $order_id ) );
			wp_schedule_single_event( $now + $days * DAY_IN_SECONDS, 'dot_auto_complete', array( $order_id ) );
		} elseif ( in_array( $from, $shipped, true ) ) {
			wp_clear_scheduled_hook( 'dot_auto_complete', array( $order_id ) );
		}
	}

	public static function auto_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! in_array( $order->get_status(), dot_shipped_slugs(), true ) ) {
			return;
		}
		$since = (int) $order->get_meta( '_dot_shipped_status_at' );
		$days  = dot_settings()['days'];
		if ( $since && time() < $since + $days * DAY_IN_SECONDS - 60 ) {
			return; // هنوز زمانش نرسیده (مثلاً تنظیم روزها عوض شده)
		}
		$order->update_status( 'completed', sprintf( 'تکمیل خودکار: %s روز از تحویل به شرکت حمل گذشته است.', dot_fa_digits( $days ) ) );
	}

	/** جاروی دوره‌ای: اگر زمان‌بندی تکی از دست رفته باشد، اینجا جبران می‌شود */
	public static function sweep() {
		$orders = wc_get_orders( array(
			'status' => dot_shipped_slugs(),
			'limit'  => -1,
			'return' => 'ids',
		) );
		$days = dot_settings()['days'];
		foreach ( $orders as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			$since = (int) $order->get_meta( '_dot_shipped_status_at' );
			if ( ! $since ) {
				// سفارش‌هایی که قبل از نصب افزونه در این وضعیت بوده‌اند: از همین لحظه شمارش کن
				$order->update_meta_data( '_dot_shipped_status_at', time() );
				$order->save_meta_data();
				continue;
			}
			if ( time() >= $since + $days * DAY_IN_SECONDS ) {
				self::auto_complete( $id );
			}
		}
	}
}
