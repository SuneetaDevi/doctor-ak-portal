/**
 * Doctor AK Portal — Dashboard behaviour (mobile sidebar toggle, desktop
 * sidebar collapse, specialty tag "+N" expand/collapse, topbar profile menu).
 */
( function () {
	'use strict';

	var COLLAPSE_STORAGE_KEY = 'dakSidebarCollapsed';

	document.addEventListener( 'DOMContentLoaded', function () {
		wireSidebarToggle();
		wireSidebarCollapseToggle();
		wireSpecialtyTagToggles();
		wireTopbarProfileMenu();
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
	 * Wires the desktop sidebar's collapse/expand toggle (icons-only when
	 * collapsed — see doctor-ak-dashboard.css's `.dak-dashboard-sidebar.is-collapsed`
	 * rules). State is persisted so it survives a page reload/navigation.
	 */
	function wireSidebarCollapseToggle() {
		var toggle = document.getElementById( 'dak-sidebar-collapse-toggle' );
		var sidebar = document.getElementById( 'dak-dashboard-sidebar' );

		if ( ! toggle || ! sidebar ) {
			return;
		}

		if ( 'true' === readStoredPreference() ) {
			setCollapsed( true );
		}

		toggle.addEventListener( 'click', function () {
			setCollapsed( ! sidebar.classList.contains( 'is-collapsed' ) );
		} );

		function setCollapsed( collapsed ) {
			sidebar.classList.toggle( 'is-collapsed', collapsed );
			toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			storePreference( collapsed );
		}

		function readStoredPreference() {
			try {
				return window.localStorage.getItem( COLLAPSE_STORAGE_KEY );
			} catch ( error ) {
				return null;
			}
		}

		function storePreference( collapsed ) {
			try {
				window.localStorage.setItem( COLLAPSE_STORAGE_KEY, collapsed ? 'true' : 'false' );
			} catch ( error ) {
				// Private-browsing/storage-disabled — the toggle still works for this page view.
			}
		}
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

	/**
	 * Wires the topbar avatar button's "Edit Profile"/"Logout" dropdown —
	 * opens on click, closes on outside click, Escape, or selecting an item.
	 */
	function wireTopbarProfileMenu() {
		var wrapper = document.getElementById( 'dak-topbar-profile' );
		var trigger = document.getElementById( 'dak-topbar-profile-trigger' );

		if ( ! wrapper || ! trigger ) {
			return;
		}

		trigger.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			var isOpen = wrapper.classList.toggle( 'is-open' );
			trigger.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( wrapper.classList.contains( 'is-open' ) && ! wrapper.contains( event.target ) ) {
				closeProfileMenu();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && wrapper.classList.contains( 'is-open' ) ) {
				closeProfileMenu();
			}
		} );

		function closeProfileMenu() {
			wrapper.classList.remove( 'is-open' );
			trigger.setAttribute( 'aria-expanded', 'false' );
		}
	}
} )();
