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
} );
