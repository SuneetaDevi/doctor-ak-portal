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

		// A `?city=<slug>` link (the site header's Doctors -> By Location
		// menu) matches directly against each card's own data-search-city —
		// see applyFilters() below — rather than driving the Country -> City
		// cascade, which only populates City once a Country is chosen and so
		// can't be preselected by a single deep link. Captured once here;
		// the moment the visitor actually touches the City <select>
		// themselves, its own value takes over (see matchesCity below).
		var presetCity = window.URLSearchParams
			? ( new URLSearchParams( window.location.search ).get( 'city' ) || '' ).toLowerCase()
			: '';

		wireLocationCascade( countrySelect, citySelect, areaSelect );
		wireClinicAreaDependency( areaSelect, clinicSelect );

		function applyFilters() {
			var query = searchInput.value.trim().toLowerCase();
			var specialization = specializationSelect ? specializationSelect.value : '';
			var country = countrySelect ? countrySelect.value : '';
			var city = ( citySelect && citySelect.value ) ? citySelect.value : presetCity;
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

		// A manual pick in the City <select> itself should always win over
		// the `?city=` deep link from here on — clearing presetCity means
		// matchesCity above falls through to citySelect's own (possibly
		// empty, i.e. "All cities") value instead of re-applying the preset.
		if ( citySelect ) {
			citySelect.addEventListener( 'change', function () {
				presetCity = '';
			} );
		}

		[ specializationSelect, countrySelect, citySelect, areaSelect, clinicSelect ].forEach( function ( select ) {
			if ( select ) {
				select.addEventListener( 'change', applyFilters );
			}
		} );

		applyPreselectedFilter( specializationSelect, 'specialization', applyFilters );
		applyPreselectedFilter( clinicSelect, 'clinic', applyFilters );
		applyPreselectedSearch( searchInput, applyFilters );

		if ( presetCity ) {
			applyFilters();
		}
	} );

	/**
	 * Lands on this page with a filter already chosen — the home page's
	 * "Consult Top Doctors Online" tiles link here as `?specialization=<lowercased
	 * label>`, and the site header's "Doctors -> By Location" menu as
	 * `?clinic=<lowercased name>`, both matching the filter's own option
	 * values. Anything that isn't an option (nothing listed under it, or a
	 * hand-edited URL) is ignored, leaving the unfiltered grid rather than an
	 * empty one.
	 *
	 * @param {HTMLSelectElement} select       The filter <select> (specialization or clinic).
	 * @param {string}            param        Its matching URL query parameter name.
	 * @param {Function}          applyFilters Re-runs the grid filtering.
	 */
	function applyPreselectedFilter( select, param, applyFilters ) {
		if ( ! select || ! window.URLSearchParams ) {
			return;
		}

		var requested = new URLSearchParams( window.location.search ).get( param );

		if ( ! requested ) {
			return;
		}

		select.value = requested.toLowerCase();

		if ( '' !== select.value ) {
			applyFilters();
		}
	}

	/**
	 * Lands on this page with a search term already typed — the site
	 * header's Doctors mega-menu search box links here as `?s=<term>`.
	 *
	 * @param {HTMLInputElement} input        The search text input.
	 * @param {Function}         applyFilters Re-runs the grid filtering.
	 */
	function applyPreselectedSearch( input, applyFilters ) {
		if ( ! input || ! window.URLSearchParams ) {
			return;
		}

		var requested = new URLSearchParams( window.location.search ).get( 's' );

		if ( ! requested ) {
			return;
		}

		input.value = requested;
		applyFilters();
	}

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

	/**
	 * Narrows the Clinic filter down to only clinics in the selected Area —
	 * clinics with no area on file always stay listed (nothing to exclude
	 * them by). Rebuilds from the server-rendered option list captured once
	 * at load, since the Clinic <select>'s options (and each one's
	 * `data-area`) already come from the page's PHP-rendered markup rather
	 * than window.dakDirectory.locations.
	 *
	 * @param {HTMLSelectElement} areaSelect   The Area filter <select>.
	 * @param {HTMLSelectElement} clinicSelect The Clinic filter <select>.
	 * @return {void}
	 */
	function wireClinicAreaDependency( areaSelect, clinicSelect ) {
		if ( ! areaSelect || ! clinicSelect ) {
			return;
		}

		var allClinics = Array.prototype.slice.call( clinicSelect.options ).map( function ( option ) {
			return { value: option.value, label: option.textContent, area: option.getAttribute( 'data-area' ) || '' };
		} );

		areaSelect.addEventListener( 'change', function () {
			var area = areaSelect.value;
			var previousValue = clinicSelect.value;
			var matches = allClinics.filter( function ( clinic ) {
				return '' === clinic.value || '' === area || clinic.area === area;
			} );

			clinicSelect.innerHTML = '';

			matches.forEach( function ( clinic ) {
				var option = document.createElement( 'option' );
				option.value = clinic.value;
				option.textContent = clinic.label;
				clinicSelect.appendChild( option );
			} );

			clinicSelect.value = matches.some( function ( clinic ) { return clinic.value === previousValue; } ) ? previousValue : '';
			clinicSelect.dispatchEvent( new Event( 'change' ) );
		} );
	}
} )();
