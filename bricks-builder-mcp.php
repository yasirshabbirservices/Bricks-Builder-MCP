<?php
/**
 * Plugin Name: Bricks Builder MCP
 * Plugin URI:  https://github.com/yasirshabbirservices/Bricks-Builder-MCP
 * Description: Model Context Protocol (MCP) server for Bricks Builder — lets Claude Code and any MCP-compatible AI build and design your site directly.
 * Version:     1.12.7
 * Author:      Yasir Shabbir
 * Author URI:  https://yasirshabbir.com
 * License:     MIT
 * Text Domain: bricks-builder-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMCP_VERSION',                  '1.12.7' );
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
define( 'BMCP_SECONDARY_KEYS_OPTION',   'bmcp_secondary_keys' );
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
ELEMENT SELECTION — ALWAYS USE NATIVE BRICKS ELEMENTS FIRST
* Never use a generic div, container, or code element when a purpose-built Bricks element exists.
  Priority order: interactive/purpose-built element → Bricks component/template → layout element → code (last resort)
* Logo slider / image carousel → use slider-nested (NOT sections or divs in a row)
* FAQ / accordion → use accordion element
* Tabbed content → use tabs element
* Collapsible → use toggle element
* Modal / lightbox → use popup element
* Repeating content → use posts (query loop) element
* Navigation → use nav-menu or off-canvas elements
* Reusable sections → embed via template element

MOBILE-FIRST DESIGN — MANDATORY
* Design for mobile first (390px viewport) before desktop. Every layout must be fully functional and visually correct on mobile before desktop styles are added.
* Default settings must produce a single-column, stacked layout that works on small screens.
* All interactive elements must have minimum 44×44px touch targets.
* Use 100svh (not 100vh) for full-height sections — svh accounts for mobile browser chrome.
* Never use fixed pixel widths that overflow on small screens. Prefer: 100%, min(600px, 100%), clamp(), or auto-fill grid.
* Reduce section padding on mobile: use spacing-xl or spacing-lg on mobile, spacing-section on desktop.

CSS CUSTOM PROPERTIES — MANDATORY
* Every repeatable value must be a CSS variable — no raw hex colors, no hardcoded px spacing, no hardcoded font sizes.
* Required variable categories: color tokens (--color-primary, --color-text, etc.), spacing scale (--spacing-xs through --spacing-section), typography (--font-size-xs through --font-size-4xl using clamp()), layout (--container-width, --border-radius-*), effects (--shadow-*, --transition).
* Font sizes must use clamp() for fluid scaling: clamp(min, preferred-vw, max)
* Check session context for existing variables before creating new ones — never duplicate.

DESIGN SYSTEM
* Always reuse predefined CSS variables, global classes, spacing, typography, colors, and utility patterns from the existing design system.
* Reuse existing components, templates, global styles, and utility classes before creating new ones.
* Follow the existing design system direction — never introduce a new pattern that contradicts established styles.

CROSS-BROWSER & MODERN CSS
* Use modern CSS (logical properties, clamp, container queries, CSS Grid subgrid) with @supports fallbacks for newly-available features.
* Verify cross-browser support on caniuse.com for any CSS or JS feature before using it.
* Never add vendor prefixes to universally-supported properties (flex, grid, transform, transition, border-radius, etc.).

JAVASCRIPT
* Prefer Bricks native interactions, elements, and CSS over custom JavaScript.
* When JS is necessary: use ES2020+, const/let (never var), async/await, event delegation, passive listeners, IntersectionObserver for scroll effects.
* Never use innerHTML with user-supplied data — use textContent.
* Debounce resize/input events, throttle scroll events.

DYNAMIC CONTENT
* Use ACF, ACF Pro, or JetEngine whenever available for post types, meta fields, relationships, options pages, and dynamic content structures.
* Prefer native Bricks dynamic data, query loops, conditions, and interactions over custom PHP or sandbox functions.

PERFORMANCE
* Minimize scripts and dependencies — check if Bricks interactions or native CSS replace JS.
* Lazy-load all below-fold images (_loading: lazy). Do NOT lazy-load above-fold hero images.
* Use fetchpriority="high" on the hero/LCP image.
* Set explicit width + height on all images to prevent CLS.
* Use WebP format for photos. Use SVG for logos and icons.
* Defer non-critical JavaScript.

ACCESSIBILITY — WCAG 2.1 AA
* Semantic HTML: use correct Bricks tag settings (section, main, nav, header, footer, article, aside).
* One h1 per page. Never skip heading levels. Never use headings for styling.
* All images need alt text (_alt setting). Decorative images: empty alt + aria-hidden="true".
* All form inputs need associated labels. Required fields must be communicated visually and via required attribute.
* Color contrast: 4.5:1 for normal text, 3:1 for large text.
* All interactive elements keyboard-reachable. Never remove focus outlines without a visible replacement.

SECURITY (WordPress)
* Sanitize and validate all inputs. Escape all outputs. Use nonce verification. Apply capability checks.
* Secure all custom API and AJAX endpoints.

MEDIA LIBRARY
* Upload and manage assets in appropriate folders/categories using HappyFiles Pro if available.

QUALITY STANDARDS — MANDATORY BEFORE ANY WRITE
* Load bricks-elements skill before building any element array.
* Load mobile-first skill before starting any layout.
* Load the relevant skills for the current task (css-best-practices, accessibility, performance, etc.)
* Always validate with bricks_validate_payload before writing. Never skip.
* Confirm with the user before any global or destructive operation (color palette, theme styles, replace across all pages).
* Never read or modify payment credentials or sensitive user data.

PRE-RELEASE CHECKLIST
  - Responsive behavior on mobile_portrait (390px), tablet, and desktop
  - Dynamic data accuracy
  - Accessibility: heading hierarchy, alt text, contrast, keyboard navigation
  - Cross-browser compatibility
  - Error handling and fallback states
  - Performance: LCP, CLS, no unused scripts
  - No console errors
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

	// Snippets Manager — registers CPT and execution hooks (always active)
	new \BricksMCP\Snippets_Manager();

	// REST API (always needed — frontend requests use REST)
	new \BricksMCP\Rest_API();

	// GitHub update checker (admin + cron contexts only)
	if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		new \BricksMCP\Updater();
	}
}
