<?php
/**
 * Delivery logger for Hamidreza Order Notifier.
 *
 * @package Hamidreza
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages lightweight delivery logs for notifications.
 */
class DOT_Bale_Log {

	/**
	 * Option key for logs storage.
	 */
	const OPTION_KEY = 'hamidreza_delivery_logs';

	/**
	 * Maximum number of logs kept in memory/db.
	 */
	const MAX_ENTRIES = 50;

	/**
	 * Appends a log entry to the delivery log.
	 *
	 * @param array $entry {
	 *     Log entry data.
	 *
	 *     @type int|string $order_id Order identifier or 'test'.
	 *     @type string     $platform 'bale'.
	 *     @type string     $status   'success' or 'failed'.
	 *     @type string     $message  Status or error details.
	 * }
	 * @return bool
	 */
	public static function add( array $entry ) {
		$logs = self::get();

		$item = array(
			'time'     => current_time( 'timestamp' ),
			'order_id' => isset( $entry['order_id'] ) ? sanitize_text_field( (string) $entry['order_id'] ) : '-',
			'platform' => isset( $entry['platform'] ) ? sanitize_key( $entry['platform'] ) : 'unknown',
			'status'   => isset( $entry['status'] ) && in_array( $entry['status'], array( 'success', 'skipped' ), true ) ? $entry['status'] : 'failed',
			'message'  => isset( $entry['message'] ) ? sanitize_text_field( $entry['message'] ) : '',
		);

		array_unshift( $logs, $item );

		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		return update_option( self::OPTION_KEY, $logs, false );
	}

	/**
	 * Retrieves all logged delivery attempts.
	 *
	 * @return array
	 */
	public static function get() {
		$logs = get_option( self::OPTION_KEY, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clears all delivery logs.
	 *
	 * @return bool
	 */
	public static function clear() {
		return delete_option( self::OPTION_KEY );
	}
}
