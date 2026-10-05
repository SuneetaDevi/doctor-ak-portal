/**
 * Doctor AK Portal — shared dashboard behaviour (dashboard pages only).
 *
 * Purely presentational helpers layered on top of the existing scripts:
 *  - Dialogs: whenever any `.dak-modal` gains `is-open`, focus moves into it,
 *    Tab is trapped inside, Escape activates its close button, and focus
 *    returns to the control that opened it once it closes. Opening/closing
 *    itself stays with each feature's own script.
 *  - Row "More" menus (`details.dak-row-menu`): one open at a time, closes on
 *    outside click, Escape, or after choosing an item (and follows its More
 *    button if the page scrolls, closing once the button leaves the screen).
 *    The open panel is placed against the viewport (position: fixed), so a
 *    card or the main column can't clip it and the sidebar can't cover it on
 *    small screens: it lines up with the More button, is kept inside the
 *    screen horizontally, and opens upward when there isn't room below.
 *    Item clicks still reach the existing delegated handlers because nothing
 *    here stops propagation.
 */
( function () {
	'use strict';

	var FOCUSABLE = 'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

	/* ------------------------------------------------------------------ */
	/* Dialogs                                                             */
	/* ------------------------------------------------------------------ */

	var openStack = [];
	var returnFocus = new WeakMap();
	var lastActivator = null;

	document.addEventListener( 'pointerdown', function ( event ) {
		lastActivator = event.target.closest( FOCUSABLE );
	}, true );

	document.addEventListener( 'focusin', function ( event ) {
		if ( ! event.target.closest( '.dak-modal' ) ) {
			lastActivator = event.target;
		}
	} );

	function isOpen( modal ) {
		return modal.classList.contains( 'is-open' ) && ! modal.hasAttribute( 'hidden' );
	}

	function visibleFocusables( root ) {
		return Array.prototype.filter.call( root.querySelectorAll( FOCUSABLE ), function ( el ) {
			return el.offsetParent !== null || el === document.activeElement;
		} );
	}

	function dialogOf( modal ) {
		return modal.querySelector( '[role="dialog"]' ) || modal.querySelector( '.dak-modal-dialog' ) || modal;
	}

	function onOpened( modal ) {
		if ( openStack.indexOf( modal ) !== -1 ) {
			return;
		}
		openStack.push( modal );
		returnFocus.set( modal, lastActivator || document.activeElement );

		// Let the feature script finish populating fields first.
		window.setTimeout( function () {
			if ( ! isOpen( modal ) || modal.contains( document.activeElement ) ) {
				return;
			}
			var dialog = dialogOf( modal );
			var target = dialog.querySelector( '[autofocus]' ) ||
				visibleFocusables( dialog ).filter( function ( el ) {
					return ! el.classList.contains( 'dak-modal-close' );
				} )[ 0 ] ||
				dialog;
			if ( target === dialog && ! dialog.hasAttribute( 'tabindex' ) ) {
				dialog.setAttribute( 'tabindex', '-1' );
			}
			try {
				target.focus( { preventScroll: true } );
			} catch ( e ) {
				target.focus();
			}
		}, 60 );
	}

	function onClosed( modal ) {
		var index = openStack.indexOf( modal );
		if ( index === -1 ) {
			return;
		}
		openStack.splice( index, 1 );
		var back = returnFocus.get( modal );
		returnFocus.delete( modal );
		if ( back && document.contains( back ) && typeof back.focus === 'function' ) {
			back.focus();
		}
	}

	function watch( modal ) {
		if ( modal.__dakWatched ) {
			return;
		}
		modal.__dakWatched = true;
		if ( isOpen( modal ) ) {
			onOpened( modal );
		}
		new MutationObserver( function () {
			if ( isOpen( modal ) ) {
				onOpened( modal );
			} else {
				onClosed( modal );
			}
		} ).observe( modal, { attributes: true, attributeFilter: [ 'class', 'hidden' ] } );
	}

	function scan( root ) {
		if ( root.nodeType !== 1 ) {
			return;
		}
		if ( root.classList.contains( 'dak-modal' ) ) {
			watch( root );
		}
		Array.prototype.forEach.call( root.querySelectorAll( '.dak-modal' ), watch );
	}

	function topModal() {
		for ( var i = openStack.length - 1; i >= 0; i-- ) {
			if ( isOpen( openStack[ i ] ) ) {
				return openStack[ i ];
			}
		}
		return null;
	}

	document.addEventListener( 'keydown', function ( event ) {
		var modal = topModal();
		if ( ! modal ) {
			return;
		}

		if ( 'Escape' === event.key ) {
			// An open row menu or searchable dropdown inside the dialog closes first.
			var menu = modal.querySelector( 'details.dak-row-menu[open]' );
			if ( menu ) {
				return;
			}
			var close = modal.querySelector( '.dak-modal-close' ) ||
				modal.querySelector( '[data-dak-modal-close], [class*="-close"]' );
			if ( close ) {
				event.preventDefault();
				close.click();
			}
			return;
		}

		if ( 'Tab' !== event.key ) {
			return;
		}
		var dialog = dialogOf( modal );
		var items = visibleFocusables( dialog );
		if ( ! items.length ) {
			event.preventDefault();
			return;
		}
		var first = items[ 0 ];
		var last = items[ items.length - 1 ];
		var active = document.activeElement;

		if ( ! dialog.contains( active ) ) {
			event.preventDefault();
			first.focus();
		} else if ( event.shiftKey && ( active === first || active === dialog ) ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && active === last ) {
			event.preventDefault();
			first.focus();
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Row "More" menus                                                    */
	/* ------------------------------------------------------------------ */

	var MENU_GAP = 4;
	var MENU_EDGE = 8;

	function closeMenus( except ) {
		Array.prototype.forEach.call( document.querySelectorAll( 'details.dak-row-menu[open]' ), function ( menu ) {
			if ( menu !== except ) {
				menu.removeAttribute( 'open' );
			}
		} );
	}

	/**
	 * Places an open menu's panel on screen, next to its More button:
	 * right-aligned with the button when there's room, otherwise pushed
	 * inside the viewport edge; below the button, or above it when the
	 * space below is too short (and capped to the taller side, scrolling
	 * inside if a very long menu still doesn't fit).
	 */
	function placeMenu( menu ) {
		var toggle = menu.querySelector( '.dak-row-menu-toggle' ) || menu.querySelector( 'summary' );
		var panel = menu.querySelector( '.dak-row-menu-panel' );

		if ( ! toggle || ! panel ) {
			return;
		}

		menu.classList.add( 'is-floating' );
		menu.classList.remove( 'is-flipped' );
		panel.style.removeProperty( 'max-height' );

		var anchor = toggle.getBoundingClientRect();
		var viewW = document.documentElement.clientWidth;
		var viewH = window.innerHeight;
		var width = Math.min( panel.offsetWidth, viewW - MENU_EDGE * 2 );
		var left = Math.max( MENU_EDGE, Math.min( anchor.right - width, viewW - width - MENU_EDGE ) );
		var below = viewH - anchor.bottom - MENU_GAP - MENU_EDGE;
		var above = anchor.top - MENU_GAP - MENU_EDGE;
		var height = panel.offsetHeight;
		var flip = height > below && above > below;
		var room = flip ? above : below;

		if ( height > room ) {
			panel.style.setProperty( 'max-height', Math.max( 120, room ) + 'px', 'important' );
			height = Math.min( height, Math.max( 120, room ) );
		}

		panel.style.setProperty( 'left', left + 'px', 'important' );
		panel.style.setProperty( 'max-width', ( viewW - MENU_EDGE * 2 ) + 'px', 'important' );
		panel.style.setProperty( 'top', ( flip ? anchor.top - MENU_GAP - height : anchor.bottom + MENU_GAP ) + 'px', 'important' );
		menu.classList.toggle( 'is-flipped', flip );
	}

	document.addEventListener( 'toggle', function ( event ) {
		var menu = event.target;
		if ( ! menu.classList || ! menu.classList.contains( 'dak-row-menu' ) ) {
			return;
		}
		if ( ! menu.open ) {
			menu.classList.remove( 'is-floating', 'is-flipped' );
			return;
		}
		closeMenus( menu );
		placeMenu( menu );
	}, true );

	// A fixed panel must stay with its row: when the page (or a scrolling
	// list) moves, follow the More button, and close once the button has
	// scrolled out of view. Scrolling inside the panel itself is ignored.
	function followOnMove( event ) {
		var open = document.querySelector( 'details.dak-row-menu[open].is-floating' );

		if ( ! open || ( event && event.target && 1 === event.target.nodeType && open.querySelector( '.dak-row-menu-panel' ).contains( event.target ) ) ) {
			return;
		}

		var toggle = ( open.querySelector( '.dak-row-menu-toggle' ) || open.querySelector( 'summary' ) ).getBoundingClientRect();

		if ( toggle.bottom < 0 || toggle.top > window.innerHeight || toggle.right < 0 || toggle.left > document.documentElement.clientWidth ) {
			closeMenus( null );
			return;
		}

		placeMenu( open );
	}

	window.addEventListener( 'scroll', followOnMove, true );
	window.addEventListener( 'resize', followOnMove );

	document.addEventListener( 'click', function ( event ) {
		var inMenu = event.target.closest( 'details.dak-row-menu' );
		if ( ! inMenu ) {
			closeMenus( null );
			return;
		}
		// Choosing an item closes the menu after the item's own handler runs.
		if ( event.target.closest( '.dak-row-menu-item' ) ) {
			window.setTimeout( function () {
				inMenu.removeAttribute( 'open' );
			}, 0 );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		var menu = document.querySelector( 'details.dak-row-menu[open]' );
		if ( ! menu ) {
			return;
		}
		var items = Array.prototype.slice.call( menu.querySelectorAll( '.dak-row-menu-item' ) );
		var index = items.indexOf( document.activeElement );

		if ( 'Escape' === event.key ) {
			event.preventDefault();
			event.stopPropagation();
			menu.removeAttribute( 'open' );
			var toggle = menu.querySelector( 'summary' );
			if ( toggle ) {
				toggle.focus();
			}
		} else if ( 'ArrowDown' === event.key && items.length ) {
			event.preventDefault();
			items[ ( index + 1 ) % items.length ].focus();
		} else if ( 'ArrowUp' === event.key && items.length ) {
			event.preventDefault();
			items[ ( index - 1 + items.length ) % items.length ].focus();
		}
	}, true );

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	function boot() {
		scan( document.body );
		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				Array.prototype.forEach.call( mutation.addedNodes, scan );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
