<?php
namespace BricksMCP\Tools;

use BricksMCP\Bricks_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and apply pre-built design system presets (global classes, color palette,
 * CSS variables, theme styles) bundled in assets/design-systems/.
 *
 * Currently ships one preset: BricksTemplate Library design system.
 * JSON files must be placed in assets/design-systems/ — filenames are preset-keyed.
 */
class Tool_Design_System extends Tool_Base {

	private static array $presets = [
		'brickstemplate' => [
			'label'       => 'BricksTemplate Library',
			'description' => 'Professional design system from BricksTemplate Library. Includes 50 global classes, 43-color palette, 100+ CSS variables, and theme style.',
			'files'       => [
				'classes'      => 'bricks-css-classes.json',
				'palette'      => 'bricks-color-palette.json',
				'css_vars'     => 'bricks-css-variables.json',
				'theme_style'  => 'bricks-theme-style.json',
			],
		],
	];

	public function define(): array {
		return [
			[
				'name'        => 'bricks_get_design_system',
				'description' => 'Get the complete design system reference for the active preset (default: BricksTemplate). Returns: (1) class_reference — all global class IDs grouped by role (typography, buttons, icons, spacing) so you know exactly which _cssGlobalClasses ID to use; (2) semantic_variables — all CSS variable names grouped by category; (3) theme_style — default typographic and spacing settings. Use this when building with BricksTemplate templates to look up the correct class ID for any role (e.g. btn=icnnin, h1=mrlpju, section-padding-l=jvlvec).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'preset' => [
							'type'        => 'string',
							'description' => 'Design system preset to query. Currently supported: "brickstemplate". Default: "brickstemplate".',
						],
					],
				],
			],
			[
				'name'        => 'bricks_apply_design_system',
				'description' => 'Import a complete design system preset into Bricks Builder on this site. Writes global classes (with exact IDs from the preset), color palette, CSS variables (appended to customCss), and theme style. This is a destructive operation — it replaces the existing color palette and merges/overwrites matching global class IDs. Always call bricks_get_session_context first to check what is already configured, and confirm with the user before running. Requires manage_options capability.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'preset' => [
							'type'        => 'string',
							'description' => 'Design system preset to apply. Currently supported: "brickstemplate". Default: "brickstemplate".',
						],
						'components' => [
							'type'        => 'array',
							'description' => 'Which parts to apply. Options: "classes", "palette", "css_vars", "theme_style". Default: all four.',
							'items'       => [ 'type' => 'string' ],
						],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_get_design_system':
				return $this->get_design_system( $this->str_arg( $args, 'preset', 'brickstemplate' ) );
			case 'bricks_apply_design_system':
				return $this->apply_design_system(
					$this->str_arg( $args, 'preset', 'brickstemplate' ),
					isset( $args['components'] ) && is_array( $args['components'] ) ? $args['components'] : [ 'classes', 'palette', 'css_vars', 'theme_style' ]
				);
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	// -------------------------------------------------------------------------

	private function get_design_system( string $preset ): array|\WP_Error {
		if ( ! isset( self::$presets[ $preset ] ) ) {
			return $this->err( "Unknown preset '{$preset}'. Available: " . implode( ', ', array_keys( self::$presets ) ) );
		}

		return [
			'preset'             => $preset,
			'label'              => self::$presets[ $preset ]['label'],
			'class_reference'    => $this->get_class_reference( $preset ),
			'semantic_variables' => $this->get_semantic_variables( $preset ),
			'theme_style'        => $this->get_theme_style_reference( $preset ),
			'note'               => 'Use class IDs in _cssGlobalClasses: ["id"]. Use variable names in {"raw": "var(--name)"} color objects or plain string settings.',
		];
	}

	private function apply_design_system( string $preset, array $components ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		if ( ! isset( self::$presets[ $preset ] ) ) {
			return $this->err( "Unknown preset '{$preset}'. Available: " . implode( ', ', array_keys( self::$presets ) ) );
		}

		$files   = self::$presets[ $preset ]['files'];
		$dir     = BMCP_PLUGIN_DIR . 'assets/design-systems/';
		$applied = [];
		$skipped = [];
		$errors  = [];

		foreach ( $components as $component ) {
			if ( ! isset( $files[ $component ] ) ) {
				$errors[] = "Unknown component '{$component}'.";
				continue;
			}

			$path = $dir . $files[ $component ];

			if ( ! file_exists( $path ) ) {
				$skipped[] = "{$component} (file not found: assets/design-systems/{$files[$component]} — upload this file to the plugin directory to enable this component)";
				continue;
			}

			$json = file_get_contents( $path );
			$data = json_decode( $json, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$errors[] = "{$component}: JSON parse error — " . json_last_error_msg();
				continue;
			}

			switch ( $component ) {
				case 'classes':
					$result = $this->apply_classes( $data );
					break;
				case 'palette':
					$result = $this->apply_palette( $data );
					break;
				case 'css_vars':
					$result = $this->apply_css_vars( $data );
					break;
				case 'theme_style':
					$result = $this->apply_theme_style( $data );
					break;
				default:
					$result = new \WP_Error( 'bmcp_invalid', "Unknown component: {$component}" );
			}

			if ( is_wp_error( $result ) ) {
				$errors[] = "{$component}: " . $result->get_error_message();
			} else {
				$applied[] = $component;
			}
		}

		return [
			'preset'      => $preset,
			'applied'     => $applied,
			'skipped'     => $skipped,
			'errors'      => $errors,
			'success'     => empty( $errors ),
			'note'        => empty( $skipped )
				? 'All components applied. Call bricks_get_session_context to verify the design system is active.'
				: 'Some components skipped due to missing JSON files. Place the BricksTemplate exported JSON files in the assets/design-systems/ directory of the plugin.',
		];
	}

	// -------------------------------------------------------------------------
	// Apply helpers
	// -------------------------------------------------------------------------

	private function apply_classes( array $data ): true|\WP_Error {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error( 'bmcp_invalid', 'Classes JSON must be a non-empty array.' );
		}

		$existing = Bricks_Data::get_global_classes();

		// Index existing by ID for fast lookup
		$existing_by_id = [];
		foreach ( $existing as $i => $c ) {
			if ( ! empty( $c['id'] ) ) {
				$existing_by_id[ $c['id'] ] = $i;
			}
		}

		foreach ( $data as $new_class ) {
			if ( empty( $new_class['id'] ) || empty( $new_class['name'] ) ) {
				continue;
			}
			if ( isset( $existing_by_id[ $new_class['id'] ] ) ) {
				// Overwrite existing
				$existing[ $existing_by_id[ $new_class['id'] ] ] = $new_class;
			} else {
				$existing[] = $new_class;
			}
		}

		$key = defined( 'BRICKS_DB_GLOBAL_CLASSES' ) ? BRICKS_DB_GLOBAL_CLASSES : 'bricks_global_classes';
		update_option( $key, array_values( $existing ) );

		return true;
	}

	private function apply_palette( array $data ): true|\WP_Error {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error( 'bmcp_invalid', 'Palette JSON must be a non-empty array.' );
		}
		Bricks_Data::update_color_palette( $data );
		return true;
	}

	private function apply_css_vars( array $data ): true|\WP_Error {
		// Accepts either a CSS string (single key "css") or a flat key→value map
		if ( isset( $data['css'] ) && is_string( $data['css'] ) ) {
			$css_block = $data['css'];
		} else {
			// Build :root { --var: value; } block from key→value map
			$lines = [ ':root {' ];
			foreach ( $data as $key => $value ) {
				if ( is_string( $key ) && is_string( $value ) ) {
					$lines[] = "  {$key}: {$value};";
				}
			}
			$lines[]   = '}';
			$css_block = implode( "\n", $lines );
		}

		$settings   = Bricks_Data::get_global_settings();
		$custom_css = $settings['customCss'] ?? '';

		// Remove any existing :root block injected by this tool (between our markers)
		$marker_start = '/* BricksTemplate design-system variables — auto-generated, do not edit */';
		$marker_end   = '/* /BricksTemplate design-system variables */';

		$start_pos = strpos( $custom_css, $marker_start );
		$end_pos   = strpos( $custom_css, $marker_end );

		if ( $start_pos !== false && $end_pos !== false ) {
			$custom_css = substr( $custom_css, 0, $start_pos )
				. substr( $custom_css, $end_pos + strlen( $marker_end ) );
		}

		$custom_css = trim( $custom_css ) . "\n\n{$marker_start}\n{$css_block}\n{$marker_end}\n";
		Bricks_Data::update_global_settings( [ 'customCss' => $custom_css ] );

		return true;
	}

	private function apply_theme_style( array $data ): true|\WP_Error {
		// Accepts either a single style object {id, label, settings} or a keyed map
		if ( isset( $data['id'] ) ) {
			// Single style object
			$id       = $data['id'];
			$settings = $data;
		} elseif ( ! empty( $data ) ) {
			// Keyed map: first entry
			$id       = array_key_first( $data );
			$settings = $data[ $id ];
		} else {
			return new \WP_Error( 'bmcp_invalid', 'Theme style JSON must be a non-empty object.' );
		}

		Bricks_Data::update_theme_style( $id, $settings );
		return true;
	}

	// -------------------------------------------------------------------------
	// Reference data (hardcoded from BricksTemplate export)
	// -------------------------------------------------------------------------

	private function get_class_reference( string $preset ): array {
		if ( $preset !== 'brickstemplate' ) {
			return [];
		}

		return [
			'note'         => 'Apply via _cssGlobalClasses: ["id"]. IDs are opaque — always use these exact values.',
			'typography'   => [
				'h1'           => [ 'id' => 'mrlpju', 'description' => 'H1 heading style' ],
				'h2'           => [ 'id' => 'rblwep', 'description' => 'H2 heading style' ],
				'h3'           => [ 'id' => 'xdlghw', 'description' => 'H3 heading style' ],
				'h4'           => [ 'id' => 'ewinig', 'description' => 'H4 heading style' ],
				'h5'           => [ 'id' => 'jvxxkf', 'description' => 'H5 heading style' ],
				'h6'           => [ 'id' => 'vunewz', 'description' => 'H6 heading style' ],
				'body-text-s'  => [ 'id' => 'zcdcay', 'description' => 'Small body text — var(--body-text-s)' ],
				'body-text-m'  => [ 'id' => 'xnbiuz', 'description' => 'Medium body text — var(--body-text-m)' ],
				'body-text-l'  => [ 'id' => 'xsebeu', 'description' => 'Large body text — var(--body-text-l)' ],
				'text-xxl'     => [ 'id' => 'qgkzrm', 'description' => 'Text size XXL' ],
				'text-xl'      => [ 'id' => 'ksgwrx', 'description' => 'Text size XL' ],
				'text-l'       => [ 'id' => 'qyzhvi', 'description' => 'Text size L' ],
				'text-m'       => [ 'id' => 'vkhjpn', 'description' => 'Text size M' ],
				'text-s'       => [ 'id' => 'pizkge', 'description' => 'Text size S' ],
				'text-xs'      => [ 'id' => 'urbdzt', 'description' => 'Text size XS' ],
			],
			'font_weights' => [
				'font-200' => [ 'id' => 'zpzdlr', 'description' => 'Font weight 200 (extralight)' ],
				'font-300' => [ 'id' => 'zqoeza', 'description' => 'Font weight 300 (light)' ],
				'font-400' => [ 'id' => 'efjjje', 'description' => 'Font weight 400 (regular)' ],
				'font-500' => [ 'id' => 'vzhlkp', 'description' => 'Font weight 500 (medium)' ],
				'font-600' => [ 'id' => 'helzar', 'description' => 'Font weight 600 (semibold)' ],
				'font-700' => [ 'id' => 'znqixu', 'description' => 'Font weight 700 (bold)' ],
				'font-800' => [ 'id' => 'dkyvht', 'description' => 'Font weight 800 (extrabold)' ],
				'font-900' => [ 'id' => 'toishz', 'description' => 'Font weight 900 (black)' ],
			],
			'buttons'      => [
				'btn'           => [ 'id' => 'icnnin', 'description' => 'Primary button — bg var(--color-primary), white text' ],
				'btn-secondary' => [ 'id' => 'vnwkta', 'description' => 'Secondary button style' ],
				'btn--outline'  => [ 'id' => 'gmbdcm', 'description' => 'Outlined button variant' ],
				'btn--round'    => [ 'id' => 'rhizcr', 'description' => 'Fully rounded button' ],
				'btn__white'    => [ 'id' => 'tccljv', 'description' => 'White button (for dark backgrounds)' ],
				'btn__black'    => [ 'id' => 'jgucoo', 'description' => 'Black button variant' ],
				'btn--xs'       => [ 'id' => 'thpbrm', 'description' => 'Extra-small button size' ],
				'btn--s'        => [ 'id' => 'hyqjzl', 'description' => 'Small button size' ],
				'btn--m'        => [ 'id' => 'aswtwb', 'description' => 'Medium button size' ],
				'btn--l'        => [ 'id' => 'ucbglo', 'description' => 'Large button size' ],
				'btn--xl'       => [ 'id' => 'jmpexw', 'description' => 'Extra-large button size' ],
			],
			'icons'        => [
				'icon'          => [ 'id' => 'liafdz', 'description' => 'Default icon style' ],
				'icon--outline' => [ 'id' => 'ptlosy', 'description' => 'Outlined icon variant' ],
				'icon--filled'  => [ 'id' => 'iptope', 'description' => 'Filled icon variant' ],
			],
			'spacing'      => [
				'section-padding-l'  => [ 'id' => 'jvlvec', 'description' => 'Large section padding — var(--section-padding-l) top/bottom' ],
				'section-padding-m'  => [ 'id' => 'xqjblc', 'description' => 'Medium section padding' ],
				'section-padding-s'  => [ 'id' => 'kmknar', 'description' => 'Small section padding' ],
				'section-padding-xs' => [ 'id' => 'pkvazj', 'description' => 'Extra-small section padding' ],
			],
		];
	}

	private function get_semantic_variables( string $preset ): array {
		if ( $preset !== 'brickstemplate' ) {
			return [];
		}

		return [
			'note'     => 'Use in {"raw": "var(--name)"} for color objects, or plain string for padding/font-size settings.',
			'colors'   => [
				'--color-primary'    => 'Primary brand color',
				'--color-secondary'  => 'Secondary brand color',
				'--color-accent'     => 'Accent / highlight color',
				'--color-text'       => 'Default body text color',
				'--color-heading'    => 'Heading text color',
				'--color-bg'         => 'Page background color',
				'--color-border'     => 'Default border color',
				'--color-white'      => 'White utility',
				'--color-black'      => 'Black utility',
			],
			'spacing'  => [
				'--space-xs'           => 'Extra-small spacing token',
				'--space-s'            => 'Small spacing token',
				'--space-m'            => 'Medium spacing token',
				'--space-l'            => 'Large spacing token',
				'--space-xl'           => 'Extra-large spacing token',
				'--section-padding-l'  => 'Large section top/bottom padding',
				'--section-padding-m'  => 'Medium section top/bottom padding',
				'--section-padding-s'  => 'Small section top/bottom padding',
				'--section-padding-xs' => 'Extra-small section padding',
				'--section-padding-lr' => 'Section left/right padding',
			],
			'layout'   => [
				'--container-width' => 'Max content width (applied by container CSS class)',
				'--grid-2'          => 'Two-column grid template',
				'--grid-3'          => 'Three-column grid template',
				'--grid-4'          => 'Four-column grid template',
			],
			'radius'   => [
				'--radius-s'    => 'Small border radius',
				'--radius-m'    => 'Medium border radius',
				'--radius-l'    => 'Large border radius',
				'--radius-full' => 'Full / pill border radius',
			],
			'typography' => [
				'--body-text-s'  => 'Small body font size',
				'--body-text-m'  => 'Medium body font size',
				'--body-text-l'  => 'Large body font size',
				'--font-base'    => 'Base font family',
				'--font-heading' => 'Heading font family',
			],
		];
	}

	private function get_theme_style_reference( string $preset ): array {
		if ( $preset !== 'brickstemplate' ) {
			return [];
		}

		return [
			'id'    => 'brickstemplate',
			'label' => 'BricksTemplate',
			'settings' => [
				'typography' => [
					'typographyBody'     => [
						'color'       => [ 'raw' => 'var(--color-text)' ],
						'font-size'   => 'var(--body-text-s)',
						'font-family' => 'Inter',
						'line-height' => '1.5',
					],
					'typographyHeadings' => [
						'color'       => [ 'raw' => 'var(--color-heading)' ],
						'font-family' => 'Inter',
						'line-height' => '1.3',
					],
				],
				'section'    => [
					'padding' => [
						'top'    => 'var(--section-padding-l)',
						'bottom' => 'var(--section-padding-l)',
						'left'   => 'var(--section-padding-lr)',
						'right'  => 'var(--section-padding-lr)',
					],
				],
				'container'  => [
					'width' => 'var(--container-width)',
				],
			],
		];
	}
}
