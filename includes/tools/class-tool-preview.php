<?php
namespace BricksMCP\Tools;

use BricksMCP\Preview_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Staged preview mode tools.
 *
 * When preview mode is active, bricks_create_page and bricks_update_page
 * automatically route writes to draft copies. The user can inspect the
 * drafts in WordPress admin, then commit (promote to live) or discard.
 */
class Tool_Preview extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_enable_preview_mode',
				'description' => 'Enable staged preview mode for this session. All bricks_create_page and bricks_update_page calls will write to draft copies instead of the live site. Returns a session_id. Call bricks_commit_preview to promote drafts to live, or bricks_discard_preview to delete them.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_disable_preview_mode',
				'description' => 'Disable preview mode without deleting draft pages. Drafts remain and can still be committed or discarded later via their WordPress draft status.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_preview_status',
				'description' => 'Check whether preview mode is active and list all draft pages created in the current session. Shows live_id → draft_id mappings and page titles.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_commit_preview',
				'description' => 'Promote all preview draft pages to live: auto-snapshots each live page first, copies Bricks content from draft to live, publishes new pages. Ends the preview session. Safe to undo — snapshots are created before every live write.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_discard_preview',
				'description' => 'Delete all preview draft pages for the current session without touching the live site. Ends the preview session. Use when you want to abandon the AI\'s changes.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_enable_preview_mode':
				return $this->enable();
			case 'bricks_disable_preview_mode':
				return $this->disable();
			case 'bricks_preview_status':
				return $this->status();
			case 'bricks_commit_preview':
				return $this->commit();
			case 'bricks_discard_preview':
				return $this->discard();
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	// -------------------------------------------------------------------------

	private function enable(): array {
		if ( Preview_Manager::is_active() ) {
			$session = Preview_Manager::get_session();
			return [
				'already_active' => true,
				'session_id'     => $session['session_id'] ?? '',
				'pages_count'    => count( $session['pages'] ?? [] ),
				'note'           => 'Preview mode is already active. bricks_create_page and bricks_update_page will continue writing to draft copies.',
			];
		}

		$session_id = Preview_Manager::start();
		return [
			'enabled'    => true,
			'session_id' => $session_id,
			'note'       => 'Preview mode active. All bricks_create_page and bricks_update_page calls will now write to draft copies. Call bricks_commit_preview when done, or bricks_discard_preview to cancel.',
		];
	}

	private function disable(): array {
		if ( ! Preview_Manager::is_active() ) {
			return [ 'was_active' => false, 'note' => 'Preview mode was not active.' ];
		}

		$session = Preview_Manager::get_session();
		$count   = count( $session['pages'] ?? [] );
		Preview_Manager::end();

		return [
			'disabled'    => true,
			'drafts_kept' => $count,
			'note'        => "Preview mode ended. {$count} draft page(s) were kept. Find them in WordPress Admin → Pages (Drafts) or call bricks_preview_status to review before committing.",
		];
	}

	private function status(): array {
		if ( ! Preview_Manager::is_active() ) {
			return [
				'active'  => false,
				'note'    => 'Preview mode is not active. Call bricks_enable_preview_mode to start a session.',
			];
		}

		$session = Preview_Manager::get_session();
		$pages   = $session['pages'] ?? [];

		$summary = array_map( function ( $p ) {
			$draft = get_post( $p['draft_id'] );
			return [
				'live_id'    => $p['live_id'],
				'draft_id'   => $p['draft_id'],
				'title'      => $p['title'] ?? '',
				'draft_url'  => $draft ? get_permalink( $p['draft_id'] ) : null,
				'is_new_page' => $p['live_id'] === 0,
			];
		}, $pages );

		return [
			'active'     => true,
			'session_id' => $session['session_id'] ?? '',
			'started_at' => isset( $session['started_at'] ) ? wp_date( 'Y-m-d H:i:s', $session['started_at'] ) : '',
			'pages'      => $summary,
			'count'      => count( $pages ),
			'note'       => count( $pages ) === 0
				? 'No pages have been written yet in this preview session.'
				: 'Call bricks_commit_preview to promote these drafts to live, or bricks_discard_preview to delete them.',
		];
	}

	private function commit(): array {
		$err = $this->require_cap( 'edit_pages' );
		if ( $err ) return $err;

		if ( ! Preview_Manager::is_active() ) {
			return $this->err( 'No active preview session. Call bricks_enable_preview_mode first.' );
		}

		$result = Preview_Manager::commit();

		return [
			'success'   => true,
			'committed' => $result['committed'],
			'skipped'   => $result['skipped'],
			'errors'    => $result['errors'],
			'note'      => "Committed {$result['committed']} page(s) to live. Snapshots were saved before each live write — use bricks_snapshot_list to review or restore if needed.",
		];
	}

	private function discard(): array {
		$err = $this->require_cap( 'delete_posts' );
		if ( $err ) return $err;

		if ( ! Preview_Manager::is_active() ) {
			return $this->err( 'No active preview session. Nothing to discard.' );
		}

		$deleted = Preview_Manager::discard();

		return [
			'success' => true,
			'deleted' => $deleted,
			'note'    => "Deleted {$deleted} preview draft(s). The live site was not modified.",
		];
	}
}
