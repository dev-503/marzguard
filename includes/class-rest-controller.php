<?php
/**
 * Headless REST API for MarzGard.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST routes.
 */
class MarzGard_REST_Controller extends WP_REST_Controller {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Route definitions.
	 */
	public function register_routes() {
		$ns = MARZGARD_REST_NS;

		register_rest_route(
			$ns,
			'/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/otp/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_otp' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone'        => array( 'required' => false, 'type' => 'string' ),
					'country_code' => array( 'required' => false, 'type' => 'string' ),
					'email'        => array( 'required' => false, 'type' => 'string' ),
					'purpose'      => array( 'required' => false, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/otp/resend',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resend_otp' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'challenge' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/otp/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'verify_otp' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'challenge' => array( 'required' => true, 'type' => 'string' ),
					'otp'       => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/logout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'logout' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			$ns,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/sms/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_sms' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'to' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/captcha',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_captcha' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/sms/providers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sms_providers' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/security/log',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_security_log' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clear_security_log' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/password/login',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'password_login' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/export/users',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_users' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/retention/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_retention' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/retention/log',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_retention_log' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/retention/recover',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'recover_cart' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Admin permission check.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Public configuration payload.
	 *
	 * @return WP_REST_Response
	 */
	public function get_config() {
		$data = array(
			'brand'      => marzgard_option( 'brand_name' ),
			'tagline'    => marzgard_option( 'brand_tagline' ),
			'mode'       => marzgard_option( 'auth_mode' ),
			'register'   => (bool) marzgard_option( 'enable_register' ),
			'countries'  => marzgard_countries(),
			'strings'    => marzgard_strings(),
			'otp_length' => (int) marzgard_option( 'otp_length' ),
			'security'   => array(
				'captcha'     => marzgard_option( 'captcha_provider' ),
				'siteKey'     => marzgard_option( 'captcha_site_key' ),
				'recaptchaV'  => marzgard_option( 'recaptcha_version' ),
				'resendWait'  => (int) marzgard_option( 'resend_wait' ),
				'channel'     => marzgard_option( 'otp_channel' ),
			),
			'appearance' => array(
				'primary'   => marzgard_option( 'primary_color' ),
				'radius'    => (int) marzgard_option( 'radius' ),
				'darkMode'  => marzgard_option( 'dark_mode' ),
				'fontSize'  => (int) marzgard_option( 'font_size' ),
			),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'loggedIn'   => is_user_logged_in(),
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Send an OTP.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function send_otp( $request ) {
		if ( ! empty( $request->get_param( 'website' ) ) ) {
			return rest_ensure_response( array( 'success' => true, 'message' => __( 'کد ارسال شد.', 'marzgard' ) ) );
		}

		$country_code = sanitize_text_field( $request->get_param( 'country_code' ) );
		$phone        = marzgard_clean_phone( $request->get_param( 'phone' ) );
		$email        = sanitize_email( $request->get_param( 'email' ) );
		$purpose      = sanitize_text_field( $request->get_param( 'purpose' ) );

		if ( ! in_array( $purpose, array( 'login', 'register' ), true ) ) {
			$purpose = 'login';
		}

		if ( 'register' === $purpose && ! marzgard_option( 'enable_register' ) ) {
			return $this->error( __( 'ثبت نام در حال حاضر غیرفعال است.', 'marzgard' ), 403 );
		}

		$is_email = ! empty( $email ) && is_email( $email );

		if ( $is_email ) {
			$identifier = 'email:' . $email;
			$template   = (string) marzgard_option( 'email_template' );
		} else {
			if ( strlen( $phone ) < 8 ) {
				return $this->error( __( 'شماره موبایل یا ایمیل معتبر نیست.', 'marzgard' ), 400 );
			}
			$identifier = marzgard_phone_key( $country_code, $phone );
			$template   = (string) marzgard_option( 'sms_template' );
		}

		$lockout = MarzGard_Security::check_lockout();
		if ( is_wp_error( $lockout ) ) {
			return $this->wp_error( $lockout );
		}

		$blocked = MarzGard_Security::check_block( $identifier, $is_email ? $email : '' );
		if ( is_wp_error( $blocked ) ) {
			return $this->wp_error( $blocked );
		}

		$captcha = MarzGard_Security::verify_captcha( $request->get_params() );
		if ( is_wp_error( $captcha ) ) {
			MarzGard_Security::log( 'captcha_failed', array( 'phone' => $identifier ) );
			return $this->wp_error( $captcha );
		}

		$challenge = MarzGard_OTP::create( $identifier, $purpose, $this->ip() );
		if ( is_wp_error( $challenge ) ) {
			return $this->wp_error( $challenge );
		}

		$message = str_replace(
			array( '{code}', '{minutes}', '{brand}' ),
			array( $challenge['code'], (string) ceil( $challenge['ttl'] / MINUTE_IN_SECONDS ), marzgard_option( 'brand_name' ) ),
			$template
		);

		$error = '';
		if ( $is_email ) {
			$sent = wp_mail(
				$email,
				(string) marzgard_option( 'email_subject' ),
				$message,
				array( 'Content-Type: text/plain; charset=UTF-8' )
			);
			if ( ! $sent ) {
				return $this->error( __( 'ارسال ایمیل با خطا مواجه شد.', 'marzgard' ), 500 );
			}
		} else {
			$gateway = marzgard_gateway();
			$context = array(
				'code'       => $challenge['code'],
				'channel'    => 'sms',
				'identifier' => $identifier,
			);
			if ( ! $gateway->send( $identifier, $message, $error, $context ) ) {
				return $this->error( $error ? $error : __( 'ارسال پیامک با خطا مواجه شد.', 'marzgard' ), 500 );
			}
		}

		$response = array(
			'success'   => true,
			'message'   => __( 'کد تأیید ارسال شد.', 'marzgard' ),
			'challenge' => $challenge['id'],
			'ttl'       => $challenge['ttl'],
			'masked'    => $this->mask( $identifier ),
			'channel'   => $is_email ? 'email' : 'sms',
		);

		if ( marzgard_option( 'dev_expose_otp' ) ) {
			$response['dev_code'] = $challenge['code'];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Resend an OTP.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function resend_otp( $request ) {
		$id = sanitize_text_field( $request->get_param( 'challenge' ) );

		$challenge = MarzGard_OTP::resend( $id, $this->ip() );
		if ( is_wp_error( $challenge ) ) {
			return $this->wp_error( $challenge );
		}

		$is_email = 0 === strpos( $challenge['phone'], 'email:' );
		$template = $is_email ? (string) marzgard_option( 'email_template' ) : (string) marzgard_option( 'sms_template' );
		$message  = str_replace(
			array( '{code}', '{minutes}', '{brand}' ),
			array( $challenge['code'], (string) ceil( $challenge['ttl'] / MINUTE_IN_SECONDS ), marzgard_option( 'brand_name' ) ),
			$template
		);

		$error = '';
		if ( $is_email ) {
			wp_mail(
				substr( $challenge['phone'], 6 ),
				(string) marzgard_option( 'email_subject' ),
				$message,
				array( 'Content-Type: text/plain; charset=UTF-8' )
			);
		} else {
			$gateway = marzgard_gateway();
			$context = array(
				'code'       => $challenge['code'],
				'channel'    => 'sms',
				'identifier' => $challenge['phone'],
			);
			if ( ! $gateway->send( $challenge['phone'], $message, $error, $context ) ) {
				return $this->error( $error ? $error : __( 'ارسال پیامک با خطا مواجه شد.', 'marzgard' ), 500 );
			}
		}

		$response = array(
			'success' => true,
			'message' => __( 'کد جدید ارسال شد.', 'marzgard' ),
			'ttl'     => $challenge['ttl'],
		);

		if ( marzgard_option( 'dev_expose_otp' ) ) {
			$response['dev_code'] = $challenge['code'];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Verify an OTP and authenticate the user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function verify_otp( $request ) {
		$id       = sanitize_text_field( $request->get_param( 'challenge' ) );
		$otp      = sanitize_text_field( $request->get_param( 'otp' ) );
		$remember = (bool) $request->get_param( 'remember' );

		$lockout = MarzGard_Security::check_lockout();
		if ( is_wp_error( $lockout ) ) {
			return $this->wp_error( $lockout );
		}

		$verified = MarzGard_OTP::verify( $id, $otp );
		if ( is_wp_error( $verified ) ) {
			MarzGard_Security::record_fail();
			if ( marzgard_option( 'hide_login_errors' ) && 'invalid' === $verified->get_error_code() ) {
				return $this->error( __( 'کد تأیید نادرست است.', 'marzgard' ), 400 );
			}
			return $this->wp_error( $verified );
		}

		MarzGard_Security::clear_fails();

		$is_email = 0 === strpos( $verified['phone'], 'email:' );
		$email    = $is_email ? substr( $verified['phone'], 6 ) : '';

		$user    = $is_email ? get_user_by( 'email', $email ) : marzgard_find_user_by_phone( $verified['phone'] );
		$created = false;

		if ( ! $user ) {
			if ( 'register' !== $verified['purpose'] && 'register' !== marzgard_option( 'auth_mode' ) ) {
				return $this->error( __( 'حسابی با این شماره یافت نشد. ابتدا ثبت نام کنید.', 'marzgard' ), 404 );
			}

			if ( ! marzgard_option( 'enable_register' ) ) {
				return $this->error( __( 'ثبت نام در حال حاضر غیرفعال است.', 'marzgard' ), 403 );
			}

			$created = true;
			$user_id = $this->create_user( $verified['phone'], $email );
			if ( is_wp_error( $user_id ) ) {
				return $this->wp_error( $user_id );
			}
			$user = get_user_by( 'id', $user_id );
		} else {
			$user_id = $user->ID;
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, $remember );
		do_action( 'wp_login', $user->user_login, $user );

		/**
		 * Fires after a successful MarzGard authentication.
		 *
		 * @param WP_User $user    User.
		 * @param bool    $created Whether the user was just created.
		 */
		do_action( 'marzgard_login', $user, $created );

		$redirect = $this->safe_redirect( $request->get_param( 'redirect' ) );

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => $created ? __( 'حساب شما با موفقیت ایجاد شد.', 'marzgard' ) : __( 'با موفقیت وارد شدید.', 'marzgard' ),
				'redirect' => $redirect,
				'created'  => $created,
				'user'     => array(
					'id'          => $user_id,
					'name'        => $user->display_name,
					'phone'       => $this->mask( $verified['phone'] ),
				),
			)
		);
	}

	/**
	 * Log out the current user.
	 *
	 * @return WP_REST_Response
	 */
	public function logout() {
		wp_destroy_current_session();
		wp_clear_auth_cookie();
		wp_set_current_user( 0 );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Get all settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		$data = marzgard_option();

		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$roles = get_editable_roles();
		$data['roles'] = array();
		if ( is_array( $roles ) ) {
			foreach ( $roles as $key => $role ) {
				$data['roles'][] = array( 'key' => $key, 'name' => $role['name'] );
			}
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_settings( $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) || empty( $payload ) ) {
			return $this->error( __( 'داده های تنظیمات معتبر نیستند.', 'marzgard' ), 400 );
		}

		update_option( 'marzgard_options', marzgard_sanitize_options( $payload ) );

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => __( 'تنظیمات ذخیره شد.', 'marzgard' ),
				'settings' => marzgard_option(),
			)
		);
	}

	/**
	 * Send a test SMS using the configured provider.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function test_sms( $request ) {
		$to = marzgard_clean_phone( $request->get_param( 'to' ) );
		if ( strlen( $to ) < 10 ) {
			return $this->error( __( 'شماره موبایل معتبر نیست.', 'marzgard' ), 400 );
		}

		$error   = '';
		$gateway = marzgard_gateway();
		$ok      = $gateway->send( '+98' . ltrim( $to, '0' ), __( 'پیام تست مرزگارد', 'marzgard' ), $error, array( 'code' => '123456', 'channel' => 'sms' ) );

		if ( ! $ok ) {
			return $this->error( $error ? $error : __( 'ارسال ناموفق بود.', 'marzgard' ), 500 );
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'پیام تست ارسال شد.', 'marzgard' ) ) );
	}

	/**
	 * Internal captcha challenge.
	 *
	 * @return WP_REST_Response
	 */
	public function get_captcha() {
		if ( 'internal' !== marzgard_option( 'captcha_provider' ) ) {
			return $this->error( __( 'کپچای داخلی فعال نیست.', 'marzgard' ), 400 );
		}
		return rest_ensure_response( MarzGard_Security::internal_challenge() );
	}

	/**
	 * All available SMS providers.
	 *
	 * @return WP_REST_Response
	 */
	public function get_sms_providers() {
		return rest_ensure_response( marzgard_sms_providers() );
	}

	/**
	 * Security audit log.
	 *
	 * @return WP_REST_Response
	 */
	public function get_security_log() {
		return rest_ensure_response( MarzGard_Security::get_log() );
	}

	/**
	 * Clear the security audit log.
	 *
	 * @return WP_REST_Response
	 */
	public function clear_security_log() {
		delete_option( MarzGard_Security::LOG_OPTION );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Login with email/username and password.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function password_login( $request ) {
		$login    = sanitize_text_field( $request->get_param( 'login' ) );
		$password = (string) $request->get_param( 'password' );
		$lockout  = MarzGard_Security::check_lockout();
		if ( is_wp_error( $lockout ) ) {
			return $this->wp_error( $lockout );
		}

		$blocked = MarzGard_Security::check_block( '', is_email( $login ) ? $login : '' );
		if ( is_wp_error( $blocked ) ) {
			return $this->wp_error( $blocked );
		}

		$user = wp_authenticate( $login, $password );
		if ( is_wp_error( $user ) ) {
			MarzGard_Security::record_fail();
			return $this->error( marzgard_option( 'hide_login_errors' ) ? __( 'اطلاعات ورود نادرست است.', 'marzgard' ) : $user->get_error_message(), 401 );
		}

		if ( ! marzgard_option( 'allow_admin_password' ) && user_can( $user, 'manage_options' ) ) {
			return $this->error( __( 'ورود مدیران با رمز عبور غیرفعال است.', 'marzgard' ), 403 );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, (bool) $request->get_param( 'remember' ) );
		MarzGard_Security::clear_fails();

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => __( 'با موفقیت وارد شدید.', 'marzgard' ),
				'redirect' => $this->safe_redirect( $request->get_param( 'redirect' ) ),
			)
		);
	}

	/**
	 * Export users with mobile numbers (Excel-compatible CSV or VCF).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function export_users( $request ) {
		$format = 'vcf' === $request->get_param( 'format' ) ? 'vcf' : 'csv';

		$users = get_users(
			array(
				'number'     => -1,
				'meta_query' => array( // phpcs:ignore WPDB
					array(
						'key'     => 'digits_phone',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$lines = array();
		if ( 'csv' === $format ) {
			$lines[] = 'name,email,mobile,country_code,role';
			foreach ( $users as $user ) {
				$lines[] = implode(
					',',
					array(
						'"' . str_replace( '"', '""', $user->display_name ) . '"',
						'"' . $user->user_email . '"',
						'"' . get_user_meta( $user->ID, 'digits_phone', true ) . '"',
						'"' . get_user_meta( $user->ID, 'digt_countrycode', true ) . '"',
						'"' . implode( ',', $user->roles ) . '"',
					)
				);
			}
		} else {
			foreach ( $users as $user ) {
				$phone = get_user_meta( $user->ID, 'digits_phone', true );
				if ( ! $phone ) {
					continue;
				}
				$lines[] = 'BEGIN:VCARD';
				$lines[] = 'VERSION:3.0';
				$lines[] = 'FN:' . $user->display_name;
				$lines[] = 'TEL;TYPE=CELL:' . $phone;
				if ( $user->user_email ) {
					$lines[] = 'EMAIL:' . $user->user_email;
				}
				$lines[] = 'END:VCARD';
			}
		}

		$response = new WP_REST_Response( implode( "\r\n", $lines ) );
		$response->set_headers(
			array(
				'Content-Type'        => 'csv' === $format ? 'text/csv; charset=utf-8' : 'text/vcard; charset=utf-8',
				'Content-Disposition' => 'attachment; filename="marzgard-users.' . $format . '"',
			)
		);

		return $response;
	}

	/**
	 * Run retention campaigns manually.
	 *
	 * @return WP_REST_Response
	 */
	public function run_retention() {
		$retention = marzgard()->retention();
		$result    = $retention->run();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: campaign counts */
					__( 'سبد رها شده: %1$d، یادآوری پرداخت: %2$d، بازگشت مشتری: %3$d', 'marzgard' ),
					$result['abandoned'],
					$result['payment'],
					$result['winback']
				),
				'result'  => $result,
			)
		);
	}

	/**
	 * Retention campaign log.
	 *
	 * @return WP_REST_Response
	 */
	public function get_retention_log() {
		return rest_ensure_response( MarzGard_Retention::get_log() );
	}

	/**
	 * Restore an abandoned cart.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function recover_cart( $request ) {
		$retention = marzgard()->retention();
		$ok        = $retention->recover( $request->get_param( 'token' ) );
		$cart_url  = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );

		return rest_ensure_response(
			array(
				'success'  => $ok,
				'message'  => $ok ? __( 'سبد خرید بازیابی شد.', 'marzgard' ) : __( 'لینک بازیابی معتبر نیست.', 'marzgard' ),
				'redirect' => $ok ? $cart_url : home_url( '/' ),
			)
		);
	}

	/**
	 * Create a new user from a phone number or email address.
	 *
	 * @param string $identifier Canonical phone or email identifier.
	 * @param string $email      Email address when available.
	 * @return int|WP_Error
	 */
	private function create_user( $identifier, $email = '' ) {
		$digits = marzgard_clean_phone( $identifier );

		if ( $email ) {
			$local    = strstr( $email, '@', true );
			$username = sanitize_user( $local ? $local : 'mg_' . substr( md5( $email ), 0, 8 ) );
			if ( empty( $username ) ) {
				$username = 'mg_' . substr( md5( $email ), 0, 8 );
			}
		} else {
			$username = 'mg_' . $digits;
		}

		$base   = $username;
		$suffix = 1;
		while ( username_exists( $username ) ) {
			$username = $base . '_' . $suffix;
			++$suffix;
		}

		$role = sanitize_key( marzgard_option( 'default_role' ) );
		if ( ! get_role( $role ) ) {
			$role = 'subscriber';
		}

		$userdata = array(
			'user_login'  => $username,
			'user_pass'   => wp_generate_password( 24, true, false ),
			'display_name'=> $email ? sanitize_text_field( strstr( $email, '@', true ) ) : sprintf( __( 'کاربر %s', 'marzgard' ), $digits ),
			'role'        => $role,
		);

		if ( $email ) {
			$userdata['user_email'] = $email;
		}

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( ! $email ) {
			preg_match( '/^(\+\d+)/', $identifier, $matches );
			$country_code = isset( $matches[1] ) ? $matches[1] : '+98';
			marzgard_set_user_phone( $user_id, $country_code, $digits );
		} else {
			update_user_meta( $user_id, 'marzgard_verified', 1 );
		}

		return $user_id;
	}

	/**
	 * Validate and return a safe redirect URL.
	 *
	 * @param string $raw Raw URL.
	 * @return string
	 */
	private function safe_redirect( $raw ) {
		$url = ! empty( $raw ) ? esc_url_raw( $raw ) : '';
		if ( empty( $url ) ) {
			$url = marzgard_option( 'redirect_url' );
		}
		if ( empty( $url ) ) {
			$url = home_url( '/' );
		}

		$host     = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( $host && $home_host && strtolower( $host ) !== strtolower( $home_host ) ) {
			$url = home_url( '/' );
		}

		/**
		 * Filter the post-login redirect URL.
		 *
		 * @param string $url URL.
		 */
		return apply_filters( 'marzgard_redirect_url', $url );
	}

	/**
	 * Mask a phone number for display.
	 *
	 * @param string $phone_key Phone.
	 * @return string
	 */
	private function mask( $phone_key ) {
		if ( 0 === strpos( $phone_key, 'email:' ) ) {
			$email = substr( $phone_key, 6 );
			$parts = explode( '@', $email );
			$local = isset( $parts[0] ) ? $parts[0] : '';
			$host  = isset( $parts[1] ) ? $parts[1] : '';

			return substr( $local, 0, 2 ) . '•••@' . $host;
		}

		$length = strlen( $phone_key );
		if ( $length <= 4 ) {
			return $phone_key;
		}

		return substr( $phone_key, 0, 3 ) . str_repeat( '•', max( 2, $length - 7 ) ) . substr( $phone_key, -4 );
	}

	/**
	 * Client IP address.
	 *
	 * @return string
	 */
	private function ip() {
		return marzgard_client_ip();
	}

	/**
	 * Error response helper.
	 *
	 * @param string $message Message.
	 * @param int    $status  Status.
	 * @return WP_REST_Response
	 */
	private function error( $message, $status = 400 ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Convert WP_Error to a REST response.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_REST_Response
	 */
	private function wp_error( $error ) {
		$code = $error->get_error_code();
		$map  = array(
			'rate_limit'       => 429,
			'resend_wait'      => 429,
			'locked'           => 429,
			'too_many'         => 429,
			'blocked'          => 403,
			'captcha_failed'   => 400,
			'expired'          => 410,
			'invalid_challenge'=> 400,
		);

		return $this->error( $error->get_error_message(), isset( $map[ $code ] ) ? $map[ $code ] : 400 );
	}
}
