<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Tool_Base {

	/**
	 * Return an array of MCP tool definition objects.
	 * Each entry: { name, description, inputSchema }
	 */
	abstract public function define(): array;

	/**
	 * Execute the named tool with the given arguments.
	 *
	 * @param string $name Tool name
	 * @param array  $args Decoded arguments from MCP client
	 * @return array|\WP_Error Result data or error
	 */
	abstract public function execute( string $name, array $args ): array|\WP_Error;

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	protected function success( $data ): array {
		return is_array( $data ) ? $data : [ 'result' => $data ];
	}

	protected function err( string $message, string $code = 'bmcp_error' ): \WP_Error {
		return new \WP_Error( $code, $message );
	}

	protected function require_cap( string $capability ): ?\WP_Error {
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'bmcp_forbidden',
				"Capability '{$capability}' is required for this operation."
			);
		}
		return null;
	}

	protected function int_arg( array $args, string $key, ?int $default = null ): ?int {
		if ( isset( $args[ $key ] ) ) {
			return (int) $args[ $key ];
		}
		return $default;
	}

	protected function str_arg( array $args, string $key, string $default = '' ): string {
		return isset( $args[ $key ] ) ? (string) $args[ $key ] : $default;
	}

	protected function bool_arg( array $args, string $key, bool $default = false ): bool {
		if ( isset( $args[ $key ] ) ) {
			return (bool) $args[ $key ];
		}
		return $default;
	}

	protected function arr_arg( array $args, string $key, array $default = [] ): array {
		if ( isset( $args[ $key ] ) && is_array( $args[ $key ] ) ) {
			return $args[ $key ];
		}
		return $default;
	}

	protected function page_url( int $post_id ): string {
		$url = get_permalink( $post_id );
		return $url ?: '';
	}

	protected function edit_url( int $post_id ): string {
		return (string) get_edit_post_link( $post_id, 'raw' );
	}
}
