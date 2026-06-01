<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Low-level data access layer for Bricks Builder.
 * All reads/writes go through this class so element format and CSS regeneration
 * are handled consistently.
 */
class Bricks_Data {

	// -------------------------------------------------------------------------
	// DB key helpers (use constants if Bricks is loaded, else literals)
	// -------------------------------------------------------------------------

	public static function meta_key( string $area = 'content' ): string {
		switch ( $area ) {
			case 'header':
				return defined( 'BRICKS_DB_PAGE_HEADER' ) ? BRICKS_DB_PAGE_HEADER : BMCP_DB_PAGE_HEADER;
			case 'footer':
				return defined( 'BRICKS_DB_PAGE_FOOTER' ) ? BRICKS_DB_PAGE_FOOTER : BMCP_DB_PAGE_FOOTER;
			default:
				return defined( 'BRICKS_DB_PAGE_CONTENT' ) ? BRICKS_DB_PAGE_CONTENT : BMCP_DB_PAGE_CONTENT;
		}
	}

	public static function template_area( string $type ): string {
		if ( $type === 'header' ) {
			return 'header';
		}
		if ( $type === 'footer' ) {
			return 'footer';
		}
		return 'content';
	}

	// -------------------------------------------------------------------------
	// Element ID generation
	// -------------------------------------------------------------------------

	public static function generate_element_id(): string {
		return substr( md5( uniqid( '', true ) ), 0, 6 );
	}

	// -------------------------------------------------------------------------
	// Page / Template element read-write
	// -------------------------------------------------------------------------

	public static function get_elements( int $post_id, string $area = 'content' ): array {
		$key  = self::meta_key( $area );
		$data = get_post_meta( $post_id, $key, true );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Write elements to post meta and trigger Bricks CSS regeneration.
	 *
	 * @param int    $post_id
	 * @param array  $elements  Array of Bricks element objects
	 * @param string $area      'content' | 'header' | 'footer'
	 */
	public static function set_elements( int $post_id, array $elements, string $area = 'content' ): bool|\WP_Error {
		$validated = self::normalize_elements( $elements );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$key = self::meta_key( $area );
		update_post_meta( $post_id, $key, $validated );

		// Trigger Bricks save_post hook so it regenerates CSS files
		wp_update_post( [
			'ID'               => $post_id,
			'post_modified'    => current_time( 'mysql' ),
			'post_modified_gmt'=> current_time( 'mysql', true ),
		] );

		return true;
	}

	/**
	 * Normalize and validate an elements array.
	 * Auto-generates IDs for elements missing them and fixes parent/children consistency.
	 */
	public static function normalize_elements( array $elements ): array|\WP_Error {
		if ( empty( $elements ) ) {
			return [];
		}

		// Pass 1: ensure every element has required keys
		$indexed = [];
		foreach ( $elements as $i => &$el ) {
			if ( ! is_array( $el ) ) {
				return new \WP_Error( 'bmcp_invalid_element', "Element at index {$i} is not an object." );
			}

			if ( empty( $el['name'] ) || ! is_string( $el['name'] ) ) {
				return new \WP_Error( 'bmcp_invalid_element', "Element at index {$i} is missing 'name'." );
			}

			if ( empty( $el['id'] ) ) {
				$el['id'] = self::generate_element_id();
			}

			if ( ! isset( $el['parent'] ) ) {
				$el['parent'] = '0';
			}

			if ( ! isset( $el['children'] ) || ! is_array( $el['children'] ) ) {
				$el['children'] = [];
			}

			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
				$el['settings'] = [];
			}

			$indexed[ $el['id'] ] = &$el;
		}
		unset( $el );

		// Pass 2: rebuild parent/children for consistency
		// Build a children-index from each element's children array
		$children_map = [];
		foreach ( $elements as $el ) {
			foreach ( $el['children'] as $child_id ) {
				$children_map[ $child_id ] = $el['id'];
			}
		}

		// Correct parent fields from children map
		foreach ( $elements as &$el ) {
			if ( isset( $children_map[ $el['id'] ] ) ) {
				$el['parent'] = $children_map[ $el['id'] ];
			} else {
				$el['parent'] = '0';
			}
		}
		unset( $el );

		return array_values( $elements );
	}

	// -------------------------------------------------------------------------
	// Template settings
	// -------------------------------------------------------------------------

	public static function get_template_type( int $post_id ): string {
		$key = defined( 'BRICKS_DB_TEMPLATE_TYPE' ) ? BRICKS_DB_TEMPLATE_TYPE : BMCP_DB_TEMPLATE_TYPE;
		return (string) get_post_meta( $post_id, $key, true );
	}

