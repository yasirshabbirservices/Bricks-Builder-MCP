<?php
namespace BricksMCP\Tools;

use BricksMCP\Bricks_Data;
use BricksMCP\Preview_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Pages extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_list_pages',
				'description' => 'List WordPress pages. Returns id, title, slug, status, URL, and whether each page has Bricks Builder content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (default 20, max 100)', 'default' => 20 ],
						'page'     => [ 'type' => 'integer', 'description' => 'Page number (default 1)', 'default' => 1 ],
						'status'   => [ 'type' => 'string', 'description' => 'Post status: publish, draft, private, any (default: any)', 'default' => 'any' ],
						'search'   => [ 'type' => 'string', 'description' => 'Search keyword' ],
						'parent'   => [ 'type' => 'integer', 'description' => 'Filter by parent page ID (0 = top-level only)' ],
					],
				],
			],
			[
				'name'        => 'bricks_get_page',
				'description' => 'Get a page with full details. Set include_elements=true to retrieve the Bricks element tree (the page layout structure).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'          => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
						'include_elements' => [ 'type' => 'boolean', 'description' => 'Include Bricks element arrays for content, header, and footer areas (default false)', 'default' => false ],
					],
					'required' => [ 'post_id' ],
				],
			],
			[
				'name'        => 'bricks_create_page',
				'description' => 'Create a new WordPress page, optionally with Bricks Builder content. Pass an elements array to set the layout immediately.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'title'         => [ 'type' => 'string', 'description' => 'Page title' ],
						'slug'          => [ 'type' => 'string', 'description' => 'URL slug (auto-generated from title if omitted)' ],
						'status'        => [ 'type' => 'string', 'description' => 'draft | publish | private (default: draft)', 'default' => 'draft' ],
						'parent'        => [ 'type' => 'integer', 'description' => 'Parent page ID (0 for top-level)', 'default' => 0 ],
						'elements'      => [ 'type' => 'array', 'description' => 'Bricks element objects for the main content area', 'items' => [ 'type' => 'object' ] ],
						'page_settings' => [ 'type' => 'object', 'description' => 'Bricks page settings (headerDisabled, footerDisabled, customCss, etc.)' ],
					],
					'required' => [ 'title' ],
				],
			],
			[
				'name'        => 'bricks_update_page',
				'description' => "Update an existing page — title, status, or Bricks elements. Use area to target content (default), header, or footer.\n\nBy default, the elements array REPLACES all existing elements in the target area. Set append=true to ADD elements after the existing content instead of replacing it. When appending, any element IDs that collide with existing ones are automatically regenerated to prevent conflicts.\n\nUse insert_after to place appended elements after a specific existing element ID (only works with append=true).",
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'       => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
						'title'         => [ 'type' => 'string', 'description' => 'New page title' ],
						'status'        => [ 'type' => 'string', 'description' => 'New status: draft | publish | private' ],
						'elements'      => [ 'type' => 'array', 'description' => 'Bricks element array. Replaces existing elements unless append=true.', 'items' => [ 'type' => 'object' ] ],
						'area'          => [ 'type' => 'string', 'description' => 'Which area to update: content | header | footer (default: content)', 'default' => 'content' ],
						'append'        => [ 'type' => 'boolean', 'description' => 'When true, ADD elements after existing content instead of replacing. Colliding IDs are auto-regenerated. (default: false)', 'default' => false ],
						'insert_after'  => [ 'type' => 'string', 'description' => 'Element ID to insert after (append mode only). New root-level elements are placed after this element\'s root section. Omit to append at the end.' ],
						'page_settings' => [ 'type' => 'object', 'description' => 'Bricks page-level settings to merge' ],
					],
					'required' => [ 'post_id' ],
				],
			],
			[
				'name'        => 'bricks_duplicate_page',
				'description' => 'Duplicate an existing page — copies the title, Bricks element layout, and page settings into a new draft page.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Source page ID to duplicate' ],
						'title'   => [ 'type' => 'string', 'description' => 'Title for the new page (defaults to "Copy of {original title}")' ],
						'status'  => [ 'type' => 'string', 'description' => 'Status for new page: draft | publish (default: draft)', 'default' => 'draft' ],
					],
					'required' => [ 'post_id' ],
				],
			],
			[
				'name'        => 'bricks_delete_page',
				'description' => 'Delete or trash a page. By default moves to trash; set force=true to permanently delete.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'WordPress post ID' ],
						'force'   => [ 'type' => 'boolean', 'description' => 'Permanently delete instead of trashing (default false)', 'default' => false ],
					],
					'required' => [ 'post_id' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_list_pages':
				return $this->list_pages( $args );
			case 'bricks_get_page':
				return $this->get_page( $args );
			case 'bricks_create_page':
				return $this->create_page( $args );
			case 'bricks_update_page':
				return $this->update_page( $args );
			case 'bricks_duplicate_page':
				return $this->duplicate_page( $args );
			case 'bricks_delete_page':
				return $this->delete_page( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function list_pages( array $args ): array {
		$per_page = min( $this->int_arg( $args, 'per_page', 20 ), 100 );
		$page     = max( $this->int_arg( $args, 'page', 1 ), 1 );
		$status   = $this->str_arg( $args, 'status', 'any' );
		$search   = $this->str_arg( $args, 'search' );
		$parent   = isset( $args['parent'] ) ? $this->int_arg( $args, 'parent' ) : null;

		$query_args = [
			'post_type'      => 'page',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		];

		if ( $search ) {
			$query_args['s'] = sanitize_text_field( $search );
		}

		if ( $parent !== null ) {
			$query_args['post_parent'] = $parent;
		}

		$query = new \WP_Query( $query_args );
		$pages = [];

		foreach ( $query->posts as $post ) {
			$pages[] = [
				'id'              => $post->ID,
				'title'           => $post->post_title,
				'slug'            => $post->post_name,
				'status'          => $post->post_status,
				'url'             => get_permalink( $post->ID ) ?: '',
				'parent'          => $post->post_parent,
				'has_bricks_data' => Bricks_Data::has_bricks_data( $post->ID ),
				'modified'        => $post->post_modified,
			];
		}

		return [
			'pages'      => $pages,
			'total'      => $query->found_posts,
			'total_pages'=> $query->max_num_pages,
			'page'       => $page,
			'per_page'   => $per_page,
		];
	}

	private function get_page( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) {
			return $this->err( 'post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'page' ) {
			return $this->err( "Page {$post_id} not found." );
		}

		return Bricks_Data::format_post( $post, $this->bool_arg( $args, 'include_elements', false ) );
	}

	private function create_page( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'edit_pages' );
		if ( $err ) return $err;

		$title  = $this->str_arg( $args, 'title' );
		if ( ! $title ) {
			return $this->err( 'title is required.' );
		}

		$in_preview = Preview_Manager::is_active();

		$post_data = [
			'post_type'   => 'page',
			'post_title'  => sanitize_text_field( $in_preview ? '[Preview] ' . $title : $title ),
			'post_status' => $in_preview ? 'draft' : $this->str_arg( $args, 'status', 'draft' ),
			'post_parent' => $this->int_arg( $args, 'parent', 0 ),
		];

		$slug = $this->str_arg( $args, 'slug' );
		if ( $slug && ! $in_preview ) {
			$post_data['post_name'] = sanitize_title( $slug );
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$elements = $this->arr_arg( $args, 'elements' );
		if ( $elements ) {
			$result = Bricks_Data::set_elements( $post_id, $elements, 'content' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$page_settings = isset( $args['page_settings'] ) && is_array( $args['page_settings'] )
			? $args['page_settings'] : [];
		if ( $page_settings ) {
			Bricks_Data::set_page_settings( $post_id, $page_settings );
		}

		if ( $in_preview ) {
			// live_id = 0 means this is a brand-new page with no live equivalent yet
			Preview_Manager::record_draft( 0, $post_id, $title );
			update_post_meta( $post_id, '_bmcp_preview_session', Preview_Manager::get_session()['session_id'] ?? '' );
		}

		return [
			'id'         => $post_id,
			'title'      => $title,
			'url'        => get_permalink( $post_id ) ?: '',
			'edit_url'   => $this->edit_url( $post_id ),
			'status'     => $post_data['post_status'],
			'message'    => $in_preview
				? 'Preview draft created (live site not modified). Call bricks_commit_preview to publish.'
				: 'Page created successfully.',
			'in_preview' => $in_preview,
		];
	}

	private function update_page( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) {
			return $this->err( 'post_id is required.' );
		}

		$err = $this->require_cap( 'edit_pages' );
		if ( $err ) return $err;

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'page' ) {
			return $this->err( "Page {$post_id} not found." );
		}

		$in_preview  = Preview_Manager::is_active();
		$target_id   = $post_id;
		$is_new_draft = false;

		if ( $in_preview ) {
			// Redirect the write to the preview draft instead of the live page
			$draft_id = Preview_Manager::get_draft_for( $post_id );
			if ( ! $draft_id ) {
				$draft_id = Preview_Manager::clone_to_draft( $post_id );
				if ( ! $draft_id ) {
					return $this->err( "Failed to create preview draft for page {$post_id}." );
				}
				$is_new_draft = true;
			}
			$target_id = $draft_id;
		}

		$update = [ 'ID' => $target_id ];
		if ( isset( $args['title'] ) ) {
			$update['post_title'] = $in_preview
				? '[Preview] ' . sanitize_text_field( $args['title'] )
				: sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['status'] ) && ! $in_preview ) {
			$update['post_status'] = sanitize_key( $args['status'] );
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		$elements = $this->arr_arg( $args, 'elements' );
		if ( $elements ) {
			$area   = $this->str_arg( $args, 'area', 'content' );
			$append = $this->bool_arg( $args, 'append', false );

			if ( $append ) {
				$insert_after = $this->str_arg( $args, 'insert_after' );
				$result = Bricks_Data::append_elements( $target_id, $elements, $area, $insert_after );
			} else {
				$result = Bricks_Data::set_elements( $target_id, $elements, $area );
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $args['page_settings'] ) && is_array( $args['page_settings'] ) ) {
			$existing = Bricks_Data::get_page_settings( $target_id );
			Bricks_Data::set_page_settings( $target_id, array_merge( $existing, $args['page_settings'] ) );
		}

		$append_used = $this->bool_arg( $args, 'append', false );

		return [
			'success'     => true,
			'post_id'     => $post_id,
			'draft_id'    => $in_preview ? $target_id : null,
			'is_new_draft' => $is_new_draft,
			'in_preview'  => $in_preview,
			'appended'    => $append_used && ! empty( $elements ),
			'message'     => $in_preview
				? "Preview draft updated (live page {$post_id} not modified). Call bricks_commit_preview to publish."
				: ( $append_used && ! empty( $elements )
					? 'Page updated successfully. Elements appended to existing content.'
					: 'Page updated successfully.' ),
		];
	}

	private function duplicate_page( array $args ): array|\WP_Error {
		$source_id = $this->int_arg( $args, 'post_id' );
		if ( ! $source_id ) return $this->err( 'post_id is required.' );

		$err = $this->require_cap( 'edit_pages' );
		if ( $err ) return $err;

		$source = get_post( $source_id );
		if ( ! $source || $source->post_type !== 'page' ) {
			return $this->err( "Page {$source_id} not found." );
		}

		$new_title = $this->str_arg( $args, 'title' ) ?: 'Copy of ' . $source->post_title;

		$new_id = wp_insert_post( [
			'post_type'   => 'page',
			'post_title'  => sanitize_text_field( $new_title ),
			'post_status' => $this->str_arg( $args, 'status', 'draft' ),
			'post_parent' => $source->post_parent,
		], true );

		if ( is_wp_error( $new_id ) ) return $new_id;

		// Copy Bricks element areas
		foreach ( [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER ] as $meta_key ) {
			$elements = get_post_meta( $source_id, $meta_key, true );
			if ( $elements ) {
				update_post_meta( $new_id, $meta_key, $elements );
			}
		}

		// Copy Bricks page settings
		$page_settings = Bricks_Data::get_page_settings( $source_id );
		if ( $page_settings ) {
			Bricks_Data::set_page_settings( $new_id, $page_settings );
		}

		return [
			'id'      => $new_id,
			'title'   => $new_title,
			'url'     => get_permalink( $new_id ) ?: '',
			'edit_url'=> $this->edit_url( $new_id ),
			'status'  => $this->str_arg( $args, 'status', 'draft' ),
			'message' => "Page duplicated from #{$source_id}.",
		];
	}

	private function delete_page( array $args ): array|\WP_Error {
		$post_id = $this->int_arg( $args, 'post_id' );
		if ( ! $post_id ) {
			return $this->err( 'post_id is required.' );
		}

		$err = $this->require_cap( 'delete_pages' );
		if ( $err ) return $err;

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'page' ) {
			return $this->err( "Page {$post_id} not found." );
		}

		$force  = $this->bool_arg( $args, 'force', false );
		$result = wp_delete_post( $post_id, $force );

		if ( ! $result ) {
			return $this->err( "Failed to delete page {$post_id}." );
		}

		return [
			'success' => true,
			'post_id' => $post_id,
			'message' => $force ? 'Page permanently deleted.' : 'Page moved to trash.',
		];
	}
}
