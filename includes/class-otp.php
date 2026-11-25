<?php
/**
 * OTP challenge handling with hashed storage and rate limiting.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-time password manager.
 */
class MarzGard_OTP {

	/**
	 * Create a new OTP challenge.
	 *
	 * @param string $phone_key Canonical phone key.
	 * @param string $purpose   login|register.
	 * @param string $ip        Client IP.
	 * @return array|WP_Error
	 */
	public static function create( $phone_key, $purpose, $ip ) {
		$rate = self::check_rate( $phone_key, $ip, 'send' );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		// Per-number rate limit (independent of IP) prevents SMS bombing.
		$number_rate = (int) get_transient( self::key( 'num_rate_' . md5( $phone_key ) ) );
		if ( $number_rate >= (int) marzgard_option( 'max_send_per_number' ) ) {
			return new WP_Error( 'rate_limit', __( 'درخواست‌های زیادی برای این شماره ارسال شد. لطفاً کمی صبر کنید.', 'marzgard' ) );
		}

		$id       = self::new_id();
		$code     = self::new_code();
		$ttl      = (int) marzgard_option( 'otp_ttl' );
		$challenge = array(
			'hash'    => self::hash( $code ),
			'phone'   => $phone_key,
			'purpose' => $purpose,
			'attempts'=> 0,
			'expires' => time() + $ttl,
			'ip'      => $ip,
		);

		set_transient( self::key( $id ), $challenge, $ttl );
		self::bump_rate( $phone_key, $ip, 'send' );
		set_transient( self::key( 'last_' . md5( $phone_key ) ), $id, $ttl );
		set_transient( self::key( 'last_send_' . md5( $phone_key ) ), time(), HOUR_IN_SECONDS );
		set_transient( self::key( 'num_rate_' . md5( $phone_key ) ), $number_rate + 1, HOUR_IN_SECONDS );

		return array(
			'id'      => $id,
			'code'    => $code,
			'ttl'     => $ttl,
			'expires' => $challenge['expires'],
		);
	}

	/**
	 * Issue a fresh code for an existing challenge.
	 *
	 * @param string $id Challenge id.
	 * @param string $ip Client IP.
	 * @return array|WP_Error
	 */
	public static function resend( $id, $ip ) {
		$challenge = get_transient( self::key( $id ) );
		if ( ! is_array( $challenge ) ) {
			return new WP_Error( 'expired', __( 'کد منقضی شده است. کد جدید درخواست کنید.', 'marzgard' ) );
		}

		$last_send = (int) get_transient( self::key( 'last_send_' . md5( $challenge['phone'] ) ) );
		$wait      = (int) marzgard_option( 'resend_wait' );
		if ( $last_send && ( time() - $last_send ) < $wait ) {
			$remaining = $wait - ( time() - $last_send );
			return new WP_Error(
				'resend_wait',
				sprintf( __( 'لطفاً %d ثانیه دیگر کد مجدد درخواست کنید.', 'marzgard' ), $remaining ),
				array( 'seconds' => $remaining )
			);
		}

		$rate = self::check_rate( $challenge['phone'], $ip, 'send' );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$code             = self::new_code();
		$ttl              = (int) marzgard_option( 'otp_ttl' );
		$challenge['hash']    = self::hash( $code );
		$challenge['attempts'] = 0;
		$challenge['expires'] = time() + $ttl;

		set_transient( self::key( $id ), $challenge, $ttl );
		self::bump_rate( $challenge['phone'], $ip, 'send' );
		set_transient( self::key( 'last_send_' . md5( $challenge['phone'] ) ), time(), HOUR_IN_SECONDS );

		return array(
			'id'      => $id,
			'code'    => $code,
			'ttl'     => $ttl,
			'expires' => $challenge['expires'],
		);
	}

