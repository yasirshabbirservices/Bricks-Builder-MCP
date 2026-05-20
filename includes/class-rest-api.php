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
		$auth = Auth::validate( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		// Rate limiting: max 120 tool calls per minute per user
		$user_id    = get_current_user_id();
		$rate_key   = 'bmcp_rate_' . $user_id;
		$window_key = 'bmcp_rate_win_' . $user_id;
		$now        = time();
		$window     = (int) get_transient( $window_key );
		$count      = (int) get_transient( $rate_key );

		if ( $window === 0 || $now - $window >= 60 ) {
			// New window
			set_transient( $window_key, $now, 120 );
			set_transient( $rate_key, 1, 120 );
		} elseif ( $count >= 120 ) {
			return new \WP_Error(
				'bmcp_rate_limited',
				'Rate limit exceeded: 120 requests per minute. Please slow down.',
				[ 'status' => 429 ]
			);
		} else {
			set_transient( $rate_key, $count + 1, 120 );
		}

		return true;
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
