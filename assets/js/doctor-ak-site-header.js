/**
 * Doctor AK Portal — Site-wide header behaviour.
 *
 * Handles the mobile menu toggle, tap-to-open submenus (desktop uses
 * hover via CSS, touch devices need a click target instead), and the
 * logged-in account dropdown.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initMobileToggle();
		initSubmenuToggles();
		initAccountMenu();
	} );

	/**
	 * Shows/hides the nav on small screens.
	 */
	function initMobileToggle() {
		var toggle = document.getElementById( 'dak-site-header-toggle' );
		var nav = document.getElementById( 'dak-site-header-nav' );

		if ( ! toggle || ! nav ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var isOpen = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );
	}

	/**
	 * On touch/mobile, tapping a parent menu item toggles its sub-menu
	 * instead of navigating (desktop reveals sub-menus on :hover via CSS).
	 */
	function initSubmenuToggles() {
		document.querySelectorAll( '.dak-site-header-menu .menu-item-has-children > a' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				if ( window.innerWidth > 900 ) {
					return;
				}

				var parentItem = link.parentElement;

				if ( ! parentItem.classList.contains( 'is-open' ) ) {
					event.preventDefault();
					parentItem.classList.add( 'is-open' );
				}
			} );
		} );
	}

	/**
	 * Toggles the logged-in user's account dropdown.
	 */
	function initAccountMenu() {
		var trigger = document.getElementById( 'dak-site-header-account' );
		var menu = document.getElementById( 'dak-site-header-account-menu' );

		if ( ! trigger || ! menu ) {
			return;
		}

		trigger.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			var isOpen = menu.classList.toggle( 'is-open' );
			trigger.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! menu.contains( event.target ) && event.target !== trigger ) {
				menu.classList.remove( 'is-open' );
				trigger.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}
} )();
