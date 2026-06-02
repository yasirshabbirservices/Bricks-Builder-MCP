<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menu' ], 99 );
		add_action( 'admin_init',            [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_bmcp_regenerate_key',  [ $this, 'ajax_regenerate_key' ] );
		add_action( 'wp_ajax_bmcp_clear_log',       [ $this, 'ajax_clear_log' ] );
		add_action( 'wp_ajax_bmcp_memory_list',     [ $this, 'ajax_memory_list' ] );
		add_action( 'wp_ajax_bmcp_memory_save',     [ $this, 'ajax_memory_save' ] );
		add_action( 'wp_ajax_bmcp_memory_delete',   [ $this, 'ajax_memory_delete' ] );
		add_action( 'wp_ajax_bmcp_history_list',    [ $this, 'ajax_history_list' ] );
		add_action( 'wp_ajax_bmcp_history_restore', [ $this, 'ajax_history_restore' ] );
		add_action( 'wp_ajax_bmcp_history_delete',  [ $this, 'ajax_history_delete' ] );
		add_action( 'wp_ajax_bmcp_history_clear',   [ $this, 'ajax_history_clear' ] );
		add_action( 'wp_ajax_bmcp_export_profile',      [ $this, 'ajax_export_profile' ] );
		add_action( 'wp_ajax_bmcp_import_profile',      [ $this, 'ajax_import_profile' ] );
		add_action( 'wp_ajax_bmcp_add_secondary_key',   [ $this, 'ajax_add_secondary_key' ] );
		add_action( 'wp_ajax_bmcp_delete_secondary_key', [ $this, 'ajax_delete_secondary_key' ] );

		// Snippets Manager AJAX
		add_action( 'wp_ajax_bmcp_snip_save',      [ $this, 'ajax_snip_save' ] );
		add_action( 'wp_ajax_bmcp_snip_delete',    [ $this, 'ajax_snip_delete' ] );
		add_action( 'wp_ajax_bmcp_snip_toggle',    [ $this, 'ajax_snip_toggle' ] );
		add_action( 'wp_ajax_bmcp_snip_safe_mode', [ $this, 'ajax_snip_safe_mode' ] );
	}

	public function register_menu(): void {
		// Always register the actual page under Settings — reliable, no URL rewriting issues
		add_options_page(
			__( 'Bricks MCP', 'bricks-builder-mcp' ),
			__( 'Bricks MCP', 'bricks-builder-mcp' ),
			'manage_options',
			'bricks-mcp',
			[ $this, 'render_settings_page' ]
		);

		// If Bricks is active, show MCP + Snippets entries under the Bricks menu.
		if ( defined( 'BRICKS_VERSION' ) || get_template() === 'bricks' ) {
			add_submenu_page(
				'bricks',
				__( 'Bricks MCP', 'bricks-builder-mcp' ),
				__( 'MCP', 'bricks-builder-mcp' ),
				'manage_options',
				'bricks-mcp-settings',
				[ $this, 'render_settings_page' ]
			);

			// Snippets submenu under Bricks
			add_submenu_page(
				'bricks',
				__( 'Snippets', 'bricks-builder-mcp' ),
				__( 'Snippets', 'bricks-builder-mcp' ),
				'manage_options',
				'bricks-mcp-snippets',
				[ $this, 'render_snippets_page' ]
			);
		}
	}

	public function register_settings(): void {
		register_setting( 'bmcp_settings_instructions', BMCP_INSTRUCTIONS_OPTION, [
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		] );

		register_setting( 'bmcp_settings_capabilities', BMCP_TOOL_STATES_OPTION, [
			'sanitize_callback' => [ $this, 'sanitize_tool_states' ],
			'default'           => [],
		] );

		register_setting( 'bmcp_settings_advanced', BMCP_ADVANCED_OPTION, [
			'sanitize_callback' => [ $this, 'sanitize_advanced_settings' ],
			'default'           => [],
		] );

		register_setting( 'bmcp_settings_business_profile', BMCP_BUSINESS_PROFILE_OPTION, [
			'sanitize_callback' => [ $this, 'sanitize_business_profile' ],
			'default'           => [],
		] );
	}

	public function sanitize_business_profile( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$output = [];

		// ── Text fields ───────────────────────────────────────────────
		foreach ( [ 'business_name', 'tagline', 'business_type', 'target_audience', 'tone_of_voice',
		             'phone', 'address', 'city_country', 'nav_items', 'cta_text', 'copyright_text',
		             'font_heading', 'font_body', 'font_size_base' ] as $field ) {
			$output[ $field ] = sanitize_text_field( $input[ $field ] ?? '' );
		}

		// ── Email & URL fields ────────────────────────────────────────
		$output['email'] = sanitize_email( $input['email'] ?? '' );

		foreach ( [ 'logo_url', 'logo_dark_url', 'cta_url' ] as $field ) {
			$output[ $field ] = sanitize_url( $input[ $field ] ?? '' );
		}

		// ── Textarea fields ───────────────────────────────────────────
		$output['about_text'] = sanitize_textarea_field( $input['about_text'] ?? '' );
		$output['services']   = sanitize_textarea_field( $input['services']   ?? '' );

		// ── Colors (hex only) ─────────────────────────────────────────
		foreach ( [ 'color_primary', 'color_secondary', 'color_accent', 'color_text',
		             'color_heading', 'color_background', 'color_surface', 'color_border',
		             'color_success', 'color_error' ] as $field ) {
			$output[ $field ] = sanitize_hex_color( $input[ $field ] ?? '' ) ?? '';
		}

		// ── Design style (allowlisted selects) ────────────────────────
		$allowed = [
			'design_style'  => [ '', 'modern', 'minimal', 'bold', 'elegant', 'playful', 'corporate', 'creative', 'luxury' ],
			'border_radius' => [ '', 'none', 'small', 'medium', 'large', 'rounded', 'pill' ],
			'spacing_scale' => [ '', 'compact', 'normal', 'spacious' ],
			'button_style'  => [ '', 'filled', 'outline', 'ghost', 'soft' ],
		];
		foreach ( $allowed as $field => $values ) {
			$val = sanitize_key( $input[ $field ] ?? '' );
			$output[ $field ] = in_array( $val, $values, true ) ? $val : '';
		}

		// ── Social links (repeater) ───────────────────────────────────
		$social_links = [];
		if ( ! empty( $input['social_links'] ) && is_array( $input['social_links'] ) ) {
			foreach ( array_values( $input['social_links'] ) as $item ) {
				if ( ! is_array( $item ) ) continue;
				$platform = sanitize_text_field( $item['platform'] ?? '' );
				$url      = sanitize_url( $item['url'] ?? '' );
				if ( $platform !== '' || $url !== '' ) {
					$social_links[] = [ 'platform' => $platform, 'url' => $url ];
				}
			}
		}
		$output['social_links'] = $social_links;

		// ── Extra contact items (repeater) ────────────────────────────
		$contact_extra = [];
		if ( ! empty( $input['contact_extra'] ) && is_array( $input['contact_extra'] ) ) {
			foreach ( array_values( $input['contact_extra'] ) as $item ) {
				if ( ! is_array( $item ) ) continue;
				$label = sanitize_text_field( $item['label'] ?? '' );
				$value = sanitize_text_field( $item['value'] ?? '' );
				if ( $label !== '' || $value !== '' ) {
					$contact_extra[] = [ 'label' => $label, 'value' => $value ];
				}
			}
		}
		$output['contact_extra'] = $contact_extra;

		return $output;
	}

	public function sanitize_advanced_settings( $input ): array {
		return [
			'erase_on_uninstall'   => ! empty( $input['erase_on_uninstall'] ),
			'disable_activity_log' => ! empty( $input['disable_activity_log'] ),
			'debug_mode'           => ! empty( $input['debug_mode'] ),
		];
	}

	public function sanitize_tool_states( $input ): array {
		$all_tools = Tools_Registry::get_all_tool_names();
		$result    = [];
		foreach ( $all_tools as $tool_name ) {
			$result[ $tool_name ] = ! empty( $input[ $tool_name ] );
		}
		return $result;
	}

	public function enqueue_assets( string $hook ): void {
		// ── Main settings page (Settings > Bricks MCP  OR  Bricks > MCP) ─
		if ( in_array( $hook, [ 'settings_page_bricks-mcp', 'bricks_page_bricks-mcp-settings', 'bricks_page_bricks-mcp' ], true ) ) {
			wp_enqueue_style(
				'bmcp-admin',
				BMCP_PLUGIN_URL . 'assets/css/admin.css',
				[],
				BMCP_VERSION
			);

			wp_enqueue_script(
				'bmcp-admin',
				BMCP_PLUGIN_URL . 'assets/js/admin.js',
				[ 'jquery' ],
				BMCP_VERSION,
				true
			);

			wp_localize_script( 'bmcp-admin', 'bmcpAdmin', [
				'nonce'      => wp_create_nonce( 'bmcp_admin_nonce' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'apiKey'     => Auth::get_key(),
				'endpoint'   => rest_url( BMCP_REST_NAMESPACE . '/mcp' ),
				'strings'    => [
					'copied'        => __( 'Copied!', 'bricks-builder-mcp' ),
					'regenerated'   => __( 'API key regenerated.', 'bricks-builder-mcp' ),
					'confirm_regen' => __( 'Regenerating the API key will invalidate your current Claude Code configuration. Continue?', 'bricks-builder-mcp' ),
					'cleared'       => __( 'Activity log cleared.', 'bricks-builder-mcp' ),
				],
			] );
		}

		// ── Snippets pages ─────────────────────────────────────────────
		if ( $hook === 'bricks_page_bricks-mcp-snippets' ) {
			wp_enqueue_style(
				'bmcp-admin',
				BMCP_PLUGIN_URL . 'assets/css/admin.css',
				[],
				BMCP_VERSION
			);
			wp_enqueue_style(
				'bmcp-snippets',
				BMCP_PLUGIN_URL . 'assets/css/snippets.css',
				[ 'bmcp-admin' ],
				BMCP_VERSION
			);

			// Shared data for both snippets JS files
			$snippets_data = [
				'nonce'   => wp_create_nonce( 'bmcp_admin_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			];

			$action = sanitize_key( $_GET['action'] ?? '' );
			if ( $action === 'edit' || $action === 'new' ) {
				// Edit / new page — CodeMirror loaded separately; our script has no hard dep on it
				wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );
				wp_enqueue_script(
					'bmcp-snippet-edit',
					BMCP_PLUGIN_URL . 'assets/js/snippet-edit.js',
					[],   // no hard dep — CodeMirror init is graceful if wp.codeEditor is absent
					BMCP_VERSION,
					true
				);
				wp_localize_script( 'bmcp-snippet-edit', 'bmcpSnippets', $snippets_data );
			} else {
				// List page
				wp_enqueue_script(
					'bmcp-snippets-admin',
					BMCP_PLUGIN_URL . 'assets/js/snippets-admin.js',
					[],
					BMCP_VERSION,
					true
				);
				wp_localize_script( 'bmcp-snippets-admin', 'bmcpSnippets', $snippets_data );
			}
		}
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bricks-builder-mcp' ) );
		}
		include BMCP_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	public function ajax_regenerate_key(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$new_key = Auth::regenerate_key();

		wp_send_json_success( [
			'key'    => $new_key,
			'masked' => Auth::masked_key(),
		] );
	}

	public function ajax_clear_log(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		delete_option( BMCP_ACTIVITY_LOG_OPTION );
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Memory AJAX
	// -------------------------------------------------------------------------

	public function ajax_memory_list(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$category = sanitize_key( $_POST['category'] ?? '' );
		$search   = sanitize_text_field( $_POST['search'] ?? '' );

		$result = Memory_Manager::get_paginated( $page, 15, $category, $search );
		wp_send_json_success( $result );
	}

	public function ajax_memory_save(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$id = sanitize_text_field( $_POST['id'] ?? '' );

		$data = [
			'category'   => sanitize_key( $_POST['category'] ?? 'general' ),
			'title'      => sanitize_text_field( $_POST['title'] ?? '' ),
			'content'    => sanitize_textarea_field( $_POST['content'] ?? '' ),
			'importance' => sanitize_key( $_POST['importance'] ?? 'medium' ),
			'tags'       => array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $_POST['tags'] ?? '' ) ) ) ),
		];

		if ( empty( $data['title'] ) || empty( $data['content'] ) ) {
			wp_send_json_error( 'Title and content are required.' );
		}

		if ( $id ) {
			$memory = Memory_Manager::update( $id, $data );
			if ( ! $memory ) {
				wp_send_json_error( 'Memory not found.' );
			}
		} else {
			$memory = Memory_Manager::add( $data );
		}

		wp_send_json_success( [ 'memory' => $memory ] );
	}

	public function ajax_memory_delete(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$id      = sanitize_text_field( $_POST['id'] ?? '' );
		$deleted = Memory_Manager::delete( $id );

		if ( ! $deleted ) {
			wp_send_json_error( 'Memory not found.' );
		}

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// History AJAX
	// -------------------------------------------------------------------------

	public function ajax_history_list(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$page    = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$area    = sanitize_key( $_POST['area'] ?? '' );

		$result = History_Manager::get_paginated( $page, 15, $post_id, $area );
		wp_send_json_success( $result );
	}

	public function ajax_history_restore(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$id  = (int) ( $_POST['id'] ?? 0 );
		$row = History_Manager::restore( $id, 'admin_restore' );

		if ( ! $row ) {
			wp_send_json_error( 'Snapshot not found.' );
		}

		wp_send_json_success( [ 'restored' => true, 'snapshot' => $row ] );
	}

	public function ajax_history_delete(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$id      = (int) ( $_POST['id'] ?? 0 );
		$deleted = History_Manager::delete( $id );

		if ( ! $deleted ) {
			wp_send_json_error( 'Snapshot not found.' );
		}

		wp_send_json_success();
	}

	public function ajax_history_clear(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		History_Manager::clear_all();
		wp_send_json_success();
	}

	public function ajax_export_profile(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$profile = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] );

		wp_send_json_success( [
			'exported_at'  => wp_date( 'Y-m-d H:i:s' ),
			'site_url'     => get_site_url(),
			'bmcp_version' => BMCP_VERSION,
			'profile'      => is_array( $profile ) ? $profile : [],
		] );
	}

	public function ajax_add_secondary_key(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$name   = sanitize_text_field( wp_unslash( $_POST['key_name'] ?? '' ) );
		$scopes = isset( $_POST['scopes'] ) && is_array( $_POST['scopes'] )
			? array_intersect( array_map( 'sanitize_key', $_POST['scopes'] ), [ 'read', 'write', 'delete', 'admin' ] )
			: [ 'read' ];

		if ( empty( $name ) ) {
			wp_send_json_error( 'Key name is required.' );
		}

		$plain_key = wp_generate_password( 32, false );
		$key_id    = wp_generate_password( 12, false );

		$secondary_keys   = get_option( BMCP_SECONDARY_KEYS_OPTION, [] );
		$secondary_keys[] = [
			'id'         => $key_id,
			'name'       => $name,
			'key'        => $plain_key,
			'scopes'     => array_values( $scopes ),
			'created_at' => current_time( 'Y-m-d' ),
		];

		update_option( BMCP_SECONDARY_KEYS_OPTION, $secondary_keys, false );

		wp_send_json_success( [
			'id'         => $key_id,
			'plain_key'  => $plain_key,
			'name'       => $name,
			'scopes'     => array_values( $scopes ),
			'created_at' => current_time( 'Y-m-d' ),
			'message'    => 'Key created. Copy it now — it will not be shown again.',
		] );
	}

	public function ajax_delete_secondary_key(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$key_id         = sanitize_text_field( wp_unslash( $_POST['key_id'] ?? '' ) );
		$secondary_keys = get_option( BMCP_SECONDARY_KEYS_OPTION, [] );
		$filtered       = array_values( array_filter( $secondary_keys, fn( $k ) => ( $k['id'] ?? '' ) !== $key_id ) );

		update_option( BMCP_SECONDARY_KEYS_OPTION, $filtered, false );
		wp_send_json_success();
	}

	// =========================================================================
	// Snippets Manager — render + AJAX
	// =========================================================================

	public function render_snippets_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bricks-builder-mcp' ) );
		}
		$action = sanitize_key( $_GET['action'] ?? '' );
		if ( $action === 'edit' || $action === 'new' ) {
			include BMCP_PLUGIN_DIR . 'admin/views/snippet-edit.php';
		} else {
			include BMCP_PLUGIN_DIR . 'admin/views/snippets-page.php';
		}
	}

	public function ajax_snip_save(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$id = (int) ( $_POST['id'] ?? 0 );

		$args = [
			'title'       => sanitize_text_field( wp_unslash( $_POST['title']       ?? '' ) ),
			'code'        => wp_unslash( $_POST['code']        ?? '' ),
			'type'        => sanitize_key( $_POST['type']        ?? 'php' ),
			'status'      => sanitize_key( $_POST['status']      ?? 'inactive' ),
			'location'    => sanitize_key( $_POST['location']    ?? 'everywhere' ),
			'hook'        => sanitize_key( $_POST['hook']        ?? 'init' ),
			'priority'    => (int) ( $_POST['priority']   ?? 10 ),
			'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'tags'        => sanitize_text_field( wp_unslash( $_POST['tags']        ?? '' ) ),
			'url'         => esc_url_raw( wp_unslash( $_POST['url']          ?? '' ) ),
			'conditions'  => json_decode( wp_unslash( $_POST['conditions'] ?? '[]' ), true ) ?: [],
		];

		if ( empty( $args['title'] ) ) {
			wp_send_json_error( [ 'message' => 'Snippet name is required.' ] );
		}

		if ( $id > 0 ) {
			$existing = Snippets_Manager::get_snippet( $id );
			if ( ! $existing ) {
				wp_send_json_error( [ 'message' => 'Snippet not found.' ] );
				return;
			}
			$args = array_merge( $existing, $args );
		}

		$result = Snippets_Manager::save_snippet( $args, $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'id' => $result, 'message' => 'Snippet saved.' ] );
	}

	public function ajax_snip_delete(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! Snippets_Manager::delete_snippet( $id ) ) {
			wp_send_json_error( [ 'message' => 'Could not delete snippet.' ] );
		}

		wp_send_json_success( [ 'id' => $id ] );
	}

	public function ajax_snip_toggle(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$id     = (int) ( $_POST['id'] ?? 0 );
		$status = sanitize_key( $_POST['status'] ?? 'inactive' );
		if ( ! in_array( $status, [ 'active', 'inactive' ], true ) ) {
			$status = 'inactive';
		}

		if ( ! Snippets_Manager::toggle_snippet( $id, $status ) ) {
			wp_send_json_error( [ 'message' => 'Snippet not found.' ] );
		}

		wp_send_json_success( [ 'id' => $id, 'status' => $status ] );
	}

	public function ajax_snip_safe_mode(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		// Blocked if constant is set
		if ( defined( 'BMCP_SNIPPETS_SAFE_MODE' ) ) {
			wp_send_json_error( [ 'message' => 'Safe mode is locked by a server constant.' ] );
		}

		$enable = filter_var( $_POST['enable'] ?? true, FILTER_VALIDATE_BOOLEAN );
		update_option( Snippets_Manager::SAFE_MODE_OPT, $enable ? '1' : '0', false );
		wp_send_json_success( [ 'safe_mode' => $enable ] );
	}

	public function ajax_import_profile(): void {
		check_ajax_referer( 'bmcp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$raw = isset( $_POST['profile_json'] ) ? wp_unslash( $_POST['profile_json'] ) : '';
		if ( empty( $raw ) ) {
			wp_send_json_error( 'No JSON provided.' );
		}

		$decoded = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( 'Invalid JSON: ' . json_last_error_msg() );
		}

		// Accept either the full export blob or just the profile object
		$incoming = isset( $decoded['profile'] ) && is_array( $decoded['profile'] )
			? $decoded['profile']
			: $decoded;

		if ( ! is_array( $incoming ) || empty( $incoming ) ) {
			wp_send_json_error( 'Profile data is empty or invalid.' );
		}

		$existing = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$merged = array_merge( $existing, $incoming );
		$clean  = $this->sanitize_business_profile( $merged );

		update_option( BMCP_BUSINESS_PROFILE_OPTION, $clean, false );

		wp_send_json_success( [ 'message' => 'Business profile imported successfully. Reload the page to see updated values.' ] );
	}
}
