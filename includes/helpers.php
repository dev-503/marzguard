<?php
/**
 * Shared helpers for MarzGard.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default plugin options.
 *
 * @return array
 */
function marzgard_default_options() {
	return array(
		// General.
		'brand_name'      => 'مرزگارد',
		'brand_tagline'   => 'ورود امن به حساب کاربری',
		'auth_mode'       => 'login_register', // login | register | login_register.
		'default_role'    => 'subscriber',
		'redirect_url'    => '',
		'replace_wp_login'=> 0,
		'enable_register' => 1,
		'auto_register'   => 1,

		// SMS.
		'sms_gateway'     => 'log', // legacy: log | rest.
		'sms_provider'    => 'log', // log | melipayamak | faraz | kavenegar | smsir | custom.
		'sms_username'    => '',
		'sms_password'    => '',
		'sms_template_id' => '',
		'sms_param_name'  => 'code',
		'sms_endpoint'    => '',
		'sms_method'      => 'POST',
		'sms_headers'     => '{"Content-Type":"application/json"}',
		'sms_body'        => '{"to":"{to}","text":"{message}"}',
		'sms_success_path'=> '',
		'sms_api_key'     => '',
		'sms_sender'      => '',
		'sms_template'    => "کد تأیید مرزگارد شما: {code}\nاین کد تا {minutes} دقیقه معتبر است.",
		'sms_pattern_template' => '', // e.g. "pattern:12345\ncode:{code}" for pattern gateways.
		'email_subject'   => 'کد تأیید مرزگارد',
		'email_template'  => "کد تأیید ورود شما:\n{code}\nاین کد تا {minutes} دقیقه معتبر است.",

		// Security.
		'otp_length'      => 6,
		'otp_ttl'         => 300,
		'max_send_per_hour'=> 5,
		'max_send_per_number' => 5,
		'max_verify_tries'=> 5,
		'resend_wait'     => 120,
		'captcha_provider'=> 'none', // none | internal | recaptcha | turnstile | arcaptcha.
		'captcha_site_key'=> '',
		'captcha_secret_key'=> '',
		'recaptcha_version' => 'v2', // v2 | v3.
		'captcha_min_score'=> 0.5,
		'captcha_after_fails' => 0, // 0 = always (when enabled).
		'max_ip_fails'    => 10,
		'lockout_duration'=> 900,
		'hide_login_errors'=> 1,
		'log_security_events' => 1,
		'blocked_phones'  => '',
		'blocked_emails'  => '',
		'blocked_ips'     => '',
		'allow_admin_password' => 1,
		'otp_channel'     => 'sms', // sms | email | voice | bale.
		'voice_endpoint'  => '',
		'bale_chat_id'    => '',
		// Retention marketing.
		'rt_abandoned'      => 0,
		'rt_abandoned_delay'=> 30,
		'rt_abandoned_msg'  => 'سبد خریدتان در {{site}} باقی مانده است. برای تکمیل خرید اینجا کلیک کنید: {{link}}',
		'rt_payment'        => 0,
		'rt_payment_delay'  => 2,
		'rt_payment_msg'    => 'سفارش {{order}} شما هنوز پرداخت نشده است. پرداخت را تکمیل کنید: {{link}}',
		'rt_winback'        => 0,
		'rt_winback_days'   => 30,
		'rt_winback_msg'    => 'مدتهاست که ما را ندیدید :) از کد {{coupon}} برای تخفیف بعدی استفاده کنید.',
		'rt_channel'        => 'sms', // sms | email | bale.
		'rt_coupon'         => '',
		'rt_max_per_customer'=> 3,
		'rt_quiet_hours'    => 0,

		'dev_expose_otp'  => 0,

		// Appearance (Sorkhab tokens).
		'primary_color'   => '#009595',
		'radius'          => 12,
		'dark_mode'       => 'auto', // auto | light | dark.
		'font_size'       => 14,
		'locale'          => 'fa_IR',

		// Advanced (imported from the onlinepayamak section map).
		'custom_css'      => '',
	);
}

/**
 * Keys that are toggles stored as 0/1 in the database.
 *
 * @return array
 */
function marzgard_boolean_keys() {
	return array(
		'enable_register', 'replace_wp_login', 'auto_register', 'allow_admin_password',
		'hide_login_errors', 'log_security_events', 'dev_expose_otp',
		'rt_abandoned', 'rt_payment', 'rt_winback',
	);
}

