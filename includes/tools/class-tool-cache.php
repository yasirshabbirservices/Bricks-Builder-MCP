<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Cache extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_clear_cache',
				'description' => 'Clear all caches on the WordPress site — detects and flushes WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, and the WordPress object cache. Call this after creating or updating pages/templates so changes are immediately visible.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_regenerate_css',
				'description' => 'Regenerate all Bricks CSS files. IMPORTANT: Bricks compiles element styles into static CSS files only when saved through the editor. When you create or update pages, templates, theme styles, or global classes via MCP tools, the element data is saved but CSS is NOT compiled. Call this tool after any write operation that changes visual styles to ensure changes are visible on the frontend. Optionally pass a post_id to regenerate CSS for a single page/template instead of the entire site.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [
							'type'        => 'integer',
							'description' => 'Optional. Regenerate CSS for a specific page/template only. Omit to regenerate all CSS files site-wide.',
						],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name === 'bricks_clear_cache' ) {
			return $this->clear_cache();
		}
		if ( $name === 'bricks_regenerate_css' ) {
			return $this->regenerate_css( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function regenerate_css( array $args ): array|\WP_Error {
		if ( ! class_exists( '\Bricks\Breakpoints' ) ) {
			return $this->err( 'Bricks is not active — cannot regenerate CSS files.' );
		}

		$post_id = (int) ( $args['post_id'] ?? 0 );

		if ( $post_id > 0 ) {
			// Regenerate CSS for a single post/template
			$post = get_post( $post_id );
			if ( ! $post ) {
				return $this->err( "Post {$post_id} not found." );
			}

			// Bricks generates per-post CSS via its CSS_Files class
			if ( class_exists( '\Bricks\CSS_Files' ) && method_exists( '\Bricks\CSS_Files', 'generate_post_css_file' ) ) {
				\Bricks\CSS_Files::generate_post_css_file( $post_id );
				return [
					'success' => true,
					'scope'   => 'single',
					'post_id' => $post_id,
					'message' => "CSS regenerated for post {$post_id} ({$post->post_title}).",
				];
			}

			// Fallback: regenerate all if per-post method unavailable
			\Bricks\Breakpoints::regenerate_bricks_css_files();
			return [
				'success' => true,
				'scope'   => 'all',
				'post_id' => $post_id,
				'message' => "Per-post CSS regeneration not available in this Bricks version. Regenerated all CSS files instead.",
			];
		}

		// Regenerate all CSS files site-wide
		\Bricks\Breakpoints::regenerate_bricks_css_files();

		return [
			'success' => true,
			'scope'   => 'all',
			'message' => 'All Bricks CSS files regenerated. Element styles from MCP writes are now compiled and visible on the frontend.',
		];
	}

	private function clear_cache(): array {
		$flushed = [];

		// WordPress object cache
		wp_cache_flush();
		$flushed[] = 'WordPress object cache';

		// WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$flushed[] = 'WP Rocket';
		}

		// LiteSpeed Cache
		if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
			\LiteSpeed_Cache_API::purge_all();
			$flushed[] = 'LiteSpeed Cache';
		} elseif ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$flushed[] = 'LiteSpeed Cache';
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$flushed[] = 'W3 Total Cache';
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$flushed[] = 'WP Super Cache';
		}

		// Autoptimize
		if ( class_exists( 'autoptimizeCache' ) ) {
			\autoptimizeCache::clearall();
			$flushed[] = 'Autoptimize';
		}

		// Comet Cache / ZenCache
		if ( class_exists( 'comet_cache' ) ) {
			\comet_cache::clear();
			$flushed[] = 'Comet Cache';
		}

		// Breeze (Cloudways)
		if ( class_exists( 'Breeze_Admin' ) ) {
			do_action( 'breeze_clear_all_cache' );
			$flushed[] = 'Breeze';
		}

		// SG Optimizer (SiteGround)
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$flushed[] = 'SG Optimizer';
		}

		// Bricks CSS file cache — bump post modified to re-trigger CSS regeneration
		do_action( 'bricks_cache_cleared' );

		return [
			'success' => true,
			'flushed' => $flushed,
			'message' => 'Cache cleared: ' . ( $flushed ? implode( ', ', $flushed ) : 'WordPress object cache only (no caching plugin detected)' ) . '.',
		];
	}
}
