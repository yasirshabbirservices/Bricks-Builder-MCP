<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Skills extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_list_skills',
				'description' => 'List all available agent skills — best-practice guides the AI loads on demand. Returns slug, title, description, and when_to_use for each skill. Very low token cost. Call bricks_get_skill(skill) to load full content for the current task.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_get_skill',
				'description' => 'Load the full content of a specific best-practice skill guide. Call before the relevant task — e.g. "accessibility" before building any form or nav, "performance" before image-heavy sections, "bricks-elements" before writing any element array.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'skill' => [
							'type'        => 'string',
							'description' => 'Skill slug, e.g. "accessibility", "css-best-practices", "layout-patterns". Call bricks_list_skills to see all available slugs.',
						],
					],
					'required' => [ 'skill' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_list_skills':
				return [
					'skills' => $this->index_skills(),
					'note'   => 'Call bricks_get_skill(skill) to load full content for any skill relevant to your current task. Load only what the task requires.',
				];
			case 'bricks_get_skill':
				return $this->get_skill( $this->str_arg( $args, 'skill' ) );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	/**
	 * Returns the lightweight skills index.
	 * Public so Tool_Context can call it when building session context.
	 *
	 * @return array<int, array{slug: string, title: string, description: string, when_to_use: string}>
	 */
	public function index_skills(): array {
		$dir   = BMCP_PLUGIN_DIR . 'assets/skills/';
		$items = [];

		if ( ! is_dir( $dir ) ) {
			return $items;
		}

		$files = glob( $dir . '*.md' );
		if ( ! $files ) {
			return $items;
		}

		foreach ( $files as $file ) {
			$slug    = basename( $file, '.md' );
			$content = file_get_contents( $file );
			$meta    = $this->parse_frontmatter( $content !== false ? $content : '' );

			$items[] = [
				'slug'        => $slug,
				'title'       => $meta['title']       ?? $slug,
				'description' => $meta['description'] ?? '',
				'when_to_use' => $meta['when_to_use'] ?? '',
			];
		}

		usort( $items, fn( $a, $b ) => strcmp( $a['slug'], $b['slug'] ) );

		return $items;
	}

	private function get_skill( string $slug ): array|\WP_Error {
		// sanitize_key strips everything except [a-z0-9_-] — prevents path traversal
		$slug = sanitize_key( $slug );

		if ( empty( $slug ) ) {
			return $this->err( '"skill" is required. Call bricks_list_skills to see available slugs.' );
		}

		$file = BMCP_PLUGIN_DIR . 'assets/skills/' . $slug . '.md';

		if ( ! file_exists( $file ) ) {
			return $this->err( "Skill not found: {$slug}. Call bricks_list_skills to see available slugs." );
		}

		$raw  = file_get_contents( $file );
		$raw  = $raw !== false ? $raw : '';
		$meta = $this->parse_frontmatter( $raw );

		// Strip frontmatter block from the body
		$body = trim( (string) preg_replace( '/^---\s*\n.*?\n---\s*\n/s', '', $raw ) );

		return [
			'skill'       => $slug,
			'title'       => $meta['title']       ?? $slug,
			'when_to_use' => $meta['when_to_use'] ?? '',
			'content'     => $body,
		];
	}

	/**
	 * Parses a YAML-style frontmatter block: ---\nkey: value\n---\n
	 *
	 * @return array<string, string>
	 */
	private function parse_frontmatter( string $content ): array {
		if ( ! preg_match( '/^---\s*\n(.*?)\n---\s*\n/s', $content, $m ) ) {
			return [];
		}

		$meta = [];
		foreach ( explode( "\n", trim( $m[1] ) ) as $line ) {
			if ( preg_match( '/^([\w]+):\s*(.+)$/', $line, $kv ) ) {
				$meta[ trim( $kv[1] ) ] = trim( $kv[2] );
			}
		}

		return $meta;
	}
}
