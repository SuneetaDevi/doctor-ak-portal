/**
 * Doctor AK Portal — generic instant client-side list search, shared by
 * every dashboard list page that has no other filters to bundle a search
 * field with (Services, Doctor Sessions, Clinic Locations, Doctor Requests,
 * Encounters, Clinics, Medical History, …). Every one of these lists already
 * loads its full (bounded) dataset up front with no real pagination, so
 * filtering happens instantly in the browser — no AJAX round-trip, no
 * debounce, and no risk of losing focus/cursor position mid-keystroke.
 * Generalizes the same pattern the Doctor dashboard's Patients tab already
 * used ad hoc (see doctor-ak-doctor-add-patient.js).
 *
 * Markup contract:
 * - `<input data-list-search="<CSS selector>">` — the selector points at the
 *   container holding the rows to filter.
 * - Each filterable row inside that container: `data-list-search-row` +
 *   `data-list-search-text="<lowercased searchable text>"`.
 * - An optional `[data-list-search-empty]` element (typically inside the
 *   same container) toggled visible when nothing matches.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( 'input[data-list-search]' ).forEach( wireSearch );
	} );

	function wireSearch( input ) {
		var container = document.querySelector( input.getAttribute( 'data-list-search' ) );

		if ( ! container ) {
			return;
		}

		var rows = container.querySelectorAll( '[data-list-search-row]' );
		var empty = container.querySelector( '[data-list-search-empty]' );

		input.addEventListener( 'input', function () {
			var query = input.value.trim().toLowerCase();
			var visibleCount = 0;

			rows.forEach( function ( row ) {
				var matches = '' === query || ( row.getAttribute( 'data-list-search-text' ) || '' ).indexOf( query ) !== -1;
				row.classList.toggle( 'dak-hidden', ! matches );

				if ( matches ) {
					visibleCount++;
				}
			} );

			if ( empty ) {
				empty.classList.toggle( 'dak-hidden', visibleCount > 0 );
			}
		} );
	}
} )();
