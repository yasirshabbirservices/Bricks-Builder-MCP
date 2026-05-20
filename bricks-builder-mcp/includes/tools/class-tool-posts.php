<?php
namespace BricksMCP\Tools;

use BricksMCP\Bricks_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Posts extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_list_post_types',
				'description' => 'List all registered public WordPress post types (pages, posts, custom post types).',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_list_posts',
				'description' => 'List posts of any post type. Works for blog posts, custom post types, WooCommerce products, etc.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type' => [ 'type' => 'string', 'description' => 'Post type slug (e.g. "post", "portfolio", "product"). Default: post', 'default' => 'post' ],
						'per_page'  => [ 'type' => 'integer', 'description' => 'Results per page (default 20)', 'default' => 20 ],
						'page'      => [ 'type' => 'integer', 'description' => 'Page number', 'default' => 1 ],
						'status'    => [ 'type' => 'string', 'description' => 'Post status: publish | draft | any (default: any)', 'default' => 'any' ],
						'search'    => [ 'type' => 'string', 'description' => 'Search keyword' ],
						'category'  => [ 'type' => 'integer', 'description' => 'Category term ID' ],
						'tag'       => [ 'type' => 'integer', 'description' => 'Tag term ID' ],
						'orderby'   => [ 'type' => 'string', 'description' => 'Sort by: date | title | menu_order | rand (default: date)', 'default' => 'date' ],
						'order'     => [ 'type' => 'string', 'description' => 'Sort direction: DESC | ASC (default: DESC)', 'default' => 'DESC' ],
					],
				],
			],
			[
				'name'        => 'bricks_get_post',
				'description' => 'Get details of any post, page, or CPT entry. Set include_elements=true to retrieve the Bricks layout.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'          => [ 'type' => 'integer', 'description' => 'Post ID' ],
						'include_elements' => [ 'type' => 'boolean', 'description' => 'Include Bricks elements', 'default' => false ],
					],
					'required' => [ 'post_id' ],
				],
			],
			[
				'name'        => 'bricks_create_post',
				'description' => 'Create a new post of any type with optional Bricks Builder layout, categories, tags, and custom meta.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'   => [ 'type' => 'string', 'description' => 'Post type slug (default: post)', 'default' => 'post' ],
						'title'       => [ 'type' => 'string', 'description' => 'Post title' ],
						'content'     => [ 'type' => 'string', 'description' => 'WordPress editor content (HTML). Leave empty if using Bricks elements.' ],
						'excerpt'     => [ 'type' => 'string', 'description' => 'Post excerpt' ],
						'status'      => [ 'type' => 'string', 'description' => 'draft | publish | private', 'default' => 'draft' ],
						'slug'        => [ 'type' => 'string', 'description' => 'URL slug' ],
						'categories'  => [ 'type' => 'array', 'description' => 'Array of category IDs', 'items' => [ 'type' => 'integer' ] ],
						'tags'        => [ 'type' => 'array', 'description' => 'Array of tag IDs', 'items' => [ 'type' => 'integer' ] ],
						'elements'    => [ 'type' => 'array', 'description' => 'Bricks element objects for layout', 'items' => [ 'type' => 'object' ] ],
						'custom_meta' => [ 'type' => 'object', 'description' => 'Custom post meta key-value pairs' ],
						'featured_image' => [ 'type' => 'integer', 'description' => 'Featured image attachment ID' ],
					],
					'required' => [ 'title' ],
				],
			],
			[
				'name'        => 'bricks_update_post',
				'description' => 'Update any post\'s title, content, status, Bricks elements, or custom meta.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'        => [ 'type' => 'integer', 'description' => 'Post ID' ],
						'title'          => [ 'type' => 'string', 'description' => 'New title' ],
						'content'        => [ 'type' => 'string', 'description' => 'New WordPress editor content' ],
						'excerpt'        => [ 'type' => 'string', 'description' => 'New excerpt' ],
						'status'         => [ 'type' => 'string', 'description' => 'New status' ],
						'elements'       => [ 'type' => 'array', 'description' => 'New Bricks element array', 'items' => [ 'type' => 'object' ] ],
						'custom_meta'    => [ 'type' => 'object', 'description' => 'Custom meta to update/add' ],
						'featured_image' => [ 'type' => 'integer', 'description' => 'Featured image attachment ID' ],
					],
					'required' => [ 'post_id' ],
				],
			],
			[
				'name'        => 'bricks_delete_post',
				'description' => 'Delete or trash a post/CPT entry.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Post ID' ],
						'force'   => [ 'type' => 'boolean', 'description' => 'Permanently delete (default false)', 'default' => false ],
					],
					'required' => [ 'post_id' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_list_post_types':
				return $this->list_post_types();
			case 'bricks_list_posts':
				return $this->list_posts( $args );
			case 'bricks_get_post':
				return $this->get_post( $args );
			case 'bricks_create_post':
				return $this->create_post( $args );
			case 'bricks_update_post':
				return $this->update_post( $args );
			case 'bricks_delete_post':
				return $this->delete_post( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function list_post_types(): array {
		$types  = get_post_types( [ 'public' => true ], 'objects' );
		$result = [];

		foreach ( $types as $type ) {
			$result[] = [
				'name'        => $type->name,
				'label'       => $type->label,
				'singular'    => $type->labels->singular_name ?? $type->label,
				'has_archive' => (bool) $type->has_archive,
				'hierarchical'=> $type->hierarchical,
			];
		}

		return [ 'post_types' => $result ];
	}

	private function list_posts( array $args ): array {
		$post_type = $this->str_arg( $args, 'post_type', 'post' );
		$per_page  = min( $this->int_arg( $args, 'per_page', 20 ), 100 );
		$page      = max( $this->int_arg( $args, 'page', 1 ), 1 );
		$status    = $this->str_arg( $args, 'status', 'any' );
		$search    = $this->str_arg( $args, 'search' );
		$orderby   = $this->str_arg( $args, 'orderby', 'date' );
		$order     = strtoupper( $this->str_arg( $args, 'order', 'DESC' ) );

		$query_args = [
			'post_type'      => sanitize_key( $post_type ),
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => sanitize_key( $orderby ),
			'order'          => in_array( $order, [ 'ASC', 'DESC' ] ) ? $order : 'DESC',
		];

		if ( $search ) {
			$query_args['s'] = sanitize_text_field( $search );
		}

		if ( isset( $args['category'] ) ) {
			$query_args['cat'] = $this->int_arg( $args, 'category' );
		}

		if ( isset( $args['tag'] ) ) {
			$query_args['tag_id'] = $this->int_arg( $args, 'tag' );
		}

		$query = new \WP_Query( $query_args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			$posts[] = [
				'id'              => $post->ID,
				'title'           => $post->post_title,
				'slug'            => $post->post_name,
				'status'          => $post->post_status,
				'type'            => $post->post_type,
				'url'             => get_permalink( $post->ID ) ?: '',
				'date'            => $post->post_date,
				'modified'        => $post->post_modified,
				'has_bricks_data' => Bricks_Data::has_bricks_data( $post->ID ),
			];
		}

		return [
			'posts'       => $posts,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	private function get_post( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) {
			return $this->err( 'post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->err( "Post {$post_id} not found." );
		}

		$data = Bricks_Data::format_post( $post, $this->bool_arg( $args, 'include_elements', false ) );

		// Add extra post data
		$data['content']        = $post->post_content;
		$data['excerpt']        = $post->post_excerpt;
		$data['date']           = $post->post_date;
		$data['featured_image'] = get_the_post_thumbnail_url( $post_id, 'large' ) ?: null;
		$data['categories']     = wp_get_post_categories( $post_id, [ 'fields' => 'all' ] );
		$data['tags']           = wp_get_post_tags( $post_id, [ 'fields' => 'all' ] );

		return $data;
	}

	private function create_post( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$title = $this->str_arg( $args, 'title' );
		if ( ! $title ) return $this->err( '"title" is required.' );

		$post_data = [
			'post_type'    => sanitize_key( $this->str_arg( $args, 'post_type', 'post' ) ),
			'post_title'   => sanitize_text_field( $title ),
			'post_status'  => $this->str_arg( $args, 'status', 'draft' ),
			'post_content' => wp_kses_post( $this->str_arg( $args, 'content' ) ),
			'post_excerpt' => sanitize_text_field( $this->str_arg( $args, 'excerpt' ) ),
		];

		$slug = $this->str_arg( $args, 'slug' );
		if ( $slug ) {
			$post_data['post_name'] = sanitize_title( $slug );
		}

		$categories = $this->arr_arg( $args, 'categories' );
		if ( $categories ) {
			$post_data['post_category'] = array_map( 'intval', $categories );
		}

		$tags = $this->arr_arg( $args, 'tags' );
		if ( $tags ) {
			$post_data['tags_input'] = implode( ',', array_map( 'intval', $tags ) );
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) return $post_id;

		// Featured image
		$featured_image = $this->int_arg( $args, 'featured_image' );
		if ( $featured_image ) {
			set_post_thumbnail( $post_id, $featured_image );
		}

		// Bricks elements
		$elements = $this->arr_arg( $args, 'elements' );
		if ( $elements ) {
			$result = Bricks_Data::set_elements( $post_id, $elements, 'content' );
			if ( is_wp_error( $result ) ) return $result;
		}

		// Custom meta
		if ( isset( $args['custom_meta'] ) && is_array( $args['custom_meta'] ) ) {
			foreach ( $args['custom_meta'] as $key => $value ) {
				$safe_key = sanitize_key( $key );
				if ( $safe_key ) {
					update_post_meta( $post_id, $safe_key, $value );
				}
			}
		}

		return [
			'id'      => $post_id,
			'title'   => $title,
			'url'     => get_permalink( $post_id ) ?: '',
			'edit_url'=> $this->edit_url( $post_id ),
			'status'  => $post_data['post_status'],
			'message' => 'Post created successfully.',
		];
	}

	private function update_post( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) return $this->err( '"post_id" is required.' );

		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$post = get_post( $post_id );
		if ( ! $post ) return $this->err( "Post {$post_id} not found." );

		$update = [ 'ID' => $post_id ];

		if ( isset( $args['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$update['post_content'] = wp_kses_post( $args['content'] );
		}
		if ( isset( $args['excerpt'] ) ) {
			$update['post_excerpt'] = sanitize_text_field( $args['excerpt'] );
		}
		if ( isset( $args['status'] ) ) {
			$update['post_status'] = sanitize_key( $args['status'] );
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		$featured_image = $this->int_arg( $args, 'featured_image' );
		if ( $featured_image ) {
			set_post_thumbnail( $post_id, $featured_image );
		}

		$elements = $this->arr_arg( $args, 'elements' );
		if ( $elements ) {
			$result = Bricks_Data::set_elements( $post_id, $elements, 'content' );
			if ( is_wp_error( $result ) ) return $result;
		}

		if ( isset( $args['custom_meta'] ) && is_array( $args['custom_meta'] ) ) {
			foreach ( $args['custom_meta'] as $key => $value ) {
				$safe_key = sanitize_key( $key );
				if ( $safe_key ) {
					update_post_meta( $post_id, $safe_key, $value );
				}
			}
		}

		return [ 'success' => true, 'post_id' => $post_id, 'message' => 'Post updated successfully.' ];
	}

	private function delete_post( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) return $this->err( '"post_id" is required.' );

		$err = $this->require_cap( 'delete_posts' );
		if ( $err ) return $err;

		$post = get_post( $post_id );
		if ( ! $post ) return $this->err( "Post {$post_id} not found." );

		$force  = $this->bool_arg( $args, 'force', false );
		$result = wp_delete_post( $post_id, $force );

		if ( ! $result ) {
			return $this->err( "Failed to delete post {$post_id}." );
		}

		return [
			'success' => true,
			'post_id' => $post_id,
			'message' => $force ? 'Post permanently deleted.' : 'Post moved to trash.',
		];
	}
}
