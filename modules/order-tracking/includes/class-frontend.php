<?php
defined( 'ABSPATH' ) || exit;

/**
 * شورت‌کد [deiara_tracking] + نمایش اطلاعات رهگیری در حساب کاربری
 */
class DOT_Frontend {

	public static function init() {
		add_shortcode( 'deiara_tracking', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_ajax_dot_track', array( __CLASS__, 'ajax_track' ) );
		add_action( 'wp_ajax_nopriv_dot_track', array( __CLASS__, 'ajax_track' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'account_box' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'dot-front', DOT_URL . 'assets/css/frontend.css', array(), DOT_VERSION . '.' . filemtime( DOT_PATH . 'assets/css/frontend.css' ) );
		wp_register_script( 'dot-front', DOT_URL . 'assets/js/frontend.js', array(), DOT_VERSION . '.' . filemtime( DOT_PATH . 'assets/js/frontend.js' ), true );
		wp_localize_script( 'dot-front', 'DOT', array( 'ajax' => admin_url( 'admin-ajax.php' ) ) );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'title' => 'پیگیری سفارش',
			'text'  => 'شماره سفارش یا شماره موبایلی که با آن سفارش ثبت کرده‌اید را وارد کنید.',
		), $atts, 'deiara_tracking' );

		wp_enqueue_style( 'dot-front' );
		wp_enqueue_script( 'dot-front' );

		ob_start();
		?>
		<div class="dot-wrap" dir="rtl">
			<?php echo dot_site_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="dot-card dot-search">
				<h3 class="dot-title"><?php echo esc_html( $atts['title'] ); ?></h3>
				<p class="dot-sub"><?php echo esc_html( $atts['text'] ); ?></p>
				<form class="dot-form" novalidate>
					<input type="hidden" name="action" value="dot_track">
					<input type="text" name="website" class="dot-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
					<input type="text" name="q" class="dot-input" inputmode="numeric" autocomplete="off"
						placeholder="شماره سفارش یا شماره موبایل" aria-label="شماره سفارش یا شماره موبایل" required>
					<button type="submit" class="dot-btn">پیگیری</button>
				</form>
				<p class="dot-error" role="alert" hidden></p>
			</div>
			<div class="dot-result" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------- AJAX ---------- */

	public static function ajax_track() {
		// محدودیت تعداد درخواست
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'dot_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 20 ) {
			wp_send_json_error( array( 'message' => 'تعداد درخواست‌ها زیاد است. چند دقیقه بعد دوباره تلاش کنید.' ) );
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

		if ( ! empty( $_POST['website'] ) ) { // honeypot
			wp_send_json_error( array( 'message' => 'درخواست نامعتبر است.' ) );
		}

		$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$q = str_replace( array( '#', ' ', '-', '+' ), '', dot_en_digits( $q ) );
		if ( '' === $q ) {
			wp_send_json_error( array( 'message' => 'لطفاً شماره سفارش یا شماره موبایل را وارد کنید.' ) );
		}

		$orders = array();
		$phone  = dot_normalize_phone( $q );

		if ( $phone ) {
			$orders = self::orders_by_phone( $phone );
		} elseif ( preg_match( '/^\d{1,12}$/', $q ) ) {
			$order = apply_filters( 'dot_find_order_by_number', null, $q );
			if ( ! $order ) {
				$order = wc_get_order( (int) $q );
			}
			if ( $order instanceof WC_Order && 'shop_order' === $order->get_type() ) {
				$orders[] = $order;
			}
		} else {
			wp_send_json_error( array( 'message' => 'ورودی نامعتبر است. شماره سفارش یا شماره موبایل را وارد کنید.' ) );
		}

		if ( ! $orders ) {
			wp_send_json_error( array( 'message' => 'سفارشی با این مشخصات پیدا نشد. لطفاً شماره را بررسی کنید.' ) );
		}

		$html = '';
		foreach ( $orders as $order ) {
			$html .= self::render_order( $order );
		}
		wp_send_json_success( array( 'html' => $html ) );
	}

	/** آخرین سفارش‌های یک شماره موبایل (حداکثر ۵ مورد) */
	protected static function orders_by_phone( $phone09 ) {
		global $wpdb;

		$variants = dot_phone_variants( $phone09 );
		$in       = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
		$hpos     = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		if ( $hpos ) {
			$t_o = $wpdb->prefix . 'wc_orders';
			$t_a = $wpdb->prefix . 'wc_order_addresses';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT o.id FROM {$t_o} o INNER JOIN {$t_a} a ON a.order_id = o.id AND a.address_type = 'billing'
				WHERE o.type = 'shop_order' AND o.status NOT IN ('trash','auto-draft','checkout-draft')
				AND REPLACE(REPLACE(REPLACE(a.phone,' ',''),'-',''),'+','') IN ($in)
				ORDER BY o.date_created_gmt DESC LIMIT 5";
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_billing_phone'
				WHERE p.post_type = 'shop_order' AND p.post_status NOT IN ('trash','auto-draft','draft')
				AND REPLACE(REPLACE(REPLACE(m.meta_value,' ',''),'-',''),'+','') IN ($in)
				ORDER BY p.ID DESC LIMIT 5";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids    = $wpdb->get_col( $wpdb->prepare( $sql, $variants ) );
		$orders = array();
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( $o instanceof WC_Order ) {
				$orders[] = $o;
			}
		}
		return $orders;
	}

	/* ---------- نمایش ---------- */

	protected static function icon( $name ) {
		$paths = array(
			'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14l2 2 4-4"/>',
			'truck'    => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
			'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
			'tick'     => '<polyline points="20 6 9 17 4 12"/>',
		);
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $name ] . '</svg>';
	}

