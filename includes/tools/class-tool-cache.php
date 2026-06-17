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
				'description' => 'Regenerate Bricks CSS files (per-post element CSS, theme styles, global classes, color palette, variables). Call after any MCP write that changes styles — pages, templates, theme styles, global classes, or color palette. Pass post_id to regenerate a single page/template, or omit to regenerate all CSS site-wide. IMPORTANT: For best results, define styles via global classes (_cssGlobalClasses) rather than inline element settings — global class CSS regenerates reliably, while per-element inline CSS may require opening the page in Bricks editor for full compilation.',
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
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$regenerated = [];

		// Try Assets_Files first — this is the correct all-files regenerator
		// Handles: per-post CSS, theme styles, global classes, variables, color palette
		if ( class_exists( '\Bricks\Assets_Files' ) && method_exists( '\Bricks\Assets_Files', 'regenerate_css_files' ) ) {

			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					return $this->err( "Post {$post_id} not found." );
				}

				// Per-post CSS via Assets_Files
				if ( method_exists( '\Bricks\Assets_Files', 'generate_post_css_file' ) ) {
					\Bricks\Assets_Files::generate_post_css_file( $post_id );
					$regenerated[] = "post {$post_id} ({$post->post_title})";
				} else {
					// Fallback: regenerate everything
					\Bricks\Assets_Files::regenerate_css_files();
					$regenerated[] = 'all CSS files (per-post method unavailable)';
				}
			} else {
				\Bricks\Assets_Files::regenerate_css_files();
				$regenerated[] = 'all CSS files (per-post, theme styles, global classes, variables, color palette)';
			}

			return [
				'success'     => true,
				'scope'       => $post_id > 0 ? 'single' : 'all',
				'post_id'     => $post_id ?: null,
				'regenerated' => $regenerated,
				'message'     => 'CSS regenerated: ' . implode( ', ', $regenerated ) . '. Note: styles defined via _cssGlobalClasses are fully compiled. Per-element inline styles (_padding, _margin, etc.) may not fully compile until the page is saved in the Bricks editor — prefer global classes for reliable MCP styling.',
			];
		}

		// Fallback: try CSS_Files class for per-post
		if ( $post_id > 0 && class_exists( '\Bricks\CSS_Files' ) && method_exists( '\Bricks\CSS_Files', 'generate_post_css_file' ) ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return $this->err( "Post {$post_id} not found." );
			}

			\Bricks\CSS_Files::generate_post_css_file( $post_id );
			return [
				'success' => true,
				'scope'   => 'single',
				'post_id' => $post_id,
				'message' => "CSS regenerated for post {$post_id} ({$post->post_title}).",
			];
		}

		// Last resort: Breakpoints regeneration (handles breakpoint-derived CSS only)
		if ( class_exists( '\Bricks\Breakpoints' ) ) {
			\Bricks\Breakpoints::regenerate_bricks_css_files();
			return [
				'success' => true,
				'scope'   => 'all',
				'message' => 'CSS regenerated via Breakpoints fallback. Assets_Files class not available in this Bricks version — consider updating Bricks for full CSS regeneration support.',
			];
		}

		return $this->err( 'Bricks is not active — cannot regenerate CSS files.' );
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

		// Bricks CSS file cache
		do_action( 'bricks_cache_cleared' );

		return [
			'success' => true,
			'flushed' => $flushed,
			'message' => 'Cache cleared: ' . ( $flushed ? implode( ', ', $flushed ) : 'WordPress object cache only (no caching plugin detected)' ) . '.',
		];
	}
}
