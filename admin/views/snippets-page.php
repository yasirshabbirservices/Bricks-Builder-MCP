<?php
/**
 * Snippets Manager — List View
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permission denied.' );

use BricksMCP\Snippets_Manager;

$safe_mode   = Snippets_Manager::is_safe_mode();
$sm_locked   = defined( 'BMCP_SNIPPETS_SAFE_MODE' );
$stats       = Snippets_Manager::get_stats();
$list_url    = admin_url( 'admin.php?page=bricks-mcp-snippets' );
$new_url     = admin_url( 'admin.php?page=bricks-mcp-snippets&action=new' );

// Safe-mode toggle URLs
$sm_enable_url  = wp_nonce_url( add_query_arg( 'bmcp_snip_safemode', '1', $list_url ), 'bmcp_snip_safemode' );
$sm_disable_url = wp_nonce_url( add_query_arg( 'bmcp_snip_safemode', '0', $list_url ), 'bmcp_snip_safemode' );

// Fetch snippet list for the current request
$filter_type   = sanitize_key( $_GET['snip_type']   ?? '' );
$filter_status = sanitize_key( $_GET['snip_status'] ?? '' );
$search        = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$current_page  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

$result = Snippets_Manager::get_snippets( [
	'type'     => $filter_type,
	'status'   => $filter_status,
	'search'   => $search,
	'page'     => $current_page,
	'per_page' => 20,
] );
$snippets   = $result['snippets'];
$total      = $result['total'];
$total_pages = $result['pages'];

// Helper: type label + colour class
function bmcp_snip_type_info( string $type ): array {
	$map = [
		'php'            => [ 'PHP',   'bmcp-snip-badge--php' ],
		'javascript'     => [ 'JS',    'bmcp-snip-badge--js' ],
		'javascript_url' => [ 'JS ↗',  'bmcp-snip-badge--js' ],
		'css'            => [ 'CSS',   'bmcp-snip-badge--css' ],
		'css_url'        => [ 'CSS ↗', 'bmcp-snip-badge--css' ],
		'html'           => [ 'HTML',  'bmcp-snip-badge--html' ],
	];
	return $map[ $type ] ?? [ strtoupper( $type ), '' ];
}
?>
<div id="bmcp-wrap" class="wrap bmcp-snippets-wrap">

	<!-- ── PAGE HEADER ──────────────────────────────────────────────── -->
	<div class="bmcp-page-header">
		<div class="bmcp-header-left">
			<div class="bmcp-header-icon">💾</div>
			<div class="bmcp-header-text">
				<h1>
					Snippets
					<span class="bmcp-version-badge">v<?php echo esc_html( BMCP_VERSION ); ?></span>
				</h1>
				<p>Create and manage PHP, JS, CSS &amp; HTML code snippets — AI-controlled via MCP tools.</p>
			</div>
		</div>
		<div class="bmcp-header-right">
			<a href="<?php echo esc_url( $new_url ); ?>" class="bmcp-btn bmcp-btn--primary">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
				New Snippet
			</a>
		</div>
	</div>

	<?php if ( $safe_mode ) : ?>
	<!-- ── SAFE MODE BANNER ─────────────────────────────────────────── -->
	<div class="bmcp-safe-mode-banner">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
		<span><strong>Safe Mode is active</strong> — PHP snippets will not execute until safe mode is disabled.</span>
		<?php if ( $sm_locked ) : ?>
			<span class="bmcp-safe-mode-locked">Locked by <code>BMCP_SNIPPETS_SAFE_MODE</code> constant in wp-config.php</span>
		<?php else : ?>
			<a href="<?php echo esc_url( $sm_disable_url ); ?>" class="bmcp-btn bmcp-btn--sm bmcp-btn--warning">Disable Safe Mode</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- ── STATS ROW ────────────────────────────────────────────────── -->
	<div class="bmcp-snip-stats">
		<div class="bmcp-snip-stat">
			<span class="bmcp-snip-stat__num"><?php echo (int) $stats['total']; ?></span>
			<span class="bmcp-snip-stat__label">Total</span>
		</div>
		<div class="bmcp-snip-stat bmcp-snip-stat--active">
			<span class="bmcp-snip-stat__num"><?php echo (int) $stats['active']; ?></span>
			<span class="bmcp-snip-stat__label">Active</span>
		</div>
		<div class="bmcp-snip-stat">
			<span class="bmcp-snip-stat__num"><?php echo (int) $stats['inactive']; ?></span>
			<span class="bmcp-snip-stat__label">Inactive</span>
		</div>
		<div class="bmcp-snip-stat">
			<span class="bmcp-snip-stat__num"><?php echo (int) ( $stats['by_type']['php'] ?? 0 ); ?></span>
			<span class="bmcp-snip-stat__label">PHP</span>
		</div>
		<div class="bmcp-snip-stat">
			<span class="bmcp-snip-stat__num"><?php echo (int) ( ( $stats['by_type']['javascript'] ?? 0 ) + ( $stats['by_type']['javascript_url'] ?? 0 ) ); ?></span>
			<span class="bmcp-snip-stat__label">JS</span>
		</div>
		<div class="bmcp-snip-stat">
			<span class="bmcp-snip-stat__num"><?php echo (int) ( ( $stats['by_type']['css'] ?? 0 ) + ( $stats['by_type']['css_url'] ?? 0 ) ); ?></span>
			<span class="bmcp-snip-stat__label">CSS</span>
		</div>
	</div>

	<!-- ── FILTER BAR ───────────────────────────────────────────────── -->
	<div class="bmcp-snip-toolbar">
		<div class="bmcp-snip-filters">
			<a href="<?php echo esc_url( $list_url ); ?>"
			   class="bmcp-snip-filter <?php echo ! $filter_status && ! $filter_type ? 'active' : ''; ?>">All</a>
			<a href="<?php echo esc_url( add_query_arg( [ 'snip_status' => 'active',   'snip_type' => '' ], $list_url ) ); ?>"
			   class="bmcp-snip-filter <?php echo 'active' === $filter_status ? 'active' : ''; ?>">Active</a>
			<a href="<?php echo esc_url( add_query_arg( [ 'snip_status' => 'inactive', 'snip_type' => '' ], $list_url ) ); ?>"
			   class="bmcp-snip-filter <?php echo 'inactive' === $filter_status ? 'active' : ''; ?>">Inactive</a>
			<span class="bmcp-snip-filter-sep"></span>
			<?php foreach ( [ 'php' => 'PHP', 'javascript' => 'JS', 'css' => 'CSS', 'html' => 'HTML' ] as $t => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'snip_type' => $t, 'snip_status' => '' ], $list_url ) ); ?>"
			   class="bmcp-snip-filter <?php echo $filter_type === $t ? 'active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<form method="get" class="bmcp-snip-search" action="<?php echo esc_url( $list_url ); ?>">
			<input type="hidden" name="page" value="bricks-mcp-snippets">
			<input type="hidden" name="snip_type"   value="<?php echo esc_attr( $filter_type ); ?>">
			<input type="hidden" name="snip_status" value="<?php echo esc_attr( $filter_status ); ?>">
			<div class="bmcp-snip-search-inner">
				<svg class="bmcp-snip-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search snippets…" class="bmcp-snip-search-input">
			</div>
		</form>
	</div>

	<!-- ── SNIPPETS TABLE ───────────────────────────────────────────── -->
	<div class="bmcp-card bmcp-snip-table-wrap">
		<?php if ( empty( $snippets ) ) : ?>
			<div class="bmcp-snip-empty">
				<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" style="opacity:.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
				<p><?php echo $search || $filter_type || $filter_status ? 'No snippets match your filters.' : 'No snippets yet. <a href="' . esc_url( $new_url ) . '">Create your first snippet →</a>'; ?></p>
			</div>
		<?php else : ?>
		<form id="bmcp-snippets-form" method="post">
			<?php wp_nonce_field( 'bmcp_admin_nonce', '_bmcp_nonce' ); ?>
			<div class="bmcp-snip-bulk-bar" id="bmcp-bulk-bar" style="display:none;">
				<span class="bmcp-snip-bulk-count"><span id="bmcp-bulk-count">0</span> selected</span>
				<button type="button" class="bmcp-btn bmcp-btn--sm" data-bulk="activate">Activate</button>
				<button type="button" class="bmcp-btn bmcp-btn--sm" data-bulk="deactivate">Deactivate</button>
				<button type="button" class="bmcp-btn bmcp-btn--sm bmcp-btn--danger-ghost" data-bulk="delete">Delete</button>
				<button type="button" class="bmcp-btn bmcp-btn--sm" data-bulk="export">Export JSON</button>
			</div>
			<table class="bmcp-snip-table">
				<thead>
					<tr>
						<th class="col-check"><input type="checkbox" id="bmcp-select-all" aria-label="Select all"></th>
						<th class="col-status">Status</th>
						<th class="col-title">Snippet</th>
						<th class="col-type">Type</th>
						<th class="col-location">Location</th>
						<th class="col-hook">Hook</th>
						<th class="col-modified">Modified</th>
						<th class="col-actions"></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $snippets as $snip ) :
					$edit_url   = admin_url( 'admin.php?page=bricks-mcp-snippets&action=edit&snippet_id=' . $snip['id'] );
					$is_active  = 'active' === $snip['status'];
					[ $type_label, $type_class ] = bmcp_snip_type_info( $snip['type'] );
				?>
				<tr class="bmcp-snip-row <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>" data-id="<?php echo (int) $snip['id']; ?>">
					<td class="col-check">
						<input type="checkbox" name="snippet_ids[]" value="<?php echo (int) $snip['id']; ?>" class="bmcp-snip-check" aria-label="Select <?php echo esc_attr( $snip['title'] ); ?>">
					</td>
					<td class="col-status">
						<button type="button"
							class="bmcp-snip-toggle <?php echo $is_active ? 'is-on' : 'is-off'; ?>"
							data-id="<?php echo (int) $snip['id']; ?>"
							data-status="<?php echo esc_attr( $snip['status'] ); ?>"
							aria-label="<?php echo $is_active ? 'Deactivate' : 'Activate'; ?> snippet"
							title="<?php echo $is_active ? 'Active — click to deactivate' : 'Inactive — click to activate'; ?>">
							<span class="bmcp-snip-toggle-dot"></span>
						</button>
					</td>
					<td class="col-title">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="bmcp-snip-title">
							<?php echo esc_html( $snip['title'] ); ?>
						</a>
						<?php if ( ! empty( $snip['error'] ) ) : ?>
							<span class="bmcp-snip-error-badge">
								<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
								Auto-deactivated: <?php echo esc_html( wp_trim_words( $snip['error'], 10 ) ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $snip['description'] ) : ?>
							<span class="bmcp-snip-desc"><?php echo esc_html( wp_trim_words( $snip['description'], 12 ) ); ?></span>
						<?php endif; ?>
						<?php if ( $snip['tags'] ) : ?>
							<span class="bmcp-snip-tags">
							<?php foreach ( array_filter( array_map( 'trim', explode( ',', $snip['tags'] ) ) ) as $tag ) : ?>
								<span class="bmcp-snip-tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
							</span>
						<?php endif; ?>
					</td>
					<td class="col-type">
						<span class="bmcp-snip-badge <?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( $type_label ); ?></span>
					</td>
					<td class="col-location"><?php echo esc_html( ucfirst( $snip['location'] ) ); ?></td>
					<td class="col-hook"><code class="bmcp-snip-hook"><?php echo esc_html( $snip['hook'] ); ?></code></td>
					<td class="col-modified"><?php echo esc_html( human_time_diff( strtotime( $snip['modified'] ), time() ) . ' ago' ); ?></td>
					<td class="col-actions">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="bmcp-snip-action" title="Edit">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
						</a>
						<button type="button" class="bmcp-snip-action bmcp-snip-action--danger" data-delete="<?php echo (int) $snip['id']; ?>" title="Delete">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
						</button>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</form>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="bmcp-snip-pagination">
			<?php
			echo paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%', $list_url ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			] );
			?>
			<span class="bmcp-snip-pagination-info">
				<?php printf( '%d snippets', (int) $total ); ?>
			</span>
		</div>
		<?php endif; ?>
		<?php endif; ?>
	</div><!-- .bmcp-snip-table-wrap -->

	<?php if ( ! $safe_mode ) : ?>
	<p class="bmcp-snip-safemode-link">
		Seeing unexpected errors from a PHP snippet?
		<a href="<?php echo esc_url( $sm_enable_url ); ?>">Enable Safe Mode</a> to pause all PHP execution.
	</p>
	<?php endif; ?>

</div><!-- #bmcp-wrap -->
