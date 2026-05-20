<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_API {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_endpoint' ] );
	}

	public function register_endpoint() {
		register_rest_route(
			BMCP_REST_NAMESPACE,
			'/mcp',
			[
				'methods'             => [ 'POST', 'GET' ],
				'callback'            => [ $this, 'handle_request' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	public function permission_callback( \WP_REST_Request $request ): bool|\WP_Error {
		// GET requests without a body are used by some MCP clients for capability discovery.
		// We still require auth for those.
		return Auth::validate( $request );
	}

	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		// Handle GET (capability probe) — return a minimal initialization response
		if ( $request->get_method() === 'GET' ) {
			return new \WP_REST_Response( [
				'server'  => 'Bricks Builder MCP',
				'version' => BMCP_VERSION,
				'endpoint' => rest_url( BMCP_REST_NAMESPACE . '/mcp' ),
			], 200 );
		}

		$server = new MCP_Server();
		return $server->dispatch( $request );
	}
}
