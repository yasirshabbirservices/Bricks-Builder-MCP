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
		el.style.cssText = 'margin:8px 0 0;font-size:.875rem;';
		const p = document.createElement( 'p' );
		p.textContent = msg;
		el.appendChild( p );
		const wrap = document.querySelector( '#wpbody-content' );
		if ( wrap ) wrap.insertBefore( el, wrap.firstChild );
		setTimeout( () => el.remove(), 4000 );
	}

	/* ── select-all ───────────────────────────────────────────────────── */
	// HTML uses #bmcp-select-all and .bmcp-snip-check
	const selectAll = document.getElementById( 'bmcp-select-all' );
	const bulkBar   = document.getElementById( 'bmcp-bulk-bar' );
	const bulkCount = document.getElementById( 'bmcp-bulk-count' );

	function getChecked() {
		return Array.from( document.querySelectorAll( '.bmcp-snip-check:checked' ) );
	}

	function updateBulkBar() {
		const checked = getChecked();
		if ( bulkBar ) bulkBar.style.display = checked.length ? 'flex' : 'none';
		if ( bulkCount ) bulkCount.textContent = checked.length;
		// highlight selected rows
		document.querySelectorAll( '.bmcp-snip-check' ).forEach( cb => {
			const row = cb.closest( 'tr' );
			if ( row ) row.classList.toggle( 'selected', cb.checked );
		} );
	}

	if ( selectAll ) {
		selectAll.addEventListener( 'change', () => {
			document.querySelectorAll( '.bmcp-snip-check' )
				.forEach( cb => { cb.checked = selectAll.checked; } );
			updateBulkBar();
		} );
	}

	document.querySelectorAll( '.bmcp-snip-check' ).forEach( cb => {
		cb.addEventListener( 'change', () => {
			if ( selectAll ) {
				const all   = document.querySelectorAll( '.bmcp-snip-check' ).length;
				const chkd  = document.querySelectorAll( '.bmcp-snip-check:checked' ).length;
				selectAll.indeterminate = chkd > 0 && chkd < all;
				selectAll.checked       = chkd === all;
			}
			updateBulkBar();
		} );
	} );

	/* ── status toggle (button.bmcp-snip-toggle) ──────────────────────── */
	document.querySelectorAll( '.bmcp-snip-toggle' ).forEach( btn => {
		btn.addEventListener( 'click', function () {
			const id        = this.dataset.id;
			const isOn      = this.classList.contains( 'is-on' );
			const newStatus = isOn ? 'inactive' : 'active';

			// optimistic UI update
			this.classList.toggle( 'is-on', ! isOn );
			this.classList.toggle( 'is-off', isOn );
			const row = this.closest( 'tr' );
			if ( row ) {
				row.classList.toggle( 'is-active',   ! isOn );
				row.classList.toggle( 'is-inactive',  isOn );
			}

			ajax( 'bmcp_snip_toggle', { id, status: newStatus } ).then( res => {
				if ( ! res.success ) {
					// revert on failure
					this.classList.toggle( 'is-on',  isOn );
					this.classList.toggle( 'is-off', ! isOn );
					if ( row ) {
						row.classList.toggle( 'is-active',  isOn );
						row.classList.toggle( 'is-inactive', ! isOn );
					}
					showNotice( ( res.data && res.data.message ) || 'Could not update status.', 'error' );
				}
			} ).catch( () => {
				this.classList.toggle( 'is-on',  isOn );
				this.classList.toggle( 'is-off', ! isOn );
				showNotice( 'Request failed.', 'error' );
			} );
		} );
	} );

	/* ── row delete (button[data-delete]) ─────────────────────────────── */
	document.querySelectorAll( '[data-delete]' ).forEach( btn => {
		btn.addEventListener( 'click', function () {
			const id  = this.dataset.delete;
			const row = this.closest( 'tr' );
			const title = ( row && row.querySelector( '.bmcp-snip-title' ) )
				? row.querySelector( '.bmcp-snip-title' ).textContent.trim()
				: 'this snippet';

			if ( ! confirm( 'Delete "' + title + '"? This cannot be undone.' ) ) return;

			ajax( 'bmcp_snip_delete', { id } ).then( res => {
				if ( res.success ) {
					if ( row ) {
						row.style.transition = 'opacity 0.25s';
						row.style.opacity = '0';
						setTimeout( () => { row.remove(); updateBulkBar(); }, 260 );
					}
					showNotice( 'Snippet deleted.', 'success' );
				} else {
					showNotice( ( res.data && res.data.message ) || 'Could not delete.', 'error' );
				}
			} ).catch( () => showNotice( 'Request failed.', 'error' ) );
		} );
	} );

	/* ── bulk actions (data-bulk buttons) ─────────────────────────────── */
	document.querySelectorAll( '[data-bulk]' ).forEach( btn => {
		btn.addEventListener( 'click', function () {
			const action = this.dataset.bulk;
			const ids    = getChecked().map( cb => cb.value );
			if ( ! ids.length ) return;

			if ( action === 'delete' ) {
				if ( ! confirm( 'Delete ' + ids.length + ' snippet(s)? This cannot be undone.' ) ) return;
				Promise.all( ids.map( id => ajax( 'bmcp_snip_delete', { id } ) ) ).then( results => {
					const ok   = results.filter( r => r.success ).length;
					const fail = results.length - ok;
					if ( ok ) {
						getChecked().forEach( cb => {
							const row = cb.closest( 'tr' );
							if ( row ) row.remove();
						} );
						updateBulkBar();
						if ( selectAll ) { selectAll.checked = false; selectAll.indeterminate = false; }
						showNotice( ok + ' snippet(s) deleted.', 'success' );
					}
					if ( fail ) showNotice( fail + ' deletion(s) failed.', 'error' );
				} );
			}

			if ( action === 'activate' || action === 'deactivate' ) {
				const status = action === 'activate' ? 'active' : 'inactive';
				Promise.all( ids.map( id => ajax( 'bmcp_snip_toggle', { id, status } ) ) ).then( results => {
					const ok = results.filter( r => r.success ).length;
					if ( ok ) {
						showNotice( ok + ' snippet(s) ' + status + 'd.', 'success' );
						setTimeout( () => location.reload(), 600 );
					}
				} );
			}
		} );
	} );

} )();
