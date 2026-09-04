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
		initMegaMenuSearch();
		initMegaMenuAutoFocus();
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
					focusMegaMenuSearch( parentItem );
				}
			} );
		} );
	}

	/**
	 * Desktop's side of "focus the search box the moment the Doctors
	 * mega-menu opens" — it opens on :hover (pure CSS, no JS event of its
	 * own to hang this off), so `mouseenter` on the menu item stands in for
	 * that. The tap-to-open path on mobile (initSubmenuToggles() above)
	 * covers the other way this menu opens.
	 */
	function initMegaMenuAutoFocus() {
		var item = document.querySelector( '.dak-site-header-doctors-item' );

		if ( ! item ) {
			return;
		}

		item.addEventListener( 'mouseenter', function () {
			focusMegaMenuSearch( item );
		} );
	}

	/**
	 * Focuses the Doctors mega-menu's search input, if the given menu item
	 * has one — a no-op everywhere else, so this is safe to call generically
	 * from both the ways this specific submenu can open.
	 *
	 * The call is deferred a tick: it runs inside the very same mouseenter/
	 * click handler that triggers the CSS which reveals the menu (hover ->
	 * visibility:hidden->visible on desktop, .is-open -> display:none->block
	 * on mobile), and at that exact instant the browser still considers the
	 * input hidden — a synchronous focus() on a still-hidden element is
	 * silently ignored. setTimeout( …, 0 ) waits for the browser to finish
	 * applying the style change first.
	 *
	 * @param {Element} menuItem The <li class="menu-item-has-children"> that just opened.
	 */
	function focusMegaMenuSearch( menuItem ) {
		setTimeout( function () {
			var input = menuItem.querySelector( '#dak-site-header-mega-search-input' );

			if ( input ) {
				input.focus();
			}
		}, 0 );
	}

	/**
	 * Doctors mega-menu's search box: filters the already-rendered By
	 * Speciality cards as you type, the same way the doctors directory's own
	 * search filters its already-rendered doctor cards
	 * (assets/js/doctor-ak-directory.js) — no AJAX round trip, since
	 * everything it can match against is already sitting in the dropdown.
	 * Pressing Enter still submits the form to the full directory search
	 * (its `?s=` is read there too — see doctor-ak-directory.js), which is
	 * the only way to search by doctor NAME: names aren't rendered as rows
	 * in this dropdown (only a handful of avatar photos, with no visible
	 * name text to filter against), just specialties.
	 */
	function initMegaMenuSearch() {
		var input = document.getElementById( 'dak-site-header-mega-search-input' );

		if ( ! input ) {
			return;
		}

		var cards = document.querySelectorAll( '.dak-site-header-mega-card' );
		var noResults = document.querySelector( '.dak-site-header-mega-no-results' );

		input.addEventListener( 'input', function () {
			var query = input.value.trim().toLowerCase();
			var visibleCount = 0;

			cards.forEach( function ( card ) {
				var isVisible = '' === query || card.textContent.toLowerCase().indexOf( query ) !== -1;

				card.classList.toggle( 'dak-hidden', ! isVisible );

				if ( isVisible ) {
					visibleCount++;
				}
			} );

			if ( noResults ) {
				noResults.classList.toggle( 'dak-hidden', '' === query || visibleCount > 0 );
			}
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
