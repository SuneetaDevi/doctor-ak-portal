/**
 * Doctor AK Portal — site header behaviour (templates/site-header.php).
 *
 * Menus: every `[data-dak-nav-item]` holds one disclosure button
 * (aria-controls → its panel). Click or tap toggles it; only one menu is open
 * at a time; Escape closes it and returns focus to its button; a click
 * outside, tabbing out of it, or following one of its links closes it. On
 * desktop with a real mouse, Doctors/Services/Clinics/Book Now also open
 * after a short hover delay and close after a short grace period, so passing
 * over them doesn't flicker; clicking a hover-opened menu keeps it open.
 *
 * Mobile (< 1024px): the nav becomes a modal drawer — focus moves into it,
 * Tab stays inside, Escape / the close button / the backdrop close it and
 * focus returns to the menu button; the page behind doesn't scroll. Panels
 * expand inline there, as an accordion.
 *
 * Panel searches filter what's already in the panel (no requests):
 * doctors by name or specialty (the same match the doctors directory uses),
 * services by name across every category, clinics by name, area or city
 * (plus the city chips).
 *
 * Also: keeps --dak-site-header-offset (sticky bar + WP toolbar height) on
 * <html> for anchor scrolling, and shows the "coming soon" toast for any
 * [data-dak-coming-soon] button on the page.
 */
