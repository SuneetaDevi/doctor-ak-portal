/**
 * Doctor AK Portal — Services directory category filter + search.
 *
 * Client-side only: the whole list is already rendered server-side in one
 * page load (see Services_Directory::render()), so this just shows/hides
 * rows — no AJAX round trip needed.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var grid = document.getElementById( 'dak-services-directory-grid' );

		if ( ! grid ) {
			return;
		}

		var searchInput = document.getElementById( 'dak-services-directory-search-input' );
		var chips = document.querySelectorAll( '[data-category-filter]' );
		var empty = document.getElementById( 'dak-services-directory-empty' );
		var category = '';

		function applyFilters() {
			var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
			var rows = Array.prototype.slice.call( grid.querySelectorAll( '[data-search-name]' ) );
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

		if ( searchInput ) {
			searchInput.addEventListener( 'input', applyFilters );
		}

		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				category = chip.getAttribute( 'data-category-filter' ) || '';

				chips.forEach( function ( el ) {
					var isActive = el === chip;

					el.classList.toggle( 'is-active', isActive );
					el.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
				} );

				applyFilters();
			} );
		} );
	} );
}() );
