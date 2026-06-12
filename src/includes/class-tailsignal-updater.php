<?php
/**
 * GitHub-based plugin updater for TailSignal.
 *
 * Checks the GitHub Releases API for new versions and integrates with
 * the WordPress plugin update system so admins see updates in the
 * standard "Plugins" screen.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailSignal_Updater {

	const GITHUB_REPO   = 'mrdemonwolf/TailSignal';
	const TRANSIENT_KEY = 'tailsignal_github_update';
	const CACHE_SECS    = 43200; // 12 hours.

	/** @var string Absolute path to the main plugin file. */
	private $plugin_file;

	/** @var string plugin-folder/plugin-file.php slug. */
	private $plugin_slug;

	/** @var string Currently installed version. */
	private $current_version;

	/**
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_slug     = plugin_basename( $plugin_file );
		$this->current_version = TAILSIGNAL_VERSION;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'after_upgrade' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Update injection
	// -------------------------------------------------------------------------

	/**
	 * Inject update data into the WP update transient when a newer GitHub
	 * release is available.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		if ( version_compare( $this->current_version, $release['version'], '<' ) ) {
			$transient->response[ $this->plugin_slug ] = (object) array(
				'id'           => 'github.com/' . self::GITHUB_REPO,
				'slug'         => dirname( $this->plugin_slug ),
				'plugin'       => $this->plugin_slug,
				'new_version'  => $release['version'],
				'url'          => $release['html_url'],
				'package'      => $release['zip_url'],
				'icons'        => array(),
				'banners'      => array(),
				'tested'       => '',
				'requires'     => '6.0',
				'requires_php' => '7.4',
			);
		} else {
			// Mark as up-to-date so WP doesn't falsely flag it as unknown.
			$transient->no_update[ $this->plugin_slug ] = (object) array(
				'id'          => 'github.com/' . self::GITHUB_REPO,
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $this->current_version,
				'url'         => 'https://github.com/' . self::GITHUB_REPO,
				'package'     => '',
			);
		}

		return $transient;
	}

	// -------------------------------------------------------------------------
	// "View details" popup
	// -------------------------------------------------------------------------

	/**
	 * Provide plugin details for the "View details" thickbox popup.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( dirname( $this->plugin_slug ) !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'TailSignal',
			'slug'          => dirname( $this->plugin_slug ),
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/mrdemonwolf">MrDemonWolf, Inc.</a>',
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'downloaded'    => 0,
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => '<p>Self-hosted WordPress push notifications via Expo. Own your data, skip third-party services.</p>',
				'changelog'   => $release['body'] ?: '<p>See the <a href="' . esc_url( $release['html_url'] ) . '">GitHub release</a> for details.</p>',
			),
			'download_link' => $release['zip_url'],
		);
	}

	// -------------------------------------------------------------------------
	// Post-upgrade cache clear
	// -------------------------------------------------------------------------

	/**
	 * Delete the cached release after any plugin upgrade so the next check
	 * always hits GitHub fresh.
	 *
	 * @param WP_Upgrader $upgrader
	 * @param array       $hook_extra
	 */
	public function after_upgrade( $upgrader, $hook_extra ) {
		if (
			isset( $hook_extra['type'], $hook_extra['action'] ) &&
			'plugin' === $hook_extra['type'] &&
			'update' === $hook_extra['action']
		) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}

	// -------------------------------------------------------------------------
	// Plugin row meta
	// -------------------------------------------------------------------------

	/**
	 * Add "View on GitHub" link to the plugin row on the Plugins screen.
	 *
	 * @param array  $links
	 * @param string $file  Plugin basename.
	 * @return array
	 */
	public function add_row_meta( $links, $file ) {
		if ( $this->plugin_slug !== $file ) {
			return $links;
		}

		$links[] = '<a href="https://github.com/' . self::GITHUB_REPO . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'View on GitHub', 'tailsignal' ) . '</a>';

		return $links;
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	/**
	 * Fetch and cache the latest GitHub release.
	 *
	 * Returns false on failure (network error, unexpected response, etc.).
	 *
	 * @return array|false {
	 *     @type string version      Semver string without leading "v".
	 *     @type string html_url     Human-readable release page URL.
	 *     @type string zip_url      Downloadable ZIP URL.
	 *     @type string published_at Formatted publish date.
	 *     @type string body         Release notes (HTML-safe).
	 * }
	 */
	private function get_latest_release() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$api_url  = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
		$response = wp_remote_get( $api_url, array(
			'timeout'    => 10,
			'user-agent' => 'TailSignal-Updater/' . $this->current_version . '; WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			'headers'    => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Short cache on failure so we don't hammer the API.
			set_transient( self::TRANSIENT_KEY, false, 300 );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['tag_name'] ) ) {
			set_transient( self::TRANSIENT_KEY, false, 300 );
			return false;
		}

		// Prefer the named build artifact; fall back to GitHub's auto-generated ZIP.
		$zip_url = '';
		if ( ! empty( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && 'tailsignal.zip' === $asset['name'] ) {
					$zip_url = $asset['browser_download_url'];
					break;
				}
			}
		}
		if ( ! $zip_url && ! empty( $data['zipball_url'] ) ) {
			$zip_url = $data['zipball_url'];
		}

		$release = array(
			'version'      => ltrim( $data['tag_name'], 'vV' ),
			'html_url'     => $data['html_url'] ?? '',
			'zip_url'      => $zip_url,
			'published_at' => ! empty( $data['published_at'] )
				? date_i18n( get_option( 'date_format' ), strtotime( $data['published_at'] ) )
				: '',
			'body'         => ! empty( $data['body'] ) ? wp_kses_post( $data['body'] ) : '',
		);

		set_transient( self::TRANSIENT_KEY, $release, self::CACHE_SECS );

		return $release;
	}
}
