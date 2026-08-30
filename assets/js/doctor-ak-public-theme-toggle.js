/**
 * Doctor AK Portal — Public site light/dark toggle (header, home page,
 * directories, auth pages, booking flow). Independent of the dashboards'
 * server-persisted toggle (doctor-ak-theme-toggle.js) — this one applies
 * `data-theme` to <html> and remembers the choice in localStorage, since a
 * public visitor may not be logged in. See the matching CSS selector
 * `html[data-theme='dark'] .dak-portal` in doctor-ak-auth.css.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'dakPublicTheme';

	applyStoredTheme();

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggles = document.querySelectorAll( '[data-dak-public-theme-toggle]' );

		if ( ! toggles.length ) {
			return;
		}

		var current = document.documentElement.getAttribute( 'data-theme' ) || 'light';

		toggles.forEach( function ( toggle ) {
			toggle.setAttribute( 'aria-pressed', 'dark' === current ? 'true' : 'false' );

			toggle.addEventListener( 'click', function () {
				var next = 'dark' === document.documentElement.getAttribute( 'data-theme' ) ? 'light' : 'dark';

				document.documentElement.setAttribute( 'data-theme', next );

				try {
					window.localStorage.setItem( STORAGE_KEY, next );
				} catch ( e ) {
					// Private-browsing/storage-blocked — theme just won't persist across page loads.
				}

				toggles.forEach( function ( el ) {
					el.setAttribute( 'aria-pressed', 'dark' === next ? 'true' : 'false' );
				} );
			} );
		} );
	} );

	/**
	 * Applied immediately (before DOMContentLoaded) so there's no
	 * light-then-dark flash on a repeat visitor's next page load.
	 *
	 * @return {void}
	 */
	function applyStoredTheme() {
		var stored = null;

		try {
			stored = window.localStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			stored = null;
		}

		if ( 'dark' === stored || 'light' === stored ) {
			document.documentElement.setAttribute( 'data-theme', stored );
		}
	}
} )();
