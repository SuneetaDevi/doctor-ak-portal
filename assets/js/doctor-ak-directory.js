/**
 * Doctor AK Portal — Doctors directory search/filter.
 *
 * Client-side only: the whole grid is already rendered server-side in one
 * page load, so filtering by name/specialization just shows/hides cards —
 * no AJAX round trip needed.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var grid = document.getElementById( 'dak-directory-grid' );
		var searchInput = document.getElementById( 'dak-directory-search-input' );
		var specializationSelect = document.getElementById( 'dak-directory-specialization-filter' );
		var locationSelect = document.getElementById( 'dak-directory-location-filter' );
		var clinicSelect = document.getElementById( 'dak-directory-clinic-filter' );

		if ( ! grid || ! searchInput ) {
			return;
		}

		var noResults = document.getElementById( 'dak-directory-no-results' );
		var cards = grid.querySelectorAll( '[data-doctor-card]' );

		function applyFilters() {
			var query = searchInput.value.trim().toLowerCase();
			var specialization = specializationSelect ? specializationSelect.value : '';
			var location = locationSelect ? locationSelect.value : '';
			var clinic = clinicSelect ? clinicSelect.value : '';
			var visibleCount = 0;

			cards.forEach( function ( card ) {
				var name = card.getAttribute( 'data-search-name' ) || '';
				var specializations = card.getAttribute( 'data-search-specializations' ) || '';
				var locations = card.getAttribute( 'data-search-location' ) || '';
				var clinics = card.getAttribute( 'data-search-clinics' ) || '';

				var matchesQuery = '' === query || name.indexOf( query ) !== -1 || specializations.indexOf( query ) !== -1;
				var matchesSpecialization = '' === specialization || specializations.indexOf( specialization ) !== -1;
				var matchesLocation = '' === location || locations.indexOf( location ) !== -1;
				var matchesClinic = '' === clinic || clinics.indexOf( clinic ) !== -1;
				var isVisible = matchesQuery && matchesSpecialization && matchesLocation && matchesClinic;

				card.classList.toggle( 'dak-hidden', ! isVisible );

				if ( isVisible ) {
					visibleCount++;
				}
			} );

			if ( noResults ) {
				noResults.classList.toggle( 'dak-hidden', visibleCount > 0 );
			}
		}

		searchInput.addEventListener( 'input', applyFilters );

		[ specializationSelect, locationSelect, clinicSelect ].forEach( function ( select ) {
			if ( select ) {
				select.addEventListener( 'change', applyFilters );
			}
		} );
	} );
} )();
