/**
 * Doctor AK Portal — Shared Country -> City -> Area cascading <select> helper.
 *
 * Used everywhere a doctor's location is picked: self-registration, profile
 * edit, admin add/edit doctor, the doctor's own Clinics tab, the admin
 * clinic-location modal and the "Doctor Sessions" modal. Each calls
 * window.dakCityArea.wire() once per Country/City/Area select trio.
 *
 * Countries and cities come from Locations::script_data()
 * (window.dakLocationsApi): every country in the world, with each country's
 * cities fetched when it's chosen (doctor_ak_location_cities) — never the
 * whole world list at once. Areas exist only for cities an administrator
 * added under Settings → Locations, so the Area select is only enabled for
 * those (it's optional elsewhere). Without dakLocationsApi it falls back to
 * the `locations` list the caller passes (the admin list only).
 *
 * After every redraw it fires a `dak-locations-updated` event on the City
 * select, so pages can refresh hints that depend on what's available.
 */
( function () {
	'use strict';

	var api = window.dakLocationsApi || null;
	var cityCache = {};    // country slug → Promise of [{ slug, name, areas }]

	/**
	 * Fills a <select> from `{ slug, name }` entries, selecting
	 * `selectedSlug` if given.
	 */
	function fillSelect( select, entries, placeholder, selectedSlug ) {
		select.innerHTML = '';

		var placeholderOption = document.createElement( 'option' );
		placeholderOption.value = '';
		placeholderOption.textContent = placeholder;
		select.appendChild( placeholderOption );

		var fragment = document.createDocumentFragment();

		entries.forEach( function ( entry ) {
			var option = document.createElement( 'option' );
			option.value = entry.slug;
			option.textContent = entry.name;

			if ( entry.slug === selectedSlug ) {
				option.selected = true;
			}

			fragment.appendChild( option );
		} );

		select.appendChild( fragment );
	}

	function findBySlug( list, slug ) {
		return ( list || [] ).filter( function ( entry ) {
			return entry.slug === slug;
		} )[ 0 ];
	}

	/** A country's cities, from the server (cached per country). */
	function loadCities( countrySlug ) {
		if ( ! countrySlug ) {
			return Promise.resolve( [] );
		}

		if ( ! cityCache[ countrySlug ] ) {
			var body = new FormData();
			body.append( 'action', api.action );
			body.append( 'country', countrySlug );

			cityCache[ countrySlug ] = fetch( api.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					return result && result.success && result.data && Array.isArray( result.data.cities ) ? result.data.cities : [];
				} )
				.catch( function () {
					delete cityCache[ countrySlug ];
					return [];
				} );
		}

		return cityCache[ countrySlug ];
	}

	function notify( citySelect ) {
		var event;

		try {
			event = new CustomEvent( 'dak-locations-updated', { bubbles: true } );
		} catch ( e ) {
			event = document.createEvent( 'CustomEvent' );
			event.initCustomEvent( 'dak-locations-updated', true, false, null );
		}

		citySelect.dispatchEvent( event );
	}

	/**
	 * Populates a Country/City/Area select trio and wires their change
	 * events so picking a Country repopulates City (and clears Area), and
	 * picking a City repopulates Area.
	 *
	 * @param {HTMLSelectElement} countrySelect     The Country <select>.
	 * @param {HTMLSelectElement} citySelect        The City <select>.
	 * @param {HTMLSelectElement} areaSelect        The Area <select>.
	 * @param {Array}             locations         Admin list (Locations::get_all() shape) — used only without dakLocationsApi.
	 * @param {string}            [selectedCountry] Country slug to pre-select.
	 * @param {string}            [selectedCity]    City slug to pre-select.
	 * @param {string}            [selectedArea]    Area slug to pre-select.
	 * @return {void}
	 */
	function wire( countrySelect, citySelect, areaSelect, locations, selectedCountry, selectedCity, selectedArea ) {
		if ( ! countrySelect || ! citySelect || ! areaSelect ) {
			return;
		}

		locations = locations || [];

		var countries = api ? api.countries : locations;
		var currentCities = [];
		var request = 0;

		function renderAreas( citySlug, preselectArea ) {
			var city = findBySlug( currentCities, citySlug );
			var areas = city && city.areas ? city.areas : [];

			fillSelect( areaSelect, areas, ! citySlug ? 'Select a city first' : ( areas.length ? 'Select an area…' : 'No areas listed (optional)' ), preselectArea || '' );
			areaSelect.disabled = 0 === areas.length;
		}

		function renderCities( countrySlug, preselectCity, preselectArea ) {
			var mine = ++request;

			if ( ! api ) {
				var country = findBySlug( locations, countrySlug );
				currentCities = country ? country.cities : [];
				fillSelect( citySelect, currentCities, 'Select a city…', preselectCity || '' );
				citySelect.disabled = 0 === currentCities.length;
				renderAreas( preselectCity || '', preselectArea );
				notify( citySelect );
				return;
			}

			currentCities = [];

			if ( ! countrySlug ) {
				fillSelect( citySelect, [], 'Select a country first', '' );
				citySelect.disabled = true;
				renderAreas( '', '' );
				notify( citySelect );
				return;
			}

			fillSelect( citySelect, [], 'Loading cities…', '' );
			citySelect.disabled = true;
			renderAreas( '', '' );

			loadCities( countrySlug ).then( function ( cities ) {
				// A newer country choice has started loading since.
				if ( mine !== request ) {
					return;
				}

				currentCities = cities;
				fillSelect( citySelect, cities, cities.length ? 'Select a city…' : 'No cities listed', preselectCity || '' );
				citySelect.disabled = 0 === cities.length;
				renderAreas( citySelect.value, preselectArea );
				notify( citySelect );
			} );
		}

		fillSelect( countrySelect, countries, 'Select a country…', selectedCountry || '' );
		renderCities( countrySelect.value, selectedCity || '', selectedArea || '' );

		countrySelect.addEventListener( 'change', function () {
			renderCities( countrySelect.value, '', '' );
		} );

		citySelect.addEventListener( 'change', function () {
			renderAreas( citySelect.value, '' );
			notify( citySelect );
		} );
	}

	window.dakCityArea = {
		wire: wire,
	};
} )();
