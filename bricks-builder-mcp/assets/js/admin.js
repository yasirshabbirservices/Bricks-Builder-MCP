/* Bricks Builder MCP — Admin JS */
jQuery( function ( $ ) {
	'use strict';

	var cfg = window.bmcpAdmin || {};

	// ---- Tab switching ----
	$( '.bmcp-tab' ).on( 'click', function ( e ) {
		e.preventDefault();
		var tab = $( this ).data( 'tab' );
		$( '.bmcp-tab' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '.bmcp-panel' ).hide();
		$( '#tab-' + tab ).show();
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
		// For the config snippet, ensure it carries the live key (catches edge cases)
		if ( target === 'bmcp-config-snippet' && cfg.apiKey ) {
			text = text.replace( /Bearer [^\s"]+/, 'Bearer ' + cfg.apiKey );
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

				// Replace any Bearer token in snippet using regex — works even if oldKey was stale
				var $snippet = $( '#bmcp-config-snippet' );
				$snippet.text( $snippet.text().replace( /Bearer\s+\S+/g, 'Bearer ' + res.data.key ) );

				// Also update data-key attribute so copy always has latest
				$snippet.attr( 'data-key', res.data.key );

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

	// ---- Utility ----
	function escHtml( str ) {
		return String( str ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
} );
