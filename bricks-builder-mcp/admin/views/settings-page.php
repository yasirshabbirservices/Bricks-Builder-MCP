<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$log        = get_option( BMCP_ACTIVITY_LOG_OPTION, [] );
$enabled    = get_option( BMCP_ENABLED_TOOLS_OPTION, [] );
$endpoint   = rest_url( BMCP_REST_NAMESPACE . '/mcp' );
$mem_total  = count( \BricksMCP\Memory_Manager::get_all() );
$mem_cats   = \BricksMCP\Memory_Manager::get_categories();
?>
<div class="wrap" id="bmcp-wrap">

	<!-- ====================== PAGE HEADER ====================== -->
	<div class="bmcp-page-header">
		<div class="bmcp-header-left">
			<div class="bmcp-header-icon">⚡</div>
			<div class="bmcp-header-text">
				<h1>Bricks Builder MCP <span class="bmcp-version-badge">v<?php echo esc_html( BMCP_VERSION ); ?></span></h1>
				<p>AI-powered Bricks Builder control via Model Context Protocol</p>
			</div>
		</div>
		<div class="bmcp-header-right">
			<div class="bmcp-status-pill">
				<span class="bmcp-status-dot"></span>
				Active &amp; Connected
			</div>
		</div>
	</div>

	<!-- ====================== TABS ====================== -->
	<nav class="bmcp-tabs" role="tablist">
		<a href="#" class="bmcp-tab active" data-tab="connection">
			<span class="bmcp-tab-icon">⚡</span> Connection
		</a>
		<a href="#" class="bmcp-tab" data-tab="instructions">
			<span class="bmcp-tab-icon">✎</span> Custom Instructions
		</a>
		<a href="#" class="bmcp-tab" data-tab="capabilities">
			<span class="bmcp-tab-icon">◈</span> Capabilities
		</a>
		<a href="#" class="bmcp-tab" data-tab="memory">
			<span class="bmcp-tab-icon">◈</span> Memory
			<?php if ( $mem_total > 0 ) : ?>
				<span class="bmcp-badge"><?php echo $mem_total; ?></span>
			<?php endif; ?>
		</a>
		<a href="#" class="bmcp-tab" data-tab="activity">
			<span class="bmcp-tab-icon">◷</span> Activity
			<?php if ( ! empty( $log ) ) : ?>
				<span class="bmcp-badge"><?php echo count( $log ); ?></span>
			<?php endif; ?>
		</a>
	</nav>

	<!-- ====================== CONNECTION TAB ====================== -->
	<div class="bmcp-panel" id="tab-connection">

		<div class="bmcp-card">
			<h2>API Key</h2>
			<p>Use this key to authenticate Claude Code (or any MCP client). Never share it publicly.</p>

			<div class="bmcp-key-row">
				<code class="bmcp-key-display" id="bmcp-key-masked"><?php echo esc_html( \BricksMCP\Auth::masked_key() ); ?></code>
				<button type="button" class="button" id="bmcp-btn-copy">⎘ Copy</button>
				<button type="button" class="button" id="bmcp-btn-regen">↺ Regenerate</button>
			</div>
			<p class="description">Regenerating invalidates the old key immediately. Update your MCP config after regenerating.</p>
		</div>

		<div class="bmcp-card">
			<h2>Claude Code Config</h2>
			<p>Add this block to <code>~/.claude/settings.json</code> (global) or <code>.claude/settings.json</code> (per project):</p>
			<div class="bmcp-config-block">
				<pre id="bmcp-config-snippet"><?php
					echo esc_html( json_encode( [
						'mcpServers' => [
							'bricks-builder' => [
								'type'    => 'http',
								'url'     => $endpoint,
								'headers' => [ 'Authorization' => 'Bearer ' . \BricksMCP\Auth::get_key() ],
							],
						],
					], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				?></pre>
				<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-snippet">⎘ Copy Config</button>
			</div>
			<p class="description">The Bearer token above reflects your current API key. It updates automatically when you regenerate. Run <code>claude mcp list</code> to verify the connection.</p>
		</div>

		<div class="bmcp-card">
			<h2>Endpoint URL</h2>
			<p>Direct MCP endpoint for manual HTTP clients or testing:</p>
			<div class="bmcp-key-row">
				<code class="bmcp-key-display" id="bmcp-endpoint"><?php echo esc_html( $endpoint ); ?></code>
				<button type="button" class="button bmcp-copy-config" data-target="bmcp-endpoint">⎘ Copy URL</button>
			</div>
		</div>

	</div><!-- /tab-connection -->

	<!-- ====================== INSTRUCTIONS TAB ====================== -->
	<div class="bmcp-panel" id="tab-instructions" style="display:none">
		<form method="post" action="options.php">
			<?php settings_fields( 'bmcp_settings_instructions' ); ?>

			<div class="bmcp-card">
				<h2>Custom Instructions</h2>
				<p>Add context about your business, brand, and preferences. This text is appended to the built-in system prompt every time the AI calls <code>bricks_get_system_prompt</code>.</p>

				<textarea
					name="<?php echo esc_attr( BMCP_INSTRUCTIONS_OPTION ); ?>"
					id="bmcp-instructions"
					rows="14"
					class="large-text"
					placeholder="Examples:&#10;— Site name: Yasir Shabbir Portfolio. Brand color: #00d68f. Secondary: #ffffff.&#10;— Typography: Inter for body, Syne for headings.&#10;— Always include a strong CTA button in hero sections.&#10;— Tone: professional, direct, no filler text.&#10;— Navigation items: Home, About, Services, Process, Portfolio, Resources, Blog, Contact.&#10;— Never use lorem ipsum — always write realistic placeholder content."
				><?php echo esc_textarea( get_option( BMCP_INSTRUCTIONS_OPTION, '' ) ); ?></textarea>

				<div class="bmcp-submit-row">
					<?php submit_button( 'Save Instructions', 'primary', 'submit', false ); ?>
					<p class="description">Changes take effect on the AI's next call to <code>bricks_get_system_prompt</code>.</p>
				</div>
			</div>
		</form>
	</div><!-- /tab-instructions -->

	<!-- ====================== CAPABILITIES TAB ====================== -->
	<div class="bmcp-panel" id="tab-capabilities" style="display:none">
		<form method="post" action="options.php">
			<?php settings_fields( 'bmcp_settings_capabilities' ); ?>

			<div class="bmcp-card">
				<h2>Tool Groups</h2>
				<p>Disable groups to restrict what the AI can access. The <strong>Site</strong> group (site info, element catalog, nav menus, components, system prompt) is always on.</p>

				<?php
				$group_info = [
					'pages'       => [
						'label' => 'Pages',
						'icon'  => '⊞',
						'tools' => 'list, get, create, update, delete pages',
						'count' => 5,
					],
					'templates'   => [
						'label' => 'Templates',
						'icon'  => '⊟',
						'tools' => 'list, get, create, update, delete Bricks templates + template conditions',
						'count' => 6,
					],
					'settings'    => [
						'label' => 'Global Design',
						'icon'  => '◎',
						'tools' => 'global settings, color palette, global classes, theme styles',
						'count' => 9,
					],
					'posts'       => [
						'label' => 'Posts & CPTs',
						'icon'  => '≡',
						'tools' => 'list post types, list, get, create, update, delete any post type',
						'count' => 6,
					],
					'media'       => [
						'label' => 'Media Library',
						'icon'  => '⊕',
						'tools' => 'list media, upload from URL, get media details',
						'count' => 3,
					],
					'woocommerce' => [
						'label' => 'WooCommerce',
						'icon'  => '◈',
						'tools' => 'list & get products, list product categories (requires WooCommerce)',
						'count' => 3,
					],
				];
				?>

				<table class="bmcp-capabilities-table">
					<thead>
						<tr>
							<th style="width:160px">Group</th>
							<th>Tools Included</th>
							<th style="width:70px;text-align:center">Enabled</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $group_info as $key => $info ) :
							$is_enabled = $enabled[ $key ] ?? true;
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $info['icon'] . ' ' . $info['label'] ); ?></strong>
								<br><span class="bmcp-tool-list"><?php echo esc_html( $info['count'] ); ?> tools</span>
							</td>
							<td class="bmcp-tool-list"><?php echo esc_html( $info['tools'] ); ?></td>
							<td style="text-align:center">
								<label class="bmcp-toggle" title="<?php echo $is_enabled ? 'Enabled — click to disable' : 'Disabled — click to enable'; ?>">
									<input type="checkbox"
										name="<?php echo esc_attr( BMCP_ENABLED_TOOLS_OPTION . '[' . $key . ']' ); ?>"
										value="1"
										<?php checked( $is_enabled ); ?>
									/>
									<span class="bmcp-toggle-slider"></span>
								</label>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="bmcp-submit-row">
					<?php submit_button( 'Save Capabilities', 'primary', 'submit', false ); ?>
				</div>
			</div>
		</form>
	</div><!-- /tab-capabilities -->

	<!-- ====================== MEMORY TAB ====================== -->
	<div class="bmcp-panel" id="tab-memory" style="display:none">
		<div class="bmcp-card">
			<div class="bmcp-card-header">
				<h2>AI Memory <span class="description" style="font-weight:400;font-size:0.8rem;color:var(--text-dim)">up to 500 entries</span></h2>
				<button type="button" class="button button-primary" id="bmcp-btn-add-memory">+ Add Memory</button>
			</div>
			<p style="margin-bottom:16px">Memories are included in every AI session automatically. Add site facts, design patterns, errors and fixes — the AI will also add and update memories on its own.</p>

			<div class="bmcp-memory-toolbar">
				<select id="bmcp-mem-cat-filter">
					<option value="">All Categories</option>
					<?php foreach ( $mem_cats as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" id="bmcp-mem-search" placeholder="Search memories…" autocomplete="off">
			</div>

			<div id="bmcp-memory-list"><p class="bmcp-empty">Loading…</p></div>
			<div id="bmcp-memory-pagination"></div>
		</div>
	</div><!-- /tab-memory -->

	<!-- ====================== MEMORY MODAL ====================== -->
	<div id="bmcp-memory-modal" class="bmcp-modal" style="display:none">
		<div class="bmcp-modal-backdrop"></div>
		<div class="bmcp-modal-inner">
			<div class="bmcp-modal-header">
				<h3 id="bmcp-modal-title">Add Memory</h3>
				<button type="button" class="bmcp-modal-close">✕</button>
			</div>
			<div class="bmcp-modal-body">
				<input type="hidden" id="bmcp-mem-id">

				<div class="bmcp-field-row">
					<div class="bmcp-field">
						<label>Category</label>
						<select id="bmcp-mem-cat">
							<?php foreach ( $mem_cats as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="bmcp-field">
						<label>Importance</label>
						<select id="bmcp-mem-importance">
							<option value="high">High — always in system prompt</option>
							<option value="medium" selected>Medium</option>
							<option value="low">Low</option>
						</select>
					</div>
				</div>

				<div class="bmcp-field">
					<label>Title</label>
					<input type="text" id="bmcp-mem-title" placeholder="Short descriptive title">
				</div>

				<div class="bmcp-field">
					<label>Content</label>
					<textarea id="bmcp-mem-content" rows="6" placeholder="Full memory content — include enough detail to be useful without additional context."></textarea>
				</div>

				<div class="bmcp-field">
					<label>Tags <span class="description">comma separated</span></label>
					<input type="text" id="bmcp-mem-tags" placeholder="e.g. hero, layout, bricks">
				</div>
			</div>
			<div class="bmcp-modal-footer">
				<button type="button" class="button bmcp-modal-cancel">Cancel</button>
				<button type="button" class="button button-primary" id="bmcp-modal-save">Save Memory</button>
			</div>
		</div>
	</div>

	<!-- ====================== ACTIVITY LOG TAB ====================== -->
	<div class="bmcp-panel" id="tab-activity" style="display:none">
		<div class="bmcp-card">
			<div class="bmcp-card-header">
				<h2>Activity Log <span class="description" style="font-weight:400;font-size:0.8rem;color:var(--text-dim)">last 20 requests</span></h2>
				<button type="button" class="button" id="bmcp-btn-clear-log">✕ Clear Log</button>
			</div>

			<?php if ( empty( $log ) ) : ?>
				<p class="bmcp-empty">No MCP requests logged yet.<br>Connect your client and make a tool call to see activity here.</p>
			<?php else : ?>
				<table class="bmcp-log-table">
					<thead>
						<tr>
							<th>Time</th>
							<th>Tool</th>
							<th>Status</th>
							<th>Error</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td style="color:var(--text-muted);font-size:0.8rem;white-space:nowrap">
								<?php echo esc_html( date( 'M j H:i:s', $entry['timestamp'] ) ); ?>
							</td>
							<td><code><?php echo esc_html( $entry['tool'] ); ?></code></td>
							<td>
								<?php if ( $entry['success'] ) : ?>
									<span class="bmcp-status ok">✓ OK</span>
								<?php else : ?>
									<span class="bmcp-status fail">✗ FAIL</span>
								<?php endif; ?>
							</td>
							<td class="bmcp-error-cell"><?php echo esc_html( $entry['error'] ?? '—' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div><!-- /tab-activity -->

	<!-- ====================== FOOTER ====================== -->
	<div class="bmcp-footer">
		<div class="bmcp-footer-left">
			<div class="bmcp-footer-avatar">YS</div>
			<div class="bmcp-footer-info">
				<strong>Yasir Shabbir</strong>
				<span>Full-Stack Developer</span>
			</div>
		</div>
		<div class="bmcp-footer-links">
			<a href="https://yasirshabbir.com" target="_blank" rel="noopener">↗ yasirshabbir.com</a>
		</div>
	</div>

</div><!-- /#bmcp-wrap -->
