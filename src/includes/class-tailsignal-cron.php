<?php
/**
 * WP-Cron handlers for TailSignal.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_Cron {

	/**
	 * Check receipts for a notification.
	 *
	 * @param int $notification_id The notification ID.
	 */
	public function check_receipts( $notification_id = null ) {
		if ( $notification_id ) {
			$this->check_single_receipt( $notification_id );
			return;
		}

		// Check all pending receipts in a single batch API call.
		$notifications = TailSignal_DB::get_pending_receipt_notifications();
		if ( empty( $notifications ) ) {
			return;
		}

		// Collect all ticket IDs across notifications for one batch call.
		$all_ticket_ids = array();
		$ticket_map     = array(); // ticket_id => notification_id.
		$token_lookup   = array(); // ticket_id => expo push token.
		$send_failed    = array(); // notification_id => failures recorded at send time.
		$ticket_counts  = array(); // notification_id => number of tickets issued.

		foreach ( $notifications as $notification ) {
			$ticket_ids = self::parse_ticket_ids( $notification->ticket_ids, $token_lookup );
			if ( empty( $ticket_ids ) ) {
				continue;
			}
			$send_failed[ $notification->id ]   = (int) $notification->total_failed;
			$ticket_counts[ $notification->id ] = count( $ticket_ids );
			foreach ( $ticket_ids as $ticket_id ) {
				$all_ticket_ids[]         = $ticket_id;
				$ticket_map[ $ticket_id ] = $notification->id;
			}
		}

		if ( empty( $all_ticket_ids ) ) {
			return;
		}

		// Single batch API call for all receipts.
		$receipts = TailSignal_Expo::check_receipts( $all_ticket_ids );

		// Group results by notification.
		$notification_results = array();
		$stale_tokens         = array();

		foreach ( $receipts as $receipt_id => $receipt ) {
			$notif_id = isset( $ticket_map[ $receipt_id ] ) ? $ticket_map[ $receipt_id ] : null;
			if ( ! $notif_id ) {
				continue;
			}
			if ( ! isset( $notification_results[ $notif_id ] ) ) {
				$notification_results[ $notif_id ] = array(
					'success' => 0,
					'failed'  => 0,
					'data'    => array(),
				);
			}

			$notification_results[ $notif_id ]['data'][ $receipt_id ] = $receipt;

			if ( isset( $receipt['status'] ) && 'ok' === $receipt['status'] ) {
				$notification_results[ $notif_id ]['success']++;
			} else {
				$notification_results[ $notif_id ]['failed']++;
				$stale_token = self::resolve_stale_token( $receipt, $receipt_id, $token_lookup );
				if ( $stale_token ) {
					$stale_tokens[] = $stale_token;
				}
			}
		}

		// Deactivate stale tokens in bulk.
		if ( ! empty( $stale_tokens ) ) {
			TailSignal_DB::deactivate_tokens( $stale_tokens );
		}

		// Update each notification with its results. Receipt failures are
		// added to the failures already recorded at send time (invalid tokens,
		// error tickets) instead of overwriting them. Only mark the
		// notification as fully checked when Expo returned a receipt for
		// every ticket; otherwise leave it 'sent' so the remainder is
		// re-checked on a later run.
		foreach ( $notification_results as $notif_id => $result ) {
			$receipts_returned = count( $result['data'] );
			$complete          = isset( $ticket_counts[ $notif_id ] ) && $receipts_returned >= $ticket_counts[ $notif_id ];

			$update = array(
				'total_success' => $result['success'],
				'total_failed'  => ( isset( $send_failed[ $notif_id ] ) ? $send_failed[ $notif_id ] : 0 ) + $result['failed'],
				'receipt_data'  => wp_json_encode( $result['data'] ),
			);

			if ( $complete ) {
				$update['status'] = 'receipts_checked';
			}

			TailSignal_DB::update_notification( $notif_id, $update );
		}
	}

	/**
	 * Parse the stored ticket_ids JSON.
	 *
	 * Supports both the legacy plain list of ticket IDs and the newer
	 * ticket_id => expo_token map (which enables stale-token cleanup from
	 * receipts).
	 *
	 * @param string|null $json         Stored JSON.
	 * @param array       $token_lookup Accumulator: ticket_id => token entries are added here.
	 * @return array List of ticket IDs.
	 */
	private static function parse_ticket_ids( $json, array &$token_lookup ) {
		if ( empty( $json ) ) {
			return array();
		}

		$decoded = json_decode( $json, true );
		if ( empty( $decoded ) || ! is_array( $decoded ) ) {
			return array();
		}

		// Sequential array = legacy list of ticket IDs.
		if ( array_keys( $decoded ) === range( 0, count( $decoded ) - 1 ) ) {
			return array_values( $decoded );
		}

		// Associative = ticket_id => token map.
		foreach ( $decoded as $ticket_id => $token ) {
			$token_lookup[ $ticket_id ] = $token;
		}

		return array_keys( $decoded );
	}

	/**
	 * Resolve the push token to deactivate for a failed receipt.
	 *
	 * @param array  $receipt      Receipt payload.
	 * @param string $receipt_id   Ticket/receipt ID.
	 * @param array  $token_lookup ticket_id => token map.
	 * @return string|null Expo push token, or null when unresolvable.
	 */
	private static function resolve_stale_token( $receipt, $receipt_id, array $token_lookup ) {
		if ( ! isset( $receipt['details']['error'] ) || 'DeviceNotRegistered' !== $receipt['details']['error'] ) {
			return null;
		}

		// Prefer the token Expo echoes back, fall back to the stored map.
		if ( ! empty( $receipt['details']['expoPushToken'] ) ) {
			return $receipt['details']['expoPushToken'];
		}

		return isset( $token_lookup[ $receipt_id ] ) ? $token_lookup[ $receipt_id ] : null;
	}

	/**
	 * Check receipts for a single notification.
	 *
	 * @param int $notification_id The notification ID.
	 */
	private function check_single_receipt( $notification_id ) {
		$notification = TailSignal_DB::get_notification( $notification_id );

		if ( ! $notification || 'sent' !== $notification->status ) {
			return;
		}

		$token_lookup = array();
		$ticket_ids   = self::parse_ticket_ids( $notification->ticket_ids, $token_lookup );
		if ( empty( $ticket_ids ) ) {
			return;
		}

		$receipts = TailSignal_Expo::check_receipts( $ticket_ids );

		$success_count = 0;
		$failed_count  = 0;
		$stale_tokens  = array();

		foreach ( $receipts as $receipt_id => $receipt ) {
			if ( isset( $receipt['status'] ) && 'ok' === $receipt['status'] ) {
				$success_count++;
			} else {
				$failed_count++;

				// Track stale tokens for cleanup.
				$stale_token = self::resolve_stale_token( $receipt, $receipt_id, $token_lookup );
				if ( $stale_token ) {
					$stale_tokens[] = $stale_token;
				}
			}
		}

		// Deactivate stale tokens.
		if ( ! empty( $stale_tokens ) ) {
			TailSignal_DB::deactivate_tokens( $stale_tokens );
		}

		// Update notification with receipt data. Receipt failures add to the
		// failures recorded at send time; only mark fully checked when every
		// ticket got a receipt back.
		$update = array(
			'total_success' => $success_count,
			'total_failed'  => (int) $notification->total_failed + $failed_count,
			'receipt_data'  => wp_json_encode( $receipts ),
		);

		if ( count( $receipts ) >= count( $ticket_ids ) ) {
			$update['status'] = 'receipts_checked';
		}

		TailSignal_DB::update_notification( $notification_id, $update );
	}

	/**
	 * Send a scheduled notification.
	 *
	 * @param int $notification_id The notification ID.
	 */
	public function send_scheduled_notification( $notification_id ) {
		$notification = TailSignal_DB::get_notification( $notification_id );

		if ( ! $notification || 'scheduled' !== $notification->status ) {
			return;
		}

		// Get tokens based on target type.
		$target_ids = ! empty( $notification->target_ids ) ? json_decode( $notification->target_ids, true ) : null;
		$tokens     = TailSignal_DB::get_tokens_by_target( $notification->target_type, $target_ids );

		if ( empty( $tokens ) ) {
			TailSignal_DB::update_notification( $notification_id, array(
				'status'        => 'failed',
				'total_devices' => 0,
			) );
			return;
		}

		$params = array(
			'title'     => $notification->title,
			'body'      => $notification->body,
			'data'      => $notification->data,
			'image_url' => $notification->image_url,
		);

		// Send via Expo.
		$result = TailSignal_Expo::send( $tokens, $params );

		// Update notification record. A send where nothing went out is a
		// failure; store the ticket→token map for receipt-based cleanup.
		TailSignal_DB::update_notification( $notification_id, array(
			'total_devices' => count( $tokens ),
			'total_success' => $result['success_count'],
			'total_failed'  => $result['failed_count'],
			'status'        => ( 0 === $result['success_count'] && empty( $result['ticket_ids'] ) ) ? 'failed' : 'sent',
			'ticket_ids'    => ! empty( $result['ticket_token_map'] )
				? wp_json_encode( $result['ticket_token_map'] )
				: ( ! empty( $result['ticket_ids'] ) ? wp_json_encode( $result['ticket_ids'] ) : null ),
		) );

		// Link to post history if applicable.
		if ( $notification->post_id ) {
			TailSignal_DB::insert_notification_history( $notification->post_id, $notification_id );
		}

		// Schedule receipt check.
		if ( ! empty( $result['ticket_ids'] ) ) {
			wp_schedule_single_event(
				time() + ( 15 * MINUTE_IN_SECONDS ),
				'tailsignal_check_receipts',
				array( $notification_id )
			);
		}
	}
}
