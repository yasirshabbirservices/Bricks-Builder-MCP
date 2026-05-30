<?php
namespace BricksMCP\Tools;

use BricksMCP\Bricks_Data;
use BricksMCP\History_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Search extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_search_content',
				'description' => 'Search for a text string, hex color, class ID, or any value across all Bricks pages and templates. Returns which pages/templates contain the match and where (element IDs). Use before bricks_replace_content to preview what will change.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search' => [ 'type' => 'string', 'description' => 'String to search for (plain text, hex color like #ff0000, class ID, etc.)' ],
						'scope'  => [ 'type' => 'string', 'description' => 'Where to search: all | pages | templates (default: all)', 'default' => 'all' ],
					],
					'required' => [ 'search' ],
				],
			],
			[
				'name'        => 'bricks_replace_content',
				'description' => 'Find and replace a string across all Bricks pages and/or templates. Auto-snapshots every post before modifying it so changes can be undone. Ideal for brand color changes, class ID renames, or global text updates.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search'      => [ 'type' => 'string', 'description' => 'Exact string to find (case-sensitive)' ],
						'replace'     => [ 'type' => 'string', 'description' => 'Replacement string' ],
						'scope'       => [ 'type' => 'string', 'description' => 'Where to replace: all | pages | templates (default: all)', 'default' => 'all' ],
						'dry_run'     => [ 'type' => 'boolean', 'description' => 'If true, show what would change without actually modifying anything (default: false)', 'default' => false ],
					],
					'required' => [ 'search', 'replace' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_search_content':
				return $this->search_content( $args );
			case 'bricks_replace_content':
				return $this->replace_content( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	// -------------------------------------------------------------------------

	private function search_content( array $args ): array|\WP_Error {
		$search = $this->str_arg( $args, 'search' );
		if ( ! $search ) return $this->err( '"search" is required.' );

		$scope = $this->str_arg( $args, 'scope', 'all' );
		$posts = $this->get_bricks_posts( $scope );
		$found = [];

		foreach ( $posts as $post_id => $post_type ) {
			$meta_keys = $this->meta_keys_for( $post_type );
			foreach ( $meta_keys as $meta_key ) {
				$elements = get_post_meta( $post_id, $meta_key, true );
				if ( ! $elements ) continue;

				$json = wp_json_encode( $elements );
				if ( $json && str_contains( $json, $search ) ) {
					$found[] = [
						'post_id'   => $post_id,
						'post_type' => $post_type,
						'title'     => get_the_title( $post_id ),
						'area'      => $this->area_label( $meta_key ),
					];
				}
			}
		}

		return [
			'query'   => $search,
			'matches' => $found,
			'count'   => count( $found ),
			'message' => count( $found ) > 0
				? "Found '{$search}' in " . count( $found ) . ' page/template area(s).'
				: "'{$search}' not found in any Bricks content.",
		];
	}

	private function replace_content( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$search  = $this->str_arg( $args, 'search' );
		$replace = $this->str_arg( $args, 'replace', '' );
		$scope   = $this->str_arg( $args, 'scope', 'all' );
		$dry_run = $this->bool_arg( $args, 'dry_run', false );

		if ( ! $search ) return $this->err( '"search" is required.' );
		if ( $search === $replace ) return $this->err( '"search" and "replace" must be different.' );

		$posts   = $this->get_bricks_posts( $scope );
		$changed = [];

		foreach ( $posts as $post_id => $post_type ) {
			$meta_keys    = $this->meta_keys_for( $post_type );
			$post_touched = false;

			foreach ( $meta_keys as $meta_key ) {
				$elements = get_post_meta( $post_id, $meta_key, true );
				if ( ! $elements ) continue;

				$json = wp_json_encode( $elements );
				if ( ! $json || ! str_contains( $json, $search ) ) continue;

				$area = $this->area_label( $meta_key );

				if ( ! $dry_run ) {
					// Snapshot each affected area individually so every area can be restored
					History_Manager::capture( $post_id, $area, 'bricks_replace_content' );

					$new_json  = str_replace( $search, $replace, $json );
					$new_elems = json_decode( $new_json, true );
					if ( json_last_error() === JSON_ERROR_NONE && is_array( $new_elems ) ) {
						update_post_meta( $post_id, $meta_key, $new_elems );
						$post_touched = true;
					}
				}

				$changed[] = [
					'post_id'   => $post_id,
					'post_type' => $post_type,
					'title'     => get_the_title( $post_id ),
					'area'      => $area,
				];
			}

			if ( $post_touched ) {
				wp_update_post( [ 'ID' => $post_id, 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ] );
			}
		}

		return [
			'search'   => $search,
			'replace'  => $replace,
			'dry_run'  => $dry_run,
			'changed'  => $changed,
			'count'    => count( $changed ),
			'message'  => $dry_run
				? count( $changed ) . ' area(s) would be updated (dry run — no changes made).'
				: count( $changed ) . ' area(s) updated. Snapshots saved for all modified posts.',
		];
	}

	// -------------------------------------------------------------------------

	/** @return array<int, string> post_id => post_type */
	private function get_bricks_posts( string $scope ): array {
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

		$result = [];
		foreach ( $query->posts as $post_id ) {
			$result[ (int) $post_id ] = get_post_type( $post_id );
		}
		return $result;
	}

	private function meta_keys_for( string $post_type ): array {
		$template_slug = defined( 'BRICKS_DB_TEMPLATE_SLUG' ) ? BRICKS_DB_TEMPLATE_SLUG : 'bricks_template';
		if ( $post_type === $template_slug ) {
			return [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER ];
		}
		return [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER ];
	}

	private function area_label( string $meta_key ): string {
		$map = [
			BMCP_DB_PAGE_CONTENT => 'content',
			BMCP_DB_PAGE_HEADER  => 'header',
			BMCP_DB_PAGE_FOOTER  => 'footer',
		];
		return $map[ $meta_key ] ?? $meta_key;
	}
}
