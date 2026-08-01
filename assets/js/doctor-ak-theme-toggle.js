/**
 * Doctor AK Portal — Dashboard light/dark theme toggle.
 *
 * Flips the `data-theme` attribute on the `.dak-dashboard` wrapper instantly
 * (so it feels immediate, no waiting on the network), then persists the
 * choice against the logged-in user so it's remembered on their next visit.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var buttons = document.querySelectorAll( '.dak-theme-toggle' );
		var dashboard = document.querySelector( '.dak-dashboard' );

		if ( ! buttons.length || ! dashboard || ! window.dakTheme ) {
			return;
		}

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var newTheme = 'dark' === dashboard.getAttribute( 'data-theme' ) ? 'light' : 'dark';
				dashboard.setAttribute( 'data-theme', newTheme );

				var formData = new FormData();
				formData.append( 'action', 'doctor_ak_toggle_theme' );
				formData.append( 'nonce', window.dakTheme.nonce );

				fetch( window.dakTheme.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
					.catch( function () {
						// Non-fatal: the toggle already applied visually; a failed
						// save just means it won't be remembered next visit.
					} );
			} );
		} );
	} );
} )();
