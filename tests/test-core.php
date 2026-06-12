<?php
/**
 * Tests for TailSignal core class.
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-loader.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-i18n.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-expo.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-notification.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-cron.php';
require_once dirname( __DIR__ ) . '/src/rest-api/class-tailsignal-rest-controller.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-settings.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-send.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-groups.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-devices.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-history.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-dashboard.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-meta-box.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal.php';

class Test_TailSignal_Core extends TailSignal_TestCase {

	/**
	 * Test get_loader returns TailSignal_Loader instance.
	 */
	public function test_get_loader_returns_loader_instance() {
		Functions\when( 'is_admin' )->justReturn( false );

		$plugin = new TailSignal();
		$this->assertInstanceOf( TailSignal_Loader::class, $plugin->get_loader() );
	}

	/**
	 * Test run triggers loader run.
	 */
	public function test_run_triggers_loader_run() {
		Functions\when( 'is_admin' )->justReturn( false );

		// After run(), add_action and add_filter should be called.
		Functions\expect( 'add_action' )->atLeast()->once();
		Functions\expect( 'add_filter' )->atLeast()->once();

		$plugin = new TailSignal();
		$plugin->run();
		$this->assertTrue( true );
	}

	/**
	 * Test REST hooks are registered.
	 */
	public function test_rest_hooks_registered() {
		Functions\when( 'is_admin' )->justReturn( false );

		$registered_actions = array();
		$registered_filters = array();

		Functions\expect( 'add_action' )->andReturnUsing( function( $hook ) use ( &$registered_actions ) {
			$registered_actions[] = $hook;
		} );
		Functions\expect( 'add_filter' )->andReturnUsing( function( $hook ) use ( &$registered_filters ) {
			$registered_filters[] = $hook;
		} );

		$plugin = new TailSignal();
		$plugin->run();

		$this->assertContains( 'rest_api_init', $registered_actions );
		$this->assertContains( 'rest_pre_serve_request', $registered_filters );
	}

	/**
	 * Test notification hooks registered.
	 */
	public function test_notification_hooks_registered() {
		Functions\when( 'is_admin' )->justReturn( false );

		$registered_actions = array();

		Functions\expect( 'add_action' )->andReturnUsing( function( $hook ) use ( &$registered_actions ) {
			$registered_actions[] = $hook;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		$this->assertContains( 'transition_post_status', $registered_actions );
	}

	/**
	 * Test cron hooks registered.
	 */
	public function test_cron_hooks_registered() {
		Functions\when( 'is_admin' )->justReturn( false );

		$registered_actions = array();

		Functions\expect( 'add_action' )->andReturnUsing( function( $hook ) use ( &$registered_actions ) {
			$registered_actions[] = $hook;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		$this->assertContains( 'tailsignal_check_receipts', $registered_actions );
		$this->assertContains( 'tailsignal_send_scheduled', $registered_actions );
	}

	/**
	 * Test i18n hook registered.
	 */
	public function test_i18n_hook_registered() {
		Functions\when( 'is_admin' )->justReturn( false );

		$registered_actions = array();

		Functions\expect( 'add_action' )->andReturnUsing( function( $hook ) use ( &$registered_actions ) {
			$registered_actions[] = $hook;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		$this->assertContains( 'plugins_loaded', $registered_actions );
	}

	/**
	 * Test admin hooks registered when is_admin true.
	 */
	public function test_admin_hooks_registered_when_admin() {
		Functions\when( 'is_admin' )->justReturn( true );

		$registered_actions = array();

		Functions\expect( 'add_action' )->andReturnUsing( function( $hook ) use ( &$registered_actions ) {
			$registered_actions[] = $hook;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		$this->assertContains( 'admin_menu', $registered_actions );
		$this->assertContains( 'admin_enqueue_scripts', $registered_actions );
		$this->assertContains( 'admin_init', $registered_actions );
		$this->assertContains( 'add_meta_boxes', $registered_actions );
		$this->assertContains( 'save_post', $registered_actions );
		$this->assertContains( 'wp_ajax_tailsignal_send_notification', $registered_actions );
		$this->assertContains( 'wp_ajax_tailsignal_cancel_scheduled', $registered_actions );
		$this->assertContains( 'wp_ajax_tailsignal_save_group', $registered_actions );
		$this->assertContains( 'wp_ajax_tailsignal_delete_group', $registered_actions );
		$this->assertContains( 'wp_ajax_tailsignal_delete_all_notifications', $registered_actions );
		$this->assertContains( 'wp_ajax_tailsignal_update_device', $registered_actions );
		$this->assertContains( 'wp_ajax_tailsignal_toggle_dev', $registered_actions );
		$this->assertContains( 'wp_ajax_tailsignal_quick_send', $registered_actions );
	}

	/**
	 * Test admin hooks NOT registered when is_admin false.
	 */
	public function test_admin_hooks_not_registered_when_not_admin() {
		Functions\when( 'is_admin' )->justReturn( false );

		$registered_actions = array();

		Functions\expect( 'add_action' )->andReturnUsing( function( $hook ) use ( &$registered_actions ) {
			$registered_actions[] = $hook;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		$this->assertNotContains( 'admin_menu', $registered_actions );
		$this->assertNotContains( 'admin_init', $registered_actions );
		$this->assertNotContains( 'wp_ajax_tailsignal_send_notification', $registered_actions );
	}

	/**
	 * Test non-admin still has base hooks.
	 */
	public function test_non_admin_still_has_base_hooks() {
		Functions\when( 'is_admin' )->justReturn( false );

		$registered_actions = array();

		Functions\expect( 'add_action' )->andReturnUsing( function( $hook ) use ( &$registered_actions ) {
			$registered_actions[] = $hook;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		// Base hooks should always be there.
		$this->assertContains( 'plugins_loaded', $registered_actions );
		$this->assertContains( 'rest_api_init', $registered_actions );
		$this->assertContains( 'transition_post_status', $registered_actions );
		$this->assertContains( 'tailsignal_check_receipts', $registered_actions );
	}

	/**
	 * Test constructor initializes loader.
	 */
	public function test_constructor_initializes_loader() {
		Functions\when( 'is_admin' )->justReturn( false );

		$plugin = new TailSignal();
		$loader = $plugin->get_loader();

		$this->assertNotNull( $loader );
	}

	/**
	 * Test admin enqueue hooks registered when admin.
	 */
	public function test_admin_enqueue_hooks_registered() {
		Functions\when( 'is_admin' )->justReturn( true );

		$registered_actions = array();

		Functions\expect( 'add_action' )->andReturnUsing( function( $hook ) use ( &$registered_actions ) {
			$registered_actions[] = $hook;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		// admin_enqueue_scripts registered twice (styles + scripts).
		$count = array_count_values( $registered_actions );
		$this->assertGreaterThanOrEqual( 2, $count['admin_enqueue_scripts'] );
	}

	/**
	 * Test total hook count for non-admin context.
	 */
	public function test_non_admin_hook_count() {
		Functions\when( 'is_admin' )->justReturn( false );

		$action_count = 0;

		Functions\expect( 'add_action' )->andReturnUsing( function() use ( &$action_count ) {
			$action_count++;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		// plugins_loaded + rest_api_init + transition_post_status + 2 cron + upgrader_process_complete = 6
		$this->assertSame( 6, $action_count );
	}

	/**
	 * Test total hook count for admin context.
	 */
	public function test_admin_hook_count() {
		Functions\when( 'is_admin' )->justReturn( true );

		$action_count = 0;

		Functions\expect( 'add_action' )->andReturnUsing( function() use ( &$action_count ) {
			$action_count++;
		} );
		Functions\expect( 'add_filter' )->andReturn( true );

		$plugin = new TailSignal();
		$plugin->run();

		// 6 base (incl. upgrader_process_complete) + admin_menu + 2 enqueue + admin_footer + admin_init
		// + add_meta_boxes + save_post + quick_send + send + cancel + save_group + delete_group
		// + delete_all + update_device + toggle_dev = 6 + 15 = 21
		$this->assertSame( 21, $action_count );
	}
}
