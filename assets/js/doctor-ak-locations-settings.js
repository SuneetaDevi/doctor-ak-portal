/**
 * Doctor AK Portal — Settings → Locations admin page: repeatable City rows.
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
		 * Appends one row, optionally pre-filled with a city name + areas.
		 *
		 * @param {string} [cityName] City name to pre-fill.
		 * @param {string[]} [areaNames] Area names to pre-fill (one per line).
		 */
		function addRow( cityName, areaNames ) {
			removeEmptyState();

			var clone = template.content.cloneNode( true );

			if ( cityName ) {
				clone.querySelector( 'input' ).value = cityName;
			}

			if ( areaNames && areaNames.length ) {
				clone.querySelector( 'textarea' ).value = areaNames.join( '\n' );
			}

			rows.appendChild( clone );
		}

		addButton.addEventListener( 'click', function () {
			addRow();
		} );

		if ( loadDefaultsButton ) {
			loadDefaultsButton.addEventListener( 'click', function () {
				var defaults = ( window.dakLocationsSettings && window.dakLocationsSettings.defaultCities ) || [];

				if ( ! defaults.length ) {
					return;
				}

				if ( ! window.confirm( 'Add ' + defaults.length + ' default cities below the current list? You can review, edit, or remove any of them before saving.' ) ) {
					return;
				}

				defaults.forEach( function ( city ) {
					addRow( city.name, city.areas.map( function ( area ) { return area.name; } ) );
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
