<?php
/**
 * Settings admin page using WordPress Settings API.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_Admin_Settings {

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// General section.
		add_settings_section(
			'tailsignal_general',
			__( 'General', 'tailsignal' ),
			array( $this, 'render_general_section' ),
			'tailsignal-settings'
		);

		register_setting( 'tailsignal_settings', 'tailsignal_dev_mode', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => '0',
		) );

		add_settings_field(
			'tailsignal_dev_mode',
			__( 'Dev Mode', 'tailsignal' ),
			array( $this, 'render_toggle_field' ),
			'tailsignal-settings',
			'tailsignal_general',
			array(
				'name'        => 'tailsignal_dev_mode',
				'description' => __( 'When ON, notifications only go to devices flagged as "dev". Use this for testing.', 'tailsignal' ),
			)
		);

		register_setting( 'tailsignal_settings', 'tailsignal_auto_notify', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => '1',
		) );

		add_settings_field(
			'tailsignal_auto_notify',
			__( 'Auto-notify on new posts', 'tailsignal' ),
			array( $this, 'render_toggle_field' ),
			'tailsignal-settings',
			'tailsignal_general',
			array(
				'name'        => 'tailsignal_auto_notify',
				'description' => __( 'Automatically send a push notification when a post is published.', 'tailsignal' ),
			)
		);

		register_setting( 'tailsignal_settings', 'tailsignal_expo_access_token', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		add_settings_field(
			'tailsignal_expo_access_token',
			__( 'Expo Access Token', 'tailsignal' ),
			array( $this, 'render_text_field' ),
			'tailsignal-settings',
			'tailsignal_general',
			array(
				'name'        => 'tailsignal_expo_access_token',
				'description' => __( 'Optional. Get from expo.dev dashboard.', 'tailsignal' ),
				'type'        => 'password',
			)
		);

		// Notification Templates section.
		add_settings_section(
			'tailsignal_templates',
			__( 'Notification Templates', 'tailsignal' ),
			array( $this, 'render_templates_section' ),
			'tailsignal-settings'
		);

		register_setting( 'tailsignal_settings', 'tailsignal_default_title', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'New from {site_name}',
		) );

		add_settings_field(
			'tailsignal_default_title',
			__( 'Default Title', 'tailsignal' ),
			array( $this, 'render_text_field' ),
			'tailsignal-settings',
			'tailsignal_templates',
			array(
				'name' => 'tailsignal_default_title',
			)
		);

		register_setting( 'tailsignal_settings', 'tailsignal_default_body', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '{post_title}',
		) );

		add_settings_field(
			'tailsignal_default_body',
			__( 'Default Body', 'tailsignal' ),
			array( $this, 'render_text_field' ),
			'tailsignal-settings',
			'tailsignal_templates',
			array(
				'name' => 'tailsignal_default_body',
			)
		);

		register_setting( 'tailsignal_settings', 'tailsignal_use_featured_image', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => '1',
		) );

		add_settings_field(
			'tailsignal_use_featured_image',
			__( 'Include Featured Image', 'tailsignal' ),
			array( $this, 'render_toggle_field' ),
			'tailsignal-settings',
			'tailsignal_templates',
			array(
				'name'        => 'tailsignal_use_featured_image',
				'description' => __( 'Sends post featured image as rich notification on iOS and Android.', 'tailsignal' ),
			)
		);

		// Portfolio Templates section.
		add_settings_section(
			'tailsignal_portfolio_templates',
			__( 'Portfolio Templates', 'tailsignal' ),
			array( $this, 'render_portfolio_templates_section' ),
			'tailsignal-settings'
		);

		register_setting( 'tailsignal_settings', 'tailsignal_portfolio_auto_notify', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => '1',
		) );

		add_settings_field(
			'tailsignal_portfolio_auto_notify',
			__( 'Auto-notify on new portfolio', 'tailsignal' ),
			array( $this, 'render_toggle_field' ),
			'tailsignal-settings',
			'tailsignal_portfolio_templates',
			array(
				'name'        => 'tailsignal_portfolio_auto_notify',
				'description' => __( 'Automatically send a push notification when a portfolio item is published.', 'tailsignal' ),
			)
		);

		register_setting( 'tailsignal_settings', 'tailsignal_portfolio_default_title', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'New Project: {post_title}',
		) );

		add_settings_field(
			'tailsignal_portfolio_default_title',
			__( 'Default Title', 'tailsignal' ),
			array( $this, 'render_text_field' ),
			'tailsignal-settings',
			'tailsignal_portfolio_templates',
			array(
				'name' => 'tailsignal_portfolio_default_title',
			)
		);

		register_setting( 'tailsignal_settings', 'tailsignal_portfolio_default_body', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '{post_title} by {author_name}',
		) );

		add_settings_field(
			'tailsignal_portfolio_default_body',
			__( 'Default Body', 'tailsignal' ),
			array( $this, 'render_text_field' ),
			'tailsignal-settings',
			'tailsignal_portfolio_templates',
			array(
				'name' => 'tailsignal_portfolio_default_body',
			)
		);

		register_setting( 'tailsignal_settings', 'tailsignal_portfolio_use_featured_image', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => '1',
		) );

		add_settings_field(
			'tailsignal_portfolio_use_featured_image',
			__( 'Include Featured Image', 'tailsignal' ),
			array( $this, 'render_toggle_field' ),
			'tailsignal-settings',
			'tailsignal_portfolio_templates',
			array(
				'name'        => 'tailsignal_portfolio_use_featured_image',
				'description' => __( 'Sends portfolio featured image as rich notification on iOS and Android.', 'tailsignal' ),
			)
		);
	}

	/**
	 * Render general section description.
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general TailSignal settings.', 'tailsignal' ) . '</p>';
	}

	/**
	 * Render templates section description.
	 */
	public function render_templates_section() {
		echo '<p>' . esc_html__( 'These templates are used for auto-publish notifications. Each post can override them in the TailSignal meta box.', 'tailsignal' ) . '</p>';
		echo '<p class="description">';
		echo esc_html__( 'Available placeholders:', 'tailsignal' ) . ' ';
		echo '<code>{post_title}</code> <code>{post_excerpt}</code> <code>{site_name}</code> <code>{author_name}</code> <code>{category}</code>';
		echo '</p>';
	}

	/**
	 * Render portfolio templates section description.
	 */
	public function render_portfolio_templates_section() {
		echo '<p>' . esc_html__( 'These templates are used for auto-publish notifications on portfolio items.', 'tailsignal' ) . '</p>';
		echo '<p class="description">';
		echo esc_html__( 'Available placeholders:', 'tailsignal' ) . ' ';
		echo '<code>{post_title}</code> <code>{post_excerpt}</code> <code>{site_name}</code> <code>{author_name}</code> <code>{category}</code>';
		echo '</p>';
	}

	/**
	 * Render a text input field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$name  = $args['name'];
		$type  = $args['type'] ?? 'text';
		$value = get_option( $name );

		printf(
			'<input type="%s" name="%s" value="%s" class="regular-text" />',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render a toggle switch field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_toggle_field( $args ) {
		$name    = $args['name'];
		$checked = '1' === get_option( $name );

		echo '<label class="tailsignal-toggle">';
		printf(
			'<input type="checkbox" name="%s" value="1" %s />',
			esc_attr( $name ),
			checked( $checked, true, false )
		);
		echo '<span class="tailsignal-toggle-slider"></span>';
		echo '</label>';

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Sanitize checkbox value.
	 *
	 * @param mixed $value The value.
	 * @return string '1' or '0'.
	 */
	public function sanitize_checkbox( $value ) {
		return $value ? '1' : '0';
	}
}
