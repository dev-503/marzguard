<?php
/**
 * پیام «سفارش جدید» در گروه بله با دکمه‌های شیشه‌ای و دریافت پاسخ دکمه‌ها (وب‌هوک)
 *
 *  - باز کردن سفارش (لینک)
 *  - پردازش شده  → وضعیت سفارش در سایت می‌شود «پردازش شده»
 *  - تحویل پست   → ربات کد رهگیری را می‌خواهد؛ با ارسال کد، ثبت و وضعیت می‌شود «تحویل به شرکت حمل»
 *
 * @package DeiaraOrderTracking
 */

defined( 'ABSPATH' ) || exit;

class DOT_Bale_API {

	/** @return array { ok: bool, message: string, result: mixed } */
	public static function call( $token, $method, array $params ) {
		if ( '' === (string) $token ) {
			return array( 'ok' => false, 'message' => 'توکن ربات خالی است.', 'result' => null );
		}
		$res = wp_remote_post( 'https://tapi.bale.ai/bot' . $token . '/' . $method, array( 'timeout' => 15, 'body' => $params ) );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message(), 'result' => null );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		$ok   = $code >= 200 && $code < 300 && is_array( $json ) && ! empty( $json['ok'] );
		return array(
			'ok'      => $ok,
			'message' => is_array( $json ) && ! empty( $json['description'] ) ? (string) $json['description'] : ( $ok ? '' : 'پاسخ نامعتبر (' . $code . ')' ),
			'result'  => is_array( $json ) && isset( $json['result'] ) ? $json['result'] : null,
		);
	}
}

class DOT_Bale_Actions {