( function () {
	'use strict';

	var OPEN_DELAY = 140;
	var CLOSE_DELAY = 240;
	var RESULT_LIMIT_ATTR = 'data-dak-nav-limit';

	var desktopQuery = window.matchMedia( '(min-width: 1024px)' );
	var hoverQuery = window.matchMedia( '(hover: hover) and (pointer: fine)' );

	var header = null;
	var drawer = null;
	var drawerToggle = null;
	var scrim = null;
	var entries = [];
	var strings = {};
	var openEntry = null;
	var drawerOpen = false;

	initComingSoonToast();

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
		header = document.querySelector( '.dak-site-header' );

		if ( ! header ) {
			return;
		}

		try {
			strings = JSON.parse( header.getAttribute( 'data-dak-nav-strings' ) || '{}' );
		} catch ( e ) {
			strings = {};
		}

		drawer = document.getElementById( 'dak-site-header-drawer' );
		drawerToggle = document.getElementById( 'dak-site-header-toggle' );
		scrim = header.querySelector( '.dak-site-header-scrim' );

		document.querySelectorAll( '[data-dak-nav-item]' ).forEach( setUpEntry );

		initDrawer();
		initSearches();
		initServiceCategories();
		initClinicCities();
		initOffset();

		document.addEventListener( 'click', onDocumentClick );
		document.addEventListener( 'keydown', onKeydown );

		window.addEventListener( 'resize', function () {
			if ( openEntry && isDesktop() ) {
				fitPanel( openEntry );
			}
		} );

		// Back/forward cache: never come back to a page with a menu left open.
		window.addEventListener( 'pageshow', function ( event ) {
			if ( event.persisted ) {
				closeMenu( openEntry, false );
				closeDrawer( false );
			}
		} );

		onChange( desktopQuery, function () {
			closeMenu( openEntry, false );

			if ( isDesktop() ) {
				closeDrawer( false );
			}
		} );
	}

	function isDesktop() {
		return desktopQuery.matches;
	}

	function onChange( query, callback ) {
		if ( query.addEventListener ) {
			query.addEventListener( 'change', callback );
		} else if ( query.addListener ) {
			query.addListener( callback );
		}
	}

	/* ------------------------------------------------------------ Menus */

	function setUpEntry( item ) {
		var trigger = item.querySelector( '[aria-controls]' );
		var panel = trigger ? document.getElementById( trigger.getAttribute( 'aria-controls' ) ) : null;

		if ( ! trigger || ! panel ) {
			return;
		}

		var entry = {
			key: item.getAttribute( 'data-dak-nav-item' ),
			item: item,
			trigger: trigger,
			panel: panel,
			via: '',
			openTimer: 0,
			closeTimer: 0,
		};

		entries.push( entry );

		trigger.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			clearTimers( entry );

			if ( openEntry === entry ) {
				// A menu that opened on hover stays open when clicked —
				// the click means "I want this one", not "close it".
				if ( 'hover' === entry.via ) {
					entry.via = 'click';
					return;
				}

				closeMenu( entry, false );
				return;
			}

			openMenu( entry, 'click' );
		} );

		// Following any link in the panel closes it (and the drawer).
		panel.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( 'a[href]' );

			if ( link ) {
				closeMenu( entry, false );
				closeDrawer( false );
			}
		} );

		// Keyboard users tabbing past a desktop menu close it behind them.
		item.addEventListener( 'focusout', function ( event ) {
			if ( openEntry !== entry || ! isDesktop() ) {
				return;
			}

			if ( event.relatedTarget && ! item.contains( event.relatedTarget ) ) {
				closeMenu( entry, false );
			}
		} );

		if ( 'account' !== entry.key ) {
			item.addEventListener( 'mouseenter', function () {
				if ( ! canHover() ) {
					return;
				}

				window.clearTimeout( entry.closeTimer );

				if ( openEntry === entry ) {
					return;
				}

				entry.openTimer = window.setTimeout( function () {
					openMenu( entry, 'hover' );
				}, OPEN_DELAY );
			} );

			item.addEventListener( 'mouseleave', function () {
				window.clearTimeout( entry.openTimer );

				if ( openEntry !== entry || 'hover' !== entry.via || ! canHover() ) {
					return;
				}

				entry.closeTimer = window.setTimeout( function () {
					// Don't pull a menu away from someone typing in it.
					if ( openEntry === entry && 'hover' === entry.via && ! entry.item.contains( document.activeElement ) ) {
						closeMenu( entry, false );
					}
				}, CLOSE_DELAY );
			} );
		}
	}

	function canHover() {
		return hoverQuery.matches && isDesktop() && ! drawerOpen;
	}

	function clearTimers( entry ) {
		window.clearTimeout( entry.openTimer );
		window.clearTimeout( entry.closeTimer );
	}

	function openMenu( entry, via ) {
		if ( openEntry && openEntry !== entry ) {
			closeMenu( openEntry, false );
		}

		clearTimers( entry );
		entry.via = via;
		entry.panel.hidden = false;
		entry.trigger.setAttribute( 'aria-expanded', 'true' );
		openEntry = entry;

		if ( isDesktop() ) {
			fitPanel( entry );
		}
	}

	function closeMenu( entry, restoreFocus ) {
		if ( ! entry ) {
			return;
		}

		clearTimers( entry );
		entry.panel.hidden = true;
		entry.trigger.setAttribute( 'aria-expanded', 'false' );
		entry.via = '';

		if ( openEntry === entry ) {
			openEntry = null;
		}

		if ( restoreFocus ) {
			entry.trigger.focus();
		}
	}

	/**
	 * Keeps a desktop panel inside the viewport: its body scrolls when the
	 * content is taller than the space left below the sticky bar.
	 */
	function fitPanel( entry ) {
		var top = entry.panel.getBoundingClientRect().top;
		var room = Math.max( 240, Math.floor( window.innerHeight - top - 24 ) );

		entry.panel.style.setProperty( '--dak-nav-panel-max', room + 'px' );
	}

	function onDocumentClick( event ) {
		if ( openEntry && ! openEntry.item.contains( event.target ) ) {
			// Inside the drawer, other triggers handle themselves.
			closeMenu( openEntry, false );
		}
	}

	function onKeydown( event ) {
		if ( 'Escape' === event.key || 'Esc' === event.key ) {
			if ( openEntry ) {
				event.preventDefault();
				closeMenu( openEntry, true );
				return;
			}

			if ( drawerOpen ) {
				event.preventDefault();
				closeDrawer( true );
			}

			return;
		}

		if ( 'Tab' === event.key && drawerOpen ) {
			trapFocus( event );
		}
	}

	/* ------------------------------------------------------------ Drawer */

	function initDrawer() {
		if ( ! drawer || ! drawerToggle ) {
			return;
		}

		drawerToggle.addEventListener( 'click', function () {
			if ( drawerOpen ) {
				closeDrawer( true );
			} else {
				openDrawer();
			}
		} );

		header.querySelectorAll( '[data-dak-drawer-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				closeDrawer( true );
			} );
		} );

		// Plain links in the drawer (Home, Videos, Blogs…) close it too —
		// a same-page anchor like Videos wouldn't otherwise.
		drawer.addEventListener( 'click', function ( event ) {
			if ( drawerOpen && event.target.closest( 'a[href]' ) ) {
				closeDrawer( false );
			}
		} );
	}

	function openDrawer() {
		if ( drawerOpen || isDesktop() ) {
			return;
		}

		closeMenu( openEntry, false );
		drawerOpen = true;
		drawer.classList.add( 'is-open' );
		drawer.setAttribute( 'role', 'dialog' );
		drawer.setAttribute( 'aria-modal', 'true' );
		drawer.setAttribute( 'aria-label', drawer.getAttribute( 'data-dak-drawer-label' ) || 'Menu' );
		drawerToggle.setAttribute( 'aria-expanded', 'true' );
		document.documentElement.classList.add( 'dak-nav-drawer-open' );

		if ( scrim ) {
			scrim.hidden = false;
		}

		var close = drawer.querySelector( '.dak-site-header-drawer-close' );

		if ( close ) {
			close.focus();
		}
	}

	function closeDrawer( restoreFocus ) {
		if ( ! drawerOpen ) {
			return;
		}

		if ( openEntry && drawer.contains( openEntry.item ) ) {
			closeMenu( openEntry, false );
		}

		drawerOpen = false;
		drawer.classList.remove( 'is-open' );
		drawer.removeAttribute( 'role' );
		drawer.removeAttribute( 'aria-modal' );
		drawer.removeAttribute( 'aria-label' );
		drawerToggle.setAttribute( 'aria-expanded', 'false' );
		document.documentElement.classList.remove( 'dak-nav-drawer-open' );

		if ( scrim ) {
			scrim.hidden = true;
		}

		if ( restoreFocus ) {
			drawerToggle.focus();
		}
	}

	function trapFocus( event ) {
		var focusables = Array.prototype.filter.call(
			drawer.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), [tabindex]:not([tabindex="-1"])' ),
			function ( el ) {
				return el.offsetParent !== null || el === document.activeElement;
			}
		);

		if ( ! focusables.length ) {
			return;
		}

		var first = focusables[ 0 ];
		var last = focusables[ focusables.length - 1 ];
		var active = document.activeElement;

		if ( ! drawer.contains( active ) ) {
			event.preventDefault();
			first.focus();
		} else if ( event.shiftKey && active === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && active === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	/* ------------------------------------------------------------ Search */

	/** A translated string from the template, with %s / %d / %1$s… filled in. */
	function t( key, values ) {
		var text = strings[ key ] || '';
		var list = [].concat( values === undefined ? [] : values );
		var next = 0;

		return text.replace( /%(?:(\d)\$)?[sd]/g, function ( match, position ) {
			var value = position ? list[ position - 1 ] : list[ next++ ];

			return value === undefined ? '' : String( value );
		} );
	}

	function count( one, many, number ) {
		return t( 1 === number ? one : many, number );
	}

	function normalise( value ) {
		return ( value || '' ).trim().toLowerCase().replace( /\s+/g, ' ' );
	}

	function initSearches() {
		header.querySelectorAll( '[data-dak-search-kind]' ).forEach( function ( box ) {
			var kind = box.getAttribute( 'data-dak-search-kind' );
			var card = box.closest( '.dak-nav-panel-card' );
			var input = box.querySelector( '[data-dak-nav-search]' );
			var clear = box.querySelector( '[data-dak-nav-search-clear]' );

			if ( ! card || ! input ) {
				return;
			}

			var run = function () {
				if ( clear ) {
					clear.hidden = '' === input.value;
				}

				if ( 'doctors' === kind ) {
					searchDoctors( card, input.value );
				} else if ( 'services' === kind ) {
					searchServices( card, input.value );
				} else if ( 'clinics' === kind ) {
					filterClinics( card );
				}
			};

			input.addEventListener( 'input', run );

			if ( clear ) {
				clear.addEventListener( 'click', function () {
					input.value = '';
					run();
					input.focus();
				} );
			}

			// The services list has no page to search on, so Enter stays put.
			if ( 'services' === kind ) {
				input.addEventListener( 'keydown', function ( event ) {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
					}
				} );
			}

			if ( 'FORM' === box.tagName ) {
				box.addEventListener( 'submit', function () {
					input.value = doctorQuery( input.value, kind );
					closeMenu( openEntry, false );
					closeDrawer( false );
				} );
			}

			card.searchRun = run;
		} );
	}

	/**
	 * The doctors directory matches names stored without the "Dr." title,
	 * so a typed "Dr. Ali" searches for "ali" (here and on the directory).
	 */
	function doctorQuery( value, kind ) {
		var query = normalise( value );

		return 'doctors' === kind ? query.replace( /^dr\.?\s*/, '' ) : query;
	}

	function setStatus( card, text ) {
		var status = card.querySelector( '[data-dak-nav-search-status]' );

		if ( status ) {
			status.textContent = text;
		}
	}

	function showEmpty( card, query, show ) {
		var empty = card.querySelector( '[data-dak-nav-empty]' );
		var title = card.querySelector( '[data-dak-nav-empty-title]' );

		if ( title ) {
			title.textContent = show ? t( 'noResults', query ) : '';
		}

		if ( empty ) {
			empty.hidden = ! show;
		}
	}

	/**
	 * Shows the items of each [data-dak-nav-group] whose data-dak-nav-match
	 * contains the query (up to the group's limit, if it has one), hides the
	 * groups with none, and returns the total number of matches.
	 */
	function filterGroups( groups, query, extraTest ) {
		var total = 0;

		groups.forEach( function ( group ) {
			var limit = parseInt( group.getAttribute( RESULT_LIMIT_ATTR ), 10 ) || 0;
			var found = 0;

			group.querySelectorAll( '[data-dak-nav-match]' ).forEach( function ( row ) {
				var hit = ( '' === query || row.getAttribute( 'data-dak-nav-match' ).indexOf( query ) !== -1 ) && ( ! extraTest || extraTest( row ) );

				if ( hit ) {
					found++;
				}

				row.hidden = ! hit || ( limit > 0 && found > limit );
			} );

			group.hidden = 0 === found;
			group.matchCount = found;
			total += found;
		} );

		return total;
	}

	function searchDoctors( card, value ) {
		var query = doctorQuery( value, 'doctors' );
		var browse = card.querySelector( '[data-dak-nav-browse]' );
		var results = card.querySelector( '[data-dak-nav-results]' );
		var groups = results ? Array.prototype.slice.call( results.querySelectorAll( '[data-dak-nav-group]' ) ) : [];

		if ( '' === query ) {
			if ( browse ) {
				browse.hidden = false;
			}

			if ( results ) {
				results.hidden = true;
			}

			showEmpty( card, '', false );
			setStatus( card, '' );
			return;
		}

		var total = filterGroups( groups, query );

		groups.forEach( function ( group ) {
			var more = group.querySelector( '[data-dak-nav-more]' );
			var limit = parseInt( group.getAttribute( RESULT_LIMIT_ATTR ), 10 ) || 0;

			if ( ! more ) {
				return;
			}

			if ( limit > 0 && group.matchCount > limit ) {
				var form = card.querySelector( 'form[data-dak-search-kind]' );
				var link = document.createElement( 'a' );

				link.href = form ? buildFormUrl( form, query ) : '#';
				link.textContent = t( 'seeAll', group.matchCount );
				more.textContent = '';
				more.appendChild( link );
				more.hidden = false;
			} else {
				more.hidden = true;
				more.textContent = '';
			}
		} );

		if ( browse ) {
			browse.hidden = true;
		}

		if ( results ) {
			results.hidden = 0 === total;
		}

		showEmpty( card, value.trim(), 0 === total );
		setStatus( card, 0 === total ? t( 'noMatches' ) : count( 'matchOne', 'matchMany', total ) );
	}

	/** The GET URL a search form would submit to (keeps its hidden args). */
	function buildFormUrl( form, query ) {
		var params = [];

		Array.prototype.forEach.call( form.elements, function ( field ) {
			if ( ! field.name || field.disabled ) {
				return;
			}

			var value = 'search' === field.type ? query : field.value;
			params.push( encodeURIComponent( field.name ) + '=' + encodeURIComponent( value ) );
		} );

		return form.getAttribute( 'action' ) + ( params.length ? '?' + params.join( '&' ) : '' );
	}

	/* ------------------------------------------------------------ Services */

	function initServiceCategories() {
		header.querySelectorAll( '[data-dak-nav-categories]' ).forEach( function ( wrap ) {
			var buttons = Array.prototype.slice.call( wrap.querySelectorAll( '[data-dak-nav-category]' ) );
			var hoverTimer = 0;

			var select = function ( button, toggle ) {
				var isOpen = 'true' === button.getAttribute( 'aria-expanded' );

				// Mobile accordion: tapping the open category closes it.
				// Desktop: a category is always selected.
				if ( isOpen && toggle && ! isDesktop() ) {
					button.setAttribute( 'aria-expanded', 'false' );
					document.getElementById( button.getAttribute( 'aria-controls' ) ).hidden = true;
					return;
				}

				buttons.forEach( function ( other ) {
					var on = other === button;

					other.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
					document.getElementById( other.getAttribute( 'aria-controls' ) ).hidden = ! on;
				} );
			};

			buttons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					select( button, true );
				} );

				// Mouse users can point at a category to preview it.
				button.addEventListener( 'mouseenter', function () {
					if ( ! canHover() ) {
						return;
					}

					window.clearTimeout( hoverTimer );
					hoverTimer = window.setTimeout( function () {
						select( button, false );
					}, OPEN_DELAY );
				} );

				button.addEventListener( 'mouseleave', function () {
					window.clearTimeout( hoverTimer );
				} );
			} );

			// Phones start with every category folded; desktop shows the first.
			onChange( desktopQuery, function () {
				resetCategories( wrap, buttons );
			} );
			resetCategories( wrap, buttons );

			wrap.selectFirst = function () {
				resetCategories( wrap, buttons );
			};
		} );
	}

	function resetCategories( wrap, buttons ) {
		if ( wrap.classList.contains( 'is-searching' ) ) {
			return;
		}

		buttons.forEach( function ( button, index ) {
			var on = isDesktop() && 0 === index;

			button.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			document.getElementById( button.getAttribute( 'aria-controls' ) ).hidden = ! on;
		} );
	}

	function searchServices( card, value ) {
		var query = normalise( value );
		var wrap = card.querySelector( '[data-dak-nav-categories]' );

		if ( ! wrap ) {
			return;
		}

		var panes = Array.prototype.slice.call( wrap.querySelectorAll( '[data-dak-nav-group]' ) );
		var buttons = Array.prototype.slice.call( wrap.querySelectorAll( '[data-dak-nav-category]' ) );

		if ( '' === query ) {
			wrap.classList.remove( 'is-searching' );
			filterGroups( panes, '' );
			resetCategories( wrap, buttons );
			wrap.hidden = false;
			showEmpty( card, '', false );
			setStatus( card, '' );
			return;
		}

		wrap.classList.add( 'is-searching' );

		var total = filterGroups( panes, query );

		wrap.hidden = 0 === total;
		showEmpty( card, value.trim(), 0 === total );
		setStatus( card, 0 === total ? t( 'noMatches' ) : count( 'serviceOne', 'serviceMany', total ) );
	}

	/* ------------------------------------------------------------ Clinics */

	function initClinicCities() {
		header.querySelectorAll( '[data-dak-nav-city]' ).forEach( function ( chip ) {
			if ( 'BUTTON' !== chip.tagName ) {
				return;
			}

			chip.addEventListener( 'click', function () {
				var card = chip.closest( '.dak-nav-panel-card' );

				card.querySelectorAll( 'button[data-dak-nav-city]' ).forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === chip ? 'true' : 'false' );
				} );

				filterClinics( card );
			} );
		} );
	}

	function filterClinics( card ) {
		var input = card.querySelector( '[data-dak-nav-search]' );
		var query = normalise( input ? input.value : '' );
		var chip = card.querySelector( 'button[data-dak-nav-city][aria-pressed="true"]' );
		var city = chip ? chip.getAttribute( 'data-dak-nav-city' ) : '';
		var list = card.querySelector( '.dak-nav-clinics' );

		if ( ! list ) {
			return;
		}

		var total = filterGroups( [ list ], query, function ( row ) {
			return '' === city || row.getAttribute( 'data-dak-nav-city' ) === city;
		} );

		var typed = input ? input.value.trim() : '';
		var cityLabel = city && chip ? chip.getAttribute( 'data-dak-nav-city-label' ) : '';
		var label = cityLabel ? ( typed ? t( 'inCity', [ typed, cityLabel ] ) : cityLabel ) : typed;

		showEmpty( card, label, 0 === total );
		setStatus( card, '' === query && '' === city ? '' : ( 0 === total ? t( 'noClinics' ) : count( 'clinicOne', 'clinicMany', total ) ) );

		// "Search all clinics" keeps the chosen city too.
		var cityField = card.querySelector( '[data-dak-nav-city-field]' );

		if ( cityField ) {
			cityField.value = city;
			cityField.disabled = '' === city;
		}

		// "View all clinics" follows the chosen city (the directory reads ?city=).
		var all = card.querySelector( '[data-dak-nav-clinics-all]' );

		if ( all ) {
			var base = all.getAttribute( 'data-dak-nav-base-url' );
			var text = all.querySelector( '[data-dak-nav-view-all-label]' );

			if ( city && chip ) {
				all.href = base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + 'city=' + encodeURIComponent( city );
				text.textContent = all.getAttribute( 'data-dak-nav-city-template' ).replace( '%s', chip.getAttribute( 'data-dak-nav-city-label' ) );
			} else {
				all.href = base;
				text.textContent = all.getAttribute( 'data-dak-nav-all-label' );
			}
		}
	}

	/* ------------------------------------------------------------ Anchor offset */

	function initOffset() {
		var update = function () {
			var height = header.getBoundingClientRect().height;
			var bar = document.getElementById( 'wpadminbar' );

			if ( bar && 'fixed' === window.getComputedStyle( bar ).position ) {
				height += bar.getBoundingClientRect().height;
			}

			document.documentElement.style.setProperty( '--dak-site-header-offset', Math.ceil( height ) + 'px' );
		};

		update();
		window.addEventListener( 'load', update );
		window.addEventListener( 'resize', update );
	}

	/* ------------------------------------------------------------ Toast */

	/**
	 * Shows a short-lived toast when a not-yet-built option is tapped
	 * (Lab tests / Pharmacy, here, in the footer and on the home page) — the
	 * button's `data-dak-coming-soon` carries the message, built server-side
	 * (feature name + clinic phone, when known).
	 */
	function initComingSoonToast() {
		var toast = null;
		var hideTimeout = null;

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest ? event.target.closest( '[data-dak-coming-soon]' ) : null;

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
			}, 5000 );
		} );
	}
}() );
