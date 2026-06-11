<?php
/**
 * Tests for TailSignal_Admin_History AJAX handler.
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-admin-history.php';

class Test_TailSignal_Admin_History extends TailSignal_TestCase {

	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	// ── handle_delete_all ───────────────────────────────────────

	/**
	 * Test handle_delete_all checks nonce.
	 */
	public function test_delete_all_checks_nonce() {
		Functions\expect( 'check_ajax_referer' )
			->with( 'tailsignal_nonce', 'nonce' )
			->once();

		Functions\expect( 'current_user_can' )->andReturn( false );

		$exited = false;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function() use ( &$exited ) {
			$exited = true;
			throw new \RuntimeException( 'denied' );
		} );

		$history = new TailSignal_Admin_History();
		try {
			$history->handle_delete_all();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertTrue( $exited );
	}

	/**
	 * Test handle_delete_all checks permission.
	 */
	public function test_delete_all_checks_permission() {
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )
			->with( 'tailsignal_manage' )
			->andReturn( false );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'denied' );
		} );

		$history = new TailSignal_Admin_History();
		try {
			$history->handle_delete_all();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'Permission', $error_data['message'] );
	}

	/**
	 * Test handle_delete_all success.
	 */
	public function test_delete_all_success() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		// delete_all_notifications truncates the table.
		$wpdb->shouldReceive( 'query' )->andReturn( true );

		$success_data = null;
		Functions\expect( 'wp_send_json_success' )->once()->andReturnUsing( function( $data ) use ( &$success_data ) {
			$success_data = $data;
			throw new \RuntimeException( 'success' );
		} );

		$history = new TailSignal_Admin_History();
		try {
			$history->handle_delete_all();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'deleted', $success_data['message'] );
	}

	/**
	 * Test handle_delete_all failure.
	 */
	public function test_delete_all_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		$wpdb->shouldReceive( 'query' )->andReturn( false );

		$error_data = null;
		Functions\expect( 'wp_send_json_error' )->once()->andReturnUsing( function( $data ) use ( &$error_data ) {
			$error_data = $data;
			throw new \RuntimeException( 'failed' );
		} );

		$history = new TailSignal_Admin_History();
		try {
			$history->handle_delete_all();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertStringContainsString( 'Failed', $error_data['message'] );
	}

	/**
	 * Test handle_delete_all with permission granted calls DB.
	 */
	public function test_delete_all_calls_db_method() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		// delete_all_notifications: START TRANSACTION, 2x DELETE, COMMIT.
		$queries = array();
		$wpdb->shouldReceive( 'query' )
			->andReturnUsing( function( $sql ) use ( &$queries ) {
				$queries[] = $sql;
				return true;
			} );

		Functions\expect( 'wp_send_json_success' )->once()->andReturnUsing( function() {
			throw new \RuntimeException( 'success' );
		} );

		$history = new TailSignal_Admin_History();
		try {
			$history->handle_delete_all();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		$this->assertCount( 4, $queries );
		$this->assertSame( 'START TRANSACTION', $queries[0] );
		$this->assertStringContainsString( 'DELETE FROM', $queries[1] );
		$this->assertStringContainsString( 'DELETE FROM', $queries[2] );
		$this->assertSame( 'COMMIT', $queries[3] );
	}

	/**
	 * Test render method exists.
	 */
	public function test_render_method_exists() {
		$history = new TailSignal_Admin_History();
		$this->assertTrue( method_exists( $history, 'render' ) );
	}
}
