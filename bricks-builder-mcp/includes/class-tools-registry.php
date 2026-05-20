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
			new Tools\Tool_Menus(),
			new Tools\Tool_Components(),
			new Tools\Tool_Site(),
			new Tools\Tool_Memory(),
			new Tools\Tool_History(),
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

	/**
	 * Returns every tool name registered across all groups. Used by Admin sanitize callback.
	 */
	public static function get_all_tool_names(): array {
		return array_keys( ( new self() )->tap_load_and_get_map() );
	}

	/** Internal: load tools just enough to return the full group map. */
	private function tap_load_and_get_map(): array {
		return $this->get_tool_group_map();
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

		$tool_states = $this->get_tool_states();

		return array_values( array_filter( $definitions, function ( $def ) use ( $tool_states ) {
			$tool = $def['name'] ?? '';
			if ( array_key_exists( $tool, $tool_states ) && ! $tool_states[ $tool ] ) {
				return false;
			}
			return true;
		} ) );
	}

	public function dispatch( string $name, array $args ): array|\WP_Error {
		// Per-tool enabled check
		$tool_states = $this->get_tool_states();
		if ( array_key_exists( $name, $tool_states ) && ! $tool_states[ $name ] ) {
			return new \WP_Error( 'bmcp_disabled', "Tool '{$name}' is disabled. Enable it under Bricks MCP → Settings → Capabilities." );
		}

		$handler = $this->tools[ $name ] ?? null;
		if ( ! $handler ) {
			return new \WP_Error( 'bmcp_unknown_tool', "Unknown tool: {$name}" );
		}

		// Auto-snapshot before any write operation so the AI can restore its own mistakes
		$snapshot_info = $this->get_snapshot_info( $name, $args );
		if ( $snapshot_info !== null ) {
			History_Manager::capture( $snapshot_info[0], $snapshot_info[1], $name );
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
			// menus (always on)
			'bricks_list_nav_menus'   => 'site',
			'bricks_create_nav_menu'  => 'site',
			'bricks_get_nav_menu'     => 'site',
			'bricks_update_nav_menu'  => 'site',
			// components (always on)
			'bricks_list_components'   => 'site',
			'bricks_get_component'     => 'site',
			'bricks_create_component'  => 'site',
			'bricks_update_component'  => 'site',
			'bricks_delete_component'  => 'site',
			// site
			'bricks_get_site_info'           => 'site',
			'bricks_get_custom_instructions' => 'site',
			'bricks_get_system_prompt'       => 'site',
			'bricks_set_front_page'          => 'site',
			// memory (always on)
			'bricks_memory_list'   => 'site',
			'bricks_memory_get'    => 'site',
			'bricks_memory_add'    => 'site',
			'bricks_memory_update' => 'site',
			'bricks_memory_delete' => 'site',
			'bricks_memory_search' => 'site',
			// history / snapshots (always on)
			'bricks_snapshot_list'    => 'site',
			'bricks_snapshot_get'     => 'site',
			'bricks_snapshot_restore' => 'site',
			'bricks_snapshot_delete'  => 'site',
		];
	}

	/**
	 * Returns [post_id, area] to snapshot before the given write tool executes, or null if no snapshot needed.
	 */
	private function get_snapshot_info( string $name, array $args ): ?array {
		switch ( $name ) {
			// Page writes
			case 'bricks_update_page':
			case 'bricks_delete_page':
				return [ (int) ( $args['id'] ?? 0 ), $args['area'] ?? 'content' ];

			// Template writes
			case 'bricks_update_template':
			case 'bricks_delete_template':
			case 'bricks_set_template_conditions':
				return [ (int) ( $args['id'] ?? 0 ), 'content' ];

			// Global settings writes (post_id = 0 → global option)
			case 'bricks_update_global_settings':
				return [ 0, 'global_settings' ];
			case 'bricks_update_color_palette':
				return [ 0, 'color_palette' ];
			case 'bricks_create_global_class':
			case 'bricks_update_global_class':
				return [ 0, 'global_classes' ];
			case 'bricks_update_theme_styles':
				return [ 0, 'theme_styles' ];

			// Post writes
			case 'bricks_update_post':
			case 'bricks_delete_post':
				return [ (int) ( $args['id'] ?? 0 ), 'content' ];

			// Component writes
			case 'bricks_create_component':
			case 'bricks_update_component':
			case 'bricks_delete_component':
				return [ 0, 'components' ];

			default:
				return null;
		}
	}

	private function get_tool_states(): array {
		$stored = get_option( BMCP_TOOL_STATES_OPTION, [] );
		if ( ! is_array( $stored ) ) {
			return [];
		}
		// Default: all tools enabled. An entry in $stored must be explicitly false to disable.
		return $stored;
	}

	// -------------------------------------------------------------------------
	// Activity log
	// -------------------------------------------------------------------------

	private function log_activity( string $tool_name, array|\WP_Error $result ): void {
		$adv = get_option( BMCP_ADVANCED_OPTION, [] );
		if ( ! empty( $adv['disable_activity_log'] ) ) {
			return;
		}

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
