/**
 * Doctor AK Portal — Settings → Locations admin page: repeatable Country rows.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var rows = document.getElementById( 'dak-locations-rows' );
		var addButton = document.getElementById( 'dak-locations-add-row' );
		var loadDefaultsButton = document.getElementById( 'dak-locations-load-defaults' );
		var template = document.getElementById( 'dak-locations-row-template' );

		if ( ! rows || ! addButton || ! template ) {
			return;
		}

		function removeEmptyState() {
			var empty = document.getElementById( 'dak-locations-empty' );

			if ( empty ) {
				empty.remove();
			}
		}

		/**
		 * Builds the `cities` textarea value from a default-seed country's
		 * cities array: one "City: Area 1, Area 2" line per city.
		 *
		 * @param {Object[]} cities List of `{ name, areas: [ { name } ] }`.
		 * @return {string}
		 */
		function citiesToTextareaValue( cities ) {
			return cities.map( function ( city ) {
				var areaNames = city.areas.map( function ( area ) { return area.name; } );

				return areaNames.length ? city.name + ': ' + areaNames.join( ', ' ) : city.name;
			} ).join( '\n' );
		}

		/**
		 * Appends one row, optionally pre-filled with a country name + cities.
		 *
		 * @param {string} [countryName] Country name to pre-fill.
		 * @param {string} [citiesText]  Pre-built `cities` textarea value.
		 */
		function addRow( countryName, citiesText ) {
			removeEmptyState();

			var clone = template.content.cloneNode( true );

			if ( countryName ) {
				clone.querySelector( 'input' ).value = countryName;
			}

			if ( citiesText ) {
				clone.querySelector( 'textarea' ).value = citiesText;
			}

			rows.appendChild( clone );
		}

		addButton.addEventListener( 'click', function () {
			addRow();
		} );

		if ( loadDefaultsButton ) {
			loadDefaultsButton.addEventListener( 'click', function () {
				var defaults = ( window.dakLocationsSettings && window.dakLocationsSettings.defaultCountries ) || [];

				if ( ! defaults.length ) {
					return;
				}

				if ( ! window.confirm( 'Add ' + defaults.length + ' default countries below the current list? You can review, edit, or remove any of them before saving.' ) ) {
					return;
				}

				defaults.forEach( function ( country ) {
					addRow( country.name, citiesToTextareaValue( country.cities ) );
				} );
			} );
		}

		rows.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-locations-remove-row]' );

			if ( ! trigger ) {
				return;
			}

			var row = trigger.closest( '.dak-locations-row' );

			if ( row ) {
				row.remove();
			}
		} );
	} );
} )();
