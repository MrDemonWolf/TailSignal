<?php
/**
 * Post editor meta box template.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tailsignal-meta-box">
	<?php if ( ! $notified ) : ?>
		<p>
			<label>
				<input type="checkbox" name="tailsignal_notify" value="1" <?php checked( '1', $notify ); ?> />
				<?php esc_html_e( 'Send notification on publish', 'tailsignal' ); ?>
			</label>
		</p>
	<?php else : ?>
		<p class="description" style="color: var(--ts-success, #2d8a4e);">
			&#10003; <?php esc_html_e( 'Auto-notification already sent for this post.', 'tailsignal' ); ?>
		</p>
	<?php endif; ?>

	<p>
		<label>
			<input type="checkbox" name="tailsignal_include_image" value="1" <?php checked( '1', $include_img ); ?> />
			<?php esc_html_e( 'Include featured image', 'tailsignal' ); ?>
		</label>
	</p>

	<hr />

	<h4 style="margin-bottom: 8px; font-weight: 600;"><?php esc_html_e( 'Quick Send', 'tailsignal' ); ?></h4>

	<p>
		<label style="font-size: 12px; font-weight: 500; color: #374151;"><?php esc_html_e( 'Title:', 'tailsignal' ); ?></label>
		<input type="text" name="tailsignal_custom_title" class="widefat tailsignal-quick-title"
			value="<?php echo esc_attr( ! empty( $custom_title ) ? $custom_title : $default_title ); ?>" />
	</p>

	<p>
		<label style="font-size: 12px; font-weight: 500; color: #374151;"><?php esc_html_e( 'Body:', 'tailsignal' ); ?></label>
		<input type="text" name="tailsignal_custom_body" class="widefat tailsignal-quick-body"
			value="<?php echo esc_attr( ! empty( $custom_body ) ? $custom_body : $default_body ); ?>" />
	</p>

	<p>
		<label style="font-size: 12px; font-weight: 500; color: #374151;"><?php esc_html_e( 'Send To:', 'tailsignal' ); ?></label><br />
		<label style="display:block; margin: 3px 0; font-size: 13px;">
			<input type="radio" name="tailsignal_quick_target" value="all" checked />
			<?php esc_html_e( 'All devices', 'tailsignal' ); ?>
		</label>
		<label style="display:block; margin: 3px 0; font-size: 13px;">
			<input type="radio" name="tailsignal_quick_target" value="dev" />
			<?php esc_html_e( 'Dev devices only', 'tailsignal' ); ?>
		</label>
		<?php if ( ! empty( $groups ) ) : ?>
			<label style="display:block; margin: 3px 0; font-size: 13px;">
				<input type="radio" name="tailsignal_quick_target" value="group" />
				<?php esc_html_e( 'Group:', 'tailsignal' ); ?>
				<select name="tailsignal_quick_group_id" class="tailsignal-quick-group-select">
					<?php foreach ( $groups as $group ) : ?>
						<option value="<?php echo esc_attr( $group->id ); ?>"><?php echo esc_html( $group->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>
	</p>

	<?php if ( $dev_mode ) : ?>
		<p class="description" style="color: var(--ts-warning, #b54708); font-size: 12px;">
			&#x26A0;&#xFE0F; <?php esc_html_e( 'Dev Mode is ON — "All devices" will only send to dev devices.', 'tailsignal' ); ?>
		</p>
	<?php endif; ?>

	<p>
		<button type="button" class="button tailsignal-quick-send-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>" style="background: linear-gradient(135deg, #0FACED 0%, #0991d4 100%); border-color: #0880bc; color: #fff; font-weight: 600;">
			<?php esc_html_e( 'Send Now', 'tailsignal' ); ?>
		</button>
		<span class="tailsignal-quick-send-status" style="margin-left: 8px; font-size: 13px;"></span>
	</p>

	<?php if ( ! empty( $history ) ) : ?>
		<hr />
		<h4 style="margin-bottom: 4px; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: #9c9690;"><?php esc_html_e( 'Send History', 'tailsignal' ); ?></h4>
		<ul style="margin: 0; padding: 0; list-style: none;">
			<?php foreach ( $history as $entry ) : ?>
				<li style="font-size: 12px; color: #6b6560; padding: 3px 0; border-bottom: 1px solid #e8e5e1;">
					<?php
					echo esc_html(
						date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->history_created_at ) )
					);
					?>
					&rarr;
					<?php
					printf(
						/* translators: %d: device count */
						esc_html__( '%d devices', 'tailsignal' ),
						$entry->total_devices
					);
					?>
					<?php if ( in_array( $entry->status, array( 'sent', 'receipts_checked' ), true ) ) : ?>
						<span style="color: #2d8a4e;">&#10003;</span>
					<?php else : ?>
						<span style="color: #c4320a;">&#10007;</span>
					<?php endif; ?>
					<span style="color: #9c9690;">(<?php echo esc_html( $entry->type ); ?>)</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
