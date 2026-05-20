<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Menus extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_list_nav_menus',
				'description' => 'List all WordPress navigation menus registered on the site. Returns menu ID, name, and item count. Use these IDs with the nav-menu Bricks element.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_create_nav_menu',
				'description' => 'Create a WordPress navigation menu with items. Returns the menu ID to use in the Bricks nav-menu element settings.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'  => [ 'type' => 'string', 'description' => 'Menu name (e.g. "Main Navigation")' ],
						'items' => [
							'type'        => 'array',
							'description' => 'Array of menu items in order.',
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'title'    => [ 'type' => 'string', 'description' => 'Link text' ],
									'url'      => [ 'type' => 'string', 'description' => 'URL or anchor (e.g. "/" or "#about")' ],
									'target'   => [ 'type' => 'string', 'description' => '"_blank" to open in new tab' ],
									'parent'   => [ 'type' => 'integer', 'description' => 'Menu item order position of parent (for dropdowns), 0 for top level' ],
								],
								'required' => [ 'title', 'url' ],
							],
						],
					],
					'required' => [ 'name', 'items' ],
				],
			],
			[
				'name'        => 'bricks_get_nav_menu',
				'description' => 'Get a navigation menu by ID including all its items.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'menu_id' => [ 'type' => 'integer', 'description' => 'WordPress nav menu term ID' ],
					],
					'required' => [ 'menu_id' ],
				],
			],
			[
				'name'        => 'bricks_update_nav_menu',
				'description' => 'Add or replace items in an existing WordPress navigation menu.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'menu_id' => [ 'type' => 'integer', 'description' => 'Menu ID to update' ],
						'items'   => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'title'  => [ 'type' => 'string' ],
									'url'    => [ 'type' => 'string' ],
									'target' => [ 'type' => 'string' ],
								],
								'required' => [ 'title', 'url' ],
							],
						],
						'replace' => [ 'type' => 'boolean', 'description' => 'If true, remove existing items first. Default false (append).' ],
					],
					'required' => [ 'menu_id', 'items' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_list_nav_menus':
				return $this->list_menus();
			case 'bricks_create_nav_menu':
				return $this->create_menu(
					$this->str_arg( $args, 'name' ),
					$this->arr_arg( $args, 'items', [] )
				);
			case 'bricks_get_nav_menu':
				return $this->get_menu( $this->int_arg( $args, 'menu_id' ) );
			case 'bricks_update_nav_menu':
				return $this->update_menu(
					$this->int_arg( $args, 'menu_id' ),
					$this->arr_arg( $args, 'items', [] ),
					$this->bool_arg( $args, 'replace', false )
				);
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function list_menus(): array {
		$menus = wp_get_nav_menus();
		return array_map( function ( $menu ) {
			return [
				'id'         => $menu->term_id,
				'name'       => $menu->name,
				'slug'       => $menu->slug,
				'item_count' => $menu->count,
			];
		}, $menus );
	}

	private function create_menu( string $name, array $items ): array {
		if ( empty( $name ) ) {
			return $this->err( 'Menu name is required.' );
		}

		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return $this->err( $menu_id->get_error_message() );
		}

		$created_items = $this->add_items_to_menu( $menu_id, $items );

		return [
			'menu_id'   => $menu_id,
			'name'      => $name,
			'items'     => $created_items,
			'message'   => 'Menu created. Use menu_id ' . $menu_id . ' in the Bricks nav-menu element setting.',
		];
	}

	private function get_menu( int $menu_id ): array {
		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu ) {
			return $this->err( 'Menu not found: ' . $menu_id );
		}

		$items = wp_get_nav_menu_items( $menu_id );
		return [
			'id'    => $menu->term_id,
			'name'  => $menu->name,
			'slug'  => $menu->slug,
			'items' => $items ? array_map( function ( $item ) {
				return [
					'id'     => $item->ID,
					'title'  => $item->title,
					'url'    => $item->url,
					'order'  => $item->menu_order,
					'parent' => $item->menu_item_parent,
					'target' => $item->target,
				];
			}, $items ) : [],
		];
	}

	private function update_menu( int $menu_id, array $items, bool $replace ): array {
		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu ) {
			return $this->err( 'Menu not found: ' . $menu_id );
		}

		if ( $replace ) {
			$existing = wp_get_nav_menu_items( $menu_id );
			if ( $existing ) {
				foreach ( $existing as $item ) {
					wp_delete_post( $item->ID, true );
				}
			}
		}

		$created_items = $this->add_items_to_menu( $menu_id, $items );

		return [
			'menu_id' => $menu_id,
			'items'   => $created_items,
			'message' => 'Menu updated.',
		];
	}

	private function add_items_to_menu( int $menu_id, array $items ): array {
		$created = [];
		$order   = 1;

		foreach ( $items as $item ) {
			$title  = $item['title'] ?? '';
			$url    = $item['url'] ?? '#';
			$target = $item['target'] ?? '';

			if ( empty( $title ) ) {
				continue;
			}

			$item_id = wp_update_nav_menu_item( $menu_id, 0, [
				'menu-item-title'       => $title,
				'menu-item-url'         => $url,
				'menu-item-status'      => 'publish',
				'menu-item-type'        => 'custom',
				'menu-item-target'      => $target,
				'menu-item-menu-order'  => $order,
			] );

			if ( ! is_wp_error( $item_id ) ) {
				$created[] = [ 'id' => $item_id, 'title' => $title, 'url' => $url, 'order' => $order ];
				$order++;
			}
		}

		return $created;
	}
}
