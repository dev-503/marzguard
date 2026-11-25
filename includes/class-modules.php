<?php
/**
 * Bundled modules: order tracking + WooCommerce SMS.
 *
 * Both modules are loaded from inside MarzGard as first-class modules, so
 * only one plugin needs to be installed. They share MarzGard's SMS provider.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module loader.
 */
class MarzGard_Modules {

	/**
	 * Load bundled modules at plugin load time (before plugins_loaded hooks fire).
	 */
	public static function load() {
		$modules_dir = MARZGARD_DIR . 'modules';

		// Module 1: order tracking (WooCommerce required at runtime by the module).
		$tracking = $modules_dir . '/order-tracking/deiara-order-tracking.php';
		if ( file_exists( $tracking ) ) {
			require_once $tracking;
		}

		// Module 3: XCart installer (bundled, menu moved under MarzGard).
		$xcart = $modules_dir . '/xcart-installer/xcart-installer.php';
		if ( file_exists( $xcart ) ) {
			require_once $xcart;
		}

		// Module 2: Persian WooCommerce SMS.
		$sms = $modules_dir . '/woocommerce-sms/WoocommerceIR_SMS.php';
		if ( file_exists( $sms ) ) {
			require_once $sms;
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_module_links' ), 20 );
	}

	/**
	 * Surface module screens under the MarzGard menu.
	 */
	public static function add_module_links() {
		$links = array(
			'marzgard-module-orders' => array(
				__( 'پیگیری سفارشات', 'marzgard' ),
				'admin.php?page=dot-settings',
				'manage_woocommerce',
			),
			'marzgard-module-sms' => array(
				__( 'پیامک ووکامرس', 'marzgard' ),
				'admin.php?page=persian-woocommerce-sms-pro',
				'manage_woocommerce',
			),
			'marzgard-module-bale' => array(
				__( 'اعلان بله', 'marzgard' ),
				'admin.php?page=dot-bale',
				'manage_woocommerce',
			),
		);

		foreach ( $links as $slug => $data ) {
			add_submenu_page(
				'marzgard',
				$data[0],
				$data[0],
				$data[2],
				$slug,
				function () use ( $data ) {
					wp_safe_redirect( admin_url( $data[1] ) );
					exit;
				}
			);
		}
	}
}
