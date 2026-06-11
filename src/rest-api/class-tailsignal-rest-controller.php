<?php
/**
 * REST API controller for TailSignal.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'tailsignal/v1';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Register device.
		register_rest_route( $this->namespace, '/register', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_device' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_register_args(),
			),
			// Public: devices self-unregister. Expo tokens are cryptographically random
			// (e.g., ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]), making enumeration infeasible.
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unregister_device' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'expo_token' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		) );

		// Check registration status (public — token is proof of ownership).
		register_rest_route( $this->namespace, '/register/status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'check_registration_status' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'expo_token' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		// Send notification (admin only).
		register_rest_route( $this->namespace, '/send', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'send_notification' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => $this->get_send_args(),
		) );

		// Dashboard stats (admin only).
		register_rest_route( $this->namespace, '/stats', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_stats' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );

		// Export devices (admin only).
		register_rest_route( $this->namespace, '/devices/export', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'export_devices' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );

		// Import devices (admin only).
		register_rest_route( $this->namespace, '/devices/import', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'import_devices' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );
	}

	/**
	 * Check if the user has admin permission.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool|WP_Error True if allowed.
	 */
	public function check_admin_permission( $request ) {
		if ( ! current_user_can( 'tailsignal_manage' ) ) {
			return new WP_Error(
				'tailsignal_forbidden',
				__( 'You do not have permission to perform this action.', 'tailsignal' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Get registration endpoint arguments.
	 *
	 * @return array
	 */
	private function get_register_args() {
		return array(
			'expo_token'   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $value ) {
					if ( ! \ExpoSDK\Utils::isExpoPushToken( $value ) ) {
						return new WP_Error(
							'tailsignal_invalid_token',
							__( 'Invalid Expo push token format.', 'tailsignal' ),
							array( 'status' => 400 )
						);
					}
					return true;
				},
			),
			'device_type'  => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $value ) {
					return in_array( $value, array( 'ios', 'android' ), true );
				},
			),
			'device_model' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'os_version'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'app_version'  => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'locale'       => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'timezone'     => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'user_label'   => array(
				'type'              => 'string',
				'default'           => null,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get send endpoint arguments.
	 *
	 * @return array
	 */
	private function get_send_args() {
		return array(
			'title'       => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'body'        => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'data'        => array(
				'type'              => 'string',
				'default'           => null,
				'sanitize_callback' => function( $value ) {
					return is_string( $value ) ? wp_unslash( trim( $value ) ) : $value;
				},
				'validate_callback' => function( $value ) {
					if ( null === $value || '' === $value ) {
						return true;
					}
					$decoded = json_decode( $value );
					if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
						return new WP_Error(
							'tailsignal_invalid_json',
							__( 'The data field must be valid JSON.', 'tailsignal' ),
							array( 'status' => 400 )
						);
					}
					return true;
				},
			),
			'image_url'   => array(
				'type'              => 'string',
				'default'           => null,
				'sanitize_callback' => 'esc_url_raw',
			),
			'target_type' => array(
				'type'              => 'string',
				'default'           => 'all',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $value ) {
					return in_array( $value, array( 'all', 'dev', 'group', 'specific' ), true );
				},
			),
			'target_ids'  => array(
				'type'    => 'array',
				'default' => null,
				'items'   => array( 'type' => 'integer' ),
			),
			'post_id'     => array(
				'type'    => 'integer',
				'default' => null,
			),
			'scheduled_at' => array(
				'type'              => 'string',
				'default'           => null,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $value ) {
					if ( null === $value || '' === $value ) {
						return true;
					}
					// Site-local input; convert via the site timezone (WP pins
					// PHP to UTC, so strtotime() would skew by the UTC offset).
					$timestamp = (int) get_gmt_from_date( $value, 'U' );
					if ( $timestamp <= 0 ) {
						return new WP_Error(
							'tailsignal_invalid_date',
							__( 'Invalid date format for scheduled_at.', 'tailsignal' ),
							array( 'status' => 400 )
						);
					}
					if ( $timestamp <= time() ) {
						return new WP_Error(
							'tailsignal_past_date',
							__( 'Scheduled time must be in the future.', 'tailsignal' ),
							array( 'status' => 400 )
						);
					}
					return true;
				},
			),
		);
	}

	/**
	 * Check rate limit for public endpoints.
	 *
	 * Uses transient-based rate limiting: 30 requests per minute per IP.
	 *
	 * @return true|WP_Error True if allowed, WP_Error if rate limited.
	 */
	private function check_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'tailsignal_rate_' . md5( $ip );

		$count = get_transient( $key );
		if ( false === $count ) {
			set_transient( $key, 1, MINUTE_IN_SECONDS );
			return true;
		}

		if ( (int) $count >= 30 ) {
			return new WP_Error(
				'tailsignal_rate_limited',
				__( 'Too many requests. Please try again later.', 'tailsignal' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, (int) $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Register a device.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_device( $request ) {
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$data = array(
			'expo_token'   => $request->get_param( 'expo_token' ),
			'device_type'  => $request->get_param( 'device_type' ),
			'device_model' => $request->get_param( 'device_model' ),
			'os_version'   => $request->get_param( 'os_version' ),
			'app_version'  => $request->get_param( 'app_version' ),
			'locale'       => $request->get_param( 'locale' ),
			'timezone'     => $request->get_param( 'timezone' ),
			'user_label'   => $request->get_param( 'user_label' ),
		);

		// Link to WP user if authenticated.
		if ( is_user_logged_in() ) {
			$data['user_id'] = get_current_user_id();
		}

		$device_id = TailSignal_DB::insert_device( $data );

		if ( false === $device_id ) {
			return new WP_Error(
				'tailsignal_registration_failed',
				__( 'Failed to register device.', 'tailsignal' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success'   => true,
				'device_id' => $device_id,
				'message'   => __( 'Device registered successfully.', 'tailsignal' ),
			),
			201
		);
	}

	/**
	 * Unregister a device.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function unregister_device( $request ) {
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$expo_token = $request->get_param( 'expo_token' );

		// Look the device up first so unregister is idempotent: a repeat
		// DELETE for an already-inactive device is a success, not a 404
		// (remove_device() returns false when 0 rows change).
		$device = TailSignal_DB::get_device_by_token( $expo_token );

		if ( ! $device ) {
			return new WP_Error(
				'tailsignal_not_found',
				__( 'Device not found.', 'tailsignal' ),
				array( 'status' => 404 )
			);
		}

		if ( $device->is_active ) {
			TailSignal_DB::remove_device( $expo_token );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Device unregistered successfully.', 'tailsignal' ),
			),
			200
		);
	}

	/**
	 * Check if a device token is registered and active.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function check_registration_status( $request ) {
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$expo_token = $request->get_param( 'expo_token' );
		$device     = TailSignal_DB::get_device_by_token( $expo_token );

		if ( ! $device ) {
			return new WP_REST_Response(
				array(
					'registered' => false,
					'active'     => false,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'registered' => true,
				'active'     => (bool) $device->is_active,
			),
			200
		);
	}

	/**
	 * Send a notification via REST API.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_notification( $request ) {
		$params = array(
			'title'     => $request->get_param( 'title' ),
			'body'      => $request->get_param( 'body' ),
			'data'      => $request->get_param( 'data' ),
			'image_url' => $request->get_param( 'image_url' ),
		);

		$target_type  = $request->get_param( 'target_type' );
		$target_ids   = $request->get_param( 'target_ids' );
		$post_id      = $request->get_param( 'post_id' );
		$scheduled_at = $request->get_param( 'scheduled_at' );

		// Handle scheduled notifications.
		if ( ! empty( $scheduled_at ) ) {
			$notification_id = TailSignal_Notification::schedule_notification(
				$params,
				$scheduled_at,
				$target_type,
				$target_ids,
				$post_id,
				get_current_user_id()
			);

			if ( ! $notification_id ) {
				return new WP_Error(
					'tailsignal_schedule_failed',
					__( 'Failed to schedule notification.', 'tailsignal' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'success'         => true,
					'notification_id' => $notification_id,
					'message'         => __( 'Notification scheduled successfully.', 'tailsignal' ),
				),
				200
			);
		}

		// Get tokens based on targeting.
		$tokens = TailSignal_DB::get_tokens_by_target( $target_type, $target_ids );

		if ( empty( $tokens ) ) {
			return new WP_Error(
				'tailsignal_no_devices',
				__( 'No devices found for the selected target.', 'tailsignal' ),
				array( 'status' => 404 )
			);
		}

		$notification_id = TailSignal_Notification::send_notification(
			$params,
			$tokens,
			'manual',
			$post_id,
			$target_type,
			$target_ids,
			get_current_user_id()
		);

		if ( ! $notification_id ) {
			return new WP_Error(
				'tailsignal_send_failed',
				__( 'Failed to send notification.', 'tailsignal' ),
				array( 'status' => 500 )
			);
		}

		$notification = TailSignal_DB::get_notification( $notification_id );

		return new WP_REST_Response(
			array(
				'success'         => true,
				'notification_id' => $notification_id,
				'total_devices'   => $notification ? $notification->total_devices : 0,
				'total_success'   => $notification ? $notification->total_success : 0,
				'total_failed'    => $notification ? $notification->total_failed : 0,
				'message'         => __( 'Notification sent successfully.', 'tailsignal' ),
			),
			200
		);
	}

	/**
	 * Get dashboard stats.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function get_stats( $request ) {
		// Single cached query instead of three separate COUNTs.
		$summary = TailSignal_DB::get_device_summary_stats();

		return new WP_REST_Response(
			array(
				'total_devices'   => $summary['total'],
				'ios_devices'     => $summary['ios'],
				'android_devices' => $summary['android'],
				'dev_devices'     => $summary['dev'],
				'monthly_sent'    => TailSignal_DB::get_monthly_send_count(),
				'success_rate'    => TailSignal_DB::get_success_rate(),
				'dev_mode'        => '1' === get_option( 'tailsignal_dev_mode', '0' ),
			),
			200
		);
	}

	/**
	 * Export devices as CSV.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function export_devices( $request ) {
		$devices = TailSignal_DB::get_devices_for_export();

		// Build CSV string.
		$output = fopen( 'php://temp', 'r+' );

		// Header row.
		fputcsv( $output, array(
			'expo_token',
			'device_type',
			'device_model',
			'os_version',
			'app_version',
			'locale',
			'timezone',
			'user_label',
			'is_dev',
			'created_at',
		), ',', '"', '\\' );

		foreach ( $devices as $device ) {
			fputcsv( $output, array_map( array( $this, 'sanitize_csv_value' ), array(
				$device->expo_token,
				$device->device_type,
				$device->device_model,
				$device->os_version,
				$device->app_version,
				$device->locale,
				$device->timezone,
				$device->user_label,
				$device->is_dev,
				$device->created_at,
			) ), ',', '"', '\\' );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		$response = new WP_REST_Response( $csv, 200 );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="tailsignal-devices-' . gmdate( 'Y-m-d' ) . '.csv"' );

		return $response;
	}

	/**
	 * Serve CSV export as raw output instead of JSON.
	 *
	 * Hooked to rest_pre_serve_request to intercept CSV responses.
	 *
	 * @param bool             $served  Whether the request has been served.
	 * @param WP_HTTP_Response $result  Response object.
	 * @param WP_REST_Request  $request Request object.
	 * @param WP_REST_Server   $server  Server object.
	 * @return bool Whether the request has been served.
	 */
	public function serve_csv_response( $served, $result, $request, $server ) {
		if ( $served || ! $result instanceof WP_REST_Response ) {
			return $served;
		}

		$headers = $result->get_headers();
		if ( empty( $headers['Content-Type'] ) || false === strpos( $headers['Content-Type'], 'text/csv' ) ) {
			return $served;
		}

		// Send headers.
		$server->send_headers( $result->get_headers() );
		status_header( $result->get_status() );

		// Output raw CSV data.
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data, not HTML.

		return true;
	}

	/**
	 * Import devices from CSV.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_devices( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) || empty( $files['file']['tmp_name'] ) ) {
			return new WP_Error(
				'tailsignal_no_file',
				__( 'No CSV file provided.', 'tailsignal' ),
				array( 'status' => 400 )
			);
		}

		// Validate file type.
		$file_info = $files['file'];
		$extension = strtolower( pathinfo( $file_info['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $extension ) {
			return new WP_Error(
				'tailsignal_invalid_file_type',
				__( 'Only CSV files are accepted.', 'tailsignal' ),
				array( 'status' => 400 )
			);
		}

		$allowed_mimes = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( ! $finfo ) {
			return new WP_Error(
				'tailsignal_mime_check_failed',
				__( 'Unable to verify file type. MIME detection unavailable.', 'tailsignal' ),
				array( 'status' => 500 )
			);
		}
		$detected_mime = finfo_file( $finfo, $file_info['tmp_name'] );
		if ( PHP_VERSION_ID < 80500 ) {
			finfo_close( $finfo );
		}
		if ( $detected_mime && ! in_array( $detected_mime, $allowed_mimes, true ) ) {
			return new WP_Error(
				'invalid_file',
				__( 'Invalid file type.', 'tailsignal' ),
				array( 'status' => 400 )
			);
		}

		$file = fopen( $file_info['tmp_name'], 'r' );
		if ( ! $file ) {
			return new WP_Error(
				'tailsignal_file_error',
				__( 'Could not read the uploaded file.', 'tailsignal' ),
				array( 'status' => 400 )
			);
		}

		$headers = fgetcsv( $file, 0, ',', '"', '\\' );

		// Strip a UTF-8 BOM (Excel exports) from the first header, otherwise
		// the expo_token column never matches and every row is skipped.
		if ( is_array( $headers ) && isset( $headers[0] ) ) {
			$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $headers[0] );
			$headers    = array_map( 'trim', $headers );
		}

		if ( ! $headers ) {
			fclose( $file );
			return new WP_Error(
				'tailsignal_invalid_csv',
				__( 'Invalid CSV format.', 'tailsignal' ),
				array( 'status' => 400 )
			);
		}

		$rows = array();
		while ( ( $row = fgetcsv( $file, 0, ',', '"', '\\' ) ) !== false ) {
			if ( count( $row ) === count( $headers ) ) {
				$rows[] = array_combine( $headers, $row );
			}
		}
		fclose( $file );

		$results = TailSignal_DB::import_devices( $rows );

		return new WP_REST_Response(
			array(
				'success' => true,
				'new'     => $results['new'],
				'updated' => $results['updated'],
				'skipped' => $results['skipped'],
				'message' => sprintf(
					/* translators: 1: new count, 2: updated count, 3: skipped count */
					__( 'Import complete: %1$d new, %2$d updated, %3$d skipped.', 'tailsignal' ),
					$results['new'],
					$results['updated'],
					$results['skipped']
				),
			),
			200
		);
	}

	/**
	 * Sanitize a value for CSV export to prevent formula injection.
	 *
	 * @param mixed $value The cell value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_csv_value( $value ) {
		if ( is_string( $value ) && isset( $value[0] ) && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
