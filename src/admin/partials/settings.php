<?php
/**
 * Settings admin page template.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="tailsignal-app" class="wrap">
	<!-- Page Header -->
	<div class="tailsignal-page-header">
		<h1>
			<span class="tailsignal-page-header-icon"><span class="dashicons dashicons-admin-generic"></span></span>
			<?php esc_html_e( 'Settings', 'tailsignal' ); ?>
		</h1>
		<p class="tailsignal-page-desc"><?php esc_html_e( 'Configure TailSignal behavior, templates, and integrations.', 'tailsignal' ); ?></p>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'tailsignal_settings' ); ?>

		<?php
		global $wp_settings_sections, $wp_settings_fields;
		$page = 'tailsignal-settings';
		if ( isset( $wp_settings_sections[ $page ] ) ) {
			foreach ( $wp_settings_sections[ $page ] as $section ) {
				echo '<div class="tailsignal-settings-section">';
				if ( $section['title'] ) {
					echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
				}
				if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
				}
				if ( isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
					echo '<table class="form-table" role="presentation">';
					do_settings_fields( $page, $section['id'] );
					echo '</table>';
				}
				echo '</div>';
			}
		}
		?>

		<div class="tailsignal-settings-submit">
			<?php submit_button( __( 'Save Settings', 'tailsignal' ), 'tailsignal-btn-brand', 'submit', false ); ?>
		</div>
	</form>

	<!-- Data Management -->
	<div class="tw-mt-6">
		<div class="tailsignal-card">
			<div class="tailsignal-card-header">
				<h2><?php esc_html_e( 'Data Management', 'tailsignal' ); ?></h2>
			</div>
			<div class="tailsignal-card-body">
				<p class="tailsignal-settings-helper"><?php esc_html_e( 'Export all registered devices as a CSV, or import devices from a CSV file.', 'tailsignal' ); ?></p>
				<div class="tw-flex tw-gap-3">
					<a href="<?php echo esc_url( wp_nonce_url( rest_url( 'tailsignal/v1/devices/export' ), 'wp_rest', '_wpnonce' ) ); ?>" class="button">
						<span class="dashicons dashicons-download" style="font-size:16px;width:16px;height:16px;margin-right:4px;vertical-align:middle;margin-top:-2px;"></span>
						<?php esc_html_e( 'Export Devices (CSV)', 'tailsignal' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tailsignal-devices' ) ); ?>" class="button">
						<span class="dashicons dashicons-upload" style="font-size:16px;width:16px;height:16px;margin-right:4px;vertical-align:middle;margin-top:-2px;"></span>
						<?php esc_html_e( 'Import Devices (CSV)', 'tailsignal' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- About -->
	<div class="tw-mt-4">
		<div class="tailsignal-about-card">
			<div class="tailsignal-about-card-logo">
				<span class="dashicons dashicons-bell"></span>
			</div>
			<div class="tailsignal-about-card-info">
				<strong>TailSignal</strong>
				<span class="tailsignal-about-card-version">v<?php echo esc_html( TAILSIGNAL_VERSION ); ?></span>
				<span class="tailsignal-about-card-sep">&bull;</span>
				<a href="https://github.com/mrdemonwolf/TailSignal/releases" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Changelog', 'tailsignal' ); ?>
				</a>
				<span class="tailsignal-about-card-sep">&bull;</span>
				<a href="https://github.com/mrdemonwolf/TailSignal/issues" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Report Issue', 'tailsignal' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
