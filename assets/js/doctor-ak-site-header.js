/**
 * Doctor AK Portal — Site-wide header behaviour.
 *
 * Handles the mobile menu toggle, tap-to-open submenus (desktop uses
 * hover via CSS, touch devices need a click target instead), the
 * logged-in account dropdown, the header's "Book Now" dropdown, and the
 * "coming soon" toast for booking options that don't have a module yet
 * (Book Lab / Book Pharmacy) — the latter also covers the matching buttons
 * in the home page hero (templates/directory/home-page.php), since this
 * script loads on every front-end page (see Site_Header::enqueue_assets()).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initMobileToggle();
		initSubmenuToggles();
		initDropdown( 'dak-site-header-account', 'dak-site-header-account-menu' );
		initDropdown( 'dak-site-header-book-trigger', 'dak-site-header-book-menu' );
		initMegaMenuAutoFocus( '.dak-site-header-doctors-item' );
		initMegaMenuSearch( 'dak-site-header-mega-search-input', '.dak-site-header-mega-card' );
		initComingSoonToast();
		initHeaderScrollState();
	} );

	/**
	 * Toggles `.is-scrolled` on the header the moment the page scrolls away
	 * from the very top — CSS (doctor-ak-site-header.css) uses that class to
	 * collapse the utility bar (logo/Call Us/Email Us) away, leaving only
	 * the main nav bar (already kept pinned via .dak-site-header's own
	 * position: sticky) visibly frozen at the top. A rAF flag coalesces
	 * rapid scroll events into at most one class toggle per frame.
	 */
	function initHeaderScrollState() {
		var header = document.querySelector( '.dak-site-header' );

		if ( ! header ) {
			return;
		}

		var ticking = false;

		function applyState() {
			header.classList.toggle( 'is-scrolled', window.scrollY > 4 );
			ticking = false;
		}

		window.addEventListener(
			'scroll',
			function () {
				if ( ! ticking ) {
					window.requestAnimationFrame( applyState );
					ticking = true;
				}
			},
			{ passive: true }
		);

		applyState();
	}

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
	 * Desktop's side of "focus the search box the moment a mega-menu opens"
	 * — it opens on :hover (pure CSS, no JS event of its own to hang this
	 * off), so `mouseenter` on the menu item stands in for that. The
	 * tap-to-open path on mobile (initSubmenuToggles() above) covers the
	 * other way these menus open. A no-op wherever the selector matches
	 * nothing, e.g. before a directory_url exists yet.
	 *
	 * @param {string} itemSelector CSS selector for the `<li
	 *                              class="menu-item-has-children">`.
	 */
	function initMegaMenuAutoFocus( itemSelector ) {
		var item = document.querySelector( itemSelector );

		if ( ! item ) {
			return;
		}

		item.addEventListener( 'mouseenter', function () {
			focusMegaMenuSearch( item );
		} );
	}

	/**
	 * Focuses a mega-menu's own search input, if the given menu item has one
	 * — a no-op everywhere else (e.g. a mega-menu with no search box, like
	 * the Services nav item's plain cascading dropdown), so this is safe to
	 * call generically from both the ways any given submenu can open.
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
			var input = menuItem.querySelector( '.dak-site-header-mega-search input' );

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
	 *
	 * Scoped to the search input's own dropdown panel (via `.closest()`),
	 * not the whole document, so a second mega-menu's search/cards/no-
	 * results elsewhere on the page can't cross-interfere with this one.
	 *
	 * @param {string} inputId      Element id of this menu's search `<input>`.
	 * @param {string} itemSelector CSS selector (scoped within the panel) for the filterable items.
	 */
	function initMegaMenuSearch( inputId, itemSelector ) {
		var input = document.getElementById( inputId );
		var panel = input ? input.closest( '.dak-site-header-mega-panel' ) : null;

		if ( ! panel ) {
			return;
		}

		var items = panel.querySelectorAll( itemSelector );
		var noResults = panel.querySelector( '.dak-site-header-mega-no-results' );

		input.addEventListener( 'input', function () {
			var query = input.value.trim().toLowerCase();
			var visibleCount = 0;

			items.forEach( function ( item ) {
				var isVisible = '' === query || item.textContent.toLowerCase().indexOf( query ) !== -1;

				item.classList.toggle( 'dak-hidden', ! isVisible );

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
	 * Wires a trigger button + its dropdown menu: click to toggle, click
	 * outside to close. Shared by the logged-in account dropdown and the
	 * "Book Now" dropdown — same open/close behaviour, different content.
	 *
	 * @param {string} triggerId Trigger button's element id.
	 * @param {string} menuId    Dropdown menu's element id.
	 */
	function initDropdown( triggerId, menuId ) {
		var trigger = document.getElementById( triggerId );
		var menu = document.getElementById( menuId );

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

	/**
	 * Shows a short-lived toast when a not-yet-built booking option is
	 * tapped (Book Lab / Book Pharmacy) — the button's own
	 * `data-dak-coming-soon` attribute carries the message to show, already
	 * built server-side (feature name + clinic phone, when known).
	 */
	function initComingSoonToast() {
		var toast = null;
		var hideTimeout = null;

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-dak-coming-soon]' );

			if ( ! trigger ) {
				return;
			}

			event.preventDefault();

			if ( ! toast ) {
				toast = document.createElement( 'div' );
				toast.className = 'dak-coming-soon-toast';
				toast.setAttribute( 'role', 'status' );
				toast.setAttribute( 'aria-live', 'polite' );
				document.body.appendChild( toast );
			}

			toast.textContent = trigger.getAttribute( 'data-dak-coming-soon' );
			toast.classList.add( 'is-visible' );

			window.clearTimeout( hideTimeout );
			hideTimeout = window.setTimeout( function () {
				toast.classList.remove( 'is-visible' );
			}, 4000 );
		} );
	}
} )();
