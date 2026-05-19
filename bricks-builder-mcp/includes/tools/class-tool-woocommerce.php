<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_WooCommerce extends Tool_Base {

	private static bool $woo_active;

	public function __construct() {
		self::$woo_active = class_exists( 'WooCommerce' );
	}

	public function define(): array {
		if ( ! self::$woo_active ) {
			return [];
		}

		return [
			[
				'name'        => 'bricks_list_products',
				'description' => 'List WooCommerce products. Returns product id, name, price, status, stock status, and permalink. Does NOT return payment or financial data.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (default 20)', 'default' => 20 ],
						'page'     => [ 'type' => 'integer', 'description' => 'Page number', 'default' => 1 ],
						'status'   => [ 'type' => 'string', 'description' => 'publish | draft | any', 'default' => 'publish' ],
						'category' => [ 'type' => 'integer', 'description' => 'Filter by product_cat term ID' ],
						'search'   => [ 'type' => 'string', 'description' => 'Search keyword' ],
						'featured' => [ 'type' => 'boolean', 'description' => 'Filter featured products only' ],
					],
				],
			],
			[
				'name'        => 'bricks_get_product',
				'description' => 'Get full details for a WooCommerce product — name, description, price, stock, images, categories, attributes. Payment data is never included.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id' => [ 'type' => 'integer', 'description' => 'WooCommerce product post ID' ],
					],
					'required' => [ 'product_id' ],
				],
			],
			[
				'name'        => 'bricks_list_product_categories',
				'description' => 'List all WooCommerce product categories.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (default 100)', 'default' => 100 ],
						'parent'   => [ 'type' => 'integer', 'description' => 'Parent category ID (0 = top-level)' ],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( ! self::$woo_active ) {
			return $this->err( 'WooCommerce is not active on this site.' );
		}

		switch ( $name ) {
			case 'bricks_list_products':
				return $this->list_products( $args );
			case 'bricks_get_product':
				return $this->get_product( $args );
			case 'bricks_list_product_categories':
				return $this->list_product_categories( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function list_products( array $args ): array {
		$per_page = min( $this->int_arg( $args, 'per_page', 20 ), 100 );
		$page     = max( $this->int_arg( $args, 'page', 1 ), 1 );
		$status   = $this->str_arg( $args, 'status', 'publish' );
		$search   = $this->str_arg( $args, 'search' );

		$query_args = [
			'post_type'      => 'product',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $search ) {
			$query_args['s'] = sanitize_text_field( $search );
		}

		if ( isset( $args['category'] ) ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $this->int_arg( $args, 'category' ),
				],
			];
		}

		if ( $this->bool_arg( $args, 'featured' ) ) {
			$query_args['tax_query'] = array_merge( $query_args['tax_query'] ?? [], [
				[
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'featured',
				],
			] );
		}

		$query    = new \WP_Query( $query_args );
		$products = [];

		foreach ( $query->posts as $post ) {
			$product    = wc_get_product( $post->ID );
			if ( ! $product ) continue;

			$products[] = [
				'id'           => $post->ID,
				'name'         => $product->get_name(),
				'sku'          => $product->get_sku(),
				'status'       => $product->get_status(),
				'type'         => $product->get_type(),
				'price'        => $product->get_price(),
				'regular_price'=> $product->get_regular_price(),
				'sale_price'   => $product->get_sale_price(),
				'currency'     => get_woocommerce_currency_symbol(),
				'stock_status' => $product->get_stock_status(),
				'stock_qty'    => $product->get_stock_quantity(),
				'featured'     => $product->is_featured(),
				'permalink'    => get_permalink( $post->ID ),
				'image_url'    => wp_get_attachment_image_url( $product->get_image_id(), 'medium' ) ?: '',
			];
		}

		return [
			'products'    => $products,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	private function get_product( array $args ): array|\WP_Error {
		$product_id = $this->int_arg( $args, 'product_id' );
		if ( ! $product_id ) return $this->err( '"product_id" is required.' );

		$product = wc_get_product( $product_id );
		if ( ! $product ) return $this->err( "Product {$product_id} not found." );

		$categories = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
		$tags       = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );

		// Gallery images (safe — never financial data)
		$gallery_ids  = $product->get_gallery_image_ids();
		$gallery_urls = array_map( fn( $id ) => wp_get_attachment_url( $id ), $gallery_ids );

		return [
			'id'             => $product_id,
			'name'           => $product->get_name(),
			'sku'            => $product->get_sku(),
			'slug'           => $product->get_slug(),
			'status'         => $product->get_status(),
			'type'           => $product->get_type(),
			'description'    => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'price'          => $product->get_price(),
			'regular_price'  => $product->get_regular_price(),
			'sale_price'     => $product->get_sale_price(),
			'currency'       => get_woocommerce_currency_symbol(),
			'stock_status'   => $product->get_stock_status(),
			'stock_qty'      => $product->get_stock_quantity(),
			'manage_stock'   => $product->get_manage_stock(),
			'featured'       => $product->is_featured(),
			'virtual'        => $product->is_virtual(),
			'downloadable'   => $product->is_downloadable(),
			'categories'     => $categories,
			'tags'           => $tags,
			'image_url'      => wp_get_attachment_url( $product->get_image_id() ),
			'gallery_urls'   => array_values( array_filter( $gallery_urls ) ),
			'attributes'     => $this->format_attributes( $product ),
			'permalink'      => get_permalink( $product_id ),
		];
	}

	private function list_product_categories( array $args ): array {
		$per_page = min( $this->int_arg( $args, 'per_page', 100 ), 500 );

		$term_args = [
			'taxonomy'   => 'product_cat',
			'number'     => $per_page,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		if ( isset( $args['parent'] ) ) {
			$term_args['parent'] = $this->int_arg( $args, 'parent' );
		}

		$terms      = get_terms( $term_args );
		$categories = [];

		if ( is_wp_error( $terms ) ) {
			return [ 'categories' => [] ];
		}

		foreach ( $terms as $term ) {
			$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
			$categories[] = [
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'parent'      => $term->parent,
				'count'       => $term->count,
				'description' => $term->description,
				'image_url'   => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : null,
				'url'         => get_term_link( $term ),
			];
		}

		return [ 'categories' => $categories, 'total' => count( $categories ) ];
	}

	private function format_attributes( \WC_Product $product ): array {
		$attributes = [];
		foreach ( $product->get_attributes() as $attribute ) {
			$name = $attribute instanceof \WC_Product_Attribute
				? wc_attribute_label( $attribute->get_name() )
				: $attribute->get_name();

			$values = $attribute instanceof \WC_Product_Attribute
				? $attribute->get_options()
				: [];

			$attributes[] = [ 'name' => $name, 'values' => $values ];
		}
		return $attributes;
	}
}
