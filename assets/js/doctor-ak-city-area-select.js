/**
 * Doctor AK Portal — Shared City -> Area cascading <select> helper.
 *
 * Used everywhere a doctor's location is picked: self-registration, profile
 * edit, admin add/edit doctor, the doctor's own Clinics tab, and the admin
 * "Doctor Sessions" modal. Each of those localizes the same admin-managed
 * city/area list (see Locations::get_all()) under a `locations` key on its
 * own localized object (dakRegistration.locations, dakClinics.locations,
 * etc.) and calls window.dakCityArea.wire() once per City/Area select pair.
 */
( function () {
	'use strict';

	/**
	 * Fills a City <select> with every city, selecting `selectedSlug` if given.
	 *
	 * @param {HTMLSelectElement} select       The City <select>.
	 * @param {Array}             locations    Locations::get_all() shape.
	 * @param {string}            selectedSlug City slug to pre-select, if any.
	 * @return {void}
	 */
	function populateCitySelect( select, locations, selectedSlug ) {
		var placeholder = select.getAttribute( 'data-placeholder' ) || 'Select a city…';

		select.innerHTML = '';

		var placeholderOption = document.createElement( 'option' );
		placeholderOption.value = '';
		placeholderOption.textContent = placeholder;
		select.appendChild( placeholderOption );

		locations.forEach( function ( city ) {
			var option = document.createElement( 'option' );
			option.value = city.slug;
			option.textContent = city.name;

			if ( city.slug === selectedSlug ) {
				option.selected = true;
			}

			select.appendChild( option );
		} );
	}

	/**
	 * Fills an Area <select> with the areas belonging to `citySlug`,
	 * selecting `selectedSlug` if given. Disables the select (with a hint
	 * placeholder) when no city is chosen yet or it has no areas.
	 *
	 * @param {HTMLSelectElement} select       The Area <select>.
	 * @param {Array}             locations    Locations::get_all() shape.
	 * @param {string}            citySlug     Currently selected city's slug.
	 * @param {string}            selectedSlug Area slug to pre-select, if any.
	 * @return {void}
	 */
	function populateAreaSelect( select, locations, citySlug, selectedSlug ) {
		var city = locations.filter( function ( c ) {
			return c.slug === citySlug;
		} )[ 0 ];

		var areas = city ? city.areas : [];

		select.innerHTML = '';

		var placeholderOption = document.createElement( 'option' );
		placeholderOption.value = '';
		placeholderOption.textContent = citySlug ? 'Select an area…' : 'Select a city first';
		select.appendChild( placeholderOption );

		areas.forEach( function ( area ) {
			var option = document.createElement( 'option' );
			option.value = area.slug;
			option.textContent = area.name;

			if ( area.slug === selectedSlug ) {
				option.selected = true;
			}

			select.appendChild( option );
		} );

		select.disabled = ! citySlug || 0 === areas.length;
	}

	/**
	 * Populates a City/Area select pair and wires the City select's change
	 * event to repopulate the Area select.
	 *
	 * @param {HTMLSelectElement} citySelect      The City <select>.
	 * @param {HTMLSelectElement} areaSelect      The Area <select>.
	 * @param {Array}             locations       Locations::get_all() shape.
	 * @param {string}            [selectedCity]  City slug to pre-select.
	 * @param {string}            [selectedArea]  Area slug to pre-select.
	 * @return {void}
	 */
	function wire( citySelect, areaSelect, locations, selectedCity, selectedArea ) {
		if ( ! citySelect || ! areaSelect ) {
			return;
		}

		locations = locations || [];

		populateCitySelect( citySelect, locations, selectedCity || '' );
		populateAreaSelect( areaSelect, locations, selectedCity || '', selectedArea || '' );

		citySelect.addEventListener( 'change', function () {
			populateAreaSelect( areaSelect, locations, citySelect.value, '' );
		} );
	}

	window.dakCityArea = {
		wire: wire,
		populateCitySelect: populateCitySelect,
		populateAreaSelect: populateAreaSelect,
	};
} )();
