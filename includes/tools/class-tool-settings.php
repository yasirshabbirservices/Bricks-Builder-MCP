<?php
namespace BricksMCP\Tools;

use BricksMCP\Bricks_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Settings extends Tool_Base {

	// Keys that must never be read or written (security)
	private static array $blocked_patterns = [
		'/secret/i',
		'/password/i',
		'/\bkey\b/i',
		'/token/i',
		'/credential/i',
		'/smtp/i',
		'/oauth/i',
		'/api_key/i',
		'/access_key/i',
		'/private_key/i',
		'/auth_key/i',
	];

	public function define(): array {
		return [
			[
				'name'        => 'bricks_get_global_settings',
				'description' => 'Get Bricks Builder global settings (typography, spacing, custom CSS/JS, feature flags, etc.). Sensitive keys are automatically filtered out.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_update_global_settings',
				'description' => 'Update specific Bricks global settings keys. Merges with existing settings. Sensitive keys are ignored.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'settings' => [
							'type'        => 'object',
							'description' => 'Key-value pairs of settings to update. Example: {"customCss": "body { font-family: Inter; }", "disableElementManager": false}',
						],
					],
					'required' => [ 'settings' ],
				],
			],
			[
				'name'        => 'bricks_get_color_palette',
				'description' => 'Get the Bricks color palette. Returns palettes with their color entries (id, name, hex/rgb, raw).',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_update_color_palette',
				'description' => 'Replace the Bricks color palette completely. Each palette needs an id, name, and colors array. Each color needs id, name, and raw (hex or CSS value).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'palette' => [
							'type'        => 'array',
							'description' => 'Full palette replacement. Example: [{"id":"main","name":"Brand","colors":[{"id":"c1","name":"Primary","raw":"#3b82f6"}]}]',
							'items'       => [ 'type' => 'object' ],
						],
					],
					'required' => [ 'palette' ],
				],
			],
			[
				'name'        => 'bricks_get_global_classes',
				'description' => 'Get all Bricks global CSS classes. Classes can be applied to any element via _cssGlobalClasses setting.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_create_global_class',
				'description' => 'Create a new Bricks global CSS class with CSS properties. Once created, apply it to elements using _cssGlobalClasses: ["class-id"].',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'     => [ 'type' => 'string', 'description' => 'CSS class name (e.g. "btn-primary")' ],
						'settings' => [
							'type'        => 'object',
							'description' => 'CSS settings for the class. Example: {"color":{"raw":"#fff"},"background":{"color":{"raw":"#3b82f6"}},"padding":{"top":"12px","right":"24px","bottom":"12px","left":"24px"}}',
						],
					],
					'required' => [ 'name' ],
				],
			],
			[
				'name'        => 'bricks_update_global_class',
				'description' => 'Update an existing Bricks global CSS class by its ID.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'       => [ 'type' => 'string', 'description' => 'Global class ID (6-char alphanumeric from bricks_get_global_classes)' ],
						'name'     => [ 'type' => 'string', 'description' => 'New class name' ],
						'settings' => [ 'type' => 'object', 'description' => 'Updated CSS settings (merged with existing)' ],
					],
					'required' => [ 'id' ],
				],
			],
			[
				'name'        => 'bricks_delete_global_class',
				'description' => 'Permanently delete a Bricks global CSS class by its ID. Any elements using this class will lose the reference.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'string', 'description' => 'Global class ID to delete (6-char alphanumeric from bricks_get_global_classes)' ],
					],
					'required' => [ 'id' ],
				],
			],
			[
				'name'        => 'bricks_get_css_variables',
				'description' => 'Extract all CSS custom properties (--variable-name: value) defined in Bricks global settings customCss. Use this to discover available design tokens before styling elements — never guess variable names.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_list_global_fonts',
				'description' => 'List all fonts registered in Bricks global settings — Google Fonts, custom uploaded fonts, and the default theme font. Use this to pick consistent typefaces rather than guessing.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_get_theme_styles',
				'description' => 'Get all Bricks theme styles. Theme styles are reusable style presets that can be applied to elements.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_update_theme_styles',
				'description' => 'Update or create a theme style entry. Merges with the existing style if it exists.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'style_id' => [ 'type' => 'string', 'description' => 'Unique style identifier' ],
						'settings' => [
							'type'        => 'object',
							'description' => 'Style settings object. Should include "label" and element-specific CSS settings.',
						],
					],
					'required' => [ 'style_id', 'settings' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_get_global_settings':
				return $this->get_global_settings();
			case 'bricks_update_global_settings':
				return $this->update_global_settings( $args );
			case 'bricks_get_color_palette':
				return $this->get_color_palette();
			case 'bricks_update_color_palette':
				return $this->update_color_palette( $args );
			case 'bricks_get_global_classes':
				return $this->get_global_classes();
			case 'bricks_create_global_class':
				return $this->create_global_class( $args );
			case 'bricks_update_global_class':
				return $this->update_global_class( $args );
			case 'bricks_delete_global_class':
				return $this->delete_global_class( $args );
			case 'bricks_get_css_variables':
				return $this->get_css_variables();
			case 'bricks_list_global_fonts':
				return $this->list_global_fonts();
			case 'bricks_get_theme_styles':
				return $this->get_theme_styles();
			case 'bricks_update_theme_styles':
				return $this->update_theme_styles( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function get_global_settings(): array {
		$settings = Bricks_Data::get_global_settings();
		$filtered = $this->filter_sensitive( $settings );

		// Replace raw customCss (can be 15–50 KB on design-system sites) with a size hint.
		// Use bricks_get_css_variables to get the extracted variables instead.
		if ( isset( $filtered['customCss'] ) ) {
			$filtered['customCss_note'] = sprintf(
				'%d chars — call bricks_get_css_variables to get extracted CSS variables.',
				strlen( $settings['customCss'] ?? '' )
			);
			unset( $filtered['customCss'] );
		}

		return $filtered;
	}

	private function update_global_settings( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		$new = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : [];
		if ( empty( $new ) ) {
			return $this->err( '"settings" must be a non-empty object.' );
		}

		// Remove blocked keys silently
		$new = $this->filter_sensitive( $new );
		Bricks_Data::update_global_settings( $new );

		return [ 'success' => true, 'updated_keys' => array_keys( $new ), 'message' => 'Global settings updated.' ];
	}

	private function get_color_palette(): array {
		return [ 'palette' => Bricks_Data::get_color_palette() ];
	}

	private function update_color_palette( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		$palette = $this->arr_arg( $args, 'palette' );
		if ( empty( $palette ) ) {
			return $this->err( '"palette" must be a non-empty array.' );
		}

		Bricks_Data::update_color_palette( $palette );
		return [ 'success' => true, 'message' => 'Color palette updated.' ];
	}

	private function get_global_classes(): array {
		return [ 'classes' => Bricks_Data::get_global_classes() ];
	}

	private function create_global_class( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		$name = $this->str_arg( $args, 'name' );
		if ( ! $name ) return $this->err( '"name" is required.' );

		$class_data = [
			'name'     => sanitize_text_field( $name ),
			'settings' => isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : [],
		];

		return Bricks_Data::add_global_class( $class_data );
	}

	private function update_global_class( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		$id = $this->str_arg( $args, 'id' );
		if ( ! $id ) return $this->err( '"id" is required.' );

		$updates = [];
		if ( isset( $args['name'] ) ) {
			$updates['name'] = sanitize_text_field( $args['name'] );
		}
		if ( isset( $args['settings'] ) && is_array( $args['settings'] ) ) {
			$updates['settings'] = $args['settings'];
		}

		return Bricks_Data::update_global_class( $id, $updates );
	}

	private function get_theme_styles(): array {
		return [ 'styles' => Bricks_Data::get_theme_styles() ];
	}

	private function update_theme_styles( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		$style_id = $this->str_arg( $args, 'style_id' );
		if ( ! $style_id ) return $this->err( '"style_id" is required.' );

		$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : [];
		$result   = Bricks_Data::update_theme_style( $style_id, $settings );

		return [ 'success' => true, 'style' => $result, 'message' => 'Theme style updated.' ];
	}

	private function delete_global_class( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		$id = $this->str_arg( $args, 'id' );
		if ( ! $id ) return $this->err( '"id" is required.' );

		$classes = Bricks_Data::get_global_classes();
		$found   = false;

		$filtered = array_values( array_filter( $classes, function ( $c ) use ( $id, &$found ) {
			if ( ( $c['id'] ?? '' ) === $id ) {
				$found = true;
				return false;
			}
			return true;
		} ) );

		if ( ! $found ) {
			return $this->err( "Global class '{$id}' not found." );
		}

		update_option( 'bricks_global_classes', $filtered );

		return [ 'success' => true, 'message' => "Global class '{$id}' deleted." ];
	}

	private function get_css_variables(): array {
		$settings   = Bricks_Data::get_global_settings();
		$custom_css = $settings['customCss'] ?? '';
		$variables  = [];

		if ( $custom_css ) {
			preg_match_all( '/(-{2}[\w-]+)\s*:\s*([^;}\n]+)/', $custom_css, $matches, PREG_SET_ORDER );
			foreach ( $matches as $m ) {
				$variables[ trim( $m[1] ) ] = trim( $m[2] );
			}
		}

		return [
			'variables' => $variables,
			'count'     => count( $variables ),
			'tip'       => empty( $variables )
				? 'No CSS variables found in customCss. Use actual hex values from bricks_get_color_palette instead.'
				: 'Use these in color objects as {"raw": "var(--variable-name)"} or in plain string settings.',
		];
	}

	private function list_global_fonts(): array {
		$settings     = Bricks_Data::get_global_settings();
		$google_fonts = $settings['googleFonts'] ?? [];
		$custom_fonts = $settings['customFonts'] ?? [];
		$theme_font   = $settings['themeFont']   ?? null;

		$fonts = [];

		foreach ( $google_fonts as $font ) {
			$fonts[] = [
				'type'    => 'google',
				'family'  => $font['font_family'] ?? ( $font['family'] ?? $font ),
				'weights' => $font['font_weight'] ?? ( $font['weights'] ?? [] ),
			];
		}

		foreach ( $custom_fonts as $font ) {
			$fonts[] = [
				'type'   => 'custom',
				'family' => $font['font_family'] ?? ( $font['family'] ?? $font ),
				'url'    => $font['url'] ?? null,
			];
		}

		if ( $theme_font ) {
			array_unshift( $fonts, [ 'type' => 'theme', 'family' => $theme_font ] );
		}

		return [
			'fonts' => $fonts,
			'count' => count( $fonts ),
			'tip'   => 'Use font-family values from this list in _typography settings to ensure consistency.',
		];
	}

	// -------------------------------------------------------------------------
	// Security helpers
	// -------------------------------------------------------------------------

	private function filter_sensitive( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( $this->is_sensitive_key( (string) $key ) ) {
				unset( $data[ $key ] );
			}
		}
		return $data;
	}

	private function is_sensitive_key( string $key ): bool {
		foreach ( self::$blocked_patterns as $pattern ) {
			if ( preg_match( $pattern, $key ) ) {
				return true;
			}
		}
		return false;
	}
}
