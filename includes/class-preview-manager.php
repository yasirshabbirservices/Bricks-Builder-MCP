<?php
namespace BricksMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages staged preview sessions.
 *
 * When a preview session is active, page/template write operations create
 * or update draft copies instead of touching the live published content.
 * The user can then inspect the drafts and commit or discard.
 */
class Preview_Manager {

	private const TRANSIENT_PREFIX = 'bmcp_preview_';
	private const TTL              = DAY_IN_SECONDS;

	// ── Session lifecycle ──────────────────────────────────────────────────────

	public static function transient_key(): string {
		return self::TRANSIENT_PREFIX . md5( Auth::get_key() );
	}

	public static function is_active(): bool {
		return ! empty( get_transient( self::transient_key() ) );
	}

	public static function get_session(): array {
		$data = get_transient( self::transient_key() );
		return is_array( $data ) ? $data : [];
	}

	public static function start(): string {
		$session_id = wp_generate_password( 12, false );
		$session    = [
			'session_id' => $session_id,
			'started_at' => time(),
			'pages'      => [],  // [ ['live_id'=>int, 'draft_id'=>int, 'title'=>string], ... ]
		];
		set_transient( self::transient_key(), $session, self::TTL );
		return $session_id;
	}

	/** End session without deleting draft posts. */
	public static function end(): void {
		delete_transient( self::transient_key() );
	}

	// ── Draft tracking ────────────────────────────────────────────────────────

	public static function record_draft( int $live_id, int $draft_id, string $title = '' ): void {
		$session = self::get_session();

		// Update existing entry if live_id already mapped
		foreach ( $session['pages'] as &$p ) {
			if ( $p['live_id'] === $live_id ) {
				$p['draft_id'] = $draft_id;
				if ( $title ) $p['title'] = $title;
				set_transient( self::transient_key(), $session, self::TTL );
				return;
			}
		}
		unset( $p );

		$session['pages'][] = [
			'live_id'  => $live_id,
			'draft_id' => $draft_id,
			'title'    => $title ?: get_the_title( $live_id ?: $draft_id ),
		];
		set_transient( self::transient_key(), $session, self::TTL );
	}

	/**
	 * Return the draft post ID for a given live post ID, or 0 if none.
	 * live_id = 0 means a new page created during preview (no live equivalent).
	 */
	public static function get_draft_for( int $live_id ): int {
		foreach ( self::get_session()['pages'] ?? [] as $p ) {
			if ( $p['live_id'] === $live_id ) return (int) $p['draft_id'];
		}
		return 0;
	}

	/**
	 * Clone a live page to a draft, copy Bricks meta, record the mapping.
	 * Returns the new draft ID, or 0 on failure.
	 */
	public static function clone_to_draft( int $live_id ): int {
		$live = get_post( $live_id );
		if ( ! $live ) return 0;

		$draft_id = wp_insert_post( [
			'post_title'   => '[Preview] ' . $live->post_title,
			'post_status'  => 'draft',
			'post_type'    => $live->post_type,
			'post_parent'  => $live->post_parent,
			'post_content' => $live->post_content,
		] );

		if ( ! $draft_id || is_wp_error( $draft_id ) ) return 0;

		// Tag so we know which live post this preview belongs to
		update_post_meta( $draft_id, '_bmcp_preview_live_id', $live_id );
		update_post_meta( $draft_id, '_bmcp_preview_session', self::get_session()['session_id'] ?? '' );

		// Copy Bricks element meta
		foreach ( [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER, BMCP_DB_PAGE_SETTINGS ] as $meta ) {
			$val = get_post_meta( $live_id, $meta, true );
			if ( $val !== '' && $val !== false ) {
				update_post_meta( $draft_id, $meta, $val );
			}
		}

		self::record_draft( $live_id, $draft_id, $live->post_title );
		return $draft_id;
	}

	// ── Commit / discard ──────────────────────────────────────────────────────

	/**
	 * Promote all preview drafts to live:
	 *   1. Auto-snapshot the live post (if it exists) via History_Manager
	 *   2. Copy Bricks meta from draft → live
	 *   3. Publish the page if it was a new page (live_id = 0)
	 *   4. Delete the draft
	 *   5. End the preview session
	 *
	 * @return array{committed: int, skipped: int, errors: string[]}
	 */
	public static function commit(): array {
		$session  = self::get_session();
		$pages    = $session['pages'] ?? [];
		$result   = [ 'committed' => 0, 'skipped' => 0, 'errors' => [] ];

		foreach ( $pages as $entry ) {
			$live_id  = (int) $entry['live_id'];
			$draft_id = (int) $entry['draft_id'];

			$draft = get_post( $draft_id );
			if ( ! $draft ) {
				$result['skipped']++;
				continue;
			}

			if ( $live_id > 0 ) {
				// Snapshot live page before overwriting
				History_Manager::capture( $live_id, 'content', 'bricks_commit_preview' );

				// Copy Bricks meta from draft to live
				foreach ( [ BMCP_DB_PAGE_CONTENT, BMCP_DB_PAGE_HEADER, BMCP_DB_PAGE_FOOTER, BMCP_DB_PAGE_SETTINGS ] as $meta ) {
					$val = get_post_meta( $draft_id, $meta, true );
					if ( $val !== '' && $val !== false ) {
						update_post_meta( $live_id, $meta, $val );
					}
				}

				// Sync title if it changed
				$draft_title = preg_replace( '/^\[Preview\]\s*/', '', $draft->post_title );
				wp_update_post( [ 'ID' => $live_id, 'post_title' => $draft_title ] );

				// Delete draft
				wp_delete_post( $draft_id, true );
				$result['committed']++;

			} else {
				// New page created during preview — publish it
				wp_update_post( [
					'ID'          => $draft_id,
					'post_status' => 'publish',
					'post_title'  => preg_replace( '/^\[Preview\]\s*/', '', $draft->post_title ),
				] );
				delete_post_meta( $draft_id, '_bmcp_preview_live_id' );
				delete_post_meta( $draft_id, '_bmcp_preview_session' );
				$result['committed']++;
			}
		}

		self::end();
		return $result;
	}

	/**
	 * Delete all preview draft posts for this session and end it.
	 *
	 * @return int Number of drafts deleted.
	 */
	public static function discard(): int {
		$session = self::get_session();
		$deleted = 0;

		foreach ( $session['pages'] ?? [] as $entry ) {
			$draft_id = (int) $entry['draft_id'];
			$live_id  = (int) $entry['live_id'];

			if ( $live_id === 0 ) {
				// New page — delete it entirely
				wp_delete_post( $draft_id, true );
			} else {
				// Existing page clone — delete the draft
				wp_delete_post( $draft_id, true );
			}
			$deleted++;
		}

		self::end();
		return $deleted;
	}
}
