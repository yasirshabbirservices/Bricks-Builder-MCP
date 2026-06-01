<?php
namespace BricksMCP\Tools;

use BricksMCP\Bricks_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Templates extends Tool_Base {

	private const TEMPLATE_TYPES = [
		'header', 'footer', 'content', 'archive', 'search',
		'error', 'popup', 'section', 'password_protection',
		'wc_cart', 'wc_checkout', 'wc_thankyou', 'wc_account',
		'wc_my_account', 'wc_product',
	];

	public function define(): array {
		return [
			[
				'name'        => 'bricks_list_templates',
				'description' => 'List all Bricks Builder templates. Optionally filter by template type (header, footer, content, archive, popup, section, etc.).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'type'     => [ 'type' => 'string', 'description' => 'Filter by template type: header | footer | content | archive | search | error | popup | section' ],
						'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (default 50)', 'default' => 50 ],
						'page'     => [ 'type' => 'integer', 'description' => 'Page number', 'default' => 1 ],
					],
				],
			],
			[
				'name'        => 'bricks_get_template',
				'description' => 'Get a Bricks template with its element structure and template settings (conditions, preview settings).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'template_id' => [ 'type' => 'integer', 'description' => 'Template post ID' ],
					],
					'required' => [ 'template_id' ],
				],
			],
			[
				'name'        => 'bricks_create_template',
				'description' => 'Create a new Bricks template of a specified type. Templates control site-wide layouts — headers, footers, archive pages, popups, etc.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'title'      => [ 'type' => 'string', 'description' => 'Template title' ],
						'type'       => [ 'type' => 'string', 'description' => 'Template type: header | footer | content | archive | search | error | popup | section' ],
						'elements'   => [ 'type' => 'array', 'description' => 'Bricks element objects', 'items' => [ 'type' => 'object' ] ],
						'conditions' => [
							'type'        => 'array',
							'description' => 'Display conditions array. Example: [{"main":"any"}] for entire site, [{"main":"frontpage"}] for homepage, [{"main":"ids","ids":[42]}] for specific page.',
							'items'       => [ 'type' => 'object' ],
						],
					],
					'required' => [ 'title', 'type' ],
				],
			],
			[
				'name'        => 'bricks_update_template',
				'description' => "Update an existing Bricks template — its title, type, and/or element structure.\n\nBy default, the elements array REPLACES all existing elements. Set append=true to ADD elements after the existing content instead of replacing it. Colliding IDs are auto-regenerated.",
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'template_id' => [ 'type' => 'integer', 'description' => 'Template post ID' ],
						'title'       => [ 'type' => 'string', 'description' => 'New title' ],
						'type'        => [ 'type' => 'string', 'description' => 'New template type' ],
						'elements'    => [ 'type' => 'array', 'description' => 'Bricks element array. Replaces existing elements unless append=true.', 'items' => [ 'type' => 'object' ] ],
						'append'      => [ 'type' => 'boolean', 'description' => 'When true, ADD elements after existing content instead of replacing. Colliding IDs are auto-regenerated. (default: false)', 'default' => false ],
						'insert_after'=> [ 'type' => 'string', 'description' => 'Element ID to insert after (append mode only). Omit to append at the end.' ],
					],
					'required' => [ 'template_id' ],
				],
			],
			[
				'name'        => 'bricks_delete_template',
				'description' => 'Delete a Bricks template (moves to trash by default).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'template_id' => [ 'type' => 'integer', 'description' => 'Template post ID' ],
						'force'       => [ 'type' => 'boolean', 'description' => 'Permanently delete (default false)', 'default' => false ],
					],
					'required' => [ 'template_id' ],
				],
			],
			[
				'name'        => 'bricks_set_template_conditions',
				'description' => 'Set display conditions for a Bricks template. Conditions determine where the template is shown. Examples: [{"main":"any"}] = entire site, [{"main":"frontpage"}] = home page, [{"main":"postType","postType":["post"]}] = all blog posts.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'template_id' => [ 'type' => 'integer', 'description' => 'Template post ID' ],
						'conditions'  => [
							'type'  => 'array',
							'description' => 'Conditions array. Each condition: {main: string, ids?: int[], postType?: string[], archiveType?: string[], exclude?: bool}',
							'items' => [ 'type' => 'object' ],
						],
					],
					'required' => [ 'template_id', 'conditions' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_list_templates':
				return $this->list_templates( $args );
			case 'bricks_get_template':
				return $this->get_template( $args );
			case 'bricks_create_template':
				return $this->create_template( $args );
			case 'bricks_update_template':
				return $this->update_template( $args );
			case 'bricks_delete_template':
				return $this->delete_template( $args );
			case 'bricks_set_template_conditions':
				return $this->set_conditions( $args );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function template_slug(): string {
		return defined( 'BRICKS_DB_TEMPLATE_SLUG' ) ? BRICKS_DB_TEMPLATE_SLUG : BMCP_DB_TEMPLATE_SLUG;
	}

	private function list_templates( array $args ): array {
		$query_args = [
			'post_type'      => $this->template_slug(),
			'post_status'    => 'publish',
			'posts_per_page' => min( $this->int_arg( $args, 'per_page', 50 ), 200 ),
			'paged'          => max( $this->int_arg( $args, 'page', 1 ), 1 ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$type = $this->str_arg( $args, 'type' );
		if ( $type ) {
			$query_args['meta_query'] = [
				[
					'key'   => defined( 'BRICKS_DB_TEMPLATE_TYPE' ) ? BRICKS_DB_TEMPLATE_TYPE : BMCP_DB_TEMPLATE_TYPE,
					'value' => sanitize_text_field( $type ),
				],
			];
		}

		$query     = new \WP_Query( $query_args );
		$templates = [];

		foreach ( $query->posts as $post ) {
			$tmpl_type  = Bricks_Data::get_template_type( $post->ID );
			$conditions = Bricks_Data::get_template_settings( $post->ID );

			$templates[] = [
				'id'              => $post->ID,
				'title'           => $post->post_title,
				'type'            => $tmpl_type,
				'status'          => $post->post_status,
				'has_elements'    => Bricks_Data::has_bricks_data( $post->ID ),
				'conditions'      => $conditions['templateConditions'] ?? [],
				'edit_url'        => $this->edit_url( $post->ID ),
			];
		}

		return [
			'templates'   => $templates,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
		];
	}

	private function get_template( array $args ): array|\WP_Error {
		$id = $this->int_arg( $args, 'template_id' );
		if ( ! $id ) {
			return $this->err( 'template_id is required.' );
		}

		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $this->template_slug() ) {
			return $this->err( "Template {$id} not found." );
		}

		$type     = Bricks_Data::get_template_type( $id );
		$area     = Bricks_Data::template_area( $type );
		$settings = Bricks_Data::get_template_settings( $id );

		return [
			'id'               => $id,
			'title'            => $post->post_title,
			'type'             => $type,
			'status'           => $post->post_status,
			'elements'         => Bricks_Data::get_elements( $id, $area ),
			'template_settings'=> $settings,
			'conditions'       => $settings['templateConditions'] ?? [],
			'edit_url'         => $this->edit_url( $id ),
		];
	}

	private function create_template( array $args ): array|\WP_Error {
		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$title = $this->str_arg( $args, 'title' );
		$type  = $this->str_arg( $args, 'type' );

		if ( ! $title ) return $this->err( 'title is required.' );
		if ( ! $type )  return $this->err( 'type is required.' );

		$post_id = wp_insert_post( [
			'post_type'   => $this->template_slug(),
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		Bricks_Data::set_template_type( $post_id, sanitize_text_field( $type ) );

		$elements = $this->arr_arg( $args, 'elements' );
		if ( $elements ) {
			$area   = Bricks_Data::template_area( $type );
			$result = Bricks_Data::set_elements( $post_id, $elements, $area );
			if ( is_wp_error( $result ) ) return $result;
		}

		$conditions = $this->arr_arg( $args, 'conditions' );
		if ( $conditions ) {
			Bricks_Data::set_template_conditions( $post_id, $conditions );
		}

		return [
			'id'       => $post_id,
			'title'    => $title,
			'type'     => $type,
			'edit_url' => $this->edit_url( $post_id ),
			'message'  => 'Template created successfully.',
		];
	}

	private function update_template( array $args ): array|\WP_Error {
		$id = $this->int_arg( $args, 'template_id' );
		if ( ! $id ) return $this->err( 'template_id is required.' );

		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $this->template_slug() ) {
			return $this->err( "Template {$id} not found." );
		}

		if ( isset( $args['title'] ) ) {
			wp_update_post( [ 'ID' => $id, 'post_title' => sanitize_text_field( $args['title'] ) ] );
		}

		$current_type = Bricks_Data::get_template_type( $id );
		$new_type     = isset( $args['type'] ) ? sanitize_text_field( $args['type'] ) : $current_type;

		if ( isset( $args['type'] ) ) {
			Bricks_Data::set_template_type( $id, $new_type );
		}

		$elements = $this->arr_arg( $args, 'elements' );
		if ( $elements ) {
			$area   = Bricks_Data::template_area( $new_type );
			$append = $this->bool_arg( $args, 'append', false );

			if ( $append ) {
				$insert_after = $this->str_arg( $args, 'insert_after' );
				$result = Bricks_Data::append_elements( $id, $elements, $area, $insert_after );
			} else {
				$result = Bricks_Data::set_elements( $id, $elements, $area );
			}

			if ( is_wp_error( $result ) ) return $result;
		}

		$append_used = $this->bool_arg( $args, 'append', false );

		return [
			'success'     => true,
			'template_id' => $id,
			'appended'    => $append_used && ! empty( $elements ),
			'message'     => $append_used && ! empty( $elements )
				? 'Template updated successfully. Elements appended to existing content.'
				: 'Template updated successfully.',
		];
	}

	private function delete_template( array $args ): array|\WP_Error {
		$id = $this->int_arg( $args, 'template_id' );
		if ( ! $id ) return $this->err( 'template_id is required.' );

		$err = $this->require_cap( 'delete_posts' );
		if ( $err ) return $err;

		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $this->template_slug() ) {
			return $this->err( "Template {$id} not found." );
		}

		$force  = $this->bool_arg( $args, 'force', false );
		$result = wp_delete_post( $id, $force );

		if ( ! $result ) {
			return $this->err( "Failed to delete template {$id}." );
		}

		return [
			'success'     => true,
			'template_id' => $id,
			'message'     => $force ? 'Template permanently deleted.' : 'Template moved to trash.',
		];
	}

	private function set_conditions( array $args ): array|\WP_Error {
		$id = $this->int_arg( $args, 'template_id' );
		if ( ! $id ) return $this->err( 'template_id is required.' );

		$err = $this->require_cap( 'edit_posts' );
		if ( $err ) return $err;

		$conditions = $this->arr_arg( $args, 'conditions' );
		Bricks_Data::set_template_conditions( $id, $conditions );

		return [
			'success'     => true,
			'template_id' => $id,
			'conditions'  => $conditions,
			'message'     => 'Template conditions updated successfully.',
		];
	}
}