	/**
	 * Verify a submitted code.
	 *
	 * @param string $id   Challenge id.
	 * @param string $code Submitted code.
	 * @return array|WP_Error Array with phone/purpose on success.
	 */
	public static function verify( $id, $code ) {
		$challenge = get_transient( self::key( $id ) );
		if ( ! is_array( $challenge ) ) {
			return new WP_Error( 'invalid_challenge', __( 'درخواست تأیید معتبر نیست. دوباره تلاش کنید.', 'marzgard' ) );
		}

		if ( time() > (int) $challenge['expires'] ) {
			delete_transient( self::key( $id ) );
			return new WP_Error( 'expired', __( 'کد منقضی شده است. کد جدید درخواست کنید.', 'marzgard' ) );
		}

		$max = (int) marzgard_option( 'max_verify_tries' );
		if ( (int) $challenge['attempts'] >= $max ) {
			delete_transient( self::key( $id ) );
			return new WP_Error( 'too_many', __( 'تعداد تلاش‌ها بیش از حد مجاز است. کد جدید درخواست کنید.', 'marzgard' ) );
		}

		$submitted = self::hash( preg_replace( '/[^0-9]/', '', (string) $code ) );
		if ( ! hash_equals( $challenge['hash'], $submitted ) ) {
			++$challenge['attempts'];
			set_transient( self::key( $id ), $challenge, (int) $challenge['expires'] - time() );
			return new WP_Error( 'invalid', __( 'کد تأیید نادرست است.', 'marzgard' ) );
		}

		delete_transient( self::key( $id ) );
		delete_transient( self::key( 'last_' . md5( $challenge['phone'] ) ) );

		return array(
			'phone'   => $challenge['phone'],
			'purpose' => $challenge['purpose'],
		);
	}

	/**
	 * Get challenge data (without exposing the hash) for UI state.
	 *
	 * @param string $id Challenge id.
	 * @return array|false
	 */
	public static function peek( $id ) {
		$challenge = get_transient( self::key( $id ) );
		if ( ! is_array( $challenge ) ) {
			return false;
		}

		return array(
			'phone'   => $challenge['phone'],
			'purpose' => $challenge['purpose'],
			'expires' => $challenge['expires'],
		);
	}

	/**
	 * Generate a random challenge id.
	 *
	 * @return string
	 */
	private static function new_id() {
		return function_exists( 'random_bytes' )
			? bin2hex( random_bytes( 16 ) )
			: md5( wp_generate_password( 32, true, true ) . microtime( true ) );
	}

	/**
	 * Generate a numeric OTP.
	 *
	 * @return string
	 */
	private static function new_code() {
		$length = (int) marzgard_option( 'otp_length' );
		$max    = (int) str_repeat( '9', $length );
		$number = function_exists( 'random_int' ) ? random_int( 0, $max ) : wp_rand( 0, $max );

		return str_pad( (string) $number, $length, '0', STR_PAD_LEFT );
	}

	/**
	 * Hash a code for storage.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private static function hash( $code ) {
		return wp_hash( 'marzgard|' . $code );
	}

	/**
	 * Build a transient key.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function key( $name ) {
		return 'mg_' . md5( $name );
	}

	/**
	 * Check send rate limit.
	 *
	 * @param string $phone_key Phone.
	 * @param string $ip        IP.
	 * @param string $action    Action name.
	 * @return true|WP_Error
	 */
	private static function check_rate( $phone_key, $ip, $action ) {
		$limit = (int) marzgard_option( 'max_send_per_hour' );
		$count = (int) get_transient( self::key( 'rate_' . $action . '_' . md5( $phone_key . '|' . $ip ) ) );

		if ( $count >= $limit ) {
			return new WP_Error( 'rate_limit', __( 'درخواست‌های زیادی ارسال شد. لطفاً کمی صبر کنید.', 'marzgard' ) );
		}

		return true;
	}

	/**
	 * Bump the rate limit counter.
	 *
	 * @param string $phone_key Phone.
	 * @param string $ip        IP.
	 * @param string $action    Action name.
	 */
	private static function bump_rate( $phone_key, $ip, $action ) {
		$key   = self::key( 'rate_' . $action . '_' . md5( $phone_key . '|' . $ip ) );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}
}
