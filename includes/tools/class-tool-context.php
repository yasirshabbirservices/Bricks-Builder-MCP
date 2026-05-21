<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single combined call for AI session startup.
 * Returns site info + design tokens + framework detection + memories in one round-trip
 * instead of 5+ separate calls.
 */
class Tool_Context extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_get_session_context',
				'description' => 'Get all context needed at the start of an AI session in a single call: site info, color palette, global classes, CSS variables, registered fonts, active design framework (CoreFramework / OxyProps / YStudio / Advanced Themer / BricksTemplate), semantic CSS variable map, and high-priority memories. Use this INSTEAD of calling bricks_get_site_info + bricks_get_color_palette + bricks_get_global_classes + bricks_memory_list separately — it reduces startup from 5 calls to 1.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'include_memories' => [
							'type'        => 'boolean',
							'description' => 'Include high-importance memories. Default: true.',
						],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name !== 'bricks_get_session_context' ) {
			return $this->err( 'Unknown tool: ' . $name );
		}

		$include_memories = $this->bool_arg( $args, 'include_memories', true );

		$color_palette  = $this->get_color_palette();
		$global_classes = $this->get_global_classes();
		$css_variables  = $this->get_css_variables();

		$result = [
			'site_info'      => $this->get_site_info(),
			'color_palette'  => $color_palette,
			'global_classes' => $global_classes,
			'css_variables'  => $css_variables,
			'global_fonts'   => $this->get_global_fonts(),
			'framework'      => $this->detect_framework(),
		];

		if ( $include_memories ) {
			$result['high_priority_memories'] = $this->get_high_priority_memories();
		}

		// Design system detection — embed mandatory onboarding directive in the response
		// so the AI treats it as a hard requirement (tool data), not a soft guideline (system prompt).
		$has_design_system = count( $global_classes ) >= 2
			|| count( $css_variables ) >= 5
			|| count( $color_palette ) >= 3;

		if ( $has_design_system ) {
			$result['design_system_status'] = 'configured';
			$result['design_system_note']   = 'Site has a design system. Follow the global styles hierarchy: global classes → CSS variables → theme styles → palette colors → inline settings. Use _cssCustom only for pseudo-elements or complex selectors.';
		} else {
			$result['design_system_status'] = 'not_configured';
			$result['action_required']      = 'STOP. Do not proceed with any build or design task. You MUST ask the user this question first (copy it exactly):' . "\n\n"
				. '"I can see this site doesn\'t have global theme styles set up yet. How would you like to proceed?' . "\n\n"
				. '1. Already done — styles exist somewhere I didn\'t detect' . "\n"
				. '2. Set it up for me — share your brand details and I\'ll create your color palette, global classes, and typography' . "\n"
				. '3. I\'ll handle it later — just use neutral placeholders for now and proceed"' . "\n\n"
				. 'Wait for the user\'s answer before doing anything else.' . "\n\n"
				. 'If user picks 1: call bricks_get_global_classes, bricks_get_css_variables, bricks_get_theme_styles to re-check. Report what you find. Proceed with whatever exists.' . "\n"
				. 'If user picks 2: ask for primary color, accent color, text color (default #1a1a1a), background color (default #ffffff), heading font, body font, style mood (minimal/modern/bold/elegant). Then call bricks_update_color_palette, create global classes (heading-1, heading-2, body-text, btn-primary, section-padding, container) via bricks_create_global_class, call bricks_update_global_settings to register fonts. Confirm setup complete, then proceed.' . "\n"
				. 'If user picks 3: proceed using neutral fallbacks only — #1a1a1a text, #ffffff background, #0066cc primary. Do NOT use semantic_map variable names (they are placeholders not defined on this site). Tell the user styles will look generic until the design system is set up.';
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Site info (minimal — key fields only)
	// -------------------------------------------------------------------------

	private function get_site_info(): array {
		$post_types     = get_post_types( [ 'public' => true ], 'objects' );
		$active_plugins = [];

		if ( function_exists( 'get_plugins' ) ) {
			foreach ( get_option( 'active_plugins', [] ) as $plugin_file ) {
				$active_plugins[] = dirname( $plugin_file ) ?: $plugin_file;
			}
		}

		$front_page_id = (int) get_option( 'page_on_front' );

		return [
			'site_name'          => get_bloginfo( 'name' ),
			'url'                => get_site_url(),
			'wp_version'         => get_bloginfo( 'version' ),
			'bricks_version'     => defined( 'BRICKS_VERSION' ) ? BRICKS_VERSION : 'unknown',
			'bricks_mcp_version' => BMCP_VERSION,
			'active_theme'       => get_template(),
			'woocommerce_active' => class_exists( 'WooCommerce' ),
			'locale'             => get_locale(),
			'timezone'           => wp_timezone_string(),
			'front_page_id'      => $front_page_id,
			'front_page_url'     => $front_page_id ? get_permalink( $front_page_id ) : get_home_url(),
			'active_plugins'     => $active_plugins,
			'custom_post_types'  => array_map( fn( $t ) => [
				'name'  => $t->name,
				'label' => $t->label,
			], array_values( array_filter( $post_types, fn( $t ) => ! in_array( $t->name, [ 'post', 'page', 'attachment', 'bricks_template' ], true ) ) ) ),
		];
	}

	// -------------------------------------------------------------------------
	// Color palette
	// -------------------------------------------------------------------------

	private function get_color_palette(): array {
		$key = defined( 'BRICKS_DB_COLOR_PALETTE' ) ? BRICKS_DB_COLOR_PALETTE : 'bricks_color_palette';
		$raw = get_option( $key, [] );
		return is_array( $raw ) ? $raw : [];
	}

	// -------------------------------------------------------------------------
	// Global classes
	// -------------------------------------------------------------------------

	private function get_global_classes(): array {
		$key = defined( 'BRICKS_DB_GLOBAL_CLASSES' ) ? BRICKS_DB_GLOBAL_CLASSES : 'bricks_global_classes';
		$raw = get_option( $key, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		return array_values( array_map( fn( $c ) => [
			'id'   => $c['id']   ?? '',
			'name' => $c['name'] ?? '',
		], $raw ) );
	}

	// -------------------------------------------------------------------------
	// CSS Variables extracted from customCss
	// -------------------------------------------------------------------------

	private function get_css_variables(): array {
		$gs_key   = defined( 'BRICKS_DB_GLOBAL_SETTINGS' ) ? BRICKS_DB_GLOBAL_SETTINGS : 'bricks_global_settings';
		$settings = get_option( $gs_key, [] );
		$custom_css = ( is_array( $settings ) && isset( $settings['customCss'] ) ) ? (string) $settings['customCss'] : '';

		$variables = [];
		if ( preg_match_all( '/(-{2}[a-zA-Z][a-zA-Z0-9_-]*):\s*([^;}\n]+)/', $custom_css, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$variables[ trim( $m[1] ) ] = trim( $m[2] );
			}
		}

		return $variables;
	}

	// -------------------------------------------------------------------------
	// Global fonts
	// -------------------------------------------------------------------------

	private function get_global_fonts(): array {
		$gs_key   = defined( 'BRICKS_DB_GLOBAL_SETTINGS' ) ? BRICKS_DB_GLOBAL_SETTINGS : 'bricks_global_settings';
		$settings = get_option( $gs_key, [] );
		if ( ! is_array( $settings ) ) {
			return [];
		}

		$fonts = [];
		foreach ( [ 'googleFonts', 'customFonts', 'themeFont' ] as $key ) {
			if ( ! empty( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
				foreach ( $settings[ $key ] as $f ) {
					if ( ! empty( $f['family'] ) ) {
						$fonts[] = [ 'family' => $f['family'], 'source' => $key ];
					}
				}
			}
		}

		return $fonts;
	}

	// -------------------------------------------------------------------------
	// Framework detection
	// -------------------------------------------------------------------------

	private function detect_framework(): array {
		$active_plugins = get_option( 'active_plugins', [] );
		$slugs          = array_map( 'dirname', $active_plugins );

		$framework = 'none';
		$prefix    = '';

		if ( in_array( 'bricks-core-framework', $slugs, true ) || in_array( 'core-framework', $slugs, true ) ) {
			$framework = 'CoreFramework';
			$prefix    = '--cf-';
		} elseif ( in_array( 'oxyprops', $slugs, true ) ) {
			$framework = 'OxyProps';
			$prefix    = '--op-';
		} elseif ( in_array( 'ystudio-variable-builder', $slugs, true ) || in_array( 'ystudio', $slugs, true ) ) {
			$framework = 'YStudio';
			$prefix    = '--ys-';
		} elseif ( in_array( 'advanced-themer', $slugs, true ) ) {
			$framework = 'AdvancedThemer';
			$prefix    = '--at-';
		} elseif ( in_array( 'bricks-template', $slugs, true ) || in_array( 'brickstemplate', $slugs, true ) ) {
			$framework = 'BricksTemplate';
			$prefix    = '--bt-';
		}

		// Fallback: sniff CSS variable prefixes from customCss
		if ( $framework === 'none' ) {
			$css_vars = $this->get_css_variables();
			foreach ( array_keys( $css_vars ) as $var ) {
				if ( str_starts_with( $var, '--cf-' ) ) { $framework = 'CoreFramework'; $prefix = '--cf-'; break; }
				if ( str_starts_with( $var, '--op-' ) ) { $framework = 'OxyProps';       $prefix = '--op-'; break; }
				if ( str_starts_with( $var, '--ys-' ) ) { $framework = 'YStudio';        $prefix = '--ys-'; break; }
				if ( str_starts_with( $var, '--at-' ) ) { $framework = 'AdvancedThemer'; $prefix = '--at-'; break; }
			}
		}

		return [
			'framework'    => $framework,
			'prefix'       => $prefix,
			'semantic_map' => $this->build_semantic_map( $framework, $prefix ),
		];
	}

	private function build_semantic_map( string $framework, string $prefix ): array {
		// Generic fallback map (vanilla Bricks without a design system)
		$generic = [
			'color_primary'   => 'var(--color-primary)',
			'color_secondary' => 'var(--color-secondary)',
			'color_text'      => 'var(--color-text)',
			'color_heading'   => 'var(--color-heading)',
			'color_bg'        => 'var(--color-bg)',
			'color_border'    => 'var(--color-border)',
			'color_white'     => 'var(--color-white)',
			'space_xs'        => 'var(--space-xs)',
			'space_s'         => 'var(--space-s)',
			'space_m'         => 'var(--space-m)',
			'space_l'         => 'var(--space-l)',
			'space_xl'        => 'var(--space-xl)',
			'radius_s'        => 'var(--radius-s)',
			'radius_m'        => 'var(--radius-m)',
			'radius_l'        => 'var(--radius-l)',
			'container_width' => 'var(--container-width)',
			'font_base'       => 'var(--font-family-base)',
			'font_heading'    => 'var(--font-family-heading)',
		];

		// If no framework detected, try to derive from actual CSS variables
		if ( $framework === 'none' ) {
			$css_vars = $this->get_css_variables();
			if ( ! empty( $css_vars ) ) {
				$map = [];
				foreach ( $generic as $role => $default_var ) {
					// Strip var( ) to get the key
					preg_match( '/var\((--[a-zA-Z0-9_-]+)\)/', $default_var, $m );
					$key = $m[1] ?? '';
					if ( $key && isset( $css_vars[ $key ] ) ) {
						$map[ $role ] = $default_var;
					}
				}
				return empty( $map ) ? $generic : $map;
			}
			return $generic;
		}

		// Framework-specific maps
		$maps = [
			'CoreFramework' => [
				'color_primary'   => "var({$prefix}color-primary)",
				'color_secondary' => "var({$prefix}color-secondary)",
				'color_text'      => "var({$prefix}color-text)",
				'color_heading'   => "var({$prefix}color-heading)",
				'color_bg'        => "var({$prefix}color-bg)",
				'color_border'    => "var({$prefix}color-border)",
				'color_white'     => "var({$prefix}color-white)",
				'space_xs'        => "var({$prefix}space-xs)",
				'space_s'         => "var({$prefix}space-s)",
				'space_m'         => "var({$prefix}space-m)",
				'space_l'         => "var({$prefix}space-l)",
				'space_xl'        => "var({$prefix}space-xl)",
				'radius_s'        => "var({$prefix}radius-s)",
				'radius_m'        => "var({$prefix}radius-m)",
				'radius_l'        => "var({$prefix}radius-l)",
				'container_width' => "var({$prefix}container-width)",
				'font_base'       => "var({$prefix}font-family-base)",
				'font_heading'    => "var({$prefix}font-family-heading)",
			],
			'OxyProps' => [
				'color_primary'   => "var({$prefix}brand)",
				'color_secondary' => "var({$prefix}brand-2)",
				'color_text'      => "var({$prefix}text-1)",
				'color_heading'   => "var({$prefix}text-1)",
				'color_bg'        => "var({$prefix}surface-1)",
				'color_border'    => "var({$prefix}surface-3)",
				'color_white'     => "var({$prefix}gray-0)",
				'space_xs'        => "var({$prefix}size-1)",
				'space_s'         => "var({$prefix}size-2)",
				'space_m'         => "var({$prefix}size-3)",
				'space_l'         => "var({$prefix}size-5)",
				'space_xl'        => "var({$prefix}size-7)",
				'radius_s'        => "var({$prefix}radius-2)",
				'radius_m'        => "var({$prefix}radius-3)",
				'radius_l'        => "var({$prefix}radius-4)",
				'container_width' => "var({$prefix}size-content-3)",
				'font_base'       => "var({$prefix}font-sans)",
				'font_heading'    => "var({$prefix}font-heading)",
			],
			'YStudio' => [
				'color_primary'   => "var({$prefix}color-primary)",
				'color_secondary' => "var({$prefix}color-secondary)",
				'color_text'      => "var({$prefix}color-body)",
				'color_heading'   => "var({$prefix}color-heading)",
				'color_bg'        => "var({$prefix}color-background)",
				'color_border'    => "var({$prefix}color-border)",
				'color_white'     => "var({$prefix}color-white)",
				'space_xs'        => "var({$prefix}spacing-xs)",
				'space_s'         => "var({$prefix}spacing-sm)",
				'space_m'         => "var({$prefix}spacing-md)",
				'space_l'         => "var({$prefix}spacing-lg)",
				'space_xl'        => "var({$prefix}spacing-xl)",
				'radius_s'        => "var({$prefix}radius-sm)",
				'radius_m'        => "var({$prefix}radius-md)",
				'radius_l'        => "var({$prefix}radius-lg)",
				'container_width' => "var({$prefix}container-width)",
				'font_base'       => "var({$prefix}font-body)",
				'font_heading'    => "var({$prefix}font-heading)",
			],
			'AdvancedThemer' => [
				'color_primary'   => "var({$prefix}primary)",
				'color_secondary' => "var({$prefix}secondary)",
				'color_text'      => "var({$prefix}body-color)",
				'color_heading'   => "var({$prefix}heading-color)",
				'color_bg'        => "var({$prefix}body-bg)",
				'color_border'    => "var({$prefix}border-color)",
				'color_white'     => "#ffffff",
				'space_xs'        => "var({$prefix}space-1)",
				'space_s'         => "var({$prefix}space-2)",
				'space_m'         => "var({$prefix}space-3)",
				'space_l'         => "var({$prefix}space-4)",
				'space_xl'        => "var({$prefix}space-5)",
				'radius_s'        => "var({$prefix}radius-sm)",
				'radius_m'        => "var({$prefix}radius-md)",
				'radius_l'        => "var({$prefix}radius-lg)",
				'container_width' => "var({$prefix}container-xl)",
				'font_base'       => "var({$prefix}font-family-base)",
				'font_heading'    => "var({$prefix}font-family-headings)",
			],
			'BricksTemplate' => [
				'color_primary'   => "var({$prefix}primary)",
				'color_secondary' => "var({$prefix}secondary)",
				'color_text'      => "var({$prefix}text)",
				'color_heading'   => "var({$prefix}heading)",
				'color_bg'        => "var({$prefix}background)",
				'color_border'    => "var({$prefix}border)",
				'color_white'     => "var({$prefix}white)",
				'space_xs'        => "var({$prefix}space-xs)",
				'space_s'         => "var({$prefix}space-s)",
				'space_m'         => "var({$prefix}space-m)",
				'space_l'         => "var({$prefix}space-l)",
				'space_xl'        => "var({$prefix}space-xl)",
				'radius_s'        => "var({$prefix}radius-s)",
				'radius_m'        => "var({$prefix}radius-m)",
				'radius_l'        => "var({$prefix}radius-l)",
				'container_width' => "var({$prefix}container)",
				'font_base'       => "var({$prefix}font-base)",
				'font_heading'    => "var({$prefix}font-heading)",
			],
		];

		return $maps[ $framework ] ?? $generic;
	}

	// -------------------------------------------------------------------------
	// High-priority memories
	// -------------------------------------------------------------------------

	private function get_high_priority_memories(): array {
		if ( ! class_exists( '\BricksMCP\Memory_Manager' ) ) {
			return [];
		}
		return \BricksMCP\Memory_Manager::get_high_importance();
	}
}
