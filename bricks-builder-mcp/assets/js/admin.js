/* Bricks Builder MCP — Admin JS */
jQuery( function ( $ ) {
	'use strict';

	var cfg = window.bmcpAdmin || {};

	// ---- Tab switching ----
	$( '.bmcp-tab' ).on( 'click', function ( e ) {
		e.preventDefault();
		var tab = $( this ).data( 'tab' );
		$( '.bmcp-tab' ).removeClass( 'active' ).attr( 'aria-selected', 'false' );
		$( this ).addClass( 'active' ).attr( 'aria-selected', 'true' );
		$( '.bmcp-panel' ).hide();
		$( '#tab-' + tab ).show();
	} );

	// ---- AI Client sub-tab switching ----
	$( '.bmcp-client-tab' ).on( 'click', function () {
		var client = $( this ).data( 'client' );
		$( '.bmcp-client-tab' ).removeClass( 'active' ).attr( 'aria-selected', 'false' );
		$( this ).addClass( 'active' ).attr( 'aria-selected', 'true' );
		$( '.bmcp-client-panel' ).hide().removeClass( 'active' );
		$( '#bmcp-panel-' + client ).show().addClass( 'active' );
	} );

	// ---- Copy helpers ----
	function copyText( text, $btn ) {
		var originalText = $btn.text();
		navigator.clipboard.writeText( text ).then( function () {
			$btn.text( cfg.strings.copied || 'Copied!' );
			setTimeout( function () { $btn.text( originalText ); }, 2000 );
		} );
	}

	$( '#bmcp-btn-copy' ).on( 'click', function () {
		copyText( cfg.apiKey || '', $( this ) );
	} );

	$( '.bmcp-copy-config' ).on( 'click', function () {
		var target = $( this ).data( 'target' );
		var text   = $( '#' + target ).text();
		// Always swap in the live API key in case it was regenerated
		if ( cfg.apiKey ) {
			text = text.replace( /Bearer [^\s"\\]+/g, 'Bearer ' + cfg.apiKey );
		}
		copyText( text, $( this ) );
	} );

	// ---- Regenerate API key ----
	$( '#bmcp-btn-regen' ).on( 'click', function () {
		if ( ! confirm( cfg.strings.confirm_regen || 'Regenerate API key?' ) ) return;

		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Regenerating…' );

		$.post( cfg.ajaxUrl, {
			action: 'bmcp_regenerate_key',
			nonce:  cfg.nonce,
		}, function ( res ) {
			if ( res.success ) {
				cfg.apiKey = res.data.key;

				// Update masked display instantly
				$( '#bmcp-key-masked' ).text( res.data.masked );

				// Replace Bearer token in all config pre elements
				$( 'pre[id^="bmcp-config-"]' ).each( function () {
					$( this ).text( $( this ).text().replace( /Bearer\s+\S+/g, 'Bearer ' + res.data.key ) );
				} );

				// Visual confirmation (no alert popup)
				$btn.text( '✓ Updated' ).addClass( 'button-primary' );
				setTimeout( function () {
					$btn.text( 'Regenerate' ).removeClass( 'button-primary' ).prop( 'disabled', false );
				}, 2500 );
				return;
			}
			$btn.prop( 'disabled', false ).text( 'Regenerate' );
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Regenerate' );
		} );
	} );

	// ---- Clear activity log ----
	$( '#bmcp-btn-clear-log' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action: 'bmcp_clear_log',
			nonce:  cfg.nonce,
		}, function ( res ) {
			if ( res.success ) {
				$( '.bmcp-log-table' ).closest( 'div' ).find( 'table' ).replaceWith(
					'<p class="bmcp-empty">' + ( cfg.strings.cleared || 'Log cleared.' ) + '</p>'
				);
			}
			$btn.prop( 'disabled', false );
		} ).fail( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	// =========================================================================
	// MEMORY TAB
	// =========================================================================

	var memPage    = 1;
	var memCat     = '';
	var memSearch  = '';
	var memDebounce;

	var catLabels = {
		site: 'Site Info', design: 'Design Patterns', errors: 'Errors & Solutions',
		bricks: 'Bricks Patterns', preferences: 'Preferences', components: 'Components', general: 'General'
	};

	function loadMemories() {
		$( '#bmcp-memory-list' ).html( '<p class="bmcp-empty">Loading…</p>' );
		$( '#bmcp-memory-pagination' ).empty();

		$.post( cfg.ajaxUrl, {
			action:   'bmcp_memory_list',
			nonce:    cfg.nonce,
			page:     memPage,
			category: memCat,
			search:   memSearch,
		}, function ( res ) {
			if ( ! res.success ) return;
			renderMemoryTable( res.data );
		} );
	}

	function renderMemoryTable( data ) {
		var items = data.items || [];

		if ( ! items.length ) {
			$( '#bmcp-memory-list' ).html( '<p class="bmcp-empty">No memories found. Add one to get started.</p>' );
			$( '#bmcp-memory-pagination' ).empty();
			return;
		}

		var rows = items.map( function ( m ) {
			var preview = m.content.length > 90 ? m.content.substring( 0, 90 ) + '…' : m.content;
			var catLabel = catLabels[ m.category ] || m.category;
			var date = new Date( m.updated_at * 1000 );
			var dateStr = date.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );

			return '<tr>' +
				'<td>' +
					'<div class="bmcp-mem-title">' + escHtml( m.title ) + '</div>' +
					'<div class="bmcp-mem-preview">' + escHtml( preview ) + '</div>' +
				'</td>' +
				'<td><span class="bmcp-mem-badge cat">' + escHtml( catLabel ) + '</span></td>' +
				'<td><span class="bmcp-mem-badge ' + escHtml( m.importance ) + '">' + escHtml( m.importance ) + '</span></td>' +
				'<td style="color:var(--text-muted);font-size:0.78rem">' + dateStr + '</td>' +
				'<td>' +
					'<div class="bmcp-mem-actions">' +
						'<button class="button bmcp-btn-edit-mem" data-id="' + escHtml( m.id ) + '" data-mem=\'' + JSON.stringify( m ).replace( /'/g, '&#39;' ) + '\'>Edit</button>' +
						'<button class="button bmcp-btn-del-mem" data-id="' + escHtml( m.id ) + '">Del</button>' +
					'</div>' +
				'</td>' +
			'</tr>';
		} );

		var table = '<table class="bmcp-memory-table">' +
			'<thead><tr>' +
				'<th>Memory</th><th>Category</th><th>Importance</th><th>Updated</th><th></th>' +
			'</tr></thead>' +
			'<tbody>' + rows.join( '' ) + '</tbody>' +
		'</table>';

		$( '#bmcp-memory-list' ).html( table );
		renderPagination( data );
		bindMemoryActions();
	}

	function renderPagination( data ) {
		var $p = $( '#bmcp-memory-pagination' );
		if ( data.total_pages <= 1 ) { $p.empty(); return; }

		var html = '';
		if ( data.page > 1 ) {
			html += '<button class="bmcp-page-btn" data-page="' + ( data.page - 1 ) + '">← Prev</button>';
		}

		var start = Math.max( 1, data.page - 2 );
		var end   = Math.min( data.total_pages, data.page + 2 );
		for ( var i = start; i <= end; i++ ) {
			html += '<button class="bmcp-page-btn' + ( i === data.page ? ' active' : '' ) + '" data-page="' + i + '">' + i + '</button>';
		}

		if ( data.page < data.total_pages ) {
			html += '<button class="bmcp-page-btn" data-page="' + ( data.page + 1 ) + '">Next →</button>';
		}

		html += '<span style="color:var(--text-dim);font-size:0.78rem;margin-left:6px">' + data.total + ' total</span>';

		$p.html( html );
		$p.find( '.bmcp-page-btn' ).on( 'click', function () {
			memPage = parseInt( $( this ).data( 'page' ), 10 );
			loadMemories();
		} );
	}

	function bindMemoryActions() {
		$( '.bmcp-btn-edit-mem' ).on( 'click', function () {
			var mem = JSON.parse( $( this ).attr( 'data-mem' ) );
			openModal( mem );
		} );

		$( '.bmcp-btn-del-mem' ).on( 'click', function () {
			var id = $( this ).data( 'id' );
			if ( ! confirm( 'Delete this memory?' ) ) return;

			$.post( cfg.ajaxUrl, { action: 'bmcp_memory_delete', nonce: cfg.nonce, id: id }, function ( res ) {
				if ( res.success ) loadMemories();
			} );
		} );
	}

	// ---- Modal ----
	function openModal( mem ) {
		var editing = !! ( mem && mem.id );
		$( '#bmcp-modal-title' ).text( editing ? 'Edit Memory' : 'Add Memory' );
		$( '#bmcp-mem-id' ).val( editing ? mem.id : '' );
		$( '#bmcp-mem-cat' ).val( editing ? mem.category : 'general' );
		$( '#bmcp-mem-importance' ).val( editing ? mem.importance : 'medium' );
		$( '#bmcp-mem-title' ).val( editing ? mem.title : '' );
		$( '#bmcp-mem-content' ).val( editing ? mem.content : '' );
		$( '#bmcp-mem-tags' ).val( editing && mem.tags ? mem.tags.join( ', ' ) : '' );
		$( '#bmcp-memory-modal' ).show();
		$( '#bmcp-mem-title' ).focus();
	}

	function closeModal() {
		$( '#bmcp-memory-modal' ).hide();
	}

	$( '#bmcp-btn-add-memory' ).on( 'click', function () { openModal( null ); } );
	$( '.bmcp-modal-close, .bmcp-modal-cancel' ).on( 'click', closeModal );
	$( '.bmcp-modal-backdrop' ).on( 'click', closeModal );

	$( '#bmcp-modal-save' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Saving…' );

		$.post( cfg.ajaxUrl, {
			action:     'bmcp_memory_save',
			nonce:      cfg.nonce,
			id:         $( '#bmcp-mem-id' ).val(),
			category:   $( '#bmcp-mem-cat' ).val(),
			importance: $( '#bmcp-mem-importance' ).val(),
			title:      $( '#bmcp-mem-title' ).val(),
			content:    $( '#bmcp-mem-content' ).val(),
			tags:       $( '#bmcp-mem-tags' ).val(),
		}, function ( res ) {
			$btn.prop( 'disabled', false ).text( 'Save Memory' );
			if ( res.success ) {
				closeModal();
				loadMemories();
			} else {
				alert( res.data || 'Save failed.' );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Save Memory' );
		} );
	} );

	// ---- Filter + search ----
	$( '#bmcp-mem-cat-filter' ).on( 'change', function () {
		memCat  = $( this ).val();
		memPage = 1;
		loadMemories();
	} );

	$( '#bmcp-mem-search' ).on( 'input', function () {
		clearTimeout( memDebounce );
		var val = $( this ).val();
		memDebounce = setTimeout( function () {
			memSearch = val;
			memPage   = 1;
			loadMemories();
		}, 350 );
	} );

	// ---- Load when tab activates ----
	$( '[data-tab="memory"]' ).on( 'click', function () {
		if ( $( '#bmcp-memory-list' ).find( '.bmcp-empty' ).length ) {
			loadMemories();
		}
	} );

	// =========================================================================
	// HISTORY TAB
	// =========================================================================

	var histPage   = 1;
	var histArea   = '';
	var histLoaded = false;

	var areaLabels = {
		content: 'Content', header: 'Header', footer: 'Footer',
		global_settings: 'Global Settings', color_palette: 'Color Palette',
		global_classes: 'Global Classes', theme_styles: 'Theme Styles',
		components: 'Components'
	};

	function loadHistory() {
		$( '#bmcp-history-list' ).html( '<p class="bmcp-empty">Loading…</p>' );
		$( '#bmcp-history-pagination' ).empty();

		$.post( cfg.ajaxUrl, {
			action: 'bmcp_history_list',
			nonce:  cfg.nonce,
			page:   histPage,
			area:   histArea,
		}, function ( res ) {
			if ( ! res.success ) return;
			renderHistoryTable( res.data );
		} );
	}

	function renderHistoryTable( data ) {
		var items = data.items || [];

		if ( ! items.length ) {
			$( '#bmcp-history-list' ).html( '<p class="bmcp-empty">No snapshots yet. Snapshots are created automatically before every AI write operation.</p>' );
			$( '#bmcp-history-pagination' ).empty();
			return;
		}

		var rows = items.map( function ( s ) {
			var date    = new Date( s.created_at * 1000 );
			var dateStr = date.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } ) +
			              ' ' + date.toLocaleTimeString( undefined, { hour: '2-digit', minute: '2-digit' } );
			var areaLabel = areaLabels[ s.area ] || s.area;

			return '<tr>' +
				'<td style="color:var(--text-muted);font-size:0.78rem;white-space:nowrap">' + escHtml( dateStr ) + '</td>' +
				'<td>' +
					'<div class="bmcp-mem-title">' + escHtml( s.post_title ) + '</div>' +
					'<div class="bmcp-mem-preview">' + escHtml( s.description ) + '</div>' +
				'</td>' +
				'<td><span class="bmcp-mem-badge cat">' + escHtml( areaLabel ) + '</span></td>' +
				'<td style="color:var(--text-dim);font-size:0.78rem"><code>' + escHtml( s.tool_name || '—' ) + '</code></td>' +
				'<td>' +
					'<div class="bmcp-mem-actions">' +
						'<button class="button button-primary bmcp-btn-restore-snap" data-id="' + s.id + '" title="Restore this snapshot">↺ Restore</button>' +
						'<button class="button bmcp-btn-del-snap" data-id="' + s.id + '" title="Delete snapshot">Del</button>' +
					'</div>' +
				'</td>' +
			'</tr>';
		} );

		var table = '<table class="bmcp-memory-table">' +
			'<thead><tr><th>Time</th><th>Description</th><th>Area</th><th>Tool</th><th></th></tr></thead>' +
			'<tbody>' + rows.join( '' ) + '</tbody>' +
		'</table>';

		$( '#bmcp-history-list' ).html( table );
		renderHistPagination( data );
		bindHistoryActions();
	}

	function renderHistPagination( data ) {
		var $p = $( '#bmcp-history-pagination' );
		if ( data.total_pages <= 1 ) { $p.empty(); return; }

		var html = '';
		if ( data.page > 1 ) {
			html += '<button class="bmcp-page-btn" data-page="' + ( data.page - 1 ) + '">← Prev</button>';
		}
		var start = Math.max( 1, data.page - 2 );
		var end   = Math.min( data.total_pages, data.page + 2 );
		for ( var i = start; i <= end; i++ ) {
			html += '<button class="bmcp-page-btn' + ( i === data.page ? ' active' : '' ) + '" data-page="' + i + '">' + i + '</button>';
		}
		if ( data.page < data.total_pages ) {
			html += '<button class="bmcp-page-btn" data-page="' + ( data.page + 1 ) + '">Next →</button>';
		}
		html += '<span style="color:var(--text-dim);font-size:0.78rem;margin-left:6px">' + data.total + ' total</span>';
		$p.html( html );
		$p.find( '.bmcp-page-btn' ).on( 'click', function () {
			histPage = parseInt( $( this ).data( 'page' ), 10 );
			loadHistory();
		} );
	}

	function bindHistoryActions() {
		$( '.bmcp-btn-restore-snap' ).on( 'click', function () {
			var id   = $( this ).data( 'id' );
			var $btn = $( this );
			if ( ! confirm( 'Restore snapshot #' + id + '? The current state will be auto-saved first so you can undo.' ) ) return;

			$btn.prop( 'disabled', true ).text( 'Restoring…' );
			$.post( cfg.ajaxUrl, { action: 'bmcp_history_restore', nonce: cfg.nonce, id: id }, function ( res ) {
				if ( res.success ) {
					loadHistory();
				} else {
					alert( res.data || 'Restore failed.' );
					$btn.prop( 'disabled', false ).text( '↺ Restore' );
				}
			} ).fail( function () {
				$btn.prop( 'disabled', false ).text( '↺ Restore' );
			} );
		} );

		$( '.bmcp-btn-del-snap' ).on( 'click', function () {
			var id = $( this ).data( 'id' );
			if ( ! confirm( 'Delete snapshot #' + id + '? This cannot be undone.' ) ) return;
			$.post( cfg.ajaxUrl, { action: 'bmcp_history_delete', nonce: cfg.nonce, id: id }, function ( res ) {
				if ( res.success ) loadHistory();
			} );
		} );
	}

	$( '#bmcp-hist-area-filter' ).on( 'change', function () {
		histArea = $( this ).val();
		histPage = 1;
		loadHistory();
	} );

	$( '#bmcp-btn-clear-history' ).on( 'click', function () {
		if ( ! confirm( 'Clear all snapshots? This cannot be undone.' ) ) return;
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		$.post( cfg.ajaxUrl, { action: 'bmcp_history_clear', nonce: cfg.nonce }, function ( res ) {
			if ( res.success ) loadHistory();
			$btn.prop( 'disabled', false );
		} ).fail( function () { $btn.prop( 'disabled', false ); } );
	} );

	$( '[data-tab="history"]' ).on( 'click', function () {
		if ( ! histLoaded ) {
			histLoaded = true;
			loadHistory();
		}
	} );

	// =========================================================================
	// CAPABILITIES — per-tool toggle group actions
	// =========================================================================

	function updateGroupCount( group ) {
		var $rows   = $( '.bmcp-cap-tool-row[data-group="' + group + '"]' );
		var total   = $rows.length;
		var enabled = $rows.filter( function () {
			return $( this ).find( 'input[type="checkbox"]' ).prop( 'checked' );
		} ).length;

		$( '.bmcp-cap-group[data-group="' + group + '"] .bmcp-cap-count' ).text( enabled + ' / ' + total + ' enabled' );
		$( '.bmcp-cap-toggle-all[data-group="' + group + '"]' ).text( enabled === total ? 'Disable All' : 'Enable All' );
	}

	$( '.bmcp-cap-toggle-all' ).on( 'click', function () {
		var group    = $( this ).data( 'group' );
		var $checks  = $( '.bmcp-cap-tool-row[data-group="' + group + '"] input[type="checkbox"]' );
		var anyOff   = $checks.filter( ':not(:checked)' ).length > 0;
		$checks.prop( 'checked', anyOff );
		updateGroupCount( group );
	} );

	$( '.bmcp-cap-tool-row input[type="checkbox"]' ).on( 'change', function () {
		var group = $( this ).closest( '.bmcp-cap-tool-row' ).data( 'group' );
		updateGroupCount( group );
	} );

	// ---- Collapsible General setup block ----
	$( '#bmcp-general-toggle' ).on( 'click', function () {
		var $wrap    = $( '#bmcp-general-collapse-wrap' );
		var expanded = $wrap.hasClass( 'bmcp-expanded' );
		$wrap.toggleClass( 'bmcp-expanded', ! expanded );
		$( this )
			.text( expanded ? 'Show full guide ↓' : 'Show less ↑' )
			.attr( 'aria-expanded', String( ! expanded ) );
	} );

	// ---- Utility ----
	function escHtml( str ) {
		return String( str ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
} );
