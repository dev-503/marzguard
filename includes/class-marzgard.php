<?php
/**
 * Main plugin class.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap.
 */
final class MarzGard {

	/**
	 * Singleton instance.
	 *
	 * @var MarzGard|null
	 */
	private static $instance = null;

	/**
	 * Shortcodes manager.
	 *
	 * @var MarzGard_Shortcodes|null
	 */
	private $shortcodes = null;

	/**
	 * Retention campaigns manager (shared with REST routes).
	 *
	 * @var MarzGard_Retention|null
	 */
	private $retention = null;

	/**
	 * Get the singleton.
	 *
	 * @return MarzGard
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Wire up the plugin.
	 */
	private function init() {
		load_plugin_textdomain( MARZGARD_TEXT_DOMAIN, false, dirname( plugin_basename( MARZGARD_FILE ) ) . '/languages' );

		new MarzGard_Assets();
		$this->shortcodes = new MarzGard_Shortcodes();
		new MarzGard_Settings();
		new MarzGard_REST_Controller();
		$this->retention = new MarzGard_Retention();

		add_filter( 'cron_schedules', array( 'MarzGard_Retention', 'add_interval' ) );

		// Elementor integration is loaded after all plugins are available.
		add_action( 'plugins_loaded', array( $this, 'init_elementor' ), 20 );

		add_action( 'show_user_profile', array( $this, 'profile_phone_field' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_phone_field' ) );

		/**
		 * Fires when MarzGard is fully loaded.
		 */
		do_action( 'marzgard_loaded' );
	}

	/**
	 * Get the shortcodes manager.
	 *
	 * @return MarzGard_Shortcodes|null
	 */
	public function shortcodes() {
		return $this->shortcodes;
	}

	/**
	 * Get the shared retention manager.
	 *
	 * @return MarzGard_Retention|null
	 */
	public function retention() {
		return $this->retention;
	}

	/**
	 * Load Elementor integration when Elementor is active.
	 */
	public function init_elementor() {
		if ( class_exists( '\Elementor\Widget_Base' ) && ! class_exists( 'MarzGard_Elementor', false ) ) {
			require_once MARZGARD_DIR . 'includes/class-elementor.php';
			new MarzGard_Elementor();
		}
	}

	/**
	 * Show the verified phone number on the user profile screen.
	 *
	 * @param WP_User $user User.
	 */
	public function profile_phone_field( $user ) {
		$phone = get_user_meta( $user->ID, 'digits_phone', true );
		if ( empty( $phone ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'شماره تأییدشده', 'marzgard' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'موبایل', 'marzgard' ); ?></th>
				<td><input type="text" class="regular-text" dir="ltr" value="<?php echo esc_attr( $phone ); ?>" readonly></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		if ( false === get_option( 'marzgard_options' ) ) {
			update_option( 'marzgard_options', marzgard_default_options() );
		}
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
