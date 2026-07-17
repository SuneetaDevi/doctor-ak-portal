/**
 * Doctor AK Portal — Dashboard behaviour (mobile sidebar toggle).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.getElementById( 'dak-sidebar-toggle' );
		var sidebar = document.getElementById( 'dak-dashboard-sidebar' );

		if ( ! toggle || ! sidebar ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var isOpen = sidebar.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );
	} );
} )();
