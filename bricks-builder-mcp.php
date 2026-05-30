<?php
/**
 * Plugin Name: Bricks Builder MCP
 * Plugin URI:  https://yasirshabbir.com
 * Description: Model Context Protocol (MCP) server for Bricks Builder — lets Claude Code and any MCP-compatible AI build and design your site directly.
 * Version:     1.5.5
 * Author:      Yasir Shabbir
 * Author URI:  https://yasirshabbir.com
 * License:     MIT
 * Text Domain: bricks-builder-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMCP_VERSION',                  '1.5.5' );
define( 'BMCP_PLUGIN_FILE',              __FILE__ );
define( 'BMCP_PLUGIN_DIR',               plugin_dir_path( __FILE__ ) );
define( 'BMCP_PLUGIN_URL',               plugin_dir_url( __FILE__ ) );
define( 'BMCP_API_KEY_OPTION',           'bmcp_api_key' );
define( 'BMCP_ADMIN_USER_OPTION',        'bmcp_admin_user_id' );
define( 'BMCP_INSTRUCTIONS_OPTION',      'bmcp_custom_instructions' );
define( 'BMCP_ENABLED_TOOLS_OPTION',     'bmcp_enabled_tools' );
define( 'BMCP_ACTIVITY_LOG_OPTION',      'bmcp_activity_log' );
define( 'BMCP_TOOL_STATES_OPTION',       'bmcp_tool_states' );
define( 'BMCP_ADVANCED_OPTION',          'bmcp_advanced_settings' );
define( 'BMCP_BUSINESS_PROFILE_OPTION',  'bmcp_business_profile' );
define( 'BMCP_REST_NAMESPACE',           'bricks-mcp/v1' );
define( 'BMCP_DB_VERSION_OPTION',    'bmcp_db_version' );

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
	\BricksMCP\History_Manager::create_table();

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

	if ( get_option( BMCP_INSTRUCTIONS_OPTION ) === false ) {
		$default_instructions = <<<'INSTRUCTIONS'
* Use Advanced Custom Fields (ACF), ACF Pro, or JetEngine whenever available instead of relying on native WordPress functions or building unnecessary custom solutions for post types, taxonomies, meta fields, relationships, options pages, or dynamic content structures.

* Follow the existing design system consistently. Always reuse predefined CSS variables, global classes, spacing, typography, colors, and utility patterns already implemented on the website.

* Prefer native Bricks Builder functionality before introducing custom PHP, JavaScript, or sandbox functions. Use built-in dynamic data, query loops, conditions, interactions, and templating whenever possible. Fully test all custom implementations before deployment.

* Keep the media library properly organized. Upload and manage assets inside their appropriate folders/categories using HappyFiles Pro if it exists to maintain a scalable and maintainable media structure.

* Reuse existing components, templates, global styles, and utility classes before creating new ones to maintain consistency and reduce maintenance overhead.

* Maintain clean, modular, and scalable architecture. Avoid duplicate logic, unnecessary abstractions, plugin bloat, and over-engineering.

* Prioritize performance-first development:
  - Minimize unnecessary scripts and dependencies
  - Reduce database queries where possible
  - Optimize DOM structure and asset loading
  - Avoid heavy frontend libraries unless required

* Follow WordPress security best practices:
  - Sanitize and validate all inputs
  - Escape outputs correctly
  - Use nonce verification where needed
  - Apply proper capability/permission checks
  - Secure all API and AJAX endpoints

* Build mobile-first, responsive layouts by default and ensure consistency across desktop, tablet, and mobile breakpoints.

* Ensure all custom code is production-ready, maintainable, and documented where necessary for future scalability and team collaboration.

* Before releasing any feature or update, always verify:
  - Responsive behavior
  - Dynamic data accuracy
  - Accessibility basics
  - Cross-browser compatibility
  - Error handling and fallback states
  - Performance impact
  - Console/network errors
INSTRUCTIONS;
		update_option( BMCP_INSTRUCTIONS_OPTION, $default_instructions, false );
	}
}

function bmcp_deactivate() {
	// Intentionally leave options/data intact so re-activation preserves the API key.
}

add_action( 'plugins_loaded', 'bmcp_init' );

function bmcp_init() {
	// Create history table on first run after upgrade (idempotent via dbDelta)
	if ( get_option( BMCP_DB_VERSION_OPTION ) !== BMCP_VERSION ) {
		\BricksMCP\History_Manager::create_table();
		update_option( BMCP_DB_VERSION_OPTION, BMCP_VERSION, false );
	}

	// Admin
	if ( is_admin() ) {
		new \BricksMCP\Admin();
	}

	// REST API (always needed — frontend requests use REST)
	new \BricksMCP\Rest_API();

	// GitHub update checker (admin + cron contexts only)
	if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		new \BricksMCP\Updater();
	}
}
