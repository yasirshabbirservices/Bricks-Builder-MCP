<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_History extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_snapshot_list',
				'description' => 'List recent content snapshots (restore points). A snapshot is auto-created before every write operation so you can undo any change. Use this to browse available history.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Filter by post/page ID. Use 0 to see only global option snapshots (color palette, global classes, etc.).' ],
						'area'    => [ 'type' => 'string',  'description' => 'Filter by area: content | header | footer | global_settings | color_palette | global_classes | theme_styles | components' ],
						'page'    => [ 'type' => 'integer', 'description' => 'Page number (default: 1, 20 per page)' ],
					],
				],
			],
			[
				'name'        => 'bricks_snapshot_get',
				'description' => 'Get a specific snapshot by ID including its full stored data. Use this before restoring to verify the content is what you expect.',
				'inputSchema' => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Snapshot ID from bricks_snapshot_list' ],
					],
				],
			],
			[
				'name'        => 'bricks_snapshot_restore',
				'description' => 'Restore a snapshot by ID. The current state is automatically captured as a new snapshot first — so the restore itself is undoable. Use bricks_snapshot_list to find the right ID.',
				'inputSchema' => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Snapshot ID to restore' ],
					],
				],
			],
			[
				'name'        => 'bricks_snapshot_delete',
				'description' => 'Delete a specific snapshot by ID to free up history space.',
				'inputSchema' => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Snapshot ID to delete' ],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		return match ( $name ) {
			'bricks_snapshot_list'    => $this->list_snapshots( $args ),
			'bricks_snapshot_get'     => $this->get_snapshot( $args ),
			'bricks_snapshot_restore' => $this->restore_snapshot( $args ),
			'bricks_snapshot_delete'  => $this->delete_snapshot( $args ),
			default                   => $this->err( "Unknown tool: {$name}" ),
		};
	}

	private function list_snapshots( array $args ): array {
		$result = \BricksMCP\History_Manager::get_paginated(
			max( 1, (int) ( $args['page'] ?? 1 ) ),
			20,
			(int) ( $args['post_id'] ?? -1 ) === -1 ? 0 : (int) $args['post_id'],
			sanitize_key( $args['area'] ?? '' )
		);
		return $this->success( $result );
	}

	private function get_snapshot( array $args ): array|\WP_Error {
		$id       = (int) ( $args['id'] ?? 0 );
		$snapshot = \BricksMCP\History_Manager::get_by_id( $id, true );
		if ( ! $snapshot ) {
			return $this->err( "Snapshot not found: {$id}" );
		}
		return $this->success( $snapshot );
	}

	private function restore_snapshot( array $args ): array|\WP_Error {
		$id  = (int) ( $args['id'] ?? 0 );
		$row = \BricksMCP\History_Manager::restore( $id, 'bricks_snapshot_restore' );
		if ( ! $row ) {
			return $this->err( "Snapshot not found or restore failed: {$id}" );
		}
		return $this->success( [
			'restored'    => true,
			'snapshot_id' => $id,
			'post_id'     => $row['post_id'],
			'area'        => $row['area'],
			'message'     => "Snapshot #{$id} restored. Current state was auto-saved as a new snapshot before restoring — you can undo this restore by restoring that new snapshot.",
		] );
	}

	private function delete_snapshot( array $args ): array|\WP_Error {
		$id      = (int) ( $args['id'] ?? 0 );
		$deleted = \BricksMCP\History_Manager::delete( $id );
		if ( ! $deleted ) {
			return $this->err( "Snapshot not found: {$id}" );
		}
		return $this->success( [ 'deleted' => true, 'id' => $id ] );
	}
}
