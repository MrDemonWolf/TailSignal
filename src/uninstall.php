<?php
/**
 * Fired when the plugin is uninstalled (deleted).
 *
 * @package TailSignal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load the DB class for table cleanup.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tailsignal-db.php';

// Drop all custom tables.
TailSignal_DB::drop_tables();

// Delete all plugin options.
$options = array(
	'tailsignal_auto_notify',
	'tailsignal_expo_access_token',
	'tailsignal_default_title',
	'tailsignal_default_body',
	'tailsignal_use_featured_image',
	'tailsignal_dev_mode',
	'tailsignal_db_version',
	'tailsignal_portfolio_auto_notify',
	'tailsignal_portfolio_default_title',
	'tailsignal_portfolio_default_body',
	'tailsignal_portfolio_use_featured_image',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove custom capability from all roles.
global $wp_roles;

if ( isset( $wp_roles ) ) {
	foreach ( $wp_roles->roles as $role_name => $role_info ) {
		$role = get_role( $role_name );
		if ( $role && $role->has_cap( 'tailsignal_manage' ) ) {
			$role->remove_cap( 'tailsignal_manage' );
		}
	}
}

// Clean up post meta.
delete_post_meta_by_key( '_tailsignal_notify' );
delete_post_meta_by_key( '_tailsignal_notified' );
delete_post_meta_by_key( '_tailsignal_custom_title' );
delete_post_meta_by_key( '_tailsignal_custom_body' );
delete_post_meta_by_key( '_tailsignal_include_image' );

// Clear any remaining cron events (all args variants).
wp_unschedule_hook( 'tailsignal_check_receipts' );
wp_unschedule_hook( 'tailsignal_send_scheduled' );
