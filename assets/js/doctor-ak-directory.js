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
		var citySelect = document.getElementById( 'dak-directory-city-filter' );
		var areaSelect = document.getElementById( 'dak-directory-area-filter' );
		var clinicSelect = document.getElementById( 'dak-directory-clinic-filter' );

		if ( ! grid || ! searchInput ) {
			return;
		}

		var noResults = document.getElementById( 'dak-directory-no-results' );
		var cards = grid.querySelectorAll( '[data-doctor-card]' );

		wireAreaCascade( citySelect, areaSelect );

		function applyFilters() {
			var query = searchInput.value.trim().toLowerCase();
			var specialization = specializationSelect ? specializationSelect.value : '';
			var city = citySelect ? citySelect.value : '';
			var area = areaSelect ? areaSelect.value : '';
			var clinic = clinicSelect ? clinicSelect.value : '';
			var visibleCount = 0;

			cards.forEach( function ( card ) {
				var name = card.getAttribute( 'data-search-name' ) || '';
				var specializations = card.getAttribute( 'data-search-specializations' ) || '';
				var cities = card.getAttribute( 'data-search-city' ) || '';
				var areas = card.getAttribute( 'data-search-area' ) || '';
				var clinics = card.getAttribute( 'data-search-clinics' ) || '';

				var matchesQuery = '' === query || name.indexOf( query ) !== -1 || specializations.indexOf( query ) !== -1;
				var matchesSpecialization = '' === specialization || specializations.indexOf( specialization ) !== -1;
				var matchesCity = '' === city || cities.split( ',' ).indexOf( city ) !== -1;
				var matchesArea = '' === area || areas.split( ',' ).indexOf( area ) !== -1;
				var matchesClinic = '' === clinic || clinics.indexOf( clinic ) !== -1;
				var isVisible = matchesQuery && matchesSpecialization && matchesCity && matchesArea && matchesClinic;

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

		[ specializationSelect, citySelect, areaSelect, clinicSelect ].forEach( function ( select ) {
			if ( select ) {
				select.addEventListener( 'change', applyFilters );
			}
		} );
	} );

	/**
	 * Populates the Area filter with the selected city's areas (from the
	 * admin-managed Locations list, not just areas a doctor happens to have)
	 * whenever the City filter changes, resetting Area back to "All areas".
	 *
	 * @param {HTMLSelectElement} citySelect The City filter <select>.
	 * @param {HTMLSelectElement} areaSelect The Area filter <select>.
	 */
	function wireAreaCascade( citySelect, areaSelect ) {
		if ( ! citySelect || ! areaSelect || ! window.dakDirectory ) {
			return;
		}

		citySelect.addEventListener( 'change', function () {
			var city = ( window.dakDirectory.locations || [] ).filter( function ( c ) {
				return c.slug === citySelect.value;
			} )[ 0 ];

			areaSelect.innerHTML = '';

			var allOption = document.createElement( 'option' );
			allOption.value = '';
			allOption.textContent = 'All areas';
			areaSelect.appendChild( allOption );

			( city ? city.areas : [] ).forEach( function ( area ) {
				var option = document.createElement( 'option' );
				option.value = area.slug;
				option.textContent = area.name;
				areaSelect.appendChild( option );
			} );

			areaSelect.disabled = ! city || 0 === city.areas.length;
			areaSelect.dispatchEvent( new Event( 'change' ) );
		} );
	}
} )();
