<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only design consistency audit tool.
 * Scans all Bricks pages and templates for design drift:
 * hardcoded colors outside the palette, font stacks not matching
 * the business profile, and spacing values outside the design system.
 */
class Tool_Audit extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_audit_design_consistency',
				'description' => 'Scan all Bricks pages and templates for design inconsistencies: hardcoded hex colors that don\'t match the site\'s color palette, inline font families that don\'t match the configured typography, and suspicious spacing values. Returns a structured report with severity levels and suggested fixes. Read-only — no changes are made. Use the report to plan targeted fixes with bricks_replace_content or bricks_update_page.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'scope'              => [ 'type' => 'string', 'description' => 'Where to audit: all | pages | templates (default: all).' ],
						'include_colors'     => [ 'type' => 'boolean', 'description' => 'Check for hardcoded hex colors not in the palette (default: true).' ],
						'include_typography' => [ 'type' => 'boolean', 'description' => 'Check for inline font-family values not matching the configured fonts (default: true).' ],
						'include_spacing'    => [ 'type' => 'boolean', 'description' => 'Check for arbitrary px spacing values not matching the design system scale (default: false — can be noisy).' ],
						'severity'           => [ 'type' => 'string', 'description' => 'Minimum severity to include: all | warning | error (default: all).' ],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name !== 'bricks_audit_design_consistency' ) {
			return $this->err( 'Unknown tool: ' . $name );
		}
		return $this->audit( $args );
	}

	// -------------------------------------------------------------------------

	private function audit( array $args ): array|\WP_Error {
		$scope              = $this->str_arg( $args, 'scope', 'all' );
		$include_colors     = $this->bool_arg( $args, 'include_colors', true );
		$include_typography = $this->bool_arg( $args, 'include_typography', true );
		$include_spacing    = $this->bool_arg( $args, 'include_spacing', false );
		$min_severity       = $this->str_arg( $args, 'severity', 'all' );

		// Build reference data
		$palette_hexes  = $this->get_palette_hexes();
		$profile_fonts  = $this->get_profile_fonts();
		$issues         = [];
		$pages_scanned  = 0;

		// Get all posts with Bricks content
		$post_types = [];
		if ( $scope === 'all' || $scope === 'pages' ) {
			$post_types[] = 'page';
		}
		if ( $scope === 'all' || $scope === 'templates' ) {
			$post_types[] = defined( 'BRICKS_DB_TEMPLATE_SLUG' ) ? BRICKS_DB_TEMPLATE_SLUG : 'bricks_template';
		}
		if ( empty( $post_types ) ) {
			$post_types = [ 'page', 'bricks_template' ];
		}

		$query = new \WP_Query( [
			'post_type'      => $post_types,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => BMCP_DB_PAGE_CONTENT, 'compare' => 'EXISTS' ],
				[ 'key' => BMCP_DB_PAGE_HEADER,  'compare' => 'EXISTS' ],
				[ 'key' => BMCP_DB_PAGE_FOOTER,  'compare' => 'EXISTS' ],
			],
		] );

		$meta_key_map = [
			BMCP_DB_PAGE_CONTENT => 'content',
			BMCP_DB_PAGE_HEADER  => 'header',
			BMCP_DB_PAGE_FOOTER  => 'footer',
		];

		foreach ( $query->posts as $post_id ) {
			$pages_scanned++;
			$title     = get_the_title( $post_id );
			$post_type = get_post_type( $post_id );

			foreach ( [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER ] as $meta_key ) {
				$elements = get_post_meta( $post_id, $meta_key, true );
				if ( ! is_array( $elements ) ) continue;

				$area = $meta_key_map[ $meta_key ] ?? $meta_key;

				foreach ( $elements as $el ) {
					if ( ! is_array( $el ) ) continue;

					$settings    = $el['settings'] ?? [];
					$element_id  = $el['id']   ?? '';
					$element_type = $el['name'] ?? '';

					// ── Color audit ────────────────────────────────────────────
					if ( $include_colors && ! empty( $palette_hexes ) ) {
						$color_fields = [
							'_color', '_background', '_borderColor',
							'_gradientFrom', '_gradientTo',
						];

						foreach ( $color_fields as $field ) {
							$val = $settings[ $field ] ?? null;
							if ( ! $val ) continue;

							// Bricks colors are either a plain hex string or {"hex":"...","raw":"..."}
							$hex = null;
							if ( is_string( $val ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $val ) ) {
								$hex = strtolower( $val );
							} elseif ( is_array( $val ) && isset( $val['hex'] ) && is_string( $val['hex'] ) ) {
								$hex = strtolower( $val['hex'] );
							}

							if ( $hex && ! in_array( $hex, $palette_hexes, true ) ) {
								$issues[] = $this->issue(
									$post_id, $title, $post_type, $area, $element_id, $element_type,
									'color',
									"Hardcoded color {$hex} in {$field} is not in the color palette.",
									"Replace with the closest palette color or CSS variable. Palette: " . implode( ', ', array_slice( $palette_hexes, 0, 6 ) ),
									'warning'
								);
							}
						}

						// Also scan _cssCustom for hex colors
						$css_custom = $settings['_cssCustom'] ?? '';
						if ( is_string( $css_custom ) ) {
							preg_match_all( '/#([0-9a-fA-F]{3,6})\b/', $css_custom, $css_hex_matches );
							foreach ( array_unique( $css_hex_matches[0] ) as $hex ) {
								$hex = strtolower( $hex );
								if ( ! in_array( $hex, $palette_hexes, true ) ) {
									$issues[] = $this->issue(
										$post_id, $title, $post_type, $area, $element_id, $element_type,
										'color',
										"Hardcoded color {$hex} in _cssCustom is not in the color palette.",
										"Move to a CSS variable or replace with a palette color.",
										'warning'
									);
								}
							}
						}
					}

					// ── Typography audit ───────────────────────────────────────
					if ( $include_typography && ! empty( $profile_fonts ) ) {
						$typo = $settings['_typography'] ?? null;
						if ( is_array( $typo ) && ! empty( $typo['font-family'] ) ) {
							$inline_font = strtolower( trim( (string) $typo['font-family'], " '\"" ) );
							$matched     = false;
							foreach ( $profile_fonts as $pf ) {
								if ( strpos( $inline_font, strtolower( $pf ) ) !== false ) {
									$matched = true;
									break;
								}
							}
							// Only flag if it's a concrete font name, not a CSS variable or system stack
							if ( ! $matched
								&& strpos( $inline_font, 'var(' ) === false
								&& strpos( $inline_font, '-apple-system' ) === false
								&& ! in_array( $inline_font, [ 'sans-serif', 'serif', 'monospace', 'inherit', 'initial', 'unset' ], true )
							) {
								$issues[] = $this->issue(
									$post_id, $title, $post_type, $area, $element_id, $element_type,
									'typography',
									"Inline font-family '{$inline_font}' does not match configured fonts: " . implode( ', ', $profile_fonts ) . '.',
									"Replace with the business profile heading or body font, or a CSS variable.",
									'warning'
								);
							}
						}
					}

					// ── Spacing audit (off by default — noisy) ─────────────────
					if ( $include_spacing ) {
						$spacing_fields = [ '_padding', '_margin' ];
						foreach ( $spacing_fields as $field ) {
							$val = $settings[ $field ] ?? null;
							if ( ! is_array( $val ) ) continue;

							foreach ( $val as $side => $px_val ) {
								if ( ! is_string( $px_val ) ) continue;
								if ( preg_match( '/^(\d+)px$/i', $px_val, $m ) ) {
									$px = (int) $m[1];
									// Flag non-zero, non-standard values (not multiples of 4 or 8)
									if ( $px > 0 && $px % 4 !== 0 ) {
										$issues[] = $this->issue(
											$post_id, $title, $post_type, $area, $element_id, $element_type,
											'spacing',
											"Arbitrary {$field}.{$side} value: {$px_val} is not a multiple of 4px.",
											"Replace with a CSS spacing variable or a value from the design system scale (4, 8, 12, 16, 24, 32, 48, 64, 80px).",
											'info'
										);
									}
								}
							}
						}
					}
				}
			}
		}

		// Filter by minimum severity
		$severity_order = [ 'info' => 0, 'warning' => 1, 'error' => 2 ];
		$min_level      = $severity_order[ $min_severity ] ?? 0;

		if ( $min_level > 0 ) {
			$issues = array_values( array_filter(
				$issues,
				fn( $i ) => ( $severity_order[ $i['severity'] ] ?? 0 ) >= $min_level
			) );
		}

		// Summarise by type
		$summary = [ 'color' => 0, 'typography' => 0, 'spacing' => 0 ];
		foreach ( $issues as $issue ) {
			$summary[ $issue['type'] ] = ( $summary[ $issue['type'] ] ?? 0 ) + 1;
		}

		return [
			'pages_scanned'  => $pages_scanned,
			'total_issues'   => count( $issues ),
			'summary'        => $summary,
			'issues'         => $issues,
			'palette_size'   => count( $palette_hexes ),
			'profile_fonts'  => $profile_fonts,
			'note'           => count( $issues ) === 0
				? 'No design inconsistencies detected. The site appears consistent with the configured palette and typography.'
				: 'Use bricks_replace_content to fix global issues, or bricks_search_elements + bricks_update_page for targeted fixes.',
		];
	}

	// -------------------------------------------------------------------------

	private function issue(
		int    $post_id,
		string $title,
		string $post_type,
		string $area,
		string $element_id,
		string $element_type,
		string $type,
		string $message,
		string $suggestion,
		string $severity
	): array {
		return [
			'severity'     => $severity,
			'type'         => $type,
			'post_id'      => $post_id,
			'title'        => $title,
			'post_type'    => $post_type,
			'area'         => $area,
			'element_id'   => $element_id,
			'element_type' => $element_type,
			'message'      => $message,
			'suggestion'   => $suggestion,
		];
	}

	/** Returns all palette hex values in lowercase. */
	private function get_palette_hexes(): array {
		$palette = get_option( '_bricks_color_palettes', [] );
		$hexes   = [];

		if ( ! is_array( $palette ) ) return $hexes;

		foreach ( $palette as $pal ) {
			$colors = $pal['colors'] ?? [];
			foreach ( $colors as $color ) {
				$hex = $color['hex'] ?? ( $color['raw'] ?? null );
				if ( $hex && is_string( $hex ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $hex ) ) {
					$hexes[] = strtolower( $hex );
				}
			}
		}

		// Also include Business Profile brand colors
		$bp = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] );
		if ( is_array( $bp ) ) {
			foreach ( [ 'color_primary', 'color_secondary', 'color_accent', 'color_text',
			             'color_heading', 'color_background', 'color_surface', 'color_border',
			             'color_success', 'color_error' ] as $field ) {
				$hex = $bp[ $field ] ?? '';
				if ( $hex && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $hex ) ) {
					$hexes[] = strtolower( $hex );
				}
			}
		}

		return array_values( array_unique( $hexes ) );
	}

	/** Returns the configured font names from the Business Profile. */
	private function get_profile_fonts(): array {
		$bp    = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] );
		$fonts = [];

		if ( ! is_array( $bp ) ) return $fonts;

		foreach ( [ 'font_heading', 'font_body' ] as $field ) {
			$f = trim( $bp[ $field ] ?? '' );
			if ( $f ) $fonts[] = $f;
		}

		return $fonts;
	}
}
