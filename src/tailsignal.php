<?php
/**
 * TailSignal - Push Notifications for WordPress via Expo
 *
 * @package     TailSignal
 * @author      MrDemonWolf, Inc.
 * @copyright   Copyright (c) 2025-2026, MrDemonWolf, Inc.
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: TailSignal
 * Plugin URI:  https://github.com/mrdemonwolf/TailSignal
 * Description: A self-hosted WordPress plugin using Expo to send custom push notifications. Own your data, bypass OneSignal, and keep your pack in the loop with a wag.
 * Version:     1.2.0
 * Author:      MrDemonWolf, Inc.
 * Author URI:  https://github.com/mrdemonwolf
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tailsignal
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TAILSIGNAL_VERSION', '1.2.0' );
define( 'TAILSIGNAL_PLUGIN_FILE', __FILE__ );
define( 'TAILSIGNAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAILSIGNAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TAILSIGNAL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load Composer autoloader.
if ( file_exists( TAILSIGNAL_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once TAILSIGNAL_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include core files.
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal-loader.php';
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal-i18n.php';
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal-db.php';
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal-activator.php';
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal-deactivator.php';
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal-expo.php';
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal-notification.php';
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal-cron.php';
require_once TAILSIGNAL_PLUGIN_DIR . 'includes/class-tailsignal.php';

// Include REST API.
require_once TAILSIGNAL_PLUGIN_DIR . 'rest-api/class-tailsignal-rest-controller.php';

// Include admin files.
if ( is_admin() ) {
	require_once TAILSIGNAL_PLUGIN_DIR . 'admin/class-tailsignal-admin.php';
	require_once TAILSIGNAL_PLUGIN_DIR . 'admin/class-tailsignal-admin-dashboard.php';
	require_once TAILSIGNAL_PLUGIN_DIR . 'admin/class-tailsignal-admin-send.php';
	require_once TAILSIGNAL_PLUGIN_DIR . 'admin/class-tailsignal-admin-devices.php';
	require_once TAILSIGNAL_PLUGIN_DIR . 'admin/class-tailsignal-admin-groups.php';
	require_once TAILSIGNAL_PLUGIN_DIR . 'admin/class-tailsignal-admin-history.php';
	require_once TAILSIGNAL_PLUGIN_DIR . 'admin/class-tailsignal-admin-settings.php';
	require_once TAILSIGNAL_PLUGIN_DIR . 'admin/class-tailsignal-meta-box.php';
}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'TailSignal_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TailSignal_Deactivator', 'deactivate' ) );

/**
 * Begin execution of the plugin.
 */
function tailsignal_run() {
	$plugin = new TailSignal();
	$plugin->run();
}
tailsignal_run();
