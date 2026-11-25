<?php
defined( 'ABSPATH' ) || exit;

const DOT_ST_PROCESSED = 'dot-processed'; // وضعیت جدید: پردازش شده
const DOT_ST_SHIPPED   = 'dot-shipped';   // تحویل به شرکت حمل

/** تبدیل ارقام فارسی/عربی به انگلیسی */
function dot_en_digits( $s ) {
	return strtr( (string) $s, array(
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	) );
}

/** تبدیل ارقام انگلیسی به فارسی */
function dot_fa_digits( $s ) {
	return strtr( (string) $s, array(
		'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
		'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
	) );
}

function dot_settings() {
	$s = wp_parse_args( get_option( 'dot_settings', array() ), array(
		'days'      => 3,
		'auto_sync' => 1,
	) );
	$s['days'] = max( 1, (int) $s['days'] );
	return $s;
}

/** اسلاگ‌هایی (بدون wc-) که معادل «تحویل به شرکت حمل» هستند */
function dot_shipped_slugs() {
	return array( DOT_ST_SHIPPED, 'shipped' );
}

/**
 * اسلاگ واقعیِ وضعیت «تحویل به شرکت حمل» روی این سایت.
 * اگر وضعیت «ارسال شده» (wc-shipped) از افزونه‌ی دیگری وجود داشته باشد همان استفاده می‌شود
 * (نامش به «تحویل به شرکت حمل» عوض شده)، وگرنه وضعیت خودِ افزونه (dot-shipped).
 * استفاده از اسلاگ ثبت‌نشده باعث می‌شود ووکامرس سفارش را به «در انتظار پرداخت» برگرداند.
 */
function dot_shipped_target() {
	$all = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
	return isset( $all['wc-shipped'] ) ? 'shipped' : DOT_ST_SHIPPED;
}

/** شماره مرحله (۰ تا ۴) بر اساس وضعیت سفارش. ۰ = هنوز وارد مراحل نشده */
function dot_step_for_status( $status ) {
	$status = preg_replace( '/^wc-/', '', (string) $status );
	if ( 'processing' === $status ) {
		return 1;
	}
	if ( DOT_ST_PROCESSED === $status ) {
		return 2;
	}
	if ( in_array( $status, dot_shipped_slugs(), true ) ) {
		return 3;
	}
	if ( 'completed' === $status ) {
		return 4;
	}
	return 0;
}

/** لیست شرکت‌های حمل. با فیلتر dot_carriers قابل افزودن است. */
function dot_carriers() {
	$logo = '';
	foreach ( array( 'png', 'webp', 'jpg', 'svg' ) as $ext ) {
		if ( file_exists( DOT_PATH . 'assets/img/post-logo.' . $ext ) ) {
			$logo = DOT_URL . 'assets/img/post-logo.' . $ext;
			break;
		}
	}
	return apply_filters( 'dot_carriers', array(
		'post' => array(
			'label' => 'پست جمهوری اسلامی ایران',
			'short' => 'پست',
			'base'  => 'https://tracking.post.ir/',
			'url'   => 'https://tracking.post.ir/?id={code}',
			'logo'  => $logo,
		),
	) );
}

function dot_tracking_url( $carrier_key, $code ) {
	$carriers = dot_carriers();
	if ( empty( $carriers[ $carrier_key ] ) ) {
		return '';
	}
	$c = $carriers[ $carrier_key ];
	if ( '' === (string) $code ) {
		return $c['base'];
	}
	return str_replace( '{code}', rawurlencode( $code ), $c['url'] );
}

/* ---------------- تاریخ شمسی ---------------- */

