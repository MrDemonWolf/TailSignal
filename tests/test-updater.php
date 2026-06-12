<?php
/**
 * Tests for TailSignal_Updater (GitHub update checker).
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-loader.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-updater.php';

class Test_TailSignal_Updater extends TailSignal_TestCase {

	/** @var string Fake plugin basename used across tests. */
	const PLUGIN_SLUG = 'tailsignal/tailsignal.php';

	/** @var string Fake plugin file path. */
	const PLUGIN_FILE = '/var/www/html/wp-content/plugins/tailsignal/tailsignal.php';

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create an updater instance backed by a real TailSignal_Loader stub.
	 *
	 * @return TailSignal_Updater
	 */
	private function make_updater() {
		Functions\when( 'plugin_basename' )->justReturn( self::PLUGIN_SLUG );
		$loader = $this->make_loader_stub();
		return new TailSignal_Updater( self::PLUGIN_FILE, $loader );
	}

	/**
	 * Build a TailSignal_Loader that silently accepts hook registrations.
	 *
	 * @return TailSignal_Loader
	 */
	private function make_loader_stub() {
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		return new TailSignal_Loader();
	}

	/**
	 * Build a minimal GitHub API response body for a release.
	 *
	 * @param string $tag    Tag name, e.g. "v2.0.0".
	 * @param array  $assets Optional assets array.
	 * @return string JSON.
	 */
	private function make_github_response( $tag = 'v2.0.0', $assets = array() ) {
		return json_encode( array(
			'tag_name'     => $tag,
			'html_url'     => 'https://github.com/mrdemonwolf/TailSignal/releases/tag/' . $tag,
			'zipball_url'  => 'https://api.github.com/repos/mrdemonwolf/TailSignal/zipball/' . $tag,
			'published_at' => '2026-01-15T12:00:00Z',
			'body'         => '## What\'s New' . "\n" . '- Feature A',
			'assets'       => $assets,
		) );
	}

	/**
	 * Mock a successful GitHub API response and all WP functions it touches.
	 *
	 * @param string $tag    Tag name.
	 * @param array  $assets Optional release assets.
	 */
	private function mock_successful_remote_get( $tag, $assets = array() ) {
		$body = $this->make_github_response( $tag, $assets );

		Functions\when( 'wp_remote_get' )->justReturn( array(
			'response' => array( 'code' => 200 ),
			'body'     => $body,
		) );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $body );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );
		Functions\when( 'get_option' )->justReturn( 'Y-m-d' );
	}

	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	public function test_constructor_creates_instance() {
		Functions\when( 'plugin_basename' )->justReturn( self::PLUGIN_SLUG );
		$loader  = $this->make_loader_stub();
		$updater = new TailSignal_Updater( self::PLUGIN_FILE, $loader );
		$this->assertInstanceOf( TailSignal_Updater::class, $updater );
	}

	// -------------------------------------------------------------------------
	// init() hook registration via loader
	// -------------------------------------------------------------------------

	public function test_init_registers_hooks_through_loader() {
		Functions\when( 'plugin_basename' )->justReturn( self::PLUGIN_SLUG );

		$filters = array();
		$actions = array();

		Functions\when( 'add_filter' )->alias( function( $hook ) use ( &$filters ) {
			$filters[] = $hook;
		} );
		Functions\when( 'add_action' )->alias( function( $hook ) use ( &$actions ) {
			$actions[] = $hook;
		} );

		$loader  = new TailSignal_Loader();
		$updater = new TailSignal_Updater( self::PLUGIN_FILE, $loader );
		$updater->init();
		$loader->run();

		$this->assertContains( 'pre_set_site_transient_update_plugins', $filters );
		$this->assertContains( 'plugins_api', $filters );
		$this->assertContains( 'plugin_row_meta', $filters );
		$this->assertContains( 'upgrader_process_complete', $actions );
	}

	// -------------------------------------------------------------------------
	// check_for_update()
	// -------------------------------------------------------------------------

	public function test_check_for_update_returns_transient_unchanged_when_checked_empty() {
		$updater   = $this->make_updater();
		$transient = new stdClass();

		$result = $updater->check_for_update( $transient );

		$this->assertSame( $transient, $result );
		$this->assertFalse( isset( $result->response ) );
	}

	public function test_check_for_update_returns_unchanged_when_backoff_active() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		// Backoff transient is set — should skip remote request entirely.
		Functions\when( 'get_transient' )->alias( function( $key ) {
			return TailSignal_Updater::TRANSIENT_BACKOFF_KEY === $key ? true : false;
		} );
		Functions\when( 'wp_remote_get' )->alias( function() {
			throw new \RuntimeException( 'wp_remote_get must not be called during backoff.' );
		} );

		$result = $updater->check_for_update( $transient );

		$this->assertFalse( isset( $result->response[ self::PLUGIN_SLUG ] ) );
	}

	public function test_check_for_update_returns_unchanged_when_release_unavailable() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.2.0' ) );

		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'timeout' ) );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );

		$result = $updater->check_for_update( $transient );

		$this->assertFalse( isset( $result->response[ self::PLUGIN_SLUG ] ) );
	}

	public function test_check_for_update_injects_update_when_newer_version_available() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.2.0' ) );

		$this->mock_successful_remote_get( 'v2.0.0' );

		$result = $updater->check_for_update( $transient );

		$this->assertTrue( isset( $result->response[ self::PLUGIN_SLUG ] ) );
		$update = $result->response[ self::PLUGIN_SLUG ];
		$this->assertSame( '2.0.0', $update->new_version );
		$this->assertSame( self::PLUGIN_SLUG, $update->plugin );
	}

	public function test_check_for_update_adds_no_update_when_version_is_same() {
		// TAILSIGNAL_VERSION is '1.0.0' in bootstrap.
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$this->mock_successful_remote_get( 'v1.0.0' );

		$result = $updater->check_for_update( $transient );

		$this->assertFalse( isset( $result->response[ self::PLUGIN_SLUG ] ) );
		$this->assertTrue( isset( $result->no_update[ self::PLUGIN_SLUG ] ) );
	}

	public function test_check_for_update_prefers_named_zip_asset() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$assets = array(
			array(
				'name'                 => 'tailsignal.zip',
				'browser_download_url' => 'https://github.com/releases/download/v2.0.0/tailsignal.zip',
			),
		);
		$this->mock_successful_remote_get( 'v2.0.0', $assets );

		$result = $updater->check_for_update( $transient );
		$update = $result->response[ self::PLUGIN_SLUG ];

		$this->assertSame(
			'https://github.com/releases/download/v2.0.0/tailsignal.zip',
			$update->package
		);
	}

	public function test_check_for_update_falls_back_to_zipball_without_named_asset() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$this->mock_successful_remote_get( 'v2.0.0', array() );

		$result = $updater->check_for_update( $transient );
		$update = $result->response[ self::PLUGIN_SLUG ];

		$this->assertStringContainsString( 'zipball', $update->package );
	}

	public function test_check_for_update_strips_lowercase_v_prefix() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$this->mock_successful_remote_get( 'v3.1.0' );

		$result = $updater->check_for_update( $transient );
		$this->assertSame( '3.1.0', $result->response[ self::PLUGIN_SLUG ]->new_version );
	}

	public function test_check_for_update_strips_uppercase_v_prefix() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$this->mock_successful_remote_get( 'V2.5.0' );

		$result = $updater->check_for_update( $transient );
		$this->assertSame( '2.5.0', $result->response[ self::PLUGIN_SLUG ]->new_version );
	}

	public function test_check_for_update_update_has_correct_requires_fields() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$this->mock_successful_remote_get( 'v2.0.0' );

		$result = $updater->check_for_update( $transient );
		$update = $result->response[ self::PLUGIN_SLUG ];

		$this->assertSame( '6.0', $update->requires );
		$this->assertSame( '7.4', $update->requires_php );
	}

	// -------------------------------------------------------------------------
	// plugin_info()
	// -------------------------------------------------------------------------

	public function test_plugin_info_returns_false_for_wrong_action() {
		$updater = $this->make_updater();
		$args    = (object) array( 'slug' => 'tailsignal' );

		$result = $updater->plugin_info( false, 'query_plugins', $args );

		$this->assertFalse( $result );
	}

	public function test_plugin_info_returns_false_for_wrong_slug() {
		$updater = $this->make_updater();
		$args    = (object) array( 'slug' => 'some-other-plugin' );

		$result = $updater->plugin_info( false, 'plugin_information', $args );

		$this->assertFalse( $result );
	}

	public function test_plugin_info_returns_object_for_matching_slug() {
		$updater = $this->make_updater();
		$args    = (object) array( 'slug' => 'tailsignal' );

		$this->mock_successful_remote_get( 'v2.0.0' );

		$result = $updater->plugin_info( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertSame( 'TailSignal', $result->name );
		$this->assertSame( '2.0.0', $result->version );
		$this->assertArrayHasKey( 'description', $result->sections );
		$this->assertArrayHasKey( 'changelog', $result->sections );
	}

	public function test_plugin_info_returns_false_when_release_unavailable() {
		$updater = $this->make_updater();
		$args    = (object) array( 'slug' => 'tailsignal' );

		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'timeout', 'timeout' ) );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );

		$result = $updater->plugin_info( false, 'plugin_information', $args );

		$this->assertFalse( $result );
	}

	public function test_plugin_info_passes_through_existing_result_for_other_slug() {
		$updater  = $this->make_updater();
		$args     = (object) array( 'slug' => 'jetpack' );
		$existing = (object) array( 'name' => 'Jetpack' );

		$result = $updater->plugin_info( $existing, 'plugin_information', $args );

		$this->assertSame( $existing, $result );
	}

	// -------------------------------------------------------------------------
	// after_upgrade()
	// -------------------------------------------------------------------------

	public function test_after_upgrade_deletes_both_transients_on_plugin_update() {
		$updater  = $this->make_updater();
		$deleted  = array();

		Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
			$deleted[] = $key;
			return true;
		} );

		$updater->after_upgrade( null, array( 'type' => 'plugin', 'action' => 'update' ) );

		$this->assertContains( TailSignal_Updater::TRANSIENT_KEY, $deleted );
		$this->assertContains( TailSignal_Updater::TRANSIENT_BACKOFF_KEY, $deleted );
	}

	public function test_after_upgrade_does_not_delete_transient_for_theme_update() {
		$updater = $this->make_updater();
		$deleted = array();

		Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
			$deleted[] = $key;
			return true;
		} );

		$updater->after_upgrade( null, array( 'type' => 'theme', 'action' => 'update' ) );

		$this->assertEmpty( $deleted );
	}

	public function test_after_upgrade_does_not_delete_transient_for_plugin_install() {
		$updater = $this->make_updater();
		$deleted = array();

		Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
			$deleted[] = $key;
			return true;
		} );

		$updater->after_upgrade( null, array( 'type' => 'plugin', 'action' => 'install' ) );

		$this->assertEmpty( $deleted );
	}

	public function test_after_upgrade_handles_empty_hook_extra() {
		$updater = $this->make_updater();
		$deleted = array();

		Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
			$deleted[] = $key;
			return true;
		} );

		$updater->after_upgrade( null, array() );

		$this->assertEmpty( $deleted );
	}

	// -------------------------------------------------------------------------
	// add_row_meta()
	// -------------------------------------------------------------------------

	public function test_add_row_meta_adds_github_link_for_matching_plugin() {
		$updater = $this->make_updater();
		$links   = array( 'Settings', 'Deactivate' );

		$result = $updater->add_row_meta( $links, self::PLUGIN_SLUG );

		$this->assertCount( 3, $result );
		$this->assertStringContainsString( 'github.com', $result[2] );
		$this->assertStringContainsString( 'mrdemonwolf/TailSignal', $result[2] );
	}

	public function test_add_row_meta_does_not_modify_links_for_other_plugins() {
		$updater = $this->make_updater();
		$links   = array( 'Settings', 'Deactivate' );

		$result = $updater->add_row_meta( $links, 'some-other-plugin/some-other-plugin.php' );

		$this->assertSame( $links, $result );
	}

	// -------------------------------------------------------------------------
	// Caching behaviour
	// -------------------------------------------------------------------------

	public function test_uses_cached_release_without_remote_request() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$cached = array(
			'version'      => '9.9.9',
			'html_url'     => 'https://github.com/mrdemonwolf/TailSignal/releases/tag/v9.9.9',
			'zip_url'      => 'https://github.com/releases/download/v9.9.9/tailsignal.zip',
			'published_at' => 'January 1, 2030',
			'body'         => 'Big release.',
		);

		// Backoff absent, success cache warm — no HTTP call.
		Functions\when( 'get_transient' )->alias( function( $key ) use ( $cached ) {
			if ( TailSignal_Updater::TRANSIENT_BACKOFF_KEY === $key ) return false;
			if ( TailSignal_Updater::TRANSIENT_KEY === $key )         return $cached;
			return false;
		} );
		Functions\when( 'wp_remote_get' )->alias( function() {
			throw new \RuntimeException( 'wp_remote_get must not be called when cache is warm.' );
		} );

		$result = $updater->check_for_update( $transient );

		$this->assertTrue( isset( $result->response[ self::PLUGIN_SLUG ] ) );
		$this->assertSame( '9.9.9', $result->response[ self::PLUGIN_SLUG ]->new_version );
	}

	public function test_caches_release_after_successful_remote_request() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$this->mock_successful_remote_get( 'v2.0.0' );

		$stored     = null;
		$stored_ttl = null;
		Functions\when( 'set_transient' )->alias(
			function( $key, $value, $expiration ) use ( &$stored, &$stored_ttl ) {
				if ( TailSignal_Updater::TRANSIENT_KEY === $key ) {
					$stored     = $value;
					$stored_ttl = $expiration;
				}
				return true;
			}
		);

		$updater->check_for_update( $transient );

		$this->assertIsArray( $stored );
		$this->assertSame( '2.0.0', $stored['version'] );
		$this->assertSame( TailSignal_Updater::CACHE_SECS, $stored_ttl );
	}

	public function test_sets_backoff_transient_on_network_failure() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'timeout', 'timeout' ) );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );

		$backoff_set = false;
		$backoff_ttl = null;
		Functions\when( 'set_transient' )->alias(
			function( $key, $value, $expiration ) use ( &$backoff_set, &$backoff_ttl ) {
				if ( TailSignal_Updater::TRANSIENT_BACKOFF_KEY === $key ) {
					$backoff_set = true;
					$backoff_ttl = $expiration;
				}
				return true;
			}
		);

		$updater->check_for_update( $transient );

		$this->assertTrue( $backoff_set );
		$this->assertSame( TailSignal_Updater::BACKOFF_SECS, $backoff_ttl );
	}

	public function test_does_not_set_success_cache_on_network_failure() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'timeout', 'timeout' ) );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );

		$success_cached = false;
		Functions\when( 'set_transient' )->alias(
			function( $key ) use ( &$success_cached ) {
				if ( TailSignal_Updater::TRANSIENT_KEY === $key ) {
					$success_cached = true;
				}
				return true;
			}
		);

		$updater->check_for_update( $transient );

		$this->assertFalse( $success_cached );
	}

	public function test_sets_backoff_on_non_200_response() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		Functions\when( 'wp_remote_get' )->justReturn( array(
			'response' => array( 'code' => 404 ),
			'body'     => '{"message":"Not Found"}',
		) );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 404 );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );

		$backoff_set = false;
		Functions\when( 'set_transient' )->alias(
			function( $key ) use ( &$backoff_set ) {
				if ( TailSignal_Updater::TRANSIENT_BACKOFF_KEY === $key ) {
					$backoff_set = true;
				}
				return true;
			}
		);

		$updater->check_for_update( $transient );

		$this->assertTrue( $backoff_set );
	}

	public function test_sets_backoff_when_tag_name_missing_from_body() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );

		$body = '{"message":"no releases yet"}';
		Functions\when( 'wp_remote_get' )->justReturn( array(
			'response' => array( 'code' => 200 ),
			'body'     => $body,
		) );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $body );
		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );

		$backoff_set = false;
		Functions\when( 'set_transient' )->alias(
			function( $key ) use ( &$backoff_set ) {
				if ( TailSignal_Updater::TRANSIENT_BACKOFF_KEY === $key ) {
					$backoff_set = true;
				}
				return true;
			}
		);

		$updater->check_for_update( $transient );

		$this->assertTrue( $backoff_set );
	}

	// -------------------------------------------------------------------------
	// User-Agent privacy
	// -------------------------------------------------------------------------

	public function test_user_agent_does_not_contain_site_url() {
		$updater   = $this->make_updater();
		$transient = (object) array( 'checked' => array( self::PLUGIN_SLUG => '1.0.0' ) );
		$captured  = null;

		Functions\when( 'get_bloginfo' )->justReturn( '6.5' );
		Functions\when( 'get_option' )->justReturn( 'Y-m-d' );
		Functions\when( 'wp_remote_get' )->alias( function( $url, $args ) use ( &$captured ) {
			$captured = $args['user-agent'] ?? '';
			return new WP_Error( 'stop', 'stop' );
		} );

		$updater->check_for_update( $transient );

		$this->assertNotNull( $captured );
		$this->assertStringNotContainsString( 'example.com', $captured );
		$this->assertStringContainsString( 'TailSignal-Updater/', $captured );
		$this->assertStringContainsString( 'WordPress/', $captured );
	}

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	public function test_github_repo_constant_is_correct() {
		$this->assertSame( 'mrdemonwolf/TailSignal', TailSignal_Updater::GITHUB_REPO );
	}

	public function test_transient_key_constant_is_not_empty() {
		$this->assertNotEmpty( TailSignal_Updater::TRANSIENT_KEY );
	}

	public function test_backoff_key_differs_from_success_key() {
		$this->assertNotSame( TailSignal_Updater::TRANSIENT_KEY, TailSignal_Updater::TRANSIENT_BACKOFF_KEY );
	}

	public function test_cache_seconds_constant_is_positive_integer() {
		$this->assertIsInt( TailSignal_Updater::CACHE_SECS );
		$this->assertGreaterThan( 0, TailSignal_Updater::CACHE_SECS );
	}

	public function test_backoff_seconds_is_much_less_than_cache_seconds() {
		$this->assertLessThan( TailSignal_Updater::CACHE_SECS, TailSignal_Updater::BACKOFF_SECS );
	}
}
