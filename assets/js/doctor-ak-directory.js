/**
 * Doctor AK Portal — Doctors directory search/filter.
 *
 * Client-side only: the whole grid is already rendered server-side in one
 * page load, so filtering by name/specialization/location just shows/hides
 * cards — no AJAX round trip needed. Country/City/Area cascade from the
 * full admin-managed Locations list (window.dakDirectory.locations, see
 * Locations::get_all()), not just locations a listed doctor happens to
 * have — consistent with every other location picker in the plugin.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var grid = document.getElementById( 'dak-directory-grid' );
		var searchInput = document.getElementById( 'dak-directory-search-input' );
		var specializationSelect = document.getElementById( 'dak-directory-specialization-filter' );
		var countrySelect = document.getElementById( 'dak-directory-country-filter' );
		var citySelect = document.getElementById( 'dak-directory-city-filter' );
		var areaSelect = document.getElementById( 'dak-directory-area-filter' );
		var clinicSelect = document.getElementById( 'dak-directory-clinic-filter' );

		if ( ! grid || ! searchInput ) {
			return;
		}

		var noResults = document.getElementById( 'dak-directory-no-results' );
		var cards = grid.querySelectorAll( '[data-doctor-card]' );

		wireLocationCascade( countrySelect, citySelect, areaSelect );

		function applyFilters() {
			var query = searchInput.value.trim().toLowerCase();
			var specialization = specializationSelect ? specializationSelect.value : '';
			var country = countrySelect ? countrySelect.value : '';
			var city = citySelect ? citySelect.value : '';
			var area = areaSelect ? areaSelect.value : '';
			var clinic = clinicSelect ? clinicSelect.value : '';
			var visibleCount = 0;

			cards.forEach( function ( card ) {
				var name = card.getAttribute( 'data-search-name' ) || '';
				var specializations = card.getAttribute( 'data-search-specializations' ) || '';
				var countries = card.getAttribute( 'data-search-country' ) || '';
				var cities = card.getAttribute( 'data-search-city' ) || '';
				var areas = card.getAttribute( 'data-search-area' ) || '';
				var clinics = card.getAttribute( 'data-search-clinics' ) || '';

				var matchesQuery = '' === query || name.indexOf( query ) !== -1 || specializations.indexOf( query ) !== -1;
				var matchesSpecialization = '' === specialization || specializations.indexOf( specialization ) !== -1;
				var matchesCountry = '' === country || countries.split( ',' ).indexOf( country ) !== -1;
				var matchesCity = '' === city || cities.split( ',' ).indexOf( city ) !== -1;
				var matchesArea = '' === area || areas.split( ',' ).indexOf( area ) !== -1;
				var matchesClinic = '' === clinic || clinics.indexOf( clinic ) !== -1;
				var isVisible = matchesQuery && matchesSpecialization && matchesCountry && matchesCity && matchesArea && matchesClinic;

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

		[ specializationSelect, countrySelect, citySelect, areaSelect, clinicSelect ].forEach( function ( select ) {
			if ( select ) {
				select.addEventListener( 'change', applyFilters );
			}
		} );
	} );

	function findBySlug( list, slug ) {
		return ( list || [] ).filter( function ( entry ) {
			return entry.slug === slug;
		} )[ 0 ];
	}

	/**
	 * Fills a filter <select> with an "All X" first option plus one option
	 * per entry.
	 *
	 * @param {HTMLSelectElement} select      The <select> to fill.
	 * @param {Object[]}          entries     List of `{ slug, name }`.
	 * @param {string}            allLabel    First option's label (e.g. "All cities").
	 * @return {void}
	 */
	function fillFilterSelect( select, entries, allLabel ) {
		select.innerHTML = '';

		var allOption = document.createElement( 'option' );
		allOption.value = '';
		allOption.textContent = allLabel;
		select.appendChild( allOption );

		entries.forEach( function ( entry ) {
			var option = document.createElement( 'option' );
			option.value = entry.slug;
			option.textContent = entry.name;
			select.appendChild( option );
		} );
	}

	/**
	 * Populates the Country filter from the full Locations list, and wires
	 * Country -> City -> Area cascading: picking a Country repopulates City
	 * (and clears/disables Area until a City is picked); picking a City
	 * repopulates Area.
	 *
	 * @param {HTMLSelectElement} countrySelect The Country filter <select>.
	 * @param {HTMLSelectElement} citySelect    The City filter <select>.
	 * @param {HTMLSelectElement} areaSelect    The Area filter <select>.
	 * @return {void}
	 */
	function wireLocationCascade( countrySelect, citySelect, areaSelect ) {
		if ( ! countrySelect || ! citySelect || ! areaSelect || ! window.dakDirectory ) {
			return;
		}

		var locations = window.dakDirectory.locations || [];

		fillFilterSelect( countrySelect, locations, 'All countries' );

		countrySelect.addEventListener( 'change', function () {
			var country = findBySlug( locations, countrySelect.value );

			fillFilterSelect( citySelect, country ? country.cities : [], 'All cities' );
			citySelect.disabled = ! country || 0 === country.cities.length;

			fillFilterSelect( areaSelect, [], 'All areas' );
			areaSelect.disabled = true;

			citySelect.dispatchEvent( new Event( 'change' ) );
		} );

		citySelect.addEventListener( 'change', function () {
			var country = findBySlug( locations, countrySelect.value );
			var city = country ? findBySlug( country.cities, citySelect.value ) : null;

			fillFilterSelect( areaSelect, city ? city.areas : [], 'All areas' );
			areaSelect.disabled = ! city || 0 === city.areas.length;

			areaSelect.dispatchEvent( new Event( 'change' ) );
		} );
	}
} )();
