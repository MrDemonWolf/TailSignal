<?php
/**
 * Tests for TailSignal_REST_Controller.
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-expo.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-notification.php';
require_once dirname( __DIR__ ) . '/src/rest-api/class-tailsignal-rest-controller.php';

class Test_TailSignal_REST_Controller extends TailSignal_TestCase {

	/**
	 * @var TailSignal_REST_Controller
	 */
	private $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->controller = new TailSignal_REST_Controller();
	}

	/**
	 * Test register_routes registers all endpoints.
	 */
	public function test_register_routes() {
		Functions\expect( 'register_rest_route' )->times( 6 );

		$this->controller->register_routes();
		$this->assertTrue( true );
	}

	/**
	 * Test check_admin_permission denies non-admins.
	 */
	public function test_check_admin_permission_denied() {
		Functions\expect( 'current_user_can' )
			->with( 'tailsignal_manage' )
			->andReturn( false );

		$request = Mockery::mock( 'WP_REST_Request' );
		$result  = $this->controller->check_admin_permission( $request );

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test check_admin_permission allows admins.
	 */
	public function test_check_admin_permission_allowed() {
		Functions\expect( 'current_user_can' )
			->with( 'tailsignal_manage' )
			->andReturn( true );

		$request = Mockery::mock( 'WP_REST_Request' );
		$result  = $this->controller->check_admin_permission( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test register_device creates a new device.
	 */
	public function test_register_device() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 1;

		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		Functions\expect( 'is_user_logged_in' )->andReturn( false );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'expo_token' )->andReturn( 'ExponentPushToken[test123]' );
		$request->shouldReceive( 'get_param' )->with( 'device_type' )->andReturn( 'ios' );
		$request->shouldReceive( 'get_param' )->with( 'device_model' )->andReturn( 'iPhone 16' );
		$request->shouldReceive( 'get_param' )->with( 'os_version' )->andReturn( 'iOS 18.2' );
		$request->shouldReceive( 'get_param' )->with( 'app_version' )->andReturn( '1.0.0' );
		$request->shouldReceive( 'get_param' )->with( 'locale' )->andReturn( 'en-US' );
		$request->shouldReceive( 'get_param' )->with( 'timezone' )->andReturn( 'America/Chicago' );
		$request->shouldReceive( 'get_param' )->with( 'user_label' )->andReturn( 'My iPhone' );

		$response = $this->controller->register_device( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( 1, $data['device_id'] );
	}

	/**
	 * Test register_device with authenticated user links user_id.
	 */
	public function test_register_device_authenticated() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 2;

		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		Functions\expect( 'is_user_logged_in' )->andReturn( true );
		Functions\expect( 'get_current_user_id' )->andReturn( 42 );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'expo_token' )->andReturn( 'ExponentPushToken[auth123]' );
		$request->shouldReceive( 'get_param' )->with( 'device_type' )->andReturn( 'android' );
		$request->shouldReceive( 'get_param' )->with( 'device_model' )->andReturn( 'Pixel 9' );
		$request->shouldReceive( 'get_param' )->with( 'os_version' )->andReturn( 'Android 15' );
		$request->shouldReceive( 'get_param' )->with( 'app_version' )->andReturn( '1.0.0' );
		$request->shouldReceive( 'get_param' )->with( 'locale' )->andReturn( 'en-US' );
		$request->shouldReceive( 'get_param' )->with( 'timezone' )->andReturn( 'America/New_York' );
		$request->shouldReceive( 'get_param' )->with( 'user_label' )->andReturn( null );

		$response = $this->controller->register_device( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 2, $response->get_data()['device_id'] );
	}

	/**
	 * Test register_device returns error on DB failure.
	 */
	public function test_register_device_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 0;

		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'insert' )->andReturn( false );

		Functions\expect( 'is_user_logged_in' )->andReturn( false );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->andReturn( '' );

		$response = $this->controller->register_device( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
	}

	/**
	 * Test unregister_device deactivates a device.
	 */
	public function test_unregister_device() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$device            = new stdClass();
		$device->is_active = 1;

		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $device );
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'expo_token' )->andReturn( 'ExponentPushToken[test123]' );

		$response = $this->controller->unregister_device( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test unregister_device is idempotent for already-inactive devices.
	 */
	public function test_unregister_device_already_inactive() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$device            = new stdClass();
		$device->is_active = 0;

		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $device );
		// update should NOT be called — already inactive.

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'expo_token' )->andReturn( 'ExponentPushToken[test123]' );

		$response = $this->controller->unregister_device( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test unregister_device returns 404 for unknown token.
	 */
	public function test_unregister_device_not_found() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'expo_token' )->andReturn( 'ExponentPushToken[unknown]' );

		$response = $this->controller->unregister_device( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
	}

	/**
	 * Test get_stats returns dashboard data.
	 */
	public function test_get_stats() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_var' )->andReturn( 10 );
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_dev_mode', '0' )
			->andReturn( '0' );

		$request  = Mockery::mock( 'WP_REST_Request' );
		$response = $this->controller->get_stats( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'total_devices', $data );
		$this->assertArrayHasKey( 'ios_devices', $data );
		$this->assertArrayHasKey( 'android_devices', $data );
		$this->assertArrayHasKey( 'dev_devices', $data );
		$this->assertArrayHasKey( 'monthly_sent', $data );
		$this->assertArrayHasKey( 'success_rate', $data );
		$this->assertArrayHasKey( 'dev_mode', $data );
		$this->assertFalse( $data['dev_mode'] );
	}

	/**
	 * Test send_notification immediate sends.
	 */
	public function test_send_notification_immediate() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 3;

		// get_tokens_by_target (all).
		Functions\expect( 'get_option' )
			->with( 'tailsignal_dev_mode', '0' )
			->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->andReturn( array( 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]' ) );

		Functions\expect( 'get_current_user_id' )->andReturn( 1 );

		// insert_notification.
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		// Expo send.
		Functions\expect( 'get_option' )
			->with( 'tailsignal_expo_access_token', '' )
			->andReturn( '' );

		TailSignal_Expo::reset_instance();

		// update_notification.
		$wpdb->shouldReceive( 'update' )->andReturn( 1 );

		// wp_schedule_single_event.
		Functions\expect( 'wp_schedule_single_event' )->andReturn( true );

		// get_notification for response.
		$notif = new stdClass();
		$notif->total_devices = 1;
		$notif->total_success = 1;
		$notif->total_failed  = 0;

		$wpdb->shouldReceive( 'get_row' )->andReturn( $notif );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'query' )->andReturn( 0 );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'title' )->andReturn( 'Test' );
		$request->shouldReceive( 'get_param' )->with( 'body' )->andReturn( 'Body' );
		$request->shouldReceive( 'get_param' )->with( 'data' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'image_url' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'target_type' )->andReturn( 'all' );
		$request->shouldReceive( 'get_param' )->with( 'target_ids' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'post_id' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'scheduled_at' )->andReturn( null );

		$response = $this->controller->send_notification( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( 3, $data['notification_id'] );
	}

	/**
	 * Test send_notification returns error when no devices.
	 */
	public function test_send_notification_no_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'get_option' )
			->with( 'tailsignal_dev_mode', '0' )
			->andReturn( '0' );

		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'title' )->andReturn( 'Test' );
		$request->shouldReceive( 'get_param' )->with( 'body' )->andReturn( 'Body' );
		$request->shouldReceive( 'get_param' )->with( 'data' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'image_url' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'target_type' )->andReturn( 'all' );
		$request->shouldReceive( 'get_param' )->with( 'target_ids' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'post_id' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'scheduled_at' )->andReturn( null );

		$response = $this->controller->send_notification( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'tailsignal_no_devices', $response->get_error_code() );
	}

	/**
	 * Test send_notification handles scheduled.
	 */
	public function test_send_notification_scheduled() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 6;

		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		Functions\expect( 'get_current_user_id' )->andReturn( 1 );
		Functions\expect( 'wp_schedule_single_event' )->andReturn( true );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'title' )->andReturn( 'Scheduled' );
		$request->shouldReceive( 'get_param' )->with( 'body' )->andReturn( 'Body' );
		$request->shouldReceive( 'get_param' )->with( 'data' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'image_url' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'target_type' )->andReturn( 'all' );
		$request->shouldReceive( 'get_param' )->with( 'target_ids' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'post_id' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'scheduled_at' )->andReturn( '2025-03-01 14:00:00' );

		$response = $this->controller->send_notification( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( 6, $data['notification_id'] );
	}

	/**
	 * Test send_notification returns error on schedule failure.
	 */
	public function test_send_notification_schedule_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 0;

		$wpdb->shouldReceive( 'insert' )->andReturn( false );

		Functions\expect( 'get_current_user_id' )->andReturn( 1 );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'title' )->andReturn( 'Scheduled' );
		$request->shouldReceive( 'get_param' )->with( 'body' )->andReturn( 'Body' );
		$request->shouldReceive( 'get_param' )->with( 'data' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'image_url' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'target_type' )->andReturn( 'all' );
		$request->shouldReceive( 'get_param' )->with( 'target_ids' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'post_id' )->andReturn( null );
		$request->shouldReceive( 'get_param' )->with( 'scheduled_at' )->andReturn( '2025-03-01 14:00:00' );

		$response = $this->controller->send_notification( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'tailsignal_schedule_failed', $response->get_error_code() );
	}

	/**
	 * Test export_devices returns CSV.
	 */
	public function test_export_devices() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$device = new stdClass();
		$device->expo_token   = 'ExponentPushToken[test]';
		$device->device_type  = 'ios';
		$device->device_model = 'iPhone 16';
		$device->os_version   = 'iOS 18';
		$device->app_version  = '1.0.0';
		$device->locale       = 'en-US';
		$device->timezone     = 'America/Chicago';
		$device->user_label   = 'Test Device';
		$device->is_dev       = '0';
		$device->created_at   = '2025-01-01 00:00:00';

		$wpdb->shouldReceive( 'get_results' )->andReturn( array( $device ) );

		$request  = Mockery::mock( 'WP_REST_Request' );
		$response = $this->controller->export_devices( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertStringContainsString( 'text/csv', $headers['Content-Type'] );
		$this->assertStringContainsString( 'tailsignal-devices-', $headers['Content-Disposition'] );

		$csv = $response->get_data();
		$this->assertStringContainsString( 'expo_token', $csv );
		$this->assertStringContainsString( 'ExponentPushToken[test]', $csv );
	}

	/**
	 * Test import_devices returns error when no file provided.
	 */
	public function test_import_devices_no_file() {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_file_params' )->andReturn( array() );

		$response = $this->controller->import_devices( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'tailsignal_no_file', $response->get_error_code() );
	}

	/**
	 * Test import_devices rejects non-CSV extension.
	 */
	public function test_import_devices_rejects_non_csv() {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_file_params' )->andReturn( array(
			'file' => array(
				'tmp_name' => '/tmp/test.txt',
				'name'     => 'devices.php',
			),
		) );

		$response = $this->controller->import_devices( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'tailsignal_invalid_file_type', $response->get_error_code() );
	}

	/**
	 * Test rate limiting on register_device.
	 */
	public function test_register_device_rate_limited() {
		// Simulate rate limit exceeded.
		Functions\when( 'get_transient' )->alias( function() {
			return 31;
		} );

		$request = Mockery::mock( 'WP_REST_Request' );

		$response = $this->controller->register_device( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'tailsignal_rate_limited', $response->get_error_code() );
	}

	/**
	 * Test rate limiting allows requests under threshold.
	 */
	public function test_register_device_rate_limit_allows() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 1;

		// Under rate limit — return count of 5 for rate limit key.
		Functions\when( 'get_transient' )->alias( function() {
			return 5;
		} );

		Functions\expect( 'is_user_logged_in' )->andReturn( false );

		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'expo_token' )->andReturn( 'ExponentPushToken[ratetest]' );
		$request->shouldReceive( 'get_param' )->with( 'device_type' )->andReturn( 'ios' );
		$request->shouldReceive( 'get_param' )->with( 'device_model' )->andReturn( '' );
		$request->shouldReceive( 'get_param' )->with( 'os_version' )->andReturn( '' );
		$request->shouldReceive( 'get_param' )->with( 'app_version' )->andReturn( '' );
		$request->shouldReceive( 'get_param' )->with( 'locale' )->andReturn( '' );
		$request->shouldReceive( 'get_param' )->with( 'timezone' )->andReturn( '' );
		$request->shouldReceive( 'get_param' )->with( 'user_label' )->andReturn( null );

		$response = $this->controller->register_device( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 201, $response->get_status() );
	}

	/**
	 * Test import_devices with valid CSV file.
	 */
	public function test_import_devices_valid_csv() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 1;

		// Create a temp CSV file.
		$tmp = tmpfile();
		$csv_path = stream_get_meta_data( $tmp )['uri'];
		fputcsv( $tmp, array( 'expo_token', 'device_type', 'device_model', 'os_version', 'app_version', 'locale', 'timezone', 'user_label', 'is_dev', 'created_at' ), ',', '"', '\\' );
		fputcsv( $tmp, array( 'ExponentPushToken[import1]', 'ios', 'iPhone', '18', '1.0', 'en', 'UTC', 'Test', '0', '2025-01-01' ), ',', '"', '\\' );
		rewind( $tmp );

		// import_devices calls - transaction + get_device_by_token check, insert for new.
		$wpdb->shouldReceive( 'query' )->andReturn( true );
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_file_params' )->andReturn( array(
			'file' => array(
				'tmp_name' => $csv_path,
				'name'     => 'devices.csv',
			),
		) );

		$response = $this->controller->import_devices( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'new', $data );
		$this->assertArrayHasKey( 'updated', $data );
		$this->assertArrayHasKey( 'skipped', $data );

		fclose( $tmp );
	}
}
