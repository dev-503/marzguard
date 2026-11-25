<?php
/**
 * SMS gateway abstraction with ready-made Iranian providers.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway contract.
 *
 * $context contains: code, channel (sms|email), identifier.
 */
interface MarzGard_Gateway_Interface {
	/**
	 * Send an SMS.
	 *
	 * @param string $to      Destination number with country code.
	 * @param string $message Message body.
	 * @param string $error   Error message by reference.
	 * @param array  $context Extra context (code, channel, identifier).
	 * @return bool
	 */
	public function send( $to, $message, &$error = '', $context = array() );
}

/**
 * Shared HTTP helpers.
 */
abstract class MarzGard_Gateway_Base implements MarzGard_Gateway_Interface {

	/**
	 * Convert "+989121234567" to "09121234567".
	 *
	 * @param string $number Number.
	 * @return string
	 */
	protected function local_number( $number ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $number );
		if ( strlen( $digits ) === 12 && 0 === strpos( $digits, '98' ) ) {
			return '0' . substr( $digits, 2 );
		}
		return $digits;
	}

	/**
	 * Make a remote request and return the decoded body.
	 *
	 * @param string $url     URL.
	 * @param array  $args    wp_remote_* args.
	 * @param string $error   Error by reference.
	 * @return string|false
	 */
	protected function request( $url, $args, &$error = '' ) {
		$response = wp_remote_post( $url, wp_parse_args( $args, array( 'timeout' => 12 ) ) );

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			$error = sprintf( 'HTTP %d', $status );
			return false;
		}

		return trim( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Decode a numeric provider result (recId style).
	 *
	 * @param string $body Response body.
	 * @return bool
	 */
	protected function is_positive_id( $body ) {
		return is_numeric( $body ) && (int) $body > 0;
	}

	/**
	 * Map common Melipayamak-style error codes to Persian messages.
	 *
	 * @param string $body Response body.
	 * @return string
	 */
	protected function describe_code( $body ) {
		$map = array(
			'0'  => 'نام کاربری یا رمز عبور اشتباه است',
			'-1' => 'درخواست نامعتبر است',
			'2'  => 'اعتبار کافی نیست',
			'3'  => 'محدودیت ارسال روزانه',
			'5'  => 'شماره فرستنده معتبر نیست',
			'6'  => 'سامانه در حال بروزرسانی است',
			'9'  => 'ارسال از خطوط عمومی امکانپذیر نیست',
			'10' => 'کاربر غیرفعال است',
			'17' => 'متن پیامک خالی است',
			'18' => 'شماره گیرنده نامعتبر است',
		);

		$key = (string) (int) $body;
		return isset( $map[ $key ] ) ? $map[ $key ] : sprintf( 'خطای سرویس پیامک (%s)', $body );
	}
}

/**
 * Development gateway: logs the message instead of sending it.
 */
class MarzGard_Gateway_Log extends MarzGard_Gateway_Base {
	/**
	 * {@inheritdoc}
	 */
	public function send( $to, $message, &$error = '', $context = array() ) {
		marzgard_log( sprintf( 'SMS (log mode) to %s: %s', $to, $message ) );

		update_option(
			'marzgard_last_sms',
			array(
				'to'      => $to,
				'message' => $message,
				'time'    => time(),
			),
			false
		);

		return true;
	}
}

/**
 * ملی پیامک — SendOtp REST endpoint.
 */
class MarzGard_Gateway_MeliPayamak extends MarzGard_Gateway_Base {
	/**
	 * {@inheritdoc}
	 */
	public function send( $to, $message, &$error = '', $context = array() ) {
		$body = $this->request(
			'https://rest.payamak-panel.com/api/SendSMS/SendOtp',
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'username' => marzgard_option( 'sms_username' ),
						'password' => marzgard_option( 'sms_password' ),
						'to'       => $this->local_number( $to ),
						'from'     => marzgard_option( 'sms_sender' ),
						'code'     => isset( $context['code'] ) ? $context['code'] : '',
					)
				),
			),
			$error
		);

		if ( false === $body ) {
			return false;
		}

		if ( $this->is_positive_id( $body ) ) {
			return true;
		}

		$error = $this->describe_code( $body );
		return false;
	}
}

/**
 * فراز اس ام اس / IranPayamak — form-based OTP endpoint (URL overridable).
 */
class MarzGard_Gateway_Faraz extends MarzGard_Gateway_Base {
	/**
	 * {@inheritdoc}
	 */
	public function send( $to, $message, &$error = '', $context = array() ) {
		$endpoint = marzgard_option( 'sms_endpoint' );
		if ( empty( $endpoint ) ) {
			$endpoint = 'https://api.iranpayamak.com/api/SendSMS/SendOtp';
		}

		$body = $this->request(
			$endpoint,
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'username' => marzgard_option( 'sms_username' ),
						'password' => marzgard_option( 'sms_password' ),
						'to'       => $this->local_number( $to ),
						'from'     => marzgard_option( 'sms_sender' ),
						'code'     => isset( $context['code'] ) ? $context['code'] : '',
					)
				),
			),
			$error
		);

		if ( false === $body ) {
			return false;
		}

		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['status'] ) && in_array( $decoded['status'], array( 1, '1', 200, '200', true ), true ) ) {
				return true;
			}
			$error = isset( $decoded['message'] ) ? $decoded['message'] : __( 'ارسال پیامک با خطا مواجه شد.', 'marzgard' );
			return false;
		}

		if ( $this->is_positive_id( $body ) ) {
			return true;
		}

		$error = $this->describe_code( $body );
		return false;
	}
}