	const SECRET_OPTION = 'dot_bale_secret';
	const HOOK_CREATED  = 'dot_bale_created';
	const WEBHOOK_STATE = 'dot_bale_webhook_state';

	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_checkout' ), 30, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_checkout_obj' ), 30, 1 );
		add_action( self::HOOK_CREATED, array( __CLASS__, 'send_created' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );

		// بعد از ذخیره‌ی تنظیمات، وب‌هوک خودکار ثبت شود
		add_action( 'update_option_' . DOT_Bale_Settings::OPTION_KEY, array( __CLASS__, 'on_settings_saved' ), 10, 0 );
		add_action( 'add_option_' . DOT_Bale_Settings::OPTION_KEY, array( __CLASS__, 'on_settings_saved' ), 10, 0 );
	}

	/* ---------- وب‌هوک ---------- */

	public static function secret() {
		$s = get_option( self::SECRET_OPTION );
		if ( ! is_string( $s ) || strlen( $s ) < 20 ) {
			$s = wp_generate_password( 32, false, false );
			update_option( self::SECRET_OPTION, $s, false );
		}
		return $s;
	}

	public static function webhook_url() {
		return rest_url( 'dot/v1/bale/' . self::secret() );
	}

	public static function on_settings_saved() {
		if ( DOT_Bale_Settings::is_ready() ) {
			self::set_webhook();
		}
	}

	/** ثبت آدرس وب‌هوک در بله */
	public static function set_webhook() {
		$token = DOT_Bale_Settings::get_bale_token();
		$res   = DOT_Bale_API::call( $token, 'setWebhook', array( 'url' => self::webhook_url() ) );
		update_option( self::WEBHOOK_STATE, array( 'time' => time(), 'ok' => $res['ok'], 'message' => $res['message'] ), false );
		return $res;
	}

	public static function register_route() {
		register_rest_route( 'dot/v1', '/bale/(?P<secret>[A-Za-z0-9]+)', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_handle' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function rest_handle( WP_REST_Request $req ) {
		if ( ! hash_equals( self::secret(), (string) $req['secret'] ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}
		$update = $req->get_json_params();
		if ( is_array( $update ) && DOT_Bale_Settings::is_ready() ) {
			try {
				if ( ! empty( $update['callback_query'] ) ) {
					self::handle_callback( $update['callback_query'] );
				} elseif ( ! empty( $update['message']['text'] ) ) {
					self::handle_text( $update['message'] );
				}
			} catch ( \Throwable $e ) {
				error_log( 'DOT Bale webhook: ' . $e->getMessage() ); // phpcs:ignore
			}
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* ---------- ارسال پیام ایجاد سفارش ---------- */

	public static function on_checkout( $order_id ) {
		self::queue( $order_id );
	}

	public static function on_checkout_obj( $order ) {
		if ( $order instanceof WC_Order ) {
			self::queue( $order->get_id() );
		}
	}

	protected static function queue( $order_id ) {
		$o = DOT_Bale_Settings::get();
		if ( empty( $o['notify_on_create'] ) || ! DOT_Bale_Settings::is_ready( $o ) ) {
			return;
		}
		// پردازش در پس‌زمینه تا تسویه‌حساب مشتری کند نشود
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_CREATED, array( (int) $order_id ), 'dot' );
		} else {
			wp_schedule_single_event( time() + 2, self::HOOK_CREATED, array( (int) $order_id ) );
		}
	}

	/**
	 * پیام ایجاد سفارش از همان مسیر اعلان وضعیت می‌گذرد، پس:
	 *  - فقط وقتی ارسال می‌شود که وضعیت فعلی سفارش در لیست وضعیت‌های انتخاب‌شده باشد
	 *    (مثلاً «در انتظار پرداخت» تیک نخورده باشد، ارسال نمی‌شود)
	 *  - قفل مشترک با اعلان تغییر وضعیت دارد، پس برای یک سفارش و یک وضعیت فقط یک پیام می‌رود
	 */
	public static function send_created( $order_id ) {
		if ( ! DOT_Bale_Settings::is_ready() ) {
			return;
		}
		$notifier = new DOT_Bale_Notifier();
		$notifier->queue_current_status( (int) $order_id );
	}

	/** دکمه‌های شیشه‌ای متناسب با وضعیت فعلی سفارش */
	public static function keyboard( WC_Order $order ) {
		$rows = array();
		$url  = $order->get_edit_order_url();
		if ( $url ) {
			$rows[] = array( array( 'text' => 'باز کردن سفارش', 'url' => esc_url_raw( $url ) ) );
		}
		$step    = dot_step_for_status( $order->get_status() );
		$actions = array();
		if ( $step < 2 && ! in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			$actions[] = array( 'text' => 'پردازش شده', 'callback_data' => 'dot:p:' . $order->get_id() );
		}
		if ( $step < 3 && ! in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			$actions[] = array( 'text' => 'تحویل پست', 'callback_data' => 'dot:s:' . $order->get_id() );
		}
		if ( $actions ) {
			$rows[] = $actions;
		}
		return array( 'inline_keyboard' => $rows );
	}

	/* ---------- پاسخ دکمه‌ها ---------- */

	protected static function allowed_chat( $chat ) {
		return in_array( (string) $chat, DOT_Bale_Settings::get_bale_chat_ids(), true );
	}

	protected static function who( $from ) {
		$name = trim( ( $from['first_name'] ?? '' ) . ' ' . ( $from['last_name'] ?? '' ) );
		if ( '' === $name && ! empty( $from['username'] ) ) {
			$name = '@' . $from['username'];
		}
		return 'بله' . ( '' !== $name ? ' - ' . $name : '' );
	}

	protected static function handle_callback( array $cb ) {
		$token = DOT_Bale_Settings::get_bale_token();
		$id    = (string) ( $cb['id'] ?? '' );
		$data  = (string) ( $cb['data'] ?? '' );
		$msg   = isset( $cb['message'] ) && is_array( $cb['message'] ) ? $cb['message'] : array();
		$chat  = (string) ( $msg['chat']['id'] ?? '' );
		$mid   = (string) ( $msg['message_id'] ?? '' );
		$from  = isset( $cb['from'] ) && is_array( $cb['from'] ) ? $cb['from'] : array();
		$uid   = (string) ( $from['id'] ?? '' );

		$answer = function ( $text ) use ( $token, $id ) {
			DOT_Bale_API::call( $token, 'answerCallbackQuery', array( 'callback_query_id' => $id, 'text' => $text ) );
		};

		if ( ! self::allowed_chat( $chat ) || ! preg_match( '/^dot:(p|s):(\d+)$/', $data, $m ) ) {
			$answer( 'این عملیات مجاز نیست.' );
			return;
		}
		$order = wc_get_order( (int) $m[2] );
		if ( ! $order ) {
			$answer( 'سفارش پیدا نشد.' );
			return;
		}
		$num = $order->get_order_number();

		if ( 'p' === $m[1] ) {
			$blocked = array( 'cancelled', 'refunded', 'failed', DOT_ST_PROCESSED, 'completed' );
			$blocked = array_merge( $blocked, dot_shipped_slugs() );
			if ( in_array( $order->get_status(), $blocked, true ) ) {
				$answer( 'وضعیت این سفارش قبلاً از این مرحله گذشته است.' );
			} else {
				$order->update_status( DOT_ST_PROCESSED, 'تغییر وضعیت از طریق ' . self::who( $from ) );
				$answer( 'سفارش ' . dot_fa_digits( $num ) . ' پردازش شده شد ✅' );
			}
			self::refresh_keyboard( $token, $chat, $mid, $order );
			return;
		}

		// تحویل پست: کد رهگیری را بخواه
		if ( dot_step_for_status( $order->get_status() ) >= 3 ) {
			$answer( 'این سفارش قبلاً تحویل شرکت حمل شده است.' );
			self::refresh_keyboard( $token, $chat, $mid, $order );
			return;
		}
		$answer( 'کد رهگیری را ارسال کنید.' );
		$prompt = DOT_Bale_API::call( $token, 'sendMessage', array(
			'chat_id'             => $chat,
			'text'                => 'کد رهگیری مربوط به سفارش ' . dot_fa_digits( $num ) . ' را تایپ و ارسال کنید.',
			'reply_to_message_id' => $mid,
		) );
		$state = array( 'order' => $order->get_id(), 'msg' => $mid );
		set_transient( 'dot_bale_wait_' . md5( $chat . '_' . $uid ), $state, 15 * MINUTE_IN_SECONDS );
		if ( $prompt['ok'] && is_array( $prompt['result'] ) && isset( $prompt['result']['message_id'] ) ) {
			set_transient( 'dot_bale_prompt_' . md5( $chat . '_' . $prompt['result']['message_id'] ), $state, DAY_IN_SECONDS );
		}
	}

	protected static function handle_text( array $msg ) {
		$text = trim( (string) $msg['text'] );
		$chat = (string) ( $msg['chat']['id'] ?? '' );
		$from = isset( $msg['from'] ) && is_array( $msg['from'] ) ? $msg['from'] : array();
		$uid  = (string) ( $from['id'] ?? '' );
		if ( '' === $text || ! self::allowed_chat( $chat ) ) {
			return;
		}

		$state = get_transient( 'dot_bale_wait_' . md5( $chat . '_' . $uid ) );
		if ( ! $state && ! empty( $msg['reply_to_message']['message_id'] ) ) {
			// در گروه‌ها ممکن است ربات پیام‌های عادی را نبیند؛ پاسخ (Reply) به پیام ربات هم پذیرفته می‌شود
			$state = get_transient( 'dot_bale_prompt_' . md5( $chat . '_' . $msg['reply_to_message']['message_id'] ) );
		}
		if ( ! is_array( $state ) || empty( $state['order'] ) ) {
			// منتظر کد رهگیری نیستیم: اگر پیام فقط «شماره سفارش» بود، آن سفارش را در گروه بگذار
			self::maybe_lookup_order( $text, $chat, $msg );
			return;
		}
		if ( '/' === $text[0] ) {
			return;
		}

		$token = DOT_Bale_Settings::get_bale_token();
		$reply = function ( $t ) use ( $token, $chat, $msg ) {
			DOT_Bale_API::call( $token, 'sendMessage', array( 'chat_id' => $chat, 'text' => $t, 'reply_to_message_id' => (string) ( $msg['message_id'] ?? '' ) ) );
		};

		$code = preg_replace( '/\s+/', '', dot_en_digits( $text ) );
		if ( ! preg_match( '/^[A-Za-z0-9]{8,35}$/', $code ) ) {
			$reply( 'کد رهگیری معتبر نیست. فقط حروف انگلیسی و عدد (بین ۸ تا ۳۵ کاراکتر) ارسال کنید.' );
			return;
		}
		$order = wc_get_order( (int) $state['order'] );
		if ( ! $order ) {
			$reply( 'سفارش پیدا نشد.' );
			return;
		}

		$carriers = dot_carriers();
		dot_apply_tracking( $order, $code, key( $carriers ), self::who( $from ) );
		delete_transient( 'dot_bale_wait_' . md5( $chat . '_' . $uid ) );

		$now = $order->get_status();
		$reply(
			'✅ کد رهگیری سفارش ' . dot_fa_digits( $order->get_order_number() ) . ' ثبت شد.' . "\n" .
			( in_array( $now, dot_shipped_slugs(), true ) ? 'وضعیت سفارش: ' . wc_get_order_status_name( $now ) : '' )
		);
		if ( ! empty( $state['msg'] ) ) {
			self::refresh_keyboard( $token, $chat, (string) $state['msg'], $order );
		}
	}

	/**
	 * استخراج شماره سفارش از متن پیام. نمونه‌های پذیرفته‌شده:
	 * 1234 — #1234 — سفارش 1234 — order 1234 — /order 1234 (ارقام فارسی هم درست است)
	 *
	 * @return string شماره یا رشته‌ی خالی
	 */
	public static function parse_order_number( $text ) {
		$t = trim( dot_en_digits( (string) $text ) );
		if ( preg_match( '/^(?:\/?(?:order|سفارش)(?:@\w+)?)?\s*#?\s*(\d{1,10})$/iu', $t, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/** پیام شماره سفارش → ارسال همان سفارش (با دکمه‌ها) در همان گروه */
	protected static function maybe_lookup_order( $text, $chat, array $msg ) {
		$number = self::parse_order_number( $text );
		if ( '' === $number ) {
			return;
		}
		$token = DOT_Bale_Settings::get_bale_token();
		$mid   = (string) ( $msg['message_id'] ?? '' );
		$order = dot_find_order_by_number( $number );

		if ( ! $order ) {
			DOT_Bale_API::call( $token, 'sendMessage', array(
				'chat_id'             => $chat,
				'text'                => 'سفارشی با شماره ' . dot_fa_digits( $number ) . ' پیدا نشد.',
				'reply_to_message_id' => $mid,
			) );
			return;
		}

		self::send_order_to_chat( $order, $chat, 'سفارش با درخواست شماره از گروه ارسال شد' );
	}

	/**
	 * ارسال پیام کامل سفارش (با سه دکمه) به یک چت — مستقل از تیک وضعیت‌ها.
	 *
	 * @return array { ok: bool, message: string }
	 */
	public static function send_order_to_chat( WC_Order $order, $chat, $log_text = 'سفارش ارسال شد' ) {
		$o        = DOT_Bale_Settings::get();
		$token    = DOT_Bale_Settings::get_bale_token( $o );
		$notifier = new DOT_Bale_Notifier();
		$built    = $notifier->build_message( $order, $order->get_status(), $o );
		$base     = array(
			'chat_id'                  => $chat,
			'reply_markup'             => wp_json_encode( self::keyboard( $order ) ),
			'disable_web_page_preview' => true,
		);
		$res = DOT_Bale_API::call( $token, 'sendMessage', $base + array( 'text' => $built['markdown'], 'parse_mode' => 'Markdown' ) );
		if ( ! $res['ok'] ) {
			$res = DOT_Bale_API::call( $token, 'sendMessage', $base + array( 'text' => $built['plain'] ) );
		}
		DOT_Bale_Log::add( array(
			'order_id' => $order->get_order_number(),
			'platform' => 'bale',
			'status'   => $res['ok'] ? 'success' : 'failed',
			'message'  => $res['ok'] ? $log_text . ' (چت ' . $chat . ')' : $res['message'],
		) );
		return $res;
	}

	/** دکمه‌های پیام اصلی را با وضعیت جدید به‌روز کن (در صورت خطا نادیده گرفته می‌شود) */
	protected static function refresh_keyboard( $token, $chat, $mid, WC_Order $order ) {
		if ( '' === $mid ) {
			return;
		}
		DOT_Bale_API::call( $token, 'editMessageReplyMarkup', array(
			'chat_id'      => $chat,
			'message_id'   => $mid,
			'reply_markup' => wp_json_encode( self::keyboard( $order ) ),
		) );
	}
}