	public static function render_order( WC_Order $order ) {
		$status = $order->get_status();
		$step   = dot_step_for_status( $status );
		$failed = in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true );

		$steps = array(
			1 => array( 'در انتظار پردازش', 'clock' ),
			2 => array( 'پردازش شده', 'clipboard' ),
			3 => array( 'تحویل به شرکت حمل', 'truck' ),
			4 => array( 'تکمیل', 'check' ),
		);

		$percent = $step > 1 ? ( ( $step - 1 ) / 3 ) * 100 : 0;
		$created = $order->get_date_created();

		ob_start();
		?>
		<div class="dot-card dot-order">
			<div class="dot-order__head">
				<div>
					<span class="dot-order__num">سفارش <?php echo esc_html( dot_fa_digits( $order->get_order_number() ) ); ?></span>
					<?php if ( $created ) : ?>
						<span class="dot-order__date"><?php echo esc_html( dot_jdate( $created->getTimestamp() ) ); ?></span>
					<?php endif; ?>
				</div>
				<span class="dot-badge <?php echo $failed ? 'is-failed' : ''; ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
			</div>

			<?php if ( $failed ) : ?>
				<p class="dot-note is-failed">این سفارش «<?php echo esc_html( wc_get_order_status_name( $status ) ); ?>» است.</p>
			<?php else : ?>
				<?php if ( 0 === $step ) : ?>
					<p class="dot-note">سفارش شما ثبت شده و پس از تأیید پرداخت وارد مراحل پردازش می‌شود.</p>
				<?php endif; ?>
				<ol class="dot-steps" style="--dot-p:<?php echo esc_attr( round( $percent, 2 ) ); ?>%">
					<?php foreach ( $steps as $i => $s ) :
						$cls = $step > $i ? 'is-done' : ( $step === $i ? 'is-current' : '' );
						if ( 4 === $i && 4 === $step ) {
							$cls = 'is-done';
						}
						?>
						<li class="dot-step <?php echo esc_attr( $cls ); ?>">
							<span class="dot-step__dot"><?php echo wp_kses( self::icon( 'is-done' === $cls ? 'tick' : $s[1] ), self::svg_kses() ); ?></span>
							<span class="dot-step__label"><?php echo esc_html( $s[0] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<?php echo self::tracking_block( $order ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** باکس اطلاعات ارسال: لوگوی شرکت حمل، کد رهگیری، تاریخ ارسال، لینک پیگیری */
	public static function tracking_block( WC_Order $order ) {
		$code = (string) $order->get_meta( '_dot_tracking_code' );
		$step = dot_step_for_status( $order->get_status() );
		if ( '' === $code && $step < 3 ) {
			return '';
		}
		$carriers = dot_carriers();
		$key      = $order->get_meta( '_dot_carrier' );
		if ( ! isset( $carriers[ $key ] ) ) {
			$key = key( $carriers );
		}
		$c          = $carriers[ $key ];
		$shipped_at = (int) $order->get_meta( '_dot_shipped_at' );
		$url        = '' !== $code ? dot_tracking_url( $key, $code ) : '';

		ob_start();
		?>
		<div class="dot-track">
			<div class="dot-track__head">
				<?php if ( ! empty( $c['logo'] ) ) : ?>
					<img src="<?php echo esc_url( $c['logo'] ); ?>" alt="<?php echo esc_attr( $c['label'] ); ?>" class="dot-track__logo">
				<?php endif; ?>
				<div>
					<div class="dot-track__title">رهگیری مرسوله</div>
					<div class="dot-track__carrier"><?php echo esc_html( $c['label'] ); ?></div>
				</div>
			</div>

			<?php if ( '' !== $code ) : ?>
				<div class="dot-track__row">
					<div class="dot-track__field">
						<span class="dot-track__k">کد رهگیری</span>
						<span class="dot-track__v">
							<bdi class="dot-code" dir="ltr"><?php echo esc_html( $code ); ?></bdi>
							<button type="button" class="dot-copy" data-code="<?php echo esc_attr( $code ); ?>">کپی</button>
						</span>
					</div>
					<?php if ( $shipped_at ) : ?>
						<div class="dot-track__field">
							<span class="dot-track__k">تاریخ ارسال</span>
							<span class="dot-track__v"><?php echo esc_html( dot_jdate( $shipped_at ) ); ?></span>
						</div>
					<?php endif; ?>
				</div>
				<a class="dot-btn dot-btn--block" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
					رهگیری مستقیم در سایت <?php echo esc_html( $c['short'] ); ?> ↗
				</a>
			<?php else : ?>
				<p class="dot-note">کد رهگیری هنوز ثبت نشده است. به‌محض ثبت، در همین بخش نمایش داده می‌شود.</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** نمایش اطلاعات رهگیری در صفحه‌ی مشاهده سفارش (حساب کاربری) */
	public static function account_box( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$html = self::tracking_block( $order );
		if ( '' === $html ) {
			return;
		}
		wp_enqueue_style( 'dot-front' );
		wp_enqueue_script( 'dot-front' );
		echo '<div class="dot-wrap" dir="rtl"><h2 class="dot-account-title">اطلاعات ارسال</h2>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	protected static function svg_kses() {
		return array(
			'svg'      => array( 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true ),
			'polyline' => array( 'points' => true ),
			'polygon'  => array( 'points' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ),
			'path'     => array( 'd' => true ),
		);
	}
}
