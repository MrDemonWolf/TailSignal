<?php
/**
 * Devices admin page with WP_List_Table.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TailSignal_Devices_List_Table extends WP_List_Table {

	/**
	 * Bulk-loaded device groups map (device_id => groups array).
	 *
	 * @var array
	 */
	private $groups_map = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'device',
			'plural'   => 'devices',
			'ajax'     => false,
		) );
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'user_label'    => __( 'Label', 'tailsignal' ),
			'expo_token'    => __( 'Token', 'tailsignal' ),
			'device_type'   => __( 'Platform', 'tailsignal' ),
			'device_model'  => __( 'Model', 'tailsignal' ),
			'os_version'    => __( 'OS', 'tailsignal' ),
			'app_version'   => __( 'App Ver', 'tailsignal' ),
			'locale'        => __( 'Locale', 'tailsignal' ),
			'last_active_at' => __( 'Last Active', 'tailsignal' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'user_label'     => array( 'user_label', false ),
			'device_type'    => array( 'device_type', false ),
			'last_active_at' => array( 'last_active_at', false ),
		);
	}

	/**
	 * Column cb.
	 *
	 * @param object $item The item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="device_ids[]" value="%d" />', $item->id );
	}

	/**
	 * Column: user_label.
	 *
	 * @param object $item The item.
	 * @return string
	 */
	public function column_user_label( $item ) {
		$label = ! empty( $item->user_label ) ? esc_html( $item->user_label ) : '<em>' . esc_html__( '(no label)', 'tailsignal' ) . '</em>';

		$badges = '';
		if ( $item->is_dev ) {
			$badges .= ' <span class="tailsignal-badge tailsignal-badge-yellow">DEV</span>';
		}
		if ( ! $item->is_active ) {
			$badges .= ' <span class="tailsignal-badge tailsignal-badge-red">' . esc_html__( 'Inactive', 'tailsignal' ) . '</span>';
		}

		// Groups (pre-loaded in bulk via prepare_items).
		$groups = isset( $this->groups_map[ $item->id ] ) ? $this->groups_map[ $item->id ] : array();
		foreach ( $groups as $group ) {
			$badges .= ' <span class="tailsignal-badge tailsignal-badge-blue">' . esc_html( $group->name ) . '</span>';
		}

		// Row actions.
		$actions = array(
			'edit'       => sprintf(
				'<a href="#" class="tailsignal-edit-device" data-id="%d" data-label="%s">%s</a>',
				$item->id,
				esc_attr( $item->user_label ),
				esc_html__( 'Edit Label', 'tailsignal' )
			),
			'toggle_dev' => sprintf(
				'<a href="#" class="tailsignal-toggle-dev" data-id="%d" data-dev="%d">%s</a>',
				$item->id,
				$item->is_dev ? 0 : 1,
				$item->is_dev ? esc_html__( 'Remove Dev', 'tailsignal' ) : esc_html__( 'Mark Dev', 'tailsignal' )
			),
			'delete'     => sprintf(
				'<a href="%s" class="tailsignal-delete-device" onclick="return confirm(\'%s\')">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=tailsignal-devices&action=delete&device_id=' . $item->id ),
					'tailsignal_delete_device_' . $item->id
				),
				esc_js( __( 'Are you sure you want to delete this device?', 'tailsignal' ) ),
				esc_html__( 'Delete', 'tailsignal' )
			),
		);

		return $label . $badges . $this->row_actions( $actions );
	}

	/**
	 * Column: expo_token.
	 *
	 * @param object $item The item.
	 * @return string
	 */
	public function column_expo_token( $item ) {
		$token = $item->expo_token;
		if ( strlen( $token ) > 30 ) {
			$token = substr( $token, 0, 25 ) . '...';
		}
		return '<code class="tailsignal-code">' . esc_html( $token ) . '</code>';
	}

	/**
	 * Column: device_type.
	 *
	 * @param object $item The item.
	 * @return string
	 */
	public function column_device_type( $item ) {
		if ( 'ios' === $item->device_type ) {
			return '<span class="tailsignal-badge tailsignal-badge-gray">iOS</span>';
		} elseif ( 'android' === $item->device_type ) {
			return '<span class="tailsignal-badge tailsignal-badge-green">Android</span>';
		}
		if ( empty( $item->device_type ) ) {
			return '<em>' . esc_html__( '(unknown)', 'tailsignal' ) . '</em>';
		}
		return esc_html( $item->device_type );
	}

	/**
	 * Default column handler.
	 *
	 * @param object $item        The item.
	 * @param string $column_name The column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'device_model':
			case 'os_version':
			case 'app_version':
			case 'locale':
				if ( empty( $item->$column_name ) ) {
					return '<em>' . esc_html__( '(unknown)', 'tailsignal' ) . '</em>';
				}
				return esc_html( $item->$column_name );
			case 'last_active_at':
				if ( empty( $item->last_active_at ) ) {
					return '<em>' . esc_html__( 'Never', 'tailsignal' ) . '</em>';
				}
				return esc_html( human_time_diff( strtotime( $item->last_active_at ), time() ) ) . ' ' . esc_html__( 'ago', 'tailsignal' );
			default:
				return '';
		}
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'tailsignal' ),
		);
	}

	/**
	 * Extra table nav for filters.
	 *
	 * @param string $which Top or bottom.
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_type   = isset( $_REQUEST['device_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['device_type'] ) ) : '';
		$current_dev    = isset( $_REQUEST['is_dev'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['is_dev'] ) ) : '';
		$current_group  = isset( $_REQUEST['group_id'] ) ? intval( $_REQUEST['group_id'] ) : '';
		$groups         = TailSignal_DB::get_all_groups();

		echo '<div class="alignleft actions">';

		// Platform filter.
		echo '<select name="device_type">';
		echo '<option value="">' . esc_html__( 'All Platforms', 'tailsignal' ) . '</option>';
		echo '<option value="ios"' . selected( $current_type, 'ios', false ) . '>iOS</option>';
		echo '<option value="android"' . selected( $current_type, 'android', false ) . '>Android</option>';
		echo '</select>';

		// Dev filter.
		echo '<select name="is_dev">';
		echo '<option value="">' . esc_html__( 'All Devices', 'tailsignal' ) . '</option>';
		echo '<option value="1"' . selected( $current_dev, '1', false ) . '>' . esc_html__( 'Dev Only', 'tailsignal' ) . '</option>';
		echo '<option value="0"' . selected( $current_dev, '0', false ) . '>' . esc_html__( 'Non-Dev', 'tailsignal' ) . '</option>';
		echo '</select>';

		// Group filter.
		if ( ! empty( $groups ) ) {
			echo '<select name="group_id">';
			echo '<option value="">' . esc_html__( 'All Groups', 'tailsignal' ) . '</option>';
			foreach ( $groups as $group ) {
				echo '<option value="' . esc_attr( $group->id ) . '"' . selected( $current_group, $group->id, false ) . '>' . esc_html( $group->name ) . '</option>';
			}
			echo '</select>';
		}

		submit_button( __( 'Filter', 'tailsignal' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;

		$args = array(
			'per_page'    => $per_page,
			'page'        => $this->get_pagenum(),
			'orderby'     => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at',
			'order'       => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC',
			'search'      => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'device_type' => isset( $_REQUEST['device_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['device_type'] ) ) : '',
			'is_dev'      => isset( $_REQUEST['is_dev'] ) && '' !== $_REQUEST['is_dev'] ? sanitize_text_field( wp_unslash( $_REQUEST['is_dev'] ) ) : '',
			'group_id'    => isset( $_REQUEST['group_id'] ) && '' !== $_REQUEST['group_id'] ? intval( $_REQUEST['group_id'] ) : '',
		);

		$result = TailSignal_DB::get_devices( $args );

		$this->items = $result['items'];

		// Bulk-load groups for all devices on this page (1 query vs N).
		if ( ! empty( $this->items ) ) {
			$device_ids       = wp_list_pluck( $this->items, 'id' );
			$this->groups_map = TailSignal_DB::get_devices_groups_bulk( $device_ids );
		}

		$this->set_pagination_args( array(
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => ceil( $result['total'] / $per_page ),
		) );
	}
}

class TailSignal_Admin_Devices {

	/**
	 * Render the devices page.
	 */
	public function render() {
		// Handle single delete action.
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['device_id'] ) ) {
			if ( ! current_user_can( 'tailsignal_manage' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'tailsignal' ) );
			}
			$device_id = intval( $_GET['device_id'] );
			check_admin_referer( 'tailsignal_delete_device_' . $device_id );
			TailSignal_DB::delete_device( $device_id );
			wp_safe_redirect( admin_url( 'admin.php?page=tailsignal-devices&deleted=1' ) );
			exit;
		}

		// Handle bulk actions.
		$bulk_action = '';
		if ( ! empty( $_POST['action'] ) && '-1' !== $_POST['action'] ) {
			$bulk_action = $_POST['action'];
		} elseif ( ! empty( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			$bulk_action = $_POST['action2'];
		}
		if ( 'delete' === $bulk_action && ! empty( $_POST['device_ids'] ) ) {
			if ( ! current_user_can( 'tailsignal_manage' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'tailsignal' ) );
			}
			check_admin_referer( 'bulk-devices' );
			$device_ids = array_map( 'intval', $_POST['device_ids'] );
			TailSignal_DB::bulk_delete_devices( $device_ids );
			wp_safe_redirect( admin_url( 'admin.php?page=tailsignal-devices&deleted=' . count( $device_ids ) ) );
			exit;
		}

		// Summary stats — single query instead of 3 separate COUNTs.
		$device_stats    = TailSignal_DB::get_device_summary_stats();
		$device_count    = $device_stats['total'];
		$platform_counts = array( 'ios' => $device_stats['ios'], 'android' => $device_stats['android'] );
		$dev_count       = $device_stats['dev'];

		include TAILSIGNAL_PLUGIN_DIR . 'admin/partials/devices.php';
	}

	/**
	 * Handle AJAX update device.
	 */
	public function handle_update_device() {
		check_ajax_referer( 'tailsignal_nonce', 'nonce' );

		if ( ! current_user_can( 'tailsignal_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tailsignal' ) ) );
		}

		$device_id  = isset( $_POST['device_id'] ) ? intval( $_POST['device_id'] ) : 0;
		$user_label = isset( $_POST['user_label'] ) ? sanitize_text_field( wp_unslash( $_POST['user_label'] ) ) : '';

		if ( ! $device_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid device ID.', 'tailsignal' ) ) );
		}

		$result = TailSignal_DB::update_device( $device_id, array( 'user_label' => $user_label ) );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Device updated.', 'tailsignal' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update device.', 'tailsignal' ) ) );
		}
	}

	/**
	 * Handle AJAX toggle dev flag.
	 */
	public function handle_toggle_dev() {
		check_ajax_referer( 'tailsignal_nonce', 'nonce' );

		if ( ! current_user_can( 'tailsignal_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tailsignal' ) ) );
		}

		$device_id = isset( $_POST['device_id'] ) ? intval( $_POST['device_id'] ) : 0;
		$is_dev    = isset( $_POST['is_dev'] ) ? intval( $_POST['is_dev'] ) : 0;

		if ( ! $device_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid device ID.', 'tailsignal' ) ) );
		}

		$result = TailSignal_DB::update_device( $device_id, array( 'is_dev' => $is_dev ) );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => $is_dev
					? __( 'Device marked as dev.', 'tailsignal' )
					: __( 'Device removed from dev.', 'tailsignal' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update device.', 'tailsignal' ) ) );
		}
	}
}
