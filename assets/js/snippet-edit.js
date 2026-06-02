/**
 * Bricks Builder MCP — Snippet Edit Page
 * Handles: CodeMirror init + mode switching, AJAX save, conditions
 *          repeater, shortcode copy, delete with confirm.
 */
( function () {
	'use strict';

	/* ── bootstrap data ───────────────────────────────────────────────── */
	const dataEl = document.getElementById( 'bmcp-snip-edit-data' );
	if ( ! dataEl ) return;
	const cfg = JSON.parse( dataEl.textContent || dataEl.innerHTML );

	// ajaxurl is always set by WordPress in admin; bmcpSnippets.ajaxUrl is preferred
	const ajaxUrl = ( window.bmcpSnippets && window.bmcpSnippets.ajaxUrl )
		? window.bmcpSnippets.ajaxUrl
		: ( window.ajaxurl || '' );
	const nonce = ( window.bmcpSnippets && window.bmcpSnippets.nonce )
		? window.bmcpSnippets.nonce
		: '';

	/* ── helpers ──────────────────────────────────────────────────────── */
	function ajax( action, data ) {
		data.action = action;
		data.nonce  = nonce;
		return fetch( ajaxUrl, {
			method  : 'POST',
			headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
			body    : new URLSearchParams( data ),
		} ).then( r => {
			// If response isn't JSON (PHP error / wrong URL), surface the status
			const ct = r.headers.get( 'content-type' ) || '';
			if ( ! ct.includes( 'json' ) ) {
				return r.text().then( txt => {
					throw new Error( 'Non-JSON response (' + r.status + '): ' + txt.slice( 0, 120 ) );
				} );
			}
			return r.json();
		} );
	}

	function setSaveStatus( msg, cls ) {
		const el = document.getElementById( 'bmcp-snip-save-status' );
		if ( ! el ) return;
		el.textContent = msg;
		el.className   = 'bmcp-snip-save-status visible ' + ( cls || '' );
		if ( cls !== 'saving' ) {
			setTimeout( () => {
				el.className = 'bmcp-snip-save-status';
			}, 3000 );
		}
	}

	/* ── Default starter code ────────────────────────────────────────── */
	const defaultCode = cfg.defaultCode || {};

	/* ── CodeMirror ───────────────────────────────────────────────────── */
	let editor = null;

	function initEditor( mode ) {
		const textarea = document.getElementById( 'bmcp-snip-code' );
		if ( ! textarea ) return;

		if ( typeof wp === 'undefined' || ! wp.codeEditor ) return;

		const settings = wp.codeEditor.defaultSettings
			? Object.assign( {}, wp.codeEditor.defaultSettings )
			: {};

		settings.codemirror = Object.assign( {}, settings.codemirror || {}, {
			mode          : mode || cfg.cmMode || 'text/plain',
			lineNumbers   : true,
			matchBrackets : true,
			autoCloseBrackets: true,
			indentUnit    : 4,
			tabSize       : 4,
			indentWithTabs: false,
			lineWrapping  : false,
			theme         : 'default', // overridden by our CSS
			extraKeys     : {
				Tab: cm => {
					if ( cm.somethingSelected() ) cm.indentSelection( 'add' );
					else cm.replaceSelection( '    ', 'end' );
				},
			},
		} );

		const instance = wp.codeEditor.initialize( textarea, settings );
		editor = instance.codemirror;
	}

	initEditor( cfg.cmMode );

	/* ── type tabs ────────────────────────────────────────────────────── */
	const hiddenType = document.getElementById( 'bmcp-snip-type' ) || {};
	let   currentType = cfg.type || 'php';

	document.querySelectorAll( '.bmcp-snip-type-tab' ).forEach( tab => {
		tab.addEventListener( 'click', function () {
			document.querySelectorAll( '.bmcp-snip-type-tab' )
				.forEach( t => t.classList.remove( 'active' ) );
			this.classList.add( 'active' );

			const prevType  = currentType;  // must capture BEFORE reassigning
			currentType     = this.dataset.type;
			const mode      = this.dataset.mode || 'text/plain';

			// switch CodeMirror mode
			if ( editor ) {
				editor.setOption( 'mode', mode );
				// For new snippets, swap in the default starter code for the new type
				if ( cfg.isNew ) {
					const cur     = editor.getValue();
					const prevDef = defaultCode[ prevType ] || '';
					if ( cur === '' || cur === prevDef ) {
						editor.setValue( defaultCode[ currentType ] || '' );
					}
				}
			}

			// show/hide URL field and code editor
			const urlRow     = document.getElementById( 'bmcp-url-row' );
			const codeWrap   = document.getElementById( 'bmcp-code-wrap' );
			const openingTag = document.getElementById( 'bmcp-opening-tag' );
			const isUrl      = currentType === 'javascript_url' || currentType === 'css_url';
			if ( urlRow )     urlRow.style.display     = isUrl ? '' : 'none';
			if ( codeWrap )   codeWrap.style.display   = isUrl ? 'none' : '';
			if ( openingTag ) openingTag.style.display = currentType === 'php' ? '' : 'none';

			// show/hide hook field (PHP + HTML only)
			const hookLabel = document.querySelector( '.bmcp-snip-hook-label' );
			const hookSel   = document.getElementById( 'bmcp-snip-hook' );
			const hookCustom = document.getElementById( 'bmcp-snip-hook-custom' );
			const showHook  = currentType === 'php' || currentType === 'html';
			if ( hookLabel ) hookLabel.style.display = showHook ? '' : 'none';
			if ( hookSel )   hookSel.closest( 'select' ) && ( hookSel.style.display = showHook ? '' : 'none' );
			if ( hookCustom && hookCustom.style.display !== 'none' ) {
				hookCustom.style.display = showHook ? '' : 'none';
			}
		} );
	} );

	/* ── custom hook ──────────────────────────────────────────────────── */
	const hookSelect = document.getElementById( 'bmcp-snip-hook' );
	const hookCustom = document.getElementById( 'bmcp-snip-hook-custom' );
	if ( hookSelect && hookCustom ) {
		hookSelect.addEventListener( 'change', function () {
			hookCustom.style.display = this.value === '__custom__' ? '' : 'none';
			if ( this.value !== '__custom__' ) hookCustom.value = '';
		} );
	}

	/* ── collect form data ────────────────────────────────────────────── */
	function collectData() {
		const code = editor ? editor.getValue() : ( document.getElementById( 'bmcp-snip-code' ) || {} ).value || '';
		const hookSel  = document.getElementById( 'bmcp-snip-hook' );
		const hookVal  = hookSel ? hookSel.value : 'init';
		const hook     = hookVal === '__custom__'
			? ( ( document.getElementById( 'bmcp-snip-hook-custom' ) || {} ).value || 'init' ).trim()
			: hookVal;

		const statusToggle = document.getElementById( 'bmcp-snip-status-toggle' );

		return {
			id          : cfg.snippetId || 0,
			title       : ( document.getElementById( 'bmcp-snip-title' )    || {} ).value || '',
			code        : code,
			type        : currentType,
			status      : statusToggle && statusToggle.checked ? 'active' : 'inactive',
			location    : ( document.getElementById( 'bmcp-snip-location' ) || {} ).value || 'everywhere',
			hook        : hook,
			priority    : parseInt( ( document.getElementById( 'bmcp-snip-priority' ) || {} ).value || '10', 10 ),
			description : ( document.getElementById( 'bmcp-snip-desc' )     || {} ).value || '',
			tags        : ( document.getElementById( 'bmcp-snip-tags' )     || {} ).value || '',
			url         : ( document.getElementById( 'bmcp-snip-url' )      || {} ).value || '',
			conditions  : JSON.stringify( collectConditions() ),
		};
	}

	/* ── save ─────────────────────────────────────────────────────────── */
	const saveBtn = document.getElementById( 'bmcp-snip-save' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', () => {
			const data = collectData();
			if ( ! data.title.trim() ) {
				setSaveStatus( '⚠ Name is required', 'error' );
				const titleEl = document.getElementById( 'bmcp-snip-title' );
				if ( titleEl ) titleEl.focus();
				return;
			}

			saveBtn.disabled = true;
			setSaveStatus( 'Saving…', 'saving' );

			ajax( 'bmcp_snip_save', data ).then( res => {
				saveBtn.disabled = false;
				if ( res.success ) {
					setSaveStatus( '✓ Saved', 'success' );
					if ( cfg.isNew && res.data && res.data.id ) {
						// redirect to edit page for the newly created snippet
						const editUrl = cfg.listUrl + '&action=edit&snippet_id=' + res.data.id;
						setTimeout( () => { location.href = editUrl; }, 600 );
					}
				} else {
					const msg = res.data && res.data.message ? res.data.message : 'Save failed.';
					setSaveStatus( '✗ ' + msg, 'error' );
				}
			} ).catch( err => {
				saveBtn.disabled = false;
				const msg = ( err && err.message ) ? err.message.slice( 0, 100 ) : 'Request failed';
				setSaveStatus( '✗ ' + msg, 'error' );
				console.error( '[bmcp] save error:', err );
			} );
		} );
	}

	/* ── status toggle display ────────────────────────────────────────── */
	const statusToggle = document.getElementById( 'bmcp-snip-status-toggle' );
	const statusText   = document.getElementById( 'bmcp-snip-status-text' );
	if ( statusToggle && statusText ) {
		statusToggle.addEventListener( 'change', function () {
			const on  = this.checked;
			const span = statusText.querySelector( 'span' );
			if ( span ) {
				span.textContent = on ? 'Active' : 'Inactive';
				span.className   = on ? 'bmcp-snip-status-on' : 'bmcp-snip-status-off';
			}
		} );
	}

	/* ── shortcode copy ───────────────────────────────────────────────── */
	const copyBtn = document.getElementById( 'bmcp-copy-shortcode' );
	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', () => {
			const val = document.getElementById( 'bmcp-snip-shortcode' );
			if ( ! val ) return;
			navigator.clipboard.writeText( val.textContent.trim() ).then( () => {
				const orig = copyBtn.textContent;
				copyBtn.textContent = 'Copied!';
				setTimeout( () => { copyBtn.textContent = orig; }, 1800 );
			} ).catch( () => {
				// Fallback for older browsers
				const sel = window.getSelection();
				const range = document.createRange();
				range.selectNodeContents( val );
				sel.removeAllRanges();
				sel.addRange( range );
				document.execCommand( 'copy' );
				sel.removeAllRanges();
			} );
		} );
	}

	/* ── delete ───────────────────────────────────────────────────────── */
	const deleteBtn = document.getElementById( 'bmcp-snip-delete' );
	if ( deleteBtn ) {
		deleteBtn.addEventListener( 'click', function () {
			const title = this.dataset.title || 'this snippet';
			if ( ! confirm( 'Delete "' + title + '"? This cannot be undone.' ) ) return;

			this.disabled = true;
			ajax( 'bmcp_snip_delete', { id: this.dataset.id } ).then( res => {
				if ( res.success ) {
					location.href = cfg.listUrl;
				} else {
					this.disabled = false;
					const msg = res.data && res.data.message ? res.data.message : 'Delete failed.';
					setSaveStatus( '✗ ' + msg, 'error' );
				}
			} ).catch( () => {
				this.disabled = false;
				setSaveStatus( '✗ Request failed', 'error' );
			} );
		} );
	}

	/* ══════════════════════════════════════════════════════════════════
	   CONDITIONS BUILDER
	   ══════════════════════════════════════════════════════════════════ */
	const conditionsList = document.getElementById( 'bmcp-conditions-list' );
	const addCondBtn     = document.getElementById( 'bmcp-add-condition' );

	const CONDITION_TYPES = [
		{ value: 'post_type',    label: 'Post Type' },
		{ value: 'user_role',    label: 'User Role' },
		{ value: 'logged_in',   label: 'Logged In' },
		{ value: 'url_pattern', label: 'URL Pattern' },
	];
	const CONDITION_OPS = [
		{ value: 'equals',     label: 'equals' },
		{ value: 'not_equals', label: 'not equals' },
	];

	function buildConditionRow( cond ) {
		cond = cond || {};
		const row = document.createElement( 'div' );
		row.className = 'bmcp-condition-row';

		// type select
		const typeSelect = document.createElement( 'select' );
		typeSelect.setAttribute( 'aria-label', 'Condition type' );
		CONDITION_TYPES.forEach( t => {
			const opt = document.createElement( 'option' );
			opt.value       = t.value;
			opt.textContent = t.label;
			if ( cond.type === t.value ) opt.selected = true;
			typeSelect.appendChild( opt );
		} );

		// op select
		const opSelect = document.createElement( 'select' );
		opSelect.style.maxWidth = '110px';
		opSelect.setAttribute( 'aria-label', 'Condition operator' );
		CONDITION_OPS.forEach( o => {
			const opt = document.createElement( 'option' );
			opt.value       = o.value;
			opt.textContent = o.label;
			if ( ( cond.op || 'equals' ) === o.value ) opt.selected = true;
			opSelect.appendChild( opt );
		} );

		// value input
		const valInput = document.createElement( 'input' );
		valInput.type        = 'text';
		valInput.placeholder = 'value';
		valInput.value       = cond.value || '';
		valInput.setAttribute( 'aria-label', 'Condition value' );

		// remove button
		const removeBtn = document.createElement( 'button' );
		removeBtn.type      = 'button';
		removeBtn.className = 'bmcp-condition-row-remove';
		removeBtn.setAttribute( 'aria-label', 'Remove condition' );
		const svgNS = 'http://www.w3.org/2000/svg';
		const svg   = document.createElementNS( svgNS, 'svg' );
		svg.setAttribute( 'width', '12' );
		svg.setAttribute( 'height', '12' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'fill', 'none' );
		svg.setAttribute( 'stroke', 'currentColor' );
		svg.setAttribute( 'stroke-width', '2.5' );
		svg.setAttribute( 'stroke-linecap', 'round' );
		svg.setAttribute( 'aria-hidden', 'true' );
		const line1 = document.createElementNS( svgNS, 'line' );
		line1.setAttribute( 'x1', '18' ); line1.setAttribute( 'y1', '6' );
		line1.setAttribute( 'x2', '6' );  line1.setAttribute( 'y2', '18' );
		const line2 = document.createElementNS( svgNS, 'line' );
		line2.setAttribute( 'x1', '6' );  line2.setAttribute( 'y1', '6' );
		line2.setAttribute( 'x2', '18' ); line2.setAttribute( 'y2', '18' );
		svg.appendChild( line1 );
		svg.appendChild( line2 );
		removeBtn.appendChild( svg );
		removeBtn.addEventListener( 'click', () => row.remove() );

		row.appendChild( typeSelect );
		row.appendChild( opSelect );
		row.appendChild( valInput );
		row.appendChild( removeBtn );

		return row;
	}

	function collectConditions() {
		if ( ! conditionsList ) return [];
		return Array.from( conditionsList.querySelectorAll( '.bmcp-condition-row' ) ).map( row => {
			const selects = row.querySelectorAll( 'select' );
			const input   = row.querySelector( 'input[type="text"]' );
			return {
				type  : selects[0] ? selects[0].value : 'post_type',
				op    : selects[1] ? selects[1].value : 'equals',
				value : input ? input.value.trim() : '',
			};
		} );
	}

	// For new snippets, populate editor with starter code for the initial type
	if ( cfg.isNew && editor ) {
		const starter = defaultCode[ cfg.currentType || 'php' ] || '';
		if ( starter ) editor.setValue( starter );
	}

	// seed existing conditions
	if ( conditionsList && Array.isArray( cfg.conditions ) ) {
		cfg.conditions.forEach( c => conditionsList.appendChild( buildConditionRow( c ) ) );
	}

	if ( addCondBtn ) {
		addCondBtn.addEventListener( 'click', () => {
			if ( conditionsList ) conditionsList.appendChild( buildConditionRow() );
		} );
	}

	/* ── keyboard shortcut: Ctrl/Cmd+S to save ───────────────────────── */
	document.addEventListener( 'keydown', e => {
		if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) {
			e.preventDefault();
			if ( saveBtn && ! saveBtn.disabled ) saveBtn.click();
		}
	} );

} )();
