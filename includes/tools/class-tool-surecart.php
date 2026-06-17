<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_SureCart extends Tool_Base {

	private static bool $sc_active;

	public function __construct() {
		self::$sc_active = class_exists( 'SureCart' ) || post_type_exists( 'sc_product' );
	}

	public function define(): array {
		$tools = [];

		// Data tools require SureCart active.
		if ( self::$sc_active ) {
			$tools[] = [
				'name'        => 'surecart_list_products',
				'description' => 'List SureCart products. Returns id, title, slug, status, permalink, sc_id, featured image, price range, and stock info. Never exposes payment or financial credentials.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page'   => [ 'type' => 'integer', 'description' => 'Results per page (default 20)',   'default' => 20 ],
						'page'       => [ 'type' => 'integer', 'description' => 'Page number',                     'default' => 1 ],
						'status'     => [ 'type' => 'string',  'description' => 'publish | draft | any',           'default' => 'publish' ],
						'search'     => [ 'type' => 'string',  'description' => 'Search keyword' ],
						'collection' => [ 'type' => 'integer', 'description' => 'Filter by sc_collection term ID' ],
					],
				],
			];

			$tools[] = [
				'name'        => 'surecart_get_product',
				'description' => 'Get full details for a SureCart product — title, description, sc_id, prices, stock, images, collections. Payment data is never included.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id' => [ 'type' => 'integer', 'description' => 'SureCart product post ID' ],
					],
					'required' => [ 'product_id' ],
				],
			];

			$tools[] = [
				'name'        => 'surecart_list_collections',
				'description' => 'List SureCart collections (sc_collection taxonomy). Returns term_id, name, slug, description, count, parent, and permalink.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (default 100)', 'default' => 100 ],
						'parent'   => [ 'type' => 'integer', 'description' => 'Parent term ID (0 = top-level)' ],
					],
				],
			];

			$tools[] = [
				'name'        => 'surecart_get_collection',
				'description' => 'Get a single SureCart collection with its details and list of products.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'collection_id' => [ 'type' => 'integer', 'description' => 'sc_collection term ID' ],
					],
					'required' => [ 'collection_id' ],
				],
			];

			$tools[] = [
				'name'        => 'surecart_list_forms',
				'description' => 'List SureCart checkout forms (sc_form CPT). Returns id, title, status, and shortcode.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (default 20)', 'default' => 20 ],
						'page'     => [ 'type' => 'integer', 'description' => 'Page number',                   'default' => 1 ],
						'status'   => [ 'type' => 'string',  'description' => 'publish | draft | any',         'default' => 'publish' ],
					],
				],
			];
		}

		// Reference tools always available — static data useful for planning.
		$tools[] = [
			'name'        => 'surecart_get_dynamic_tags',
			'description' => 'Returns a reference guide of all SureCart Bricks dynamic data tags, organized by category with usage notes.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],
		];

		$tools[] = [
			'name'        => 'surecart_get_bricks_elements',
			'description' => 'Returns a reference guide of all SureCart Bricks elements with their controls and hierarchy examples.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],
		];

		$tools[] = [
			'name'        => 'surecart_get_template_guide',
			'description' => 'Returns guidance for building SureCart templates in Bricks — template types, build patterns, query loops, shortcodes.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],
		];

		return $tools;
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		// Reference tools do not require SureCart active.
		switch ( $name ) {
			case 'surecart_get_dynamic_tags':
				return $this->get_dynamic_tags();
			case 'surecart_get_bricks_elements':
				return $this->get_bricks_elements();
			case 'surecart_get_template_guide':
				return $this->get_template_guide();
		}

		// Data tools require SureCart.
		if ( ! self::$sc_active ) {
			return $this->err( 'SureCart is not active on this site.' );
		}

		switch ( $name ) {
			case 'surecart_list_products':
				return $this->list_products( $args );
			case 'surecart_get_product':
				return $this->get_product( $args );
			case 'surecart_list_collections':
				return $this->list_collections( $args );
			case 'surecart_get_collection':
				return $this->get_collection( $args );
			case 'surecart_list_forms':
				return $this->list_forms( $args );
		}

		return $this->err( 'Unknown tool: ' . $name );
	}

	// -------------------------------------------------------------------------
	// Data tools
	// -------------------------------------------------------------------------

	private function list_products( array $args ): array {
		$per_page = min( $this->int_arg( $args, 'per_page', 20 ), 100 );
		$page     = max( $this->int_arg( $args, 'page', 1 ), 1 );
		$status   = $this->str_arg( $args, 'status', 'publish' );
		$search   = $this->str_arg( $args, 'search' );

		$query_args = [
			'post_type'      => 'sc_product',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $search ) {
			$query_args['s'] = sanitize_text_field( $search );
		}

		if ( isset( $args['collection'] ) ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'sc_collection',
					'field'    => 'term_id',
					'terms'    => $this->int_arg( $args, 'collection' ),
				],
			];
		}

		$query    = new \WP_Query( $query_args );
		$products = [];

		foreach ( $query->posts as $post ) {
			$sc_id           = get_post_meta( $post->ID, 'sc_id', true );
			$min_price       = get_post_meta( $post->ID, 'min_price_amount', true );
			$max_price       = get_post_meta( $post->ID, 'max_price_amount', true );
			$available_stock = get_post_meta( $post->ID, 'available_stock', true );
			$stock_enabled   = get_post_meta( $post->ID, 'stock_enabled', true );

			$products[] = [
				'id'              => $post->ID,
				'title'           => get_the_title( $post->ID ),
				'slug'            => $post->post_name,
				'status'          => $post->post_status,
				'permalink'       => get_permalink( $post->ID ),
				'sc_id'           => $sc_id ?: null,
				'featured_image'  => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '',
				'min_price'       => $min_price !== '' ? $min_price : null,
				'max_price'       => $max_price !== '' ? $max_price : null,
				'available_stock' => $available_stock !== '' ? (int) $available_stock : null,
				'stock_enabled'   => ! empty( $stock_enabled ),
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
		if ( ! $product_id ) {
			return $this->err( '"product_id" is required.' );
		}

		$post = get_post( $product_id );
		if ( ! $post || $post->post_type !== 'sc_product' ) {
			return $this->err( "SureCart product {$product_id} not found." );
		}

		$sc_id                       = get_post_meta( $product_id, 'sc_id', true );
		$min_price                   = get_post_meta( $product_id, 'min_price_amount', true );
		$max_price                   = get_post_meta( $product_id, 'max_price_amount', true );
		$available_stock             = get_post_meta( $product_id, 'available_stock', true );
		$stock_enabled               = get_post_meta( $product_id, 'stock_enabled', true );
		$allow_out_of_stock          = get_post_meta( $product_id, 'allow_out_of_stock_purchases', true );
		$featured                    = get_post_meta( $product_id, 'featured', true );
		$recurring                   = get_post_meta( $product_id, 'recurring', true );
		$shipping_enabled            = get_post_meta( $product_id, 'shipping_enabled', true );

		// Collections (sc_collection taxonomy).
		$collections = wp_get_post_terms( $product_id, 'sc_collection', [ 'fields' => 'all' ] );
		$coll_data   = [];
		if ( ! is_wp_error( $collections ) ) {
			foreach ( $collections as $term ) {
				$coll_data[] = [
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'slug'    => $term->slug,
				];
			}
		}

		// Gallery images — try REST field first, fallback to featured image only.
		$gallery = [];
		$thumbnail_url = get_the_post_thumbnail_url( $product_id, 'large' );
		if ( $thumbnail_url ) {
			$gallery[] = $thumbnail_url;
		}
		$gallery_meta = get_post_meta( $product_id, 'gallery', true );
		if ( is_array( $gallery_meta ) ) {
			foreach ( $gallery_meta as $attachment_id ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					$gallery[] = $url;
				}
			}
		}

		return [
			'id'                          => $product_id,
			'title'                       => get_the_title( $product_id ),
			'slug'                        => $post->post_name,
			'status'                      => $post->post_status,
			'description'                 => $post->post_content,
			'excerpt'                     => $post->post_excerpt,
			'sc_id'                       => $sc_id ?: null,
			'min_price'                   => $min_price !== '' ? $min_price : null,
			'max_price'                   => $max_price !== '' ? $max_price : null,
			'available_stock'             => $available_stock !== '' ? (int) $available_stock : null,
			'stock_enabled'               => ! empty( $stock_enabled ),
			'allow_out_of_stock_purchases'=> ! empty( $allow_out_of_stock ),
			'featured'                    => ! empty( $featured ),
			'recurring'                   => ! empty( $recurring ),
			'shipping_enabled'            => ! empty( $shipping_enabled ),
			'featured_image'              => $thumbnail_url ?: '',
			'gallery'                     => array_values( array_unique( $gallery ) ),
			'collections'                 => $coll_data,
			'permalink'                   => get_permalink( $product_id ),
		];
	}

	private function list_collections( array $args ): array {
		$per_page = min( $this->int_arg( $args, 'per_page', 100 ), 500 );

		$term_args = [
			'taxonomy'   => 'sc_collection',
			'number'     => $per_page,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		if ( isset( $args['parent'] ) ) {
			$term_args['parent'] = $this->int_arg( $args, 'parent' );
		}

		$terms       = get_terms( $term_args );
		$collections = [];

		if ( is_wp_error( $terms ) ) {
			return [ 'collections' => [] ];
		}

		foreach ( $terms as $term ) {
			$collections[] = [
				'term_id'     => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'count'       => $term->count,
				'parent'      => $term->parent,
				'permalink'   => get_term_link( $term ),
			];
		}

		return [ 'collections' => $collections, 'total' => count( $collections ) ];
	}

	private function get_collection( array $args ): array|\WP_Error {
		$collection_id = $this->int_arg( $args, 'collection_id' );
		if ( ! $collection_id ) {
			return $this->err( '"collection_id" is required.' );
		}

		$term = get_term( $collection_id, 'sc_collection' );
		if ( ! $term || is_wp_error( $term ) ) {
			return $this->err( "SureCart collection {$collection_id} not found." );
		}

		// Fetch products in this collection.
		$product_query = new \WP_Query( [
			'post_type'      => 'sc_product',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'tax_query'      => [
				[
					'taxonomy' => 'sc_collection',
					'field'    => 'term_id',
					'terms'    => $collection_id,
				],
			],
		] );

		$products = [];
		foreach ( $product_query->posts as $post ) {
			$products[] = [
				'id'        => $post->ID,
				'title'     => get_the_title( $post->ID ),
				'slug'      => $post->post_name,
				'permalink' => get_permalink( $post->ID ),
			];
		}

		return [
			'term_id'       => $term->term_id,
			'name'          => $term->name,
			'slug'          => $term->slug,
			'description'   => $term->description,
			'count'         => $term->count,
			'parent'        => $term->parent,
			'permalink'     => get_term_link( $term ),
			'products'      => $products,
			'product_count' => count( $products ),
		];
	}

	private function list_forms( array $args ): array {
		$per_page = min( $this->int_arg( $args, 'per_page', 20 ), 100 );
		$page     = max( $this->int_arg( $args, 'page', 1 ), 1 );
		$status   = $this->str_arg( $args, 'status', 'publish' );

		$query = new \WP_Query( [
			'post_type'      => 'sc_form',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$forms = [];
		foreach ( $query->posts as $post ) {
			$forms[] = [
				'id'        => $post->ID,
				'title'     => get_the_title( $post->ID ),
				'status'    => $post->post_status,
				'shortcode' => '[sc_form id="' . $post->ID . '"]',
			];
		}

		return [
			'forms'       => $forms,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	// -------------------------------------------------------------------------
	// Reference tools (static data — always available)
	// -------------------------------------------------------------------------

	private function get_dynamic_tags(): array {
		return [
			'usage_notes' => 'Use dynamic tags in Bricks with {tag_name} syntax. Filter suffixes are appended with a colon, e.g. {sc_product_price:raw}. Tags resolve in the context of the current SureCart product (set by the Product element or a query loop on sc_product).',
			'categories'  => [
				'product_static' => [
					'description' => 'Static product data tags — resolve from post meta, available anywhere inside a Product element or sc_product query loop.',
					'tags'        => [
						[
							'tag'         => 'sc_product_price',
							'description' => 'Formatted product price. Filters: :value (numeric), :raw (unformatted integer in cents).',
						],
						[
							'tag'         => 'sc_product_scratch_price',
							'description' => 'Original / compare-at price (before sale). Same :value and :raw filters.',
						],
						[
							'tag'         => 'sc_product_price_range',
							'description' => 'Price range string when multiple prices exist.',
						],
						[
							'tag'         => 'sc_product_description',
							'description' => 'Full product description. Filter :N limits to N words, e.g. {sc_product_description:20}.',
						],
						[
							'tag'         => 'sc_product_stock',
							'description' => 'Stock information. Filters: :available (available stock), :on_hand (on-hand stock), :held (held/reserved stock).',
						],
						[
							'tag'         => 'sc_product_sku',
							'description' => 'Product SKU.',
						],
						[
							'tag'         => 'sc_product_on_sale',
							'description' => 'Returns "true" or "false" — useful for conditional visibility.',
						],
						[
							'tag'         => 'sc_product_trial',
							'description' => 'Trial period text (e.g. "14 days").',
						],
						[
							'tag'         => 'sc_product_billing_interval',
							'description' => 'Billing interval text (e.g. "Monthly", "Yearly").',
						],
						[
							'tag'         => 'sc_product_setup_fee',
							'description' => 'One-time setup fee amount.',
						],
					],
				],
				'product_interactive' => [
					'description' => 'Interactive product tags — update dynamically when the customer selects a price variant via PriceChooser.',
					'tags'        => [
						[
							'tag'         => 'sc_product_selected_price',
							'description' => 'Currently selected price amount (updates on PriceChooser selection).',
						],
						[
							'tag'         => 'sc_product_selected_scratch_price',
							'description' => 'Scratch/compare-at price for the selected variant.',
						],
						[
							'tag'         => 'sc_product_selected_trial',
							'description' => 'Trial text for the selected price.',
						],
						[
							'tag'         => 'sc_product_selected_billing_interval',
							'description' => 'Billing interval for the selected price.',
						],
						[
							'tag'         => 'sc_product_selected_setup_fee',
							'description' => 'Setup fee for the selected price.',
						],
					],
				],
				'price_tags' => [
					'description' => 'Price-level tags — only resolve inside a PriceChoiceTemplate within a PriceChooser. They refer to the individual price option being rendered.',
					'tags'        => [
						[
							'tag'         => 'sc_price_name',
							'description' => 'Name/label of the individual price.',
						],
						[
							'tag'         => 'sc_price_amount',
							'description' => 'Formatted amount for this price option.',
						],
						[
							'tag'         => 'sc_price_trial',
							'description' => 'Trial period text for this price.',
						],
						[
							'tag'         => 'sc_price_setup_fee',
							'description' => 'Setup fee for this price.',
						],
					],
				],
				'review_tags' => [
					'description' => 'Product review aggregate tags.',
					'tags'        => [
						[
							'tag'         => 'sc_product_review_average_ratings',
							'description' => 'Average review rating (numeric, e.g. 4.5).',
						],
						[
							'tag'         => 'sc_product_review_total_ratings',
							'description' => 'Total number of reviews.',
						],
					],
				],
			],
		];
	}

	private function get_bricks_elements(): array {
		return [
			'description' => 'SureCart Bricks elements registered for the Bricks Builder visual editor. These are used inside Bricks templates to build product pages, product cards, collection pages, and checkout flows.',
			'elements'    => [
				'layout' => [
					[
						'element'     => 'Product',
						'description' => 'Nestable container that sets the SureCart product context. Use on single product pages. Default 2-column layout.',
						'controls'    => [ 'product' => 'Select product (auto-detects on product pages)' ],
						'nestable'    => true,
						'notes'       => 'All product-related child elements must be nested inside this element or inside an sc_product query loop.',
					],
					[
						'element'     => 'ProductCard',
						'description' => 'Compact product display for grids and loops.',
						'controls'    => [ 'linkToPost' => 'Boolean — wrap card as link to product page.' ],
						'nestable'    => false,
					],
					[
						'element'     => 'ProductReviews',
						'description' => 'Nestable container for review-related elements.',
						'nestable'    => true,
					],
				],
				'product_content' => [
					[
						'element'     => 'Media',
						'description' => 'Product image gallery with lightbox support.',
						'controls'    => [
							'desktop_gallery'      => 'Gallery layout for desktop',
							'auto_height'          => 'Auto-adjust height to image ratio',
							'lightbox'             => 'Enable lightbox on click',
							'thumbnails_per_page'  => 'Number of thumbnail images to show',
						],
					],
					[
						'element'     => 'ProductData',
						'description' => 'Flexible data display using a repeater of dynamic tags.',
						'controls'    => [
							'items' => 'Repeater — each item uses a dynamic tag (e.g. {sc_product_price}, {sc_product_sku}).',
						],
					],
					[
						'element'     => 'PriceData',
						'description' => 'Displays price information for the product.',
					],
					[
						'element'     => 'ProductContent',
						'description' => 'Renders the product description / post content.',
					],
				],
				'form_interactive' => [
					[
						'element'     => 'PriceChooser',
						'description' => 'Nestable container that renders price options. Nest PriceChoiceTemplate inside.',
						'nestable'    => true,
					],
					[
						'element'     => 'PriceChoiceTemplate',
						'description' => 'Template for each price option inside PriceChooser. Use sc_price_* dynamic tags inside.',
						'nestable'    => true,
					],
					[
						'element'     => 'VariantPills',
						'description' => 'Renders variant option pills (e.g. size, color).',
					],
					[
						'element'     => 'VariantPill',
						'description' => 'Individual variant pill — typically auto-rendered inside VariantPills.',
					],
					[
						'element'     => 'Quantity',
						'description' => 'Quantity input selector.',
						'controls'    => [ 'label' => 'Text label for the quantity field.' ],
					],
					[
						'element'     => 'SelectedPriceAdHocAmount',
						'description' => 'Input for pay-what-you-want / ad-hoc pricing.',
					],
					[
						'element'     => 'BuyButton',
						'description' => 'Add-to-cart / buy-now button.',
						'controls'    => [
							'content'                    => 'Button text',
							'buy_now'                    => 'Boolean — skip cart and go straight to checkout',
							'show_sticky_purchase_button'=> 'Boolean — show sticky buy button on scroll',
							'size'                       => 'Button size (sm, md, lg)',
							'style'                      => 'Button style variant',
							'circle'                     => 'Boolean — circular button',
							'outline'                    => 'Boolean — outline style',
						],
					],
					[
						'element'     => 'ProductQuickAddButton',
						'description' => 'Quick add-to-cart button for product cards / grids.',
					],
					[
						'element'     => 'ProductLineItemNote',
						'description' => 'Optional note field attached to a line item.',
					],
				],
				'collection' => [
					[
						'element'     => 'CollectionTags',
						'description' => 'Renders all collection tags for a product.',
					],
					[
						'element'     => 'CollectionTag',
						'description' => 'Individual collection tag.',
					],
				],
				'sale' => [
					[
						'element'     => 'SaleBadge',
						'description' => 'Displays a sale/discount badge when the product is on sale.',
					],
				],
				'review' => [
					[
						'element'     => 'ProductReviewAverageRatingStars',
						'description' => 'Star display for average rating.',
					],
					[
						'element'     => 'ProductReviewAverageRatingValue',
						'description' => 'Numeric average rating value.',
					],
					[
						'element'     => 'ProductReviewTotalRating',
						'description' => 'Total number of reviews.',
					],
					[
						'element'     => 'ProductReviewBreakdown',
						'description' => 'Rating breakdown (5-star, 4-star, etc.).',
					],
					[
						'element'     => 'ProductReviewContent',
						'description' => 'Individual review content.',
					],
					[
						'element'     => 'ProductReviewList',
						'description' => 'List of product reviews.',
					],
					[
						'element'     => 'ProductReviewRating',
						'description' => 'Star rating for an individual review.',
					],
					[
						'element'     => 'ProductReviewItem',
						'description' => 'Single review item container.',
					],
				],
				'navigation' => [
					[
						'element'     => 'CartMenuIcon',
						'description' => 'Cart icon for header navigation with item count badge.',
						'controls'    => [
							'cart_icon'              => 'Icon selection for the cart',
							'cart_menu_always_shown' => 'Boolean — always show cart menu (even when empty)',
						],
					],
				],
			],
			'hierarchy_examples' => [
				'product_page' => [
					'description' => 'Typical single product page element hierarchy.',
					'structure'   => [
						'Product (nestable container)' => [
							'Column 1' => [ 'Media' ],
							'Column 2' => [
								'Heading (product title via {post_title})',
								'ProductData (price, SKU, stock via dynamic tags)',
								'PriceChooser' => [
									'PriceChoiceTemplate' => [
										'Text ({sc_price_name})',
										'Text ({sc_price_amount})',
									],
								],
								'VariantPills',
								'Quantity',
								'BuyButton',
								'ProductContent',
							],
						],
					],
				],
				'product_card' => [
					'description' => 'Product card for grids (inside sc_product query loop).',
					'structure'   => [
						'Query Loop (sc_product)' => [
							'ProductCard (linkToPost: true)' => [
								'Media',
								'Heading ({post_title})',
								'ProductData ({sc_product_price})',
								'SaleBadge',
								'ProductQuickAddButton',
							],
						],
					],
				],
				'reviews_section' => [
					'description' => 'Product reviews section.',
					'structure'   => [
						'ProductReviews (nestable)' => [
							'ProductReviewAverageRatingStars',
							'ProductReviewAverageRatingValue',
							'ProductReviewTotalRating',
							'ProductReviewBreakdown',
							'ProductReviewList' => [
								'ProductReviewItem' => [
									'ProductReviewRating',
									'ProductReviewContent',
								],
							],
						],
					],
				],
			],
		];
	}

	private function get_template_guide(): array {
		return [
			'description' => 'Guide for building SureCart templates in Bricks Builder.',
			'template_types' => [
				[
					'type'         => 'sc_product',
					'label'        => 'Single Product',
					'description'  => 'Template applied to individual SureCart product pages.',
					'registration' => 'Bricks > Templates > Add New > Template Type: Single > Conditions: Post Type = sc_product.',
				],
				[
					'type'         => 'sc_collection',
					'label'        => 'Collection Archive',
					'description'  => 'Template for SureCart collection archive pages.',
					'registration' => 'Bricks > Templates > Add New > Template Type: Archive > Conditions: Taxonomy Archive = sc_collection.',
				],
			],
			'build_patterns' => [
				'product_page' => [
					'title'       => 'Single Product Page',
					'description' => 'Build a single product page using the Product nestable element as the outer container. All SureCart child elements (Media, ProductData, BuyButton, etc.) must be placed inside the Product element to receive the product context.',
					'steps'       => [
						'1. Create a Bricks template with type Single, condition sc_product.',
						'2. Add the SureCart "Product" element (nestable container).',
						'3. Inside Product, create a 2-column layout.',
						'4. Left column: add "Media" element for gallery.',
						'5. Right column: add Heading with {post_title}, ProductData, PriceChooser > PriceChoiceTemplate, VariantPills, Quantity, BuyButton.',
						'6. Below columns: add ProductContent for the description.',
						'7. Optionally add ProductReviews section.',
					],
				],
				'product_grid' => [
					'title'       => 'Product Grid (Query Loop)',
					'description' => 'Display products in a grid using Bricks query loop on the sc_product post type.',
					'steps'       => [
						'1. Add a Container or Block element.',
						'2. Enable Query Loop on the container.',
						'3. Set Query Loop post type to sc_product.',
						'4. Optionally filter by sc_collection taxonomy.',
						'5. Inside the loop, add SureCart elements: ProductCard (with linkToPost), or build custom layout with Media, Heading, ProductData, ProductQuickAddButton.',
						'6. Use dynamic tags like {sc_product_price}, {sc_product_price_range} for pricing.',
					],
				],
				'cart_icon_header' => [
					'title'       => 'Cart Icon in Header',
					'description' => 'Add a SureCart cart icon to your Bricks header template.',
					'steps'       => [
						'1. Edit your Bricks header template.',
						'2. Add the SureCart "CartMenuIcon" element to the header navigation area.',
						'3. Configure cart_icon and cart_menu_always_shown as needed.',
						'4. The cart icon automatically shows an item count badge.',
					],
				],
				'checkout_form' => [
					'title'       => 'Checkout Form Embedding',
					'description' => 'Embed a SureCart checkout form in any Bricks page.',
					'steps'       => [
						'1. Create a checkout form in SureCart > Forms.',
						'2. In Bricks, add a Shortcode or Code element.',
						'3. Enter [sc_form id="X"] where X is the form post ID.',
						'4. Use surecart_list_forms tool to find available form IDs.',
					],
				],
			],
			'shortcodes' => [
				[
					'shortcode'   => '[sc_product_list]',
					'description' => 'Displays a product list/grid. SureCart handles the layout and styling.',
				],
				[
					'shortcode'   => '[sc_form id="X"]',
					'description' => 'Embeds a specific SureCart checkout form by post ID.',
				],
				[
					'shortcode'   => '[sc_cart_menu_icon]',
					'description' => 'Renders the cart icon with item count — alternative to using the Bricks CartMenuIcon element.',
				],
				[
					'shortcode'   => '[sc_customer_dashboard]',
					'description' => 'Renders the customer self-service dashboard (orders, subscriptions, billing).',
				],
			],
		];
	}
}
