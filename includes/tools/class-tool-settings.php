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
				'name'        => 'bricks_code_execution_status',
				'description' => 'Check whether Bricks code execution is enabled on this site. Call before writing any code element (name: "code"), SVG element with inline code (name: "svg", source: "code"), or any query loop with useQueryEditor. If disabled, PHP/code elements will not execute on the frontend. Returns: enabled (bool), locked (bool), message (string with instructions to enable if needed). Note: this plugin automatically generates code signatures (wp_hash) when writing elements — you do not need to set the "signature" field yourself.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
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
				'description' => 'Extract all CSS custom properties (--variable-name: value) from BOTH global customCss AND Style Manager global variables (bricks_global_variables). Use this to discover all available design tokens before styling elements — never guess variable names.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_get_global_variables',
				'description' => 'Get Style Manager global variables (stored in bricks_global_variables option). These are the CSS variables managed via Bricks → Settings → Style Manager, including HSL-decomposed color tokens. Separate from customCss variables.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_update_global_variables',
				'description' => 'Replace Style Manager global variables (bricks_global_variables option). This is the proper way to update CSS variables shown in Bricks Style Manager (e.g., HSL color tokens). Pass the full variables array.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'variables' => [
							'type'        => 'array',
							'description' => 'Full replacement array of variable groups. Each group has id, name, and variables array. Example: [{"id":"colors","name":"Colors","variables":[{"id":"v1","name":"--primary-h","value":"160"}]}]',
							'items'       => [ 'type' => 'object' ],
						],
					],
					'required' => [ 'variables' ],
				],
			],
			[
				'name'        => 'bricks_get_custom_css',
				'description' => 'Get the raw customCss content from Bricks global settings. Use this when you need to read or incrementally edit the full CSS, not just extract variable names.',
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
				'description' => 'Update or create a theme style entry. Merges with the existing style. '
					. 'Bricks uses a DUAL structure: top-level keys (typography, links, buttons, section, container, conditions) '
					. 'generate CSS output, and a nested "settings" key mirrors those same values for the builder UI panels. '
					. 'The server auto-creates the nested "settings" wrapper from your top-level keys, so you only need to provide the top-level structure. '
					. 'Example: {"label":"My Style", "typography":{"font-family":"Inter","font-size":"16px","color":{"raw":"var(--text)"}}, '
					. '"links":{"color":{"raw":"var(--primary)"},"text-decoration":"none"}, '
					. '"conditions":[{"condition":"all"}]}',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'style_id' => [ 'type' => 'string', 'description' => 'Unique style identifier (lowercase, hyphens, e.g. "ys-styles")' ],
						'settings' => [
							'type'        => 'object',
							'description' => 'Style settings object. Must include "label". Top-level keys: typography, links, buttons, section, container, conditions, headings, colors, forms, misc. The nested "settings" wrapper is auto-generated.',
						],
					],
					'required' => [ 'style_id', 'settings' ],
				],
			],
			[
				'name'        => 'bricks_get_breakpoints',
				'description' => 'Get all Bricks breakpoints including custom ones. Returns each breakpoint with key, label, width, icon, and flags (base, custom, paused, edited). The "base" breakpoint is where unsuffixed CSS properties apply. In desktop-first mode, desktop is base. In mobile-first mode, the smallest breakpoint is base. Understanding breakpoints is critical for responsive design — element settings use breakpoint-suffixed keys (e.g. _padding:tablet_portrait).',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_update_breakpoints',
				'description' => 'Update Bricks breakpoints. Pass the FULL breakpoints array (replacement, not merge). Each breakpoint needs: key (string), label (string), width (integer, px), icon (string). Optional flags: base (boolean — only ONE breakpoint should be base), custom (boolean), paused (boolean — disables without deleting). Order matters: store width-descending for desktop-first, width-descending with base=true on the smallest for mobile-first. After updating, CSS files are automatically regenerated. Also set customBreakpoints=true in global settings if adding custom breakpoints.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'breakpoints' => [
							'type'        => 'array',
							'description' => 'Full breakpoints array. Example: [{"key":"desktop","label":"Desktop","width":1280,"icon":"ti-desktop"},{"key":"tablet_portrait","label":"Tablet","width":1024,"icon":"ti-tablet"},{"key":"mobile_landscape","label":"Mobile Landscape","width":768,"icon":"ti-mobile"},{"key":"mobile","label":"Mobile","width":480,"icon":"ti-mobile","base":true}]',
							'items'       => [ 'type' => 'object' ],
						],
						'mobile_first' => [
							'type'        => 'boolean',
							'description' => 'Set to true to enable mobile-first mode. The smallest breakpoint will have base=true and CSS uses min-width media queries. Default: false (desktop-first, max-width).',
						],
					],
					'required' => [ 'breakpoints' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_code_execution_status':
				return Bricks_Data::get_code_execution_status();
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
			case 'bricks_get_global_variables':
				return $this->get_global_variables();
			case 'bricks_update_global_variables':
				return $this->update_global_variables( $args );
			case 'bricks_get_custom_css':
				return $this->get_custom_css();
			case 'bricks_list_global_fonts':
				return $this->list_global_fonts();
			case 'bricks_get_theme_styles':
				return $this->get_theme_styles();
			case 'bricks_update_theme_styles':
				return $this->update_theme_styles( $args );
			case 'bricks_get_breakpoints':
				return $this->get_breakpoints();
			case 'bricks_update_breakpoints':
				return $this->update_breakpoints( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function get_global_settings(): array {
		$settings = Bricks_Data::get_global_settings();
		$filtered = $this->filter_sensitive( $settings );

		// Replace raw customCss (can be 15–50 KB on design-system sites) with a size hint.
		// Use bricks_get_custom_css for the raw content or bricks_get_css_variables for extracted variables.
		if ( isset( $filtered['customCss'] ) ) {
			$filtered['customCss_note'] = sprintf(
				'%d chars — call bricks_get_custom_css for raw CSS content, or bricks_get_css_variables for extracted variables from both customCss and Style Manager.',
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
		$variables = [];

		// Source 1: customCss in global settings
		$settings   = Bricks_Data::get_global_settings();
		$custom_css = $settings['customCss'] ?? '';

		if ( $custom_css ) {
			preg_match_all( '/(-{2}[\w-]+)\s*:\s*([^;}\n]+)/', $custom_css, $matches, PREG_SET_ORDER );
			foreach ( $matches as $m ) {
				$variables[ trim( $m[1] ) ] = [
					'value'  => trim( $m[2] ),
					'source' => 'customCss',
				];
			}
		}

		// Source 2: Style Manager global variables (bricks_global_variables option)
		$global_vars = Bricks_Data::get_global_variables();
		if ( is_array( $global_vars ) ) {
			foreach ( $global_vars as $group ) {
				$group_name = $group['name'] ?? 'unnamed';
				$entries    = $group['variables'] ?? [];
				foreach ( $entries as $entry ) {
					$var_name = $entry['name'] ?? '';
					$var_val  = $entry['value'] ?? '';
					if ( $var_name !== '' ) {
						// Ensure -- prefix
						$key = str_starts_with( $var_name, '--' ) ? $var_name : '--' . $var_name;
						$variables[ $key ] = [
							'value'  => $var_val,
							'source' => 'style_manager',
							'group'  => $group_name,
						];
					}
				}
			}
		}

		// Flatten for backward compatibility — also provide source info
		$flat = [];
		foreach ( $variables as $name => $info ) {
			$flat[ $name ] = $info['value'];
		}

		return [
			'variables'        => $flat,
			'variables_detail' => $variables,
			'count'            => count( $variables ),
			'tip'              => empty( $variables )
				? 'No CSS variables found. Use actual hex values from bricks_get_color_palette instead.'
				: 'Use these in color objects as {"raw": "var(--variable-name)"} or in plain string settings. Check "source" in variables_detail to see where each variable is defined.',
		];
	}

	private function get_global_variables(): array {
		$variables = Bricks_Data::get_global_variables();
		return [
			'variables' => $variables,
			'count'     => is_array( $variables ) ? count( $variables ) : 0,
			'tip'       => 'These are Style Manager variables (Bricks → Settings → Style Manager). Use bricks_update_global_variables to modify them. Separate from customCss.',
		];
	}

	private function update_global_variables( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		$variables = $args['variables'] ?? null;
		if ( ! is_array( $variables ) ) {
			return $this->err( '"variables" must be an array of variable groups.' );
		}

		$result = Bricks_Data::update_global_variables( $variables );
		return [
			'success'   => true,
			'variables' => $result,
			'message'   => 'Style Manager global variables updated.',
		];
	}

	private function get_custom_css(): array {
		$settings   = Bricks_Data::get_global_settings();
		$custom_css = $settings['customCss'] ?? '';
		return [
			'customCss' => $custom_css,
			'length'    => strlen( $custom_css ),
			'tip'       => 'Use bricks_update_global_settings with {"settings":{"customCss":"..."}} to update. For Style Manager variables, use bricks_update_global_variables instead.',
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
	// Breakpoints
	// -------------------------------------------------------------------------

	private function get_breakpoints(): array {
		$bp_key      = defined( 'BRICKS_DB_BREAKPOINTS' ) ? BRICKS_DB_BREAKPOINTS : 'bricks_breakpoints';
		$breakpoints = get_option( $bp_key, [] );
		$gs_key      = defined( 'BRICKS_DB_GLOBAL_SETTINGS' ) ? BRICKS_DB_GLOBAL_SETTINGS : 'bricks_global_settings';
		$settings    = get_option( $gs_key, [] );

		$custom_enabled = ! empty( $settings['customBreakpoints'] );

		$base_key = 'desktop';
		foreach ( $breakpoints as $bp ) {
			if ( ! empty( $bp['base'] ) ) {
				$base_key = $bp['key'] ?? 'desktop';
				break;
			}
		}

		$is_mobile_first = $base_key !== 'desktop';

		return [
			'breakpoints'      => is_array( $breakpoints ) ? $breakpoints : [],
			'count'            => is_array( $breakpoints ) ? count( $breakpoints ) : 0,
			'custom_enabled'   => $custom_enabled,
			'base_breakpoint'  => $base_key,
			'mode'             => $is_mobile_first ? 'mobile-first' : 'desktop-first',
			'tip'              => $is_mobile_first
				? 'Mobile-first: unsuffixed element settings apply to the base (smallest) breakpoint. Use :tablet_portrait, :mobile_landscape, :desktop suffixes for larger screens.'
				: 'Desktop-first: unsuffixed element settings apply to desktop. Use :tablet_portrait, :mobile_landscape, :mobile suffixes for smaller screens.',
		];
	}

	private function update_breakpoints( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		$breakpoints  = $this->arr_arg( $args, 'breakpoints' );
		$mobile_first = $this->bool_arg( $args, 'mobile_first', false );

		if ( empty( $breakpoints ) ) {
			return $this->err( '"breakpoints" must be a non-empty array.' );
		}

		foreach ( $breakpoints as $i => $bp ) {
			if ( empty( $bp['key'] ) || empty( $bp['label'] ) || empty( $bp['width'] ) ) {
				return $this->err( "Breakpoint at index {$i} is missing required fields: key, label, width." );
			}
		}

		if ( $mobile_first ) {
			foreach ( $breakpoints as &$bp ) {
				unset( $bp['base'] );
			}
			unset( $bp );
			$widths = array_column( $breakpoints, 'width' );
			$min_idx = array_search( min( $widths ), $widths, true );
			$breakpoints[ $min_idx ]['base'] = true;
		}

		$bp_key = defined( 'BRICKS_DB_BREAKPOINTS' ) ? BRICKS_DB_BREAKPOINTS : 'bricks_breakpoints';
		update_option( $bp_key, $breakpoints );

		$gs_key  = defined( 'BRICKS_DB_GLOBAL_SETTINGS' ) ? BRICKS_DB_GLOBAL_SETTINGS : 'bricks_global_settings';
		$settings = get_option( $gs_key, [] );
		if ( ! is_array( $settings ) ) $settings = [];

		$has_custom = false;
		foreach ( $breakpoints as $bp ) {
			if ( ! empty( $bp['custom'] ) ) {
				$has_custom = true;
				break;
			}
		}
		if ( $has_custom ) {
			$settings['customBreakpoints'] = true;
			update_option( $gs_key, $settings );
		}

		if ( class_exists( '\Bricks\Breakpoints' ) ) {
			\Bricks\Breakpoints::init_breakpoints();
			\Bricks\Breakpoints::regenerate_bricks_css_files();
		}

		return [
			'success'      => true,
			'breakpoints'  => $breakpoints,
			'mobile_first' => $mobile_first,
			'message'      => 'Breakpoints updated and CSS files regenerated.',
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
