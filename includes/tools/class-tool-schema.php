<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Schema extends Tool_Base {

	private const META_KEY = '_bmcp_structured_data';

	private const ALLOWED_TYPES = [
		'FAQPage',
		'LocalBusiness',
		'MobilePhoneStore',
		'Store',
		'Product',
		'BreadcrumbList',
		'Review',
		'HowTo',
		'Organization',
	];

	public function define(): array {
		add_action( 'wp_head', [ __CLASS__, 'output_jsonld' ], 1 );

		return [
			[
				'name'        => 'bricks_get_structured_data',
				'description' => 'Read existing JSON-LD structured data for a page — checks BMCP storage, Yoast SEO, and Rank Math.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
					],
					'required' => [ 'post_id' ],
				],
			],
			[
				'name'        => 'bricks_update_structured_data',
				'description' => 'Add or update JSON-LD structured data for a page. Supports FAQPage, LocalBusiness, MobilePhoneStore, Store, Product, BreadcrumbList, Review, HowTo, Organization.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
						'schemas' => [
							'type'        => 'array',
							'description' => 'Array of schema objects. Each must have @type.',
							'items'       => [ 'type' => 'object' ],
						],
						'merge' => [
							'type'        => 'boolean',
							'description' => 'Merge with existing schemas by @type (true) or replace all (false). Default true.',
						],
					],
					'required' => [ 'post_id', 'schemas' ],
				],
			],
			[
				'name'        => 'bricks_delete_structured_data',
				'description' => 'Remove structured data from a page. Optionally remove only a specific @type.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
						'type'    => [ 'type' => 'string', 'description' => 'Specific @type to remove. Omit to remove all.' ],
					],
					'required' => [ 'post_id' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_get_structured_data':
				return $this->get_structured_data( $args );
			case 'bricks_update_structured_data':
				return $this->update_structured_data( $args );
			case 'bricks_delete_structured_data':
				return $this->delete_structured_data( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	// -------------------------------------------------------------------------

	public static function output_jsonld(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$schemas = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $schemas ) || empty( $schemas ) ) {
			return;
		}

		foreach ( $schemas as $schema ) {
			if ( ! isset( $schema['@context'] ) ) {
				$schema['@context'] = 'https://schema.org';
			}
			$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( $json ) {
				echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
			}
		}
	}

	// -------------------------------------------------------------------------

	private function get_structured_data( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) return $this->err( 'post_id is required.' );

		$post = get_post( $post_id );
		if ( ! $post ) return $this->err( "Post {$post_id} not found." );

		$results = [];

		$bmcp = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_array( $bmcp ) && ! empty( $bmcp ) ) {
			foreach ( $bmcp as $schema ) {
				$results[] = [
					'source' => 'bmcp',
					'schema' => $schema,
				];
			}
		}

		$results = array_merge( $results, $this->peek_yoast( $post_id ) );
		$results = array_merge( $results, $this->peek_rankmath( $post_id ) );

		return [
			'post_id' => $post_id,
			'count'   => count( $results ),
			'schemas' => $results,
		];
	}

	private function peek_yoast( int $post_id ): array {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return [];
		}

		$results = [];

		// Yoast stores schema pieces via the wpseo_schema_graph filter.
		// We simulate a read-only peek by checking if the graph helper exists.
		if ( class_exists( 'Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece' ) ) {
			$graph = apply_filters( 'wpseo_schema_graph_pieces', [], [
				'canonical' => get_permalink( $post_id ),
			] );
			if ( is_array( $graph ) ) {
				foreach ( $graph as $piece ) {
					if ( is_object( $piece ) && method_exists( $piece, 'generate' ) ) {
						$data = $piece->generate();
						if ( is_array( $data ) && ! empty( $data['@type'] ) ) {
							$results[] = [
								'source' => 'yoast',
								'schema' => $data,
							];
						}
					}
				}
			}
		}

		// Fallback: check Yoast's FAQ block schema stored in post content.
		$faq_blocks = get_post_meta( $post_id, '_yoast_wpseo_schema_page_type', true );
		if ( $faq_blocks && is_string( $faq_blocks ) ) {
			$results[] = [
				'source'    => 'yoast',
				'page_type' => $faq_blocks,
			];
		}

		return $results;
	}

	private function peek_rankmath( int $post_id ): array {
		if ( ! class_exists( 'RankMath' ) ) {
			return [];
		}

		$results  = [];
		$rm_schema = get_post_meta( $post_id, 'rank_math_schema', true );

		// Rank Math stores schema as a serialized array in post meta.
		if ( is_string( $rm_schema ) && ! empty( $rm_schema ) ) {
			$parsed = maybe_unserialize( $rm_schema );
			if ( is_array( $parsed ) ) {
				foreach ( $parsed as $schema ) {
					if ( is_array( $schema ) && ! empty( $schema['@type'] ) ) {
						$results[] = [
							'source' => 'rankmath',
							'schema' => $schema,
						];
					}
				}
			}
		} elseif ( is_array( $rm_schema ) && ! empty( $rm_schema ) ) {
			foreach ( $rm_schema as $schema ) {
				if ( is_array( $schema ) && ! empty( $schema['@type'] ) ) {
					$results[] = [
						'source' => 'rankmath',
						'schema' => $schema,
					];
				}
			}
		}

		return $results;
	}

	// -------------------------------------------------------------------------

	private function update_structured_data( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) return $this->err( 'post_id is required.' );

		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$post = get_post( $post_id );
		if ( ! $post ) return $this->err( "Post {$post_id} not found." );

		$schemas = $this->arr_arg( $args, 'schemas' );
		if ( empty( $schemas ) ) return $this->err( 'schemas array is required and must not be empty.' );

		$merge = $this->bool_arg( $args, 'merge', true );

		$validated = [];
		foreach ( $schemas as $i => $schema ) {
			if ( ! is_array( $schema ) ) {
				return $this->err( "Schema at index {$i} must be an object." );
			}

			$validation = $this->validate_schema( $schema, $i );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$validated[] = $this->sanitize_schema( $schema );
		}

		if ( $merge ) {
			$existing = get_post_meta( $post_id, self::META_KEY, true );
			if ( ! is_array( $existing ) ) {
				$existing = [];
			}

			// Index existing schemas by @type for merge.
			$by_type = [];
			foreach ( $existing as $s ) {
				if ( isset( $s['@type'] ) ) {
					$by_type[ $s['@type'] ] = $s;
				}
			}

			foreach ( $validated as $s ) {
				$by_type[ $s['@type'] ] = $s;
			}

			$validated = array_values( $by_type );
		}

		update_post_meta( $post_id, self::META_KEY, $validated );

		return [
			'success' => true,
			'post_id' => $post_id,
			'count'   => count( $validated ),
			'message' => count( $validated ) . ' schema(s) stored.',
		];
	}

	// -------------------------------------------------------------------------

	private function delete_structured_data( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) return $this->err( 'post_id is required.' );

		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$post = get_post( $post_id );
		if ( ! $post ) return $this->err( "Post {$post_id} not found." );

		$type = $this->str_arg( $args, 'type' );

		if ( empty( $type ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return [
				'success' => true,
				'post_id' => $post_id,
				'message' => 'All structured data removed.',
			];
		}

		$existing = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $existing ) || empty( $existing ) ) {
			return $this->err( "No structured data found on post {$post_id}." );
		}

		$filtered = array_values( array_filter( $existing, function ( $s ) use ( $type ) {
			return ! isset( $s['@type'] ) || $s['@type'] !== $type;
		} ) );

		if ( count( $filtered ) === count( $existing ) ) {
			return $this->err( "No schema with @type '{$type}' found on post {$post_id}." );
		}

		if ( empty( $filtered ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, $filtered );
		}

		return [
			'success' => true,
			'post_id' => $post_id,
			'removed' => $type,
			'remaining' => count( $filtered ),
			'message' => "@type '{$type}' removed.",
		];
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	private function validate_schema( array $schema, int $index ): true|\WP_Error {
		if ( empty( $schema['@type'] ) ) {
			return $this->err( "Schema at index {$index} must have @type." );
		}

		$type = $schema['@type'];

		if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
			return $this->err( "Unsupported @type '{$type}' at index {$index}. Allowed: " . implode( ', ', self::ALLOWED_TYPES ) );
		}

		if ( $type === 'FAQPage' ) {
			if ( empty( $schema['mainEntity'] ) || ! is_array( $schema['mainEntity'] ) ) {
				return $this->err( "FAQPage at index {$index} must have mainEntity with at least one Question." );
			}
			foreach ( $schema['mainEntity'] as $j => $entity ) {
				if ( ! is_array( $entity ) || empty( $entity['@type'] ) || $entity['@type'] !== 'Question' ) {
					return $this->err( "FAQPage mainEntity[{$j}] at index {$index} must be a Question." );
				}
			}
		}

		if ( $type === 'Product' ) {
			if ( empty( $schema['name'] ) ) {
				return $this->err( "Product at index {$index} must have a name." );
			}
			if ( empty( $schema['offers'] ) ) {
				return $this->err( "Product at index {$index} should have offers." );
			}
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Sanitization — recursive wp_kses_post on all string values.
	// -------------------------------------------------------------------------

	private function sanitize_schema( $value ) {
		if ( is_string( $value ) ) {
			return wp_kses_post( $value );
		}

		if ( is_array( $value ) ) {
			return array_map( [ $this, 'sanitize_schema' ], $value );
		}

		return $value;
	}
}
