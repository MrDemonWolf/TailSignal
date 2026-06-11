<?php
/**
 * Tests for TailSignal_DB.
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';

class Test_TailSignal_DB extends TailSignal_TestCase {

	/**
	 * Test insert_device returns an ID.
	 */
	public function test_insert_device_returns_id() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 42;

		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$result = TailSignal_DB::insert_device( array(
			'expo_token'  => 'ExponentPushToken[test123]',
			'device_type' => 'ios',
		) );

		$this->assertSame( 42, $result );
	}

	/**
	 * Test insert_device updates existing device.
	 */
	public function test_insert_device_updates_existing() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( '10' );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$result = TailSignal_DB::insert_device( array(
			'expo_token'  => 'ExponentPushToken[existing]',
			'device_type' => 'android',
		) );

		$this->assertSame( 10, $result );
	}

	/**
	 * Test insert_device returns false on failure.
	 */
	public function test_insert_device_returns_false_on_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 0;

		$wpdb->shouldReceive( 'get_var' )->twice()->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$result = TailSignal_DB::insert_device( array(
			'expo_token'  => 'ExponentPushToken[fail]',
			'device_type' => 'ios',
		) );

		$this->assertFalse( $result );
	}

	/**
	 * Test remove_device soft-deletes.
	 */
	public function test_remove_device() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$result = TailSignal_DB::remove_device( 'ExponentPushToken[test]' );
		$this->assertTrue( $result );
	}

	/**
	 * Test delete_device hard-deletes with related data.
	 */
	public function test_delete_device() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Should delete from device_meta, device_groups, and devices.
		$wpdb->shouldReceive( 'delete' )->times( 3 )->andReturn( 1 );

		$result = TailSignal_DB::delete_device( 1 );
		$this->assertTrue( $result );
	}

	/**
	 * Test get_device returns device object.
	 */
	public function test_get_device() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$device = new stdClass();
		$device->id = 1;
		$device->expo_token = 'ExponentPushToken[abc]';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $device );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$result = TailSignal_DB::get_device( 1 );
		$this->assertSame( 'ExponentPushToken[abc]', $result->expo_token );
	}

	/**
	 * Test get_device returns null when not found.
	 */
	public function test_get_device_not_found() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$result = TailSignal_DB::get_device( 999 );
		$this->assertNull( $result );
	}

	/**
	 * Test get_device_by_token returns device object.
	 */
	public function test_get_device_by_token() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$device = new stdClass();
		$device->id = 5;
		$device->expo_token = 'ExponentPushToken[xyz]';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $device );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$result = TailSignal_DB::get_device_by_token( 'ExponentPushToken[xyz]' );
		$this->assertSame( 5, $result->id );
	}

	/**
	 * Test get_device_by_token returns null when not found.
	 */
	public function test_get_device_by_token_not_found() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$result = TailSignal_DB::get_device_by_token( 'ExponentPushToken[nope]' );
		$this->assertNull( $result );
	}

	/**
	 * Test update_device success.
	 */
	public function test_update_device() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$result = TailSignal_DB::update_device( 10, array( 'user_label' => 'My Phone' ) );
		$this->assertTrue( $result );
	}

	/**
	 * Test update_device returns false on failure.
	 */
	public function test_update_device_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'update' )->once()->andReturn( false );

		$result = TailSignal_DB::update_device( 999, array( 'user_label' => 'Nope' ) );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_all_active_tokens in normal mode.
	 */
	public function test_get_all_active_tokens() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'get_option' )
			->with( 'tailsignal_dev_mode', '0' )
			->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->once()->andReturn( array(
			'ExponentPushToken[aaa]',
			'ExponentPushToken[bbb]',
		) );

		$tokens = TailSignal_DB::get_all_active_tokens();
		$this->assertCount( 2, $tokens );
	}

	/**
	 * Test get_all_active_tokens in dev mode.
	 */
	public function test_get_all_active_tokens_dev_mode() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'get_option' )
			->with( 'tailsignal_dev_mode', '0' )
			->andReturn( '1' );

		$wpdb->shouldReceive( 'get_col' )->once()->andReturn( array(
			'ExponentPushToken[dev1]',
		) );

		$tokens = TailSignal_DB::get_all_active_tokens();
		$this->assertCount( 1, $tokens );
	}

	/**
	 * Test get_tokens_by_target with dev type.
	 */
	public function test_get_tokens_by_target_dev() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_col' )->once()->andReturn( array( 'ExponentPushToken[dev1]' ) );

		$tokens = TailSignal_DB::get_tokens_by_target( 'dev' );
		$this->assertCount( 1, $tokens );
	}

	/**
	 * Test get_tokens_by_target with empty group.
	 */
	public function test_get_tokens_by_target_empty_group() {
		$tokens = TailSignal_DB::get_tokens_by_target( 'group', array() );
		$this->assertEmpty( $tokens );
	}

	/**
	 * Test get_tokens_by_target with empty specific.
	 */
	public function test_get_tokens_by_target_empty_specific() {
		$tokens = TailSignal_DB::get_tokens_by_target( 'specific', array() );
		$this->assertEmpty( $tokens );
	}

	/**
	 * Test get_devices returns items and total.
	 */
	public function test_get_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$items = array(
			(object) array( 'id' => 1, 'expo_token' => 'ExponentPushToken[a]' ),
			(object) array( 'id' => 2, 'expo_token' => 'ExponentPushToken[b]' ),
		);

		// Count query (no filters = no prepare for count).
		$wpdb->shouldReceive( 'get_var' )->andReturn( 2 );
		// Items query (always uses prepare for LIMIT/OFFSET).
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_results' )->andReturn( $items );

		$result = TailSignal_DB::get_devices();
		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 2, $result['items'] );
	}

	/**
	 * Test get_devices with filters.
	 */
	public function test_get_devices_with_filters() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$items = array(
			(object) array( 'id' => 1, 'device_type' => 'ios' ),
		);

		$wpdb->shouldReceive( 'get_var' )->andReturn( 1 );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_results' )->andReturn( $items );
		$wpdb->shouldReceive( 'esc_like' )->andReturn( 'search' );

		$result = TailSignal_DB::get_devices( array(
			'device_type' => 'ios',
			'is_active'   => 1,
			'search'      => 'search',
		) );

		$this->assertSame( 1, $result['total'] );
		$this->assertCount( 1, $result['items'] );
	}

	/**
	 * Test get_device_count with active_only.
	 */
	public function test_get_device_count_active() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( 25 );

		$result = TailSignal_DB::get_device_count( true );
		$this->assertSame( 25, $result );
	}

	/**
	 * Test get_device_count all devices.
	 */
	public function test_get_device_count_all() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( 50 );

		$result = TailSignal_DB::get_device_count( false );
		$this->assertSame( 50, $result );
	}

	/**
	 * Test get_device_count_by_platform.
	 */
	public function test_get_device_count_by_platform() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( array(
			(object) array( 'device_type' => 'ios', 'count' => 10 ),
			(object) array( 'device_type' => 'android', 'count' => 15 ),
		) );

		$result = TailSignal_DB::get_device_count_by_platform();
		$this->assertSame( 10, $result['ios'] );
		$this->assertSame( 15, $result['android'] );
	}

	/**
	 * Test get_device_count_by_platform returns zeros for missing platforms.
	 */
	public function test_get_device_count_by_platform_empty() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		$result = TailSignal_DB::get_device_count_by_platform();
		$this->assertSame( 0, $result['ios'] );
		$this->assertSame( 0, $result['android'] );
	}

	/**
	 * Test get_dev_device_count.
	 */
	public function test_get_dev_device_count() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( 3 );

		$result = TailSignal_DB::get_dev_device_count();
		$this->assertSame( 3, $result );
	}

	/**
	 * Test bulk_delete_devices.
	 */
	public function test_bulk_delete_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// 3 batch DELETE queries (device_meta, device_groups, devices).
		$wpdb->shouldReceive( 'prepare' )->times( 3 )->andReturn( 'prepared_query' );
		$wpdb->shouldReceive( 'query' )->times( 3 )->andReturn( 2 );

		$count = TailSignal_DB::bulk_delete_devices( array( 1, 2 ) );
		$this->assertSame( 2, $count );
	}

	/**
	 * Test create_group.
	 */
	public function test_create_group() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 5;

		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$result = TailSignal_DB::create_group( array(
			'name'        => 'Test Group',
			'description' => 'A test group',
		) );

		$this->assertSame( 5, $result );
	}

	/**
	 * Test delete_group removes device assignments.
	 */
	public function test_delete_group() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Should delete from device_groups and groups.
		$wpdb->shouldReceive( 'delete' )->twice()->andReturn( 1 );

		$result = TailSignal_DB::delete_group( 1 );
		$this->assertTrue( $result );
	}

	/**
	 * Test insert_notification.
	 */
	public function test_insert_notification() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 99;

		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$result = TailSignal_DB::insert_notification( array(
			'title' => 'Test',
			'body'  => 'Test body',
			'type'  => 'manual',
		) );

		$this->assertSame( 99, $result );
	}

	/**
	 * Test insert_notification returns false on failure.
	 */
	public function test_insert_notification_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 0;

		$wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$result = TailSignal_DB::insert_notification( array(
			'title' => 'Test',
			'body'  => 'Body',
		) );

		$this->assertFalse( $result );
	}

	/**
	 * Test update_notification.
	 */
	public function test_update_notification() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$result = TailSignal_DB::update_notification( 10, array( 'status' => 'sent' ) );
		$this->assertTrue( $result );
	}

	/**
	 * Test get_notification returns notification object.
	 */
	public function test_get_notification() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$notification = new stdClass();
		$notification->id = 10;
		$notification->title = 'Test';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $notification );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$result = TailSignal_DB::get_notification( 10 );
		$this->assertSame( 'Test', $result->title );
	}

	/**
	 * Test get_notification returns null when not found.
	 */
	public function test_get_notification_not_found() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$result = TailSignal_DB::get_notification( 999 );
		$this->assertNull( $result );
	}

	/**
	 * Test get_notifications with pagination.
	 */
	public function test_get_notifications() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$items = array(
			(object) array( 'id' => 1, 'title' => 'Notif 1' ),
			(object) array( 'id' => 2, 'title' => 'Notif 2' ),
		);

		$wpdb->shouldReceive( 'get_var' )->andReturn( 50 );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_results' )->andReturn( $items );

		$result = TailSignal_DB::get_notifications();
		$this->assertSame( 50, $result['total'] );
		$this->assertCount( 2, $result['items'] );
	}

	/**
	 * Test get_notifications with type filter.
	 */
	public function test_get_notifications_with_type_filter() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_var' )->andReturn( 5 );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );

		$result = TailSignal_DB::get_notifications( array( 'type' => 'manual', 'status' => 'sent' ) );
		$this->assertSame( 5, $result['total'] );
	}

	/**
	 * Test get_recent_notifications.
	 */
	public function test_get_recent_notifications() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$items = array(
			(object) array( 'id' => 1, 'title' => 'Recent' ),
		);

		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( $items );

		$result = TailSignal_DB::get_recent_notifications( 5 );
		$this->assertCount( 1, $result );
	}

	/**
	 * Test get_scheduled_notifications.
	 */
	public function test_get_scheduled_notifications() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$items = array(
			(object) array( 'id' => 1, 'status' => 'scheduled' ),
		);

		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( $items );

		$result = TailSignal_DB::get_scheduled_notifications();
		$this->assertCount( 1, $result );
		$this->assertSame( 'scheduled', $result[0]->status );
	}

	/**
	 * Test get_pending_receipt_notifications.
	 */
	public function test_get_pending_receipt_notifications() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$items = array(
			(object) array( 'id' => 1, 'status' => 'sent', 'ticket_ids' => '["abc"]' ),
		);

		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( $items );

		$result = TailSignal_DB::get_pending_receipt_notifications();
		$this->assertCount( 1, $result );
	}

	/**
	 * Test get_notification_counts_by_status.
	 */
	public function test_get_notification_counts_by_status() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( array(
			(object) array( 'status' => 'sent', 'count' => 50 ),
			(object) array( 'status' => 'failed', 'count' => 3 ),
		) );

		$result = TailSignal_DB::get_notification_counts_by_status();
		$this->assertSame( 50, $result['sent'] );
		$this->assertSame( 3, $result['failed'] );
	}

	/**
	 * Test get_monthly_send_count.
	 */
	public function test_get_monthly_send_count() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( 42 );

		$result = TailSignal_DB::get_monthly_send_count();
		$this->assertSame( 42, $result );
	}

	/**
	 * Test get_success_rate with data.
	 */
	public function test_get_success_rate() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$row = new stdClass();
		$row->total_success = 95;
		$row->total_devices = 100;

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $row );

		$result = TailSignal_DB::get_success_rate();
		$this->assertSame( 95.0, $result );
	}

	/**
	 * Test get_success_rate with no data.
	 */
	public function test_get_success_rate_no_data() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$result = TailSignal_DB::get_success_rate();
		$this->assertSame( 0.0, $result );
	}

	/**
	 * Test get_success_rate with zero devices.
	 */
	public function test_get_success_rate_zero_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$row = new stdClass();
		$row->total_success = 0;
		$row->total_devices = 0;

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $row );

		$result = TailSignal_DB::get_success_rate();
		$this->assertSame( 0.0, $result );
	}

	/**
	 * Test insert_notification_history.
	 */
	public function test_insert_notification_history() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 88;

		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$result = TailSignal_DB::insert_notification_history( 42, 10 );
		$this->assertSame( 88, $result );
	}

	/**
	 * Test get_post_notification_history.
	 */
	public function test_get_post_notification_history() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$history = array(
			(object) array( 'id' => 1, 'notification_id' => 10, 'history_created_at' => '2025-02-12' ),
			(object) array( 'id' => 2, 'notification_id' => 11, 'history_created_at' => '2025-02-11' ),
		);

		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( $history );

		$result = TailSignal_DB::get_post_notification_history( 42 );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test get_devices_for_export.
	 */
	public function test_get_devices_for_export() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$devices = array(
			(object) array( 'expo_token' => 'ExponentPushToken[a]', 'device_type' => 'ios' ),
			(object) array( 'expo_token' => 'ExponentPushToken[b]', 'device_type' => 'android' ),
		);

		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( $devices );

		$result = TailSignal_DB::get_devices_for_export();
		$this->assertCount( 2, $result );
	}

	/**
	 * Test import_devices with new device.
	 */
	public function test_import_devices_new() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 100;

		// Transaction queries.
		$wpdb->shouldReceive( 'query' )->andReturn( true );

		// get_device_by_token returns null (new).
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		// insert_device: get_var (existing check) returns null, then insert.
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$rows = array(
			array(
				'expo_token'  => 'ExponentPushToken[new]',
				'device_type' => 'ios',
			),
		);

		$result = TailSignal_DB::import_devices( $rows );
		$this->assertSame( 1, $result['new'] );
		$this->assertSame( 0, $result['updated'] );
		$this->assertSame( 0, $result['skipped'] );
	}

	/**
	 * Test import_devices skips empty token.
	 */
	public function test_import_devices_skips_empty_token() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Transaction queries.
		$wpdb->shouldReceive( 'query' )->andReturn( true );

		$rows = array(
			array( 'expo_token' => '', 'device_type' => 'ios' ),
		);

		$result = TailSignal_DB::import_devices( $rows );
		$this->assertSame( 0, $result['new'] );
		$this->assertSame( 0, $result['updated'] );
		$this->assertSame( 1, $result['skipped'] );
	}

	/**
	 * Test import_devices with existing device.
	 */
	public function test_import_devices_existing() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Transaction queries.
		$wpdb->shouldReceive( 'query' )->andReturn( true );

		$existing = new stdClass();
		$existing->id = 50;

		// get_device_by_token returns existing.
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $existing );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		// insert_device: get_var returns existing ID, then update.
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( '50' );
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$rows = array(
			array(
				'expo_token'  => 'ExponentPushToken[existing]',
				'device_type' => 'android',
			),
		);

		$result = TailSignal_DB::import_devices( $rows );
		$this->assertSame( 0, $result['new'] );
		$this->assertSame( 1, $result['updated'] );
		$this->assertSame( 0, $result['skipped'] );
	}

	/**
	 * Test deactivate_tokens.
	 */
	public function test_deactivate_tokens() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared_query' );
		$wpdb->shouldReceive( 'query' )->once()->with( 'prepared_query' )->andReturn( 2 );

		$count = TailSignal_DB::deactivate_tokens( array(
			'ExponentPushToken[stale1]',
			'ExponentPushToken[stale2]',
		) );

		$this->assertSame( 2, $count );
	}

	/**
	 * Test get_device_summary_stats uses transient cache.
	 */
	public function test_get_device_summary_stats_cached() {
		global $wpdb;

		// Need a wpdb mock even though cache hits — the method signature uses global.
		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$cached = array( 'total' => 10, 'ios' => 5, 'android' => 5, 'dev' => 2 );

		Functions\when( 'get_transient' )->alias( function( $key ) use ( $cached ) {
			if ( 'tailsignal_device_summary_stats' === $key ) {
				return $cached;
			}
			return false;
		} );

		$result = TailSignal_DB::get_device_summary_stats();
		$this->assertSame( 10, $result['total'] );
		$this->assertSame( 5, $result['ios'] );
	}

	/**
	 * Test get_device_summary_stats queries DB on cache miss.
	 */
	public function test_get_device_summary_stats_uncached() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$row = new stdClass();
		$row->total   = 20;
		$row->ios     = 12;
		$row->android = 8;
		$row->dev     = 3;

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $row );

		// Default stubs handle get_transient (returns false) and set_transient (returns true).
		$result = TailSignal_DB::get_device_summary_stats();
		$this->assertSame( 20, $result['total'] );
	}

	/**
	 * Test invalidate_device_cache deletes transients.
	 */
	public function test_invalidate_device_cache() {
		// Default stubs handle delete_transient. Just verify no errors.
		TailSignal_DB::invalidate_device_cache();
		$this->assertTrue( true );
	}

	/**
	 * Test invalidate_notification_cache deletes transients.
	 */
	public function test_invalidate_notification_cache() {
		// Default stubs handle delete_transient. Just verify no errors.
		TailSignal_DB::invalidate_notification_cache();
		$this->assertTrue( true );
	}

	/**
	 * Test import_devices uses transaction.
	 */
	public function test_import_devices_uses_transaction() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 100;

		// Transaction + cache invalidation queries.
		$wpdb->shouldReceive( 'query' )->andReturn( true );

		// get_device_by_token returns null (new).
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		$rows = array(
			array(
				'expo_token'  => 'ExponentPushToken[txn_test]',
				'device_type' => 'ios',
			),
		);

		$result = TailSignal_DB::import_devices( $rows );
		$this->assertSame( 1, $result['new'] );
	}

	/**
	 * Test drop_tables.
	 */
	public function test_drop_tables() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// 6 tables to drop.
		$wpdb->shouldReceive( 'query' )->times( 6 )->andReturn( true );

		TailSignal_DB::drop_tables();
		$this->assertTrue( true );
	}
}
