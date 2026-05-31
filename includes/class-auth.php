<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Auth {

	public static function get_key(): string {
		return (string) get_option( BMCP_API_KEY_OPTION, '' );
	}

	public static function generate_key(): string {
		$key = wp_generate_password( 32, false );
		update_option( BMCP_API_KEY_OPTION, $key, false );
		return $key;
	}

	public static function regenerate_key(): string {
		return self::generate_key();
	}

	/**
	 * Scopes granted to the matched secondary key for this request.
	 * Empty = primary key matched (full access).
	 *
	 * @var string[]
	 */
	public static array $current_scopes = [];

	/**
	 * Validate the API key from a REST request.
	 * On success, sets the current WordPress user to the stored admin ID
	 * and populates $current_scopes if a secondary key matched.
	 *
	 * @param \WP_REST_Request $request
	 * @return true|\WP_Error
	 */
	public static function validate( \WP_REST_Request $request ) {
		self::$current_scopes = [];

		$stored = self::get_key();

		if ( empty( $stored ) ) {
			return new \WP_Error(
				'bmcp_no_key',
				'MCP server has no API key configured. Please activate the Bricks Builder MCP plugin.',
				[ 'status' => 503 ]
			);
		}

		$provided = self::extract_key( $request );
		if ( $provided === null ) {
			return new \WP_Error(
				'bmcp_auth_failed',
				'Invalid or missing API key. Provide it as: Authorization: Bearer YOUR_KEY',
				[ 'status' => 401 ]
			);
		}

		$admin_id    = (int) get_option( BMCP_ADMIN_USER_OPTION, 1 );
		$matched_key = null;
		$matched_scopes = [];

		// ── Primary key (full access) ──────────────────────────────────────
		if ( hash_equals( $stored, $provided ) ) {
			$matched_key    = $stored;
			$matched_scopes = []; // empty = full access
		}

		// ── Secondary keys (scoped access) ────────────────────────────────
		if ( $matched_key === null ) {
			$secondary_keys = get_option( BMCP_SECONDARY_KEYS_OPTION, [] );
			if ( is_array( $secondary_keys ) ) {
				foreach ( $secondary_keys as $sk ) {
					$sk_key = $sk['key'] ?? '';
					if ( $sk_key && hash_equals( $sk_key, $provided ) ) {
						$matched_key    = $sk_key;
						$matched_scopes = $sk['scopes'] ?? [ 'read' ];
						break;
					}
				}
			}
		}

		if ( $matched_key === null ) {
			return new \WP_Error(
				'bmcp_auth_failed',
				'Invalid or missing API key. Provide it as: Authorization: Bearer YOUR_KEY',
				[ 'status' => 401 ]
			);
		}

		// ── HMAC validation applies to ALL matched keys when required ─────
		if ( self::is_hmac_required() ) {
			$hmac_err = self::validate_hmac( $request, $matched_key );
			if ( $hmac_err ) return $hmac_err;
		}

		wp_set_current_user( $admin_id );
		self::$current_scopes = $matched_scopes;

		return true;
	}

	/**
	 * Check whether the current request has a given permission scope.
	 * Returns true if the primary key was used (no scope restrictions).
	 */
	public static function has_scope( string $scope ): bool {
		if ( empty( self::$current_scopes ) ) {
			return true; // primary key — full access
		}

		// Scope hierarchy: admin > delete > write > read
		$hierarchy = [ 'read' => 0, 'write' => 1, 'delete' => 2, 'admin' => 3 ];
		$required  = $hierarchy[ $scope ] ?? 0;

		foreach ( self::$current_scopes as $granted ) {
			if ( ( $hierarchy[ $granted ] ?? 0 ) >= $required ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Derive the required scope for a tool from its name.
	 * No per-tool annotations needed — pattern matching is sufficient.
	 */
	public static function scope_for_tool( string $tool_name ): string {
		static $delete_patterns = [ '_delete_', 'bricks_snapshot_restore' ];
		static $write_patterns  = [
			'_create_', '_update_', '_write_', '_set_', '_import_',
			'_replace_', '_clear_', '_enable_', '_disable_', '_commit_',
			'_discard_', '_restore', '_save', '_memory_save', '_memory_delete',
		];

		foreach ( $delete_patterns as $p ) {
			if ( $p[0] === 'b' ? $tool_name === $p : str_contains( $tool_name, $p ) ) return 'delete';
		}
		if ( $tool_name === 'bricks_snapshot_restore' ) return 'delete';

		foreach ( $write_patterns as $p ) {
			if ( str_contains( $tool_name, $p ) ) return 'write';
		}

		return 'read';
	}

	// ── HMAC signing ──────────────────────────────────────────────────────────

	private static function is_hmac_required(): bool {
		$adv = get_option( BMCP_ADVANCED_OPTION, [] );
		return ! empty( $adv['require_hmac'] );
	}

	private static function validate_hmac( \WP_REST_Request $request, string $key ): ?\WP_Error {
		$sig_header = $request->get_header( 'X-Bmcp-Signature' );
		$ts_header  = $request->get_header( 'X-Bmcp-Timestamp' );

		if ( ! $sig_header || ! $ts_header ) {
			return new \WP_Error(
				'bmcp_hmac_missing',
				'HMAC signing is required. Include X-BMCP-Signature and X-BMCP-Timestamp headers.',
				[ 'status' => 401 ]
			);
		}

		// Reject requests outside the 5-minute window
		if ( abs( time() - (int) $ts_header ) > 300 ) {
			return new \WP_Error(
				'bmcp_hmac_expired',
				'Request timestamp is outside the 5-minute validity window. Check your system clock.',
				[ 'status' => 401 ]
			);
		}

		$body     = $request->get_body();
		$expected = 'sha256=' . hash_hmac( 'sha256', $ts_header . '.' . $body, $key );

		if ( ! hash_equals( $expected, $sig_header ) ) {
			return new \WP_Error(
				'bmcp_hmac_invalid',
				'HMAC signature is invalid.',
				[ 'status' => 401 ]
			);
		}

		return null;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function extract_key( \WP_REST_Request $request ): ?string {
		// Standard Bearer token
		$auth_header = $request->get_header( 'Authorization' );
		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', trim( $auth_header ), $m ) ) {
			return $m[1];
		}

		// Fallback: x-api-key header
		$api_key_header = $request->get_header( 'X-Api-Key' );
		if ( $api_key_header ) {
			return trim( $api_key_header );
		}

		return null;
	}

	public static function masked_key(): string {
		$key = self::get_key();
		if ( strlen( $key ) <= 8 ) {
			return str_repeat( '*', strlen( $key ) );
		}
		return str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -8 );
	}
}