/**
 * کاوه نگار — sms/send.json
 */
class MarzGard_Gateway_Kavenegar extends MarzGard_Gateway_Base {
	/**
	 * {@inheritdoc}
	 */
	public function send( $to, $message, &$error = '', $context = array() ) {
		$api_key = marzgard_option( 'sms_api_key' );
		if ( empty( $api_key ) ) {
			$error = __( 'کلید API کاوه نگار تنظیم نشده است.', 'marzgard' );
			return false;
		}

		$fields = array(
			'receptor' => $this->local_number( $to ),
			'message'  => $message,
		);
		$sender = marzgard_option( 'sms_sender' );
		if ( ! empty( $sender ) ) {
			$fields['sender'] = $sender;
		}

		$url  = 'https://api.kavenegar.com/v1/' . rawurlencode( $api_key ) . '/sms/send.json';
		$body = $this->request(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query( $fields ),
			),
			$error
		);

		if ( false === $body ) {
			return false;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['return']['status'] ) || 200 !== (int) $data['return']['status'] ) {
			$error = isset( $data['return']['message'] ) ? $data['return']['message'] : __( 'ارسال پیامک کاوه نگار با خطا مواجه شد.', 'marzgard' );
			return false;
		}

		return true;
	}
}

/**
 * SMS.ir — /v1/send/verify (الگوی OTP).
 */
class MarzGard_Gateway_SmsIr extends MarzGard_Gateway_Base {
	/**
	 * {@inheritdoc}
	 */
	public function send( $to, $message, &$error = '', $context = array() ) {
		$api_key = marzgard_option( 'sms_api_key' );
		$template = marzgard_option( 'sms_template_id' );

		if ( empty( $api_key ) || empty( $template ) ) {
			$error = __( 'کلید API یا شناسه الگوی SMS.ir تنظیم نشده است.', 'marzgard' );
			return false;
		}

		$digits = preg_replace( '/[^0-9]/', '', (string) $to );
		if ( strlen( $digits ) > 10 ) {
			$digits = substr( $digits, -10 );
		}

		$payload = array(
			'mobile'     => $digits,
			'templateId' => (int) $template,
			'parameters' => array(
				array(
					'name'  => marzgard_option( 'sms_param_name' ) ? marzgard_option( 'sms_param_name' ) : 'code',
					'value' => isset( $context['code'] ) ? $context['code'] : '',
				),
			),
		);

		$body = $this->request(
			'https://api.sms.ir/v1/send/verify',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'x-api-key'    => $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			),
			$error
		);

		if ( false === $body ) {
			return false;
		}

		$data = json_decode( $body, true );
		if ( is_array( $data ) && isset( $data['status'] ) && 1 === (int) $data['status'] ) {
			return true;
		}

		$error = isset( $data['message'] ) ? $data['message'] : __( 'ارسال پیامک SMS.ir با خطا مواجه شد.', 'marzgard' );
		return false;
	}
}

/**
 * Generic REST gateway (custom provider).
 */
class MarzGard_Gateway_Rest extends MarzGard_Gateway_Base {
	/**
	 * {@inheritdoc}
	 */
	public function send( $to, $message, &$error = '', $context = array() ) {
		$endpoint = marzgard_option( 'sms_endpoint' );
		if ( empty( $endpoint ) ) {
			$error = __( 'آدرس سرویس پیامک تنظیم نشده است.', 'marzgard' );
			return false;
		}

		$sms_context = array(
			'to'      => $to,
			'message' => $message,
			'code'    => isset( $context['code'] ) ? $context['code'] : '',
			'api_key' => marzgard_option( 'sms_api_key' ),
			'sender'  => marzgard_option( 'sms_sender' ),
			'user'    => marzgard_option( 'sms_username' ),
			'pass'    => marzgard_option( 'sms_password' ),
		);

		$body    = $this->replace( (string) marzgard_option( 'sms_body' ), $sms_context );
		$headers = json_decode( $this->replace( (string) marzgard_option( 'sms_headers' ), $sms_context ), true );
		if ( ! is_array( $headers ) ) {
			$headers = array( 'Content-Type' => 'application/json' );
		}

		$method = strtoupper( (string) marzgard_option( 'sms_method' ) );
		$args   = array(
			'method'  => $method,
			'headers' => $headers,
		);

		if ( 'GET' !== $method ) {
			$args['body'] = $body;
		} else {
			$params = json_decode( $body, true );
			if ( is_array( $params ) ) {
				$endpoint = add_query_arg( $params, $endpoint );
			}
		}

		$response = wp_remote_request( $endpoint, $args );
		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$error = sprintf( 'SMS HTTP %d', $code );
			return false;
		}

