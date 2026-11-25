<?php
/**
 * تنظیمات اعلان بله — در دیتابیس (جدول options) ذخیره می‌شود.
 *
 * کلید گزینه همان کلید افزونه‌ی قبلی (hamidreza_settings) است تا توکن و شناسه‌های
 * قبلاً ذخیره‌شده بدون نیاز به تنظیم دوباره کار کنند. ثابت‌های wp-config لازم نیست.
 *
 * @package DeiaraOrderTracking
 */

defined( 'ABSPATH' ) || exit;

class DOT_Bale_Settings {

	const OPTION_KEY        = 'hamidreza_settings';
	const LEGACY_OPTION_KEY = 'hrwcn_settings';

	public static function defaults() {
		return array(
			'enabled'               => 0,
			'bale_bot_token'        => '',
			'bale_chat_ids'         => '',
			'include_sku'           => 0,
			'include_customer_note' => 0,
			'include_coupon'        => 0,
			'include_shipping'      => 0,
			'message_template'      => '',
			'statuses'              => array( 'wc-processing', 'wc-completed' ),
			'silent_notification'   => 0,
			'notify_on_create'      => 1,
			// کلیدهای قدیمیِ افزونه‌ی قبلی؛ فقط برای خواندن اطلاعات ذخیره‌شده
			'platform'              => 'bale',
			'bot_token'             => '',
			'chat_id'               => '',
			'chat_ids'              => '',
		);
	}

	public static function get() {
		self::maybe_migrate();

		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		unset( $options['log_errors'] );

		return wp_parse_args( $options, self::defaults() );
	}

	/** انتقال تنظیمات خیلی قدیمی (hrwcn_settings) در صورت وجود */
	protected static function maybe_migrate() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done   = true;
		$legacy = get_option( self::LEGACY_OPTION_KEY, false );
		if ( is_array( $legacy ) ) {
			if ( false === get_option( self::OPTION_KEY, false ) ) {
				unset( $legacy['log_errors'] );
				update_option( self::OPTION_KEY, wp_parse_args( $legacy, self::defaults() ) );
			}
			delete_option( self::LEGACY_OPTION_KEY );
		}
	}

	protected static function parse_chat_id_list( $raw ) {
		$parts = preg_split( '/[\r\n,]+/', (string) $raw );
		$ids   = array();
		if ( is_array( $parts ) ) {
			foreach ( $parts as $part ) {
				$id = self::sanitize_chat_id( trim( (string) $part ) );
				if ( '' !== $id ) {
					$ids[] = $id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * شناسه‌های چت بله. اگر فیلد بله خالی باشد و کاربر قبلاً در افزونه‌ی قدیمی
	 * پلتفرم «بله» را انتخاب کرده بود، لیست قدیمی استفاده می‌شود.
	 */
	public static function get_bale_chat_ids( $options = null ) {
		if ( null === $options ) {
			$options = self::get();
		}
		$raw = isset( $options['bale_chat_ids'] ) ? (string) $options['bale_chat_ids'] : '';
		if ( '' === trim( $raw ) && 'bale' === ( $options['platform'] ?? '' ) ) {
			$raw = (string) ( $options['chat_ids'] ?? '' );
			if ( '' === trim( $raw ) ) {
				$raw = (string) ( $options['chat_id'] ?? '' );
			}
		}
		return self::parse_chat_id_list( $raw );
	}

	public static function get_chat_ids( $options = null ) {
		return self::get_bale_chat_ids( $options );
	}

	public static function get_platform_chat_ids( $platform, $options = null ) {
		if ( null === $options ) {
			$options = self::get();
		}
		return apply_filters( 'hron_platform_chat_ids', self::get_bale_chat_ids( $options ), 'bale', $options );
	}

	public static function get_bale_token( $options = null ) {
		if ( null === $options ) {
			$options = self::get();
		}
		$token = isset( $options['bale_bot_token'] ) ? trim( (string) $options['bale_bot_token'] ) : '';
		if ( '' === $token && 'bale' === ( $options['platform'] ?? '' ) ) {
			$token = trim( (string) ( $options['bot_token'] ?? '' ) ); // توکن مشترکِ افزونه‌ی قبلی
		}
		return $token;
	}

	public static function token_source( $platform = 'bale', $options = null ) {
		return '' !== self::get_bale_token( $options ) ? 'database' : 'none';
	}

	public static function mask_token( $token ) {
		$token = (string) $token;
		if ( strlen( $token ) <= 8 ) {
			return 'ذخیره شده (پنهان)';
		}
		return substr( $token, 0, 4 ) . '••••' . substr( $token, -4 );
	}

	public static function get_delivery_platforms( $options = null ) {
		if ( null === $options ) {
			$options = self::get();
		}
		$platforms = array();
		$token     = self::get_bale_token( $options );
		if ( '' !== $token ) {
			$platforms[] = array(
				'platform'  => 'bale',
				'bot_token' => $token,
			);
		}
		return apply_filters( 'hron_delivery_platforms', $platforms, $options );
	}

	public static function has_credentials( $options = null ) {
		if ( null === $options ) {
			$options = self::get();
		}
		return '' !== self::get_bale_token( $options ) && ! empty( self::get_bale_chat_ids( $options ) );
	}

	public static function status() {
		$options = self::get();
		if ( empty( $options['enabled'] ) ) {
			return 'disabled';
		}
		return self::has_credentials( $options ) ? 'active' : 'misconfigured';
	}

	public static function is_ready( $options = null ) {
		if ( null === $options ) {
			$options = self::get();
		}
		return ! empty( $options['enabled'] ) && self::has_credentials( $options );
	}

	public static function sanitize_bot_token( $token ) {
		return preg_replace( '/[^A-Za-z0-9:_-]/', '', sanitize_text_field( (string) $token ) );
	}

	public static function sanitize_chat_id( $chat_id ) {
		return sanitize_text_field( dot_en_digits( (string) $chat_id ) );
	}

	public static function sanitize_chat_ids( $raw ) {
		return implode( "\n", self::parse_chat_id_list( $raw ) );
	}

	public static function sanitize_bale_chat_ids( $raw ) {
		return self::sanitize_chat_ids( $raw );
	}
}
