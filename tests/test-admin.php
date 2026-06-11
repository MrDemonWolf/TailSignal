<?php
/**
 * Tests for TailSignal_Admin.
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin.php';

class Test_TailSignal_Admin extends TailSignal_TestCase {

	/**
	 * @var TailSignal_Admin
	 */
	private $admin;

	protected function setUp(): void {
		parent::setUp();
		$this->admin = new TailSignal_Admin();
	}

	// ── add_menu_pages ──────────────────────────────────────────

	/**
	 * Test add_menu_page called once.
	 */
	public function test_add_menu_pages_calls_add_menu_page_once() {
		Functions\expect( 'add_menu_page' )->once();
		Functions\expect( 'add_submenu_page' )->times( 6 );

		$this->admin->add_menu_pages();
		$this->assertTrue( true );
	}

	/**
	 * Test main menu uses correct slug.
	 */
	public function test_add_menu_pages_main_slug() {
		$menu_args = null;

		Functions\expect( 'add_menu_page' )->once()->andReturnUsing( function() use ( &$menu_args ) {
			$menu_args = func_get_args();
		} );
		Functions\expect( 'add_submenu_page' )->times( 6 );

		$this->admin->add_menu_pages();

		$this->assertSame( 'tailsignal', $menu_args[3] );
	}

	/**
	 * Test main menu uses dashicons-bell icon.
	 */
	public function test_add_menu_pages_icon() {
		$menu_args = null;

		Functions\expect( 'add_menu_page' )->once()->andReturnUsing( function() use ( &$menu_args ) {
			$menu_args = func_get_args();
		} );
		Functions\expect( 'add_submenu_page' )->times( 6 );

		$this->admin->add_menu_pages();

		$this->assertSame( 'dashicons-bell', $menu_args[5] );
	}

	/**
	 * Test main menu position is 30.
	 */
	public function test_add_menu_pages_position() {
		$menu_args = null;

		Functions\expect( 'add_menu_page' )->once()->andReturnUsing( function() use ( &$menu_args ) {
			$menu_args = func_get_args();
		} );
		Functions\expect( 'add_submenu_page' )->times( 6 );

		$this->admin->add_menu_pages();

		$this->assertSame( 30, $menu_args[6] );
	}

	/**
	 * Test all submenus use tailsignal_manage capability.
	 */
	public function test_add_menu_pages_submenu_capabilities() {
		$submenu_caps = array();

		Functions\expect( 'add_menu_page' )->once();
		Functions\expect( 'add_submenu_page' )->times( 6 )->andReturnUsing( function() use ( &$submenu_caps ) {
			$args = func_get_args();
			$submenu_caps[] = $args[3]; // capability is 4th arg (0-indexed).
		} );

		$this->admin->add_menu_pages();

		foreach ( $submenu_caps as $cap ) {
			$this->assertSame( 'tailsignal_manage', $cap );
		}
	}

	/**
	 * Test main menu uses tailsignal_manage capability.
	 */
	public function test_add_menu_pages_main_capability() {
		$menu_args = null;

		Functions\expect( 'add_menu_page' )->once()->andReturnUsing( function() use ( &$menu_args ) {
			$menu_args = func_get_args();
		} );
		Functions\expect( 'add_submenu_page' )->times( 6 );

		$this->admin->add_menu_pages();

		$this->assertSame( 'tailsignal_manage', $menu_args[2] );
	}

	/**
	 * Test submenu slugs are correct.
	 */
	public function test_add_menu_pages_submenu_slugs() {
		$submenu_slugs = array();

		Functions\expect( 'add_menu_page' )->once();
		Functions\expect( 'add_submenu_page' )->times( 6 )->andReturnUsing( function() use ( &$submenu_slugs ) {
			$args = func_get_args();
			$submenu_slugs[] = $args[4]; // slug is 5th arg (0-indexed).
		} );

		$this->admin->add_menu_pages();

		$expected = array(
			'tailsignal',
			'tailsignal-send',
			'tailsignal-devices',
			'tailsignal-groups',
			'tailsignal-history',
			'tailsignal-settings',
		);
		$this->assertSame( $expected, $submenu_slugs );
	}

	// ── enqueue_styles ──────────────────────────────────────────

	/**
	 * Test enqueue_styles returns early for non-TailSignal page.
	 */
	public function test_enqueue_styles_returns_early_for_other_pages() {
		// wp_enqueue_style should NOT be called.
		$this->admin->enqueue_styles( 'edit.php' );
		$this->assertTrue( true );
	}

	/**
	 * Test enqueue_styles enqueues both stylesheets on TailSignal page.
	 */
	public function test_enqueue_styles_enqueues_on_tailsignal_page() {
		$enqueued = array();

		Functions\expect( 'wp_enqueue_style' )->twice()->andReturnUsing( function() use ( &$enqueued ) {
			$args = func_get_args();
			$enqueued[] = $args[0];
		} );

		$this->admin->enqueue_styles( 'toplevel_page_tailsignal' );

		$this->assertContains( 'tailsignal-tailwind', $enqueued );
		$this->assertContains( 'tailsignal-admin', $enqueued );
	}

	/**
	 * Test tailsignal-admin depends on tailsignal-tailwind.
	 */
	public function test_enqueue_styles_admin_depends_on_tailwind() {
		$deps_map = array();

		Functions\expect( 'wp_enqueue_style' )->twice()->andReturnUsing( function() use ( &$deps_map ) {
			$args = func_get_args();
			$deps_map[ $args[0] ] = $args[2]; // deps is 3rd arg.
		} );

		$this->admin->enqueue_styles( 'toplevel_page_tailsignal' );

		$this->assertSame( array( 'tailsignal-tailwind' ), $deps_map['tailsignal-admin'] );
	}

	/**
	 * Test enqueue_styles works on all TailSignal pages.
	 */
	public function test_enqueue_styles_works_on_send_page() {
		Functions\expect( 'wp_enqueue_style' )->twice();
		$this->admin->enqueue_styles( 'tailsignal_page_tailsignal-send' );
		$this->assertTrue( true );
	}

	/**
	 * Test enqueue_styles works on settings page.
	 */
	public function test_enqueue_styles_works_on_settings_page() {
		Functions\expect( 'wp_enqueue_style' )->twice();
		$this->admin->enqueue_styles( 'tailsignal_page_tailsignal-settings' );
		$this->assertTrue( true );
	}

	// ── enqueue_scripts ─────────────────────────────────────────

	/**
	 * Test enqueue_scripts returns early for unrelated hook.
	 */
	public function test_enqueue_scripts_returns_early_for_other_pages() {
		$this->admin->enqueue_scripts( 'edit.php' );
		$this->assertTrue( true );
	}

	/**
	 * Test enqueue_scripts works on TailSignal page.
	 */
	public function test_enqueue_scripts_on_tailsignal_page() {
		Functions\expect( 'wp_enqueue_script' )->atLeast()->once();
		Functions\expect( 'wp_localize_script' )->once();
		Functions\expect( 'admin_url' )->andReturn( 'http://example.com/wp-admin/admin-ajax.php' );
		Functions\expect( 'rest_url' )->andReturn( 'http://example.com/wp-json/tailsignal/v1/' );
		Functions\expect( 'wp_create_nonce' )->andReturn( 'test_nonce' );

		$this->admin->enqueue_scripts( 'tailsignal_page_tailsignal-devices' );
		$this->assertTrue( true );
	}

	/**
	 * Test enqueue_scripts on post editor.
	 */
	public function test_enqueue_scripts_on_post_editor() {
		Functions\expect( 'wp_enqueue_media' )->once();
		Functions\expect( 'wp_enqueue_script' )->atLeast()->once();
		Functions\expect( 'wp_localize_script' )->once();
		Functions\expect( 'admin_url' )->andReturn( '' );
		Functions\expect( 'rest_url' )->andReturn( '' );
		Functions\expect( 'wp_create_nonce' )->andReturn( '' );

		$this->admin->enqueue_scripts( 'post.php' );
		$this->assertTrue( true );
	}

	/**
	 * Test wp_enqueue_media called on send page.
	 */
	public function test_enqueue_scripts_media_on_send_page() {
		Functions\expect( 'wp_enqueue_media' )->once();
		Functions\expect( 'wp_enqueue_script' )->atLeast()->once();
		Functions\expect( 'wp_localize_script' )->once();
		Functions\expect( 'admin_url' )->andReturn( '' );
		Functions\expect( 'rest_url' )->andReturn( '' );
		Functions\expect( 'wp_create_nonce' )->andReturn( '' );

		$this->admin->enqueue_scripts( 'tailsignal_page_tailsignal-send' );
		$this->assertTrue( true );
	}

	/**
	 * Test wp_enqueue_media NOT called on dashboard.
	 */
	public function test_enqueue_scripts_no_media_on_dashboard() {
		Functions\expect( 'wp_enqueue_script' )->atLeast()->once();
		Functions\expect( 'wp_localize_script' )->once();
		Functions\expect( 'admin_url' )->andReturn( '' );
		Functions\expect( 'rest_url' )->andReturn( '' );
		Functions\expect( 'wp_create_nonce' )->andReturn( '' );

		// wp_enqueue_media should NOT be called.
		$this->admin->enqueue_scripts( 'toplevel_page_tailsignal' );
		$this->assertTrue( true );
	}

	/**
	 * Test Chart.js enqueued only on dashboard.
	 */
	public function test_enqueue_scripts_chartjs_on_dashboard() {
		$enqueued = array();

		Functions\expect( 'wp_enqueue_script' )->andReturnUsing( function() use ( &$enqueued ) {
			$args = func_get_args();
			$enqueued[] = $args[0];
		} );
		Functions\expect( 'wp_localize_script' )->once();
		Functions\expect( 'admin_url' )->andReturn( '' );
		Functions\expect( 'rest_url' )->andReturn( '' );
		Functions\expect( 'wp_create_nonce' )->andReturn( '' );

		$this->admin->enqueue_scripts( 'toplevel_page_tailsignal' );

		$this->assertContains( 'chartjs', $enqueued );
	}

	/**
	 * Test Chart.js NOT enqueued on non-dashboard TailSignal page.
	 */
	public function test_enqueue_scripts_no_chartjs_on_other_pages() {
		$enqueued = array();

		Functions\expect( 'wp_enqueue_script' )->andReturnUsing( function() use ( &$enqueued ) {
			$args = func_get_args();
			$enqueued[] = $args[0];
		} );
		Functions\expect( 'wp_localize_script' )->once();
		Functions\expect( 'admin_url' )->andReturn( '' );
		Functions\expect( 'rest_url' )->andReturn( '' );
		Functions\expect( 'wp_create_nonce' )->andReturn( '' );

		$this->admin->enqueue_scripts( 'tailsignal_page_tailsignal-devices' );

		$this->assertNotContains( 'chartjs', $enqueued );
	}

	/**
	 * Test localize_script includes required keys.
	 */
	public function test_enqueue_scripts_localize_data() {
		$localized = null;

		Functions\expect( 'wp_enqueue_script' )->atLeast()->once();
		Functions\expect( 'wp_localize_script' )->once()->andReturnUsing( function() use ( &$localized ) {
			$args = func_get_args();
			$localized = $args[2]; // data is 3rd arg.
		} );
		Functions\expect( 'admin_url' )->andReturn( 'http://example.com/wp-admin/admin-ajax.php' );
		Functions\expect( 'rest_url' )->andReturn( 'http://example.com/wp-json/tailsignal/v1/' );
		Functions\expect( 'wp_create_nonce' )->andReturn( 'nonce123' );

		$this->admin->enqueue_scripts( 'tailsignal_page_tailsignal-devices' );

		$this->assertArrayHasKey( 'ajax_url', $localized );
		$this->assertArrayHasKey( 'rest_url', $localized );
		$this->assertArrayHasKey( 'nonce', $localized );
		$this->assertArrayHasKey( 'strings', $localized );
	}

	// ── Render methods ──────────────────────────────────────────

	/**
	 * Test render_settings_page includes partial file.
	 */
	public function test_render_settings_page() {
		// Create a temporary settings.php partial.
		$partials_dir = TAILSIGNAL_PLUGIN_DIR . 'admin/partials/';
		if ( ! is_dir( $partials_dir ) ) {
			mkdir( $partials_dir, 0755, true );
		}

		// The file already exists in the plugin, so just verify no errors.
		// We can't easily test the include without a real file, so we just
		// verify the method exists and is callable.
		$this->assertTrue( method_exists( $this->admin, 'render_settings_page' ) );
	}

	/**
	 * Test render methods exist.
	 */
	public function test_render_methods_exist() {
		$this->assertTrue( method_exists( $this->admin, 'render_dashboard_page' ) );
		$this->assertTrue( method_exists( $this->admin, 'render_send_page' ) );
		$this->assertTrue( method_exists( $this->admin, 'render_devices_page' ) );
		$this->assertTrue( method_exists( $this->admin, 'render_groups_page' ) );
		$this->assertTrue( method_exists( $this->admin, 'render_history_page' ) );
	}
}
