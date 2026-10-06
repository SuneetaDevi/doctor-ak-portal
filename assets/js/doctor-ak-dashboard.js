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
	 * Two kinds of "+N" expanders, both delegated from document so they keep
	 * working after a live filter swaps the list HTML (a direct per-element
	 * binding never reached rows rendered later). This is now the only place
	 * either is wired — the admin dashboard script used to bind the
	 * specialty chip too, so every click toggled twice and did nothing.
	 *
	 *  - [data-specialty-toggle]: the older chip that reveals
	 *    .dak-specialty-tag-extra siblings (Patients table, sidebar).
	 *  - [data-dak-disclosure]: a button with aria-controls pointing at a
	 *    [hidden] list (Doctors directory), keeping aria-expanded in sync.
	 */
	function wireSpecialtyTagToggles() {
		document.addEventListener( 'click', function ( event ) {
			var disclosure = event.target.closest( '[data-dak-disclosure]' );

			if ( disclosure ) {
				var panel = document.getElementById( disclosure.getAttribute( 'aria-controls' ) );

				if ( ! panel ) {
					return;
				}

				var willExpand = 'true' !== disclosure.getAttribute( 'aria-expanded' );

				disclosure.setAttribute( 'aria-expanded', willExpand ? 'true' : 'false' );
				panel.hidden = ! willExpand;
				disclosure.textContent = willExpand ? disclosure.getAttribute( 'data-less-label' ) : disclosure.getAttribute( 'data-more-label' );
				return;
			}

			var toggle = event.target.closest( '[data-specialty-toggle]' );

			if ( ! toggle ) {
				return;
			}

			var container = toggle.closest( '[data-specialty-tags]' );

			if ( ! container ) {
				return;
			}

			var expanded = toggle.classList.toggle( 'is-expanded' );

			toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
			container.querySelectorAll( '.dak-specialty-tag-extra' ).forEach( function ( tag ) {
				tag.classList.toggle( 'dak-hidden', ! expanded );
			} );

			toggle.textContent = expanded ? toggle.getAttribute( 'data-less-label' ) : toggle.getAttribute( 'data-more-label' );
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
