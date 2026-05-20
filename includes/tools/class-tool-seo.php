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
		if ( ! $this->has_any_seo() ) {
			return [];
		}

		$active = $this->has_yoast() ? 'Yoast SEO' : ( $this->has_rankmath() ? 'Rank Math' : 'The SEO Framework' );

		return [
			[
				'name'        => 'bricks_get_page_seo',
				'description' => "Get SEO metadata for a page or post — title, meta description, OG title/description, robots settings. Uses {$active}.",
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
					],
					'required' => [ 'post_id' ],
				],
			],
			[
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
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_get_page_seo':
				return $this->get_seo( $args );
			case 'bricks_update_page_seo':
				return $this->update_seo( $args );
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
}
