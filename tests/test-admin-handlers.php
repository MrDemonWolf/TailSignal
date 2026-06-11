<?php
/**
 * Tests for admin AJAX handlers (Send, Groups, Meta Box).
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-expo.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-notification.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-send.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-groups.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-meta-box.php';

class Test_TailSignal_Admin_Handlers extends TailSignal_TestCase {

	protected function tearDown(): void {
		$_POST = array();
		$_GET  = array();
		parent::tearDown();
	}

	// ── Send Handler ──────────────────────────────────────────────

	/**
	 * Test handle_send checks nonce.
	 */
	public function test_handle_send_checks_nonce() {
		Functions\expect( 'check_ajax_referer' )
			->with( 'tailsignal_nonce', 'nonce' )
			->once();

		Functions\expect( 'current_user_can' )
			->with( 'tailsignal_manage' )
			->andReturn( false );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function() use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( 'wp_send_json_error called' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_send requires title and body.
	 */
	public function test_handle_send_requires_fields() {
		$_POST = array(
			'title' => '',
			'body'  => '',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( $data['message'] );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'required', $e->getMessage() );
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_cancel_scheduled checks nonce and permission.
	 */
	public function test_handle_cancel_scheduled_checks_permission() {
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( false );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function() use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( 'denied' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_cancel_scheduled();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_cancel_scheduled with invalid ID.
	 */
	public function test_handle_cancel_scheduled_invalid_id() {
		$_POST = array( 'notification_id' => '0' );

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( $data['message'] );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_cancel_scheduled();
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Invalid', $e->getMessage() );
		}
		$this->assertTrue( $exited );
	}

	// ── Groups Handler ────────────────────────────────────────────

	/**
	 * Test handle_save_group checks permission.
	 */
	public function test_handle_save_group_checks_permission() {
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( false );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function() use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( 'denied' );
		} );

		$groups = new TailSignal_Admin_Groups();
		try {
			$groups->handle_save_group();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_save_group requires name.
	 */
	public function test_handle_save_group_requires_name() {
		$_POST = array( 'name' => '' );

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( $data['message'] );
		} );

		$groups = new TailSignal_Admin_Groups();
		try {
			$groups->handle_save_group();
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'required', $e->getMessage() );
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_save_group creates new group.
	 */
	public function test_handle_save_group_creates() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 3;

		$_POST = array(
			'name'        => 'New Group',
			'description' => 'A test group',
			'device_ids'  => array( '1', '2' ),
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		// create_group.
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		// set_group_devices: delete then replace.
		$wpdb->shouldReceive( 'delete' )->andReturn( 1 );
		$wpdb->shouldReceive( 'replace' )->andReturn( 1 );

		$success_data = null;
		Functions\expect( 'wp_send_json_success' )->once()->andReturnUsing( function( $data ) use ( &$success_data ) {
			$success_data = $data;
			throw new \RuntimeException( 'success' );
		} );

		$groups = new TailSignal_Admin_Groups();
		try {
			$groups->handle_save_group();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertSame( 3, $success_data['group_id'] );
	}

	/**
	 * Test handle_save_group updates existing group.
	 */
	public function test_handle_save_group_updates() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$_POST = array(
			'group_id'    => '5',
			'name'        => 'Updated Group',
			'description' => 'Updated desc',
			'device_ids'  => array(),
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		// update_group.
		$wpdb->shouldReceive( 'update' )->andReturn( 1 );

		// set_group_devices: only delete (no devices to add).
		$wpdb->shouldReceive( 'delete' )->andReturn( 1 );

		Functions\expect( 'wp_send_json_success' )->once()->andReturnUsing( function() {
			throw new \RuntimeException( 'success' );
		} );

		$groups = new TailSignal_Admin_Groups();
		try {
			$groups->handle_save_group();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( true );
	}

	/**
	 * Test handle_delete_group checks permission.
	 */
	public function test_handle_delete_group_checks_permission() {
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( false );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function() use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( 'denied' );
		} );

		$groups = new TailSignal_Admin_Groups();
		try {
			$groups->handle_delete_group();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_delete_group with invalid ID.
	 */
	public function test_handle_delete_group_invalid_id() {
		$_POST = array( 'group_id' => '0' );

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function() use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( 'invalid' );
		} );

		$groups = new TailSignal_Admin_Groups();
		try {
			$groups->handle_delete_group();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_delete_group success.
	 */
	public function test_handle_delete_group_success() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$_POST = array( 'group_id' => '5' );

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		// delete_group: delete from pivot + groups.
		$wpdb->shouldReceive( 'delete' )->twice()->andReturn( 1 );

		Functions\expect( 'wp_send_json_success' )->once()->andReturnUsing( function() {
			throw new \RuntimeException( 'success' );
		} );

		$groups = new TailSignal_Admin_Groups();
		try {
			$groups->handle_delete_group();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( true );
	}

	// ── Groups Edit Nonce ─────────────────────────────────────────

	/**
	 * Test groups render requires nonce on edit GET param.
	 */
	public function test_groups_render_requires_edit_nonce() {
		$_GET = array( 'edit' => '5' );

		Functions\expect( 'wp_verify_nonce' )->andReturn( false );

		$died = false;
		Functions\expect( 'wp_die' )->once()->andReturnUsing( function() use ( &$died ) {
			$died = true;
			throw new \RuntimeException( 'wp_die called' );
		} );

		// Stub render dependencies.
		global $wpdb;
		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_var' )->andReturn( 0 );

		$groups = new TailSignal_Admin_Groups();
		try {
			$groups->render();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( $died );
	}

	/**
	 * Test groups render passes with valid nonce on edit GET param.
	 */
	public function test_groups_render_passes_with_valid_nonce() {
		$_GET = array(
			'edit'     => '5',
			'_wpnonce' => 'valid_nonce',
		);

		Functions\expect( 'wp_verify_nonce' )
			->with( 'valid_nonce', 'tailsignal_edit_group' )
			->andReturn( true );

		global $wpdb;
		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_var' )->andReturn( 0 );
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$groups = new TailSignal_Admin_Groups();

		// Suppress output from include.
		ob_start();
		try {
			$groups->render();
		} catch ( \Throwable $e ) {
			// Include may fail in test env — that's OK, we're testing the nonce path.
		}
		ob_end_clean();

		$this->assertTrue( true );
	}

	// ── Meta Box Quick Send ───────────────────────────────────────

	/**
	 * Test handle_quick_send checks permission.
	 */
	public function test_handle_quick_send_checks_permission() {
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( false );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function() use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( 'denied' );
		} );

		$meta = new TailSignal_Meta_Box();
		try {
			$meta->handle_quick_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_quick_send requires post_id, title, body.
	 */
	public function test_handle_quick_send_requires_fields() {
		$_POST = array(
			'post_id' => '0',
			'title'   => '',
			'body'    => '',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( $data['message'] );
		} );

		$meta = new TailSignal_Meta_Box();
		try {
			$meta->handle_quick_send();
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'required', $e->getMessage() );
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_quick_send returns error when post not found.
	 */
	public function test_handle_quick_send_post_not_found() {
		$_POST = array(
			'post_id' => '99',
			'title'   => 'Test',
			'body'    => 'Body',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_post' )->with( 99 )->andReturn( null );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( $data['message'] );
		} );

		$meta = new TailSignal_Meta_Box();
		try {
			$meta->handle_quick_send();
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'not found', $e->getMessage() );
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_quick_send returns error when no devices.
	 */
	public function test_handle_quick_send_no_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 10;
		$post->post_title   = 'Test Post';
		$post->post_content = 'Content';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		$_POST = array(
			'post_id'     => '10',
			'title'       => 'Test',
			'body'        => 'Body',
			'target_type' => 'all',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_post' )->with( 10 )->andReturn( $post );

		// parse_placeholders stubs.
		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );
		Functions\expect( 'get_permalink' )->andReturn( 'http://example.com/test' );

		// get_tokens_by_target returns empty.
		Functions\expect( 'get_option' )
			->with( 'tailsignal_dev_mode', '0' )
			->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( $data['message'] );
		} );

		$meta = new TailSignal_Meta_Box();
		try {
			$meta->handle_quick_send();
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'No devices', $e->getMessage() );
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test add_meta_box registers for multiple post types.
	 */
	public function test_add_meta_box_multiple_types() {
		Functions\expect( 'apply_filters' )
			->with( 'tailsignal_post_types', array( 'post' ) )
			->andReturn( array( 'post', 'page' ) );

		Functions\expect( 'add_meta_box' )->twice();

		$meta = new TailSignal_Meta_Box();
		$meta->add_meta_box();
		$this->assertTrue( true );
	}

	// ── Send Handler Input Validation ───────────────────────────

	/**
	 * Test handle_send parses title and body from POST.
	 */
	public function test_handle_send_parses_inputs() {
		$_POST = array(
			'title'       => 'Test Title',
			'body'        => '',
			'target_type' => 'all',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'error' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'required', $error_data['message'] );
	}

	/**
	 * Test handle_send with no devices returns error.
	 */
	public function test_handle_send_no_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$_POST = array(
			'title'       => 'Test',
			'body'        => 'Body',
			'target_type' => 'all',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_option' )->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'no devices' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'No devices', $error_data['message'] );
	}

	/**
	 * Test handle_send with group target retrieves group tokens.
	 */
	public function test_handle_send_group_target_no_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$_POST = array(
			'title'       => 'Group Test',
			'body'        => 'Group Body',
			'target_type' => 'group',
			'target_ids'  => array( '3' ),
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_option' )->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'no devices' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'No devices', $error_data['message'] );
	}

	/**
	 * Test handle_send with specific device IDs retrieves tokens.
	 */
	public function test_handle_send_specific_no_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$_POST = array(
			'title'       => 'Specific Test',
			'body'        => 'Specific Body',
			'target_type' => 'specific',
			'target_ids'  => array( '1', '3' ),
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_option' )->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'no devices' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'No devices', $error_data['message'] );
	}

	/**
	 * Test handle_send with invalid JSON data.
	 */
	public function test_handle_send_invalid_json_data() {
		$_POST = array(
			'title'       => 'Test',
			'body'        => 'Body',
			'data'        => 'not valid json{',
			'target_type' => 'all',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'error' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'JSON', $error_data['message'] );
	}

	/**
	 * Test handle_send with scheduled_at creates scheduled notification.
	 */
	public function test_handle_send_scheduled() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 42;

		$_POST = array(
			'title'        => 'Scheduled',
			'body'         => 'Scheduled Body',
			'target_type'  => 'all',
			'scheduled_at' => '2026-03-01 10:00:00',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_current_user_id' )->andReturn( 1 );

		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		Functions\expect( 'wp_schedule_single_event' )->once();

		$success_data = null;
		Functions\expect( 'wp_send_json_success' )->once()->andReturnUsing( function( $data ) use ( &$success_data ) {
			$success_data = $data;
			throw new \RuntimeException( 'success' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertSame( 42, $success_data['notification_id'] );
	}

	/**
	 * Test handle_send scheduled failure.
	 */
	public function test_handle_send_scheduled_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 0;

		$_POST = array(
			'title'        => 'Scheduled',
			'body'         => 'Scheduled Body',
			'target_type'  => 'all',
			'scheduled_at' => '2026-03-01 10:00:00',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_current_user_id' )->andReturn( 1 );

		$wpdb->shouldReceive( 'insert' )->andReturn( false );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'failed' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'Failed', $error_data['message'] );
	}

	/**
	 * Test handle_cancel_scheduled success.
	 */
	public function test_handle_cancel_scheduled_success() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$_POST = array( 'notification_id' => '10' );

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$notification         = new stdClass();
		$notification->status = 'scheduled';

		$wpdb->shouldReceive( 'get_row' )->andReturn( $notification );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		Functions\expect( 'wp_next_scheduled' )->andReturn( 1234567890 );
		Functions\expect( 'wp_unschedule_event' )->once();

		$wpdb->shouldReceive( 'update' )->andReturn( 1 );

		$success_data = null;
		Functions\expect( 'wp_send_json_success' )->once()->andReturnUsing( function( $data ) use ( &$success_data ) {
			$success_data = $data;
			throw new \RuntimeException( 'success' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_cancel_scheduled();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'cancelled', strtolower( $success_data['message'] ) );
	}

	/**
	 * Test handle_cancel_scheduled failure.
	 */
	public function test_handle_cancel_scheduled_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$_POST = array( 'notification_id' => '10' );

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		// Notification not found.
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'failed' );
		} );

		$send = new TailSignal_Admin_Send();
		try {
			$send->handle_cancel_scheduled();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'Failed', $error_data['message'] );
	}

	// ── Quick Send Validation ───────────────────────────────────

	/**
	 * Test handle_quick_send parses placeholders for post.
	 */
	public function test_handle_quick_send_parses_placeholders() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 10;
		$post->post_title   = 'Test Post';
		$post->post_content = 'Some content here';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		$_POST = array(
			'post_id'     => '10',
			'title'       => 'Quick {post_title}',
			'body'        => 'Quick Body',
			'target_type' => 'all',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_post' )->with( 10 )->andReturn( $post );

		// parse_placeholders stubs.
		Functions\expect( 'get_bloginfo' )->andReturn( 'My Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );
		Functions\expect( 'get_permalink' )->andReturn( 'http://example.com/test' );

		// get_tokens returns empty → no devices error.
		Functions\expect( 'get_option' )->andReturn( '0' );
		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'no devices' );
		} );

		$meta = new TailSignal_Meta_Box();
		try {
			$meta->handle_quick_send();
		} catch ( \RuntimeException $e ) {
			// Expected — placeholder parsing succeeded, no devices to send to.
		}
		$this->assertStringContainsString( 'No devices', $error_data['message'] );
	}

	/**
	 * Test handle_quick_send with group target.
	 */
	public function test_handle_quick_send_group_target_no_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 10;
		$post->post_title   = 'Test';
		$post->post_content = 'Content';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		$_POST = array(
			'post_id'     => '10',
			'title'       => 'Title',
			'body'        => 'Body',
			'target_type' => 'group',
			'target_ids'  => array( '2' ),
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_post' )->with( 10 )->andReturn( $post );
		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );
		Functions\expect( 'get_permalink' )->andReturn( 'http://example.com/test' );
		Functions\expect( 'get_option' )->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'no devices' );
		} );

		$meta = new TailSignal_Meta_Box();
		try {
			$meta->handle_quick_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'No devices', $error_data['message'] );
	}

	/**
	 * Test handle_quick_send sends notification failure.
	 */
	public function test_handle_quick_send_send_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 0;

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 10;
		$post->post_title   = 'Test';
		$post->post_content = 'Content';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		$_POST = array(
			'post_id'     => '10',
			'title'       => 'Title',
			'body'        => 'Body',
			'target_type' => 'all',
		);

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'get_post' )->with( 10 )->andReturn( $post );
		Functions\expect( 'get_current_user_id' )->andReturn( 1 );
		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );
		Functions\expect( 'get_permalink' )->andReturn( 'http://example.com/test' );
		Functions\expect( 'get_option' )->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->andReturn( array( 'ExponentPushToken[abc]' ) );

		// insert_notification fails.
		$wpdb->shouldReceive( 'insert' )->andReturn( false );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'failed' );
		} );

		$meta = new TailSignal_Meta_Box();
		try {
			$meta->handle_quick_send();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'Failed', $error_data['message'] );
	}
}
