<?php
/**
 * Plugin Name: Bricks Builder MCP
 * Plugin URI:  https://yasirshabbir.com
 * Description: Model Context Protocol (MCP) server for Bricks Builder — lets Claude Code and any MCP-compatible AI build and design your site directly.
 * Version:     1.0.3
 * Author:      Yasir Shabbir
 * Author URI:  https://yasirshabbir.com
 * License:     GPL-2.0-or-later
 * Text Domain: bricks-builder-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMCP_VERSION',              '1.0.3' );
define( 'BMCP_PLUGIN_FILE',          __FILE__ );
define( 'BMCP_PLUGIN_DIR',           plugin_dir_path( __FILE__ ) );
define( 'BMCP_PLUGIN_URL',           plugin_dir_url( __FILE__ ) );
define( 'BMCP_API_KEY_OPTION',       'bmcp_api_key' );
define( 'BMCP_ADMIN_USER_OPTION',    'bmcp_admin_user_id' );
define( 'BMCP_INSTRUCTIONS_OPTION',  'bmcp_custom_instructions' );
define( 'BMCP_ENABLED_TOOLS_OPTION', 'bmcp_enabled_tools' );
define( 'BMCP_ACTIVITY_LOG_OPTION',  'bmcp_activity_log' );
define( 'BMCP_REST_NAMESPACE',       'bricks-mcp/v1' );

// Bricks DB key fallbacks (used when Bricks constants not yet defined)
define( 'BMCP_DB_PAGE_CONTENT',      '_bricks_page_content_2' );
define( 'BMCP_DB_PAGE_HEADER',       '_bricks_page_header_2' );
define( 'BMCP_DB_PAGE_FOOTER',       '_bricks_page_footer_2' );
define( 'BMCP_DB_PAGE_SETTINGS',     '_bricks_page_settings' );
define( 'BMCP_DB_TEMPLATE_SLUG',     'bricks_template' );
define( 'BMCP_DB_TEMPLATE_TYPE',     '_bricks_template_type' );
define( 'BMCP_DB_TEMPLATE_SETTINGS', '_bricks_template_settings' );

spl_autoload_register( function ( $class ) {
	if ( strpos( $class, 'BricksMCP\\' ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( 'BricksMCP\\' ) );
	$parts    = explode( '\\', $relative );

	// Tools sub-namespace
	if ( count( $parts ) === 2 && $parts[0] === 'Tools' ) {
		$file_name = 'class-' . str_replace( '_', '-', strtolower( $parts[1] ) ) . '.php';
		$file_path = BMCP_PLUGIN_DIR . 'includes/tools/' . $file_name;
	} else {
		$file_name = 'class-' . str_replace( '_', '-', strtolower( $parts[0] ) ) . '.php';
		$file_path = BMCP_PLUGIN_DIR . 'includes/' . $file_name;
	}

	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
} );

register_activation_hook( __FILE__, 'bmcp_activate' );
register_deactivation_hook( __FILE__, 'bmcp_deactivate' );

function bmcp_activate() {
	if ( get_option( BMCP_API_KEY_OPTION ) === false ) {
		$key = wp_generate_password( 32, false );
		update_option( BMCP_API_KEY_OPTION, $key, false );
	}

	$admin_users = get_users( [
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	] );

	$admin_id = ! empty( $admin_users ) ? (int) $admin_users[0] : 1;
	update_option( BMCP_ADMIN_USER_OPTION, $admin_id, false );

	$default_tools = [
		'pages'       => true,
		'templates'   => true,
		'settings'    => true,
		'posts'       => true,
		'media'       => true,
		'woocommerce' => true,
		'site'        => true,
	];
	if ( get_option( BMCP_ENABLED_TOOLS_OPTION ) === false ) {
		update_option( BMCP_ENABLED_TOOLS_OPTION, $default_tools, false );
	}
}

function bmcp_deactivate() {
	// Intentionally leave options/data intact so re-activation preserves the API key.
}

add_action( 'plugins_loaded', 'bmcp_init' );

function bmcp_init() {
	// Admin
	if ( is_admin() ) {
		new \BricksMCP\Admin();
	}

	// REST API (always needed — frontend requests use REST)
	new \BricksMCP\Rest_API();
}
