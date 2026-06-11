<?php
/**
 * Notification builder and sender.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_Notification {

	/**
	 * Handle post status transition for auto-notifications.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       The post object.
	 */
	public function on_post_published( $new_status, $old_status, $post ) {
		// Only trigger on first publish.
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Only for posts (not pages, custom types, etc. unless filtered).
		$allowed_types = apply_filters( 'tailsignal_post_types', array( 'post', 'portfolio' ) );
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return;
		}

		// Check post-type-specific auto-notify setting.
		if ( 'portfolio' === $post->post_type ) {
			$auto_notify = get_option( 'tailsignal_portfolio_auto_notify', '1' );
		} else {
			$auto_notify = get_option( 'tailsignal_auto_notify', '1' );
		}
		if ( '1' !== $auto_notify ) {
			return;
		}

		// Check per-post meta box toggle. On first publish transition_post_status
		// fires before save_post persists the meta box, so prefer the submitted
		// value when the meta box nonce is present in the request.
		$notify = get_post_meta( $post->ID, '_tailsignal_notify', true );
		if ( isset( $_POST['tailsignal_meta_box_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tailsignal_meta_box_nonce'] ) ), 'tailsignal_meta_box' ) ) {
			$notify = isset( $_POST['tailsignal_notify'] ) ? '1' : '0';
		}
		if ( '0' === $notify ) {
			return;
		}

		// Prevent duplicate notifications.
		if ( get_post_meta( $post->ID, '_tailsignal_notified', true ) ) {
			return;
		}

		// Mark as notified BEFORE sending so a slow Expo call plus an editor
		// retry cannot double-send. Cleared below if nothing was sent.
		update_post_meta( $post->ID, '_tailsignal_notified', '1' );

		// Build notification params.
		$params = $this->build_post_notification_params( $post );

		// Get tokens.
		$tokens = TailSignal_DB::get_all_active_tokens();

		if ( empty( $tokens ) ) {
			delete_post_meta( $post->ID, '_tailsignal_notified' );
			return;
		}

		// Send notification.
		$result = self::send_notification( $params, $tokens, 'post', $post->ID );

		if ( ! $result ) {
			delete_post_meta( $post->ID, '_tailsignal_notified' );
		}
	}

	/**
	 * Build notification parameters from a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return array Notification parameters.
	 */
	public function build_post_notification_params( $post ) {
		// Check per-post overrides.
		$custom_title = get_post_meta( $post->ID, '_tailsignal_custom_title', true );
		$custom_body  = get_post_meta( $post->ID, '_tailsignal_custom_body', true );

		// Use post-type-specific templates when no per-post override.
		if ( ! empty( $custom_title ) ) {
			$title = $custom_title;
		} elseif ( 'portfolio' === $post->post_type ) {
			$title = get_option( 'tailsignal_portfolio_default_title', 'New Project: {post_title}' );
		} else {
			$title = get_option( 'tailsignal_default_title', 'New from {site_name}' );
		}

		if ( ! empty( $custom_body ) ) {
			$body = $custom_body;
		} elseif ( 'portfolio' === $post->post_type ) {
			$body = get_option( 'tailsignal_portfolio_default_body', '{post_title} by {author_name}' );
		} else {
			$body = get_option( 'tailsignal_default_body', '{post_title}' );
		}

		// Parse placeholders.
		$title = $this->parse_placeholders( $title, $post );
		$body  = $this->parse_placeholders( $body, $post );

		$params = array(
			'title' => $title,
			'body'  => $body,
			'data'  => wp_json_encode( array(
				'post_id'   => $post->ID,
				'post_type' => $post->post_type,
				'url'       => get_permalink( $post ),
			) ),
		);

		// Featured image.
		$include_image = get_post_meta( $post->ID, '_tailsignal_include_image', true );
		if ( '' === $include_image ) {
			if ( 'portfolio' === $post->post_type ) {
				$include_image = get_option( 'tailsignal_portfolio_use_featured_image', '1' );
			} else {
				$include_image = get_option( 'tailsignal_use_featured_image', '1' );
			}
		}

		if ( '1' === $include_image && has_post_thumbnail( $post->ID ) ) {
			$params['image_url'] = get_the_post_thumbnail_url( $post->ID, 'large' );
		}

		return $params;
	}

	/**
	 * Parse template placeholders.
	 *
	 * @param string  $template The template string.
	 * @param WP_Post $post     The post object.
	 * @return string Parsed string.
	 */
	public function parse_placeholders( $template, $post ) {
		$replacements = array(
			'{post_title}'  => $post->post_title,
			'{site_name}'   => get_bloginfo( 'name' ),
			'{author_name}' => get_the_author_meta( 'display_name', $post->post_author ),
		);

		// Post excerpt: first 20 words of content.
		$content = wp_strip_all_tags( $post->post_content );
		$words   = explode( ' ', $content );
		$excerpt = implode( ' ', array_slice( $words, 0, 20 ) );
		if ( count( $words ) > 20 ) {
			$excerpt .= '...';
		}
		$replacements['{post_excerpt}'] = $excerpt;

		// Primary category.
		$categories = get_the_category( $post->ID );
		$replacements['{category}'] = ! empty( $categories ) ? $categories[0]->name : '';

		// Featured image URL.
		$replacements['{featured_image}'] = has_post_thumbnail( $post->ID )
			? get_the_post_thumbnail_url( $post->ID, 'large' )
			: '';

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$template
		);
	}

	/**
	 * Send a notification and log it.
	 *
	 * @param array       $params      Notification parameters (title, body, data, image_url).
	 * @param array       $tokens      Array of Expo push tokens.
	 * @param string      $type        Notification type: post, manual, scheduled.
	 * @param int|null    $post_id     Associated post ID.
	 * @param string      $target_type Target type: all, dev, group, specific.
	 * @param array|null  $target_ids  Target IDs for group/specific.
	 * @param int|null    $sent_by     WP user ID who sent.
	 * @return int|false Notification ID or false on failure.
	 */
	public static function send_notification( $params, $tokens, $type = 'manual', $post_id = null, $target_type = 'all', $target_ids = null, $sent_by = null ) {
		// Create notification record.
		$notification_id = TailSignal_DB::insert_notification( array(
			'title'         => $params['title'] ?? '',
			'body'          => $params['body'] ?? '',
			'data'          => $params['data'] ?? null,
			'post_id'       => $post_id,
			'type'          => $type,
			'target_type'   => $target_type,
			'target_ids'    => ! empty( $target_ids ) ? wp_json_encode( $target_ids ) : null,
			'image_url'     => $params['image_url'] ?? null,
			'total_devices' => count( $tokens ),
			'status'        => 'pending',
			'sent_by'       => $sent_by,
		) );

		if ( ! $notification_id ) {
			return false;
		}

		// Send via Expo.
		$result = TailSignal_Expo::send( $tokens, $params );

		// Update notification record. A send where nothing went out is a
		// failure, not a success. Store the ticket→token map so receipt
		// checking can deactivate stale tokens later.
		TailSignal_DB::update_notification( $notification_id, array(
			'total_success' => $result['success_count'],
			'total_failed'  => $result['failed_count'],
			'status'        => ( 0 === $result['success_count'] && empty( $result['ticket_ids'] ) ) ? 'failed' : 'sent',
			'ticket_ids'    => ! empty( $result['ticket_token_map'] )
				? wp_json_encode( $result['ticket_token_map'] )
				: ( ! empty( $result['ticket_ids'] ) ? wp_json_encode( $result['ticket_ids'] ) : null ),
		) );

		// Link to post history if applicable.
		if ( $post_id ) {
			TailSignal_DB::insert_notification_history( $post_id, $notification_id );
		}

		// Schedule receipt check in 15 minutes.
		if ( ! empty( $result['ticket_ids'] ) ) {
			wp_schedule_single_event(
				time() + ( 15 * MINUTE_IN_SECONDS ),
				'tailsignal_check_receipts',
				array( $notification_id )
			);
		}

		return $notification_id;
	}

	/**
	 * Schedule a notification for later.
	 *
	 * @param array       $params       Notification parameters.
	 * @param string      $scheduled_at Datetime string for when to send.
	 * @param string      $target_type  Target type.
	 * @param array|null  $target_ids   Target IDs.
	 * @param int|null    $post_id      Associated post ID.
	 * @param int|null    $sent_by      WP user ID.
	 * @return int|false Notification ID or false.
	 */
	public static function schedule_notification( $params, $scheduled_at, $target_type = 'all', $target_ids = null, $post_id = null, $sent_by = null ) {
		$notification_id = TailSignal_DB::insert_notification( array(
			'title'        => $params['title'] ?? '',
			'body'         => $params['body'] ?? '',
			'data'         => $params['data'] ?? null,
			'post_id'      => $post_id,
			'type'         => 'scheduled',
			'target_type'  => $target_type,
			'target_ids'   => ! empty( $target_ids ) ? wp_json_encode( $target_ids ) : null,
			'image_url'    => $params['image_url'] ?? null,
			'scheduled_at' => $scheduled_at,
			'status'       => 'scheduled',
			'sent_by'      => $sent_by,
		) );

		if ( ! $notification_id ) {
			return false;
		}

		// Schedule WP-Cron event. The datetime comes from the admin/REST in
		// site-local time; WordPress pins PHP to UTC, so convert via the site
		// timezone instead of strtotime() or the event fires offset by the
		// site's UTC offset.
		$timestamp = (int) get_gmt_from_date( $scheduled_at, 'U' );

		if ( $timestamp <= 0 ) {
			TailSignal_DB::update_notification( $notification_id, array( 'status' => 'failed' ) );
			return false;
		}

		$scheduled = wp_schedule_single_event(
			$timestamp,
			'tailsignal_send_scheduled',
			array( $notification_id )
		);

		// wp_schedule_single_event() returns false (or WP_Error pre-5.7
		// semantics aside) when the event could not be registered — without a
		// cron event the row would sit in "scheduled" forever.
		if ( false === $scheduled || is_wp_error( $scheduled ) ) {
			TailSignal_DB::update_notification( $notification_id, array( 'status' => 'failed' ) );
			return false;
		}

		return $notification_id;
	}

	/**
	 * Cancel a scheduled notification.
	 *
	 * @param int $notification_id The notification ID.
	 * @return bool True on success.
	 */
	public static function cancel_scheduled( $notification_id ) {
		$notification = TailSignal_DB::get_notification( $notification_id );

		if ( ! $notification || 'scheduled' !== $notification->status ) {
			return false;
		}

		// Unschedule the cron event.
		$timestamp = wp_next_scheduled( 'tailsignal_send_scheduled', array( (int) $notification_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tailsignal_send_scheduled', array( (int) $notification_id ) );
		}

		// Update status to cancelled.
		return TailSignal_DB::update_notification( $notification_id, array(
			'status' => 'cancelled',
		) );
	}
}
