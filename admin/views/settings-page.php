<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$log        = get_option( BMCP_ACTIVITY_LOG_OPTION, [] );
$endpoint   = rest_url( BMCP_REST_NAMESPACE . '/mcp' );
$mem_total  = count( \BricksMCP\Memory_Manager::get_all() );
$mem_cats   = \BricksMCP\Memory_Manager::get_categories();

global $wpdb;
$hist_total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . \BricksMCP\History_Manager::table() );
$api_key    = \BricksMCP\Auth::get_key();
$site_name  = get_bloginfo( 'name' );

// ── Inline SVG icons (static, not user input — no escaping needed) ────
$svg_bolt     = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>';
$svg_lines    = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>';
$svg_grid     = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>';
$svg_bookmark = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
$svg_clock    = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
$svg_pulse    = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
$svg_gear     = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
$svg_plus     = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
$svg_trash    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>';
$svg_close    = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
$svg_search   = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
$svg_building = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>';

// Connection status — "Connected" only if an AI called a tool in the last 30 min
$last_seen       = (int) get_option( 'bmcp_last_seen', 0 );
$is_connected    = $last_seen > 0 && ( time() - $last_seen ) < 1800;
$status_label    = $is_connected ? 'Active &amp; Connected' : 'Active';
$status_modifier = $is_connected ? ' bmcp-status-connected' : ' bmcp-status-idle';

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
"# Bricks Builder MCP\n" .
"# Site: {$site_name}\n\n" .
"ENDPOINT  ▶  {$endpoint}\n" .
"AUTH      ▶  Authorization: Bearer {$api_key}\n" .
"PROTOCOL  ▶  MCP 2024-11-05 · JSON-RPC 2.0 · Streamable HTTP\n\n" .
"══════════════════════════════════════════════════════════\n" .
"  CAPABILITIES\n" .
"══════════════════════════════════════════════════════════\n\n" .
"You have full programmatic control over this Bricks Builder\n" .
"WordPress site. Available tool groups:\n\n" .
"  Pages          create, read, update, delete + Bricks layouts\n" .
"  Templates      header / footer / section templates + conditions\n" .
"  Global Design  color palette, CSS classes, theme styles, CSS vars\n" .
"  Posts & CPTs   any WordPress post type\n" .
"  Media          browse and import assets\n" .
"  Nav Menus      create and manage navigation menus\n" .
"  Components     Bricks reusable components\n" .
"  Search         find and replace across all pages and templates\n" .
"  SEO            meta title, description, OG data (Yoast / Rank Math)\n" .
"  Cache          clear site cache after writes\n" .
"  WooCommerce    browse products and categories (read-only)\n" .
"  AI Memory      persistent site knowledge across sessions\n" .
"  History        auto-snapshot before every write — restore anytime\n\n" .
"══════════════════════════════════════════════════════════\n" .
"  START OF SESSION\n" .
"══════════════════════════════════════════════════════════\n\n" .
"Run this single call first — it loads site info, color palette,\n" .
"global classes, CSS variables, design framework, and memories\n" .
"in one request:\n\n" .
"  bricks_get_session_context\n\n" .
"Then load the full element format guide and site rules:\n\n" .
"  bricks_get_system_prompt\n\n" .
"Before writing any elements, always validate first:\n\n" .
"  bricks_validate_payload  { \"elements\": [...] }\n\n" .
"══════════════════════════════════════════════════════════\n" .
"  RULES\n" .
"══════════════════════════════════════════════════════════\n\n" .
"  • Use bricks_get_session_context at the start of every session\n" .
"  • Always validate element arrays before writing\n" .
"  • Use framework.semantic_map for CSS variable names — never guess\n" .
"  • Reuse existing colors and global classes — never hardcode values\n" .
"  • Save useful patterns and preferences via bricks_memory_add\n" .
"  • Never access payment gateway settings or API credentials\n" .
"  • Follow the site-specific rules returned by bricks_get_system_prompt\n";

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

	<!-- ====================== BRANDED NOTICES ====================== -->
	<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
	<div class="bmcp-notice bmcp-notice-success" role="alert" id="bmcp-settings-notice">
		<span class="bmcp-notice-icon" aria-hidden="true">✓</span>
		<span class="bmcp-notice-text">Settings saved.</span>
		<button type="button" class="bmcp-notice-close" aria-label="Dismiss notice">✕</button>
	</div>
	<?php endif; ?>

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
			<div class="bmcp-status-pill<?php echo $status_modifier; ?>" role="status">
				<span class="bmcp-status-dot" aria-hidden="true"></span>
				<?php echo $status_label; ?>
				<?php if ( $is_connected ) : ?>
					<span class="bmcp-status-age"><?php echo esc_html( human_time_diff( $last_seen ) ); ?> ago</span>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- ====================== TABS ====================== -->
	<nav class="bmcp-tabs" role="tablist" aria-label="Plugin sections">
		<a href="#" class="bmcp-tab active" role="tab" aria-selected="true"  id="nav-tab-connection"   data-tab="connection"   aria-controls="tab-connection">
			<span class="bmcp-tab-icon"><?php echo $svg_bolt; ?></span> Connection
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-instructions" data-tab="instructions" aria-controls="tab-instructions">
			<span class="bmcp-tab-icon"><?php echo $svg_lines; ?></span> Instructions
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-business-profile" data-tab="business-profile" aria-controls="tab-business-profile">
			<span class="bmcp-tab-icon"><?php echo $svg_building; ?></span> Business Profile
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-capabilities" data-tab="capabilities" aria-controls="tab-capabilities">
			<span class="bmcp-tab-icon"><?php echo $svg_grid; ?></span> Capabilities
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-memory"       data-tab="memory"       aria-controls="tab-memory">
			<span class="bmcp-tab-icon"><?php echo $svg_bookmark; ?></span> Memory
			<?php if ( $mem_total > 0 ) : ?>
				<span class="bmcp-badge" aria-label="<?php echo esc_attr( $mem_total . ' memories' ); ?>"><?php echo $mem_total; ?></span>
			<?php endif; ?>
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-history"      data-tab="history"      aria-controls="tab-history">
			<span class="bmcp-tab-icon"><?php echo $svg_clock; ?></span> History
			<?php if ( $hist_total > 0 ) : ?>
				<span class="bmcp-badge" aria-label="<?php echo esc_attr( $hist_total . ' snapshots' ); ?>"><?php echo $hist_total; ?></span>
			<?php endif; ?>
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-activity"     data-tab="activity"     aria-controls="tab-activity">
			<span class="bmcp-tab-icon"><?php echo $svg_pulse; ?></span> Activity
			<?php if ( ! empty( $log ) ) : ?>
				<span class="bmcp-badge" aria-label="<?php echo esc_attr( count( $log ) . ' log entries' ); ?>"><?php echo count( $log ); ?></span>
			<?php endif; ?>
		</a>
		<a href="#" class="bmcp-tab"        role="tab" aria-selected="false" id="nav-tab-advanced"     data-tab="advanced"     aria-controls="tab-advanced">
			<span class="bmcp-tab-icon"><?php echo $svg_gear; ?></span> Advanced
		</a>
	</nav>

	<!-- ====================== CONNECTION TAB ====================== -->
	<div class="bmcp-panel" id="tab-connection" role="tabpanel" aria-labelledby="nav-tab-connection">

		<div class="bmcp-card">
			<h2>API Key &amp; Endpoint</h2>
			<p>Authenticate MCP clients with the key below. Never share it publicly.</p>

			<div class="bmcp-api-grid">
				<div class="bmcp-api-grid-item">
					<div class="bmcp-api-grid-label">API Key</div>
					<div class="bmcp-key-row">
						<code class="bmcp-key-display" id="bmcp-key-masked"><?php echo esc_html( \BricksMCP\Auth::masked_key() ); ?></code>
						<button type="button" class="button bmcp-icon-btn" id="bmcp-btn-copy" title="<?php esc_attr_e( 'Copy API key', 'bricks-builder-mcp' ); ?>" aria-label="<?php esc_attr_e( 'Copy API key', 'bricks-builder-mcp' ); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
						<button type="button" class="button bmcp-icon-btn" id="bmcp-btn-regen" title="<?php esc_attr_e( 'Regenerate API key', 'bricks-builder-mcp' ); ?>" aria-label="<?php esc_attr_e( 'Regenerate API key', 'bricks-builder-mcp' ); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>
					</div>
				</div>
				<div class="bmcp-api-grid-item">
					<div class="bmcp-api-grid-label">MCP Endpoint</div>
					<div class="bmcp-key-row">
						<code class="bmcp-key-display" id="bmcp-endpoint"><?php echo esc_html( $endpoint ); ?></code>
						<button type="button" class="button bmcp-icon-btn bmcp-copy-config" data-target="bmcp-endpoint" title="<?php esc_attr_e( 'Copy endpoint URL', 'bricks-builder-mcp' ); ?>" aria-label="<?php esc_attr_e( 'Copy endpoint URL', 'bricks-builder-mcp' ); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
					</div>
				</div>
			</div>
			<p class="description" style="margin-top:10px">Regenerating the key invalidates your current config — update all MCP clients after regenerating.</p>
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
				<p class="bmcp-client-hint">Connection details and session instructions — paste this into any AI client to get started.</p>
				<div class="bmcp-config-block bmcp-config-collapsible" id="bmcp-general-collapse-wrap">
					<pre id="bmcp-config-general"><?php echo esc_html( $cfg_general ); ?></pre>
					<button type="button" class="button bmcp-icon-btn bmcp-copy-config" title="Copy" data-target="bmcp-config-general" aria-label="Copy general setup"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
				</div>
				<button type="button" class="bmcp-collapse-toggle" id="bmcp-general-toggle" aria-expanded="false" aria-controls="bmcp-general-collapse-wrap">Show full guide ↓</button>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-claude" role="tabpanel" aria-labelledby="client-tab-claude" style="display:none">
				<p class="bmcp-client-hint">Add to <code>~/.claude/settings.json</code> (global) or <code>.claude/settings.json</code> (per project). Run <code>claude mcp list</code> to verify.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-claude"><?php echo esc_html( $cfg_standard ); ?></pre>
					<button type="button" class="button bmcp-icon-btn bmcp-copy-config" title="Copy" data-target="bmcp-config-claude" aria-label="Copy Claude Code config"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-vscode" role="tabpanel" aria-labelledby="client-tab-vscode" style="display:none">
				<p class="bmcp-client-hint">Create <code>.vscode/mcp.json</code> in your project root. Requires GitHub Copilot (Agent mode) or the Continue extension.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-vscode"><?php echo esc_html( $cfg_vscode ); ?></pre>
					<button type="button" class="button bmcp-icon-btn bmcp-copy-config" title="Copy" data-target="bmcp-config-vscode" aria-label="Copy VS Code config"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-cursor" role="tabpanel" aria-labelledby="client-tab-cursor" style="display:none">
				<p class="bmcp-client-hint">Add to <code>~/.cursor/mcp.json</code> (global) or <code>.cursor/mcp.json</code> in the project root. Verify under <strong>Cursor → Settings → MCP</strong>.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-cursor"><?php echo esc_html( $cfg_standard ); ?></pre>
					<button type="button" class="button bmcp-icon-btn bmcp-copy-config" title="Copy" data-target="bmcp-config-cursor" aria-label="Copy Cursor config"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-trae" role="tabpanel" aria-labelledby="client-tab-trae" style="display:none">
				<p class="bmcp-client-hint">Open <strong>Trae AI → Settings → MCP Servers → Add Server</strong> and enter the URL and Bearer token, or paste the JSON below into <code>~/.trae/mcp.json</code>.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-trae"><?php echo esc_html( $cfg_standard ); ?></pre>
					<button type="button" class="button bmcp-icon-btn bmcp-copy-config" title="Copy" data-target="bmcp-config-trae" aria-label="Copy Trae AI config"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-gemini" role="tabpanel" aria-labelledby="client-tab-gemini" style="display:none">
				<p class="bmcp-client-hint">For <strong>Gemini CLI</strong>: add to <code>~/.gemini/settings.json</code>. For <strong>Gemini Code Assist</strong> (VS Code/JetBrains): use your editor's standard MCP HTTP config.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-gemini"><?php echo esc_html( $cfg_gemini ); ?></pre>
					<button type="button" class="button bmcp-icon-btn bmcp-copy-config" title="Copy" data-target="bmcp-config-gemini" aria-label="Copy Gemini config"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
				</div>
			</div>

			<div class="bmcp-client-panel" id="bmcp-panel-perplexity" role="tabpanel" aria-labelledby="client-tab-perplexity" style="display:none">
				<p class="bmcp-client-hint">In <strong>Comet browser</strong>: <strong>Settings → AI → MCP Servers → Add Server</strong> and fill in the details below.</p>
				<div class="bmcp-config-block">
					<pre id="bmcp-config-perplexity"><?php echo esc_html( $cfg_perplexity ); ?></pre>
					<button type="button" class="button bmcp-icon-btn bmcp-copy-config" title="Copy" data-target="bmcp-config-perplexity" aria-label="Copy Perplexity config"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
				</div>
			</div>

		</div><!-- /AI Client Setup card -->

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

	<!-- ====================== BUSINESS PROFILE TAB ====================== -->
	<div class="bmcp-panel" id="tab-business-profile" role="tabpanel" aria-labelledby="nav-tab-business-profile" style="display:none">
		<form method="post" action="options.php">
			<?php settings_fields( 'bmcp_settings_business_profile' ); ?>
			<?php $bp = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] ); ?>

			<!-- Brand Identity -->
			<div class="bmcp-card">
				<h2>Brand Identity</h2>
				<p>Context the AI uses to understand your project — brand, contact details, services, and assets — so it can make accurate decisions without asking you every time.</p>
				<div class="bmcp-bp-grid">
					<div class="bmcp-field">
						<label for="bmcp-bp-business-name">Business Name</label>
						<input type="text" id="bmcp-bp-business-name" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[business_name]" value="<?php echo esc_attr( $bp['business_name'] ?? '' ); ?>" placeholder="Acme Studio" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-tagline">Tagline / Slogan</label>
						<input type="text" id="bmcp-bp-tagline" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[tagline]" value="<?php echo esc_attr( $bp['tagline'] ?? '' ); ?>" placeholder="We build things people love" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-business-type">Business Type</label>
						<input type="text" id="bmcp-bp-business-type" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[business_type]" value="<?php echo esc_attr( $bp['business_type'] ?? '' ); ?>" placeholder="e.g. SaaS, Agency, Restaurant, E-commerce" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-target-audience">Target Audience</label>
						<input type="text" id="bmcp-bp-target-audience" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[target_audience]" value="<?php echo esc_attr( $bp['target_audience'] ?? '' ); ?>" placeholder="e.g. B2B SaaS founders, local homeowners" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-tone">Tone of Voice</label>
						<input type="text" id="bmcp-bp-tone" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[tone_of_voice]" value="<?php echo esc_attr( $bp['tone_of_voice'] ?? '' ); ?>" placeholder="e.g. Professional, Friendly, Bold, Minimal" />
					</div>
					<div class="bmcp-field bmcp-field--full">
						<label for="bmcp-bp-about">About / Description <span class="description">1–3 sentences — replaces Lorem ipsum in templates</span></label>
						<textarea id="bmcp-bp-about" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[about_text]" rows="3"><?php echo esc_textarea( $bp['about_text'] ?? '' ); ?></textarea>
					</div>
				</div>
			</div>

			<!-- Contact -->
			<div class="bmcp-card">
				<h2>Contact Information</h2>
				<div class="bmcp-bp-grid">
					<div class="bmcp-field">
						<label for="bmcp-bp-email">Email Address</label>
						<input type="email" id="bmcp-bp-email" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[email]" value="<?php echo esc_attr( $bp['email'] ?? '' ); ?>" placeholder="hello@example.com" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-phone">Phone Number</label>
						<input type="text" id="bmcp-bp-phone" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[phone]" value="<?php echo esc_attr( $bp['phone'] ?? '' ); ?>" placeholder="+1 (555) 000-0000" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-address">Street Address</label>
						<input type="text" id="bmcp-bp-address" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[address]" value="<?php echo esc_attr( $bp['address'] ?? '' ); ?>" placeholder="123 Main Street" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-city">City &amp; Country</label>
						<input type="text" id="bmcp-bp-city" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[city_country]" value="<?php echo esc_attr( $bp['city_country'] ?? '' ); ?>" placeholder="e.g. London, UK" />
					</div>
				</div>
			</div>

			<!-- Social Media -->
			<div class="bmcp-card">
				<h2>Social Media</h2>
				<div class="bmcp-bp-grid">
					<?php
					$social_fields = [
						'facebook_url'  => [ 'label' => 'Facebook', 'placeholder' => 'https://facebook.com/yourpage' ],
						'instagram_url' => [ 'label' => 'Instagram', 'placeholder' => 'https://instagram.com/yourhandle' ],
						'linkedin_url'  => [ 'label' => 'LinkedIn', 'placeholder' => 'https://linkedin.com/company/yourco' ],
						'twitter_url'   => [ 'label' => 'Twitter / X', 'placeholder' => 'https://x.com/yourhandle' ],
						'youtube_url'   => [ 'label' => 'YouTube', 'placeholder' => 'https://youtube.com/@yourchannel' ],
					];
					foreach ( $social_fields as $field_key => $field_info ) :
					?>
					<div class="bmcp-field">
						<label for="bmcp-bp-<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $field_info['label'] ); ?></label>
						<input type="url" id="bmcp-bp-<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( $bp[ $field_key ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $field_info['placeholder'] ); ?>" />
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Navigation & Copy -->
			<div class="bmcp-card">
				<h2>Navigation &amp; Copy</h2>
				<div class="bmcp-bp-grid">
					<div class="bmcp-field bmcp-field--full">
						<label for="bmcp-bp-nav-items">Navigation Items <span class="description">comma-separated</span></label>
						<input type="text" id="bmcp-bp-nav-items" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[nav_items]" value="<?php echo esc_attr( $bp['nav_items'] ?? '' ); ?>" placeholder="Home, About, Services, Contact" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-cta-text">CTA Button Text</label>
						<input type="text" id="bmcp-bp-cta-text" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[cta_text]" value="<?php echo esc_attr( $bp['cta_text'] ?? '' ); ?>" placeholder="Get Started" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-cta-url">CTA Button URL</label>
						<input type="url" id="bmcp-bp-cta-url" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[cta_url]" value="<?php echo esc_attr( $bp['cta_url'] ?? '' ); ?>" placeholder="https://" />
					</div>
					<div class="bmcp-field bmcp-field--full">
						<label for="bmcp-bp-copyright">Copyright Text</label>
						<input type="text" id="bmcp-bp-copyright" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[copyright_text]" value="<?php echo esc_attr( $bp['copyright_text'] ?? '' ); ?>" placeholder="© 2025 Company Name. All rights reserved." />
					</div>
				</div>
			</div>

			<!-- Brand Assets -->
			<div class="bmcp-card">
				<h2>Brand Assets</h2>
				<div class="bmcp-bp-grid">
					<div class="bmcp-field">
						<label for="bmcp-bp-logo">Logo URL <span class="description">replaces logoipsum images in templates</span></label>
						<input type="url" id="bmcp-bp-logo" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[logo_url]" value="<?php echo esc_attr( $bp['logo_url'] ?? '' ); ?>" placeholder="https://" />
					</div>
					<div class="bmcp-field">
						<label for="bmcp-bp-logo-dark">Dark Logo URL <span class="description">for dark backgrounds</span></label>
						<input type="url" id="bmcp-bp-logo-dark" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[logo_dark_url]" value="<?php echo esc_attr( $bp['logo_dark_url'] ?? '' ); ?>" placeholder="https://" />
					</div>
				</div>
			</div>

			<!-- Services -->
			<div class="bmcp-card">
				<h2>Services / Offerings</h2>
				<div class="bmcp-field">
					<label for="bmcp-bp-services">One service per line <span class="description">populates service sections and icon-box grids in templates</span></label>
					<textarea id="bmcp-bp-services" name="<?php echo esc_attr( BMCP_BUSINESS_PROFILE_OPTION ); ?>[services]" rows="6" placeholder="Web Design&#10;SEO Consulting&#10;Brand Strategy"><?php echo esc_textarea( $bp['services'] ?? '' ); ?></textarea>
				</div>
			</div>

			<div class="bmcp-submit-row">
				<?php submit_button( 'Save Business Profile', 'primary', 'submit', false ); ?>
				<p class="description">Saved data is returned by <code>bricks_get_business_profile</code> and automatically included in every session via <code>bricks_get_session_context</code>.</p>
			</div>

		</form>
	</div><!-- /tab-business-profile -->

	<!-- ====================== CAPABILITIES TAB ====================== -->
	<div class="bmcp-panel" id="tab-capabilities" role="tabpanel" aria-labelledby="nav-tab-capabilities" style="display:none">
		<form method="post" action="options.php">
			<?php settings_fields( 'bmcp_settings_capabilities' ); ?>

			<?php
			$tool_states = get_option( BMCP_TOOL_STATES_OPTION, [] );

			$cap_groups = [
				'pages' => [
					'label' => 'Pages', 'icon' => '⊞',
					'tools' => [
						'bricks_list_pages'  => [ 'label' => 'List Pages',   'desc' => 'Browse all pages with status and URL' ],
						'bricks_get_page'    => [ 'label' => 'Get Page',     'desc' => 'Read page elements and metadata' ],
						'bricks_create_page' => [ 'label' => 'Create Page',  'desc' => 'Create new WordPress pages' ],
						'bricks_update_page' => [ 'label' => 'Update Page',  'desc' => 'Edit page content and Bricks elements' ],
						'bricks_delete_page' => [ 'label' => 'Delete Page',  'desc' => 'Trash or permanently delete pages' ],
					],
				],
				'templates' => [
					'label' => 'Templates', 'icon' => '⊟',
					'tools' => [
						'bricks_list_templates'          => [ 'label' => 'List Templates',          'desc' => 'Browse all Bricks templates' ],
						'bricks_get_template'            => [ 'label' => 'Get Template',            'desc' => 'Read template elements and type' ],
						'bricks_create_template'         => [ 'label' => 'Create Template',         'desc' => 'Create header / footer / section templates' ],
						'bricks_update_template'         => [ 'label' => 'Update Template',         'desc' => 'Edit template elements' ],
						'bricks_delete_template'         => [ 'label' => 'Delete Template',         'desc' => 'Delete templates' ],
						'bricks_set_template_conditions' => [ 'label' => 'Set Template Conditions', 'desc' => 'Control where a template appears' ],
					],
				],
				'settings' => [
					'label' => 'Global Design', 'icon' => '◎',
					'tools' => [
						'bricks_get_global_settings'    => [ 'label' => 'Get Global Settings',    'desc' => 'Read Bricks global settings' ],
						'bricks_update_global_settings' => [ 'label' => 'Update Global Settings', 'desc' => 'Write Bricks global settings' ],
						'bricks_get_color_palette'      => [ 'label' => 'Get Color Palette',      'desc' => 'Read brand color palette' ],
						'bricks_update_color_palette'   => [ 'label' => 'Update Color Palette',   'desc' => 'Replace the color palette' ],
						'bricks_get_global_classes'     => [ 'label' => 'Get Global Classes',     'desc' => 'Read reusable CSS classes' ],
						'bricks_create_global_class'    => [ 'label' => 'Create Global Class',    'desc' => 'Add a new CSS utility class' ],
						'bricks_update_global_class'    => [ 'label' => 'Update Global Class',    'desc' => 'Edit an existing CSS class' ],
						'bricks_get_theme_styles'       => [ 'label' => 'Get Theme Styles',       'desc' => 'Read theme style entries' ],
						'bricks_update_theme_styles'    => [ 'label' => 'Update Theme Styles',    'desc' => 'Edit theme style entries' ],
					],
				],
				'posts' => [
					'label' => 'Posts & CPTs', 'icon' => '≡',
					'tools' => [
						'bricks_list_post_types' => [ 'label' => 'List Post Types', 'desc' => 'List all registered public post types' ],
						'bricks_list_posts'      => [ 'label' => 'List Posts',      'desc' => 'Browse posts of any type' ],
						'bricks_get_post'        => [ 'label' => 'Get Post',        'desc' => 'Read post content and elements' ],
						'bricks_create_post'     => [ 'label' => 'Create Post',     'desc' => 'Create new posts or CPT entries' ],
						'bricks_update_post'     => [ 'label' => 'Update Post',     'desc' => 'Edit post content and meta' ],
						'bricks_delete_post'     => [ 'label' => 'Delete Post',     'desc' => 'Trash or delete posts' ],
					],
				],
				'media' => [
					'label' => 'Media Library', 'icon' => '⊕',
					'tools' => [
						'bricks_list_media'            => [ 'label' => 'List Media',            'desc' => 'Browse media library items' ],
						'bricks_upload_media_from_url' => [ 'label' => 'Upload Media from URL', 'desc' => 'Import images from external URLs' ],
						'bricks_get_media'             => [ 'label' => 'Get Media',             'desc' => 'Read media file metadata' ],
					],
				],
				'woocommerce' => [
					'label' => 'WooCommerce', 'icon' => '◈',
					'tools' => [
						'bricks_list_products'           => [ 'label' => 'List Products',           'desc' => 'Browse WooCommerce products (requires WooCommerce)' ],
						'bricks_get_product'             => [ 'label' => 'Get Product',             'desc' => 'Read product details' ],
						'bricks_list_product_categories' => [ 'label' => 'List Product Categories', 'desc' => 'Browse product categories' ],
					],
				],
			];

			foreach ( $cap_groups as $group_key => $group ) :
				$group_tools    = array_keys( $group['tools'] );
				$enabled_count  = count( array_filter( $group_tools, fn( $t ) => ( $tool_states[ $t ] ?? true ) ) );
				$total_count    = count( $group_tools );
				$all_on         = $enabled_count === $total_count;
			?>
			<div class="bmcp-card bmcp-cap-group" data-group="<?php echo esc_attr( $group_key ); ?>" style="padding:0;overflow:hidden;margin-bottom:12px;">
				<div class="bmcp-cap-group-header">
					<div class="bmcp-cap-group-title">
						<span class="bmcp-cap-group-icon" aria-hidden="true"><?php echo esc_html( $group['icon'] ); ?></span>
						<strong><?php echo esc_html( $group['label'] ); ?></strong>
						<span class="bmcp-cap-count"><?php echo esc_html( $enabled_count . ' / ' . $total_count ); ?> enabled</span>
					</div>
					<button type="button" class="button bmcp-cap-toggle-all" data-group="<?php echo esc_attr( $group_key ); ?>" aria-label="Toggle all <?php echo esc_attr( $group['label'] ); ?> tools">
						<?php echo $all_on ? 'Disable All' : 'Enable All'; ?>
					</button>
				</div>
				<table class="bmcp-capabilities-table" style="border:none;border-radius:0;margin:0;">
					<thead>
						<tr>
							<th scope="col" style="width:200px;padding-left:20px">Tool</th>
							<th scope="col">Description</th>
							<th scope="col" style="width:70px;text-align:center">On</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $group['tools'] as $tool_name => $tool_info ) :
							$is_on = $tool_states[ $tool_name ] ?? true;
						?>
						<tr class="bmcp-cap-tool-row" data-tool="<?php echo esc_attr( $tool_name ); ?>" data-group="<?php echo esc_attr( $group_key ); ?>">
							<td style="padding-left:20px">
								<code style="font-size:0.76rem"><?php echo esc_html( $tool_name ); ?></code>
								<div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px"><?php echo esc_html( $tool_info['label'] ); ?></div>
							</td>
							<td style="font-size:0.82rem;color:var(--text-muted)"><?php echo esc_html( $tool_info['desc'] ); ?></td>
							<td style="text-align:center">
								<label class="bmcp-toggle">
									<input type="checkbox"
										name="<?php echo esc_attr( BMCP_TOOL_STATES_OPTION . '[' . $tool_name . ']' ); ?>"
										value="1"
										<?php checked( $is_on ); ?>
										aria-label="<?php echo esc_attr( 'Enable ' . $tool_info['label'] ); ?>"
									/>
									<span class="bmcp-toggle-slider" aria-hidden="true"></span>
								</label>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endforeach; ?>

			<div class="bmcp-submit-row" style="padding-top:16px">
				<?php submit_button( 'Save Capabilities', 'primary', 'submit', false ); ?>
				<p class="description">Site tools (system prompt, memory, history, nav menus, components) are always on and cannot be disabled.</p>
			</div>

		</form>
	</div><!-- /tab-capabilities -->

	<!-- ====================== MEMORY TAB ====================== -->
	<div class="bmcp-panel" id="tab-memory" role="tabpanel" aria-labelledby="nav-tab-memory" style="display:none">
		<div class="bmcp-card">
			<div class="bmcp-card-header">
				<h2>AI Memory <span class="description" style="font-weight:400;font-size:0.8rem;color:var(--text-dim)">up to 500 entries</span></h2>
				<button type="button" class="button button-primary" id="bmcp-btn-add-memory" aria-haspopup="dialog"><?php echo $svg_plus; ?> Add Memory</button>
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
				<div class="bmcp-search-wrap">
					<?php echo $svg_search; ?>
					<input type="search" id="bmcp-mem-search" placeholder="Search memories…" autocomplete="off">
				</div>
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
				<button type="button" class="bmcp-modal-close" aria-label="Close dialog"><?php echo $svg_close; ?></button>
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

	<!-- ====================== HISTORY TAB ====================== -->
	<div class="bmcp-panel" id="tab-history" role="tabpanel" aria-labelledby="nav-tab-history" style="display:none">
		<div class="bmcp-card">
			<div class="bmcp-card-header">
				<h2>Content History <span class="description" style="font-weight:400;font-size:0.8rem;color:var(--text-dim)">up to 200 snapshots</span></h2>
				<button type="button" class="button bmcp-btn-danger" id="bmcp-btn-clear-history" aria-label="Clear all snapshots"><?php echo $svg_trash; ?> Clear All</button>
			</div>
			<p style="margin-bottom:16px">A snapshot is automatically saved before every AI write operation. Use these restore points to undo any unwanted change. Restoring is also undoable — the current state is always snapshotted first.</p>

			<div class="bmcp-memory-toolbar" role="search" aria-label="Filter history">
				<label for="bmcp-hist-area-filter" class="bmcp-sr-only">Filter by area</label>
				<select id="bmcp-hist-area-filter" aria-label="Filter by content area">
					<option value="">All Areas</option>
					<option value="content">Content</option>
					<option value="header">Header</option>
					<option value="footer">Footer</option>
					<option value="global_settings">Global Settings</option>
					<option value="color_palette">Color Palette</option>
					<option value="global_classes">Global Classes</option>
					<option value="theme_styles">Theme Styles</option>
					<option value="components">Components</option>
				</select>
			</div>

			<div id="bmcp-history-list" aria-live="polite"><p class="bmcp-empty">Click the History tab to load snapshots.</p></div>
			<nav id="bmcp-history-pagination" aria-label="History pagination"></nav>
		</div>
	</div><!-- /tab-history -->

	<!-- ====================== ACTIVITY LOG TAB ====================== -->
	<div class="bmcp-panel" id="tab-activity" role="tabpanel" aria-labelledby="nav-tab-activity" style="display:none">
		<div class="bmcp-card">
			<div class="bmcp-card-header">
				<h2>Activity Log <span class="description" style="font-weight:400;font-size:0.8rem;color:var(--text-dim)">last 20 requests</span></h2>
				<button type="button" class="button bmcp-btn-danger" id="bmcp-btn-clear-log" aria-label="Clear activity log"><?php echo $svg_trash; ?> Clear Log</button>
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

	<!-- ====================== ADVANCED TAB ====================== -->
	<div class="bmcp-panel" id="tab-advanced" role="tabpanel" aria-labelledby="nav-tab-advanced" style="display:none">
		<form method="post" action="options.php">
			<?php
			settings_fields( 'bmcp_settings_advanced' );
			$adv          = get_option( BMCP_ADVANCED_OPTION, [] );
			$erase        = ! empty( $adv['erase_on_uninstall'] );
			$no_log       = ! empty( $adv['disable_activity_log'] );
			$debug        = ! empty( $adv['debug_mode'] );
			?>

			<div class="bmcp-card">
				<h2>Data &amp; Privacy</h2>
				<table class="bmcp-adv-table">
					<tbody>
						<tr>
							<td class="bmcp-adv-td-label">
								<strong>Erase all plugin data on uninstall</strong>
								<div class="description" style="margin-top:4px">Permanently deletes all API keys, memories, history snapshots, settings, and the history database table when the plugin is deleted. Default: off.</div>
							</td>
							<td class="bmcp-adv-td-toggle">
								<label class="bmcp-toggle">
									<input type="checkbox" name="<?php echo esc_attr( BMCP_ADVANCED_OPTION ); ?>[erase_on_uninstall]" value="1" <?php checked( $erase ); ?> />
									<span class="bmcp-toggle-slider" aria-hidden="true"></span>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="bmcp-card">
				<h2>Logging &amp; Debugging</h2>
				<table class="bmcp-adv-table">
					<tbody>
						<tr>
							<td class="bmcp-adv-td-label">
								<strong>Disable activity logging</strong>
								<div class="description" style="margin-top:4px">Stop recording tool calls in the Activity Log. Reduces DB writes on high-traffic sites. Default: off (logging enabled).</div>
							</td>
							<td class="bmcp-adv-td-toggle">
								<label class="bmcp-toggle">
									<input type="checkbox" name="<?php echo esc_attr( BMCP_ADVANCED_OPTION ); ?>[disable_activity_log]" value="1" <?php checked( $no_log ); ?> />
									<span class="bmcp-toggle-slider" aria-hidden="true"></span>
								</label>
							</td>
						</tr>
						<tr>
							<td class="bmcp-adv-td-label">
								<strong>Debug mode</strong>
								<div class="description" style="margin-top:4px">Include detailed error messages and PHP traces in MCP error responses. Keep off in production. Default: off.</div>
							</td>
							<td class="bmcp-adv-td-toggle">
								<label class="bmcp-toggle">
									<input type="checkbox" name="<?php echo esc_attr( BMCP_ADVANCED_OPTION ); ?>[debug_mode]" value="1" <?php checked( $debug ); ?> />
									<span class="bmcp-toggle-slider" aria-hidden="true"></span>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="bmcp-submit-row">
				<?php submit_button( 'Save Advanced Settings', 'primary', 'submit', false ); ?>
			</div>
		</form>
	</div><!-- /tab-advanced -->

	<!-- ====================== FOOTER ====================== -->
	<footer class="bmcp-footer" role="contentinfo">
		<div class="bmcp-footer-bar" aria-hidden="true"></div>
		<div class="bmcp-footer-inner">
			<span class="bmcp-footer-dev">
				Developed by <a href="https://yasirshabbir.com" target="_blank" rel="noopener noreferrer" class="bmcp-footer-link">Yasir Shabbir</a>
			</span>
			<span class="bmcp-footer-dev bmcp-footer-ver-inline">v<?php echo esc_html( BMCP_VERSION ); ?></span>
		</div>
	</footer>

</div><!-- /#bmcp-wrap -->
