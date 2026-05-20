<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks GitHub releases and surfaces updates through the standard WordPress plugin update flow.
 * The GitHub Actions workflow (/.github/workflows/release.yml) creates a properly-structured
 * bricks-builder-mcp.zip asset on every push to main — this class reads that release.
 */
class Updater {

	private const GITHUB_REPO  = 'yasirshabbirservices/Bricks-Builder-MCP';
	private const PLUGIN_SLUG  = 'bricks-builder-mcp/bricks-builder-mcp.php';
	private const CACHE_KEY    = 'bmcp_github_release';
	private const CACHE_TTL    = 15 * MINUTE_IN_SECONDS;

	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
		add_action( 'upgrader_process_complete',             [ $this, 'purge_cache' ], 10, 2 );
		add_action( 'admin_init',                            [ $this, 'maybe_force_update_check' ] );
	}

	// -------------------------------------------------------------------------
	// WordPress hooks
	// -------------------------------------------------------------------------

	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release->tag_name, 'v' );
		if ( version_compare( BMCP_VERSION, $remote_version, '<' ) ) {
			$download = $this->get_download_url( $release );
			if ( $download ) {
				$transient->response[ self::PLUGIN_SLUG ] = (object) [
					'slug'         => 'bricks-builder-mcp',
					'plugin'       => self::PLUGIN_SLUG,
					'new_version'  => $remote_version,
					'url'          => $release->html_url,
					'package'      => $download,
					'requires_php' => '8.0',
					'tested'       => get_bloginfo( 'version' ),
				];
			}
		}

		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || ( $args->slug ?? '' ) !== 'bricks-builder-mcp' ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'             => 'Bricks Builder MCP',
			'slug'             => 'bricks-builder-mcp',
			'version'          => ltrim( $release->tag_name, 'v' ),
			'author'           => '<a href="https://yasirshabbir.com">Yasir Shabbir</a>',
			'homepage'         => 'https://github.com/' . self::GITHUB_REPO,
			'requires_php'     => '8.0',
			'tested'           => get_bloginfo( 'version' ),
			'last_updated'     => $release->published_at ?? '',
			'short_description' => 'MCP server for Bricks Builder — lets Claude Code and any MCP-compatible AI build and design your site directly.',
			'sections'         => [
				'changelog' => $this->format_release_notes( $release->body ?? '' ),
			],
			'download_link'    => $this->get_download_url( $release ),
		];
	}

	public function purge_cache( $upgrader, $options ): void {
		if ( ( $options['action'] ?? '' ) === 'update' && ( $options['type'] ?? '' ) === 'plugin' ) {
			delete_transient( self::CACHE_KEY );
		}
	}

	/**
	 * When our GitHub release cache expires, force WordPress to re-check for plugin
	 * updates on the next admin page load instead of waiting up to 12 hours.
	 */
	public function maybe_force_update_check(): void {
		if ( get_transient( self::CACHE_KEY ) === false ) {
			delete_site_transient( 'update_plugins' );
		}
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	private function get_latest_release(): ?object {
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached !== false ) {
			return $cached ?: null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; BricksMCP',
				],
			]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( self::CACHE_KEY, '', self::CACHE_TTL );
			return null;
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ) );
		$release = ( $body && isset( $body->tag_name ) ) ? $body : null;

		set_transient( self::CACHE_KEY, $release ?: '', self::CACHE_TTL );
		return $release;
	}

	private function get_download_url( object $release ): string {
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( $asset->name === 'bricks-builder-mcp.zip' ) {
					return $asset->browser_download_url;
				}
			}
		}
		// Return empty string — don't fall back to zipball_url which has wrong directory structure.
		return '';
	}

	private function format_release_notes( string $md ): string {
		// Minimal markdown → HTML (enough for WP's plugin info modal).
		$md = esc_html( $md );
		$md = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $md );
		$md = preg_replace( '/^## (.+)$/m',  '<h2>$1</h2>', $md );
		$md = preg_replace( '/^- (.+)$/m',   '<li>$1</li>', $md );
		$md = preg_replace( '/`([^`]+)`/',    '<code>$1</code>', $md );
		return nl2br( $md );
	}
}
