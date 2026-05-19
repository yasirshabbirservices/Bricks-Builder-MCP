<?php
namespace BricksMCP;

use BricksMCP\Tools\Tool_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tools_Registry {

	/** @var array<string, Tool_Base> Tool instances keyed by tool name */
	private array $tools = [];

	/** @var array<string, Tool_Base> Tool instance keyed by class slug (group) */
	private array $handlers = [];

	public function load_all(): void {
		$groups = [
			new Tools\Tool_Pages(),
			new Tools\Tool_Templates(),
			new Tools\Tool_Settings(),
			new Tools\Tool_Elements(),
			new Tools\Tool_Posts(),
			new Tools\Tool_Media(),
			new Tools\Tool_WooCommerce(),
			new Tools\Tool_Site(),
		];

		foreach ( $groups as $handler ) {
			$this->register( $handler );
		}
	}

	public function register( Tool_Base $handler ): void {
		$definitions = $handler->define();
		foreach ( $definitions as $def ) {
			$name = $def['name'] ?? null;
			if ( $name ) {
				$this->tools[ $name ]    = $handler;
				$this->handlers[ $name ] = $handler;
			}
		}
	}

	public function get_all_definitions(): array {
		$seen        = [];
		$definitions = [];

		foreach ( $this->tools as $name => $handler ) {
			$handler_id = spl_object_id( $handler );
			if ( isset( $seen[ $handler_id ] ) ) {
				continue;
			}
			$seen[ $handler_id ] = true;
			$definitions         = array_merge( $definitions, $handler->define() );
		}

		// Filter by enabled tool groups
		$enabled = $this->get_enabled_groups();
		$group_map = $this->get_tool_group_map();

		return array_values( array_filter( $definitions, function ( $def ) use ( $enabled, $group_map ) {
			$name  = $def['name'] ?? '';
			$group = $group_map[ $name ] ?? 'site';
			return $enabled[ $group ] ?? true;
		} ) );
	}

	public function dispatch( string $name, array $args ): array|\WP_Error {
		$enabled   = $this->get_enabled_groups();
		$group_map = $this->get_tool_group_map();
		$group     = $group_map[ $name ] ?? 'site';

		if ( ! ( $enabled[ $group ] ?? true ) ) {
			return new \WP_Error( 'bmcp_disabled', "Tool group '{$group}' is disabled. Enable it in Bricks MCP settings." );
		}

		$handler = $this->tools[ $name ] ?? null;
		if ( ! $handler ) {
			return new \WP_Error( 'bmcp_unknown_tool', "Unknown tool: {$name}" );
		}

		$result = $handler->execute( $name, $args );

		$this->log_activity( $name, $result );

		return $result;
	}

	public function get_tool( string $name ): ?Tool_Base {
		return $this->tools[ $name ] ?? null;
	}

	// -------------------------------------------------------------------------
	// Group resolution
	// -------------------------------------------------------------------------

	private function get_tool_group_map(): array {
		return [
			// pages
			'bricks_list_pages'    => 'pages',
			'bricks_get_page'      => 'pages',
			'bricks_create_page'   => 'pages',
			'bricks_update_page'   => 'pages',
			'bricks_delete_page'   => 'pages',
			// templates
			'bricks_list_templates'          => 'templates',
			'bricks_get_template'            => 'templates',
			'bricks_create_template'         => 'templates',
			'bricks_update_template'         => 'templates',
			'bricks_delete_template'         => 'templates',
			'bricks_set_template_conditions' => 'templates',
			// settings
			'bricks_get_global_settings'    => 'settings',
			'bricks_update_global_settings' => 'settings',
			'bricks_get_color_palette'      => 'settings',
			'bricks_update_color_palette'   => 'settings',
			'bricks_get_global_classes'     => 'settings',
			'bricks_create_global_class'    => 'settings',
			'bricks_update_global_class'    => 'settings',
			'bricks_get_theme_styles'       => 'settings',
			'bricks_update_theme_styles'    => 'settings',
			// elements (always on — it's reference data)
			'bricks_get_elements' => 'site',
			// posts
			'bricks_list_post_types' => 'posts',
			'bricks_list_posts'      => 'posts',
			'bricks_get_post'        => 'posts',
			'bricks_create_post'     => 'posts',
			'bricks_update_post'     => 'posts',
			'bricks_delete_post'     => 'posts',
			// media
			'bricks_list_media'             => 'media',
			'bricks_upload_media_from_url'  => 'media',
			'bricks_get_media'              => 'media',
			// woocommerce
			'bricks_list_products'            => 'woocommerce',
			'bricks_get_product'              => 'woocommerce',
			'bricks_list_product_categories'  => 'woocommerce',
			// site
			'bricks_get_site_info'           => 'site',
			'bricks_get_custom_instructions' => 'site',
			'bricks_get_system_prompt'       => 'site',
		];
	}

	private function get_enabled_groups(): array {
		$stored = get_option( BMCP_ENABLED_TOOLS_OPTION, [] );
		$defaults = [
			'pages'       => true,
			'templates'   => true,
			'settings'    => true,
			'posts'       => true,
			'media'       => true,
			'woocommerce' => true,
			'site'        => true,
		];
		return array_merge( $defaults, is_array( $stored ) ? $stored : [] );
	}

	// -------------------------------------------------------------------------
	// Activity log
	// -------------------------------------------------------------------------

	private function log_activity( string $tool_name, array|\WP_Error $result ): void {
		$log = get_option( BMCP_ACTIVITY_LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}

		array_unshift( $log, [
			'timestamp' => time(),
			'tool'      => $tool_name,
			'success'   => ! is_wp_error( $result ),
			'error'     => is_wp_error( $result ) ? $result->get_error_message() : null,
		] );

		// Keep last 20 entries
		$log = array_slice( $log, 0, 20 );
		update_option( BMCP_ACTIVITY_LOG_OPTION, $log, false );
	}
}