	public static function set_template_type( int $post_id, string $type ): void {
		$key = defined( 'BRICKS_DB_TEMPLATE_TYPE' ) ? BRICKS_DB_TEMPLATE_TYPE : BMCP_DB_TEMPLATE_TYPE;
		update_post_meta( $post_id, $key, $type );
	}

	public static function get_template_settings( int $post_id ): array {
		$key  = defined( 'BRICKS_DB_TEMPLATE_SETTINGS' ) ? BRICKS_DB_TEMPLATE_SETTINGS : BMCP_DB_TEMPLATE_SETTINGS;
		$data = get_post_meta( $post_id, $key, true );
		return is_array( $data ) ? $data : [];
	}

	public static function set_template_conditions( int $post_id, array $conditions ): void {
		$key      = defined( 'BRICKS_DB_TEMPLATE_SETTINGS' ) ? BRICKS_DB_TEMPLATE_SETTINGS : BMCP_DB_TEMPLATE_SETTINGS;
		$settings = self::get_template_settings( $post_id );
		$settings['templateConditions'] = $conditions;
		update_post_meta( $post_id, $key, $settings );
	}

	public static function get_page_settings( int $post_id ): array {
		$key  = defined( 'BRICKS_DB_PAGE_SETTINGS' ) ? BRICKS_DB_PAGE_SETTINGS : BMCP_DB_PAGE_SETTINGS;
		$data = get_post_meta( $post_id, $key, true );
		return is_array( $data ) ? $data : [];
	}

	public static function set_page_settings( int $post_id, array $settings ): void {
		$key = defined( 'BRICKS_DB_PAGE_SETTINGS' ) ? BRICKS_DB_PAGE_SETTINGS : BMCP_DB_PAGE_SETTINGS;
		update_post_meta( $post_id, $key, $settings );
	}

	// -------------------------------------------------------------------------
	// Global options
	// -------------------------------------------------------------------------

	public static function get_global_settings(): array {
		$key  = defined( 'BRICKS_DB_GLOBAL_SETTINGS' ) ? BRICKS_DB_GLOBAL_SETTINGS : 'bricks_global_settings';
		$data = get_option( $key, [] );
		return is_array( $data ) ? $data : [];
	}

	public static function update_global_settings( array $new_settings ): void {
		$key      = defined( 'BRICKS_DB_GLOBAL_SETTINGS' ) ? BRICKS_DB_GLOBAL_SETTINGS : 'bricks_global_settings';
		$existing = self::get_global_settings();
		update_option( $key, array_merge( $existing, $new_settings ) );
	}

	public static function get_color_palette(): array {
		$key  = defined( 'BRICKS_DB_COLOR_PALETTE' ) ? BRICKS_DB_COLOR_PALETTE : 'bricks_color_palette';
		$data = get_option( $key, [] );
		return is_array( $data ) ? $data : [];
	}

	public static function update_color_palette( array $palette ): void {
		$key = defined( 'BRICKS_DB_COLOR_PALETTE' ) ? BRICKS_DB_COLOR_PALETTE : 'bricks_color_palette';
		update_option( $key, $palette );
	}

	public static function get_global_classes(): array {
		$key  = defined( 'BRICKS_DB_GLOBAL_CLASSES' ) ? BRICKS_DB_GLOBAL_CLASSES : 'bricks_global_classes';
		$data = get_option( $key, [] );
		return is_array( $data ) ? $data : [];
	}

	public static function add_global_class( array $class_data ): array|\WP_Error {
		if ( empty( $class_data['name'] ) ) {
			return new \WP_Error( 'bmcp_invalid', 'Global class requires a "name" field.' );
		}

		if ( empty( $class_data['id'] ) ) {
			$class_data['id'] = self::generate_element_id();
		}

		$classes = self::get_global_classes();
		$classes[] = $class_data;

		$key = defined( 'BRICKS_DB_GLOBAL_CLASSES' ) ? BRICKS_DB_GLOBAL_CLASSES : 'bricks_global_classes';
		update_option( $key, $classes );

		return $class_data;
	}

