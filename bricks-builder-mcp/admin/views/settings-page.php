<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$log     = get_option( BMCP_ACTIVITY_LOG_OPTION, [] );
$enabled = get_option( BMCP_ENABLED_TOOLS_OPTION, [] );
$groups  = [ 'pages', 'templates', 'settings', 'posts', 'media', 'woocommerce' ];
$endpoint = rest_url( BMCP_REST_NAMESPACE . '/mcp' );
?>
<div class="wrap" id="bmcp-wrap">
	<h1><span class="bmcp-logo">⚡</span> Bricks Builder MCP <span class="bmcp-version">v<?php echo esc_html( BMCP_VERSION ); ?></span></h1>

	<nav class="bmcp-tabs" role="tablist">
		<a href="#" class="bmcp-tab active" data-tab="connection">Connection</a>
		<a href="#" class="bmcp-tab" data-tab="instructions">Custom Instructions</a>
		<a href="#" class="bmcp-tab" data-tab="capabilities">Capabilities</a>
		<a href="#" class="bmcp-tab" data-tab="activity">Activity Log <?php if( ! empty( $log ) ) echo '<span class="bmcp-badge">' . count( $log ) . '</span>'; ?></a>
	</nav>

	<!-- ====================== CONNECTION TAB ====================== -->
	<div class="bmcp-panel active" id="tab-connection">
		<div class="bmcp-card">
			<h2>API Key</h2>
			<p>Use this key to authenticate your Claude Code (or any MCP client) connection.</p>

			<div class="bmcp-key-row">
				<code class="bmcp-key-display" id="bmcp-key-masked"><?php echo esc_html( \BricksMCP\Auth::masked_key() ); ?></code>
				<button type="button" class="button" id="bmcp-btn-copy">Copy Key</button>
				<button type="button" class="button button-secondary" id="bmcp-btn-regen">Regenerate</button>
			</div>
			<p class="description">Never share your API key publicly. Click "Regenerate" to invalidate the old key.</p>
		</div>

		<div class="bmcp-card">
			<h2>Connect Claude Code</h2>
			<p>Add this to your <code>~/.claude/settings.json</code> (or <code>.claude/settings.json</code> in your project):</p>
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
				<button type="button" class="button bmcp-copy-config" data-target="bmcp-config-snippet">Copy Config</button>
			</div>
			<p class="description">The config above always contains your current API key. After regenerating, the snippet updates automatically. Run <code>claude mcp list</code> to verify the connection.</p>
		</div>

		<div class="bmcp-card">
			<h2>Endpoint URL</h2>
			<div class="bmcp-key-row">
				<code class="bmcp-key-display" id="bmcp-endpoint"><?php echo esc_html( $endpoint ); ?></code>
				<button type="button" class="button bmcp-copy-config" data-target="bmcp-endpoint">Copy URL</button>
			</div>
		</div>
	</div>

	<!-- ====================== INSTRUCTIONS TAB ====================== -->
	<div class="bmcp-panel" id="tab-instructions" style="display:none">
		<form method="post" action="options.php">
			<?php settings_fields( 'bmcp_settings' ); ?>

			<div class="bmcp-card">
				<h2>Custom Instructions</h2>
				<p>Add context about your business, brand voice, design preferences, or any other guidance for the AI. This is appended to the built-in system prompt.</p>

				<textarea
					name="<?php echo esc_attr( BMCP_INSTRUCTIONS_OPTION ); ?>"
					id="bmcp-instructions"
					rows="12"
					class="large-text"
					placeholder="Examples:&#10;- This is a SaaS company called Acme. Primary color: #3b82f6 (blue). Secondary: #f59e0b (amber).&#10;- Always use Inter font for headings and body text.&#10;- The hero section should always have a strong CTA button.&#10;- Tone of voice: professional but approachable.&#10;- Never use stock photo placeholder text — use realistic content.&#10;- Main navigation items: Home, About, Services, Pricing, Blog, Contact."
				><?php echo esc_textarea( get_option( BMCP_INSTRUCTIONS_OPTION, '' ) ); ?></textarea>

				<p class="description">These instructions are included every time the AI calls <code>bricks_get_system_prompt</code>.</p>

				<?php submit_button( 'Save Instructions' ); ?>
			</div>
		</form>
	</div>

	<!-- ====================== CAPABILITIES TAB ====================== -->
	<div class="bmcp-panel" id="tab-capabilities" style="display:none">
		<form method="post" action="options.php">
			<?php settings_fields( 'bmcp_settings' ); ?>

			<div class="bmcp-card">
				<h2>Tool Groups</h2>
				<p>Disable tool groups to restrict what the AI can do. Site tools (site info, element catalog, system prompt) are always enabled.</p>

				<table class="bmcp-capabilities-table">
					<thead>
						<tr><th>Group</th><th>Tools</th><th>Enabled</th></tr>
					</thead>
					<tbody>
						<?php
						$group_info = [
							'pages'       => [ 'label' => 'Pages', 'tools' => 'list, get, create, update, delete pages' ],
							'templates'   => [ 'label' => 'Templates', 'tools' => 'list, get, create, update, delete Bricks templates + conditions' ],
							'settings'    => [ 'label' => 'Global Design', 'tools' => 'global settings, colors, global classes, theme styles' ],
							'posts'       => [ 'label' => 'Posts & CPTs', 'tools' => 'list, get, create, update, delete any post type' ],
							'media'       => [ 'label' => 'Media Library', 'tools' => 'list media, upload from URL, get media details' ],
							'woocommerce' => [ 'label' => 'WooCommerce', 'tools' => 'list/get products, product categories (requires WooCommerce)' ],
						];
						foreach ( $group_info as $key => $info ):
							$is_enabled = $enabled[ $key ] ?? true;
						?>
						<tr>
							<td><strong><?php echo esc_html( $info['label'] ); ?></strong></td>
							<td class="bmcp-tool-list"><?php echo esc_html( $info['tools'] ); ?></td>
							<td>
								<label class="bmcp-toggle">
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

				<?php submit_button( 'Save Capabilities' ); ?>
			</div>
		</form>
	</div>

	<!-- ====================== ACTIVITY LOG TAB ====================== -->
	<div class="bmcp-panel" id="tab-activity" style="display:none">
		<div class="bmcp-card">
			<div class="bmcp-card-header">
				<h2>Activity Log <span class="description">(last 20 requests)</span></h2>
				<button type="button" class="button button-secondary" id="bmcp-btn-clear-log">Clear Log</button>
			</div>

			<?php if ( empty( $log ) ): ?>
				<p class="bmcp-empty">No MCP requests logged yet. Connect your MCP client and make a tool call to see activity here.</p>
			<?php else: ?>
				<table class="widefat striped bmcp-log-table">
					<thead>
						<tr>
							<th>Time</th>
							<th>Tool</th>
							<th>Status</th>
							<th>Error</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $entry ): ?>
						<tr>
							<td><?php echo esc_html( date( 'Y-m-d H:i:s', $entry['timestamp'] ) ); ?></td>
							<td><code><?php echo esc_html( $entry['tool'] ); ?></code></td>
							<td>
								<?php if ( $entry['success'] ): ?>
									<span class="bmcp-status ok">✓ OK</span>
								<?php else: ?>
									<span class="bmcp-status fail">✗ FAIL</span>
								<?php endif; ?>
							</td>
							<td class="bmcp-error-cell"><?php echo esc_html( $entry['error'] ?? '' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
