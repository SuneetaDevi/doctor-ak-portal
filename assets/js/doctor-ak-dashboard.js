/**
 * Doctor AK Portal — Dashboard behaviour (mobile sidebar toggle, specialty
 * tag "+N" expand/collapse).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		wireSidebarToggle();
		wireSpecialtyTagToggles();
	} );

	function wireSidebarToggle() {
		var toggle = document.getElementById( 'dak-sidebar-toggle' );
		var sidebar = document.getElementById( 'dak-dashboard-sidebar' );

		if ( ! toggle || ! sidebar ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var isOpen = sidebar.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );
	}

	/**
	 * Wires the sidebar's specialty "+N" chip — expands/collapses the extra
	 * tags beyond the first two shown by default (mirrors the same pattern
	 * on the admin dashboard's Doctors table, see doctor-ak-admin-dashboard.js).
	 */
	function wireSpecialtyTagToggles() {
		document.querySelectorAll( '[data-specialty-toggle]' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var container = toggle.closest( '[data-specialty-tags]' );

				if ( ! container ) {
					return;
				}

				var expanded = toggle.classList.toggle( 'is-expanded' );

				container.querySelectorAll( '.dak-specialty-tag-extra' ).forEach( function ( tag ) {
					tag.classList.toggle( 'dak-hidden', ! expanded );
				} );

				toggle.textContent = expanded ? toggle.getAttribute( 'data-less-label' ) : toggle.getAttribute( 'data-more-label' );
			} );
		} );
	}
} )();
