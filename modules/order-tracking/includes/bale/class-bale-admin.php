<?php
/**
 * صفحه‌ی تنظیمات اعلان بله (ووکامرس > اعلان‌های بله)
 *
 * @package DeiaraOrderTracking
 */

defined( 'ABSPATH' ) || exit;

class DOT_Bale_Admin {

	const PAGE = 'dot-bale';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_dot_bale_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_dot_bale_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_dot_bale_send_order', array( __CLASS__, 'ajax_send_order' ) );
		add_action( 'wp_ajax_dot_bale_webhook', array( __CLASS__, 'ajax_webhook' ) );
		add_action( 'wp_ajax_dot_bale_clear_logs', array( __CLASS__, 'ajax_clear_logs' ) );
	}

	public static function menu() {
		add_submenu_page( 'woocommerce', 'اعلان‌های بله', 'اعلان‌های بله', 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function register() {
		register_setting( 'dot_bale_group', DOT_Bale_Settings::OPTION_KEY, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'dot-bale', DOT_URL . 'assets/css/bale-admin.css', array(), DOT_VERSION . '.' . filemtime( DOT_PATH . 'assets/css/bale-admin.css' ) );
		wp_enqueue_script( 'dot-bale', DOT_URL . 'assets/js/bale-admin.js', array(), DOT_VERSION . '.' . filemtime( DOT_PATH . 'assets/js/bale-admin.js' ), true );
		wp_localize_script( 'dot-bale', 'DOT_BALE', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'dot_bale' ),
			'key'   => DOT_Bale_Settings::OPTION_KEY,
		) );
	}

	/* ---------- ذخیره ---------- */

	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		$previous = get_option( DOT_Bale_Settings::OPTION_KEY, array() );
		$previous = is_array( $previous ) ? $previous : array();

		$out = $previous; // کلیدهای ناشناخته/قدیمی حفظ شوند

		$out['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;

		// توکن هیچ‌وقت در فرم چاپ نمی‌شود؛ خالی = همان توکن قبلی
		$new_token = isset( $input['bale_bot_token'] ) ? DOT_Bale_Settings::sanitize_bot_token( $input['bale_bot_token'] ) : '';
		if ( '' !== $new_token ) {
			$out['bale_bot_token'] = $new_token;
		} elseif ( ! isset( $out['bale_bot_token'] ) ) {
			$out['bale_bot_token'] = '';
		}

		$out['bale_chat_ids'] = isset( $input['bale_chat_ids'] ) ? DOT_Bale_Settings::sanitize_chat_ids( $input['bale_chat_ids'] ) : '';
		$out['platform']      = 'bale';

		foreach ( array( 'include_sku', 'include_customer_note', 'include_coupon', 'include_shipping', 'silent_notification', 'notify_on_create' ) as $k ) {
			$out[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
		}
		$out['message_template'] = isset( $input['message_template'] ) ? sanitize_textarea_field( $input['message_template'] ) : '';

		$allowed  = function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : array();
		$statuses = array();
		if ( isset( $input['statuses'] ) && is_array( $input['statuses'] ) ) {
			foreach ( $input['statuses'] as $st ) {
				$st = sanitize_text_field( $st );
				if ( in_array( $st, $allowed, true ) ) {
					$statuses[] = $st;
				}
			}
		}
		$out['statuses'] = $statuses ? array_values( array_unique( $statuses ) ) : array( 'wc-processing', 'wc-completed' );

		if ( $out['enabled'] && ! DOT_Bale_Settings::has_credentials( wp_parse_args( $out, DOT_Bale_Settings::defaults() ) ) ) {
			$out['enabled'] = 0;
			add_settings_error( DOT_Bale_Settings::OPTION_KEY, 'dot_bale_incomplete', 'تا وقتی توکن ربات و شناسه چت را وارد نکنید، اعلان‌ها خاموش می‌مانند.', 'error' );
		}
		return $out;
	}

	/* ---------- AJAX ---------- */

	protected static function guard() {
		check_ajax_referer( 'dot_bale', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی کافی ندارید.' ), 403 );
		}
	}

	/** تنظیمات فعلی فرم (بدون ذخیره) برای تست و پیش‌نمایش */
	protected static function options_from_request() {
		$stored = DOT_Bale_Settings::get();
		$token  = isset( $_POST['bale_bot_token'] ) ? DOT_Bale_Settings::sanitize_bot_token( wp_unslash( $_POST['bale_bot_token'] ) ) : '';
		return wp_parse_args( array(
			'bale_bot_token'        => '' !== $token ? $token : DOT_Bale_Settings::get_bale_token( $stored ),
			'bale_chat_ids'         => isset( $_POST['bale_chat_ids'] ) ? DOT_Bale_Settings::sanitize_chat_ids( wp_unslash( $_POST['bale_chat_ids'] ) ) : '',
			'include_sku'           => ! empty( $_POST['include_sku'] ) ? 1 : 0,
			'include_customer_note' => ! empty( $_POST['include_customer_note'] ) ? 1 : 0,
			'include_coupon'        => ! empty( $_POST['include_coupon'] ) ? 1 : 0,
			'include_shipping'      => ! empty( $_POST['include_shipping'] ) ? 1 : 0,
			'message_template'      => isset( $_POST['message_template'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message_template'] ) ) : '',
			'platform'              => 'bale',
		), DOT_Bale_Settings::defaults() );
	}

	public static function ajax_test() {
		self::guard();
		$opt   = self::options_from_request();
		$token = DOT_Bale_Settings::get_bale_token( $opt );
		$ids   = DOT_Bale_Settings::get_bale_chat_ids( $opt );
		if ( '' === $token || ! $ids ) {
			wp_send_json_error( array( 'message' => 'ابتدا توکن ربات و شناسه چت بله را وارد کنید.' ) );
		}
		$text     = 'پیام آزمایشی از ' . wp_strip_all_tags( get_bloginfo( 'name' ) ) . ": اعلان‌های بله با موفقیت متصل شد ✅";
		$notifier = new DOT_Bale_Notifier();
		$ok       = 0;
		$fail     = '';
		foreach ( $ids as $id ) {
			$res = $notifier->send_test( 'bale', $token, $id, $text, $text );
			if ( ! empty( $res['ok'] ) ) {
				$ok++;
			} elseif ( '' === $fail ) {
				$fail = ! empty( $res['message'] ) ? $res['message'] : 'ارسال ناموفق بود.';
			}
		}
		if ( $ok === count( $ids ) ) {
			wp_send_json_success( array( 'message' => 'پیام آزمایشی ارسال شد.' ) );
		}
		wp_send_json_error( array( 'message' => ( $ok ? 'ارسال برای بعضی چت‌ها ناموفق بود: ' : 'ارسال ناموفق بود: ' ) . $fail ) );
	}

	public static function ajax_preview() {
		self::guard();
		$notifier = new DOT_Bale_Notifier();
		$built    = $notifier->build_preview( self::options_from_request() );
		wp_send_json_success( array( 'plain' => isset( $built['plain'] ) ? $built['plain'] : '' ) );
	}

	public static function ajax_send_order() {
		self::guard();
		if ( ! DOT_Bale_Settings::is_ready() ) {
			wp_send_json_error( array( 'message' => 'اعلان‌های بله فعال نیست یا توکن و شناسه چت کامل نیست. ابتدا تنظیمات را ذخیره کنید.' ) );
		}
		$number = isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '';
		$order  = dot_find_order_by_number( $number );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'سفارشی با این شماره پیدا نشد.' ) );
		}
		$ok   = 0;
		$fail = '';
		$ids  = DOT_Bale_Settings::get_bale_chat_ids();
		foreach ( $ids as $chat ) {
			$res = DOT_Bale_Actions::send_order_to_chat( $order, $chat, 'سفارش از پنل مدیریت به گروه ارسال شد' );
			if ( $res['ok'] ) {
				$ok++;
			} elseif ( '' === $fail ) {
				$fail = $res['message'];
			}
		}
		if ( $ok && $ok === count( $ids ) ) {
			wp_send_json_success( array( 'message' => 'سفارش ' . dot_fa_digits( $order->get_order_number() ) . ' در گروه ارسال شد ✅' ) );
		}
		wp_send_json_error( array( 'message' => 'ارسال ناموفق بود: ' . $fail ) );
	}

	public static function ajax_webhook() {
		self::guard();
		$res = DOT_Bale_Actions::set_webhook();
		if ( $res['ok'] ) {
			wp_send_json_success( array( 'message' => 'وب‌هوک در بله ثبت شد؛ دکمه‌ها فعال هستند.' ) );
		}
		wp_send_json_error( array( 'message' => 'ثبت وب‌هوک ناموفق بود: ' . $res['message'] . ' (ابتدا تنظیمات را ذخیره کنید؛ سایت باید با HTTPS در دسترس باشد.)' ) );
	}

	public static function ajax_clear_logs() {
		self::guard();
		DOT_Bale_Log::clear();
		wp_send_json_success();
	}

	/* ---------- صفحه ---------- */

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o        = DOT_Bale_Settings::get();
		$key      = DOT_Bale_Settings::OPTION_KEY;
		$token    = DOT_Bale_Settings::get_bale_token( $o );
		$ids      = implode( "\n", DOT_Bale_Settings::get_bale_chat_ids( $o ) );
		$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$selected = is_array( $o['statuses'] ) ? $o['statuses'] : array();
		$logs     = DOT_Bale_Log::get();
		$state    = DOT_Bale_Settings::status();
		$labels   = array( 'active' => 'فعال', 'misconfigured' => 'ناقص', 'disabled' => 'غیرفعال' );

		$tags = array(
			'{order_number}', '{status}', '{customer}', '{phone}', '{payment}', '{total}', '{date_jalali}', '{date_time_jalali}',
			'{customer_email}', '{shipping_method}', '{items_count}', '{city}', '{province}', '{items}', '{address}',
			'{customer_note}', '{coupon}', '{shipping}', '{order_url}', '{site_name}', '{site_url}',
		);
		?>
		<div class="wrap dot-bale" dir="rtl">
			<h1>اعلان‌های بله <span class="dot-bale__state is-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $labels[ $state ] ); ?></span></h1>
			<p>با هر تغییر وضعیت سفارش، یک پیام در بله برای شما ارسال می‌شود. تنظیمات در دیتابیس همین سایت ذخیره می‌شود و نیازی به ویرایش فایل تنظیمات نیست.</p>
			<?php settings_errors( $key ); ?>

			<form method="post" action="options.php" id="dot-bale-form">
				<?php settings_fields( 'dot_bale_group' ); ?>

				<section class="dot-bale__card">
					<h2>اتصال</h2>
					<label class="dot-bale__switch">
						<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[enabled]" value="1" <?php checked( ! empty( $o['enabled'] ) ); ?>>
						ارسال اعلان سفارش‌ها به بله فعال باشد
					</label>

					<p class="dot-bale__label"><label for="dot-bale-token">توکن ربات بله</label></p>
					<input type="password" id="dot-bale-token" name="<?php echo esc_attr( $key ); ?>[bale_bot_token]" class="regular-text" dir="ltr"
						autocomplete="new-password" placeholder="<?php echo $token ? esc_attr( DOT_Bale_Settings::mask_token( $token ) ) : '123456789:AA...'; ?>">
					<p class="description"><?php echo $token ? 'توکن ذخیره شده است؛ برای نگه‌داشتن همان، این فیلد را خالی بگذارید.' : 'توکن را از ربات‌ساز بله (@botfather) بگیرید.'; ?></p>

					<p class="dot-bale__label"><label for="dot-bale-ids">شناسه چت‌ها (هر خط یکی)</label></p>
					<textarea id="dot-bale-ids" name="<?php echo esc_attr( $key ); ?>[bale_chat_ids]" rows="3" class="large-text code" dir="ltr"><?php echo esc_textarea( $ids ); ?></textarea>
					<p class="description">به ربات پیام بدهید و شناسه چت را از getUpdates در tapi.bale.ai بخوانید.</p>

					<p>
						<button type="button" class="button" id="dot-bale-test">ارسال پیام آزمایشی</button>
						<span id="dot-bale-test-result" class="dot-bale__result" hidden></span>
					</p>
				</section>

				<section class="dot-bale__card">
					<h2>سفارش جدید و دکمه‌های شیشه‌ای</h2>
					<label class="dot-bale__switch">
						<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[notify_on_create]" value="1" <?php checked( ! empty( $o['notify_on_create'] ) ); ?>>
						هنگام ثبت سفارش هم اعلان ارسال شود (فقط اگر وضعیت سفارش در لیست «وضعیت‌هایی که اعلان می‌گیرند» تیک خورده باشد؛ هر سفارش در هر وضعیت فقط یک پیام دارد)
					</label>
					<?php
					$wh = get_option( DOT_Bale_Actions::WEBHOOK_STATE );
					?>
					<p class="description">
						برای کار کردن دکمه‌ها، آدرس وب‌هوک باید در بله ثبت شود (با ذخیره تنظیمات خودکار انجام می‌شود).
						<?php if ( is_array( $wh ) ) : ?>
							آخرین وضعیت: <strong><?php echo ! empty( $wh['ok'] ) ? 'ثبت شد ✅' : 'ناموفق ❌ ' . esc_html( $wh['message'] ); ?></strong>
						<?php endif; ?>
					</p>
					<p>
						<button type="button" class="button" id="dot-bale-webhook">ثبت دوباره وب‌هوک</button>
						<span id="dot-bale-webhook-result" class="dot-bale__result" hidden></span>
					</p>
				</section>

				<section class="dot-bale__card">
					<h2>وضعیت‌هایی که اعلان می‌گیرند</h2>
					<div class="dot-bale__chips">
						<?php foreach ( $statuses as $st_key => $st_label ) : ?>
							<label class="dot-bale__chip">
								<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[statuses][]" value="<?php echo esc_attr( $st_key ); ?>" <?php checked( in_array( $st_key, $selected, true ) ); ?>>
								<span><?php echo esc_html( $st_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="dot-bale__card">
					<h2>متن پیام</h2>
					<div class="dot-bale__chips">
						<label class="dot-bale__chip"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>[include_sku]" value="1" <?php checked( ! empty( $o['include_sku'] ) ); ?>><span>کد محصول (SKU)</span></label>
						<label class="dot-bale__chip"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>[include_customer_note]" value="1" <?php checked( ! empty( $o['include_customer_note'] ) ); ?>><span>یادداشت مشتری</span></label>
						<label class="dot-bale__chip"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>[include_coupon]" value="1" <?php checked( ! empty( $o['include_coupon'] ) ); ?>><span>کد تخفیف</span></label>
						<label class="dot-bale__chip"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>[include_shipping]" value="1" <?php checked( ! empty( $o['include_shipping'] ) ); ?>><span>هزینه ارسال</span></label>
					</div>

					<p class="dot-bale__label">قالب دلخواه (اختیاری) — برای درج، روی هر برچسب کلیک کنید:</p>
					<div class="dot-bale__tags">
						<?php foreach ( $tags as $t ) : ?>
							<button type="button" class="dot-bale__tag" data-tag="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></button>
						<?php endforeach; ?>
					</div>
					<textarea id="dot-bale-template" name="<?php echo esc_attr( $key ); ?>[message_template]" rows="10" class="large-text code"><?php echo esc_textarea( $o['message_template'] ); ?></textarea>
					<p class="description">خالی بگذارید تا قالب پیش‌فرض استفاده شود.</p>
					<p>
						<button type="button" class="button" id="dot-bale-preview">پیش‌نمایش پیام</button>
						<button type="button" class="button" id="dot-bale-reset">پاک کردن قالب</button>
					</p>
					<pre id="dot-bale-preview-out" class="dot-bale__preview" hidden></pre>

					<label class="dot-bale__switch">
						<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[silent_notification]" value="1" <?php checked( ! empty( $o['silent_notification'] ) ); ?>>
						اعلان بی‌صدا
					</label>
				</section>

				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>

			<section class="dot-bale__card">
				<h2>ارسال سفارش به گروه</h2>
				<p>شماره سفارش را وارد کنید و دکمه ارسال را بزنید تا ربات همان سفارش را (با دکمه‌های باز کردن سفارش، پردازش شده و تحویل پست) در گروه بگذارد. به تیک وضعیت‌ها کاری ندارد.</p>
				<p>
					<input type="text" id="dot-bale-order-no" class="regular-text" dir="ltr" inputmode="numeric" placeholder="مثلاً 2512" style="max-width:200px">
					<button type="button" class="button button-primary" id="dot-bale-send-order">ارسال به گروه</button>
					<span id="dot-bale-send-result" class="dot-bale__result" hidden></span>
				</p>
			</section>

			<section class="dot-bale__card">
				<h2>گزارش ارسال‌ها
					<?php if ( $logs ) : ?><button type="button" class="button button-small" id="dot-bale-clear">پاک کردن</button><?php endif; ?>
				</h2>
				<?php if ( ! $logs ) : ?>
					<p>هنوز گزارشی ثبت نشده است.</p>
				<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th>زمان</th><th>سفارش</th><th>وضعیت</th><th>توضیحات</th></tr></thead>
						<tbody>
						<?php foreach ( $logs as $row ) :
							$dt = new DateTime( '@' . (int) $row['time'] );
							$dt->setTimezone( wp_timezone() );
							?>
							<tr>
								<td><?php echo esc_html( DOT_Bale_Jalali::format_datetime( $dt, true, true ) ); ?></td>
								<td><?php echo esc_html( $row['order_id'] ); ?></td>
								<td><?php echo 'success' === $row['status'] ? '✅ موفق' : ( 'skipped' === $row['status'] ? '⏭ ارسال نشد (عمدی)' : '❌ ناموفق' ); ?></td>
								<td><?php echo esc_html( $row['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}
}
