<?php
defined( 'ABSPATH' ) || exit;

/**
 * بخش مدیریت: متاباکس کد رهگیری در صفحه سفارش + صفحه تنظیمات
 */
class DOT_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_metabox' ), 60, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_head', array( __CLASS__, 'status_colors' ) );
	}

	/* ---------- متاباکس ---------- */

	public static function add_metabox() {
		$screen = 'shop_order';
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$screen = wc_get_page_screen_id( 'shop-order' );
		}
		add_meta_box( 'dot_tracking', 'ارسال و کد رهگیری', array( __CLASS__, 'render_metabox' ), $screen, 'side', 'high' );
	}

	public static function render_metabox( $post_or_order ) {
		$order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order ) {
			return;
		}
		$carriers = dot_carriers();
		$current  = $order->get_meta( '_dot_carrier' );
		if ( ! $current || ! isset( $carriers[ $current ] ) ) {
			$current = key( $carriers );
		}
		$code       = $order->get_meta( '_dot_tracking_code' );
		$shipped_at = (int) $order->get_meta( '_dot_shipped_at' );

		wp_nonce_field( 'dot_save_tracking', 'dot_nonce' );
		?>
		<div class="dot-mb">
			<p class="dot-mb__label">شرکت حمل‌کننده</p>
			<div class="dot-carriers">
				<?php foreach ( $carriers as $key => $c ) : ?>
					<label class="dot-carrier">
						<input type="radio" name="dot_carrier" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
						<span class="dot-carrier__box">
							<?php if ( ! empty( $c['logo'] ) ) : ?>
								<img src="<?php echo esc_url( $c['logo'] ); ?>" alt="">
							<?php endif; ?>
							<span><?php echo esc_html( $c['short'] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>

			<p class="dot-mb__label"><label for="dot_tracking_code">کد رهگیری</label></p>
			<input type="text" id="dot_tracking_code" name="dot_tracking_code" value="<?php echo esc_attr( $code ); ?>"
				class="widefat" dir="ltr" style="text-align:left" placeholder="مثلاً 24 رقم کد پستی">

			<p class="dot-mb__label">تاریخ ارسال (خودکار)</p>
			<div class="dot-mb__date">
				<?php echo $shipped_at ? esc_html( dot_jdate( $shipped_at, true ) ) : 'پس از ثبت کد رهگیری یا تغییر وضعیت به «تحویل به شرکت حمل» ثبت می‌شود'; ?>
			</div>

			<?php if ( $code ) : ?>
				<p><a href="<?php echo esc_url( dot_tracking_url( $current, $code ) ); ?>" target="_blank" rel="noopener">بررسی کد در سایت شرکت حمل ↗</a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function save_metabox( $order_id ) {
		if ( ! isset( $_POST['dot_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dot_nonce'] ) ), 'dot_save_tracking' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$carriers = dot_carriers();
		$carrier  = isset( $_POST['dot_carrier'] ) ? sanitize_key( wp_unslash( $_POST['dot_carrier'] ) ) : '';
		if ( ! isset( $carriers[ $carrier ] ) ) {
			$carrier = key( $carriers );
		}
		$code = isset( $_POST['dot_tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['dot_tracking_code'] ) ) : '';
		$code = preg_replace( '/\s+/', '', dot_en_digits( $code ) );

		// ثبت کد + تاریخ ارسال خودکار + انتقال خودکار به «تحویل به شرکت حمل»
		// (اولویت ۶۰ تا بعد از ذخیره‌ی وضعیت خودِ فرم سفارش اجرا شود)
		dot_apply_tracking( $order, $code, $carrier, 'پنل مدیریت' );
	}

	/* ---------- ظاهر مدیریت ---------- */

	public static function assets( $hook ) {
		$screen = get_current_screen();
		$is_order   = $screen && ( 'shop_order' === $screen->post_type || ( function_exists( 'wc_get_page_screen_id' ) && wc_get_page_screen_id( 'shop-order' ) === $screen->id ) );
		$is_setting = false !== strpos( (string) $hook, 'dot-settings' );
		if ( ! $is_order && ! $is_setting ) {
			return;
		}
		wp_enqueue_style( 'dot-admin', DOT_URL . 'assets/css/admin.css', array(), DOT_VERSION );
		if ( $is_setting ) {
			wp_enqueue_script( 'dot-admin', DOT_URL . 'assets/js/admin.js', array(), DOT_VERSION, true );
			wp_localize_script( 'dot-admin', 'DOT_ADMIN', array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'dot_sync' ),
			) );
		}
	}

	public static function status_colors() {
		echo '<style>
			.order-status.status-' . esc_html( DOT_ST_PROCESSED ) . '{background:#fce7f3;color:#9d174d}
			.order-status.status-' . esc_html( DOT_ST_SHIPPED ) . ',.order-status.status-shipped{background:#fbcfe8;color:#831843}
		</style>';
	}

	/* ---------- تنظیمات ---------- */

	public static function menu() {
		add_submenu_page( 'woocommerce', 'پیگیری سفارشات دیارا', 'پیگیری سفارشات دیارا', 'manage_woocommerce', 'dot-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function register_settings() {
		register_setting( 'dot_settings_group', 'dot_settings', array(
			'type'              => 'array',
			'sanitize_callback' => function ( $in ) {
				return array(
					'days'      => max( 1, min( 60, (int) dot_en_digits( $in['days'] ?? 3 ) ) ),
					'auto_sync' => empty( $in['auto_sync'] ) ? 0 : 1,
				);
			},
		) );
	}

	public static function settings_page() {
		$s = dot_settings();
		?>
		<div class="wrap dot-settings" dir="rtl">
			<h1>پیگیری سفارشات دیارا</h1>

			<form method="post" action="options.php" class="dot-panel">
				<?php settings_fields( 'dot_settings_group' ); ?>
				<h2>تنظیمات</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dot_days">تکمیل خودکار سفارش</label></th>
						<td>
							<input type="number" min="1" max="60" id="dot_days" name="dot_settings[days]" value="<?php echo esc_attr( $s['days'] ); ?>" style="width:80px">
							روز بعد از ورود سفارش به وضعیت «تحویل به شرکت حمل»، وضعیت به «تکمیل شده» تغییر کند.
						</td>
					</tr>
					<tr>
						<th scope="row">مشتریان جدید</th>
						<td>
							<label>
								<input type="checkbox" name="dot_settings[auto_sync]" value="1" <?php checked( $s['auto_sync'], 1 ); ?>>
								مشتری هر سفارش جدید، خودکار با نقش «مشتری» به کاربران سایت اضافه شود (بدون ایجاد تکراری)
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>

			<div class="dot-panel">
				<h2>افزودن مشتریان قبلی به کاربران سایت</h2>
				<p>
					همه‌ی سفارش‌های ثبت‌شده تا امروز (با هر وضعیتی) بررسی می‌شوند. مشتری‌ای که قبلاً کاربر سایت است
					(بر اساس ایمیل یا شماره موبایل) دوباره اضافه نمی‌شود و فقط سفارش به همان کاربر متصل می‌شود.
					نام کاربری برای کاربران جدید همان شماره موبایل است و رمز عبور تصادفی ساخته می‌شود؛ ایمیلی برای مشتری ارسال نمی‌شود.
				</p>
				<p>
					<button type="button" class="button button-primary" id="dot-sync-start">شروع افزودن مشتریان</button>
				</p>
				<div class="dot-progress" id="dot-progress" hidden>
					<div class="dot-progress__bar"><span id="dot-progress-fill"></span></div>
					<p id="dot-progress-text"></p>
				</div>
			</div>
		</div>
		<?php
	}
}