/**
 * Get a single option or all options.
 *
 * @param string $key     Option key.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function marzgard_option( $key = '', $default = null ) {
	static $cache    = null;
	static $cache_for = -1;

	// Invalidated by marzgard_flush_options_cache() on every option write.
	$epoch = isset( $GLOBALS['marzgard_options_epoch'] ) ? (int) $GLOBALS['marzgard_options_epoch'] : 0;
	if ( null === $cache || $cache_for !== $epoch ) {
		$stored  = get_option( 'marzgard_options', array() );
		$cache   = wp_parse_args( is_array( $stored ) ? $stored : array(), marzgard_default_options() );
		$cache_for = $epoch;
	}

	if ( '' === $key ) {
		return $cache;
	}

	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}

	return $default;
}

/**
 * Bump the request-level options cache epoch.
 */
function marzgard_flush_options_cache() {
	$GLOBALS['marzgard_options_epoch'] = isset( $GLOBALS['marzgard_options_epoch'] ) ? (int) $GLOBALS['marzgard_options_epoch'] + 1 : 1;
}
add_action( 'update_option_marzgard_options', 'marzgard_flush_options_cache' );
add_action( 'add_option_marzgard_options', 'marzgard_flush_options_cache' );

/**
 * Resolve the real client IP without trusting a user-supplied proxy header.
 *
 * X-Forwarded-For is only honoured when REMOTE_ADDR is an explicitly trusted
 * proxy (MARZGARD_TRUSTED_PROXIES constant or marzgard_trusted_proxies filter).
 *
 * @return string
 */
