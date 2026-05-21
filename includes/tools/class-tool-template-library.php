<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools for searching and retrieving pre-built Bricks Builder templates
 * from the bundled CSV library (assets/Bricks Builder Templates.csv).
 */
class Tool_Template_Library extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_search_templates',
				'description' => 'Search the built-in Bricks Builder template library by category name or keyword. Returns a list of matching templates with their name and category. Use this BEFORE building any section from scratch — a pre-built template almost certainly exists. Available categories: Back To Top, Banner, Bio Links, Brands, Button, Call To Action, Cart, Coming Soon, Contact US, Counter, Email Opt-In, Error Page, FAQs, Features, Footer, Header, Hero, Pagination, Popup, Post Grid, Post Loop, Post Section, Pricing, Product Categories, Product Tabs, Products, Pros and Cons, Single Post, Single Product, Slider, Table of Contents, Team, Testimonials.',
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

	private function get_csv_path(): string {
		return BMCP_PLUGIN_DIR . 'assets/Bricks Builder Templates.csv';
	}

	private function load_csv(): array {
		$path = $this->get_csv_path();
		if ( ! file_exists( $path ) ) {
			return [];
		}
		$rows   = [];
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			return [];
		}
		while ( ( $line = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
			if ( isset( $line[0], $line[1] ) && trim( $line[0] ) !== '' ) {
				$rows[] = [
					'name' => trim( $line[0] ),
					'json' => $line[1],
				];
			}
		}
		fclose( $handle );
		return $rows;
	}

	private function search_templates( string $category, string $keyword, int $limit ): array {
		$rows    = $this->load_csv();
		$results = [];

		foreach ( $rows as $row ) {
			$name = $row['name'];

			if ( $category !== '' && stripos( $name, $category ) === false ) {
				continue;
			}
			if ( $keyword !== '' && stripos( $name, $keyword ) === false ) {
				continue;
			}

			$results[] = [
				'name'     => $name,
				'category' => $name,
			];

			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		return [
			'count'     => count( $results ),
			'templates' => $results,
			'note'      => 'Use bricks_get_template_library with the exact template name to fetch its full JSON structure.',
		];
	}

	private function get_template( string $template_name ): array|\WP_Error {
		if ( empty( $template_name ) ) {
			return $this->err( 'template_name is required.' );
		}

		$rows  = $this->load_csv();
		$found = null;

		foreach ( $rows as $row ) {
			if ( strtolower( $row['name'] ) === strtolower( $template_name ) ) {
				$found = $row;
				break;
			}
		}

		if ( $found === null ) {
			return $this->err( "Template not found: {$template_name}. Use bricks_search_templates to list available templates." );
		}

		$decoded = json_decode( $found['json'], true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return $this->err( 'Failed to parse template JSON: ' . json_last_error_msg() );
		}

		$content         = $decoded['content']       ?? [];
		$global_classes  = $decoded['globalClasses'] ?? [];
		$placeholder_map = $this->detect_placeholders( $content );

		return [
			'name'          => $found['name'],
			'content'       => $content,
			'globalClasses' => $global_classes,
			'placeholder_map' => $placeholder_map,
			'note'          => empty( $placeholder_map )
				? 'No placeholder values detected. Template is ready to use after validation.'
				: 'Replace all values in placeholder_map with real content from bricks_get_business_profile before writing to a page.',
		];
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
