<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$log        = get_option( BMCP_ACTIVITY_LOG_OPTION, [] );
$enabled    = get_option( BMCP_ENABLED_TOOLS_OPTION, [] );
$endpoint   = rest_url( BMCP_REST_NAMESPACE . '/mcp' );
$mem_total  = count( \BricksMCP\Memory_Manager::get_all() );
$mem_cats   = \BricksMCP\Memory_Manager::get_categories();
$api_key    = \BricksMCP\Auth::get_key();
$site_name  = get_bloginfo( 'name' );

// ── Config snippets ──────────────────────────────────────────────────
$cfg_standard = json_encode( [
	'mcpServers' => [
		'bricks-builder' => [
			'type'    => 'http',
			'url'     => $endpoint,
			'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
		],
	],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

$cfg_vscode = json_encode( [
	'servers' => [
		'bricks-builder' => [
			'type'    => 'http',
			'url'     => $endpoint,
			'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
		],
	],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

$cfg_gemini = json_encode( [
	'mcpServers' => [
		'bricks-builder' => [
			'httpTransport' => [
				'url'     => $endpoint,
				'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
			],
		],
	],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

$cfg_general =
"# Bricks Builder MCP — Universal Connection & Setup\n" .
"# ─────────────────────────────────────────────────────────────\n" .
"# Share this block with any AI client to connect and get started.\n\n" .
"ENDPOINT  ▶  {$endpoint}\n" .
"AUTH      ▶  Authorization: Bearer {$api_key}\n" .
"PROTOCOL  ▶  MCP 2024-11-05  ·  JSON-RPC 2.0  ·  Streamable HTTP\n\n" .
"══════════════════════════════════════════════════════════\n" .
"  WHAT THIS MCP DOES\n" .
"══════════════════════════════════════════════════════════\n\n" .
"You are connected to Bricks Builder MCP on \"{$site_name}\".\n" .
"This plugin gives you full programmatic control over a Bricks\n" .
"Builder WordPress website — no visual editor required.\n\n" .
"  Pages         create, read, update, delete pages + Bricks layouts\n" .
"  Templates     header / footer / section templates + conditions\n" .
"  Global Design color palette, global CSS classes, theme styles\n" .
"  Posts & CPTs  manage any WordPress post type\n" .
"  Media         list and upload media assets\n" .
"  WooCommerce   browse products & categories (read-only)\n" .
"  AI Memory     persistent site knowledge across all AI sessions\n\n" .
"══════════════════════════════════════════════════════════\n" .
"  FIRST STEPS — RUN THESE BEFORE BUILDING ANYTHING\n" .
"══════════════════════════════════════════════════════════\n\n" .
"  1  bricks_get_system_prompt    Bricks element format guide + site rules\n" .
"  2  bricks_get_site_info        WP/Bricks versions, active plugins, theme\n" .
"  3  bricks_memory_list            load all saved knowledge about this site\n" .
"  4  bricks_get_color_palette    brand colors and design tokens\n" .
"  5  bricks_get_global_classes   reusable CSS utility classes\n\n" .
"══════════════════════════════════════════════════════════\n" .
"  RULES\n" .
"══════════════════════════════════════════════════════════\n\n" .
"  • Always call bricks_get_system_prompt before building layouts\n" .
"  • Reuse existing colors and global classes — never hardcode values\n" .
"  • Save patterns, fixes, and preferences via bricks_memory_add\n" .
"  • Never access payment gateway settings or API credentials\n" .
"  • Follow the site-specific custom instructions in the system prompt\n";

$cfg_perplexity =
"# Bricks Builder MCP — Perplexity / Comet Setup\n\n" .
"In Comet browser: Settings → AI → MCP Servers → Add Server\n\n" .
"  Name    Bricks Builder MCP\n" .
"  Type    HTTP\n" .
"  URL     {$endpoint}\n" .
"  Header  Authorization: Bearer {$api_key}\n\n" .
"─── or paste this JSON if your client supports it ───\n\n" .
$cfg_standard;
?>
<div class="wrap" id="bmcp-wrap">

	<!-- ====================== PAGE HEADER ====================== -->
	<div class="bmcp-page-header">
		<div class="bmcp-header-left">
			<div class="bmcp-header-icon" aria-hidden="true">⚡</div>
			<div class="bmcp-header-text">
				<h1>Bricks Builder MCP <span class="bmcp-version-badge">v<?php echo esc_html( BMCP_VERSION ); ?></span></h1>
				<p>AI-powered Bricks Builder control via Model Context Protocol</p>
			</div>
		</div>
		<div class="bmcp-header-right">
			<div class="bmcp-status-pill" role="status">
				<span class="bmcp-status-dot" aria-hidden="true"></span>
				Active &amp; Connected
			</div>
		</div>
	</div>

	<!-- ====================== TABS ====================== -->
	<nav class="bmcp-tabs" role="tablist" aria-label="Plugin sections">
		<a href="#" class="bmcp-tab active" role="tab" aria-selected="true"  id="nav-tab-connection"   data-tab="connection"   aria-controls="tab-connection">
			<span class="bmcp-tab-icon" aria-hidden="true">⚡</span> Connection
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-instructions" data-tab="instructions" aria-controls="tab-instructions">
			<span class="bmcp-tab-icon" aria-hidden="true">✎</span> Instructions
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-capabilities" data-tab="capabilities" aria-controls="tab-capabilities">
			<span class="bmcp-tab-icon" aria-hidden="true">◈</span> Capabilities
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-memory"       data-tab="memory"       aria-controls="tab-memory">
			<span class="bmcp-tab-icon" aria-hidden="true">◎</span> Memory
			<?php if ( $mem_total > 0 ) : ?>
				<span class="bmcp-badge" aria-label="<?php echo esc_attr( $mem_total . ' memories' ); ?>"><?php echo $mem_total; ?></span>
			<?php endif; ?>
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-activity"     data-tab="activity"     aria-controls="tab-activity">
			<span class="bmcp-tab-icon" aria-hidden="true">◷</span> Activity
			<?php if ( ! empty( $log ) ) : ?>
				<span class="bmcp-badge" aria-label="<?php echo esc_attr( count( $log ) . ' log entries' ); ?>"><?php echo count( $log ); ?></span>
			<?php endif; ?>
		</a>
	</nav>

	<!-- ====================== CONNECTION TAB ====================== -->
	<div class="bmcp-panel" id="tab-connection" role="tabpanel" aria-labelledby="nav-tab-connection">

		<div class="bmcp-card">
			<h2>API Key</h2>
			<p>Use this key to authenticate any MCP client. Never share it publicly.</p>

			<div class="bmcp-key-row">
				<code class="bmcp-key-display" id="bmcp-key-masked"><?php echo esc_html( \BricksMCP\Auth::masked_key() ); ?></code>
				<button type="button" class="button" id="bmcp-btn-copy" aria-label="Copy API key to clipboard">⎘ Copy</button>
				<button type="button" class="button" id="bmcp-btn-regen" aria-label="Regenerate API key">↺ Regenerate</button>
			</div>
			<p class="description">Regenerating invalidates the old key immediately. Update your MCP config after regenerating.</p>
		</div>

		<div class="bmcp-card">
			<h2>Connect Your AI Client</h2>

			<div class="bmcp-setup-intro">
				<div class="bmcp-setup-intro-icon" aria-hidden="true">⚡</div>
				<div class="bmcp-setup-intro-body">
					<strong>What this MCP provides</strong>
					<p>Full programmatic control over your Bricks Builder WordPress site — create pages, edit templates, manage global design, handle media and posts, and maintain persistent AI memory across sessions.</p>
					<div class="bmcp-setup-intro-tools" aria-label="Available tool groups">
						<span class="bmcp-tool-chip">Pages</span>
						<span class="bmcp-tool-chip">Templates</span>
						<span class="bmcp-tool-chip">Global Design</span>
						<span class="bmcp-tool-chip">Posts &amp; CPTs</span>
						<span class="bmcp-tool-chip">Media</span>
						<span class="bmcp-tool-chip">WooCommerce</span>
						<span class="bmcp-tool-chip">AI Memory</span>
					</div>
				</div>
			</div>

			<nav class="bmcp-client-tabs" role="tablist" aria-label="Select AI client">
				<button class="bmcp-client-tab active" role="tab" aria-selected="true"  data-client="general"    id="client-tab-general"    aria-controls="bmcp-panel-general">General</button>
				<button class="bmcp-client-tab"        role="tab" aria-selected="false" data-client="claude"     id="client-tab-claude"     aria-controls="bmcp-panel-claude">Claude Code</button>
				<button class="bmcp-client-tab"        role="tab" aria-selected="false" data-client="vscode"     id="client-tab-vscode"     aria-controls="bmcp-panel-vscode">VS Code</button>
				<button class="bmcp-client-tab"        role="tab" aria-selected="false" data-client="cursor"     id="client-tab-cursor"     aria-controls="bmcp-panel-cursor">Cursor</button>
				<button class="bmcp-client-tab"        role="tab" aria-selected="false" data-client="trae"       id="client-tab-trae"       aria-controls="bmcp-panel-trae">Trae AI</button>
				<button class="bmcp-client-tab"        role="tab" aria-selected="false" data-client="gemini"     id="client-tab-gemini"     aria-controls="bmcp-panel-gemini">Gemini</button>
				<button class="bmcp-client-tab"        role="tab" aria-selected="false" data-client="perplexity" id="client-tab-perplexity" aria-controls="bmcp-panel-perplexity">Perplexity</button>
			</nav>

			<div class="bmcp-client-panel active" id="bmcp-panel-general" role="tabpanel" aria-labelledby="client-tab-general">
				<p class="bmcp-client-hint">Universal setup block — copy and share with any AI to connect and get started immediately.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-general"><?php echo esc_html( $cfg_general ); ?></pre>
					<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-general" aria-label="Copy general setup">⎘ Copy</button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-claude" role="tabpanel" aria-labelledby="client-tab-claude" style="display:none">
				<p class="bmcp-client-hint">Add to <code>~/.claude/settings.json</code> (global) or <code>.claude/settings.json</code> (per project). Run <code>claude mcp list</code> to verify.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-claude"><?php echo esc_html( $cfg_standard ); ?></pre>
					<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-claude" aria-label="Copy Claude Code config">⎘ Copy</button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-vscode" role="tabpanel" aria-labelledby="client-tab-vscode" style="display:none">
				<p class="bmcp-client-hint">Create <code>.vscode/mcp.json</code> in your project root. Requires GitHub Copilot (Agent mode) or the Continue extension.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-vscode"><?php echo esc_html( $cfg_vscode ); ?></pre>
					<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-vscode" aria-label="Copy VS Code config">⎘ Copy</button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-cursor" role="tabpanel" aria-labelledby="client-tab-cursor" style="display:none">
				<p class="bmcp-client-hint">Add to <code>~/.cursor/mcp.json</code> (global) or <code>.cursor/mcp.json</code> in the project root. Verify under <strong>Cursor → Settings → MCP</strong>.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-cursor"><?php echo esc_html( $cfg_standard ); ?></pre>
					<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-cursor" aria-label="Copy Cursor config">⎘ Copy</button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-trae" role="tabpanel" aria-labelledby="client-tab-trae" style="display:none">
				<p class="bmcp-client-hint">Open <strong>Trae AI → Settings → MCP Servers → Add Server</strong> and enter the URL and Bearer token, or paste the JSON below into <code>~/.trae/mcp.json</code>.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-trae"><?php echo esc_html( $cfg_standard ); ?></pre>
					<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-trae" aria-label="Copy Trae AI config">⎘ Copy</button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-gemini" role="tabpanel" aria-labelledby="client-tab-gemini" style="display:none">
				<p class="bmcp-client-hint">For <strong>Gemini CLI</strong>: add to <code>~/.gemini/settings.json</code>. For <strong>Gemini Code Assist</strong> (VS Code/JetBrains): use your editor's standard MCP HTTP config.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-gemini"><?php echo esc_html( $cfg_gemini ); ?></pre>
					<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-gemini" aria-label="Copy Gemini config">⎘ Copy</button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-perplexity" role="tabpanel" aria-labelledby="client-tab-perplexity" style="display:none">
				<p class="bmcp-client-hint">In <strong>Comet browser</strong>: <strong>Settings → AI → MCP Servers → Add Server</strong> and fill in the details below.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-perplexity"><?php echo esc_html( $cfg_perplexity ); ?></pre>
					<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-perplexity" aria-label="Copy Perplexity config">⎘ Copy</button>
				</div>
			</div>

		</div><!-- /AI Client Setup card -->

		<div class="bmcp-card">
			<h2>Endpoint URL</h2>
			<p>Direct MCP endpoint for manual HTTP clients or testing:</p>
			<div class="bmcp-key-row">
				<code class="bmcp-key-display" id="bmcp-endpoint"><?php echo esc_html( $endpoint ); ?></code>
				<button type="button" class="button bmcp-copy-config" data-target="bmcp-endpoint" aria-label="Copy endpoint URL">⎘ Copy URL</button>
			</div>
		</div>

	</div><!-- /tab-connection -->

	<!-- ====================== INSTRUCTIONS TAB ====================== -->
	<div class="bmcp-panel" id="tab-instructions" role="tabpanel" aria-labelledby="nav-tab-instructions" style="display:none">
		<form method="post" action="options.php">
			<?php settings_fields( 'bmcp_settings_instructions' ); ?>

			<div class="bmcp-card">
				<h2>Custom Instructions</h2>
				<p>Site-specific rules appended to the AI's built-in system prompt on every <code>bricks_get_system_prompt</code> call. Pre-filled with sensible defaults — edit freely to match your project.</p>

				<label for="bmcp-instructions" class="bmcp-sr-only">Custom AI instructions</label>
				<textarea
					name="<?php echo esc_attr( BMCP_INSTRUCTIONS_OPTION ); ?>"
					id="bmcp-instructions"
					rows="14"
					class="large-text"
					placeholder="Add site-specific context: brand colors, typography, tone, navigation items, business rules…"
				><?php echo esc_textarea( get_option( BMCP_INSTRUCTIONS_OPTION, '' ) ); ?></textarea>

				<div class="bmcp-submit-row">
					<?php submit_button( 'Save Instructions', 'primary', 'submit', false ); ?>
					<p class="description">Changes take effect on the AI's next call to <code>bricks_get_system_prompt</code>.</p>
				</div>
			</div>
		</form>
	</div><!-- /tab-instructions -->

	<!-- ====================== CAPABILITIES TAB ====================== -->
	<div class="bmcp-panel" id="tab-capabilities" role="tabpanel" aria-labelledby="nav-tab-capabilities" style="display:none">
		<form method="post" action="options.php">
			<?php settings_fields( 'bmcp_settings_capabilities' ); ?>

			<div class="bmcp-card">
				<h2>Tool Groups</h2>
				<p>Disable groups to restrict what the AI can access. The <strong>Site</strong> group (site info, element catalog, nav menus, components, system prompt, memory) is always on.</p>

				<?php
				$group_info = [
					'pages'       => [ 'label' => 'Pages',        'icon' => '⊞', 'tools' => 'list, get, create, update, delete pages',                                  'count' => 5 ],
					'templates'   => [ 'label' => 'Templates',     'icon' => '⊟', 'tools' => 'list, get, create, update, delete Bricks templates + template conditions', 'count' => 6 ],
					'settings'    => [ 'label' => 'Global Design', 'icon' => '◎', 'tools' => 'global settings, color palette, global classes, theme styles',             'count' => 9 ],
					'posts'       => [ 'label' => 'Posts & CPTs',  'icon' => '≡', 'tools' => 'list post types, list, get, create, update, delete any post type',         'count' => 6 ],
					'media'       => [ 'label' => 'Media Library', 'icon' => '⊕', 'tools' => 'list media, upload from URL, get media details',                           'count' => 3 ],
					'woocommerce' => [ 'label' => 'WooCommerce',   'icon' => '◈', 'tools' => 'list & get products, list product categories (requires WooCommerce)',      'count' => 3 ],
				];
				?>

				<table class="bmcp-capabilities-table">
					<thead>
						<tr>
							<th scope="col" style="width:160px">Group</th>
							<th scope="col">Tools Included</th>
							<th scope="col" style="width:70px;text-align:center">Enabled</th>
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
								<label class="bmcp-toggle">
									<input type="checkbox"
										name="<?php echo esc_attr( BMCP_ENABLED_TOOLS_OPTION . '[' . $key . ']' ); ?>"
										value="1"
										<?php checked( $is_enabled ); ?>
										aria-label="<?php echo esc_attr( 'Enable ' . $info['label'] . ' tools' ); ?>"
									/>
									<span class="bmcp-toggle-slider" aria-hidden="true"></span>
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
	<div class="bmcp-panel" id="tab-memory" role="tabpanel" aria-labelledby="nav-tab-memory" style="display:none">
		<div class="bmcp-card">
			<div class="bmcp-card-header">
				<h2>AI Memory <span class="description" style="font-weight:400;font-size:0.8rem;color:var(--text-dim)">up to 500 entries</span></h2>
				<button type="button" class="button button-primary" id="bmcp-btn-add-memory" aria-haspopup="dialog">+ Add Memory</button>
			</div>
			<p style="margin-bottom:16px">Memories are injected into every AI session automatically. Add site facts, design patterns, errors and fixes — the AI will also add and update memories on its own.</p>

			<div class="bmcp-memory-toolbar" role="search" aria-label="Filter memories">
				<label for="bmcp-mem-cat-filter" class="bmcp-sr-only">Filter by category</label>
				<select id="bmcp-mem-cat-filter" aria-label="Filter memories by category">
					<option value="">All Categories</option>
					<?php foreach ( $mem_cats as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="bmcp-mem-search" class="bmcp-sr-only">Search memories</label>
				<input type="search" id="bmcp-mem-search" placeholder="Search memories…" autocomplete="off">
			</div>

			<div id="bmcp-memory-list" aria-live="polite"><p class="bmcp-empty">Loading…</p></div>
			<nav id="bmcp-memory-pagination" aria-label="Memory pagination"></nav>
		</div>
	</div><!-- /tab-memory -->

	<!-- ====================== MEMORY MODAL ====================== -->
	<div id="bmcp-memory-modal" class="bmcp-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="bmcp-modal-title">
		<div class="bmcp-modal-backdrop"></div>
		<div class="bmcp-modal-inner">
			<div class="bmcp-modal-header">
				<h3 id="bmcp-modal-title">Add Memory</h3>
				<button type="button" class="bmcp-modal-close" aria-label="Close dialog">✕</button>
			</div>
			<div class="bmcp-modal-body">
				<input type="hidden" id="bmcp-mem-id">

				<div class="bmcp-field-row">
					<div class="bmcp-field">
						<label for="bmcp-mem-cat">Category</label>
						<select id="bmcp-mem-cat">
							<?php foreach ( $mem_cats as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="bmcp-field">
						<label for="bmcp-mem-importance">Importance</label>
						<select id="bmcp-mem-importance">
							<option value="high">High — always in system prompt</option>
							<option value="medium" selected>Medium</option>
							<option value="low">Low</option>
						</select>
					</div>
				</div>

				<div class="bmcp-field">
					<label for="bmcp-mem-title">Title</label>
					<input type="text" id="bmcp-mem-title" placeholder="Short descriptive title" autocomplete="off">
				</div>

				<div class="bmcp-field">
					<label for="bmcp-mem-content">Content</label>
					<textarea id="bmcp-mem-content" rows="6" placeholder="Full memory content — include enough detail to be useful without additional context."></textarea>
				</div>

				<div class="bmcp-field">
					<label for="bmcp-mem-tags">Tags <span class="description">comma separated</span></label>
					<input type="text" id="bmcp-mem-tags" placeholder="e.g. hero, layout, bricks" autocomplete="off">
				</div>
			</div>
			<div class="bmcp-modal-footer">
				<button type="button" class="button bmcp-modal-cancel">Cancel</button>
				<button type="button" class="button button-primary" id="bmcp-modal-save">Save Memory</button>
			</div>
		</div>
	</div>

	<!-- ====================== ACTIVITY LOG TAB ====================== -->
	<div class="bmcp-panel" id="tab-activity" role="tabpanel" aria-labelledby="nav-tab-activity" style="display:none">
		<div class="bmcp-card">
			<div class="bmcp-card-header">
				<h2>Activity Log <span class="description" style="font-weight:400;font-size:0.8rem;color:var(--text-dim)">last 20 requests</span></h2>
				<button type="button" class="button" id="bmcp-btn-clear-log" aria-label="Clear activity log">✕ Clear Log</button>
			</div>

			<?php if ( empty( $log ) ) : ?>
				<p class="bmcp-empty">No MCP requests logged yet.<br>Connect your client and make a tool call to see activity here.</p>
			<?php else : ?>
				<table class="bmcp-log-table" aria-label="Activity log">
					<thead>
						<tr>
							<th scope="col">Time</th>
							<th scope="col">Tool</th>
							<th scope="col">Status</th>
							<th scope="col">Error</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td style="color:var(--text-muted);font-size:0.8rem;white-space:nowrap">
								<time datetime="<?php echo esc_attr( date( 'c', $entry['timestamp'] ) ); ?>">
									<?php echo esc_html( date( 'M j H:i:s', $entry['timestamp'] ) ); ?>
								</time>
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
	<footer class="bmcp-footer" role="contentinfo">
		<div class="bmcp-footer-bar" aria-hidden="true"></div>
		<div class="bmcp-footer-inner">
			<div class="bmcp-footer-brand">
				<div class="bmcp-footer-logo" aria-hidden="true">⚡</div>
				<div>
					<div class="bmcp-footer-name">
						Bricks Builder MCP
						<span class="bmcp-footer-ver">v<?php echo esc_html( BMCP_VERSION ); ?></span>
					</div>
					<div class="bmcp-footer-tagline">AI-powered Bricks control via Model Context Protocol</div>
				</div>
			</div>
			<div class="bmcp-footer-meta">
				<span>Open source</span>
				<span class="bmcp-footer-dot" aria-hidden="true">·</span>
				<span>GPL-2.0-or-later</span>
			</div>
		</div>
	</footer>

</div><!-- /#bmcp-wrap -->
