<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools for searching and retrieving pre-built Bricks Builder templates
 * from assets/templates/{category}/{template-name}.json
 */
class Tool_Template_Library extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_search_templates',
				'description' => 'Search the built-in Bricks Builder template library by category name or keyword. Returns a list of matching templates with their name and category. Use this BEFORE building any section from scratch — a pre-built template almost certainly exists. Categories are discovered automatically from the assets/templates/ directory.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'category' => [
							'type'        => 'string',
							'description' => 'Exact or partial category name to filter by (e.g. "Hero", "Footer", "Pricing").',
						],
						'keyword'  => [
							'type'        => 'string',
							'description' => 'Keyword to search within template names.',
						],
						'limit'    => [
							'type'        => 'integer',
							'description' => 'Maximum results to return. Default: 20.',
						],
					],
				],
			],
			[
				'name'        => 'bricks_get_template_library',
				'description' => 'Fetch the full JSON structure of a named template from the built-in library. Returns the content array (Bricks elements), globalClasses, and a placeholder_map listing all values that need to be replaced with real business content (logos, dummy emails, Lorem ipsum text, placeholder links, dummy phone numbers). Call bricks_get_business_profile first so you have real content to substitute. Do NOT confuse this with bricks_get_template, which retrieves site Bricks templates saved in WordPress.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'template_name' => [
							'type'        => 'string',
							'description' => 'Exact template name from the library (as returned by bricks_search_templates).',
						],
					],
					'required'   => [ 'template_name' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_search_templates':
				return $this->search_templates(
					$this->str_arg( $args, 'category' ),
					$this->str_arg( $args, 'keyword' ),
					$this->int_arg( $args, 'limit', 20 )
				);
			case 'bricks_get_template_library':
				return $this->get_template(
					$this->str_arg( $args, 'template_name' )
				);
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	// -------------------------------------------------------------------------

	private function get_templates_dir(): string {
		return BMCP_PLUGIN_DIR . 'assets/templates/';
	}

	/**
	 * Scans assets/templates/{category}/*.json and returns a flat index.
	 * Category = folder name (title-cased, hyphens/underscores → spaces).
	 * Name     = filename without .json (title-cased, hyphens/underscores → spaces).
	 */
	private function index_templates(): array {
		$dir = $this->get_templates_dir();
		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$items = [];
		foreach ( glob( $dir . '*', GLOB_ONLYDIR ) as $cat_dir ) {
			$category = ucwords( str_replace( [ '-', '_' ], ' ', basename( $cat_dir ) ) );
			$files    = glob( $cat_dir . '/*.json' );
			if ( ! $files ) {
				continue;
			}
			foreach ( $files as $file ) {
				$slug    = basename( $file, '.json' );
				$name    = ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
				$items[] = [
					'name'     => $name,
					'category' => $category,
					'path'     => $file,
				];
			}
		}
		return $items;
	}

	private function search_templates( string $category, string $keyword, int $limit ): array {
		$results = [];

		foreach ( $this->index_templates() as $tpl ) {
			if ( $category !== '' && stripos( $tpl['category'], $category ) === false ) {
				continue;
			}
			if ( $keyword !== '' && stripos( $tpl['name'], $keyword ) === false ) {
				continue;
			}
			$results[] = [
				'name'     => $tpl['name'],
				'category' => $tpl['category'],
			];
			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		return [
			'count'     => count( $results ),
			'templates' => $results,
			'note'      => count( $results ) === 0
				? 'No templates found. Add JSON files to assets/templates/{category}/ to populate the library.'
				: 'Use bricks_get_template_library with the exact template name to fetch its full structure.',
		];
	}

	private function get_template( string $template_name ): array|\WP_Error {
		if ( empty( $template_name ) ) {
			return $this->err( 'template_name is required.' );
		}

		foreach ( $this->index_templates() as $tpl ) {
			if ( strtolower( $tpl['name'] ) !== strtolower( $template_name ) ) {
				continue;
			}

			$json = file_get_contents( $tpl['path'] );
			if ( $json === false ) {
				return $this->err( 'Could not read template file.' );
			}

			$decoded = json_decode( $json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return $this->err( 'Failed to parse template JSON: ' . json_last_error_msg() );
			}

			$content         = $decoded['content']       ?? [];
			$global_classes  = $decoded['globalClasses'] ?? [];
			$placeholder_map = $this->detect_placeholders( $content );

			return [
				'name'            => $tpl['name'],
				'category'        => $tpl['category'],
				'content'         => $content,
				'globalClasses'   => $global_classes,
				'placeholder_map' => $placeholder_map,
				'note'            => empty( $placeholder_map )
					? 'No placeholder values detected. Template is ready to use after validation.'
					: 'Replace placeholder_map values with real content from bricks_get_business_profile before writing.',
			];
		}

		return $this->err( "Template not found: {$template_name}. Use bricks_search_templates to list available templates." );
	}

	// -------------------------------------------------------------------------
	// Placeholder detection
	// -------------------------------------------------------------------------

	private function detect_placeholders( array $elements ): array {
		$placeholders = [];
		$this->scan_value( $elements, $placeholders );
		return $placeholders;
	}

	private function scan_value( $value, array &$found, string $path = '' ): void {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$child_path = $path !== '' ? "{$path}.{$k}" : (string) $k;
				$this->scan_value( $v, $found, $child_path );
			}
			return;
		}

		if ( ! is_string( $value ) || $value === '' ) {
			return;
		}

		$type = null;

		if ( preg_match( '/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}\b/i', $value ) ) {
			$type = 'email';
		} elseif ( stripos( $value, 'logoipsum' ) !== false || stripos( $value, 'via.placeholder' ) !== false ) {
			$type = 'logo_or_placeholder_image';
		} elseif ( stripos( $value, 'lorem ipsum' ) !== false || stripos( $value, 'lorem' ) !== false ) {
			$type = 'lorem_ipsum_text';
		} elseif ( $value === '#' ) {
			$type = 'placeholder_link';
		} elseif ( strpos( $value, '+111' ) !== false || strpos( $value, '555-' ) !== false ) {
			$type = 'phone_number';
		}

		if ( $type !== null ) {
			$found[] = [
				'path'  => $path,
				'value' => strlen( $value ) > 80 ? substr( $value, 0, 80 ) . '…' : $value,
				'type'  => $type,
			];
		}
	}
}
