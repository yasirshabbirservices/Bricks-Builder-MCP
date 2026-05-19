<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCP_Server {

	private Tools_Registry $registry;

	public function __construct() {
		$this->registry = new Tools_Registry();
		$this->registry->load_all();
	}

	public function dispatch( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return $this->error_response( null, -32700, 'Parse error: body must be JSON object' );
		}

		// Batch requests are not supported
		if ( isset( $body[0] ) ) {
			return $this->error_response( null, -32600, 'Batch requests are not supported' );
		}

		if ( ! isset( $body['jsonrpc'] ) || $body['jsonrpc'] !== '2.0' ) {
			return $this->error_response( null, -32600, 'Invalid Request: jsonrpc must be "2.0"' );
		}

		$id     = $body['id'] ?? null;
		$method = $body['method'] ?? null;
		$params = $body['params'] ?? [];

		if ( ! is_string( $method ) || $method === '' ) {
			return $this->error_response( $id, -32600, 'Invalid Request: method is required' );
		}

		switch ( $method ) {
			case 'initialize':
				$result = $this->handle_initialize( $params );
				break;

			case 'notifications/initialized':
				// Acknowledgement notification — no response needed per spec
				return new \WP_REST_Response( null, 204 );

			case 'tools/list':
				$result = $this->handle_tools_list( $params );
				break;

			case 'tools/call':
				$result = $this->handle_tools_call( $params );
				break;

			case 'prompts/list':
				$result = $this->handle_prompts_list();
				break;

			case 'prompts/get':
				$result = $this->handle_prompts_get( $params );
				break;

			case 'resources/list':
				$result = $this->handle_resources_list();
				break;

			case 'resources/read':
				$result = $this->handle_resources_read( $params );
				break;

			default:
				return $this->error_response( $id, -32601, 'Method not found: ' . $method );
		}

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $id, -32603, $result->get_error_message() );
		}

		return $this->json_response( $id, $result );
	}

	// -------------------------------------------------------------------------
	// Method Handlers
	// -------------------------------------------------------------------------

	private function handle_initialize( array $params ): array {
		return [
			'protocolVersion' => '2024-11-05',
			'capabilities'    => [
				'tools'     => [ 'listChanged' => false ],
				'prompts'   => [ 'listChanged' => false ],
				'resources' => [ 'listChanged' => false, 'subscribe' => false ],
			],
			'serverInfo'      => [
				'name'    => 'Bricks Builder MCP',
				'version' => BMCP_VERSION,
			],
			'instructions'    => $this->get_brief_instructions(),
		];
	}

	private function handle_tools_list( array $params ): array {
		return [
			'tools' => $this->registry->get_all_definitions(),
		];
	}

	private function handle_tools_call( array $params ): array|\WP_Error {
		$name      = $params['name'] ?? null;
		$arguments = $params['arguments'] ?? [];

		if ( ! is_string( $name ) || $name === '' ) {
			return new \WP_Error( 'bmcp_invalid_tool', 'tools/call requires "name"' );
		}

		$raw = $this->registry->dispatch( $name, (array) $arguments );

		if ( is_wp_error( $raw ) ) {
			return [
				'content'  => [ [ 'type' => 'text', 'text' => $raw->get_error_message() ] ],
				'isError'  => true,
			];
		}

		$text = is_string( $raw ) ? $raw : wp_json_encode( $raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return [
			'content' => [ [ 'type' => 'text', 'text' => $text ] ],
		];
	}

	private function handle_prompts_list(): array {
		return [
			'prompts' => [
				[
					'name'        => 'bricks_system_prompt',
					'description' => 'Complete guide for working with Bricks Builder — element catalog, data formats, workflow, and site-specific custom instructions.',
				],
			],
		];
	}

	private function handle_prompts_get( array $params ): array|\WP_Error {
		$name = $params['name'] ?? '';

		if ( $name !== 'bricks_system_prompt' ) {
			return new \WP_Error( 'bmcp_not_found', 'Prompt not found: ' . $name );
		}

		$tool   = $this->registry->get_tool( 'bricks_get_system_prompt' );
		$prompt = $tool ? $tool->execute( [] ) : 'System prompt unavailable.';
		if ( is_array( $prompt ) ) {
			$prompt = $prompt['prompt'] ?? wp_json_encode( $prompt );
		}

		return [
			'description' => 'Bricks Builder system prompt',
			'messages'    => [
				[
					'role'    => 'user',
					'content' => [ 'type' => 'text', 'text' => (string) $prompt ],
				],
			],
		];
	}

	private function handle_resources_list(): array {
		return [
			'resources' => [
				[
					'uri'         => 'bricks://site-info',
					'name'        => 'Site Info',
					'description' => 'WordPress site metadata, active plugins, registered CPTs.',
					'mimeType'    => 'application/json',
				],
				[
					'uri'         => 'bricks://global-settings',
					'name'        => 'Global Settings',
					'description' => 'Bricks Builder global settings.',
					'mimeType'    => 'application/json',
				],
				[
					'uri'         => 'bricks://color-palette',
					'name'        => 'Color Palette',
					'description' => 'Active Bricks color palette.',
					'mimeType'    => 'application/json',
				],
				[
					'uri'         => 'bricks://global-classes',
					'name'        => 'Global Classes',
					'description' => 'All Bricks global CSS classes.',
					'mimeType'    => 'application/json',
				],
			],
		];
	}

	private function handle_resources_read( array $params ): array|\WP_Error {
		$uri = $params['uri'] ?? '';

		$tool_map = [
			'bricks://site-info'       => 'bricks_get_site_info',
			'bricks://global-settings' => 'bricks_get_global_settings',
			'bricks://color-palette'   => 'bricks_get_color_palette',
			'bricks://global-classes'  => 'bricks_get_global_classes',
		];

		if ( ! isset( $tool_map[ $uri ] ) ) {
			return new \WP_Error( 'bmcp_not_found', 'Resource not found: ' . $uri );
		}

		$raw  = $this->registry->dispatch( $tool_map[ $uri ], [] );
		$text = is_wp_error( $raw ) ? $raw->get_error_message() : wp_json_encode( $raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		return [
			'contents' => [
				[
					'uri'      => $uri,
					'mimeType' => 'application/json',
					'text'     => $text,
				],
			],
		];
	}

	// -------------------------------------------------------------------------
	// Response Helpers
	// -------------------------------------------------------------------------

	private function json_response( $id, array $result ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		], 200 );
	}

	private function error_response( $id, int $code, string $message ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => [
				'code'    => $code,
				'message' => $message,
			],
		], 200 ); // MCP spec: errors still return HTTP 200 with JSON-RPC error object
	}

	private function get_brief_instructions(): string {
		return 'You are connected to Bricks Builder MCP. Start with bricks_get_site_info to understand the site, then bricks_get_elements to see available elements. Use bricks_get_system_prompt for the full guide. Build pages by constructing an elements array and calling bricks_create_page or bricks_update_page.';
	}
}
