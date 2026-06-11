<?php
/**
 * Fired during plugin activation.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		// Create database tables.
		TailSignal_DB::create_tables();

		// Set default options.
		$defaults = array(
			'tailsignal_auto_notify'         => '1',
			'tailsignal_expo_access_token'   => '',
			'tailsignal_default_title'       => 'New from {site_name}',
			'tailsignal_default_body'        => '{post_title}',
			'tailsignal_use_featured_image'  => '1',
			'tailsignal_dev_mode'                      => '0',
			'tailsignal_db_version'                    => TAILSIGNAL_VERSION,
			'tailsignal_portfolio_auto_notify'         => '1',
			'tailsignal_portfolio_default_title'       => 'New Project: {post_title}',
			'tailsignal_portfolio_default_body'        => '{post_title} by {author_name}',
			'tailsignal_portfolio_use_featured_image'  => '1',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}

		// Add custom capability to administrator role.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'tailsignal_manage' );
		}

		// Re-schedule pending scheduled notifications. Deactivation clears
		// their cron events; without this they would sit in 'scheduled'
		// status forever. Overdue ones are sent shortly after reactivation.
		$notifications = TailSignal_DB::get_scheduled_notifications();
		foreach ( $notifications as $notification ) {
			$args = array( (int) $notification->id );
			if ( wp_next_scheduled( 'tailsignal_send_scheduled', $args ) ) {
				continue;
			}
			$timestamp = (int) get_gmt_from_date( $notification->scheduled_at, 'U' );
			wp_schedule_single_event( max( $timestamp, time() + MINUTE_IN_SECONDS ), 'tailsignal_send_scheduled', $args );
		}
	}
}
