<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Accessibility extends Tool_Base {

	private const SEMANTIC_TAGS = [ 'section', 'nav', 'main', 'article', 'aside', 'header', 'footer' ];

	private const LAYOUT_ELEMENTS = [ 'section', 'container', 'div', 'block' ];

	private const HEADING_TAGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];

	private const CHECK_TYPES = [ 'alt_text', 'headings', 'aria', 'semantic_html', 'links' ];

	public function define(): array {
		return [
			[
				'name'        => 'bricks_audit_accessibility',
				'description' => 'Scan all Bricks pages and templates for WCAG 2.2 AA accessibility issues: missing alt text on images, heading hierarchy violations, missing ARIA labels on interactive elements, semantic HTML gaps, missing link text, and icon-only buttons without labels. Returns a structured report with severity levels and actionable suggestions. Read-only — no changes are made.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'scope'    => [ 'type' => 'string', 'description' => 'Where to audit: all | pages | templates (default: all).' ],
						'checks'   => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Which checks to run: alt_text, headings, aria, semantic_html, links. Default: all.',
						],
						'severity' => [ 'type' => 'string', 'description' => 'Minimum severity to include: info | warning | error (default: all).' ],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name !== 'bricks_audit_accessibility' ) {
			return $this->err( 'Unknown tool: ' . $name );
		}
		return $this->audit( $args );
	}

	// -------------------------------------------------------------------------

	private function audit( array $args ): array|\WP_Error {
		$scope        = $this->str_arg( $args, 'scope', 'all' );
		$checks       = $this->arr_arg( $args, 'checks', self::CHECK_TYPES );
		$min_severity = $this->str_arg( $args, 'severity', 'all' );

		if ( empty( $checks ) ) {
			$checks = self::CHECK_TYPES;
		}
		$checks = array_intersect( $checks, self::CHECK_TYPES );

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
			'no_found_rows'  => true,
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

		$issues        = [];
		$pages_scanned = 0;

		foreach ( $query->posts as $post_id ) {
			$pages_scanned++;
			$title     = get_the_title( $post_id );
			$post_type = get_post_type( $post_id );
			$page_headings = [];

			foreach ( [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER ] as $meta_key ) {
				$elements = get_post_meta( $post_id, $meta_key, true );
				if ( ! is_array( $elements ) ) continue;

				$area = $meta_key_map[ $meta_key ] ?? $meta_key;

				foreach ( $elements as $el ) {
					if ( ! is_array( $el ) ) continue;

					$settings     = $el['settings'] ?? [];
					$element_id   = $el['id']   ?? '';
					$element_type = $el['name'] ?? '';

					if ( in_array( 'alt_text', $checks, true ) ) {
						$this->check_alt_text( $el, $post_id, $title, $post_type, $area, $issues );
					}

					if ( in_array( 'headings', $checks, true ) ) {
						$this->collect_headings( $el, $area, $page_headings );
					}

					if ( in_array( 'aria', $checks, true ) ) {
						$this->check_aria_labels( $el, $post_id, $title, $post_type, $area, $issues );
					}

					if ( in_array( 'semantic_html', $checks, true ) ) {
						$this->check_semantic_html( $el, $post_id, $title, $post_type, $area, $issues );
					}

					if ( in_array( 'links', $checks, true ) ) {
						$this->check_links_and_buttons( $el, $post_id, $title, $post_type, $area, $issues );
					}
				}
			}

			if ( in_array( 'headings', $checks, true ) ) {
				$this->validate_heading_hierarchy( $page_headings, $post_id, $title, $post_type, $issues );
			}
		}

		$severity_order = [ 'info' => 0, 'warning' => 1, 'error' => 2 ];
		$min_level      = $severity_order[ $min_severity ] ?? 0;

		if ( $min_level > 0 ) {
			$issues = array_values( array_filter(
				$issues,
				fn( $i ) => ( $severity_order[ $i['severity'] ] ?? 0 ) >= $min_level
			) );
		}

		$summary = [];
		foreach ( self::CHECK_TYPES as $type ) {
			$summary[ $type ] = 0;
		}
		foreach ( $issues as $issue ) {
			$summary[ $issue['type'] ] = ( $summary[ $issue['type'] ] ?? 0 ) + 1;
		}

		return [
			'pages_scanned' => $pages_scanned,
			'total_issues'  => count( $issues ),
			'summary'       => $summary,
			'issues'        => $issues,
			'note'          => count( $issues ) === 0
				? 'No accessibility issues detected. The site appears to meet basic WCAG 2.2 AA requirements.'
				: 'Use bricks_update_page to fix individual elements, or bricks_replace_content for global fixes.',
		];
	}

	// -------------------------------------------------------------------------

	private function check_alt_text( array $el, int $post_id, string $title, string $post_type, string $area, array &$issues ): void {
		$element_type = $el['name'] ?? '';
		if ( $element_type !== 'image' ) return;

		$settings   = $el['settings'] ?? [];
		$element_id = $el['id'] ?? '';

		$alt = trim( $settings['alt'] ?? '' );
		if ( $alt ) return;

		$has_alt_attr = $this->has_attribute( $settings, 'alt' );
		if ( $has_alt_attr ) return;

		$issues[] = $this->issue(
			$post_id, $title, $post_type, $area, $element_id, $element_type,
			'alt_text',
			'Image element has no alt text. Screen readers cannot describe this image to users.',
			'Add descriptive alt text in the image element settings, or set alt="" for purely decorative images.',
			'error'
		);
	}

	private function collect_headings( array $el, string $area, array &$page_headings ): void {
		$element_type = $el['name'] ?? '';
		$settings     = $el['settings'] ?? [];
		$tag          = $settings['tag'] ?? '';

		if ( $element_type === 'heading' ) {
			$tag = $tag ?: 'h2';
		}

		if ( in_array( strtolower( $tag ), self::HEADING_TAGS, true ) ) {
			$page_headings[] = [
				'tag'        => strtolower( $tag ),
				'area'       => $area,
				'element_id' => $el['id'] ?? '',
				'element_type' => $element_type,
			];
		}
	}

	private function validate_heading_hierarchy( array $headings, int $post_id, string $title, string $post_type, array &$issues ): void {
		if ( empty( $headings ) ) return;

		$h1_count   = 0;
		$prev_level = 0;

		foreach ( $headings as $h ) {
			$level = (int) substr( $h['tag'], 1 );

			if ( $level === 1 ) {
				$h1_count++;
			}

			// Detect skipped levels (e.g. h1 -> h3, h2 -> h4)
			if ( $prev_level > 0 && $level > $prev_level + 1 ) {
				$issues[] = $this->issue(
					$post_id, $title, $post_type, $h['area'], $h['element_id'], $h['element_type'],
					'headings',
					"Heading level skipped: {$h['tag']} follows h{$prev_level}. This breaks the document outline for assistive technology.",
					"Use h" . ( $prev_level + 1 ) . " instead, or add the missing intermediate heading level.",
					'error'
				);
			}

			$prev_level = $level;
		}

		if ( $h1_count === 0 ) {
			$issues[] = $this->issue(
				$post_id, $title, $post_type, 'content', '', 'page',
				'headings',
				'Page has no h1 heading. Every page should have exactly one h1 for the main topic.',
				'Add an h1 heading element at the top of the page content.',
				'error'
			);
		} elseif ( $h1_count > 1 ) {
			$issues[] = $this->issue(
				$post_id, $title, $post_type, 'content', '', 'page',
				'headings',
				"Page has {$h1_count} h1 headings. Each page should have exactly one h1.",
				'Change extra h1 elements to h2 or another appropriate level.',
				'warning'
			);
		}
	}

	private function check_aria_labels( array $el, int $post_id, string $title, string $post_type, string $area, array &$issues ): void {
		$element_type = $el['name'] ?? '';
		$settings     = $el['settings'] ?? [];
		$element_id   = $el['id'] ?? '';

		if ( $element_type === 'nav-menu' || $element_type === 'nav-nested' ) {
			if ( ! $this->has_attribute( $settings, 'aria-label' ) && ! $this->has_attribute( $settings, 'aria-labelledby' ) ) {
				$issues[] = $this->issue(
					$post_id, $title, $post_type, $area, $element_id, $element_type,
					'aria',
					'Navigation element has no aria-label. When a page has multiple nav regions, each needs a unique label.',
					'Add an aria-label attribute (e.g. "Main navigation" or "Footer navigation") in the element\'s _attributes setting.',
					'warning'
				);
			}
		}

		// Icon-only buttons need aria-label
		if ( $element_type === 'button' ) {
			$text = trim( $settings['text'] ?? '' );
			$icon = $settings['icon'] ?? null;

			if ( empty( $text ) && ! empty( $icon ) ) {
				if ( ! $this->has_attribute( $settings, 'aria-label' ) && ! $this->has_attribute( $settings, 'aria-labelledby' ) ) {
					$issues[] = $this->issue(
						$post_id, $title, $post_type, $area, $element_id, $element_type,
						'aria',
						'Icon-only button has no aria-label. Screen readers will not convey the button\'s purpose.',
						'Add an aria-label attribute describing the button\'s action (e.g. "Close", "Search", "Menu").',
						'error'
					);
				}
			}
		}
	}

	private function check_semantic_html( array $el, int $post_id, string $title, string $post_type, string $area, array &$issues ): void {
		$element_type = $el['name'] ?? '';
		$settings     = $el['settings'] ?? [];
		$element_id   = $el['id'] ?? '';

		if ( ! in_array( $element_type, self::LAYOUT_ELEMENTS, true ) ) return;

		$tag = strtolower( $settings['tag'] ?? 'div' );

		if ( $tag === 'div' ) {
			$issues[] = $this->issue(
				$post_id, $title, $post_type, $area, $element_id, $element_type,
				'semantic_html',
				"Layout element uses a generic <div> tag. Consider using a semantic HTML5 tag for better accessibility and SEO.",
				'Change the tag to section, nav, main, article, aside, header, or footer as appropriate for the content.',
				'info'
			);
		}
	}

	private function check_links_and_buttons( array $el, int $post_id, string $title, string $post_type, string $area, array &$issues ): void {
		$element_type = $el['name'] ?? '';
		$settings     = $el['settings'] ?? [];
		$element_id   = $el['id'] ?? '';

		if ( $element_type === 'text-link' ) {
			$text = trim( $settings['text'] ?? '' );
			$icon = $settings['icon'] ?? null;

			if ( empty( $text ) && ! empty( $icon ) ) {
				if ( ! $this->has_attribute( $settings, 'aria-label' ) ) {
					$issues[] = $this->issue(
						$post_id, $title, $post_type, $area, $element_id, $element_type,
						'links',
						'Link has an icon but no visible text and no aria-label. Screen readers cannot determine the link destination.',
						'Add visible link text or an aria-label attribute describing where the link goes.',
						'error'
					);
				}
			} elseif ( empty( $text ) && empty( $icon ) ) {
				$issues[] = $this->issue(
					$post_id, $title, $post_type, $area, $element_id, $element_type,
					'links',
					'Link element has no text content. Empty links are invisible to screen readers and confusing to keyboard users.',
					'Add descriptive link text that indicates the destination or purpose.',
					'error'
				);
			}
		}

		if ( $element_type === 'button' ) {
			$text = trim( $settings['text'] ?? '' );
			$icon = $settings['icon'] ?? null;

			if ( empty( $text ) && empty( $icon ) ) {
				$issues[] = $this->issue(
					$post_id, $title, $post_type, $area, $element_id, $element_type,
					'links',
					'Button element has no text and no icon. It will be invisible to screen readers.',
					'Add button text or an icon with an aria-label.',
					'error'
				);
			}
		}
	}

	// -------------------------------------------------------------------------

	/**
	 * Bricks stores custom attributes as a repeater: _attributes => [ [ 'name' => 'aria-label', 'value' => '...' ], ... ]
	 */
	private function has_attribute( array $settings, string $attr_name ): bool {
		$attributes = $settings['_attributes'] ?? [];
		if ( ! is_array( $attributes ) ) return false;

		foreach ( $attributes as $attr ) {
			if ( ! is_array( $attr ) ) continue;
			if ( strtolower( trim( $attr['name'] ?? '' ) ) === strtolower( $attr_name ) ) {
				$value = trim( $attr['value'] ?? '' );
				if ( $value !== '' ) return true;
			}
		}

		return false;
	}

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
}