function dot_g2j( $gy, $gm, $gd ) {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 )
		+ (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
	$days %= 12053;
	$jy   += 4 * (int) ( $days / 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy  += (int) ( ( $days - 1 ) / 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + (int) ( $days / 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + (int) ( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

/** تاریخ شمسی از timestamp؛ مثال: ۱۴۰۵/۰۸/۰۶ - ۱۱:۵۸ */
function dot_jdate( $ts, $with_time = false ) {
	if ( ! $ts ) {
		return '';
	}
	// نکته: wp_date ممکن است توسط افزونه‌های شمسی (مثل WP-Parsidate) فیلتر شود و خودش تاریخ شمسی بدهد،
	// بنابراین از DateTime خام با منطقه زمانی سایت استفاده می‌کنیم تا همیشه میلادی بخوانیم.
	$dt = new DateTime( '@' . (int) $ts );
	$dt->setTimezone( function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Asia/Tehran' ) );
	list( $jy, $jm, $jd ) = dot_g2j( (int) $dt->format( 'Y' ), (int) $dt->format( 'n' ), (int) $dt->format( 'j' ) );
	$out = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	if ( $with_time ) {
		$out .= ' - ' . $dt->format( 'H:i' );
	}
	return dot_fa_digits( $out );
}

/** نرمال‌سازی شماره موبایل ایران به 09xxxxxxxxx؛ در صورت نامعتبر بودن رشته خالی */
function dot_normalize_phone( $phone ) {
	$p = preg_replace( '/[^0-9]/', '', dot_en_digits( $phone ) );
	if ( preg_match( '/^(?:98|0098|0)?(9\d{9})$/', $p, $m ) ) {
		return '0' . $m[1];
	}
	return '';
}

/** همه شکل‌های محتمل ذخیره‌ی یک شماره موبایل */
function dot_phone_variants( $phone09 ) {
	$core = substr( $phone09, 1 );
	return array( '0' . $core, $core, '98' . $core, '0098' . $core );
}

/** لوگوی سایت (لوگوی سفارشی پوسته، در غیر این صورت نماد سایت، در غیر این صورت نام سایت) */
function dot_site_logo_html() {
	$html = '';
	$id   = (int) get_theme_mod( 'custom_logo' );
	if ( ! $id ) {
		$id = (int) get_option( 'site_logo' ); // لوگوی ویرایشگر بلوکی
	}
	if ( ! $id ) {
		$id = (int) get_option( 'site_icon' );
	}
	if ( $id ) {
		$html = wp_get_attachment_image( $id, 'medium', false, array(
			'class'   => 'dot-logo__img',
			'alt'     => get_bloginfo( 'name' ),
			'loading' => 'lazy',
		) );
	}
	if ( '' === $html ) {
		$html = '<span class="dot-logo__text">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
	}
	return '<div class="dot-logo"><a href="' . esc_url( home_url( '/' ) ) . '">' . $html . '</a></div>';
}

/**
 * ثبت کد رهگیری روی سفارش (از پنل ادمین یا از ربات بله).
 * اگر سفارش هنوز به «تحویل به شرکت حمل» نرسیده و لغو/تکمیل/ناموفق نیست (مثلاً در انتظار پرداخت، در انتظار بررسی، در انتظار پردازش، پردازش شده)، خودکار به «تحویل به شرکت حمل» می‌رود.
 *
 * @return bool true اگر وضعیت سفارش تغییر کرد
 */
function dot_apply_tracking( WC_Order $order, $code, $carrier = 'post', $by = '' ) {
	$code = preg_replace( '/\s+/', '', dot_en_digits( (string) $code ) );
	$old  = (string) $order->get_meta( '_dot_tracking_code' );

	$order->update_meta_data( '_dot_carrier', $carrier );
	$order->update_meta_data( '_dot_tracking_code', $code );
	if ( '' !== $code && ! $order->get_meta( '_dot_shipped_at' ) ) {
		$order->update_meta_data( '_dot_shipped_at', time() ); // تاریخ ارسال خودکار
	}
	$order->save_meta_data();

	if ( '' === $code ) {
		return false;
	}
	if ( $code !== $old ) {
		$order->add_order_note( sprintf( 'کد رهگیری %s ثبت شد%s.', $code, '' !== $by ? ' (' . $by . ')' : '' ) );
	}
	// فقط وقتی کد جدید ثبت شده؛ ذخیره‌ی دوباره‌ی همان کد وضعیت را عوض نمی‌کند
	if ( $code !== $old && ! in_array( $order->get_status(), array_merge( array( 'completed', 'cancelled', 'refunded', 'failed', 'trash', 'draft', 'auto-draft', 'checkout-draft' ), dot_shipped_slugs() ), true ) ) {
		$order->update_status( dot_shipped_target(), 'تحویل به شرکت حمل پس از ثبت کد رهگیری.' );
		if ( ! in_array( $order->get_status(), dot_shipped_slugs(), true ) ) {
			$order->add_order_note( 'تغییر وضعیت به «تحویل به شرکت حمل» انجام نشد؛ وضعیت فعلی: ' . wc_get_order_status_name( $order->get_status() ) );
			return false;
		}
		return true;
	}
	return false;
}

/** پیدا کردن سفارش با شماره (پشتیبانی از افزونه‌های شماره‌ی ترتیبی با فیلتر dot_find_order_by_number) */
function dot_find_order_by_number( $number ) {
	$number = preg_replace( '/\D+/', '', dot_en_digits( (string) $number ) );
	if ( '' === $number ) {
		return null;
	}
	$order = apply_filters( 'dot_find_order_by_number', null, $number );
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( (int) $number );
	}
	return ( $order instanceof WC_Order && 'shop_order' === $order->get_type() ) ? $order : null;
}
