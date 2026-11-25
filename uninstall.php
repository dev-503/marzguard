<?php
/**
 * Uninstall routine for MarzGard.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Core + module options and logs.
$options = array(
	'marzgard_options',
	'marzgard_last_sms',
	'marzgard_security_log',
	'marzgard_retention_log',
	'dot_settings',
	'dot_bale_settings',
	'pwsms_sms_main_settings',
	'xcart_installer_redirect',
);
foreach ( $options as $option ) {
	delete_option( $option );
}

// Scheduled events.
$crons = array( 'marzgard_retention_run', 'dot_daily_check', 'hron_send_notification' );
foreach ( $crons as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// Remove transient caches created by the OTP/security/retention managers.
global $wpdb;
if ( isset( $wpdb ) ) {
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mg_%' OR option_name LIKE '_transient_timeout_mg_%'" ); // phpcs:ignore WordPress.DB
}

flush_rewrite_rules();
