<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO integration — Yoast SEO, Rank Math, and The SEO Framework.
 * Tools only appear when a supported SEO plugin is active.
 */
class Tool_SEO extends Tool_Base {

	private function has_yoast(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	private function has_rankmath(): bool {
		return class_exists( 'RankMath' );
	}

	private function has_seoframework(): bool {
		return class_exists( 'The_SEO_Framework\Load' );
	}

	private function has_any_seo(): bool {
		return $this->has_yoast() || $this->has_rankmath() || $this->has_seoframework();
	}

	public function define(): array {
		$tools = [];

		// SEO audit works even without an SEO plugin — it checks Bricks content structure
		$tools[] = [
			'name'        => 'bricks_audit_seo',
			'description' => 'Audit all pages and templates for SEO issues: missing/short meta titles and descriptions, heading hierarchy problems (multiple h1s, skipped levels, no h1), missing image alt text, duplicate meta descriptions across pages, pages with no internal links, and long URLs. Returns a prioritized report. Works with or without an SEO plugin — heading and content checks use the Bricks element tree directly.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'scope'    => [ 'type' => 'string', 'description' => 'Where to audit: all | pages | templates (default: all).' ],
					'checks'   => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Which checks to run: meta, headings, images, links, urls (default: all).',
					],
					'severity' => [ 'type' => 'string', 'description' => 'Minimum severity: info | warning | error (default: all).' ],
				],
			],
		];

		$tools[] = [
			'name'        => 'bricks_get_heading_structure',
			'description' => 'Get the heading hierarchy (h1–h6) for a specific page or template from its Bricks element tree. Returns an ordered list of headings with their level, text content, element ID, and any hierarchy violations. Useful for SEO analysis and accessibility review.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer', 'description' => 'WordPress post/template ID' ],
				],
				'required' => [ 'post_id' ],
			],
		];

		if ( $this->has_any_seo() ) {
			$active = $this->has_yoast() ? 'Yoast SEO' : ( $this->has_rankmath() ? 'Rank Math' : 'The SEO Framework' );

			$tools[] = [
				'name'        => 'bricks_get_page_seo',
				'description' => "Get SEO metadata for a page or post — title, meta description, OG title/description, robots settings. Uses {$active}.",
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
					],
					'required' => [ 'post_id' ],
				],
			];

			$tools[] = [
				'name'        => 'bricks_update_page_seo',
				'description' => "Update SEO metadata for a page or post — title, meta description, OG image, robots settings. Uses {$active}.",
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'          => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
						'title'            => [ 'type' => 'string', 'description' => 'SEO title (browser tab + search result title)' ],
						'description'      => [ 'type' => 'string', 'description' => 'Meta description (160 chars max)' ],
						'og_title'         => [ 'type' => 'string', 'description' => 'Open Graph title for social shares' ],
						'og_description'   => [ 'type' => 'string', 'description' => 'Open Graph description for social shares' ],
						'og_image_id'      => [ 'type' => 'integer', 'description' => 'WordPress media ID for OG image' ],
						'noindex'          => [ 'type' => 'boolean', 'description' => 'Set to true to prevent search engine indexing' ],
						'nofollow'         => [ 'type' => 'boolean', 'description' => 'Set to true to prevent link following' ],
						'canonical'        => [ 'type' => 'string', 'description' => 'Canonical URL (leave empty to use the page URL)' ],
						'focus_keyword'    => [ 'type' => 'string', 'description' => 'Primary focus keyword (Yoast/Rank Math)' ],
					],
					'required' => [ 'post_id' ],
				],
			];
		}

		return $tools;
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_get_page_seo':
				return $this->get_seo( $args );
			case 'bricks_update_page_seo':
				return $this->update_seo( $args );
			case 'bricks_audit_seo':
				return $this->audit_seo( $args );
			case 'bricks_get_heading_structure':
				return $this->get_heading_structure( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	// -------------------------------------------------------------------------

	private function get_seo( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) return $this->err( 'post_id is required.' );

		$post = get_post( $post_id );
		if ( ! $post ) return $this->err( "Post {$post_id} not found." );

		if ( $this->has_yoast() ) {
			return $this->get_yoast( $post_id );
		}
		if ( $this->has_rankmath() ) {
			return $this->get_rankmath( $post_id );
		}
		return $this->get_seoframework( $post_id );
	}

	private function get_yoast( int $post_id ): array {
		return [
			'plugin'          => 'Yoast SEO',
			'title'           => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
			'description'     => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
			'og_title'        => get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ),
			'og_description'  => get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true ),
			'og_image_id'     => (int) get_post_meta( $post_id, '_yoast_wpseo_opengraph-image-id', true ) ?: null,
			'canonical'       => get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
			'noindex'         => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) === '1',
			'nofollow'        => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ) === '1',
			'focus_keyword'   => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
		];
	}

	private function get_rankmath( int $post_id ): array {
		return [
			'plugin'         => 'Rank Math',
			'title'          => get_post_meta( $post_id, 'rank_math_title', true ),
			'description'    => get_post_meta( $post_id, 'rank_math_description', true ),
			'og_title'       => get_post_meta( $post_id, 'rank_math_facebook_title', true ),
			'og_description' => get_post_meta( $post_id, 'rank_math_facebook_description', true ),
			'og_image_id'    => (int) get_post_meta( $post_id, 'rank_math_facebook_image_id', true ) ?: null,
			'canonical'      => get_post_meta( $post_id, 'rank_math_canonical_url', true ),
			'noindex'        => get_post_meta( $post_id, 'rank_math_robots', true ) === 'noindex',
			'focus_keyword'  => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
		];
	}

	private function get_seoframework( int $post_id ): array {
		return [
			'plugin'      => 'The SEO Framework',
			'title'       => get_post_meta( $post_id, '_genesis_title', true ),
			'description' => get_post_meta( $post_id, '_genesis_description', true ),
			'noindex'     => (bool) get_post_meta( $post_id, '_genesis_noindex', true ),
			'nofollow'    => (bool) get_post_meta( $post_id, '_genesis_nofollow', true ),
			'canonical'   => get_post_meta( $post_id, '_genesis_canonical_uri', true ),
		];
	}

	// -------------------------------------------------------------------------

	private function update_seo( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) return $this->err( 'post_id is required.' );

		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$post = get_post( $post_id );
		if ( ! $post ) return $this->err( "Post {$post_id} not found." );

		if ( $this->has_yoast() ) {
			$this->update_yoast( $post_id, $args );
		} elseif ( $this->has_rankmath() ) {
			$this->update_rankmath( $post_id, $args );
		} else {
			$this->update_seoframework( $post_id, $args );
		}

		return [ 'success' => true, 'post_id' => $post_id, 'message' => 'SEO metadata updated.' ];
	}

	private function update_yoast( int $post_id, array $args ): void {
		$map = [
			'title'          => '_yoast_wpseo_title',
			'description'    => '_yoast_wpseo_metadesc',
			'og_title'       => '_yoast_wpseo_opengraph-title',
			'og_description' => '_yoast_wpseo_opengraph-description',
			'canonical'      => '_yoast_wpseo_canonical',
			'focus_keyword'  => '_yoast_wpseo_focuskw',
		];
		foreach ( $map as $arg_key => $meta_key ) {
			if ( isset( $args[ $arg_key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $args[ $arg_key ] ) );
			}
		}
		if ( isset( $args['og_image_id'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-image-id', (int) $args['og_image_id'] );
		}
		if ( isset( $args['noindex'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', $args['noindex'] ? '1' : '0' );
		}
		if ( isset( $args['nofollow'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', $args['nofollow'] ? '1' : '0' );
		}
	}

	private function update_rankmath( int $post_id, array $args ): void {
		$map = [
			'title'          => 'rank_math_title',
			'description'    => 'rank_math_description',
			'og_title'       => 'rank_math_facebook_title',
			'og_description' => 'rank_math_facebook_description',
			'canonical'      => 'rank_math_canonical_url',
			'focus_keyword'  => 'rank_math_focus_keyword',
		];
		foreach ( $map as $arg_key => $meta_key ) {
			if ( isset( $args[ $arg_key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $args[ $arg_key ] ) );
			}
		}
		if ( isset( $args['og_image_id'] ) ) {
			update_post_meta( $post_id, 'rank_math_facebook_image_id', (int) $args['og_image_id'] );
		}
		if ( isset( $args['noindex'] ) ) {
			update_post_meta( $post_id, 'rank_math_robots', $args['noindex'] ? 'noindex' : 'index' );
		}
	}

	private function update_seoframework( int $post_id, array $args ): void {
		$map = [
			'title'       => '_genesis_title',
			'description' => '_genesis_description',
			'canonical'   => '_genesis_canonical_uri',
		];
		foreach ( $map as $arg_key => $meta_key ) {
			if ( isset( $args[ $arg_key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $args[ $arg_key ] ) );
			}
		}
		if ( isset( $args['noindex'] ) ) {
			update_post_meta( $post_id, '_genesis_noindex', $args['noindex'] ? '1' : '' );
		}
		if ( isset( $args['nofollow'] ) ) {
			update_post_meta( $post_id, '_genesis_nofollow', $args['nofollow'] ? '1' : '' );
		}
	}

	// -------------------------------------------------------------------------
	// SEO Audit
	// -------------------------------------------------------------------------

	private function audit_seo( array $args ): array|\WP_Error {
		$scope        = $this->str_arg( $args, 'scope', 'all' );
		$checks       = $this->arr_arg( $args, 'checks', [ 'meta', 'headings', 'images', 'links', 'urls' ] );
		$min_severity = $this->str_arg( $args, 'severity', 'all' );

		$post_types = $this->resolve_post_types( $scope );
		$issues     = [];
		$pages      = 0;
		$seen_descs = [];

		$query = new \WP_Query( [
			'post_type'      => $post_types,
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );

		foreach ( $query->posts as $post ) {
			$pages++;
			$title     = $post->post_title;
			$post_type = $post->post_type;
			$post_id   = $post->ID;

			// --- Meta checks ---
			if ( in_array( 'meta', $checks, true ) ) {
				$seo_title = '';
				$seo_desc  = '';

				if ( $this->has_yoast() ) {
					$seo_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
					$seo_desc  = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
				} elseif ( $this->has_rankmath() ) {
					$seo_title = get_post_meta( $post_id, 'rank_math_title', true );
					$seo_desc  = get_post_meta( $post_id, 'rank_math_description', true );
				} elseif ( $this->has_seoframework() ) {
					$seo_title = get_post_meta( $post_id, '_genesis_title', true );
					$seo_desc  = get_post_meta( $post_id, '_genesis_description', true );
				}

				if ( empty( $seo_title ) ) {
					$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'meta', 'error',
						'Missing SEO title.',
						'Set a unique SEO title (50-60 chars) with the primary keyword near the start.'
					);
				} elseif ( mb_strlen( $seo_title ) > 60 ) {
					$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'meta', 'warning',
						"SEO title is " . mb_strlen( $seo_title ) . " chars (recommended: 50-60).",
						'Shorten to under 60 characters to avoid truncation in search results.'
					);
				}

				if ( empty( $seo_desc ) ) {
					$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'meta', 'error',
						'Missing meta description.',
						'Write a compelling meta description (150-160 chars) with a call to action.'
					);
				} elseif ( mb_strlen( $seo_desc ) > 160 ) {
					$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'meta', 'warning',
						"Meta description is " . mb_strlen( $seo_desc ) . " chars (recommended: 150-160).",
						'Shorten to under 160 characters to avoid truncation.'
					);
				} elseif ( mb_strlen( $seo_desc ) < 70 ) {
					$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'meta', 'info',
						"Meta description is only " . mb_strlen( $seo_desc ) . " chars.",
						'Expand to 150-160 chars to maximize search result real estate.'
					);
				}

				if ( ! empty( $seo_desc ) ) {
					$desc_key = strtolower( trim( $seo_desc ) );
					if ( isset( $seen_descs[ $desc_key ] ) ) {
						$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'meta', 'warning',
							"Duplicate meta description — same as page ID {$seen_descs[$desc_key]}.",
							'Each page should have a unique meta description.'
						);
					} else {
						$seen_descs[ $desc_key ] = $post_id;
					}
				}
			}

			// --- Heading, image, link checks from Bricks element tree ---
			$all_elements = $this->get_all_elements( $post_id );

			if ( in_array( 'headings', $checks, true ) ) {
				$heading_issues = $this->check_headings( $post_id, $title, $post_type, $all_elements );
				$issues         = array_merge( $issues, $heading_issues );
			}

			if ( in_array( 'images', $checks, true ) ) {
				foreach ( $all_elements as $el ) {
					if ( ( $el['name'] ?? '' ) !== 'image' ) continue;
					$settings = $el['settings'] ?? [];
					$has_alt  = ! empty( $settings['alt'] );

					if ( ! $has_alt ) {
						$attrs = $settings['_attributes'] ?? [];
						foreach ( $attrs as $attr ) {
							if ( strtolower( $attr['name'] ?? '' ) === 'alt' && ! empty( $attr['value'] ) ) {
								$has_alt = true;
								break;
							}
						}
					}

					if ( ! $has_alt && empty( $settings['useDynamicData'] ?? '' ) ) {
						$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'images', 'warning',
							"Image element {$el['id']} has no alt text.",
							'Add descriptive alt text for SEO and accessibility. Use the element alt field or _attributes.'
						);
					}
				}
			}

			if ( in_array( 'urls', $checks, true ) && $post_type !== BMCP_DB_TEMPLATE_SLUG ) {
				$slug = $post->post_name;
				if ( mb_strlen( $slug ) > 75 ) {
					$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'urls', 'info',
						"URL slug is " . mb_strlen( $slug ) . " chars: /{$slug}/",
						'Keep URLs short, descriptive, and keyword-rich.'
					);
				}
			}

			if ( in_array( 'links', $checks, true ) && $post_type !== BMCP_DB_TEMPLATE_SLUG ) {
				$has_internal_link = false;
				foreach ( $all_elements as $el ) {
					$settings = $el['settings'] ?? [];
					$link     = $settings['link'] ?? null;
					if ( is_array( $link ) && ! empty( $link['url'] ) ) {
						$url = $link['url'];
						if ( strpos( $url, home_url() ) !== false || ( strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) ) {
							$has_internal_link = true;
							break;
						}
					}
				}
				if ( ! $has_internal_link && count( $all_elements ) > 0 ) {
					$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'links', 'info',
						'No internal links found on this page.',
						'Add internal links to related content. Every page should be reachable within 3 clicks.'
					);
				}
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
		foreach ( $issues as $issue ) {
			$summary[ $issue['type'] ] = ( $summary[ $issue['type'] ] ?? 0 ) + 1;
		}

		return [
			'pages_scanned' => $pages,
			'total_issues'  => count( $issues ),
			'summary'       => $summary,
			'issues'        => $issues,
			'seo_plugin'    => $this->has_any_seo()
				? ( $this->has_yoast() ? 'Yoast SEO' : ( $this->has_rankmath() ? 'Rank Math' : 'The SEO Framework' ) )
				: 'none',
		];
	}

	// -------------------------------------------------------------------------
	// Heading Structure
	// -------------------------------------------------------------------------

	private function get_heading_structure( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) return $this->err( 'post_id is required.' );

		$post = get_post( $post_id );
		if ( ! $post ) return $this->err( "Post {$post_id} not found." );

		$all_elements = $this->get_all_elements( $post_id );
		$headings     = $this->extract_headings( $all_elements );
		$violations   = [];

		$h1_count = count( array_filter( $headings, fn( $h ) => $h['level'] === 1 ) );
		if ( $h1_count === 0 ) {
			$violations[] = [ 'type' => 'missing_h1', 'message' => 'No H1 heading found on this page.' ];
		} elseif ( $h1_count > 1 ) {
			$violations[] = [ 'type' => 'multiple_h1', 'message' => "Found {$h1_count} H1 headings — should be exactly one." ];
		}

		$prev_level = 0;
		foreach ( $headings as $h ) {
			if ( $prev_level > 0 && $h['level'] > $prev_level + 1 ) {
				$violations[] = [
					'type'       => 'skipped_level',
					'message'    => "Heading level jumps from H{$prev_level} to H{$h['level']} (element {$h['element_id']}).",
					'element_id' => $h['element_id'],
				];
			}
			$prev_level = $h['level'];
		}

		return [
			'post_id'    => $post_id,
			'title'      => $post->post_title,
			'headings'   => $headings,
			'violations' => $violations,
			'h1_count'   => $h1_count,
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function resolve_post_types( string $scope ): array {
		$types = [];
		if ( $scope === 'all' || $scope === 'pages' ) {
			$types[] = 'page';
		}
		if ( $scope === 'all' || $scope === 'templates' ) {
			$types[] = BMCP_DB_TEMPLATE_SLUG;
		}
		return empty( $types ) ? [ 'page', BMCP_DB_TEMPLATE_SLUG ] : $types;
	}

	private function get_all_elements( int $post_id ): array {
		$all = [];
		foreach ( [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER ] as $meta_key ) {
			$elements = get_post_meta( $post_id, $meta_key, true );
			if ( is_array( $elements ) ) {
				$all = array_merge( $all, $elements );
			}
		}
		return $all;
	}

	private function extract_headings( array $elements ): array {
		$headings = [];
		$tag_map  = [ 'h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6 ];

		foreach ( $elements as $el ) {
			$name = $el['name'] ?? '';
			if ( $name !== 'heading' && $name !== 'post-title' ) continue;

			$settings = $el['settings'] ?? [];
			$tag      = strtolower( $settings['tag'] ?? 'h2' );
			$level    = $tag_map[ $tag ] ?? null;
			if ( ! $level ) continue;

			$text = $settings['text'] ?? '';
			if ( is_string( $text ) ) {
				$text = wp_strip_all_tags( $text );
			}

			$headings[] = [
				'level'        => $level,
				'tag'          => $tag,
				'text'         => $text,
				'element_id'   => $el['id'] ?? '',
				'element_type' => $name,
			];
		}

		return $headings;
	}

	private function check_headings( int $post_id, string $title, string $post_type, array $elements ): array {
		$headings   = $this->extract_headings( $elements );
		$issues     = [];

		$h1_count = count( array_filter( $headings, fn( $h ) => $h['level'] === 1 ) );

		if ( $h1_count === 0 && $post_type !== BMCP_DB_TEMPLATE_SLUG ) {
			$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'headings', 'error',
				'No H1 heading found.',
				'Every page needs exactly one H1 that describes the main topic. Add a heading element with tag h1.'
			);
		} elseif ( $h1_count > 1 ) {
			$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'headings', 'warning',
				"Found {$h1_count} H1 headings — should be exactly one.",
				'Demote extra H1s to H2. Multiple H1s dilute the page topic signal.'
			);
		}

		$prev_level = 0;
		foreach ( $headings as $h ) {
			if ( $prev_level > 0 && $h['level'] > $prev_level + 1 ) {
				$issues[] = $this->seo_issue( $post_id, $title, $post_type, 'headings', 'warning',
					"Heading level jumps from H{$prev_level} to H{$h['level']} (element {$h['element_id']}).",
					'Don\'t skip heading levels. Use H2 after H1, H3 after H2, etc.'
				);
			}
			$prev_level = $h['level'];
		}

		return $issues;
	}

	private function seo_issue( int $post_id, string $title, string $post_type, string $type, string $severity, string $message, string $suggestion ): array {
		return [
			'severity'   => $severity,
			'type'       => $type,
			'post_id'    => $post_id,
			'title'      => $title,
			'post_type'  => $post_type,
			'message'    => $message,
			'suggestion' => $suggestion,
		];
	}
}
