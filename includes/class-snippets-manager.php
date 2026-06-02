<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snippets Manager
 *
 * Registers CPT bmcp_snippet, schedules execution of active snippets on their
 * configured WordPress hooks, manages safe-mode, and exposes static CRUD
 * helpers used by both the admin UI AJAX handlers and the MCP tool layer.
 *
 * PHP snippets are signed with wp_hash() (same system as the Bricks code element).
 * A snippet without a valid signature will not execute.
 */
class Snippets_Manager {

	const CPT           = 'bmcp_snippet';
	const SAFE_MODE_OPT = 'bmcp_snippets_safe_mode';

	/** Valid snippet types */
	const TYPES = [ 'php', 'javascript', 'javascript_url', 'css', 'css_url', 'html' ];

	/** Valid locations */
	const LOCATIONS = [ 'everywhere', 'frontend', 'admin', 'shortcode' ];

	/** Common WordPress hooks exposed in the admin UI */
	const HOOKS = [
		'init', 'plugins_loaded', 'after_setup_theme',
		'wp_head', 'wp_body_open', 'wp_footer',
		'wp_enqueue_scripts',
		'admin_init', 'admin_head', 'admin_footer',
		'login_head', 'login_footer',
		'save_post', 'wp_login', 'user_register',
	];

	public function __construct() {
		add_action( 'init', [ $this, 'register_cpt' ], 0 );
		add_action( 'init', [ $this, 'execute_snippets' ], 1 );
		add_action( 'init', [ $this, 'register_shortcodes' ], 2 );

		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'handle_safe_mode_toggle' ] );
		}
	}

	// ── CPT ──────────────────────────────────────────────────────────────────

	public function register_cpt(): void {
		register_post_type( self::CPT, [
			'labels'              => [
				'name'          => 'Snippets',
				'singular_name' => 'Snippet',
			],
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'supports'            => [ 'title', 'editor', 'revisions' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'rewrite'             => false,
			'query_var'           => false,
			'hierarchical'        => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
		] );
	}

	// ── Safe Mode ─────────────────────────────────────────────────────────────

	public static function is_safe_mode(): bool {
		if ( defined( 'BMCP_SNIPPETS_SAFE_MODE' ) ) {
			return (bool) BMCP_SNIPPETS_SAFE_MODE;
		}
		return (bool) get_option( self::SAFE_MODE_OPT, false );
	}

	public function handle_safe_mode_toggle(): void {
		if ( ! isset( $_GET['bmcp_snip_safemode'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'bmcp_snip_safemode' ) ) {
			return;
		}
		$enable = (bool) intval( $_GET['bmcp_snip_safemode'] );
		update_option( self::SAFE_MODE_OPT, $enable );
		wp_safe_redirect( remove_query_arg( [ 'bmcp_snip_safemode', '_wpnonce' ] ) );
		exit;
	}

	// ── Signature helpers (mirrors Bricks code element) ──────────────────────

	public static function sign( string $code ): string {
		return wp_hash( $code );
	}

	public static function verify( string $code, string $signature ): bool {
		return hash_equals( wp_hash( $code ), $signature );
	}

	// ── Execution scheduling ──────────────────────────────────────────────────

	public function execute_snippets(): void {
		foreach ( $this->get_active_posts_raw() as $post ) {
			$this->schedule_snippet( $post );
		}
	}

	private function schedule_snippet( \WP_Post $post ): void {
		$type     = get_post_meta( $post->ID, '_bmcp_snip_type',     true ) ?: 'php';
		$location = get_post_meta( $post->ID, '_bmcp_snip_location', true ) ?: 'everywhere';
		$hook     = get_post_meta( $post->ID, '_bmcp_snip_hook',     true ) ?: 'init';
		$priority = (int) ( get_post_meta( $post->ID, '_bmcp_snip_priority', true ) ?: 10 );

		// Location guard
		if ( 'frontend'  === $location && is_admin() )  return;
		if ( 'admin'     === $location && ! is_admin() ) return;
		if ( 'shortcode' === $location ) return; // Handled by register_shortcodes()

		// Condition check (done here to avoid registering hooks at all)
		if ( ! $this->conditions_pass( $post->ID ) ) return;

		$id = $post->ID;

		switch ( $type ) {
			case 'php':
				if ( self::is_safe_mode() ) return;
				if ( did_action( $hook ) ) {
					$this->run_php( $id );
				} else {
					add_action( $hook, function () use ( $id ) { $this->run_php( $id ); }, $priority );
				}
				break;

			case 'javascript':
				$target = is_admin() ? 'admin_footer' : 'wp_footer';
				add_action( $target, function () use ( $id ) { $this->output_inline_js( $id ); }, $priority );
				break;

			case 'javascript_url':
				$target = is_admin() ? 'admin_enqueue_scripts' : 'wp_enqueue_scripts';
				add_action( $target, function () use ( $id ) { $this->enqueue_js_url( $id ); }, $priority );
				break;

			case 'css':
				$target = is_admin() ? 'admin_head' : 'wp_head';
				add_action( $target, function () use ( $id ) { $this->output_inline_css( $id ); }, $priority );
				break;

			case 'css_url':
				$target = is_admin() ? 'admin_enqueue_scripts' : 'wp_enqueue_scripts';
				add_action( $target, function () use ( $id ) { $this->enqueue_css_url( $id ); }, $priority );
				break;

			case 'html':
				if ( did_action( $hook ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wp_kses_post( get_post_field( 'post_content', $id ) );
				} else {
					add_action( $hook, function () use ( $id ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo wp_kses_post( get_post_field( 'post_content', $id ) );
					}, $priority );
				}
				break;
		}
	}

	// ── Execution helpers ─────────────────────────────────────────────────────

	private function run_php( int $id ): void {
		$code = get_post_field( 'post_content', $id );
		$sig  = get_post_meta( $id, '_bmcp_snip_signature', true );

		// Security: eval() is intentional here — this is a code snippets executor whose
		// entire purpose is running admin-authored PHP. Execution is gated by a wp_hash()
		// signature that is computed server-side on every save and verified here before any
		// code runs. Snippets without a valid signature are silently skipped. This mirrors
		// exactly how Bricks Builder's own code element works (see Bricks helpers.php).
		if ( empty( $sig ) || ! self::verify( $code, $sig ) ) {
			return; // Signature missing or tampered — refuse execution
		}

		// During AJAX / REST / WP-Cron the PHP snippet still runs so that any
		// add_action / add_filter calls inside it take effect — but we buffer
		// direct echo/print output so it cannot corrupt the JSON response.
		$suppress_output = wp_doing_ajax()
			|| wp_doing_cron()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST );

		try {
			delete_post_meta( $id, '_bmcp_snip_error' );
			if ( $suppress_output ) ob_start();
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- intentional; guarded by wp_hash() above
			eval( '?>' . $code );
			if ( $suppress_output ) ob_end_clean();
		} catch ( \Throwable $e ) {
			if ( $suppress_output ) @ob_end_clean(); // phpcs:ignore
			self::deactivate_on_error( $id, $e );
		}
	}

	/**
	 * Deactivate a snippet after a runtime error and store the error message.
	 * Uses direct DB update to avoid triggering save hooks mid-execution.
	 */
	private static function deactivate_on_error( int $id, \Throwable $e ): void {
		global $wpdb;

		$msg = sprintf(
			'[%s] %s in %s on line %d',
			get_class( $e ),
			$e->getMessage(),
			$e->getFile(),
			$e->getLine()
		);

		// Store the error so it can be displayed in the admin UI
		update_post_meta( $id, '_bmcp_snip_error', $msg );

		// Set post_status to draft — snippet will not execute on next load
		$wpdb->update(
			$wpdb->posts,
			[ 'post_status' => 'draft' ],
			[ 'ID' => $id, 'post_type' => self::CPT ],
			[ '%s' ],
			[ '%d', '%s' ]
		);
		clean_post_cache( $id );

		// Always write to PHP error log so server admins can see it
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Bricks MCP Snippet #' . $id . ' auto-deactivated: ' . $msg );
	}

	private function output_inline_js( int $id ): void {
		$code = get_post_field( 'post_content', $id );
		if ( $code ) {
			printf( '<script id="bmcp-snip-%d">%s</script>' . "\n", (int) $id, $code ); // phpcs:ignore
		}
	}

	private function output_inline_css( int $id ): void {
		$code = get_post_field( 'post_content', $id );
		if ( $code ) {
			printf( '<style id="bmcp-snip-%d">%s</style>' . "\n", (int) $id, $code ); // phpcs:ignore
		}
	}

	private function enqueue_js_url( int $id ): void {
		$url = get_post_meta( $id, '_bmcp_snip_url', true );
		if ( $url ) {
			wp_enqueue_script( 'bmcp-snip-' . $id, esc_url_raw( $url ), [], null, true );
		}
	}

	private function enqueue_css_url( int $id ): void {
		$url = get_post_meta( $id, '_bmcp_snip_url', true );
		if ( $url ) {
			wp_enqueue_style( 'bmcp-snip-' . $id, esc_url_raw( $url ) );
		}
	}

	// ── Shortcodes ────────────────────────────────────────────────────────────

	public function register_shortcodes(): void {
		foreach ( $this->get_active_posts_raw( 'shortcode' ) as $post ) {
			$slug = get_post_meta( $post->ID, '_bmcp_snip_shortcode', true )
				?: ( 'bmcp-snippet-' . $post->ID );
			$id = $post->ID;
			add_shortcode( $slug, function () use ( $id ) {
				return $this->render_shortcode( $id );
			} );
		}
	}

	private function render_shortcode( int $id ): string {
		$type = get_post_meta( $id, '_bmcp_snip_type', true );
		$code = get_post_field( 'post_content', $id );

		if ( 'php' === $type ) {
			if ( self::is_safe_mode() ) return '';
			$sig = get_post_meta( $id, '_bmcp_snip_signature', true );
			if ( empty( $sig ) || ! self::verify( $code, $sig ) ) return '';
			ob_start();
			try {
				delete_post_meta( $id, '_bmcp_snip_error' );
				// phpcs:ignore Squiz.PHP.Eval.Discouraged -- intentional; guarded by wp_hash() above
				eval( '?>' . $code );
			} catch ( \Throwable $e ) {
				ob_end_clean();
				self::deactivate_on_error( $id, $e );
				return '';
			}
			return (string) ob_get_clean();
		}

		if ( 'javascript' === $type ) return '<script>' . $code . '</script>';
		if ( 'css'        === $type ) return '<style>'  . $code . '</style>';

		// HTML type: wp_kses_post intentionally strips <script> — use 'javascript' type for JS.
		return wp_kses_post( $code );
	}

	// ── Condition evaluation ──────────────────────────────────────────────────

	private function conditions_pass( int $id ): bool {
		$raw = get_post_meta( $id, '_bmcp_snip_conditions', true );
		if ( ! $raw ) return true;

		$conditions = is_string( $raw ) ? json_decode( $raw, true ) : (array) $raw;
		if ( empty( $conditions ) || ! is_array( $conditions ) ) return true;

		foreach ( $conditions as $cond ) {
			if ( ! $this->evaluate_condition( (array) $cond ) ) {
				return false; // ALL rules must pass (AND logic)
			}
		}
		return true;
	}

	private function evaluate_condition( array $cond ): bool {
		$type  = $cond['type']  ?? '';
		$op    = $cond['op']    ?? 'equals';
		$value = (string) ( $cond['value'] ?? '' );

		switch ( $type ) {
			case 'post_type':
				$current = (string) get_post_type();
				$match   = ( $current === $value );
				return 'equals' === $op ? $match : ! $match;

			case 'user_role':
				if ( ! is_user_logged_in() ) {
					$match = ( 'logged_out' === $value );
					return 'equals' === $op ? $match : ! $match;
				}
				$user  = wp_get_current_user();
				$has   = in_array( $value, (array) ( $user->roles ?? [] ), true );
				return 'equals' === $op ? $has : ! $has;

			case 'logged_in':
				$is_in = is_user_logged_in();
				$want  = in_array( strtolower( $value ), [ '1', 'true', 'yes' ], true );
				return $is_in === $want;

			case 'url_pattern':
				$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
				$url = set_url_scheme( home_url( $request_uri ) );
				$has = ( false !== strpos( $url, $value ) );
				return 'equals' === $op ? $has : ! $has;

			default:
				return true;
		}
	}

	// ── Internal query helpers ────────────────────────────────────────────────

	private function get_active_posts_raw( string $location_filter = '' ): array {
		$args = [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		];

		if ( $location_filter ) {
			$args['meta_query'] = [ [
				'key'   => '_bmcp_snip_location',
				'value' => $location_filter,
			] ];
		}

		return get_posts( $args );
	}

	// ── Public CRUD API (admin AJAX + MCP tool layer) ─────────────────────────

	public static function get_snippets( array $filters = [] ): array {
		$per_page   = min( 100, (int) ( $filters['per_page'] ?? 50 ) );
		$page       = max( 1,  (int) ( $filters['page']     ?? 1 ) );
		$status_raw = $filters['status'] ?? '';

		$post_status = [ 'publish', 'draft' ];
		if ( 'active'   === $status_raw ) $post_status = [ 'publish' ];
		if ( 'inactive' === $status_raw ) $post_status = [ 'draft' ];

		$args = [
			'post_type'      => self::CPT,
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( ! empty( $filters['search'] ) ) {
			$args['s'] = sanitize_text_field( $filters['search'] );
		}

		$meta_queries = [];
		if ( ! empty( $filters['type'] ) ) {
			$meta_queries[] = [
				'key'   => '_bmcp_snip_type',
				'value' => sanitize_key( $filters['type'] ),
			];
		}
		if ( ! empty( $filters['tag'] ) ) {
			$meta_queries[] = [
				'key'     => '_bmcp_snip_tags',
				'value'   => sanitize_text_field( $filters['tag'] ),
				'compare' => 'LIKE',
			];
		}
		if ( $meta_queries ) {
			$args['meta_query'] = $meta_queries;
		}

		$query = new \WP_Query( $args );

		return [
			'snippets' => array_map( [ __CLASS__, 'format_snippet' ], $query->posts ),
			'total'    => (int) $query->found_posts,
			'pages'    => (int) $query->max_num_pages,
			'page'     => $page,
		];
	}

	public static function get_snippet( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) return null;
		return self::format_snippet( $post, true );
	}

	/**
	 * Format a WP_Post into the canonical snippet array.
	 *
	 * @param \WP_Post $post
	 * @param bool     $with_code Include the code body (default false for list views).
	 */
	public static function format_snippet( \WP_Post $post, bool $with_code = false ): array {
		$conditions_raw = get_post_meta( $post->ID, '_bmcp_snip_conditions', true );
		$conditions     = [];
		if ( $conditions_raw ) {
			$decoded    = is_string( $conditions_raw ) ? json_decode( $conditions_raw, true ) : $conditions_raw;
			$conditions = is_array( $decoded ) ? $decoded : [];
		}

		$error = get_post_meta( $post->ID, '_bmcp_snip_error', true );

		$data = [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'status'      => 'publish' === $post->post_status ? 'active' : 'inactive',
			'type'        => get_post_meta( $post->ID, '_bmcp_snip_type',      true ) ?: 'php',
			'location'    => get_post_meta( $post->ID, '_bmcp_snip_location',  true ) ?: 'everywhere',
			'hook'        => get_post_meta( $post->ID, '_bmcp_snip_hook',      true ) ?: 'init',
			'priority'    => (int) ( get_post_meta( $post->ID, '_bmcp_snip_priority', true ) ?: 10 ),
			'description' => get_post_meta( $post->ID, '_bmcp_snip_desc',      true ) ?: '',
			'tags'        => get_post_meta( $post->ID, '_bmcp_snip_tags',      true ) ?: '',
			'url'         => get_post_meta( $post->ID, '_bmcp_snip_url',       true ) ?: '',
			'shortcode'   => get_post_meta( $post->ID, '_bmcp_snip_shortcode', true ) ?: ( 'bmcp-snippet-' . $post->ID ),
			'conditions'  => $conditions,
			'modified'    => $post->post_modified,
			'error'       => $error ?: '',   // set when snippet was auto-deactivated by a runtime error
		];

		if ( $with_code ) {
			$data['code'] = $post->post_content;
		}

		return $data;
	}

	/**
	 * Create (id=0) or update (id>0) a snippet. Returns the post ID or WP_Error.
	 */
	public static function save_snippet( array $data, int $id = 0 ): int|\WP_Error {
		$title    = sanitize_text_field(     $data['title']       ?? 'Untitled Snippet' );
		$code     =                          $data['code']        ?? '';

		// Validate type and location against the known-good lists; fall back to safe defaults
		$type_raw = sanitize_key( $data['type'] ?? 'php' );
		$type     = in_array( $type_raw, self::TYPES, true ) ? $type_raw : 'php';

		$location_raw = sanitize_key( $data['location'] ?? 'everywhere' );
		$location     = in_array( $location_raw, self::LOCATIONS, true ) ? $location_raw : 'everywhere';

		$hook     = sanitize_text_field(     $data['hook']        ?? 'init' );
		$priority = (int) (                  $data['priority']    ?? 10 );
		$desc     = sanitize_textarea_field( $data['description'] ?? '' );
		$tags     = sanitize_text_field(     $data['tags']        ?? '' );
		$url      = esc_url_raw(             $data['url']         ?? '' );
		$wp_status = ( 'active' === ( $data['status'] ?? 'inactive' ) ) ? 'publish' : 'draft';

		// Conditions — accept array or JSON string
		$conditions = $data['conditions'] ?? [];
		if ( is_string( $conditions ) ) {
			$conditions = json_decode( $conditions, true ) ?: [];
		}

		// Validate each condition rule
		$clean_conditions = [];
		foreach ( (array) $conditions as $cond ) {
			$cond = (array) $cond;
			if ( ! isset( $cond['type'] ) ) continue;
			$clean_conditions[] = [
				'type'  => sanitize_key( $cond['type'] ),
				'op'    => sanitize_key( $cond['op'] ?? 'equals' ),
				'value' => sanitize_text_field( $cond['value'] ?? '' ),
			];
		}

		$post_args = [
			'post_type'    => self::CPT,
			'post_title'   => $title,
			'post_content' => $code,
			'post_status'  => $wp_status,
		];

		if ( $id > 0 ) {
			$post_args['ID'] = $id;
			$result = wp_update_post( $post_args, true );
		} else {
			$result = wp_insert_post( $post_args, true );
		}

		if ( is_wp_error( $result ) ) return $result;
		$new_id = (int) $result;

		// Auto-generate shortcode slug now that we have the ID
		$shortcode = sanitize_key( $data['shortcode'] ?? '' );
		if ( ! $shortcode ) {
			$shortcode = 'bmcp-snippet-' . $new_id;
		}

		// Strip any leading <?php tag — the executor prepends '?>' itself, so the
		// tag is redundant and the decorator already shows it visually.
		if ( 'php' === $type ) {
			$code = ltrim( $code );
			if ( str_starts_with( $code, '<?php' ) ) {
				$code = ltrim( substr( $code, 5 ) );
			}
		}

		// Sign PHP code using wp_hash() (same as Bricks code element)
		$signature = '';
		if ( 'php' === $type && $code ) {
			$signature = self::sign( $code );
		}

		update_post_meta( $new_id, '_bmcp_snip_type',       $type );
		update_post_meta( $new_id, '_bmcp_snip_location',   $location );
		update_post_meta( $new_id, '_bmcp_snip_hook',       $hook );
		update_post_meta( $new_id, '_bmcp_snip_priority',   $priority );
		update_post_meta( $new_id, '_bmcp_snip_desc',       $desc );
		update_post_meta( $new_id, '_bmcp_snip_tags',       $tags );
		update_post_meta( $new_id, '_bmcp_snip_url',        $url );
		update_post_meta( $new_id, '_bmcp_snip_shortcode',  $shortcode );
		update_post_meta( $new_id, '_bmcp_snip_conditions', wp_json_encode( $clean_conditions ) );
		update_post_meta( $new_id, '_bmcp_snip_signature',  $signature );
		delete_post_meta( $new_id, '_bmcp_snip_error' ); // clear any previous runtime error

		return $new_id;
	}

	public static function delete_snippet( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) return false;
		return (bool) wp_delete_post( $id, true );
	}

	public static function toggle_snippet( int $id, string $status ): bool {
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) return false;
		return (bool) wp_update_post( [
			'ID'          => $id,
			'post_status' => ( 'active' === $status ) ? 'publish' : 'draft',
		] );
	}

	/**
	 * Export snippets as a portable JSON-ready array.
	 * Passing an empty $ids array exports everything.
	 */
	public static function export_snippets( array $ids = [] ): array {
		if ( empty( $ids ) ) {
			$ids = get_posts( [
				'post_type'      => self::CPT,
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			] );
		}

		$snippets = [];
		foreach ( $ids as $id ) {
			$s = self::get_snippet( (int) $id );
			if ( $s ) $snippets[] = $s;
		}

		return [
			'generator' => 'Bricks Builder MCP',
			'version'   => BMCP_VERSION,
			'date'      => gmdate( 'Y-m-d H:i:s' ),
			'snippets'  => $snippets,
		];
	}

	/**
	 * Import snippets from an export array. All imported snippets are set inactive by default.
	 *
	 * @param  array $snippets  Array of snippet objects (from export JSON).
	 * @return int[]            IDs of newly created posts.
	 */
	public static function import_snippets( array $snippets ): array {
		$imported = [];
		foreach ( $snippets as $s ) {
			$s           = (array) $s;
			$s['status'] = 'inactive'; // Safe default on import
			unset( $s['id'] );         // Force new post creation
			$result = self::save_snippet( $s );
			if ( ! is_wp_error( $result ) ) {
				$imported[] = $result;
			}
		}
		return $imported;
	}

	// ── Admin stats helper ────────────────────────────────────────────────────

	public static function get_stats(): array {
		global $wpdb;

		$table = $wpdb->posts;
		$cpt   = self::CPT;

		// Total active / inactive
		$active   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE post_type=%s AND post_status='publish'", $cpt ) );
		$inactive = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE post_type=%s AND post_status='draft'", $cpt ) );

		// By type (joined with postmeta)
		$meta_table = $wpdb->postmeta;
		$counts_raw = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_value AS type, COUNT(*) AS cnt
			 FROM {$table} p
			 JOIN {$meta_table} pm ON pm.post_id = p.ID AND pm.meta_key = '_bmcp_snip_type'
			 WHERE p.post_type = %s AND p.post_status IN ('publish','draft')
			 GROUP BY pm.meta_value",
			$cpt
		) );

		$by_type = [];
		foreach ( $counts_raw as $row ) {
			$by_type[ $row->type ] = (int) $row->cnt;
		}

		return [
			'total'    => $active + $inactive,
			'active'   => $active,
			'inactive' => $inactive,
			'by_type'  => $by_type,
		];
	}
}
