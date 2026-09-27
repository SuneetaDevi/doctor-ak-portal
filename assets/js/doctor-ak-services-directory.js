/**
 * Doctor AK Portal — Services directory category filter + search.
 *
 * Client-side only: the whole list is already rendered server-side in one
 * page load (see Services_Directory::render()), so this just shows/hides
 * rows — no AJAX round trip needed.
 *
 * Wired with event delegation on `document` (attached the instant this file
 * runs, regardless of where in <head>/<body> the enqueued <script> tag ends
 * up, and regardless of whether the DOM is still parsing) rather than
 * addEventListener() on the individual chips/input — a caching/minification
 * plugin (Autoptimize, WP Rocket, …) commonly moves, combines, or
 * defers/asyncs enqueued scripts, any of which can otherwise leave a
 * "wait for DOMContentLoaded, then query the elements" script listening for
 * an event that already fired, or querying elements before they exist.
 * Delegation sidesteps both: `document` itself is always present, and a
 * delegated listener only needs an element to exist at click/input time, not
 * at script-load time.
 */
( function () {
	'use strict';

	var category = '';

	function grid() {
		return document.getElementById( 'dak-services-directory-grid' );
	}

	function applyFilters() {
		var theGrid = grid();

		if ( ! theGrid ) {
			return;
		}

		var searchInput = document.getElementById( 'dak-services-directory-search-input' );
		var empty = document.getElementById( 'dak-services-directory-empty' );
		var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
		var rows = theGrid.querySelectorAll( '[data-search-name]' );
		var shown = 0;

		rows.forEach( function ( row ) {
			var name = row.getAttribute( 'data-search-name' ) || '';
			var rowCategory = row.getAttribute( 'data-search-category' ) || '';

			var matchesQuery = '' === query || name.indexOf( query ) !== -1;
			var matchesCategory = '' === category || rowCategory === category;
			var isMatch = matchesQuery && matchesCategory;

			row.classList.toggle( 'dak-hidden', ! isMatch );
			shown += isMatch ? 1 : 0;
		} );

		if ( empty ) {
			empty.classList.toggle( 'dak-hidden', shown > 0 );
		}
	}

	document.addEventListener( 'input', function ( event ) {
		if ( event.target && 'dak-services-directory-search-input' === event.target.id ) {
			applyFilters();
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var chip = event.target ? event.target.closest( '[data-category-filter]' ) : null;

		if ( ! chip ) {
			return;
		}

		event.preventDefault();
		category = chip.getAttribute( 'data-category-filter' ) || '';

		document.querySelectorAll( '[data-category-filter]' ).forEach( function ( el ) {
			var isActive = el === chip;

			el.classList.toggle( 'is-active', isActive );
			el.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
		} );

		applyFilters();
	} );
}() );
