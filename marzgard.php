<?php
/**
 * Plugin Name: مرزگارد | MarzGard
 * Plugin URI:  https://marzgard.ir
 * Description: ورود و ثبت‌نام امن با شماره موبایل و کد یکبارمصرف (OTP) — رابط کاربری هدلس مبتنی بر Vue 3، فونت آراد و سیستم طراحی سرخآب.
 * Version:     1.0.0
 * Author:      مرزگارد
 * Author URI:  https://marzgard.ir
 * Text Domain: marzgard
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MARZGARD_VERSION', '1.0.0' );
define( 'MARZGARD_FILE', __FILE__ );
define( 'MARZGARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'MARZGARD_URL', plugin_dir_url( __FILE__ ) );
define( 'MARZGARD_TEXT_DOMAIN', 'marzgard' );
define( 'MARZGARD_REST_NS', 'marzgard/v1' );

require_once MARZGARD_DIR . 'includes/helpers.php';
require_once MARZGARD_DIR . 'includes/class-gateway.php';
require_once MARZGARD_DIR . 'includes/class-otp.php';
require_once MARZGARD_DIR . 'includes/class-rest-controller.php';
require_once MARZGARD_DIR . 'includes/class-security.php';
require_once MARZGARD_DIR . 'includes/class-retention.php';
require_once MARZGARD_DIR . 'includes/class-assets.php';
require_once MARZGARD_DIR . 'includes/class-shortcodes.php';
require_once MARZGARD_DIR . 'includes/class-settings.php';
require_once MARZGARD_DIR . 'includes/class-marzgard.php';
require_once MARZGARD_DIR . 'includes/class-modules.php';

// Bundled modules are part of MarzGard (no separate plugins required).
MarzGard_Modules::load();

/**
 * Main access point.
 *
 * @return MarzGard
 */
function marzgard() {
	return MarzGard::instance();
}

add_action( 'plugins_loaded', 'marzgard' );

register_activation_hook( __FILE__, array( 'MarzGard', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MarzGard', 'deactivate' ) );