function marzgard_client_ip() {
	$remote = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	$trusted = defined( 'MARZGARD_TRUSTED_PROXIES' ) && is_array( MARZGARD_TRUSTED_PROXIES ) ? MARZGARD_TRUSTED_PROXIES : array();
	$trusted = (array) apply_filters( 'marzgard_trusted_proxies', $trusted );

	if ( $remote && in_array( $remote, $trusted, true ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		$ip    = trim( $parts[0] );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
	}

	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
}

/**
 * Sanitize and merge options before saving.
 *
 * @param array $input Raw input.
 * @return array
 */
function marzgard_sanitize_options( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = marzgard_default_options();
	$clean    = array();

	foreach ( $defaults as $key => $default ) {
		$value = array_key_exists( $key, $input ) ? $input[ $key ] : $default;

		if ( in_array( $key, marzgard_boolean_keys(), true ) ) {
			$clean[ $key ] = ! empty( $value ) ? 1 : 0;
		} elseif ( is_int( $default ) ) {
			$clean[ $key ] = absint( $value );
		} elseif ( 'sms_headers' === $key || 'sms_body' === $key ) {
			// Stored as JSON strings — validate at save time and keep the default on error.
			$json = wp_kses_post( wp_unslash( $value ) );
			$clean[ $key ] = ( '' === $json || null !== json_decode( $json ) ) ? $json : $default;
		} elseif ( 'sms_template' === $key || 'sms_pattern_template' === $key ) {
			$clean[ $key ] = sanitize_textarea_field( wp_unslash( $value ) );
		} elseif ( 'custom_css' === $key ) {
			// Allow CSS text but never raw HTML/script tags.
			$clean[ $key ] = wp_strip_all_tags( wp_unslash( $value ) );
		} elseif ( 'auth_mode' === $key ) {
			$clean[ $key ] = in_array( $value, array( 'login', 'register', 'login_register' ), true ) ? $value : 'login_register';
		} elseif ( 'captcha_provider' === $key ) {
			$allowed       = array( 'none', 'internal', 'recaptcha', 'turnstile', 'arcaptcha' );
			$clean[ $key ] = in_array( $value, $allowed, true ) ? $value : 'none';
		} elseif ( 'sms_provider' === $key ) {
			$allowed       = array( 'log', 'melipayamak', 'faraz', 'kavenegar', 'smsir', 'custom' );
			$value         = (string) $value;
			if ( in_array( $value, $allowed, true ) ) {
				$clean[ $key ] = $value;
			} elseif ( 0 === strpos( $value, 'pwsms:' ) ) {
				// The gateway adapter validates the class at send time; do not
				// silently reset a saved provider when the module is not loaded
				// in the current request.
				$clean[ $key ] = $value;
			} else {
				$clean[ $key ] = 'log';
			}
		} elseif ( 'dark_mode' === $key ) {
			$clean[ $key ] = in_array( $value, array( 'auto', 'light', 'dark' ), true ) ? $value : 'auto';
		} elseif ( 'otp_channel' === $key ) {
			$clean[ $key ] = in_array( $value, array( 'sms', 'email', 'voice', 'bale' ), true ) ? $value : 'sms';
		} elseif ( 'redirect_url' === $key || 'sms_endpoint' === $key ) {
			$clean[ $key ] = esc_url_raw( wp_unslash( $value ) );
		} else {
			$clean[ $key ] = sanitize_text_field( wp_unslash( $value ) );
		}
	}

	$clean['otp_length']       = min( 8, max( 4, (int) $clean['otp_length'] ) );
	$clean['otp_ttl']          = min( 1800, max( 60, (int) $clean['otp_ttl'] ) );
	$clean['font_size']        = min( 20, max( 12, (int) $clean['font_size'] ) );
	$clean['radius']           = min( 24, max( 0, (int) $clean['radius'] ) );
	$clean['primary_color']    = preg_match( '/^#[0-9a-fA-F]{6}$/', $clean['primary_color'] ) ? $clean['primary_color'] : '#009595';

	return $clean;
}

/**
 * Normalise a phone number: keep digits only.
 *
 * @param string $phone Raw phone.
 * @return string
 */
function marzgard_clean_phone( $phone ) {
	return preg_replace( '/[^0-9]/', '', (string) $phone );
}

/**
 * Build the canonical phone key used for lookups (digits compatibility).
 *
 * @param string $country_code Dial code, e.g. +98.
 * @param string $phone        Local number.
 * @return string
 */
function marzgard_phone_key( $country_code, $phone ) {
	$country_code = preg_replace( '/[^0-9+]/', '', (string) $country_code );
	if ( '' !== $country_code && '+' !== $country_code[0] ) {
		$country_code = '+' . $country_code;
	}

	return $country_code . marzgard_clean_phone( $phone );
}

/**
 * Find an existing user by phone (compatible with Digits user meta).
 *
 * @param string $phone_key Canonical phone key.
 * @return WP_User|false
 */
function marzgard_find_user_by_phone( $phone_key ) {
	$users = get_users(
		array(
			'number'     => 1,
			'meta_query' => array( // phpcs:ignore WPDB
				'relation' => 'OR',
				array(
					'key'   => 'digits_phone',
					'value' => $phone_key,
				),
				array(
					'key'   => 'billing_phone',
					'value' => marzgard_clean_phone( $phone_key ),
				),
			),
		)
	);

	return ! empty( $users ) ? $users[0] : false;
}

/**
 * Persist phone meta in a Digits-compatible way.
 *
 * @param int    $user_id      User ID.
 * @param string $country_code Dial code.
 * @param string $phone        Local number.
 */
function marzgard_set_user_phone( $user_id, $country_code, $phone ) {
	$country_code = preg_replace( '/[^0-9+]/', '', (string) $country_code );
	if ( '' !== $country_code && '+' !== $country_code[0] ) {
		$country_code = '+' . $country_code;
	}

	update_user_meta( $user_id, 'digt_countrycode', $country_code );
	update_user_meta( $user_id, 'digits_phone_no', marzgard_clean_phone( $phone ) );
	update_user_meta( $user_id, 'digits_phone', $country_code . marzgard_clean_phone( $phone ) );
	update_user_meta( $user_id, 'marzgard_verified', 1 );
}

/**
 * Countries supported by the picker (RTL/Persian labels).
 *
 * @return array
 */
function marzgard_countries() {
	static $countries = null;

	if ( null !== $countries ) {
		return $countries;
	}

	$countries = array(
		array( 'code' => '+98',  'name' => 'ایران',              'flag' => '🇮🇷', 'iso' => 'IR' ),
		array( 'code' => '+1',   'name' => 'آمریکا',             'flag' => '🇺🇸', 'iso' => 'US' ),
		array( 'code' => '+44',  'name' => 'انگلستان',           'flag' => '🇬🇧', 'iso' => 'GB' ),
		array( 'code' => '+49',  'name' => 'آلمان',              'flag' => '🇩🇪', 'iso' => 'DE' ),
		array( 'code' => '+33',  'name' => 'فرانسه',             'flag' => '🇫🇷', 'iso' => 'FR' ),
		array( 'code' => '+39',  'name' => 'ایتالیا',            'flag' => '🇮🇹', 'iso' => 'IT' ),
		array( 'code' => '+34',  'name' => 'اسپانیا',            'flag' => '🇪🇸', 'iso' => 'ES' ),
		array( 'code' => '+7',   'name' => 'روسیه',              'flag' => '🇷🇺', 'iso' => 'RU' ),
		array( 'code' => '+90',  'name' => 'ترکیه',              'flag' => '🇹🇷', 'iso' => 'TR' ),
		array( 'code' => '+971', 'name' => 'امارات متحده عربی', 'flag' => '🇦🇪', 'iso' => 'AE' ),
		array( 'code' => '+966', 'name' => 'عربستان سعودی',      'flag' => '🇸🇦', 'iso' => 'SA' ),
		array( 'code' => '+974', 'name' => 'قطر',                'flag' => '🇶🇦', 'iso' => 'QA' ),
		array( 'code' => '+965', 'name' => 'کویت',               'flag' => '🇰🇼', 'iso' => 'KW' ),
		array( 'code' => '+973', 'name' => 'بحرین',              'flag' => '🇧🇭', 'iso' => 'BH' ),
		array( 'code' => '+968', 'name' => 'عمان',               'flag' => '🇴🇲', 'iso' => 'OM' ),
		array( 'code' => '+961', 'name' => 'لبنان',              'flag' => '🇱🇧', 'iso' => 'LB' ),
		array( 'code' => '+962', 'name' => 'اردن',               'flag' => '🇯🇴', 'iso' => 'JO' ),
		array( 'code' => '+20',  'name' => 'مصر',                'flag' => '🇪🇬', 'iso' => 'EG' ),
		array( 'code' => '+212', 'name' => 'مراکش',              'flag' => '🇲🇦', 'iso' => 'MA' ),
		array( 'code' => '+92',  'name' => 'پاکستان',            'flag' => '🇵🇰', 'iso' => 'PK' ),
		array( 'code' => '+91',  'name' => 'هند',                'flag' => '🇮🇳', 'iso' => 'IN' ),
		array( 'code' => '+880', 'name' => 'بنگلادش',            'flag' => '🇧🇩', 'iso' => 'BD' ),
		array( 'code' => '+86',  'name' => 'چین',                'flag' => '🇨🇳', 'iso' => 'CN' ),
		array( 'code' => '+81',  'name' => 'ژاپن',               'flag' => '🇯🇵', 'iso' => 'JP' ),
		array( 'code' => '+82',  'name' => 'کره جنوبی',          'flag' => '🇰🇷', 'iso' => 'KR' ),
		array( 'code' => '+61',  'name' => 'استرالیا',           'flag' => '🇦🇺', 'iso' => 'AU' ),
		array( 'code' => '+64',  'name' => 'نیوزیلند',           'flag' => '🇳🇿', 'iso' => 'NZ' ),
		array( 'code' => '+27',  'name' => 'آفریقای جنوبی',      'flag' => '🇿🇦', 'iso' => 'ZA' ),
		array( 'code' => '+55',  'name' => 'برزیل',              'flag' => '🇧🇷', 'iso' => 'BR' ),
		array( 'code' => '+52',  'name' => 'مکزیک',              'flag' => '🇲🇽', 'iso' => 'MX' ),
		array( 'code' => '+31',  'name' => 'هلند',               'flag' => '🇳🇱', 'iso' => 'NL' ),
		array( 'code' => '+32',  'name' => 'بلژیک',              'flag' => '🇧🇪', 'iso' => 'BE' ),
		array( 'code' => '+41',  'name' => 'سوئیس',              'flag' => '🇨🇭', 'iso' => 'CH' ),
		array( 'code' => '+46',  'name' => 'سوئد',               'flag' => '🇸🇪', 'iso' => 'SE' ),
		array( 'code' => '+47',  'name' => 'نروژ',               'flag' => '🇳🇴', 'iso' => 'NO' ),
		array( 'code' => '+358', 'name' => 'فنلاند',             'flag' => '🇫🇮', 'iso' => 'FI' ),
		array( 'code' => '+36',  'name' => 'مجارستان',           'flag' => '🇭🇺', 'iso' => 'HU' ),
		array( 'code' => '+48',  'name' => 'لهستان',             'flag' => '🇵🇱', 'iso' => 'PL' ),
		array( 'code' => '+380', 'name' => 'اوکراین',            'flag' => '🇺🇦', 'iso' => 'UA' ),
		array( 'code' => '+60',  'name' => 'مالزی',              'flag' => '🇲🇾', 'iso' => 'MY' ),
		array( 'code' => '+65',  'name' => 'سنگاپور',            'flag' => '🇸🇬', 'iso' => 'SG' ),
		array( 'code' => '+66',  'name' => 'تایلند',             'flag' => '🇹🇭', 'iso' => 'TH' ),
		array( 'code' => '+62',  'name' => 'اندونزی',            'flag' => '🇮🇩', 'iso' => 'ID' ),
		array( 'code' => '+993', 'name' => 'ترکمنستان',          'flag' => '🇹🇲', 'iso' => 'TM' ),
		array( 'code' => '+994', 'name' => 'جمهوری آذربایجان',   'flag' => '🇦🇿', 'iso' => 'AZ' ),
		array( 'code' => '+995', 'name' => 'گرجستان',            'flag' => '🇬🇪', 'iso' => 'GE' ),
		array( 'code' => '+964', 'name' => 'عراق',               'flag' => '🇮🇶', 'iso' => 'IQ' ),
		array( 'code' => '+963', 'name' => 'سوریه',              'flag' => '🇸🇾', 'iso' => 'SY' ),
		array( 'code' => '+93',  'name' => 'افغانستان',          'flag' => '🇦🇫', 'iso' => 'AF' ),
	);

	return $countries;
}

/**
 * UI strings (localised). Overridable via filter `marzgard_strings`.
 *
 * @return array
 */
function marzgard_strings() {
	$strings = array(
		'welcome'          => __( 'خوش آمدید', 'marzgard' ),
		'login_title'      => __( 'ورود به حساب', 'marzgard' ),
		'register_title'   => __( 'ایجاد حساب جدید', 'marzgard' ),
		'login_subtitle'   => __( 'شماره موبایل خود را وارد کنید تا کد تأیید ارسال شود.', 'marzgard' ),
		'register_subtitle'=> __( 'با شماره موبایل خود به سادگی عضو شوید.', 'marzgard' ),
		'phone_label'      => __( 'شماره موبایل', 'marzgard' ),
		'phone_placeholder'=> __( '9123456789', 'marzgard' ),
		'country_label'    => __( 'کشور', 'marzgard' ),
		'search_country'   => __( 'جستجوی کشور…', 'marzgard' ),
		'continue'         => __( 'ادامه', 'marzgard' ),
		'sending'          => __( 'در حال ارسال کد…', 'marzgard' ),
		'otp_title'        => __( 'کد تأیید را وارد کنید', 'marzgard' ),
		'otp_subtitle'     => __( 'کد ۶ رقمی ارسال‌شده به شماره %s را وارد نمایید.', 'marzgard' ),
		'otp_label'        => __( 'کد تأیید', 'marzgard' ),
		'verify'           => __( 'تأیید و ورود', 'marzgard' ),
		'verifying'        => __( 'در حال بررسی…', 'marzgard' ),
		'resend'           => __( 'ارسال مجدد کد', 'marzgard' ),
		'resend_in'        => __( 'ارسال مجدد تا %s ثانیه', 'marzgard' ),
		'change_number'    => __( 'تغییر شماره', 'marzgard' ),
		'remember_me'      => __( 'مرا به خاطر بسپار', 'marzgard' ),
		'have_account'     => __( 'حساب دارید؟', 'marzgard' ),
		'no_account'       => __( 'حسابی ندارید؟', 'marzgard' ),
		'login_tab'        => __( 'ورود', 'marzgard' ),
		'register_tab'     => __( 'ثبت نام', 'marzgard' ),
		'login_success'    => __( 'با موفقیت وارد شدید.', 'marzgard' ),
		'register_success' => __( 'حساب شما با موفقیت ایجاد شد.', 'marzgard' ),
		'err_empty_phone'  => __( 'لطفاً شماره موبایل را وارد کنید.', 'marzgard' ),
		'err_invalid_phone'=> __( 'شماره موبایل معتبر نیست.', 'marzgard' ),
		'err_empty_otp'    => __( 'لطفاً کد تأیید را وارد کنید.', 'marzgard' ),
		'err_invalid_otp'  => __( 'کد تأیید نادرست است.', 'marzgard' ),
		'err_expired'      => __( 'کد منقضی شده است. کد جدید درخواست کنید.', 'marzgard' ),
		'err_rate'         => __( 'درخواست‌های زیادی ارسال شد. لطفاً کمی صبر کنید.', 'marzgard' ),
		'err_generic'      => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'marzgard' ),
		'err_not_found'    => __( 'حسابی با این شماره یافت نشد. ابتدا ثبت نام کنید.', 'marzgard' ),
		'terms'            => __( 'با ادامه دادن، شما با <a href="#">شرایط و قوانین</a> موافقت می کنید.', 'marzgard' ),
		'powered_by'       => __( 'امن شده با مرزگارد', 'marzgard' ),
	);

	/**
	 * Filter UI strings.
	 *
	 * @param array $strings Strings.
	 */
	return apply_filters( 'marzgard_strings', $strings );
}

/**
 * Write a debug log line when WP_DEBUG is on.
 *
 * @param mixed $message Message.
 */
function marzgard_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( '[MarzGard] ' . print_r( $message, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}
