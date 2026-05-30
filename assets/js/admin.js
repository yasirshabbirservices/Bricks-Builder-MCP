/* Bricks Builder MCP — Admin JS */
jQuery( function ( $ ) {
	'use strict';

	var cfg = window.bmcpAdmin || {};

	// ---- SVG icon constants ----
	var ICON_EDIT    = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>';
	var ICON_TRASH   = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>';
	var ICON_RESTORE = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>';
	var ICON_SPIN    = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="bmcp-spin" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';
	var ICON_COPY    = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
	var ICON_CHECK   = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
	var ICON_REGEN   = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>';

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
	function showCopySuccess( $btn ) {
		$btn.html( ICON_CHECK ).addClass( 'bmcp-icon-btn--success' );
		setTimeout( function () {
			$btn.html( ICON_COPY ).removeClass( 'bmcp-icon-btn--success' );
		}, 2000 );
	}

	function fallbackCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
		document.body.appendChild( ta );
		ta.select();
		var ok = false;
		try { ok = document.execCommand( 'copy' ); } catch ( e ) {}
		document.body.removeChild( ta );
		return ok;
	}

	function copyText( text, $btn ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text )
				.then( function () { showCopySuccess( $btn ); } )
				.catch( function () {
					if ( fallbackCopy( text ) ) { showCopySuccess( $btn ); }
				} );
		} else {
			if ( fallbackCopy( text ) ) { showCopySuccess( $btn ); }
		}
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
		$btn.prop( 'disabled', true ).html( ICON_SPIN );

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

				// Success state — filled check, green tint, then revert
				$btn.html( ICON_CHECK ).addClass( 'bmcp-icon-btn--success' );
				setTimeout( function () {
					$btn.html( ICON_REGEN ).removeClass( 'bmcp-icon-btn--success' ).prop( 'disabled', false );
				}, 2500 );
				return;
			}
			$btn.prop( 'disabled', false ).html( ICON_REGEN );
		} ).fail( function () {
			$btn.prop( 'disabled', false ).html( ICON_REGEN );
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
						'<button class="button bmcp-icon-btn bmcp-btn-edit-mem" data-id="' + escHtml( m.id ) + '" data-mem=\'' + JSON.stringify( m ).replace( /'/g, '&#39;' ) + '\' data-tooltip="Edit memory" aria-label="Edit memory">' + ICON_EDIT + '</button>' +
						'<button class="button bmcp-icon-btn bmcp-btn-del-mem" data-id="' + escHtml( m.id ) + '" data-tooltip="Delete memory" aria-label="Delete memory">' + ICON_TRASH + '</button>' +
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
			$modalTrigger = $( this );
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
	var $modalTrigger = null;

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
		if ( $modalTrigger ) {
			$modalTrigger.focus();
			$modalTrigger = null;
		}
	}

	$( '#bmcp-btn-add-memory' ).on( 'click', function () {
		$modalTrigger = $( this );
		openModal( null );
	} );
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
						'<button class="button bmcp-btn-restore-snap" data-id="' + s.id + '" data-tooltip="Restore snapshot" aria-label="Restore snapshot">' + ICON_RESTORE + ' Restore</button>' +
						'<button class="button bmcp-icon-btn bmcp-btn-del-snap" data-id="' + s.id + '" data-tooltip="Delete snapshot" aria-label="Delete snapshot">' + ICON_TRASH + '</button>' +
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

			$btn.prop( 'disabled', true ).html( ICON_SPIN + ' Restoring…' );
			$.post( cfg.ajaxUrl, { action: 'bmcp_history_restore', nonce: cfg.nonce, id: id }, function ( res ) {
				if ( res.success ) {
					loadHistory();
				} else {
					alert( res.data || 'Restore failed.' );
					$btn.prop( 'disabled', false ).html( ICON_RESTORE + ' Restore' );
				}
			} ).fail( function () {
				$btn.prop( 'disabled', false ).html( ICON_RESTORE + ' Restore' );
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
		if ( expanded ) {
			$wrap.removeClass( 'bmcp-expanded' );
			$( this ).text( 'Show full guide ↓' ).attr( 'aria-expanded', 'false' );
		} else {
			$wrap.addClass( 'bmcp-expanded' );
			$( this ).text( 'Show less ↑' ).attr( 'aria-expanded', 'true' );
		}
	} );

	// ---- Branded notice dismiss ----
	$( document ).on( 'click', '.bmcp-notice-close', function () {
		$( this ).closest( '.bmcp-notice' ).slideUp( 200, function () {
			$( this ).remove();
		} );
	} );
	if ( $( '#bmcp-settings-notice' ).length ) {
		setTimeout( function () {
			$( '#bmcp-settings-notice' ).slideUp( 300, function () { $( this ).remove(); } );
		}, 4000 );
	}

	// =========================================================================
	// COLOR PICKERS
	// =========================================================================

	$( document ).on( 'input', '.bmcp-color-swatch', function () {
		var $hex = $( '#' + $( this ).data( 'target' ) );
		$hex.val( $( this ).val().toUpperCase() );
	} );

	$( document ).on( 'input', '.bmcp-color-hex', function () {
		var val   = $( this ).val();
		var $row  = $( this ).closest( '.bmcp-color-row' );
		var $sw   = $row.find( '.bmcp-color-swatch' );
		if ( /^#[0-9A-Fa-f]{6}$/.test( val ) ) {
			$sw.val( val.toLowerCase() );
		}
	} );

	// =========================================================================
	// REPEATERS
	// =========================================================================

	function bmcpReindexRepeater( $repeater ) {
		var fieldBase = $repeater.data( 'field-base' );
		$repeater.find( '.bmcp-repeater-row' ).each( function ( i ) {
			$( this ).attr( 'data-index', i );
			$( this ).find( 'input' ).each( function () {
				var name  = $( this ).attr( 'name' );
				var clean = name.replace( /\[\d+\]/, '[' + i + ']' );
				$( this ).attr( 'name', clean );
			} );
		} );
	}

	function bmcpMakeRow( fieldBase, index, fields ) {
		var inputs = fields.map( function ( f ) {
			return '<input type="' + f.type + '" ' +
				'name="' + fieldBase + '[' + index + '][' + f.key + ']" ' +
				'value="' + ( f.value || '' ) + '" ' +
				'placeholder="' + ( f.placeholder || '' ) + '" ' +
				'data-role="' + f.key + '" />';
		} ).join( '' );
		return '<div class="bmcp-repeater-row" data-index="' + index + '">' +
			inputs +
			'<button type="button" class="button bmcp-repeater-remove" aria-label="Remove row">&#x2715;</button>' +
		'</div>';
	}

	$( document ).on( 'click', '.bmcp-repeater-add', function () {
		var $btn      = $( this );
		var $repeater = $( '#' + $btn.data( 'repeater' ) );
		var fieldBase = $repeater.data( 'field-base' );
		var fields    = JSON.parse( $btn.attr( 'data-fields' ) );
		var newIndex  = $repeater.find( '.bmcp-repeater-row' ).length;
		$repeater.append( bmcpMakeRow( fieldBase, newIndex, fields ) );
	} );

	$( document ).on( 'click', '.bmcp-repeater-remove', function () {
		var $repeater = $( this ).closest( '.bmcp-repeater' );
		$( this ).closest( '.bmcp-repeater-row' ).remove();
		bmcpReindexRepeater( $repeater );
	} );

	// ---- Business Profile Export ----
	$( '#bmcp-export-profile-btn' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Exporting…' );

		$.post( ajaxurl, { action: 'bmcp_export_profile', nonce: cfg.nonce }, function ( res ) {
			$btn.prop( 'disabled', false ).text( 'Export Profile JSON' );
			if ( ! res.success ) { alert( 'Export failed: ' + ( res.data || 'Unknown error' ) ); return; }

			var json    = JSON.stringify( res.data, null, 2 );
			var blob    = new Blob( [ json ], { type: 'application/json' } );
			var url     = URL.createObjectURL( blob );
			var a       = document.createElement( 'a' );
			var site    = ( res.data.site_url || 'site' ).replace( /https?:\/\//, '' ).replace( /[^a-z0-9]/gi, '-' );
			a.href      = url;
			a.download  = 'bmcp-profile-' + site + '.json';
			a.click();
			URL.revokeObjectURL( url );
		} );
	} );

	// ---- Business Profile Import ----
	$( '#bmcp-import-profile-btn' ).on( 'click', function () {
		var json    = $( '#bmcp-import-profile-json' ).val().trim();
		var $status = $( '#bmcp-import-profile-status' );
		var $btn    = $( this );

		if ( ! json ) { $status.text( 'Paste JSON first.' ).css( 'color', '#d63638' ); return; }

		$btn.prop( 'disabled', true ).text( 'Importing…' );
		$status.text( '' );

		$.post( ajaxurl, { action: 'bmcp_import_profile', nonce: cfg.nonce, profile_json: json }, function ( res ) {
			$btn.prop( 'disabled', false ).text( 'Import Profile JSON' );
			if ( res.success ) {
				$status.text( '✓ ' + ( res.data.message || 'Imported.' ) ).css( 'color', '#00a32a' );
				$( '#bmcp-import-profile-json' ).val( '' );
			} else {
				$status.text( '✗ ' + ( res.data || 'Import failed.' ) ).css( 'color', '#d63638' );
			}
		} );
	} );

	// ---- Utility ----
	function escHtml( str ) {
		return String( str ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
} );
