<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_API {

	// MCP protocol versions supported (newest first)
	public const SUPPORTED_VERSIONS = [ '2025-11-25', '2025-03-26', '2024-11-05' ];

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
		// Allow MCP-specific headers in CORS preflight responses
		add_filter( 'rest_allowed_cors_headers', [ $this, 'add_cors_headers' ] );
	}

	public function add_cors_headers( array $headers ): array {
		$headers[] = 'MCP-Session-Id';
		$headers[] = 'MCP-Protocol-Version';
		return $headers;
	}

	public function register_endpoints(): void {
		// ── Streamable HTTP transport (MCP 2025-03-26+) ── primary endpoint ──
		register_rest_route(
			BMCP_REST_NAMESPACE,
			'/mcp',
			[
				'methods'             => [ 'POST', 'GET', 'DELETE' ],
				'callback'            => [ $this, 'handle_mcp' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		// ── Legacy HTTP+SSE transport (MCP 2024-11-05) ── backwards compat ──
		// GET /sse  → SSE stream that sends an `endpoint` event
		// POST /messages → receives JSON-RPC, returns response in HTTP body
		register_rest_route(
			BMCP_REST_NAMESPACE,
			'/sse',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_sse' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
		register_rest_route(
			BMCP_REST_NAMESPACE,
			'/messages',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_messages' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	public function permission_callback( \WP_REST_Request $request ): bool|\WP_Error {
		$auth = Auth::validate( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		// Rate limiting: max 120 requests per minute per user
		$user_id    = get_current_user_id();
		$rate_key   = 'bmcp_rate_' . $user_id;
		$window_key = 'bmcp_rate_win_' . $user_id;
		$now        = time();
		$window     = (int) get_transient( $window_key );
		$count      = (int) get_transient( $rate_key );

		if ( $window === 0 || $now - $window >= 60 ) {
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

	// -------------------------------------------------------------------------
	// Streamable HTTP transport  (/mcp)
	// MCP 2025-03-26 / 2025-11-25
	// -------------------------------------------------------------------------

	public function handle_mcp( \WP_REST_Request $request ): \WP_REST_Response {
		$method = $request->get_method();

		// GET /mcp — per 2025-11-25 spec the server MUST return a text/event-stream
		// SSE stream OR HTTP 405. We do not implement server-push SSE on this
		// endpoint, so return 405 with an informative body.
		if ( $method === 'GET' ) {
			$response = new \WP_REST_Response(
				[ 'error' => 'Method Not Allowed. Send POST with a JSON-RPC 2.0 body to use this MCP endpoint.' ],
				405
			);
			$response->header( 'Allow', 'POST, DELETE' );
			return $response;
		}

		// DELETE /mcp — MCP 2025-03-26 §6.4: client signals session termination.
		// Plugin is stateless; simply acknowledge.
		if ( $method === 'DELETE' ) {
			return new \WP_REST_Response( null, 200 );
		}

		// POST /mcp — validate MCP-Protocol-Version header if present (2025-11-25).
		// Per spec: if missing, treat as 2025-03-26. If present and unsupported → 400.
		$proto_header = $request->get_header( 'MCP-Protocol-Version' );
		if ( $proto_header !== null && ! in_array( $proto_header, self::SUPPORTED_VERSIONS, true ) ) {
			return new \WP_REST_Response( [
				'jsonrpc' => '2.0',
				'id'      => null,
				'error'   => [
					'code'    => -32600,
					'message' => 'Unsupported MCP-Protocol-Version: ' . esc_html( $proto_header )
						. '. Supported versions: ' . implode( ', ', self::SUPPORTED_VERSIONS ),
				],
			], 400 );
		}

		$server = new MCP_Server( self::SUPPORTED_VERSIONS );
		return $server->dispatch( $request );
	}

	// -------------------------------------------------------------------------
	// Legacy HTTP+SSE transport  (/sse  +  /messages)
	// MCP 2024-11-05 — for Claude Desktop (legacy config), older IDE extensions,
	// and any client that uses the two-endpoint SSE transport.
	// -------------------------------------------------------------------------

	/**
	 * GET /sse
	 *
	 * Opens a text/event-stream connection and immediately emits an `endpoint`
	 * event so the client knows where to POST JSON-RPC messages. The connection
	 * is closed after the event is sent — PHP/WordPress is not designed for
	 * persistent long-lived SSE connections.
	 *
	 * Clients expecting a persistent stream will time-out and reconnect, which
	 * is fine; the endpoint URL they received is stable. For a fully persistent
	 * streaming server, run a dedicated Node/Go sidecar and proxy through it.
	 */
	public function handle_sse( \WP_REST_Request $request ): void {
		$messages_url = rest_url( BMCP_REST_NAMESPACE . '/messages' );

		// Bypass the normal WP REST response pipeline.
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/event-stream; charset=UTF-8' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'X-Accel-Buffering: no' ); // disable nginx buffering
			header( 'Connection: close' );
		}

		// Flush any output buffers so the event arrives immediately.
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		echo "event: endpoint\n";
		echo 'data: ' . $messages_url . "\n\n";

		flush();

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		exit;
	}

	/**
	 * POST /messages
	 *
	 * Receives JSON-RPC from legacy SSE-transport clients and returns the
	 * JSON-RPC response directly in the HTTP body (same behaviour as the
	 * Streamable HTTP /mcp endpoint).
	 */
	public function handle_messages( \WP_REST_Request $request ): \WP_REST_Response {
		$server = new MCP_Server( self::SUPPORTED_VERSIONS );
		return $server->dispatch( $request );
	}
}
