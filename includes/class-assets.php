<?php
/**
 * Asset registration and localisation.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets manager.
 */
class MarzGard_Assets {

	/**
	 * Public flag: assets requested from a shortcode/block.
	 *
	 * @var bool
	 */
	private static $public_needed = false;

	/**
	 * Digikala skin flag.
	 *
	 * @var bool
	 */
	private static $digikala_needed = false;

	/**
	 * Mark public assets as needed.
	 */
	public static function need_public() {
		self::$public_needed = true;
	}

	/**
	 * Mark Digikala skin assets as needed.
	 */
	public static function need_digikala() {
		self::$digikala_needed = true;
	}

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// Detect shortcode/block usage before wp_head so styles are printed in <head>.
		add_action( 'wp', array( $this, 'detect_usage' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_retention' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_public' ), 20 );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor' ) );
	}

	/**
	 * Detect whether the current singular view contains a MarzGard form.
	 */
	public function detect_usage() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		$digikala_tags = array( 'marzgard_digikala', 'دیجی_کالا' );
		foreach ( $digikala_tags as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				self::$digikala_needed = true;
			}
		}

		if ( false !== stripos( $post->post_content, 'skin="digikala"' ) || false !== stripos( $post->post_content, "skin='digikala'" ) ) {
			self::$digikala_needed = true;
		}

		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( is_string( $elementor_data ) && false !== strpos( $elementor_data, 'marzgard-digikala-login' ) ) {
			self::$digikala_needed = true;
		}

		$tags = array( 'marzgard_form', 'marzgard', 'مرزگارد' );
		foreach ( $tags as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				self::$public_needed = true;
				return;
			}
		}

		if ( function_exists( 'has_block' ) && has_block( 'marzgard/form', $post ) ) {
			self::$public_needed = true;
		}
	}

	/**
	 * Enqueue the lightweight cart-recovery handler on every front-end page.
	 */
	public function enqueue_retention() {
		if ( is_admin() ) {
			return;
		}

		$needed = ! empty( marzgard_option( 'rt_abandoned' ) ) || isset( $_GET['mg_recover'] );
		if ( ! apply_filters( 'marzgard_need_retention_js', $needed ) ) {
			return;
		}

		wp_enqueue_script(
			'marzgard-retention',
			MARZGARD_URL . 'frontend/dist/marzgard-retention.js',
			array(),
			MARZGARD_VERSION,
			true
		);

		// Separate global: the main MARZGARD payload belongs to the Vue form app.
		wp_localize_script(
			'marzgard-retention',
			'MARZGARD_RETENTION',
			array(
				'restUrl' => esc_url_raw( rest_url( MARZGARD_REST_NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Enqueue on the front-end when needed.
	 */
	public function maybe_enqueue_public() {
		if ( self::$public_needed ) {
			$this->enqueue_public();
		}
		if ( self::$digikala_needed ) {
			$this->enqueue_digikala();
		}
	}

	/**
	 * Enqueue the Digikala-skin assets.
	 */
	public function enqueue_digikala() {
		$this->register_vendor();

		wp_enqueue_style(
			'marzgard-digikala',
			MARZGARD_URL . 'frontend/dist/marzgard-digikala.css',
			array(),
			MARZGARD_VERSION,
			'all'
		);

		$this->maybe_add_custom_css( 'marzgard-digikala' );

		wp_enqueue_script(
			'marzgard-digikala',
			MARZGARD_URL . 'frontend/dist/marzgard-digikala.js',
			array( 'marzgard-vue' ),
			MARZGARD_VERSION,
			true
		);

		$this->localize( 'marzgard-digikala' );
	}

	/**
	 * Enqueue public assets.
	 */
	public function enqueue_public() {
		$this->register_vendor();

		wp_enqueue_style(
			'marzgard-public',
			MARZGARD_URL . 'frontend/dist/marzgard-public.css',
			array(),
			MARZGARD_VERSION,
			'all'
		);

		$this->maybe_add_custom_css( 'marzgard-public' );

		wp_enqueue_script(
			'marzgard-public',
			MARZGARD_URL . 'frontend/dist/marzgard-public.js',
			array( 'marzgard-vue' ),
			MARZGARD_VERSION,
			true
		);

		$this->localize( 'marzgard-public' );
	}

	/**
	 * Enqueue assets on the WordPress login screen when enabled.
	 */
	public function enqueue_login() {
		if ( ! marzgard_option( 'replace_wp_login' ) ) {
			return;
		}

		$this->enqueue_public();
	}

	/**
	 * Enqueue admin assets on the settings page.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_admin( $hook ) {
		if ( false === strpos( $hook, 'marzgard' ) ) {
			return;
		}

		$this->register_vendor();

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			'marzgard-admin',
			MARZGARD_URL . 'frontend/dist/marzgard-admin.css',
			array( 'wp-components' ),
			MARZGARD_VERSION,
			'all'
		);

		wp_enqueue_script(
			'marzgard-admin',
			MARZGARD_URL . 'frontend/dist/marzgard-admin.js',
			array( 'marzgard-vue', 'wp-api-fetch', 'wp-i18n' ),
			MARZGARD_VERSION,
			true
		);

		$this->localize( 'marzgard-admin' );
	}

	/**
	 * Register the Gutenberg block editor script.
	 */
	public function enqueue_block_editor() {
		wp_enqueue_script(
			'marzgard-block',
			MARZGARD_URL . 'frontend/dist/marzgard-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
			MARZGARD_VERSION,
			true
		);
	}

	/**
	 * Append the administrator's custom CSS to a MarzGard form stylesheet.
	 *
	 * @param string $handle Enqueued style handle.
	 */
	private function maybe_add_custom_css( $handle ) {
		$css = trim( (string) marzgard_option( 'custom_css' ) );
		if ( '' !== $css ) {
			wp_add_inline_style( $handle, $css );
		}
	}

	/**
	 * Register the local Vue 3 runtime (no CDN dependency).
	 */
	private function register_vendor() {
		if ( ! wp_script_is( 'marzgard-vue', 'registered' ) ) {
			wp_register_script(
				'marzgard-vue',
				MARZGARD_URL . 'frontend/dist/vendor/vue.global.prod.js',
				array(),
				'3.5.13',
				true
			);
		}
	}

	/**
	 * Localise shared config.
	 *
	 * @param string $handle Script handle.
	 */
	private function localize( $handle ) {
		wp_localize_script(
			$handle,
			'MARZGARD',
			array(
				'restUrl'   => esc_url_raw( rest_url( MARZGARD_REST_NS ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'assetsUrl' => MARZGARD_URL . 'assets/',
				'version'   => MARZGARD_VERSION,
				'isAdmin'   => is_admin(),
				'demosUrl'  => admin_url( 'admin.php?page=marzgard-demos' ),
			)
		);
	}
}
