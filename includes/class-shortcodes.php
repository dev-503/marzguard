<?php
/**
 * Shortcodes, Gutenberg block and reusable mount renderers.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render helpers and hooks.
 */
class MarzGard_Shortcodes {

	/**
	 * Instance counter for unique mount IDs.
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_shortcode( 'marzgard_form', array( $this, 'render' ) );
		add_shortcode( 'marzgard', array( $this, 'render' ) );
		add_shortcode( 'مرزگارد', array( $this, 'render' ) );
		add_shortcode( 'marzgard_digikala', array( $this, 'render_digikala' ) );
		add_shortcode( 'دیجی_کالا', array( $this, 'render_digikala' ) );

		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'login_form', array( $this, 'render_login_form' ) );
	}

	/**
	 * Shortcode callback. Supports skin="digikala".
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'mode'     => '',
				'redirect' => '',
				'class'    => '',
				'skin'     => 'sorkhab',
			),
			$atts,
			'marzgard_form'
		);

		if ( 'digikala' === sanitize_key( $atts['skin'] ) ) {
			return $this->render_digikala( $atts );
		}

		MarzGard_Assets::need_public();

		return $this->build_mount(
			array(
				'id'            => 'marzgard-app-' . ++self::$counter,
				'class'         => 'marzgard-mount' . ( $atts['class'] ? ' ' . sanitize_html_class( $atts['class'] ) : '' ),
				'data-attribute'=> 'data-marzgard-form',
				'data'          => array(
					'mode'     => sanitize_key( $atts['mode'] ),
					'redirect' => esc_url_raw( $atts['redirect'] ),
				),
			)
		);
	}

	/**
	 * Digikala-style login shortcode / Elementor render helper.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_digikala( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'       => 'ورود یا ثبتنام در دیجیکالا',
				'subtitle'    => 'لطفا شماره موبایل یا ایمیل خود را وارد کنید',
				'placeholder' => '912 345 6789',
				'button_text' => 'ورود دیجیکالا',
				'otp_title'   => 'تایید شماره موبایل',
				'otp_button'  => 'تایید و ورود',
				'color'       => '#e60023',
				'show_logo'   => 'yes',
				'terms'       => 'ورود به معنای پذیرش شرایط و قوانین حریم خصوصی است',
				'mode'        => '',
				'redirect'    => '',
				'class'       => '',
			),
			$atts,
			'marzgard_digikala'
		);

		MarzGard_Assets::need_digikala();

		$color = preg_match( '/^#[0-9a-fA-F]{6}$/', $atts['color'] ) ? $atts['color'] : '#e60023';

		return $this->build_mount(
			array(
				'id'            => 'marzgard-digikala-' . ++self::$counter,
				'class'         => 'marzgard-digikala' . ( $atts['class'] ? ' ' . sanitize_html_class( $atts['class'] ) : '' ),
				'data-attribute'=> 'data-marzgard-digikala',
				'style'         => '--dk-red:' . esc_attr( $color ),
				'data'          => array(
					'title'       => sanitize_text_field( $atts['title'] ),
					'subtitle'    => sanitize_text_field( $atts['subtitle'] ),
					'placeholder' => sanitize_text_field( $atts['placeholder'] ),
					'button'      => sanitize_text_field( $atts['button_text'] ),
					'otp-title'   => sanitize_text_field( $atts['otp_title'] ),
					'otp-button'  => sanitize_text_field( $atts['otp_button'] ),
					'terms'       => sanitize_text_field( $atts['terms'] ),
					'show-logo'   => in_array( $atts['show_logo'], array( 'yes', '1', 'true' ), true ) ? '1' : '',
					'mode'        => sanitize_key( $atts['mode'] ),
					'redirect'    => esc_url_raw( $atts['redirect'] ),
				),
			)
		);
	}

	/**
	 * Gutenberg block registration (server-side render keeps markup in one place).
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'marzgard/form',
			array(
				'editor_script'   => 'marzgard-block',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'mode'     => array( 'type' => 'string', 'default' => '' ),
					'redirect' => array( 'type' => 'string', 'default' => '' ),
					'skin'     => array( 'type' => 'string', 'default' => 'sorkhab' ),
				),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		return $this->render(
			array(
				'mode'     => isset( $attributes['mode'] ) ? $attributes['mode'] : '',
				'redirect' => isset( $attributes['redirect'] ) ? $attributes['redirect'] : '',
				'skin'     => isset( $attributes['skin'] ) ? $attributes['skin'] : 'sorkhab',
			)
		);
	}

	/**
	 * Replace the default login form on wp-login.php.
	 */
	public function render_login_form() {
		if ( ! marzgard_option( 'replace_wp_login' ) ) {
			return;
		}

		MarzGard_Assets::need_public();

		echo $this->render( array( 'mode' => 'login' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Build the Vue mount element from a set of attributes.
	 *
	 * @param array $args Mount arguments.
	 * @return string
	 */
	private function build_mount( $args ) {
		$attrs = array(
			'id'               => $args['id'],
			'class'            => $args['class'],
			$args['data-attribute'] => '1',
		);

		if ( ! empty( $args['style'] ) ) {
			$attrs['style'] = $args['style'];
		}

		foreach ( $args['data'] as $key => $value ) {
			if ( '' !== $value ) {
				$attrs[ 'data-' . $key ] = $value;
			}
		}

		$html = '<div';
		foreach ( $attrs as $key => $value ) {
			$html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		$html .= '></div>';

		return $html;
	}
}
