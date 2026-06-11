<?php
/**
 * Groups admin page.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_Admin_Groups {

	/**
	 * Render the groups page.
	 */
	public function render() {
		$groups  = TailSignal_DB::get_groups_with_counts();
		$devices = TailSignal_DB::get_devices( array( 'per_page' => 999, 'is_active' => 1 ) );

		// Editing a group?
		$editing_group    = null;
		$editing_devices  = array();
		if ( isset( $_GET['edit'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'tailsignal_edit_group' ) ) {
				wp_die( esc_html__( 'Invalid nonce. Please try again.', 'tailsignal' ) );
			}
			$group_id      = intval( $_GET['edit'] );
			$editing_group = TailSignal_DB::get_group( $group_id );
			if ( $editing_group ) {
				$editing_devices = TailSignal_DB::get_group_device_ids( $group_id );
			}
		}

		include TAILSIGNAL_PLUGIN_DIR . 'admin/partials/groups.php';
	}

	/**
	 * Handle AJAX save group.
	 */
	public function handle_save_group() {
		check_ajax_referer( 'tailsignal_nonce', 'nonce' );

		if ( ! current_user_can( 'tailsignal_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tailsignal' ) ) );
		}

		$group_id    = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$device_ids  = isset( $_POST['device_ids'] ) ? array_map( 'intval', (array) $_POST['device_ids'] ) : array();

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Group name is required.', 'tailsignal' ) ) );
		}

		if ( $group_id ) {
			// Update existing.
			TailSignal_DB::update_group( $group_id, array(
				'name'        => $name,
				'description' => $description,
			) );
		} else {
			// Create new.
			$group_id = TailSignal_DB::create_group( array(
				'name'        => $name,
				'description' => $description,
			) );
		}

		if ( ! $group_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save group.', 'tailsignal' ) ) );
		}

		// Set devices.
		TailSignal_DB::set_group_devices( $group_id, $device_ids );

		wp_send_json_success( array(
			'message'  => __( 'Group saved.', 'tailsignal' ),
			'group_id' => $group_id,
		) );
	}

	/**
	 * Handle AJAX delete group.
	 */
	public function handle_delete_group() {
		check_ajax_referer( 'tailsignal_nonce', 'nonce' );

		if ( ! current_user_can( 'tailsignal_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tailsignal' ) ) );
		}

		$group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;

		if ( ! $group_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid group ID.', 'tailsignal' ) ) );
		}

		$result = TailSignal_DB::delete_group( $group_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Group deleted.', 'tailsignal' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete group.', 'tailsignal' ) ) );
		}
	}

}
