<?php
/**
 * MarzGard security layer: captcha, lockout, block lists and audit log.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security helpers.
 */
class MarzGard_Security {

	/**
	 * Audit log option.
	 */
	const LOG_OPTION = 'marzgard_security_log';

	/**
	 * Client IP.
	 *
	 * @return string
	 */
	public static function ip() {
		return marzgard_client_ip();
	}

	/**
	 * Create an internal (math) captcha challenge.
	 *
	 * @return array
	 */
	public static function internal_challenge() {
		$a = wp_rand( 2, 9 );
		$b = wp_rand( 1, 8 );
		$id = function_exists( 'random_bytes' ) ? bin2hex( random_bytes( 8 ) ) : md5( wp_generate_password( 16 ) . microtime( true ) );

		set_transient( 'mg_captcha_' . $id, wp_hash( 'mgc|' . ( $a + $b ) ), 5 * MINUTE_IN_SECONDS );

		return array(
			'id'       => $id,
			'question' => sprintf( __( 'حاصل جمع %d + %d چقدر است؟', 'marzgard' ), $a, $b ),
		);
	}

	/**
	 * Verify a captcha response.
	 *
	 * @param array $params Request params.
	 * @return true|WP_Error
	 */
	public static function verify_captcha( $params = array() ) {
		$provider = marzgard_option( 'captcha_provider' );
		if ( 'none' === $provider ) {
			return true;
		}

		$token = isset( $params['captcha_token'] ) ? sanitize_text_field( $params['captcha_token'] ) : '';

		if ( 'internal' === $provider ) {
			$id       = isset( $params['captcha_id'] ) ? sanitize_text_field( $params['captcha_id'] ) : '';
			$answer   = isset( $params['captcha_answer'] ) ? sanitize_text_field( $params['captcha_answer'] ) : '';
			$expected = get_transient( 'mg_captcha_' . $id );
			if ( ! $expected || ! hash_equals( $expected, wp_hash( 'mgc|' . preg_replace( '/[^0-9]/', '', $answer ) ) ) ) {
				return new WP_Error( 'captcha_failed', __( 'پاسخ کپچا نادرست یا منقضی است.', 'marzgard' ) );
			}
			delete_transient( 'mg_captcha_' . $id );
			return true;
		}

		if ( '' === $token ) {
			return new WP_Error( 'captcha_failed', __( 'لطفاً کپچا را تکمیل کنید.', 'marzgard' ) );
		}

		$secret = marzgard_option( 'captcha_secret_key' );

		switch ( $provider ) {
			case 'recaptcha':
				$response = wp_remote_post(
					'https://www.google.com/recaptcha/api/siteverify',
					array(
						'timeout' => 10,
						'body'    => array(
							'secret'   => $secret,
							'response' => $token,
							'remoteip' => self::ip(),
						),
					)
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( empty( $data['success'] ) ) {
					return new WP_Error( 'captcha_failed', __( 'تأیید reCAPTCHA ناموفق بود.', 'marzgard' ) );
				}
				if ( 'v3' === marzgard_option( 'recaptcha_version' ) ) {
					$min = (float) marzgard_option( 'captcha_min_score' );
					if ( empty( $data['score'] ) || (float) $data['score'] < $min || ( isset( $data['action'] ) && 'marzgard' !== $data['action'] ) ) {
						return new WP_Error( 'captcha_failed', __( 'امتیاز امنیتی reCAPTCHA کافی نیست.', 'marzgard' ) );
					}
				}
				return true;

			case 'turnstile':
				$response = wp_remote_post(
					'https://challenges.cloudflare.com/turnstile/v0/siteverify',
					array(
						'timeout' => 10,
						'body'    => array(
							'secret'   => $secret,
							'response' => $token,
							'remoteip' => self::ip(),
						),
					)
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( empty( $data['success'] ) ) {
					return new WP_Error( 'captcha_failed', __( 'تأیید Cloudflare Turnstile ناموفق بود.', 'marzgard' ) );
				}
				return true;

			case 'arcaptcha':
				$response = wp_remote_post(
					'https://api.arcaptcha.co/arcaptcha/api/verify',
					array(
						'timeout' => 10,
						'headers' => array( 'Content-Type' => 'application/json' ),
						'body'    => wp_json_encode(
							array(
								'secret_key'  => $secret,
								'site_key'    => marzgard_option( 'captcha_site_key' ),
								'challenge_id'=> $token,
							)
						),
					)
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( empty( $data['success'] ) ) {
					return new WP_Error( 'captcha_failed', __( 'تأیید آرکپچا ناموفق بود.', 'marzgard' ) );
				}
				return true;
		}

		// Unknown/disabled providers must not silently bypass the check.
		return new WP_Error( 'captcha_failed', __( 'سرویس کپچا معتبر نیست.', 'marzgard' ) );
	}

	/**
	 * Check phone/email/IP block lists.
	 *
	 * @param string $phone_key Canonical phone.
	 * @param string $email     Email.
	 * @return true|WP_Error
	 */
	public static function check_block( $phone_key = '', $email = '' ) {
		$ip = self::ip();

		$phones = self::option_lines( 'blocked_phones' );
		$emails = self::option_lines( 'blocked_emails' );
		$ips    = self::option_lines( 'blocked_ips' );

		$digits = marzgard_clean_phone( $phone_key );

		foreach ( $phones as $blocked ) {
			$blocked_digits = marzgard_clean_phone( $blocked );
			if ( '' !== $digits && '' !== $blocked_digits && $digits === $blocked_digits ) {
				return new WP_Error( 'blocked', __( 'این شماره موبایل مسدود است.', 'marzgard' ) );
			}
		}

		if ( $email ) {
			foreach ( $emails as $blocked_email ) {
				if ( 0 === strcasecmp( trim( $email ), trim( $blocked_email ) ) ) {
					return new WP_Error( 'blocked', __( 'این ایمیل مسدود است.', 'marzgard' ) );
				}
			}
		}

		foreach ( $ips as $blocked_ip ) {
			if ( 0 === strcasecmp( $ip, trim( $blocked_ip ) ) ) {
				return new WP_Error( 'blocked', __( 'آدرس IP شما مسدود است.', 'marzgard' ) );
			}
		}

		return true;
	}

	/**
	 * Read a newline separated option as an array.
	 *
	 * @param string $key Option key.
	 * @return array
	 */
	private static function option_lines( $key ) {
		$raw = (string) marzgard_option( $key );
		return array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ) );
	}

	/**
	 * Check IP lockout after too many failures.
	 *
	 * @return true|WP_Error
	 */
	public static function check_lockout() {
		$count = (int) get_transient( 'mg_fails_' . md5( self::ip() ) );
		$max   = (int) marzgard_option( 'max_ip_fails' );
		if ( $count >= $max ) {
			return new WP_Error( 'locked', sprintf( __( 'به دلیل تلاش های ناموفق، دسترسی موقتاً مسدود است. لطفاً %d دقیقه صبر کنید.', 'marzgard' ), ceil( (int) marzgard_option( 'lockout_duration' ) / MINUTE_IN_SECONDS ) ) );
		}
		return true;
	}

	/**
	 * Record a failed attempt.
	 *
	 * @param string $phone Phone (optional).
	 */
	public static function record_fail( $phone = '' ) {
		$key   = 'mg_fails_' . md5( self::ip() );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, (int) marzgard_option( 'lockout_duration' ) );
		self::log( 'failed_attempt', array( 'ip' => self::ip(), 'phone' => $phone ) );

		if ( $phone ) {
			$pkey = 'mg_phone_fails_' . md5( $phone );
			set_transient( $pkey, (int) get_transient( $pkey ) + 1, (int) marzgard_option( 'lockout_duration' ) );
		}
	}

	/**
	 * Clear failure counters after success.
	 *
	 * @param string $phone Phone.
	 */
	public static function clear_fails( $phone = '' ) {
		delete_transient( 'mg_fails_' . md5( self::ip() ) );
		if ( $phone ) {
			delete_transient( 'mg_phone_fails_' . md5( $phone ) );
		}
	}

	/**
	 * Add an audit log entry (bounded to 200 records).
	 *
	 * @param string $type Event type.
	 * @param array  $data Event data.
	 */
	public static function log( $type, $data = array() ) {
		if ( ! marzgard_option( 'log_security_events' ) ) {
			return;
		}

		$log   = get_option( self::LOG_OPTION, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array_merge(
			array(
				'time' => time(),
				'type' => $type,
				'ip'   => self::ip(),
			),
			$data
		);

		if ( count( $log ) > 200 ) {
			$log = array_slice( $log, -200 );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Get the audit log.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? array_reverse( $log ) : array();
	}
}
