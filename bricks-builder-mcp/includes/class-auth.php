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
	 * Validate the API key from a REST request.
	 * On success, sets the current WordPress user to the stored admin ID.
	 *
	 * @param \WP_REST_Request $request
	 * @return true|\WP_Error
	 */
	public static function validate( \WP_REST_Request $request ) {
		$stored = self::get_key();

		if ( empty( $stored ) ) {
			return new \WP_Error(
				'bmcp_no_key',
				'MCP server has no API key configured. Please activate the Bricks Builder MCP plugin.',
				[ 'status' => 503 ]
			);
		}

		$provided = self::extract_key( $request );

		if ( $provided === null || ! hash_equals( $stored, $provided ) ) {
			return new \WP_Error(
				'bmcp_auth_failed',
				'Invalid or missing API key. Provide it as: Authorization: Bearer YOUR_KEY',
				[ 'status' => 401 ]
			);
		}

		// Set current user so capability checks work in tool handlers
		$admin_id = (int) get_option( BMCP_ADMIN_USER_OPTION, 1 );
		wp_set_current_user( $admin_id );

		return true;
	}

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
