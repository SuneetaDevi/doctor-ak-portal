/**
 * Doctor AK Portal — Searchable select.
 *
 * Progressive enhancement for any <select class="dak-select-searchable">:
 * wraps it with a text input + filterable dropdown, so a long doctor/patient
 * list can be typed through instead of scrolled. The original <select>
 * stays in the DOM (visually hidden) as the single source of truth — every
 * other script keeps reading/writing its .value exactly as before; this
 * only changes how a human picks that value.
 *
 * Because other scripts routinely set a select's .value (or rebuild its
 * .innerHTML) programmatically — e.g. opening an "Edit" modal, or repopulating
 * a doctor list after a clinic changes — without that select ever
 * dispatching a native 'change' event, this exposes
 * window.DAKSearchableSelect.refresh( select ) for those call sites to
 * invoke afterwards, so the visible input stays in sync.
 */
( function () {
	'use strict';

	function init() {
		document.querySelectorAll( 'select.dak-select-searchable' ).forEach( enhance );
	}

	function enhance( select ) {
		if ( select.dakSearchableEnhanced ) {
			return;
		}

		select.dakSearchableEnhanced = true;

		var wrap = document.createElement( 'div' );
		wrap.className = 'dak-searchable-select';

		select.parentNode.insertBefore( wrap, select );
		wrap.appendChild( select );
		select.classList.add( 'dak-searchable-select-native' );
		select.setAttribute( 'tabindex', '-1' );
		select.setAttribute( 'aria-hidden', 'true' );

		var input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'dak-searchable-select-input';
		input.setAttribute( 'autocomplete', 'off' );
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.placeholder = select.getAttribute( 'data-placeholder' ) || '';

		var menu = document.createElement( 'div' );
		menu.className = 'dak-searchable-select-menu dak-hidden';
		menu.setAttribute( 'role', 'listbox' );

		wrap.appendChild( input );
		wrap.appendChild( menu );

		function currentLabel() {
			var opt = select.options[ select.selectedIndex ];
			return opt ? opt.textContent : '';
		}

		function syncFromSelect() {
			input.value = currentLabel();
			input.disabled = select.disabled;
			wrap.classList.toggle( 'is-disabled', select.disabled );
		}

		function renderMenu( filterText ) {
			menu.innerHTML = '';

			var needle = ( filterText || '' ).toLowerCase();
			var any = false;

			Array.prototype.forEach.call( select.options, function ( opt ) {
				if ( opt.disabled ) {
					return;
				}

				var label = opt.textContent;

				if ( needle && -1 === label.toLowerCase().indexOf( needle ) ) {
					return;
				}

				any = true;

				var item = document.createElement( 'button' );
				item.type = 'button';
				item.className = 'dak-searchable-select-option' + ( opt.value === select.value ? ' is-selected' : '' );
				item.setAttribute( 'role', 'option' );
				item.textContent = label;

				// mousedown (not click) so this runs before the input's blur
				// handler closes the menu out from under it.
				item.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();

					select.value = opt.value;
					closeMenu();
					select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );

				menu.appendChild( item );
			} );

			if ( ! any ) {
				var empty = document.createElement( 'div' );
				empty.className = 'dak-searchable-select-empty';
				empty.textContent = window.dakSearchableSelectStrings && window.dakSearchableSelectStrings.noMatches
					? window.dakSearchableSelectStrings.noMatches
					: 'No matches';
				menu.appendChild( empty );
			}
		}

		function openMenu() {
			if ( select.disabled ) {
				return;
			}

			renderMenu( '' );
			menu.classList.remove( 'dak-hidden' );
			wrap.classList.add( 'is-open' );
			input.setAttribute( 'aria-expanded', 'true' );
			input.select();
		}

		function closeMenu() {
			menu.classList.add( 'dak-hidden' );
			wrap.classList.remove( 'is-open' );
			input.setAttribute( 'aria-expanded', 'false' );
			syncFromSelect();
		}

		input.addEventListener( 'focus', openMenu );
		input.addEventListener( 'click', openMenu );

		input.addEventListener( 'input', function () {
			renderMenu( input.value );
			menu.classList.remove( 'dak-hidden' );
			wrap.classList.add( 'is-open' );
			input.setAttribute( 'aria-expanded', 'true' );
		} );

		input.addEventListener( 'blur', function () {
			// A menu option's mousedown already preventDefault()s, but the
			// blur still fires first in some browsers — give its click a
			// moment to land before snapping the input back to the select's
			// current value.
			window.setTimeout( closeMenu, 150 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				closeMenu();
				input.blur();
			}
		} );

		syncFromSelect();

		select.dakSearchableRefresh = syncFromSelect;
	}

	/**
	 * Re-syncs an enhanced select's visible input to its current .value/
	 * .disabled state — call this after setting either programmatically
	 * (a plain .value = assignment never fires 'change', which is what the
	 * wrapper otherwise listens for).
	 *
	 * @param {HTMLSelectElement} select
	 */
	function refresh( select ) {
		if ( select && select.dakSearchableRefresh ) {
			select.dakSearchableRefresh();
		}
	}

	document.addEventListener( 'DOMContentLoaded', init );

	window.DAKSearchableSelect = { refresh: refresh, enhance: enhance };
} )();
