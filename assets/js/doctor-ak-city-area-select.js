/**
 * Doctor AK Portal — Shared Country -> City -> Area cascading <select> helper.
 *
 * Used everywhere a doctor's location is picked: self-registration, profile
 * edit, admin add/edit doctor, the doctor's own Clinics tab, and the admin
 * "Doctor Sessions" modal. Each of those localizes the same admin-managed
 * location list (see Locations::get_all()) under a `locations` key on its
 * own localized object (dakRegistration.locations, dakClinics.locations,
 * etc.) and calls window.dakCityArea.wire() once per Country/City/Area
 * select trio.
 */
( function () {
	'use strict';

	/**
	 * Fills a <select> from a slug=>name-shaped list of `{ slug, name }`
	 * entries, selecting `selectedSlug` if given.
	 *
	 * @param {HTMLSelectElement} select       The <select> to fill.
	 * @param {Array}             entries      List of `{ slug, name }`.
	 * @param {string}            placeholder  First, empty-value option's label.
	 * @param {string}            selectedSlug Slug to pre-select, if any.
	 * @return {void}
	 */
	function fillSelect( select, entries, placeholder, selectedSlug ) {
		select.innerHTML = '';

		var placeholderOption = document.createElement( 'option' );
		placeholderOption.value = '';
		placeholderOption.textContent = placeholder;
		select.appendChild( placeholderOption );

		entries.forEach( function ( entry ) {
			var option = document.createElement( 'option' );
			option.value = entry.slug;
			option.textContent = entry.name;

			if ( entry.slug === selectedSlug ) {
				option.selected = true;
			}

			select.appendChild( option );
		} );
	}

	function findBySlug( list, slug ) {
		return ( list || [] ).filter( function ( entry ) {
			return entry.slug === slug;
		} )[ 0 ];
	}

	/**
	 * Populates a Country/City/Area select trio and wires their change
	 * events so picking a Country repopulates City (and clears Area), and
	 * picking a City repopulates Area.
	 *
	 * @param {HTMLSelectElement} countrySelect   The Country <select>.
	 * @param {HTMLSelectElement} citySelect      The City <select>.
	 * @param {HTMLSelectElement} areaSelect      The Area <select>.
	 * @param {Array}             locations       Locations::get_all() shape (countries).
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

		function renderCities( countrySlug, preselectCity ) {
			var country = findBySlug( locations, countrySlug );

			fillSelect( citySelect, country ? country.cities : [], 'Select a city…', preselectCity || '' );
			citySelect.disabled = ! country || 0 === country.cities.length;
		}

		function renderAreas( countrySlug, citySlug, preselectArea ) {
			var country = findBySlug( locations, countrySlug );
			var city = country ? findBySlug( country.cities, citySlug ) : null;

			fillSelect( areaSelect, city ? city.areas : [], citySlug ? 'Select an area…' : 'Select a city first', preselectArea || '' );
			areaSelect.disabled = ! city || 0 === city.areas.length;
		}

		fillSelect( countrySelect, locations, 'Select a country…', selectedCountry || '' );
		renderCities( selectedCountry || '', selectedCity || '' );
		renderAreas( selectedCountry || '', selectedCity || '', selectedArea || '' );

		countrySelect.addEventListener( 'change', function () {
			renderCities( countrySelect.value, '' );
			renderAreas( countrySelect.value, '', '' );
		} );

		citySelect.addEventListener( 'change', function () {
			renderAreas( countrySelect.value, citySelect.value, '' );
		} );
	}

	window.dakCityArea = {
		wire: wire,
	};
} )();
