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
		add_action( 'wp_ajax_bmcp_regenerate_key', [ $this, 'ajax_regenerate_key' ] );
		add_action( 'wp_ajax_bmcp_clear_log',      [ $this, 'ajax_clear_log' ] );
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
		register_setting( 'bmcp_settings', BMCP_INSTRUCTIONS_OPTION, [
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		] );

		register_setting( 'bmcp_settings', BMCP_ENABLED_TOOLS_OPTION, [
			'sanitize_callback' => [ $this, 'sanitize_enabled_tools' ],
			'default'           => [],
		] );
	}

	public function sanitize_enabled_tools( $input ): array {
		$groups  = [ 'pages', 'templates', 'settings', 'posts', 'media', 'woocommerce' ];
		$result  = [];
		foreach ( $groups as $group ) {
			$result[ $group ] = ! empty( $input[ $group ] );
		}
		$result['site'] = true; // Site tools always enabled
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
}
