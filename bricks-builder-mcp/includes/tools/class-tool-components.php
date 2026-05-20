<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Components extends Tool_Base {

	private function db_key(): string {
		return defined( 'BRICKS_DB_COMPONENTS' ) ? BRICKS_DB_COMPONENTS : 'bricks_components';
	}

	public function define(): array {
		return [
			[
				'name'        => 'bricks_list_components',
				'description' => 'List all saved Bricks components (reusable element groups). Returns component ID, name, and element count. Components can be inserted into any page/template by referencing their ID.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_get_component',
				'description' => 'Get a Bricks component by ID including its full elements array.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'component_id' => [ 'type' => 'string', 'description' => 'Component ID (6-char alphanumeric)' ],
					],
					'required' => [ 'component_id' ],
				],
			],
			[
				'name'        => 'bricks_create_component',
				'description' => 'Create a reusable Bricks component from an elements array. The first element in the array is the root element and its ID becomes the component ID. Once created, insert it into pages using bricks_insert_component.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'     => [ 'type' => 'string', 'description' => 'Human-readable component name (e.g. "Pricing Card")' ],
						'elements' => [
							'type'        => 'array',
							'description' => 'Bricks elements array. The root element (parent=0) becomes the component root.',
						],
					],
					'required' => [ 'name', 'elements' ],
				],
			],
			[
				'name'        => 'bricks_update_component',
				'description' => 'Update the elements of an existing Bricks component.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'component_id' => [ 'type' => 'string', 'description' => 'Component ID to update' ],
						'elements'     => [ 'type' => 'array', 'description' => 'New elements array for the component' ],
						'name'         => [ 'type' => 'string', 'description' => 'Optional new name for the component' ],
					],
					'required' => [ 'component_id', 'elements' ],
				],
			],
			[
				'name'        => 'bricks_delete_component',
				'description' => 'Delete a Bricks component by ID.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'component_id' => [ 'type' => 'string', 'description' => 'Component ID to delete' ],
					],
					'required' => [ 'component_id' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_list_components':
				return $this->list_components();
			case 'bricks_get_component':
				return $this->get_component( $this->str_arg( $args, 'component_id' ) );
			case 'bricks_create_component':
				return $this->create_component(
					$this->str_arg( $args, 'name' ),
					$this->arr_arg( $args, 'elements', [] )
				);
			case 'bricks_update_component':
				return $this->update_component(
					$this->str_arg( $args, 'component_id' ),
					$this->arr_arg( $args, 'elements', [] ),
					$this->str_arg( $args, 'name', '' )
				);
			case 'bricks_delete_component':
				return $this->delete_component( $this->str_arg( $args, 'component_id' ) );
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function get_all(): array {
		return (array) get_option( $this->db_key(), [] );
	}

	private function save_all( array $components ): void {
		update_option( $this->db_key(), $components );
	}

	private function list_components(): array {
		$all = $this->get_all();
		if ( empty( $all ) ) {
			return [ 'components' => [], 'count' => 0 ];
		}

		$result = [];
		foreach ( $all as $id => $component ) {
			$result[] = [
				'id'            => $id,
				'name'          => $component['name'] ?? $id,
				'element_count' => count( $component['elements'] ?? [] ),
			];
		}

		return [ 'components' => $result, 'count' => count( $result ) ];
	}

	private function get_component( string $component_id ): array {
		$all = $this->get_all();
		if ( ! isset( $all[ $component_id ] ) ) {
			return $this->err( 'Component not found: ' . $component_id );
		}

		$component = $all[ $component_id ];
		return [
			'id'       => $component_id,
			'name'     => $component['name'] ?? $component_id,
			'elements' => $component['elements'] ?? [],
		];
	}

	private function create_component( string $name, array $elements ): array {
		if ( empty( $elements ) ) {
			return $this->err( 'Elements array is required.' );
		}

		// Normalize elements
		$elements = \BricksMCP\Bricks_Data::normalize_elements( $elements );

		// Find the root element (parent === 0 or "0")
		$root_id = null;
		foreach ( $elements as $el ) {
			$parent = $el['parent'] ?? 0;
			if ( $parent === 0 || $parent === '0' ) {
				$root_id = $el['id'];
				break;
			}
		}

		if ( ! $root_id ) {
			return $this->err( 'No root element (parent=0) found in elements array.' );
		}

		$all = $this->get_all();

		if ( isset( $all[ $root_id ] ) ) {
			return $this->err( 'A component with ID ' . $root_id . ' already exists. Use bricks_update_component.' );
		}

		$all[ $root_id ] = [
			'id'       => $root_id,
			'name'     => $name ?: $root_id,
			'elements' => $elements,
		];

		$this->save_all( $all );

		return [
			'component_id' => $root_id,
			'name'         => $name,
			'element_count'=> count( $elements ),
			'message'      => 'Component created. Reference it in pages by setting "cid": "' . $root_id . '" on a container element.',
		];
	}

	private function update_component( string $component_id, array $elements, string $name ): array {
		$all = $this->get_all();

		if ( ! isset( $all[ $component_id ] ) ) {
			return $this->err( 'Component not found: ' . $component_id );
		}

		$elements = \BricksMCP\Bricks_Data::normalize_elements( $elements );

		if ( $name ) {
			$all[ $component_id ]['name'] = $name;
		}
		$all[ $component_id ]['elements'] = $elements;

		$this->save_all( $all );

		return [
			'component_id'  => $component_id,
			'name'          => $all[ $component_id ]['name'],
			'element_count' => count( $elements ),
			'message'       => 'Component updated.',
		];
	}

	private function delete_component( string $component_id ): array {
		$all = $this->get_all();

		if ( ! isset( $all[ $component_id ] ) ) {
			return $this->err( 'Component not found: ' . $component_id );
		}

		$name = $all[ $component_id ]['name'] ?? $component_id;
		unset( $all[ $component_id ] );
		$this->save_all( $all );

		return [
			'success'      => true,
			'component_id' => $component_id,
			'message'      => 'Component "' . $name . '" deleted.',
		];
	}
}
