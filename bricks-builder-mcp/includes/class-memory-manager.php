<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Memory_Manager {

	const OPTION      = 'bmcp_ai_memory';
	const MAX_ENTRIES = 500;

	public static function get_categories(): array {
		return [
			'site'        => 'Site Info',
			'design'      => 'Design Patterns',
			'errors'      => 'Errors & Solutions',
			'bricks'      => 'Bricks Patterns',
			'preferences' => 'Preferences',
			'components'  => 'Components',
			'general'     => 'General',
		];
	}

	public static function get_all(): array {
		$data = get_option( self::OPTION, [] );
		return is_array( $data ) ? $data : [];
	}

	public static function get_by_id( string $id ): ?array {
		return self::get_all()[ $id ] ?? null;
	}

	public static function add( array $data ): array {
		$all = self::get_all();

		if ( count( $all ) >= self::MAX_ENTRIES ) {
			uasort( $all, fn( $a, $b ) => $a['created_at'] - $b['created_at'] );
			unset( $all[ array_key_first( $all ) ] );
		}

		$id  = self::generate_id();
		$now = time();

		$all[ $id ] = [
			'id'         => $id,
			'category'   => self::sanitize_category( $data['category'] ?? 'general' ),
			'title'      => sanitize_text_field( $data['title'] ?? '' ),
			'content'    => sanitize_textarea_field( $data['content'] ?? '' ),
			'tags'       => array_map( 'sanitize_text_field', (array) ( $data['tags'] ?? [] ) ),
			'importance' => self::sanitize_importance( $data['importance'] ?? 'medium' ),
			'created_at' => $now,
			'updated_at' => $now,
		];

		update_option( self::OPTION, $all, false );
		return $all[ $id ];
	}

	public static function update( string $id, array $data ): ?array {
		$all = self::get_all();
		if ( ! isset( $all[ $id ] ) ) {
			return null;
		}

		$entry = $all[ $id ];

		if ( isset( $data['category'] ) )   $entry['category']   = self::sanitize_category( $data['category'] );
		if ( isset( $data['title'] ) )      $entry['title']      = sanitize_text_field( $data['title'] );
		if ( isset( $data['content'] ) )    $entry['content']    = sanitize_textarea_field( $data['content'] );
		if ( isset( $data['tags'] ) )       $entry['tags']       = array_map( 'sanitize_text_field', (array) $data['tags'] );
		if ( isset( $data['importance'] ) ) $entry['importance'] = self::sanitize_importance( $data['importance'] );

		$entry['updated_at'] = time();
		$all[ $id ]          = $entry;

		update_option( self::OPTION, $all, false );
		return $entry;
	}

	public static function delete( string $id ): bool {
		$all = self::get_all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		update_option( self::OPTION, $all, false );
		return true;
	}

	public static function search( string $query ): array {
		$q   = strtolower( $query );
		$all = self::get_all();

		return array_values( array_filter( $all, function ( $m ) use ( $q ) {
			return str_contains( strtolower( $m['title'] ), $q )
				|| str_contains( strtolower( $m['content'] ), $q )
				|| str_contains( strtolower( implode( ' ', $m['tags'] ?? [] ) ), $q );
		} ) );
	}

	public static function get_paginated( int $page, int $per_page, string $category = '', string $search = '' ): array {
		$all = self::get_all();

		if ( $category ) {
			$all = array_filter( $all, fn( $m ) => $m['category'] === $category );
		}

		if ( $search ) {
			$q   = strtolower( $search );
			$all = array_filter( $all, function ( $m ) use ( $q ) {
				return str_contains( strtolower( $m['title'] ), $q )
					|| str_contains( strtolower( $m['content'] ), $q );
			} );
		}

		uasort( $all, fn( $a, $b ) => $b['updated_at'] - $a['updated_at'] );

		$total  = count( $all );
		$offset = ( $page - 1 ) * $per_page;
		$items  = array_values( array_slice( $all, $offset, $per_page ) );

		return [
			'items'       => $items,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		];
	}

	public static function get_high_importance(): array {
		$all = self::get_all();
		$hi  = array_filter( $all, fn( $m ) => $m['importance'] === 'high' );
		uasort( $hi, fn( $a, $b ) => $b['updated_at'] - $a['updated_at'] );
		return array_values( $hi );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function sanitize_category( string $val ): string {
		return in_array( $val, array_keys( self::get_categories() ), true ) ? $val : 'general';
	}

	private static function sanitize_importance( string $val ): string {
		return in_array( $val, [ 'high', 'medium', 'low' ], true ) ? $val : 'medium';
	}

	private static function generate_id(): string {
		return substr( md5( uniqid( '', true ) ), 0, 8 );
	}
}
