<?php
namespace BricksMCP\Tools;

use BricksMCP\Snippets_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP Tools — Snippets
 *
 * Exposes 7 tools that let AI clients create, read, update, delete,
 * toggle, and export code snippets managed by Snippets_Manager.
 */
class Tool_Snippets extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bmcp_snippets_list',
				'description' => 'List all code snippets. Returns id, title, type, status, location, hook, tags and modified date. Code body is NOT included — call bmcp_snippet_get for that.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'status'   => [ 'type' => 'string',  'description' => 'Filter: active | inactive (omit for all)' ],
						'type'     => [ 'type' => 'string',  'description' => 'Filter: php | javascript | javascript_url | css | css_url | html' ],
						'tag'      => [ 'type' => 'string',  'description' => 'Filter by tag (partial match)' ],
						'search'   => [ 'type' => 'string',  'description' => 'Search in snippet title' ],
						'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (default 50, max 100)', 'default' => 50 ],
						'page'     => [ 'type' => 'integer', 'description' => 'Page number (default 1)', 'default' => 1 ],
					],
				],
			],
			[
				'name'        => 'bmcp_snippet_get',
				'description' => 'Get a single snippet including its full code body.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Snippet post ID' ],
					],
					'required' => [ 'id' ],
				],
			],
			[
				'name'        => 'bmcp_snippet_create',
				'description' => implode( "\n", [
					'Create a new code snippet.',
					'',
					'TYPES:',
					'  php             — PHP executed via a WordPress action hook (default)',
					'  javascript      — Inline <script> output in wp_footer',
					'  javascript_url  — External JS enqueued via wp_enqueue_script',
					'  css             — Inline <style> output in wp_head',
					'  css_url         — External CSS enqueued via wp_enqueue_style',
					'  html            — Raw HTML output on the configured hook',
					'',
					'LOCATIONS: everywhere | frontend | admin | shortcode',
					'',
					'PHP snippets are auto-signed with wp_hash() on every save — never add',
					'signature fields manually. Snippets without a valid signature will not',
					'execute (same security model as the Bricks code element).',
					'',
					'CONDITIONS: optional array of rule objects. All rules must pass (AND logic).',
					'  type  — post_type | user_role | logged_in | url_pattern',
					'  op    — equals | not_equals',
					'  value — string to compare against',
				] ),
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'title'       => [ 'type' => 'string',  'description' => 'Snippet name (required)' ],
						'code'        => [ 'type' => 'string',  'description' => 'The code body. For PHP omit the opening <?php tag (it is added automatically by eval wrapper).' ],
						'type'        => [ 'type' => 'string',  'description' => 'php | javascript | css | html | javascript_url | css_url (default: php)', 'default' => 'php' ],
						'status'      => [ 'type' => 'string',  'description' => 'active | inactive (default: inactive — always test before activating)', 'default' => 'inactive' ],
						'location'    => [ 'type' => 'string',  'description' => 'everywhere | frontend | admin | shortcode (default: everywhere)', 'default' => 'everywhere' ],
						'hook'        => [ 'type' => 'string',  'description' => 'WordPress action hook. Ignored for CSS/JS types. (default: init)', 'default' => 'init' ],
						'priority'    => [ 'type' => 'integer', 'description' => 'Hook execution priority (default: 10)', 'default' => 10 ],
						'description' => [ 'type' => 'string',  'description' => 'Human-readable notes about what this snippet does' ],
						'tags'        => [ 'type' => 'string',  'description' => 'Comma-separated tags, e.g. "woocommerce, checkout, fixes"' ],
						'url'         => [ 'type' => 'string',  'description' => 'External URL — only for javascript_url and css_url types' ],
						'conditions'  => [
							'type'        => 'array',
							'description' => 'Optional execution conditions (AND logic — all must match).',
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'type'  => [ 'type' => 'string', 'description' => 'post_type | user_role | logged_in | url_pattern' ],
									'op'    => [ 'type' => 'string', 'description' => 'equals | not_equals (default: equals)', 'default' => 'equals' ],
									'value' => [ 'type' => 'string', 'description' => 'The comparison value (e.g. "product", "administrator", "true", "/shop/")' ],
								],
								'required' => [ 'type', 'value' ],
							],
						],
					],
					'required' => [ 'title' ],
				],
			],
			[
				'name'        => 'bmcp_snippet_update',
				'description' => 'Update an existing snippet. Only supply the fields you want to change — omitted fields keep their current values. PHP signature is automatically recomputed whenever the code field is present.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'          => [ 'type' => 'integer', 'description' => 'Snippet post ID (required)' ],
						'title'       => [ 'type' => 'string',  'description' => 'New title' ],
						'code'        => [ 'type' => 'string',  'description' => 'New code body' ],
						'type'        => [ 'type' => 'string',  'description' => 'php | javascript | css | html | javascript_url | css_url' ],
						'status'      => [ 'type' => 'string',  'description' => 'active | inactive' ],
						'location'    => [ 'type' => 'string',  'description' => 'everywhere | frontend | admin | shortcode' ],
						'hook'        => [ 'type' => 'string',  'description' => 'WordPress action hook' ],
						'priority'    => [ 'type' => 'integer', 'description' => 'Hook priority' ],
						'description' => [ 'type' => 'string',  'description' => 'Description / notes' ],
						'tags'        => [ 'type' => 'string',  'description' => 'Comma-separated tags' ],
						'url'         => [ 'type' => 'string',  'description' => 'External URL (javascript_url / css_url only)' ],
						'conditions'  => [ 'type' => 'array',   'description' => 'Condition rules array — replaces all existing conditions' ],
					],
					'required' => [ 'id' ],
				],
			],
			[
				'name'        => 'bmcp_snippet_delete',
				'description' => 'Permanently delete a snippet. This cannot be undone.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Snippet post ID to delete' ],
					],
					'required' => [ 'id' ],
				],
			],
			[
				'name'        => 'bmcp_snippet_toggle',
				'description' => 'Enable or disable a snippet without touching its code.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'     => [ 'type' => 'integer', 'description' => 'Snippet post ID' ],
						'status' => [ 'type' => 'string',  'description' => 'active | inactive' ],
					],
					'required' => [ 'id', 'status' ],
				],
			],
			[
				'name'        => 'bmcp_snippets_export',
				'description' => 'Export one or more snippets as a portable JSON object (includes the full code body). Pass an empty ids array to export everything.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ids' => [
							'type'        => 'array',
							'description' => 'Snippet IDs to export. Empty = export all.',
							'items'       => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $cap_error = $this->require_cap( 'manage_options' ) ) {
			return $cap_error;
		}

		switch ( $name ) {

			// ── List ──────────────────────────────────────────────────────────
			case 'bmcp_snippets_list':
				return $this->success( Snippets_Manager::get_snippets( $args ) );

			// ── Get ───────────────────────────────────────────────────────────
			case 'bmcp_snippet_get':
				$snippet = Snippets_Manager::get_snippet( $this->int_arg( $args, 'id' ) );
				if ( ! $snippet ) {
					return $this->err( 'Snippet not found.', 'bmcp_not_found' );
				}
				return $this->success( $snippet );

			// ── Create ────────────────────────────────────────────────────────
			case 'bmcp_snippet_create':
				$id = Snippets_Manager::save_snippet( $args );
				if ( is_wp_error( $id ) ) return $id;
				return $this->success( [
					'id'      => $id,
					'snippet' => Snippets_Manager::get_snippet( $id ),
					'message' => "Snippet created (ID {$id}).",
				] );

			// ── Update ────────────────────────────────────────────────────────
			case 'bmcp_snippet_update':
				$id       = $this->int_arg( $args, 'id' );
				$existing = Snippets_Manager::get_snippet( $id );
				if ( ! $existing ) {
					return $this->err( 'Snippet not found.', 'bmcp_not_found' );
				}
				// Merge existing values so omitted fields are preserved
				$merged = array_merge( $existing, $args );
				$result = Snippets_Manager::save_snippet( $merged, $id );
				if ( is_wp_error( $result ) ) return $result;
				return $this->success( [
					'id'      => $id,
					'snippet' => Snippets_Manager::get_snippet( $id ),
					'message' => "Snippet ID {$id} updated.",
				] );

			// ── Delete ────────────────────────────────────────────────────────
			case 'bmcp_snippet_delete':
				$id = $this->int_arg( $args, 'id' );
				if ( ! Snippets_Manager::delete_snippet( $id ) ) {
					return $this->err( 'Snippet not found or could not be deleted.', 'bmcp_not_found' );
				}
				return $this->success( [ 'deleted' => true, 'id' => $id ] );

			// ── Toggle ────────────────────────────────────────────────────────
			case 'bmcp_snippet_toggle':
				$id     = $this->int_arg( $args, 'id' );
				$status = $this->str_arg( $args, 'status', 'inactive' );
				if ( ! Snippets_Manager::toggle_snippet( $id, $status ) ) {
					return $this->err( 'Snippet not found.', 'bmcp_not_found' );
				}
				return $this->success( [
					'id'      => $id,
					'status'  => $status,
					'message' => "Snippet ID {$id} set to {$status}.",
				] );

			// ── Export ────────────────────────────────────────────────────────
			case 'bmcp_snippets_export':
				$ids    = array_map( 'intval', $this->arr_arg( $args, 'ids' ) );
				$export = Snippets_Manager::export_snippets( $ids );
				return $this->success( $export );

			default:
				return $this->err( "Unknown tool: {$name}" );
		}
	}
}