	public static function update_global_class( string $id, array $updates ): array|\WP_Error {
		$classes = self::get_global_classes();
		$found   = false;

		foreach ( $classes as &$class ) {
			if ( isset( $class['id'] ) && $class['id'] === $id ) {
				$class  = array_merge( $class, $updates );
				$class['id'] = $id; // ensure id cannot change
				$found  = true;
				$result = $class;
				break;
			}
		}
		unset( $class );

		if ( ! $found ) {
			return new \WP_Error( 'bmcp_not_found', "Global class '{$id}' not found." );
		}

		$key = defined( 'BRICKS_DB_GLOBAL_CLASSES' ) ? BRICKS_DB_GLOBAL_CLASSES : 'bricks_global_classes';
		update_option( $key, $classes );

		return $result;
	}

	public static function get_theme_styles(): array {
		$key  = defined( 'BRICKS_DB_THEME_STYLES' ) ? BRICKS_DB_THEME_STYLES : 'bricks_theme_styles';
		$data = get_option( $key, [] );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Update or create a theme style.
	 *
	 * Bricks expects a dual structure for theme styles to work correctly:
	 * - Top-level keys (typography, links, buttons, etc.) → used for CSS output
	 * - Nested `settings` key mirroring those same values → used by the Bricks builder UI panels
	 *
	 * This method auto-creates the nested `settings` wrapper from top-level keys
	 * so the AI doesn't need to maintain both copies manually.
	 */
	public static function update_theme_style( string $style_id, array $settings ): array {
		$styles = self::get_theme_styles();

		if ( ! isset( $styles[ $style_id ] ) ) {
			$styles[ $style_id ] = [ 'id' => $style_id, 'label' => $style_id ];
		}

		$styles[ $style_id ] = array_merge( $styles[ $style_id ], $settings );
		$styles[ $style_id ]['id'] = $style_id;

		// Auto-populate the nested 'settings' wrapper for Bricks UI compatibility.
		// The builder reads from settings.typography, settings.links, etc. for its panels.
		$ui_keys = [
			'typography', 'links', 'buttons', 'section', 'container',
			'conditions', 'headings', 'colors', 'forms', 'misc',
		];

		$nested = $styles[ $style_id ]['settings'] ?? [];
		foreach ( $ui_keys as $uk ) {
			if ( isset( $styles[ $style_id ][ $uk ] ) && ! isset( $nested[ $uk ] ) ) {
				$nested[ $uk ] = $styles[ $style_id ][ $uk ];
			}
		}
		if ( ! empty( $nested ) ) {
			$styles[ $style_id ]['settings'] = $nested;
		}

		$key = defined( 'BRICKS_DB_THEME_STYLES' ) ? BRICKS_DB_THEME_STYLES : 'bricks_theme_styles';
		update_option( $key, $styles );

		return $styles[ $style_id ];
	}

	public static function get_global_variables(): array {
		$key  = defined( 'BRICKS_DB_GLOBAL_VARIABLES' ) ? BRICKS_DB_GLOBAL_VARIABLES : 'bricks_global_variables';
		$data = get_option( $key, [] );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Update Style Manager global variables (stored in bricks_global_variables option).
	 *
	 * These are the CSS variables visible in Bricks → Settings → Style Manager,
	 * including HSL-decomposed color tokens (--primary-h, --primary-s, --primary-l).
	 *
	 * @param array $variables Full replacement array of variable groups/entries.
	 * @return array Updated variables.
	 */
	public static function update_global_variables( array $variables ): array {
		$key = defined( 'BRICKS_DB_GLOBAL_VARIABLES' ) ? BRICKS_DB_GLOBAL_VARIABLES : 'bricks_global_variables';
		update_option( $key, $variables );
		return $variables;
	}

	// -------------------------------------------------------------------------
	// Post helper
	// -------------------------------------------------------------------------

	public static function has_bricks_data( int $post_id ): bool {
		$key  = self::meta_key( 'content' );
		$data = get_post_meta( $post_id, $key, true );
		return is_array( $data ) && count( $data ) > 0;
	}

	public static function format_post( \WP_Post $post, bool $include_elements = false ): array {
		$result = [
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'slug'            => $post->post_name,
			'status'          => $post->post_status,
			'type'            => $post->post_type,
			'url'             => get_permalink( $post->ID ) ?: '',
			'has_bricks_data' => self::has_bricks_data( $post->ID ),
			'modified'        => $post->post_modified,
		];

		if ( $include_elements ) {
			$result['elements']        = self::get_elements( $post->ID, 'content' );
			$result['header_elements'] = self::get_elements( $post->ID, 'header' );
			$result['footer_elements'] = self::get_elements( $post->ID, 'footer' );
			$result['page_settings']   = self::get_page_settings( $post->ID );
		}

		return $result;
	}
}
