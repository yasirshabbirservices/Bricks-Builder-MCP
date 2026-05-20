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
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name === 'bricks_clear_cache' ) {
			return $this->clear_cache();
		}
		return $this->err( 'Unknown tool: ' . $name );
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
