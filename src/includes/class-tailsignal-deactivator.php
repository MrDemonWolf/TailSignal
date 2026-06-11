<?php
/**
 * Fired during plugin deactivation.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		// Clear all scheduled cron events regardless of args —
		// wp_clear_scheduled_hook() with no args only clears events scheduled
		// with an empty args array, which misses every per-notification event.
		wp_unschedule_hook( 'tailsignal_check_receipts' );
		wp_unschedule_hook( 'tailsignal_send_scheduled' );
	}
}
