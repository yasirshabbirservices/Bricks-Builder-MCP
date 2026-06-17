<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Performance extends Tool_Base {

	private const MAX_ELEMENTS_WARNING = 100;
	private const MAX_ELEMENTS_ERROR   = 200;
	private const MAX_NESTING_DEPTH    = 8;
	private const MAX_CSS_CUSTOM_LEN   = 500;

	public function define(): array {
		return [
			[
				'name'        => 'bricks_audit_performance',
				'description' => 'Scan all Bricks pages and templates for performance issues affecting Core Web Vitals (LCP, CLS, INP). Checks for: above-fold images without eager loading (LCP), images without dimensions (CLS), excessive element counts and deep nesting (INP), lazy loading on above-fold content, large background images in first section, excessive inline CSS, and missing font preload hints. Returns a structured report with severity levels and actionable suggestions. Read-only — no changes are made.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'scope'    => [ 'type' => 'string', 'description' => 'Where to audit: all | pages | templates | post_id (default: all). Use post_id with the post_id parameter.' ],
						'post_id'  => [ 'type' => 'integer', 'description' => 'Specific post ID to audit (only used when scope=post_id).' ],
						'checks'   => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Which checks to run: images, element_count, css_bloat, fonts. Default: all.',
						],
						'severity' => [ 'type' => 'string', 'description' => 'Minimum severity to include: all | info | warning | error (default: all).' ],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name !== 'bricks_audit_performance' ) {
			return $this->err( 'Unknown tool: ' . $name );
		}
		return $this->audit( $args );
	}

	// -------------------------------------------------------------------------

	private function audit( array $args ): array|\WP_Error {
		$scope        = $this->str_arg( $args, 'scope', 'all' );
		$post_id      = $this->int_arg( $args, 'post_id' );
		$checks       = $this->arr_arg( $args, 'checks', [ 'images', 'element_count', 'css_bloat', 'fonts' ] );
		$min_severity = $this->str_arg( $args, 'severity', 'all' );

		$run_images        = in_array( 'images', $checks, true );
		$run_element_count = in_array( 'element_count', $checks, true );
		$run_css_bloat     = in_array( 'css_bloat', $checks, true );
		$run_fonts         = in_array( 'fonts', $checks, true );

		$post_ids = $this->resolve_post_ids( $scope, $post_id );
		if ( is_wp_error( $post_ids ) ) {
			return $post_ids;
		}

		$meta_key_map = [
			BMCP_DB_PAGE_CONTENT => 'content',
			BMCP_DB_PAGE_HEADER  => 'header',
			BMCP_DB_PAGE_FOOTER  => 'footer',
		];

		$issues        = [];
		$pages_scanned = 0;

		foreach ( $post_ids as $pid ) {
			$pages_scanned++;
			$title     = get_the_title( $pid );
			$post_type = get_post_type( $pid );

			$all_elements_count = 0;

			foreach ( [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER ] as $meta_key ) {
				$elements = get_post_meta( $pid, $meta_key, true );
				if ( ! is_array( $elements ) || empty( $elements ) ) {
					continue;
				}

				$area = $meta_key_map[ $meta_key ] ?? $meta_key;
				$all_elements_count += count( $elements );

				$first_section_ids = $this->get_first_section_ids( $elements );
				$depth_map         = $this->build_depth_map( $elements );

				foreach ( $elements as $el ) {
					if ( ! is_array( $el ) ) continue;

					$el_id   = $el['id']       ?? '';
					$el_type = $el['name']      ?? '';
					$settings = $el['settings'] ?? [];

					$in_first_section = isset( $first_section_ids[ $el_id ] );

					if ( $run_images ) {
						$this->check_images( $el, $in_first_section, $pid, $title, $post_type, $area, $issues );
					}

					if ( $run_css_bloat ) {
						$this->check_css_bloat( $el, $pid, $title, $post_type, $area, $issues );
					}

					if ( $run_element_count ) {
						$depth = $depth_map[ $el_id ] ?? 0;
						if ( $depth > self::MAX_NESTING_DEPTH ) {
							$issues[] = $this->issue(
								$pid, $title, $post_type, $area, $el_id, $el_type,
								'element_count',
								"Element is nested {$depth} levels deep (max recommended: " . self::MAX_NESTING_DEPTH . ').',
								'Flatten the structure by reducing wrapper containers. Deep nesting increases DOM complexity and hurts INP.',
								'warning'
							);
						}
					}
				}
			}

			if ( $run_element_count && $all_elements_count > 0 ) {
				if ( $all_elements_count >= self::MAX_ELEMENTS_ERROR ) {
					$issues[] = $this->issue(
						$pid, $title, $post_type, 'content', '', '',
						'element_count',
						"Page has {$all_elements_count} elements (threshold: " . self::MAX_ELEMENTS_ERROR . '). This will significantly impact rendering performance and INP.',
						'Break the page into smaller templates or remove unused elements. Consider lazy-loading sections below the fold.',
						'error'
					);
				} elseif ( $all_elements_count >= self::MAX_ELEMENTS_WARNING ) {
					$issues[] = $this->issue(
						$pid, $title, $post_type, 'content', '', '',
						'element_count',
						"Page has {$all_elements_count} elements (threshold: " . self::MAX_ELEMENTS_WARNING . '). Consider reducing complexity.',
						'Audit the page for unnecessary wrapper elements and consolidate where possible.',
						'warning'
					);
				}
			}
		}

		if ( $run_fonts ) {
			$font_issues = $this->check_font_preload();
			$issues      = array_merge( $issues, $font_issues );
		}

		$severity_order = [ 'info' => 0, 'warning' => 1, 'error' => 2 ];
		$min_level      = $severity_order[ $min_severity ] ?? 0;

		if ( $min_level > 0 ) {
			$issues = array_values( array_filter(
				$issues,
				fn( $i ) => ( $severity_order[ $i['severity'] ] ?? 0 ) >= $min_level
			) );
		}

		$summary = [ 'images' => 0, 'element_count' => 0, 'css_bloat' => 0, 'fonts' => 0 ];
		foreach ( $issues as $issue ) {
			$summary[ $issue['type'] ] = ( $summary[ $issue['type'] ] ?? 0 ) + 1;
		}

		return $this->success( [
			'pages_scanned'   => $pages_scanned,
			'total_issues'    => count( $issues ),
			'summary'         => $summary,
			'issues'          => $issues,
			'performance_tips' => $this->get_tips( $summary ),
		] );
	}

	// -------------------------------------------------------------------------
	// Post resolution
	// -------------------------------------------------------------------------

	private function resolve_post_ids( string $scope, ?int $post_id ): array|\WP_Error {
		if ( $scope === 'post_id' ) {
			if ( ! $post_id ) {
				return $this->err( 'post_id is required when scope=post_id.' );
			}
			if ( ! get_post( $post_id ) ) {
				return $this->err( "Post {$post_id} not found." );
			}
			return [ $post_id ];
		}

		$post_types = [];
		$template_slug = defined( 'BRICKS_DB_TEMPLATE_SLUG' ) ? BRICKS_DB_TEMPLATE_SLUG : 'bricks_template';

		if ( $scope === 'all' || $scope === 'pages' ) {
			$post_types[] = 'page';
		}
		if ( $scope === 'all' || $scope === 'templates' ) {
			$post_types[] = $template_slug;
		}
		if ( empty( $post_types ) ) {
			$post_types = [ 'page', $template_slug ];
		}

		$query = new \WP_Query( [
			'post_type'      => $post_types,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => BMCP_DB_PAGE_CONTENT, 'compare' => 'EXISTS' ],
				[ 'key' => BMCP_DB_PAGE_HEADER,  'compare' => 'EXISTS' ],
				[ 'key' => BMCP_DB_PAGE_FOOTER,  'compare' => 'EXISTS' ],
			],
		] );

		return $query->posts;
	}

	// -------------------------------------------------------------------------
	// Element tree helpers
	// -------------------------------------------------------------------------

	/** Returns a set of element IDs that belong to the first root-level element and its descendants. */
	private function get_first_section_ids( array $elements ): array {
		$first_root_id = null;
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) continue;
			$parent = $el['parent'] ?? 0;
			if ( $parent === 0 || $parent === '0' ) {
				$first_root_id = $el['id'] ?? null;
				break;
			}
		}

		if ( ! $first_root_id ) {
			return [];
		}

		$ids = [ $first_root_id => true ];
		// Iteratively collect all descendants
		$changed = true;
		while ( $changed ) {
			$changed = false;
			foreach ( $elements as $el ) {
				if ( ! is_array( $el ) ) continue;
				$el_id  = $el['id']     ?? '';
				$parent = $el['parent'] ?? 0;
				if ( ! isset( $ids[ $el_id ] ) && isset( $ids[ $parent ] ) ) {
					$ids[ $el_id ] = true;
					$changed       = true;
				}
			}
		}

		return $ids;
	}

	/** Returns element_id => nesting depth map. */
	private function build_depth_map( array $elements ): array {
		$parent_map = [];
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) continue;
			$el_id  = $el['id']     ?? '';
			$parent = $el['parent'] ?? 0;
			if ( $el_id ) {
				$parent_map[ $el_id ] = ( $parent === 0 || $parent === '0' ) ? '' : (string) $parent;
			}
		}

		$depth_map = [];
		foreach ( $parent_map as $el_id => $parent_id ) {
			$depth   = 0;
			$current = $parent_id;
			$visited = [];
			while ( $current !== '' && ! isset( $visited[ $current ] ) ) {
				$visited[ $current ] = true;
				$depth++;
				$current = $parent_map[ $current ] ?? '';
			}
			$depth_map[ $el_id ] = $depth;
		}

		return $depth_map;
	}

	// -------------------------------------------------------------------------
	// Image checks
	// -------------------------------------------------------------------------

	private function check_images( array $el, bool $in_first_section, int $post_id, string $title, string $post_type, string $area, array &$issues ): void {
		$el_id    = $el['id']       ?? '';
		$el_type  = $el['name']      ?? '';
		$settings = $el['settings'] ?? [];

		$is_image_element = in_array( $el_type, [ 'image', 'img', 'logo', 'image-gallery' ], true );

		if ( $is_image_element ) {
			$this->check_image_dimensions( $settings, $post_id, $title, $post_type, $area, $el_id, $el_type, $issues );
		}

		if ( ! $in_first_section ) {
			return;
		}

		// Above-fold image without eager loading
		if ( $is_image_element ) {
			$attrs   = $settings['_attributes'] ?? [];
			$loading       = $this->find_attribute( $attrs, 'loading' );
			$fetchpriority = $this->find_attribute( $attrs, 'fetchpriority' );

			if ( $loading === 'lazy' ) {
				$issues[] = $this->issue(
					$post_id, $title, $post_type, $area, $el_id, $el_type,
					'images',
					'Above-fold image has loading=lazy, which delays LCP.',
					'Remove loading=lazy or set loading=eager and fetchpriority=high for LCP candidate images.',
					'error'
				);
			} elseif ( $loading !== 'eager' && $fetchpriority !== 'high' ) {
				$issues[] = $this->issue(
					$post_id, $title, $post_type, $area, $el_id, $el_type,
					'images',
					'Above-fold image lacks loading=eager or fetchpriority=high. It may be an LCP candidate.',
					'Add loading=eager and fetchpriority=high via _attributes to prioritize this image for LCP.',
					'warning'
				);
			}
		}

		// Background image in first section
		$background = $settings['_background'] ?? null;
		if ( is_array( $background ) ) {
			$bg_image = $background['image'] ?? ( $background['url'] ?? null );
			if ( $bg_image ) {
				$issues[] = $this->issue(
					$post_id, $title, $post_type, $area, $el_id, $el_type,
					'images',
					'Above-fold element has a background image which may be an LCP candidate but cannot use fetchpriority.',
					'Consider using an <img> element with fetchpriority=high instead, or add a preload link for this image in the page head.',
					'info'
				);
			}
		}
	}

	private function check_image_dimensions( array $settings, int $post_id, string $title, string $post_type, string $area, string $el_id, string $el_type, array &$issues ): void {
		$has_width       = ! empty( $settings['_width'] );
		$has_height      = ! empty( $settings['_height'] );
		$has_aspect      = ! empty( $settings['_aspectRatio'] );

		if ( ! $has_width && ! $has_height && ! $has_aspect ) {
			$issues[] = $this->issue(
				$post_id, $title, $post_type, $area, $el_id, $el_type,
				'images',
				'Image element has no explicit width, height, or aspect ratio set. This can cause CLS (Cumulative Layout Shift).',
				'Set _width and _height, or _aspectRatio to reserve space and prevent layout shifts.',
				'warning'
			);
		}
	}

	/** Finds an attribute value from Bricks _attributes array (array of {name, value} objects). */
	private function find_attribute( $attrs, string $name ): string {
		if ( ! is_array( $attrs ) ) {
			return '';
		}
		foreach ( $attrs as $attr ) {
			if ( ! is_array( $attr ) ) continue;
			$attr_name = strtolower( trim( $attr['name'] ?? '' ) );
			if ( $attr_name === $name ) {
				return strtolower( trim( $attr['value'] ?? '' ) );
			}
		}
		return '';
	}

	// -------------------------------------------------------------------------
	// CSS bloat check
	// -------------------------------------------------------------------------

	private function check_css_bloat( array $el, int $post_id, string $title, string $post_type, string $area, array &$issues ): void {
		$el_id    = $el['id']       ?? '';
		$el_type  = $el['name']      ?? '';
		$settings = $el['settings'] ?? [];

		$css_custom = $settings['_cssCustom'] ?? '';
		if ( ! is_string( $css_custom ) ) {
			return;
		}

		$len = strlen( $css_custom );
		if ( $len > self::MAX_CSS_CUSTOM_LEN ) {
			$severity = $len > 1000 ? 'error' : 'warning';
			$issues[] = $this->issue(
				$post_id, $title, $post_type, $area, $el_id, $el_type,
				'css_bloat',
				"Element has {$len} characters of inline custom CSS (threshold: " . self::MAX_CSS_CUSTOM_LEN . ').',
				'Move shared styles to global CSS classes or the theme stylesheet. Inline CSS increases page weight and cannot be cached independently.',
				$severity
			);
		}
	}

	// -------------------------------------------------------------------------
	// Font preload check
	// -------------------------------------------------------------------------

	private function check_font_preload(): array {
		$issues   = [];
		$settings = get_option( 'bricks_global_settings', [] );
		if ( ! is_array( $settings ) ) {
			return $issues;
		}

		$custom_fonts = $settings['customFonts'] ?? [];
		if ( ! is_array( $custom_fonts ) || empty( $custom_fonts ) ) {
			return $issues;
		}

		$preload_enabled = ! empty( $settings['customFontsPreload'] );

		if ( ! $preload_enabled ) {
			$font_names = [];
			foreach ( $custom_fonts as $font ) {
				$name = $font['family'] ?? ( $font['name'] ?? '' );
				if ( $name ) {
					$font_names[] = $name;
				}
			}

			if ( ! empty( $font_names ) ) {
				$issues[] = $this->issue(
					0, 'Global Settings', 'option', 'global', '', '',
					'fonts',
					'Custom fonts (' . implode( ', ', $font_names ) . ') are loaded without preload hints. This delays text rendering and can cause FOIT/FOUT.',
					'Enable customFontsPreload in Bricks global settings, or add <link rel="preload"> tags for the primary font files in the page head.',
					'warning'
				);
			}
		}

		return $issues;
	}

	// -------------------------------------------------------------------------
	// Issue builder
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

	// -------------------------------------------------------------------------
	// Performance tips
	// -------------------------------------------------------------------------

	private function get_tips( array $summary ): array {
		$tips = [];

		if ( $summary['images'] > 0 ) {
			$tips[] = 'Set loading=eager and fetchpriority=high on the single largest above-fold image (your LCP element). All other images should use loading=lazy.';
			$tips[] = 'Always set explicit width and height on images to prevent CLS. Use the _aspectRatio setting as an alternative.';
		}

		if ( $summary['element_count'] > 0 ) {
			$tips[] = 'Reduce DOM size by removing unnecessary wrapper elements. Each element adds to parse time, style recalculation, and layout cost.';
			$tips[] = 'For long pages, consider splitting content into Bricks templates with conditional loading.';
		}

		if ( $summary['css_bloat'] > 0 ) {
			$tips[] = 'Move repeated inline CSS to global classes. Inline CSS cannot be cached separately and increases HTML payload size.';
			$tips[] = 'Use Bricks global CSS variables and utility classes instead of per-element custom CSS.';
		}

		if ( $summary['fonts'] > 0 ) {
			$tips[] = 'Preload critical fonts using <link rel="preload" as="font" crossorigin>. This eliminates the flash of invisible/unstyled text (FOIT/FOUT).';
			$tips[] = 'Consider using font-display: swap in your @font-face rules to show fallback text while fonts load.';
		}

		if ( empty( $tips ) ) {
			$tips[] = 'No performance issues detected. The site appears well-optimized for Core Web Vitals.';
		}

		return $tips;
	}
}
