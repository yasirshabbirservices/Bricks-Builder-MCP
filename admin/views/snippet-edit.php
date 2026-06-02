<?php
/**
 * Snippets Manager — Create / Edit View
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permission denied.' );

use BricksMCP\Snippets_Manager;

$snippet_id = (int) ( $_GET['snippet_id'] ?? 0 );
$is_new     = ( 0 === $snippet_id );
$snippet    = $is_new ? null : Snippets_Manager::get_snippet( $snippet_id );

if ( ! $is_new && ! $snippet ) {
	wp_die( 'Snippet not found.' );
}

$list_url = admin_url( 'admin.php?page=bricks-mcp-snippets' );

// Defaults for a new snippet
$s = $snippet ?? [
	'id'          => 0,
	'title'       => '',
	'code'        => '',
	'type'        => 'php',
	'status'      => 'inactive',
	'location'    => 'everywhere',
	'hook'        => 'init',
	'priority'    => 10,
	'description' => '',
	'tags'        => '',
	'url'         => '',
	'shortcode'   => '',
	'conditions'  => [],
];

$is_active  = 'active' === $s['status'];
$page_title = $is_new ? 'New Snippet' : 'Edit: ' . $s['title'];

// Type → CodeMirror mode
$cm_modes = [
	'php'            => 'application/x-httpd-php',
	'javascript'     => 'text/javascript',
	'javascript_url' => 'text/javascript',
	'css'            => 'text/css',
	'css_url'        => 'text/css',
	'html'           => 'text/html',
];
$initial_cm_mode = $cm_modes[ $s['type'] ] ?? 'text/plain';

// Whether hook row should be hidden on load (JS/CSS types don't use hooks)
$hook_hidden = in_array( $s['type'], [ 'javascript', 'javascript_url', 'css', 'css_url' ], true );

// Default starter code shown to the user when creating a new snippet
$default_code = [
	'php' => "// Write your PHP code below — no opening <?php tag needed.\n// This snippet runs on the hook you set in the Execution panel.\n\nadd_action( 'wp_footer', function () {\n\t// Example: echo a hidden comment\n\t// echo '<!-- snippet active -->';\n} );\n",
	'javascript' => "( function () {\n\t'use strict';\n\n\t// Your JavaScript runs in wp_footer on every page.\n\tdocument.addEventListener( 'DOMContentLoaded', function () {\n\t\t// DOM is ready — write your code here.\n\t\tconsole.log( 'Snippet loaded.' );\n\t} );\n\n} )();\n",
	'javascript_url' => '',
	'css' => "/* Add your custom styles below.\n   These are injected into <head> on every page. */\n\n.my-element {\n\t/* color: #333; */\n\t/* font-size: 1rem; */\n}\n",
	'css_url' => '',
	'html' => "<!-- Your HTML is output at the configured hook.\n     Use the Shortcode option to embed it in specific pages. -->\n\n<div class=\"my-custom-block\">\n\t<p>Hello from a snippet!</p>\n</div>\n",
];
?>
<div id="bmcp-wrap" class="wrap bmcp-snippets-wrap">

	<!-- PAGE HEADER -->
	<div class="bmcp-page-header bmcp-snip-edit-header">
		<div class="bmcp-header-left">
			<a href="<?php echo esc_url( $list_url ); ?>" class="bmcp-snip-back" title="Back to snippets">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
			</a>
			<div class="bmcp-header-icon">&#x1F4BE;</div>
			<div class="bmcp-header-text">
				<h1><?php echo esc_html( $page_title ); ?></h1>
				<p><?php echo $is_new ? 'Fill in the details below and click Save.' : 'Edit snippet code and settings, then click Save.'; ?></p>
			</div>
		</div>
		<div class="bmcp-header-right">
			<span id="bmcp-snip-save-status" class="bmcp-snip-save-status"></span>
			<button type="button" id="bmcp-snip-save" class="bmcp-btn bmcp-btn--primary">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
				Save Snippet
			</button>
		</div>
	</div>

	<!-- TWO-COLUMN LAYOUT -->
	<div class="bmcp-snip-edit-layout">

		<!-- LEFT: Code Editor -->
		<div class="bmcp-snip-editor-col">
			<div class="bmcp-card bmcp-snip-editor-card">

				<!-- Type tabs -->
				<div class="bmcp-snip-type-tabs" role="group" aria-label="Snippet type">
					<?php
					$types = [
						'php'            => 'PHP',
						'javascript'     => 'JavaScript',
						'javascript_url' => 'JS (URL)',
						'css'            => 'CSS',
						'css_url'        => 'CSS (URL)',
						'html'           => 'HTML',
					];
					foreach ( $types as $t => $label ) : ?>
					<button type="button"
						class="bmcp-snip-type-tab <?php echo $s['type'] === $t ? 'active' : ''; ?>"
						data-type="<?php echo esc_attr( $t ); ?>"
						data-mode="<?php echo esc_attr( $cm_modes[ $t ] ?? 'text/plain' ); ?>">
						<?php echo esc_html( $label ); ?>
					</button>
					<?php endforeach; ?>
				</div>

				<!-- External URL (shown for URL types only) -->
				<div class="bmcp-snip-url-field" id="bmcp-url-row"
					style="<?php echo in_array( $s['type'], [ 'javascript_url', 'css_url' ], true ) ? '' : 'display:none'; ?>">
					<label for="bmcp-snip-url">External URL</label>
					<input type="url" id="bmcp-snip-url" value="<?php echo esc_attr( $s['url'] ); ?>"
						placeholder="https://example.com/script.js" class="bmcp-snip-url-input">
				</div>

				<!-- Code editor -->
				<div class="bmcp-snip-editor-wrap" id="bmcp-code-wrap"
					style="<?php echo in_array( $s['type'], [ 'javascript_url', 'css_url' ], true ) ? 'display:none' : ''; ?>">
					<textarea id="bmcp-snip-code" name="code" spellcheck="false" autocomplete="off"
						class="bmcp-snip-code"><?php echo esc_textarea( $s['code'] ); ?></textarea>
				</div>

			</div>
		</div><!-- .bmcp-snip-editor-col -->

		<!-- RIGHT: Settings Panel -->
		<div class="bmcp-snip-settings-col">

			<!-- 1. Name + Status (combined top card) -->
			<div class="bmcp-card bmcp-snip-settings-card bmcp-snip-identity-card">
				<label class="bmcp-snip-label" for="bmcp-snip-title">Snippet Name</label>
				<input type="text" id="bmcp-snip-title"
					value="<?php echo esc_attr( $s['title'] ); ?>"
					placeholder="e.g. Remove emoji scripts"
					class="bmcp-snip-input bmcp-snip-title-input"
					<?php echo $is_new ? 'autofocus' : ''; ?>>

				<div class="bmcp-snip-status-row">
					<div class="bmcp-snip-status-info">
						<span class="bmcp-snip-label" style="margin:0">Status</span>
						<span id="bmcp-snip-status-text" class="bmcp-snip-status-text">
							<?php if ( $is_active ) : ?>
								<span class="bmcp-snip-status-on">Active</span>
							<?php else : ?>
								<span class="bmcp-snip-status-off">Inactive</span>
							<?php endif; ?>
						</span>
					</div>
					<label class="bmcp-toggle" for="bmcp-snip-status-toggle" aria-label="Toggle snippet status">
						<input type="checkbox" id="bmcp-snip-status-toggle"
							<?php checked( $is_active ); ?> role="switch">
						<span class="bmcp-toggle-slider"></span>
					</label>
				</div>
			</div>

			<!-- 2. Execution settings -->
			<div class="bmcp-card bmcp-snip-settings-card">
				<h3 class="bmcp-snip-settings-heading">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
					Execution
				</h3>

				<label class="bmcp-snip-label" for="bmcp-snip-location">Location</label>
				<select id="bmcp-snip-location" class="bmcp-snip-select">
					<?php foreach ( [ 'everywhere' => 'Everywhere', 'frontend' => 'Front-end only', 'admin' => 'Admin area only', 'shortcode' => 'Shortcode only' ] as $val => $lbl ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['location'], $val ); ?>><?php echo esc_html( $lbl ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="bmcp-snip-label bmcp-snip-hook-label" for="bmcp-snip-hook"
					style="<?php echo $hook_hidden ? 'display:none' : ''; ?>">
					Hook <span class="bmcp-snip-label-hint">(PHP &amp; HTML only)</span>
				</label>
				<select id="bmcp-snip-hook" class="bmcp-snip-select"
					style="<?php echo $hook_hidden ? 'display:none' : ''; ?>">
					<?php foreach ( Snippets_Manager::HOOKS as $h ) : ?>
					<option value="<?php echo esc_attr( $h ); ?>" <?php selected( $s['hook'], $h ); ?>><?php echo esc_html( $h ); ?></option>
					<?php endforeach; ?>
					<option value="__custom__" <?php selected( ! in_array( $s['hook'], Snippets_Manager::HOOKS, true ) ); ?>>Custom...</option>
				</select>
				<input type="text" id="bmcp-snip-hook-custom" class="bmcp-snip-input bmcp-snip-hook-custom"
					value="<?php echo in_array( $s['hook'], Snippets_Manager::HOOKS, true ) ? '' : esc_attr( $s['hook'] ); ?>"
					placeholder="Custom hook name"
					style="<?php echo in_array( $s['hook'], Snippets_Manager::HOOKS, true ) ? 'display:none' : ''; ?>">

				<div class="bmcp-snip-priority-row">
					<label class="bmcp-snip-label" for="bmcp-snip-priority">Priority</label>
					<input type="number" id="bmcp-snip-priority" value="<?php echo (int) $s['priority']; ?>"
						min="1" max="9999" class="bmcp-snip-input bmcp-snip-input--sm">
				</div>
			</div>

			<!-- 3. Description + Tags -->
			<div class="bmcp-card bmcp-snip-settings-card">
				<label class="bmcp-snip-label" for="bmcp-snip-desc">Description</label>
				<textarea id="bmcp-snip-desc" rows="3" class="bmcp-snip-textarea"
					placeholder="What does this snippet do?"><?php echo esc_textarea( $s['description'] ); ?></textarea>

				<label class="bmcp-snip-label" for="bmcp-snip-tags">Tags</label>
				<input type="text" id="bmcp-snip-tags" value="<?php echo esc_attr( $s['tags'] ); ?>"
					placeholder="woocommerce, checkout, fixes" class="bmcp-snip-input">
			</div>

			<!-- 4. Shortcode (existing snippets only) -->
			<?php if ( ! $is_new ) : ?>
			<div class="bmcp-card bmcp-snip-settings-card">
				<label class="bmcp-snip-label">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
					Shortcode
				</label>
				<div class="bmcp-snip-shortcode-row">
					<code class="bmcp-snip-shortcode-val" id="bmcp-snip-shortcode">[<?php echo esc_html( $s['shortcode'] ); ?>]</code>
					<button type="button" class="bmcp-btn bmcp-btn--sm" id="bmcp-copy-shortcode">Copy</button>
				</div>
			</div>
			<?php endif; ?>

			<!-- 5. Conditions -->
			<div class="bmcp-card bmcp-snip-settings-card">
				<h3 class="bmcp-snip-settings-heading">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
					Conditions
					<span class="bmcp-snip-label-hint">— all must match (AND)</span>
				</h3>
				<div id="bmcp-conditions-list" class="bmcp-conditions-list"></div>
				<button type="button" id="bmcp-add-condition" class="bmcp-btn bmcp-btn--sm bmcp-btn--ghost">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					Add Condition
				</button>
			</div>

			<!-- 6. Delete (existing snippets only) -->
			<?php if ( ! $is_new ) : ?>
			<div class="bmcp-snip-danger-zone">
				<button type="button" id="bmcp-snip-delete"
					class="bmcp-btn bmcp-btn--danger-ghost bmcp-btn--full"
					data-id="<?php echo (int) $s['id']; ?>"
					data-title="<?php echo esc_attr( $s['title'] ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
					Delete Snippet
				</button>
			</div>
			<?php endif; ?>

		</div><!-- .bmcp-snip-settings-col -->
	</div><!-- .bmcp-snip-edit-layout -->

</div><!-- #bmcp-wrap -->

<!-- Hidden data for JS -->
<script id="bmcp-snip-edit-data" type="application/json"><?php
	echo wp_json_encode( [
		'snippetId'    => $s['id'],
		'isNew'        => $is_new,
		'listUrl'      => $list_url,
		'cmMode'       => $initial_cm_mode,
		'conditions'   => $s['conditions'] ?: [],
		'cmModes'      => $cm_modes,
		'defaultCode'  => $default_code,
		'currentType'  => $s['type'],
	] );
?></script>
