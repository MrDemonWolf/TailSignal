<?php
/**
 * Notification History admin page template.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new TailSignal_History_List_Table();
$table->prepare_items();
?>
<div id="tailsignal-app" class="wrap">
	<!-- Page Header -->
	<div class="tailsignal-page-header tw-flex tw-items-start tw-justify-between">
		<div>
			<h1>
				<span class="tailsignal-page-header-icon"><span class="dashicons dashicons-backup"></span></span>
				<?php esc_html_e( 'Notification History', 'tailsignal' ); ?>
			</h1>
			<p class="tailsignal-page-desc"><?php esc_html_e( 'Browse all past and scheduled notification sends.', 'tailsignal' ); ?></p>
		</div>
		<button type="button" id="tailsignal-delete-all-history" class="button tailsignal-btn-danger">
			<?php esc_html_e( 'Delete All History', 'tailsignal' ); ?>
		</button>
	</div>

	<div class="tailsignal-table-wrap">
		<form method="get">
			<input type="hidden" name="page" value="tailsignal-history" />
			<?php $table->display(); ?>
		</form>
	</div>
</div>
