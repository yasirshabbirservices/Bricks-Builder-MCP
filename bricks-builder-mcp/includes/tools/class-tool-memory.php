<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Memory extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_memory_list',
				'description' => 'List AI memories filtered by category or search query. Call this at the start of sessions to recall relevant context, patterns, and past solutions.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'category' => [ 'type' => 'string', 'description' => 'Filter by: site, design, errors, bricks, preferences, components, general' ],
						'search'   => [ 'type' => 'string', 'description' => 'Full-text search query' ],
						'page'     => [ 'type' => 'integer', 'description' => 'Page number (default: 1)' ],
					],
				],
			],
			[
				'name'        => 'bricks_memory_get',
				'description' => 'Get a specific memory by ID.',
				'inputSchema' => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id' => [ 'type' => 'string', 'description' => 'Memory ID' ],
					],
				],
			],
			[
				'name'        => 'bricks_memory_add',
				'description' => 'Save a new memory. Use this to remember important patterns, errors and fixes, user preferences, site-specific facts, and Bricks quirks discovered during a session.',
				'inputSchema' => [
					'type'       => 'object',
					'required'   => [ 'category', 'title', 'content' ],
					'properties' => [
						'category'   => [ 'type' => 'string', 'description' => 'site | design | errors | bricks | preferences | components | general' ],
						'title'      => [ 'type' => 'string', 'description' => 'Short descriptive title (max ~100 chars)' ],
						'content'    => [ 'type' => 'string', 'description' => 'Full memory content — include enough detail to be useful without context' ],
						'tags'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Optional tags for searchability' ],
						'importance' => [ 'type' => 'string', 'enum' => [ 'high', 'medium', 'low' ], 'description' => 'high = always included in system prompt; default: medium' ],
					],
				],
			],
			[
				'name'        => 'bricks_memory_update',
				'description' => 'Update an existing memory by ID.',
				'inputSchema' => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id'         => [ 'type' => 'string' ],
						'category'   => [ 'type' => 'string' ],
						'title'      => [ 'type' => 'string' ],
						'content'    => [ 'type' => 'string' ],
						'tags'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'importance' => [ 'type' => 'string', 'enum' => [ 'high', 'medium', 'low' ] ],
					],
				],
			],
			[
				'name'        => 'bricks_memory_delete',
				'description' => 'Delete a memory by ID.',
				'inputSchema' => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id' => [ 'type' => 'string' ],
					],
				],
			],
			[
				'name'        => 'bricks_memory_search',
				'description' => 'Full-text search across all memories (title, content, tags).',
				'inputSchema' => [
					'type'       => 'object',
					'required'   => [ 'query' ],
					'properties' => [
						'query' => [ 'type' => 'string', 'description' => 'Search query' ],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		return match ( $name ) {
			'bricks_memory_list'   => $this->list_memories( $args ),
			'bricks_memory_get'    => $this->get_memory( $args ),
			'bricks_memory_add'    => $this->add_memory( $args ),
			'bricks_memory_update' => $this->update_memory( $args ),
			'bricks_memory_delete' => $this->delete_memory( $args ),
			'bricks_memory_search' => $this->search_memories( $args ),
			default                => $this->err( "Unknown tool: {$name}" ),
		};
	}

	private function list_memories( array $args ): array {
		$result = \BricksMCP\Memory_Manager::get_paginated(
			max( 1, (int) ( $args['page'] ?? 1 ) ),
			20,
			sanitize_key( $args['category'] ?? '' ),
			sanitize_text_field( $args['search'] ?? '' )
		);
		return $this->success( $result );
	}

	private function get_memory( array $args ): array|\WP_Error {
		$id     = sanitize_text_field( $args['id'] ?? '' );
		$memory = \BricksMCP\Memory_Manager::get_by_id( $id );
		if ( ! $memory ) {
			return $this->err( "Memory not found: {$id}" );
		}
		return $this->success( $memory );
	}

	private function add_memory( array $args ): array|\WP_Error {
		if ( empty( $args['title'] ) || empty( $args['content'] ) || empty( $args['category'] ) ) {
			return $this->err( 'category, title, and content are required.' );
		}
		$memory = \BricksMCP\Memory_Manager::add( $args );
		return $this->success( [ 'saved' => true, 'memory' => $memory ] );
	}

	private function update_memory( array $args ): array|\WP_Error {
		$id     = sanitize_text_field( $args['id'] ?? '' );
		$memory = \BricksMCP\Memory_Manager::update( $id, $args );
		if ( ! $memory ) {
			return $this->err( "Memory not found: {$id}" );
		}
		return $this->success( [ 'updated' => true, 'memory' => $memory ] );
	}

	private function delete_memory( array $args ): array|\WP_Error {
		$id      = sanitize_text_field( $args['id'] ?? '' );
		$deleted = \BricksMCP\Memory_Manager::delete( $id );
		if ( ! $deleted ) {
			return $this->err( "Memory not found: {$id}" );
		}
		return $this->success( [ 'deleted' => true, 'id' => $id ] );
	}

	private function search_memories( array $args ): array|\WP_Error {
		$query = sanitize_text_field( $args['query'] ?? '' );
		if ( ! $query ) {
			return $this->err( 'query is required.' );
		}
		$results = \BricksMCP\Memory_Manager::search( $query );
		return $this->success( [ 'results' => $results, 'count' => count( $results ) ] );
	}
}