		$success_path = trim( (string) marzgard_option( 'sms_success_path' ) );
		if ( '' !== $success_path ) {
			$data  = json_decode( wp_remote_retrieve_body( $response ), true );
			$value = $data;
			foreach ( explode( '.', $success_path ) as $part ) {
				if ( is_array( $value ) && array_key_exists( $part, $value ) ) {
					$value = $value[ $part ];
				} else {
					$value = null;
					break;
				}
			}
			if ( empty( $value ) && ! in_array( $value, array( 0, '0' ), true ) ) {
				$error = __( 'سرویس پیامک با خطا مواجه شد.', 'marzgard' );
				return false;
			}
		}

		return true;
	}

	/**
	 * Replace placeholders.
	 *
	 * @param string $text    Template.
	 * @param array  $context Values.
	 * @return string
	 */
	private function replace( $text, $context ) {
		foreach ( $context as $key => $value ) {
			$text = str_replace( '{' . $key . '}', (string) $value, $text );
		}
		return $text;
	}
}

/**
 * Adapter for any gateway from the bundled Persian WooCommerce SMS module.
 * Enables all of its providers to send MarzGard login OTP codes.
 */
class MarzGard_Gateway_WooSms extends MarzGard_Gateway_Base {

	/**
	 * PWSMS gateway class.
	 *
	 * @var string
	 */
	private $class;

	/**
	 * Constructor.
	 *
	 * @param string $class Gateway FQCN.
	 */
	public function __construct( $class ) {
		$this->class = $class;
	}

	/**
	 * {@inheritdoc}
	 */
	public function send( $to, $message, &$error = '', $context = array() ) {
		if ( ! class_exists( $this->class ) || ! is_subclass_of( $this->class, '\PW\PWSMS\Gateways\Gateway' ) ) {
			$error = __( 'درگاه انتخابشده یافت نشد.', 'marzgard' );
			return false;
		}

		$code    = isset( $context['code'] ) ? $context['code'] : '';
		$pattern = trim( (string) marzgard_option( 'sms_pattern_template' ) );

		if ( '' !== $pattern ) {
			$message = str_replace( '{code}', $code, $pattern );
		}

		try {
			$gateway          = new $this->class();
			$gateway->mobile  = array( $this->local_number( $to ) );
			$gateway->message = $message;
			$result           = $gateway->send();
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
			return false;
		}

		if ( true === $result ) {
			return true;
		}

		if ( is_string( $result ) && '' !== $result ) {
			$error = $result;
			return false;
		}

		return (bool) $result;
	}
}

/**
 * List all providers available to MarzGard (native + bundled module gateways).
 *
 * @return array
 */
function marzgard_sms_providers() {
	$providers = array(
		'log'         => __( 'ثبت در لاگ (توسعه)', 'marzgard' ),
		'melipayamak' => __( 'ملی پیامک', 'marzgard' ),
		'faraz'       => __( 'فراز اس ام اس', 'marzgard' ),
		'kavenegar'   => __( 'کاوه نگار', 'marzgard' ),
		'smsir'       => __( 'اس ام اس دات آی آر', 'marzgard' ),
		'custom'      => __( 'REST سفارشی', 'marzgard' ),
	);

	if ( function_exists( 'PWSMS' ) ) {
		try {
			foreach ( PWSMS()->get_sms_gateways() as $id => $name ) {
				if ( ! is_string( $id ) || '' === $id ) {
					continue;
				}
				$providers[ 'pwsms:' . $id ] = $name . ' (پیامک ووکامرس)';
			}
		} catch ( \Throwable $e ) {
			// The bundled module may be unavailable; native providers still work.
		}
	}

	return apply_filters( 'marzgard_sms_providers', $providers );
}

/**
 * Resolve the configured gateway.
 *
 * @return MarzGard_Gateway_Interface
 */
function marzgard_gateway() {
	$provider = marzgard_option( 'sms_provider' );

	if ( 0 === strpos( (string) $provider, 'pwsms:' ) ) {
		return new MarzGard_Gateway_WooSms( substr( $provider, 6 ) );
	}

	// Backwards compatibility with the previous "sms_gateway" option.
	if ( 'log' === $provider && 'rest' === marzgard_option( 'sms_gateway' ) ) {
		$provider = 'custom';
	}

	switch ( $provider ) {
		case 'melipayamak':
			return new MarzGard_Gateway_MeliPayamak();
		case 'faraz':
			return new MarzGard_Gateway_Faraz();
		case 'kavenegar':
			return new MarzGard_Gateway_Kavenegar();
		case 'smsir':
			return new MarzGard_Gateway_SmsIr();
		case 'custom':
			return new MarzGard_Gateway_Rest();
		default:
			return new MarzGard_Gateway_Log();
	}
}
