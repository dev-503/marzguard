<?php
/**
 * Plugin Name: پیگیری سفارشات دیارا (Deiara Order Tracking)
 * Description: پیگیری چهار مرحله‌ای سفارش با شورت‌کد، وضعیت‌های سفارشی ووکامرس، ثبت کد رهگیری پست، تکمیل خودکار سفارش و افزودن مشتریان قدیمی به کاربران سایت و اعلان سفارش‌ها در بله.
 * Version: 1.3.7
 * Author: Deiara
 * Text Domain: deiara-order-tracking
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'DOT_VERSION', '1.3.7' );
define( 'DOT_FILE', __FILE__ );
define( 'DOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'DOT_URL', plugin_dir_url( __FILE__ ) );

// سازگاری با HPOS (جداول اختصاصی سفارشات)
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DOT_FILE, true );
	}
} );

register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( 'dot_daily_check' ) ) {
		wp_schedule_event( time() + 300, 'twicedaily', 'dot_daily_check' );
	}
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'dot_daily_check' );
	wp_clear_scheduled_hook( 'hron_send_notification' );
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>افزونه «پیگیری سفارشات دیارا» به ووکامرس نیاز دارد. لطفاً ابتدا ووکامرس را فعال کنید.</p></div>';
		} );
		return;
	}

	require_once DOT_PATH . 'includes/functions.php';
	require_once DOT_PATH . 'includes/class-statuses.php';
	require_once DOT_PATH . 'includes/class-admin.php';
	require_once DOT_PATH . 'includes/class-frontend.php';
	require_once DOT_PATH . 'includes/class-customer-sync.php';

	DOT_Statuses::init();
	DOT_Admin::init();
	DOT_Frontend::init();
	DOT_Customer_Sync::init();

	// اعلان بله (ادغام‌شده از Hamidreza Order Notifier؛ فقط بله)
	require_once DOT_PATH . 'includes/bale/bootstrap.php';

	// اگر زمان‌بندی روزانه به هر دلیلی پاک شده بود، دوباره بساز
	if ( ! wp_next_scheduled( 'dot_daily_check' ) ) {
		wp_schedule_event( time() + 300, 'twicedaily', 'dot_daily_check' );
	}
} );
