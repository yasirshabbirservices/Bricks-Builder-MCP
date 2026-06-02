<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snapshot-based history manager.
 * Auto-captures Bricks content before every write so the AI can restore any previous state.
 * Stores snapshots in a custom DB table ({prefix}bmcp_history).
 */
class History_Manager {

	private const MAX_SNAPSHOTS = 200;

	// Global option areas (post_id = 0 for these)
	private const GLOBAL_AREAS = [
		'global_settings',
		'color_palette',
		'global_classes',
		'theme_styles',
		'components',
	];

	// -------------------------------------------------------------------------
	// Table management
	// -------------------------------------------------------------------------

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bmcp_history';
	}

	public static function create_table(): void {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			area varchar(32) NOT NULL DEFAULT 'content',
			tool_name varchar(64) NOT NULL DEFAULT '',
			description text NOT NULL,
			data longtext NOT NULL,
			created_at int(10) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// -------------------------------------------------------------------------
	// Capture (auto-snapshot before writes)
	// -------------------------------------------------------------------------

	/**
	 * Capture the current state of a post area or global option before a write.
	 *
	 * @param int    $post_id   Post/page ID; 0 for global options.
	 * @param string $area      content|header|footer|global_settings|color_palette|global_classes|theme_styles|components
	 * @param string $tool_name MCP tool that triggered the write.
	 * @param string $description Optional override; auto-generated if empty.
	 * @return int|false  Inserted snapshot ID or false on failure.
	 */
	public static function capture( int $post_id, string $area, string $tool_name, string $description = '' ): int|false {
		global $wpdb;

		// Don't snapshot element areas without a real post ID
		$is_global = in_array( $area, self::GLOBAL_AREAS, true );
		if ( ! $is_global && $post_id <= 0 ) {
			return false;
		}

		if ( $description === '' ) {
			$description = self::build_description( $post_id, $area, $tool_name );
		}

		$data = self::read_current( $post_id, $area );

		$inserted = $wpdb->insert(
			self::table(),
			[
				'post_id'     => $post_id,
				'area'        => $area,
				'tool_name'   => $tool_name,
				'description' => $description,
				'data'        => maybe_serialize( $data ),
				'created_at'  => time(),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( ! $inserted ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;
		self::prune();

		return $id;
	}

	// -------------------------------------------------------------------------
	// Restore
	// -------------------------------------------------------------------------

	/**
	 * Restore a snapshot. Auto-captures the current state first so the restore itself is undoable.
	 *
	 * @return array|false  The restored snapshot row (without data) or false if not found.
	 */
	public static function restore( int $snapshot_id, string $restore_tool = 'bricks_snapshot_restore' ): array|false {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $snapshot_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return false;
		}

		$post_id = (int) $row['post_id'];
		$area    = $row['area'];
		$data    = maybe_unserialize( $row['data'] );

		// Snapshot current state before overwriting so the restore is undoable
		self::capture( $post_id, $area, $restore_tool, "Auto-snapshot before restore of #{$snapshot_id}" );

		self::write_data( $post_id, $area, $data );

		return self::format_row( $row );
	}

	// -------------------------------------------------------------------------
	// Read / List / Delete
	// -------------------------------------------------------------------------

	public static function get_by_id( int $id, bool $include_data = false ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ? self::format_row( $row, $include_data ) : null;
	}

	public static function get_paginated( int $page, int $per_page = 20, int $post_id = 0, string $area = '' ): array {
		global $wpdb;
		$table  = self::table();
		$offset = ( $page - 1 ) * $per_page;

		$conditions = [];
		$params     = [];

		if ( $post_id > 0 ) {
			$conditions[] = 'post_id = %d';
			$params[]     = $post_id;
		}
		if ( $area !== '' ) {
			$conditions[] = 'area = %s';
			$params[]     = $area;
		}

		$where = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", ...$params ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows  = $wpdb->get_results(
				$wpdb->prepare( "SELECT id,post_id,area,tool_name,description,created_at FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", ...[...$params, $per_page, $offset] ),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows  = $wpdb->get_results(
				$wpdb->prepare( "SELECT id,post_id,area,tool_name,description,created_at FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
				ARRAY_A
			);
		}

		return [
			'items'       => array_map( [ __CLASS__, 'format_row' ], $rows ?: [] ),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		];
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
	}

	public static function clear_all(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( 'TRUNCATE TABLE ' . self::table() );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private static function read_current( int $post_id, string $area ): mixed {
		switch ( $area ) {
			case 'global_settings':
				return get_option( 'bricks_global_settings', [] );
			case 'color_palette':
				return get_option( 'bricks_color_palette', [] );
			case 'global_classes':
				return get_option( 'bricks_global_classes', [] );
			case 'theme_styles':
				return get_option( 'bricks_theme_styles', [] );
			case 'components':
				$key = defined( 'BRICKS_DB_COMPONENTS' ) ? BRICKS_DB_COMPONENTS : 'bricks_components';
				return get_option( $key, [] );
			case 'snippet':
				// Capture full snippet state so it can be fully restored
				$post = get_post( $post_id );
				if ( ! $post || $post->post_type !== Snippets_Manager::CPT ) return null;
				return [
					'title'       => $post->post_title,
					'code'        => $post->post_content,
					'post_status' => $post->post_status,
					'type'        => get_post_meta( $post_id, '_bmcp_snip_type',       true ),
					'location'    => get_post_meta( $post_id, '_bmcp_snip_location',   true ),
					'hook'        => get_post_meta( $post_id, '_bmcp_snip_hook',        true ),
					'priority'    => (int) get_post_meta( $post_id, '_bmcp_snip_priority', true ),
					'description' => get_post_meta( $post_id, '_bmcp_snip_desc',       true ),
					'tags'        => get_post_meta( $post_id, '_bmcp_snip_tags',        true ),
					'url'         => get_post_meta( $post_id, '_bmcp_snip_url',         true ),
					'shortcode'   => get_post_meta( $post_id, '_bmcp_snip_shortcode',   true ),
					'conditions'  => json_decode( get_post_meta( $post_id, '_bmcp_snip_conditions', true ) ?: '[]', true ),
					'signature'   => get_post_meta( $post_id, '_bmcp_snip_signature',   true ),
				];
			default:
				return Bricks_Data::get_elements( $post_id, $area );
		}
	}

	private static function write_data( int $post_id, string $area, mixed $data ): void {
		switch ( $area ) {
			case 'global_settings':
				update_option( 'bricks_global_settings', $data );
				break;
			case 'color_palette':
				update_option( 'bricks_color_palette', $data );
				break;
			case 'global_classes':
				update_option( 'bricks_global_classes', $data );
				break;
			case 'theme_styles':
				update_option( 'bricks_theme_styles', $data );
				break;
			case 'components':
				$key = defined( 'BRICKS_DB_COMPONENTS' ) ? BRICKS_DB_COMPONENTS : 'bricks_components';
				update_option( $key, $data );
				break;
			case 'snippet':
				// Restore full snippet state from snapshot
				if ( ! is_array( $data ) || empty( $data['title'] ) ) break;
				$restore_args = array_merge( $data, [
					'status' => ( $data['post_status'] ?? 'draft' ) === 'publish' ? 'active' : 'inactive',
				] );
				Snippets_Manager::save_snippet( $restore_args, $post_id );
				break;
			default:
				// Elements: write post meta + trigger Bricks CSS regeneration
				$meta_key = Bricks_Data::meta_key( $area );
				update_post_meta( $post_id, $meta_key, is_array( $data ) ? $data : [] );
				wp_update_post( [
					'ID'                => $post_id,
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql', true ),
				] );
				break;
		}
	}

	private static function prune(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count <= self::MAX_SNAPSHOTS ) {
			return;
		}
		$excess = $count - self::MAX_SNAPSHOTS;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} ORDER BY id ASC LIMIT %d", $excess ) );
	}

	private static function format_row( array $row, bool $include_data = false ): array {
		$post_id = (int) $row['post_id'];
		$result  = [
			'id'          => (int) $row['id'],
			'post_id'     => $post_id,
			'post_title'  => $post_id > 0 ? ( get_the_title( $post_id ) ?: "Post #{$post_id}" ) : '— global —',
			'area'        => $row['area'],
			'tool_name'   => $row['tool_name'],
			'description' => $row['description'],
			'created_at'  => (int) $row['created_at'],
		];
		if ( $include_data && isset( $row['data'] ) ) {
			$result['data'] = maybe_unserialize( $row['data'] );
		}
		return $result;
	}

	private static function build_description( int $post_id, string $area, string $tool_name ): string {
		$title = $post_id > 0 ? ( get_the_title( $post_id ) ?: "Post #{$post_id}" ) : 'Global';
		return 'Before ' . $tool_name . ' — "' . $title . '" (' . $area . ')';
	}
}
