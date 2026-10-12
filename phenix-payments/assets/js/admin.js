( function () {
	'use strict';
	var cfg = window.PhenixPayAdmin || {};

	var form = document.getElementById( 'phx-settings-form' );
	var env = document.getElementById( 'phx-env' );
	if ( form && env ) {
		form.addEventListener( 'submit', function ( e ) {
			if ( env.value === 'live' && env.getAttribute( 'data-current' ) !== 'live' && ! window.confirm( cfg.confirmLive ) ) {
				e.preventDefault();
			}
		} );
	}

	document.querySelectorAll( '[data-phx-copy]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var el = document.getElementById( btn.getAttribute( 'data-phx-copy' ) );
			if ( el && navigator.clipboard ) {
				navigator.clipboard.writeText( el.textContent.trim() ).then( function () {
					var old = btn.textContent;
					btn.textContent = cfg.copied || 'Copied';
					setTimeout( function () {
						btn.textContent = old;
					}, 1500 );
				} );
			}
		} );
	} );
} )();
