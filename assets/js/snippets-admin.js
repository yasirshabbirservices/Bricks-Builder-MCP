/**
 * Bricks Builder MCP — Snippets List Page
 * Handles: status toggle, row delete, bulk actions, select-all
 */
( function () {
	'use strict';

	const cfg = window.bmcpSnippets || {};

	/* ── helpers ──────────────────────────────────────────────────────── */
	function ajax( action, data ) {
		data.action = action;
		data.nonce  = cfg.nonce;
		return fetch( cfg.ajaxUrl, {
			method  : 'POST',
			headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
			body    : new URLSearchParams( data ),
		} ).then( r => r.json() );
	}

	function showNotice( msg, type ) {
		const el = document.createElement( 'div' );
		el.className = 'notice notice-' + type + ' is-dismissible';
		el.style.cssText = 'margin:8px 0;font-size:.875rem;';
		const p = document.createElement( 'p' );
		p.textContent = msg;
		el.appendChild( p );
		const wrap = document.querySelector( '#wpbody-content' );
		if ( wrap ) wrap.insertBefore( el, wrap.firstChild );
		setTimeout( () => el.remove(), 4000 );
	}

	/* ── select-all ───────────────────────────────────────────────────── */
	const selectAll  = document.getElementById( 'bmcp-snip-select-all' );
	const bulkBar    = document.getElementById( 'bmcp-snip-bulk-bar' );
	const bulkCount  = document.getElementById( 'bmcp-snip-bulk-count' );
	const bulkDelete = document.getElementById( 'bmcp-snip-bulk-delete' );

	function getChecked() {
		return Array.from( document.querySelectorAll( '.bmcp-snip-cb:checked' ) );
	}

	function updateBulkBar() {
		const checked = getChecked();
		if ( ! bulkBar ) return;
		if ( checked.length ) {
			bulkBar.style.display = 'flex';
			if ( bulkCount ) bulkCount.textContent = checked.length + ' selected';
		} else {
			bulkBar.style.display = 'none';
		}
		// row highlight
		document.querySelectorAll( '.bmcp-snip-cb' ).forEach( cb => {
			const row = cb.closest( 'tr' );
			if ( row ) row.classList.toggle( 'selected', cb.checked );
		} );
	}

	if ( selectAll ) {
		selectAll.addEventListener( 'change', () => {
			document.querySelectorAll( '.bmcp-snip-cb' )
				.forEach( cb => { cb.checked = selectAll.checked; } );
			updateBulkBar();
		} );
	}

	document.querySelectorAll( '.bmcp-snip-cb' ).forEach( cb => {
		cb.addEventListener( 'change', updateBulkBar );
	} );

	/* ── status toggle ────────────────────────────────────────────────── */
	document.querySelectorAll( '.bmcp-snip-status-toggle' ).forEach( toggle => {
		toggle.addEventListener( 'change', function () {
			const id     = this.dataset.id;
			const status = this.checked ? 'active' : 'inactive';
			const pill   = this.closest( 'td' ).querySelector( '.bmcp-snip-status-label' );

			ajax( 'bmcp_snip_toggle', { id, status } ).then( res => {
				if ( res.success ) {
					if ( pill ) {
						pill.textContent = status === 'active' ? 'Active' : 'Inactive';
						pill.className   = 'bmcp-snip-status-label ' +
							( status === 'active' ? 'bmcp-snip-status-on' : 'bmcp-snip-status-off' );
					}
				} else {
					// revert
					this.checked = ! this.checked;
					showNotice( ( res.data && res.data.message ) || 'Could not update status.', 'error' );
				}
			} ).catch( () => {
				this.checked = ! this.checked;
				showNotice( 'Request failed.', 'error' );
			} );
		} );
	} );

	/* ── row delete ───────────────────────────────────────────────────── */
	document.querySelectorAll( '.bmcp-snip-row-delete' ).forEach( btn => {
		btn.addEventListener( 'click', function () {
			const id    = this.dataset.id;
			const title = this.dataset.title || 'this snippet';
			if ( ! confirm( 'Delete "' + title + '"? This cannot be undone.' ) ) return;

			ajax( 'bmcp_snip_delete', { id } ).then( res => {
				if ( res.success ) {
					const row = this.closest( 'tr' );
					if ( row ) {
						row.style.transition = 'opacity 0.25s';
						row.style.opacity = '0';
						setTimeout( () => row.remove(), 260 );
					}
					showNotice( 'Snippet deleted.', 'success' );
				} else {
					showNotice( ( res.data && res.data.message ) || 'Could not delete.', 'error' );
				}
			} ).catch( () => showNotice( 'Request failed.', 'error' ) );
		} );
	} );

	/* ── bulk delete ──────────────────────────────────────────────────── */
	if ( bulkDelete ) {
		bulkDelete.addEventListener( 'click', () => {
			const ids = getChecked().map( cb => cb.value );
			if ( ! ids.length ) return;
			if ( ! confirm( 'Delete ' + ids.length + ' snippet(s)? This cannot be undone.' ) ) return;

			Promise.all( ids.map( id => ajax( 'bmcp_snip_delete', { id } ) ) ).then( results => {
				const ok  = results.filter( r => r.success ).length;
				const fail = results.length - ok;
				if ( ok ) {
					getChecked().forEach( cb => {
						const row = cb.closest( 'tr' );
						if ( row ) row.remove();
					} );
					updateBulkBar();
					if ( selectAll ) selectAll.checked = false;
					showNotice( ok + ' snippet(s) deleted.', 'success' );
				}
				if ( fail ) showNotice( fail + ' deletion(s) failed.', 'error' );
			} );
		} );
	}

	/* ── safe mode toggle ─────────────────────────────────────────────── */
	const safeModeToggle = document.getElementById( 'bmcp-safe-mode-toggle' );
	if ( safeModeToggle ) {
		safeModeToggle.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			ajax( 'bmcp_snip_safe_mode', { enable: this.dataset.enable } ).then( res => {
				if ( res.success ) {
					location.reload();
				} else {
					showNotice( 'Could not change safe mode.', 'error' );
				}
			} );
		} );
	}

} )();
