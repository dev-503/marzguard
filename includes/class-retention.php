<?php
/**
 * Retention marketing: abandoned cart, payment reminder and win-back campaigns.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retention campaigns.
 */
class MarzGard_Retention {

	/**
	 * Audit log option.
	 */
	const LOG_OPTION = 'marzgard_retention_log';

	/**
	 * Cron hook.
	 */
	const CRON_HOOK = 'marzgard_retention_run';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( 'wp_footer', array( $this, 'track_cart' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'order_placed' ), 10, 1 );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
	}

	/**
	 * Schedule the 15-minute cron.
	 */
	public function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'every_fifteen_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Register the custom cron interval.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function add_interval( $schedules ) {
		$schedules['every_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'هر ۱۵ دقیقه', 'marzgard' ),
		);
		return $schedules;
	}

	/**
	 * Track the current cart with a token so it can be recovered later.
	 */
	public function track_cart() {
		if ( ! marzgard_option( 'rt_abandoned' ) || ! function_exists( 'WC' ) || is_admin() || is_checkout() ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$phone = $this->current_phone();
		if ( ! $phone ) {
			return;
		}

		$token = isset( $_COOKIE['mg_cart_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['mg_cart_token'] ) ) : '';
		if ( ! $token ) {
			$token = wp_generate_password( 20, false );
			if ( ! headers_sent() ) {
				setcookie( 'mg_cart_token', $token, time() + DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
			}
		}

		$key   = 'mg_cart_' . md5( $token );
		$data  = get_transient( $key );
		$items = array();
		foreach ( $cart->get_cart() as $item ) {
			$items[] = array(
				'product_id' => $item['product_id'],
				'quantity'   => $item['quantity'],
				'variation'  => isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
			);
		}

		set_transient(
			$key,
			array(
				'phone'    => $phone,
				'items'    => $items,
				'token'    => $token,
				'updated'  => time(),
				'sent'     => isset( $data['sent'] ) ? (int) $data['sent'] : 0,
			),
			3 * DAY_IN_SECONDS
		);
	}

	/**
	 * Get the current customer's phone number.
	 *
	 * @return string
	 */
	private function current_phone() {
		if ( is_user_logged_in() ) {
			$phone = get_user_meta( get_current_user_id(), 'digits_phone_no', true );
			if ( $phone ) {
				return $phone;
			}
			$phone = get_user_meta( get_current_user_id(), 'billing_phone', true );
			if ( $phone ) {
				return $phone;
			}
		}

		if ( function_exists( 'WC' ) && WC()->customer && method_exists( WC()->customer, 'get_billing_phone' ) ) {
			return (string) WC()->customer->get_billing_phone();
		}

		return '';
	}

	/**
	 * Clear the abandoned-cart record once an order is placed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_placed( $order_id ) {
		$token = isset( $_COOKIE['mg_cart_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['mg_cart_token'] ) ) : '';
		if ( $token ) {
			delete_transient( 'mg_cart_' . md5( $token ) );
		}
	}

	/**
	 * Run all enabled campaigns.
	 *
	 * @return array
	 */
	public function run() {
		$result = array(
			'abandoned' => 0,
			'payment'   => 0,
			'winback'   => 0,
		);

		if ( marzgard_option( 'rt_abandoned' ) ) {
			$result['abandoned'] = $this->run_abandoned();
		}
		if ( marzgard_option( 'rt_payment' ) ) {
			$result['payment'] = $this->run_payment();
		}
		if ( marzgard_option( 'rt_winback' ) ) {
			$result['winback'] = $this->run_winback();
		}

		return $result;
	}

	/**
	 * Abandoned cart campaign.
	 *
	 * @return int
	 */
	private function run_abandoned() {
		global $wpdb;

		$delay = max( 5, (int) marzgard_option( 'rt_abandoned_delay' ) ) * MINUTE_IN_SECONDS;
		$like  = $wpdb->esc_like( '_transient_mg_cart_' ) . '%';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		$count = 0;
		foreach ( $rows as $row ) {
			$data = maybe_unserialize( $row->option_value );
			if ( ! is_array( $data ) || empty( $data['phone'] ) || empty( $data['items'] ) ) {
				continue;
			}
			if ( ( time() - (int) $data['updated'] ) < $delay ) {
				continue;
			}
			if ( (int) $data['sent'] >= (int) marzgard_option( 'rt_max_per_customer' ) ) {
				continue;
			}
			if ( $this->is_quiet_hours() ) {
				continue;
			}

			$token = isset( $data['token'] ) ? $data['token'] : '';
			$link  = add_query_arg( array( 'mg_recover' => $token ), home_url( '/' ) );
			$text  = $this->render( marzgard_option( 'rt_abandoned_msg' ), array( 'link' => $link ) );

			if ( $this->send( $data['phone'], $text, 'abandoned_cart', $token ) ) {
				$data['sent'] = (int) $data['sent'] + 1;
				$data['updated'] = time();
				set_transient( str_replace( '_transient_', '', $row->option_name ), $data, 3 * DAY_IN_SECONDS );
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Pending payment reminder campaign.
	 *
	 * @return int
	 */
	private function run_payment() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$delay  = max( 1, (int) marzgard_option( 'rt_payment_delay' ) ) * HOUR_IN_SECONDS;
		$orders = wc_get_orders(
			array(
				'status'       => array( 'pending', 'on-hold' ),
				'limit'        => 50,
				'date_created' => '<' . ( time() - $delay ),
			)
		);

		$count = 0;
		foreach ( $orders as $order ) {
			if ( $order->get_meta( '_mg_payment_reminded' ) || $this->is_quiet_hours() ) {
				continue;
			}
			$phone = $order->get_billing_phone();
			if ( ! $phone ) {
				continue;
			}

			$text = $this->render(
				marzgard_option( 'rt_payment_msg' ),
				array(
					'order' => $order->get_order_number(),
					'link'  => $order->get_checkout_payment_url(),
				)
			);

			if ( $this->send( $phone, $text, 'payment_reminder', (string) $order->get_id() ) ) {
				$order->update_meta_data( '_mg_payment_reminded', time() );
				$order->save();
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Win-back campaign for inactive customers.
	 *
	 * @return int
	 */
	private function run_winback() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$days  = max( 7, (int) marzgard_option( 'rt_winback_days' ) );
		$since = strtotime( '-' . $days . ' days' );
		$users = get_users(
			array(
				'number'     => 100,
				'meta_query' => array( // phpcs:ignore WPDB
					array(
						'key'     => 'digits_phone_no',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$count = 0;
		foreach ( $users as $user ) {
			if ( get_user_meta( $user->ID, '_mg_winback_sent', true ) > $since ) {
				continue;
			}
			if ( $this->is_quiet_hours() ) {
				continue;
			}

			$orders = wc_get_orders(
				array(
					'customer_id' => $user->ID,
					'status'      => array( 'completed', 'processing' ),
					'limit'       => 1,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);

			$last = ! empty( $orders ) ? (int) $orders[0]->get_date_created()->getTimestamp() : 0;
			if ( $last > $since ) {
				continue;
			}

			$text = $this->render( marzgard_option( 'rt_winback_msg' ), array( 'coupon' => marzgard_option( 'rt_coupon' ) ) );
			$phone = get_user_meta( $user->ID, 'digits_phone_no', true );

			if ( $this->send( $phone, $text, 'winback', (string) $user->ID ) ) {
				update_user_meta( $user->ID, '_mg_winback_sent', time() );
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Replace template placeholders.
	 *
	 * @param string $text    Template.
	 * @param array  $context Values.
	 * @return string
	 */
	private function render( $text, $context ) {
		$context['site'] = get_bloginfo( 'name' );
		foreach ( $context as $key => $value ) {
			$text = str_replace( '{{' . $key . '}}', (string) $value, $text );
		}
		return $text;
	}

	/**
	 * Send a retention message through the configured channel.
	 *
	 * @param string $to      Destination.
	 * @param string $message Message.
	 * @param string $type    Campaign type.
	 * @param string $ref     Reference.
	 * @return bool
	 */
	private function send( $to, $message, $type, $ref = '' ) {
		$channel = marzgard_option( 'rt_channel' );
		$error   = '';

		if ( 'email' === $channel && is_email( $to ) ) {
			$ok = wp_mail( $to, get_bloginfo( 'name' ) . ' — ' . $type, $message );
		} elseif ( 'bale' === $channel && class_exists( '\DOT_Bale_Actions' ) ) {
			$chat   = marzgard_option( 'bale_chat_id' );
			$ok     = (bool) $chat;
			$method = 'send_message_to_chat';
			if ( $ok && method_exists( '\DOT_Bale_Actions', $method ) ) {
				\DOT_Bale_Actions::$method( $chat, $message );
			}
		} else {
			$ok = marzgard_gateway()->send( '+' . ltrim( marzgard_clean_phone( $to ), '0' ), $message, $error, array( 'code' => '', 'channel' => 'retention' ) );
		}

		$this->log(
			array(
				'type'    => $type,
				'channel' => $channel,
				'to'      => $this->mask( $to ),
				'ref'     => $ref,
				'status'  => $ok ? 'ok' : 'fail',
				'error'   => $error,
			)
		);

		return (bool) $ok;
	}

	/**
	 * Mask a destination for the log.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function mask( $value ) {
		$length = strlen( $value );
		return $length > 6 ? substr( $value, 0, 3 ) . '•••' . substr( $value, -3 ) : $value;
	}

	/**
	 * Quiet hours check (23:00 – 08:00 local time).
	 *
	 * @return bool
	 */
	private function is_quiet_hours() {
		if ( ! marzgard_option( 'rt_quiet_hours' ) ) {
			return false;
		}
		$hour = (int) current_time( 'H' );
		return $hour >= 23 || $hour < 8;
	}

	/**
	 * Add a log entry.
	 *
	 * @param array $entry Entry.
	 */
	public function log( $entry ) {
		$log   = get_option( self::LOG_OPTION, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array_merge( array( 'time' => time() ), $entry );

		if ( count( $log ) > 200 ) {
			$log = array_slice( $log, -200 );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Get the retention log.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? array_reverse( $log ) : array();
	}

	/**
	 * Restore an abandoned cart from a recovery link.
	 *
	 * @param string $token Token.
	 * @return bool
	 */
	public function recover( $token ) {
		$data = get_transient( 'mg_cart_' . md5( sanitize_text_field( $token ) ) );
		if ( ! is_array( $data ) || empty( $data['items'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		WC()->cart->empty_cart();
		foreach ( $data['items'] as $item ) {
			WC()->cart->add_to_cart(
				(int) $item['product_id'],
				(int) $item['quantity'],
				(int) $item['variation']
			);
		}

		return true;
	}
}
