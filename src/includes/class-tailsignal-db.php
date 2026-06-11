<?php
/**
 * Database operations for TailSignal.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_DB {

	/**
	 * Map of device column format specifiers.
	 */
	private static $device_formats = array(
		'user_id'        => '%d',
		'expo_token'     => '%s',
		'device_type'    => '%s',
		'device_model'   => '%s',
		'os_version'     => '%s',
		'app_version'    => '%s',
		'locale'         => '%s',
		'timezone'       => '%s',
		'user_label'     => '%s',
		'is_dev'         => '%d',
		'is_active'      => '%d',
		'last_active_at' => '%s',
		'created_at'     => '%s',
		'updated_at'     => '%s',
	);

	/**
	 * Map of notification column format specifiers.
	 */
	private static $notification_formats = array(
		'title'         => '%s',
		'body'          => '%s',
		'data'          => '%s',
		'post_id'       => '%d',
		'type'          => '%s',
		'target_type'   => '%s',
		'target_ids'    => '%s',
		'image_url'     => '%s',
		'scheduled_at'  => '%s',
		'total_devices' => '%d',
		'total_success' => '%d',
		'total_failed'  => '%d',
		'status'        => '%s',
		'ticket_ids'    => '%s',
		'receipt_data'  => '%s',
		'sent_by'       => '%d',
		'created_at'    => '%s',
		'updated_at'    => '%s',
	);

	/**
	 * Get format array for device data.
	 *
	 * @param array $data The data array.
	 * @return array Format specifiers in same order as data keys.
	 */
	private static function get_device_format( $data ) {
		$format = array();
		foreach ( array_keys( $data ) as $key ) {
			$format[] = self::$device_formats[ $key ] ?? '%s';
		}
		return $format;
	}

	/**
	 * Get format array for notification data.
	 *
	 * @param array $data The data array.
	 * @return array Format specifiers in same order as data keys.
	 */
	private static function get_notification_format( $data ) {
		$format = array();
		foreach ( array_keys( $data ) as $key ) {
			$format[] = self::$notification_formats[ $key ] ?? '%s';
		}
		return $format;
	}

	/**
	 * Create all custom tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'tailsignal_';

		$sql = array();

		// Devices table.
		$sql[] = "CREATE TABLE {$prefix}devices (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) DEFAULT NULL,
			expo_token varchar(255) NOT NULL,
			device_type varchar(10) NOT NULL DEFAULT '',
			device_model varchar(100) NOT NULL DEFAULT '',
			os_version varchar(50) NOT NULL DEFAULT '',
			app_version varchar(50) NOT NULL DEFAULT '',
			locale varchar(20) NOT NULL DEFAULT '',
			timezone varchar(100) NOT NULL DEFAULT '',
			user_label varchar(255) DEFAULT NULL,
			is_dev tinyint(1) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			last_active_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY expo_token (expo_token),
			KEY user_id (user_id),
			KEY is_active (is_active),
			KEY is_dev (is_dev)
		) $charset_collate;";

		// Device meta table.
		$sql[] = "CREATE TABLE {$prefix}device_meta (
			meta_id bigint(20) NOT NULL AUTO_INCREMENT,
			device_id bigint(20) NOT NULL,
			meta_key varchar(255) NOT NULL DEFAULT '',
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY device_id (device_id),
			KEY meta_key (meta_key(191)),
			KEY device_meta_key (device_id, meta_key(191))
		) $charset_collate;";

		// Groups table.
		$sql[] = "CREATE TABLE {$prefix}groups (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// Device groups pivot table.
		$sql[] = "CREATE TABLE {$prefix}device_groups (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			device_id bigint(20) NOT NULL,
			group_id bigint(20) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY device_group (device_id, group_id),
			KEY device_id (device_id),
			KEY group_id (group_id)
		) $charset_collate;";

		// Notifications table.
		$sql[] = "CREATE TABLE {$prefix}notifications (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			body text NOT NULL,
			data longtext,
			post_id bigint(20) DEFAULT NULL,
			type varchar(20) NOT NULL DEFAULT 'manual',
			target_type varchar(20) NOT NULL DEFAULT 'all',
			target_ids longtext,
			image_url varchar(500) DEFAULT NULL,
			scheduled_at datetime DEFAULT NULL,
			total_devices int NOT NULL DEFAULT 0,
			total_success int NOT NULL DEFAULT 0,
			total_failed int NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			ticket_ids longtext,
			receipt_data longtext,
			sent_by bigint(20) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY type (type),
			KEY created_at (created_at)
		) $charset_collate;";

		// Notification history (post-notification link).
		$sql[] = "CREATE TABLE {$prefix}notification_history (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			notification_id bigint(20) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY notification_id (notification_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Drop all custom tables.
	 */
	public static function drop_tables() {
		global $wpdb;

		$prefix = $wpdb->prefix . 'tailsignal_';
		$tables = array(
			'notification_history',
			'device_groups',
			'device_meta',
			'notifications',
			'groups',
			'devices',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Insert or update a device.
	 *
	 * @param array $data Device data.
	 * @return int|false The device ID or false on failure.
	 */
	public static function insert_device( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';
		$now   = current_time( 'mysql' );

		$defaults = array(
			'user_id'        => null,
			'expo_token'     => '',
			'device_type'    => '',
			'device_model'   => '',
			'os_version'     => '',
			'app_version'    => '',
			'locale'         => '',
			'timezone'       => '',
			'user_label'     => null,
			'is_dev'         => 0,
			'is_active'      => 1,
			'last_active_at' => $now,
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		// Keep the caller-supplied keys: the update path must only touch
		// fields the caller actually sent, otherwise a partial re-register
		// wipes stored values (user_id link, labels, etc.) with defaults.
		$supplied = $data;
		$data     = wp_parse_args( $data, $defaults );

		// Check if device already exists.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE expo_token = %s",
				$data['expo_token']
			)
		);

		if ( $existing ) {
			return self::update_existing_device( (int) $existing, $supplied, $now );
		}

		$format = self::get_device_format( $data );

		$inserted = $wpdb->insert( $table, $data, $format );

		if ( $wpdb->insert_id ) {
			self::invalidate_device_cache();
			return (int) $wpdb->insert_id;
		}

		// Insert failed — a concurrent registration may have won the race.
		// Re-check and fall through to the update path instead of erroring.
		if ( false === $inserted ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE expo_token = %s",
					$data['expo_token']
				)
			);
			if ( $existing ) {
				return self::update_existing_device( (int) $existing, $supplied, $now );
			}
		}

		return false;
	}

	/**
	 * Update an existing device row from a (re-)registration.
	 *
	 * Only the caller-supplied fields are written; registering always
	 * refreshes activity timestamps and re-activates the device (a register
	 * call expresses intent to receive notifications).
	 *
	 * @param int    $device_id Existing device ID.
	 * @param array  $supplied  Fields the caller actually passed.
	 * @param string $now       Current MySQL datetime.
	 * @return int The device ID.
	 */
	private static function update_existing_device( $device_id, $supplied, $now ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		unset( $supplied['created_at'], $supplied['expo_token'] );

		// Never clear an existing user link with an empty value.
		if ( array_key_exists( 'user_id', $supplied ) && empty( $supplied['user_id'] ) ) {
			unset( $supplied['user_id'] );
		}

		$supplied['updated_at']     = $now;
		$supplied['last_active_at'] = $now;
		$supplied['is_active']      = 1;

		$wpdb->update(
			$table,
			$supplied,
			array( 'id' => $device_id ),
			self::get_device_format( $supplied ),
			array( '%d' )
		);

		self::invalidate_device_cache();

		return $device_id;
	}

	/**
	 * Remove a device by token (soft delete).
	 *
	 * @param string $expo_token The Expo push token.
	 * @return bool True on success.
	 */
	public static function remove_device( $expo_token ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		$result = (bool) $wpdb->update(
			$table,
			array(
				'is_active'  => 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'expo_token' => $expo_token ),
			array( '%d', '%s' ),
			array( '%s' )
		);

		if ( $result ) {
			self::invalidate_device_cache();
		}

		return $result;
	}

	/**
	 * Hard delete a device and its related data.
	 *
	 * @param int $device_id The device ID.
	 * @return bool True on success.
	 */
	public static function delete_device( $device_id ) {
		global $wpdb;

		$prefix = $wpdb->prefix . 'tailsignal_';

		// Delete device meta.
		$wpdb->delete( $prefix . 'device_meta', array( 'device_id' => $device_id ), array( '%d' ) );

		// Delete device group assignments.
		$wpdb->delete( $prefix . 'device_groups', array( 'device_id' => $device_id ), array( '%d' ) );

		// Delete device.
		$result = (bool) $wpdb->delete( $prefix . 'devices', array( 'id' => $device_id ), array( '%d' ) );

		if ( $result ) {
			self::invalidate_device_cache();
		}

		return $result;
	}

	/**
	 * Get a device by ID.
	 *
	 * @param int $device_id The device ID.
	 * @return object|null The device row or null.
	 */
	public static function get_device( $device_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $device_id )
		);
	}

	/**
	 * Get a device by expo token.
	 *
	 * @param string $expo_token The Expo push token.
	 * @return object|null The device row or null.
	 */
	public static function get_device_by_token( $expo_token ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE expo_token = %s", $expo_token )
		);
	}

	/**
	 * Update a device.
	 *
	 * @param int   $device_id The device ID.
	 * @param array $data      Data to update.
	 * @return bool True on success.
	 */
	public static function update_device( $device_id, $data ) {
		global $wpdb;

		$table             = $wpdb->prefix . 'tailsignal_devices';
		$data['updated_at'] = current_time( 'mysql' );

		return (bool) $wpdb->update(
			$table,
			$data,
			array( 'id' => $device_id ),
			self::get_device_format( $data ),
			array( '%d' )
		);
	}

	/**
	 * Get all active tokens, optionally filtered by dev mode.
	 *
	 * @return array Array of expo tokens.
	 */
	public static function get_all_active_tokens() {
		global $wpdb;

		$table    = $wpdb->prefix . 'tailsignal_devices';
		$dev_mode = get_option( 'tailsignal_dev_mode', '0' );

		if ( '1' === $dev_mode ) {
			return $wpdb->get_col(
				"SELECT expo_token FROM {$table} WHERE is_active = 1 AND is_dev = 1"
			);
		}

		return $wpdb->get_col(
			"SELECT expo_token FROM {$table} WHERE is_active = 1"
		);
	}

	/**
	 * Get tokens by target type.
	 *
	 * @param string     $target_type Target type: all, dev, group, specific.
	 * @param array|null $target_ids  Array of group IDs or device IDs.
	 * @return array Array of expo tokens.
	 */
	public static function get_tokens_by_target( $target_type, $target_ids = null ) {
		global $wpdb;

		$devices_table       = $wpdb->prefix . 'tailsignal_devices';
		$device_groups_table = $wpdb->prefix . 'tailsignal_device_groups';

		switch ( $target_type ) {
			case 'dev':
				return $wpdb->get_col(
					"SELECT expo_token FROM {$devices_table} WHERE is_active = 1 AND is_dev = 1"
				);

			case 'group':
				if ( empty( $target_ids ) ) {
					return array();
				}
				$placeholders = implode( ',', array_fill( 0, count( $target_ids ), '%d' ) );
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT d.expo_token FROM {$devices_table} d
						INNER JOIN {$device_groups_table} dg ON d.id = dg.device_id
						WHERE d.is_active = 1 AND dg.group_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$target_ids
					)
				);

			case 'specific':
				if ( empty( $target_ids ) ) {
					return array();
				}
				$placeholders = implode( ',', array_fill( 0, count( $target_ids ), '%d' ) );
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT expo_token FROM {$devices_table} WHERE is_active = 1 AND id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$target_ids
					)
				);

			case 'all':
			default:
				return self::get_all_active_tokens();
		}
	}

	/**
	 * Get devices with pagination and filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array Array with 'items' and 'total'.
	 */
	public static function get_devices( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		$defaults = array(
			'per_page'    => 20,
			'page'        => 1,
			'search'      => '',
			'device_type' => '',
			'is_active'   => '',
			'is_dev'      => '',
			'group_id'    => '',
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( '' !== $args['is_active'] ) {
			$where[]  = 'd.is_active = %d';
			$values[] = (int) $args['is_active'];
		}

		if ( '' !== $args['is_dev'] ) {
			$where[]  = 'd.is_dev = %d';
			$values[] = (int) $args['is_dev'];
		}

		if ( ! empty( $args['device_type'] ) ) {
			$where[]  = 'd.device_type = %s';
			$values[] = $args['device_type'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(d.expo_token LIKE %s OR d.user_label LIKE %s)';
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );

		$join = '';
		if ( ! empty( $args['group_id'] ) ) {
			$device_groups_table = $wpdb->prefix . 'tailsignal_device_groups';
			$join                = "INNER JOIN {$device_groups_table} dg ON d.id = dg.device_id";
			$where_clause       .= $wpdb->prepare( ' AND dg.group_id = %d', (int) $args['group_id'] );
		}

		$allowed_orderby = array( 'id', 'expo_token', 'device_type', 'created_at', 'last_active_at', 'user_label' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$offset = ( (int) $args['page'] - 1 ) * (int) $args['per_page'];

		// Get total.
		$count_query = "SELECT COUNT(DISTINCT d.id) FROM {$table} d {$join} WHERE {$where_clause}";
		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Get items.
		$query = "SELECT DISTINCT d.* FROM {$table} d {$join} WHERE {$where_clause} ORDER BY d.{$orderby} {$order} LIMIT %d OFFSET %d";
		$query_values = array_merge( $values, array( (int) $args['per_page'], $offset ) );
		$items = $wpdb->get_results( $wpdb->prepare( $query, $query_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Invalidate device-related transient caches.
	 */
	public static function invalidate_device_cache() {
		delete_transient( 'tailsignal_device_summary_stats' );
		delete_transient( 'tailsignal_device_count_platform' );
	}

	/**
	 * Invalidate notification-related transient caches.
	 */
	public static function invalidate_notification_cache() {
		delete_transient( 'tailsignal_notification_counts_status' );
		delete_transient( 'tailsignal_success_rate' );
		delete_transient( 'tailsignal_monthly_notification_stats' );
	}

	/**
	 * Get device count.
	 *
	 * @param bool $active_only Whether to count only active devices.
	 * @return int Device count.
	 */
	public static function get_device_count( $active_only = true ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		if ( $active_only ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1" );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Get device count by platform.
	 *
	 * @return array Associative array of platform => count.
	 */
	public static function get_device_count_by_platform() {
		$cached = get_transient( 'tailsignal_device_count_platform' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'tailsignal_devices';
		$results = $wpdb->get_results(
			"SELECT device_type, COUNT(*) as count FROM {$table} WHERE is_active = 1 GROUP BY device_type"
		);

		$counts = array( 'ios' => 0, 'android' => 0 );
		foreach ( $results as $row ) {
			$counts[ $row->device_type ] = (int) $row->count;
		}

		set_transient( 'tailsignal_device_count_platform', $counts, 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Get dev device count.
	 *
	 * @return int Dev device count.
	 */
	public static function get_dev_device_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND is_dev = 1"
		);
	}

	/**
	 * Get all device summary stats in a single query.
	 *
	 * Returns total active, iOS count, Android count, and dev count
	 * from one DB round-trip instead of three separate COUNT queries.
	 *
	 * @return array Associative array with total, ios, android, dev keys.
	 */
	public static function get_device_summary_stats() {
		$cached = get_transient( 'tailsignal_device_summary_stats' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN device_type = 'ios' THEN 1 ELSE 0 END) as ios,
				SUM(CASE WHEN device_type = 'android' THEN 1 ELSE 0 END) as android,
				SUM(CASE WHEN is_dev = 1 THEN 1 ELSE 0 END) as dev
			FROM {$table}
			WHERE is_active = 1"
		);

		$result = array(
			'total'   => $row ? (int) $row->total : 0,
			'ios'     => $row ? (int) $row->ios : 0,
			'android' => $row ? (int) $row->android : 0,
			'dev'     => $row ? (int) $row->dev : 0,
		);

		set_transient( 'tailsignal_device_summary_stats', $result, 5 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Bulk delete devices.
	 *
	 * @param array $device_ids Array of device IDs.
	 * @return int Number of devices deleted.
	 */
	public static function bulk_delete_devices( $device_ids ) {
		global $wpdb;

		if ( empty( $device_ids ) ) {
			return 0;
		}

		$device_ids   = array_map( 'intval', $device_ids );
		$prefix       = $wpdb->prefix . 'tailsignal_';
		$placeholders = implode( ',', array_fill( 0, count( $device_ids ), '%d' ) );

		// Batch delete related data in 3 queries instead of 3 per device.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}device_meta WHERE device_id IN ({$placeholders})", $device_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}device_groups WHERE device_id IN ({$placeholders})", $device_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$count = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}devices WHERE id IN ({$placeholders})", $device_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $count > 0 ) {
			self::invalidate_device_cache();
		}

		return $count;
	}

	/**
	 * Remove stale tokens (mark inactive).
	 *
	 * @param array $tokens Array of expo tokens to deactivate.
	 * @return int Number of tokens deactivated.
	 */
	public static function deactivate_tokens( $tokens ) {
		global $wpdb;

		if ( empty( $tokens ) ) {
			return 0;
		}

		$table        = $wpdb->prefix . 'tailsignal_devices';
		$now          = current_time( 'mysql' );
		$placeholders = implode( ',', array_fill( 0, count( $tokens ), '%s' ) );

		// Single UPDATE with WHERE IN instead of one query per token.
		$count = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_active = 0, updated_at = %s WHERE expo_token IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $now ), $tokens )
			)
		);

		if ( $count > 0 ) {
			self::invalidate_device_cache();
		}

		return $count;
	}

	// ── Groups ──────────────────────────────────────────────────

	/**
	 * Create a group.
	 *
	 * @param array $data Group data (name, description).
	 * @return int|false The group ID or false.
	 */
	public static function create_group( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_groups';

		$result = $wpdb->insert(
			$table,
			array(
				'name'        => sanitize_text_field( $data['name'] ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a group.
	 *
	 * @param int   $group_id The group ID.
	 * @param array $data     Data to update.
	 * @return bool True on success.
	 */
	public static function update_group( $group_id, $data ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'tailsignal_groups';
		$update  = array();
		$formats = array();

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$formats[]      = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update['description'] = sanitize_textarea_field( $data['description'] );
			$formats[]             = '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		return (bool) $wpdb->update( $table, $update, array( 'id' => $group_id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete a group and its device assignments.
	 *
	 * @param int $group_id The group ID.
	 * @return bool True on success.
	 */
	public static function delete_group( $group_id ) {
		global $wpdb;

		$prefix = $wpdb->prefix . 'tailsignal_';

		// Remove device assignments.
		$wpdb->delete( $prefix . 'device_groups', array( 'group_id' => $group_id ), array( '%d' ) );

		// Delete group.
		return (bool) $wpdb->delete( $prefix . 'groups', array( 'id' => $group_id ), array( '%d' ) );
	}

	/**
	 * Get a group by ID.
	 *
	 * @param int $group_id The group ID.
	 * @return object|null The group row or null.
	 */
	public static function get_group( $group_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_groups';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $group_id )
		);
	}

	/**
	 * Get all groups.
	 *
	 * @return array Array of group objects.
	 */
	public static function get_all_groups() {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_groups';

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
	}

	/**
	 * Get groups with device counts.
	 *
	 * @return array Array of group objects with device_count.
	 */
	public static function get_groups_with_counts() {
		global $wpdb;

		$groups_table        = $wpdb->prefix . 'tailsignal_groups';
		$device_groups_table = $wpdb->prefix . 'tailsignal_device_groups';

		return $wpdb->get_results(
			"SELECT g.*, COUNT(dg.device_id) as device_count
			FROM {$groups_table} g
			LEFT JOIN {$device_groups_table} dg ON g.id = dg.group_id
			GROUP BY g.id
			ORDER BY g.name ASC"
		);
	}

	/**
	 * Assign devices to a group.
	 *
	 * @param int   $group_id   The group ID.
	 * @param array $device_ids Array of device IDs.
	 */
	public static function assign_devices_to_group( $group_id, $device_ids ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_device_groups';

		foreach ( $device_ids as $device_id ) {
			$wpdb->replace(
				$table,
				array(
					'device_id' => (int) $device_id,
					'group_id'  => (int) $group_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Remove devices from a group.
	 *
	 * @param int   $group_id   The group ID.
	 * @param array $device_ids Array of device IDs.
	 */
	public static function remove_devices_from_group( $group_id, $device_ids ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_device_groups';

		foreach ( $device_ids as $device_id ) {
			$wpdb->delete(
				$table,
				array(
					'device_id' => (int) $device_id,
					'group_id'  => (int) $group_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Set devices for a group (replaces all existing).
	 *
	 * @param int   $group_id   The group ID.
	 * @param array $device_ids Array of device IDs.
	 */
	public static function set_group_devices( $group_id, $device_ids ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_device_groups';

		// Remove all existing.
		$wpdb->delete( $table, array( 'group_id' => (int) $group_id ), array( '%d' ) );

		// Add new assignments.
		if ( ! empty( $device_ids ) ) {
			self::assign_devices_to_group( $group_id, $device_ids );
		}
	}

	/**
	 * Get device IDs for a group.
	 *
	 * @param int $group_id The group ID.
	 * @return array Array of device IDs.
	 */
	public static function get_group_device_ids( $group_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_device_groups';

		return $wpdb->get_col(
			$wpdb->prepare( "SELECT device_id FROM {$table} WHERE group_id = %d", $group_id )
		);
	}

	/**
	 * Get groups for a device.
	 *
	 * @param int $device_id The device ID.
	 * @return array Array of group objects.
	 */
	public static function get_device_groups( $device_id ) {
		global $wpdb;

		$groups_table        = $wpdb->prefix . 'tailsignal_groups';
		$device_groups_table = $wpdb->prefix . 'tailsignal_device_groups';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.* FROM {$groups_table} g
				INNER JOIN {$device_groups_table} dg ON g.id = dg.group_id
				WHERE dg.device_id = %d
				ORDER BY g.name ASC",
				$device_id
			)
		);
	}

	/**
	 * Get groups for multiple devices in a single query.
	 *
	 * Returns an associative array keyed by device_id, each containing
	 * an array of group objects. Eliminates N+1 queries on list pages.
	 *
	 * @param array $device_ids Array of device IDs.
	 * @return array Associative array of device_id => array of group objects.
	 */
	public static function get_devices_groups_bulk( $device_ids ) {
		global $wpdb;

		if ( empty( $device_ids ) ) {
			return array();
		}

		$device_ids          = array_map( 'intval', $device_ids );
		$groups_table        = $wpdb->prefix . 'tailsignal_groups';
		$device_groups_table = $wpdb->prefix . 'tailsignal_device_groups';
		$placeholders        = implode( ',', array_fill( 0, count( $device_ids ), '%d' ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT dg.device_id, g.id, g.name, g.description FROM {$groups_table} g
				INNER JOIN {$device_groups_table} dg ON g.id = dg.group_id
				WHERE dg.device_id IN ({$placeholders})
				ORDER BY g.name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$device_ids
			)
		);

		$map = array();
		foreach ( $results as $row ) {
			$did = (int) $row->device_id;
			if ( ! isset( $map[ $did ] ) ) {
				$map[ $did ] = array();
			}
			$map[ $did ][] = $row;
		}

		return $map;
	}

	// ── Notifications ───────────────────────────────────────────

	/**
	 * Insert a notification record.
	 *
	 * @param array $data Notification data.
	 * @return int|false The notification ID or false.
	 */
	public static function insert_notification( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';
		$now   = current_time( 'mysql' );

		$defaults = array(
			'title'         => '',
			'body'          => '',
			'data'          => null,
			'post_id'       => null,
			'type'          => 'manual',
			'target_type'   => 'all',
			'target_ids'    => null,
			'image_url'     => null,
			'scheduled_at'  => null,
			'total_devices' => 0,
			'total_success' => 0,
			'total_failed'  => 0,
			'status'        => 'pending',
			'ticket_ids'    => null,
			'receipt_data'  => null,
			'sent_by'       => null,
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert( $table, $data, self::get_notification_format( $data ) );

		if ( $wpdb->insert_id ) {
			self::invalidate_notification_cache();
			return (int) $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a notification record.
	 *
	 * @param int   $notification_id The notification ID.
	 * @param array $data            Data to update.
	 * @return bool True on success.
	 */
	public static function update_notification( $notification_id, $data ) {
		global $wpdb;

		$table             = $wpdb->prefix . 'tailsignal_notifications';
		$data['updated_at'] = current_time( 'mysql' );

		$result = (bool) $wpdb->update(
			$table,
			$data,
			array( 'id' => $notification_id ),
			self::get_notification_format( $data ),
			array( '%d' )
		);

		if ( $result ) {
			self::invalidate_notification_cache();
		}

		return $result;
	}

	/**
	 * Get a notification by ID.
	 *
	 * @param int $notification_id The notification ID.
	 * @return object|null The notification row or null.
	 */
	public static function get_notification( $notification_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $notification_id )
		);
	}

	/**
	 * Get notifications with pagination and filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array Array with 'items' and 'total'.
	 */
	public static function get_notifications( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'type'     => '',
			'status'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'columns'  => '*',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$values[] = $args['type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'id', 'title', 'type', 'status', 'created_at', 'total_devices' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$offset = ( (int) $args['page'] - 1 ) * (int) $args['per_page'];

		// Get total.
		$count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Get items.
		$select_cols  = '*' === $args['columns'] ? '*' : $args['columns'];
		$query        = "SELECT {$select_cols} FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_values = array_merge( $values, array( (int) $args['per_page'], $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $query, $query_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Get recent notifications.
	 *
	 * @param int $limit Number of notifications.
	 * @return array Array of notification objects.
	 */
	public static function get_recent_notifications( $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get scheduled notifications.
	 *
	 * @return array Array of notification objects.
	 */
	public static function get_scheduled_notifications() {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'scheduled' ORDER BY scheduled_at ASC"
		);
	}

	/**
	 * Get notifications that need receipt checking.
	 *
	 * @return array Array of notification objects.
	 */
	public static function get_pending_receipt_notifications() {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'sent' AND ticket_ids IS NOT NULL"
		);
	}

	/**
	 * Get notification count by status.
	 *
	 * @return array Associative array of status => count.
	 */
	public static function get_notification_counts_by_status() {
		$cached = get_transient( 'tailsignal_notification_counts_status' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'tailsignal_notifications';
		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
		);

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
		}

		set_transient( 'tailsignal_notification_counts_status', $counts, 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Get total notifications sent this month.
	 *
	 * @return int Notification count.
	 */
	public static function get_monthly_send_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';

		// created_at is stored in site-local time (current_time), so the
		// month boundary must be site-local too, not UTC.
		$start = gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp' ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status IN ('sent', 'receipts_checked') AND created_at >= %s",
				$start
			)
		);
	}

	/**
	 * Get success rate.
	 *
	 * @return float Success rate percentage.
	 */
	public static function get_success_rate() {
		$cached = get_transient( 'tailsignal_success_rate' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';

		$row = $wpdb->get_row(
			"SELECT SUM(total_success) as total_success, SUM(total_devices) as total_devices
			FROM {$table}
			WHERE status IN ('sent', 'receipts_checked') AND total_devices > 0"
		);

		if ( ! $row || ! $row->total_devices ) {
			return 0.0;
		}

		$rate = round( ( (float) $row->total_success / (float) $row->total_devices ) * 100, 1 );

		set_transient( 'tailsignal_success_rate', $rate, 5 * MINUTE_IN_SECONDS );

		return $rate;
	}

	// ── Notification History ────────────────────────────────────

	/**
	 * Link a notification to a post.
	 *
	 * @param int $post_id         The post ID.
	 * @param int $notification_id The notification ID.
	 * @return int|false The history record ID or false.
	 */
	public static function insert_notification_history( $post_id, $notification_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notification_history';

		$wpdb->insert(
			$table,
			array(
				'post_id'         => $post_id,
				'notification_id' => $notification_id,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get notification history for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return array Array of notification objects with history data.
	 */
	public static function get_post_notification_history( $post_id ) {
		global $wpdb;

		$history_table      = $wpdb->prefix . 'tailsignal_notification_history';
		$notifications_table = $wpdb->prefix . 'tailsignal_notifications';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT n.*, nh.created_at as history_created_at
				FROM {$notifications_table} n
				INNER JOIN {$history_table} nh ON n.id = nh.notification_id
				WHERE nh.post_id = %d
				ORDER BY nh.created_at DESC",
				$post_id
			)
		);
	}

	/**
	 * Delete all notifications and notification history.
	 *
	 * @return bool True on success.
	 */
	public static function delete_all_notifications() {
		global $wpdb;

		$prefix = $wpdb->prefix . 'tailsignal_';

		// DELETE (not TRUNCATE) so the transaction is real: TRUNCATE is DDL
		// and implicitly commits, which made the previous rollback illusory.
		$wpdb->query( 'START TRANSACTION' );

		$r1 = $wpdb->query( "DELETE FROM {$prefix}notification_history" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$r2 = $wpdb->query( "DELETE FROM {$prefix}notifications" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$success = false !== $r1 && false !== $r2;

		if ( $success ) {
			$wpdb->query( 'COMMIT' );
			self::invalidate_notification_cache();
		} else {
			$wpdb->query( 'ROLLBACK' );
		}

		return $success;
	}

	/**
	 * Get monthly notification stats for the last N months.
	 *
	 * @param int $months Number of months to retrieve.
	 * @return array Array of objects with month, total, success, failed.
	 */
	public static function get_monthly_notification_stats( $months = 12 ) {
		$cache_key = 'tailsignal_monthly_notification_stats';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_notifications';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(created_at, '%%Y-%%m') as month,
					COUNT(*) as total,
					SUM(total_success) as success,
					SUM(total_failed) as failed
				FROM {$table}
				WHERE created_at >= DATE_SUB(%s, INTERVAL %d MONTH)
					AND status IN ('sent', 'receipts_checked', 'failed')
				GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
				ORDER BY month ASC",
				current_time( 'mysql' ),
				$months
			)
		);

		set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

		return $results;
	}

	// ── Export/Import ────────────────────────────────────────────

	/**
	 * Get all devices for export.
	 *
	 * @return array Array of device objects.
	 */
	public static function get_devices_for_export() {
		global $wpdb;

		$table = $wpdb->prefix . 'tailsignal_devices';

		return $wpdb->get_results(
			"SELECT expo_token, device_type, device_model, os_version, app_version,
			        locale, timezone, user_label, is_dev, created_at
			FROM {$table}
			WHERE is_active = 1
			ORDER BY created_at DESC"
		);
	}

	/**
	 * Import devices from parsed CSV data.
	 *
	 * @param array $rows Array of associative arrays.
	 * @return array Import results with 'new', 'updated', 'skipped' counts.
	 */
	public static function import_devices( $rows ) {
		global $wpdb;

		$results = array(
			'new'     => 0,
			'updated' => 0,
			'skipped' => 0,
		);

		$wpdb->query( 'START TRANSACTION' );

		try {
		foreach ( $rows as $row ) {
			if ( empty( $row['expo_token'] ) ) {
				$results['skipped']++;
				continue;
			}

			$token = sanitize_text_field( $row['expo_token'] );

			if ( ! \ExpoSDK\Utils::isExpoPushToken( $token ) ) {
				$results['skipped']++;
				continue;
			}

			$existing = self::get_device_by_token( $token );

			$data = array(
				'expo_token'   => $token,
				'device_type'  => sanitize_text_field( $row['device_type'] ?? '' ),
				'device_model' => sanitize_text_field( $row['device_model'] ?? '' ),
				'os_version'   => sanitize_text_field( $row['os_version'] ?? '' ),
				'app_version'  => sanitize_text_field( $row['app_version'] ?? '' ),
				'locale'       => sanitize_text_field( $row['locale'] ?? '' ),
				'timezone'     => sanitize_text_field( $row['timezone'] ?? '' ),
				'user_label'   => sanitize_text_field( $row['user_label'] ?? '' ),
				'is_dev'       => isset( $row['is_dev'] ) ? (int) $row['is_dev'] : 0,
			);

			$id = self::insert_device( $data );

			if ( $id ) {
				if ( $existing ) {
					$results['updated']++;
				} else {
					$results['new']++;
				}
			} else {
				$results['skipped']++;
			}
		}

		$wpdb->query( 'COMMIT' );
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}

		return $results;
	}
}
