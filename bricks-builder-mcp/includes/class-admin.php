<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
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
	}

	public function register_menu(): void {
		add_options_page(
			__( 'Bricks MCP', 'bricks-builder-mcp' ),
			__( 'Bricks MCP', 'bricks-builder-mcp' ),
			'manage_options',
			'bricks-mcp',
			[ $this, 'render_settings_page' ]
		);
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
		if ( $hook !== 'settings_page_bricks-mcp' ) {
			return;
		}

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
				'copied'      => __( 'Copied!', 'bricks-builder-mcp' ),
				'regenerated' => __( 'API key regenerated.', 'bricks-builder-mcp' ),
				'confirm_regen' => __( 'Regenerating the API key will invalidate your current Claude Code configuration. Continue?', 'bricks-builder-mcp' ),
				'cleared'     => __( 'Activity log cleared.', 'bricks-builder-mcp' ),
			],
		] );
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
}
