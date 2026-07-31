/**
 * Doctor AK Portal — Admin dashboard "Locations" section: a nested
 * repeatable-row editor (Country -> City -> Area), matching the doctor
 * profile's "Awards & Recognition" add/remove-row pattern. On save, each
 * country's cities/areas are serialized into the same "City: Area1, Area2"
 * text-block shape Locations::sanitize_from_request() already expects, so
 * the AJAX handler (and the wp-admin Settings → Locations page) needed no
 * changes.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-locations-form' );
		var countriesRows = document.querySelector( '[data-countries-rows]' );
		var addCountryButton = document.getElementById( 'dak-locations-add-country' );
		var loadDefaultsButton = document.getElementById( 'dak-locations-load-defaults' );
		var saveButton = document.getElementById( 'dak-locations-save' );

		if ( ! form || ! countriesRows || ! addCountryButton || ! window.dakLocations ) {
			return;
		}

		function removeEmptyState() {
			var empty = document.getElementById( 'dak-locations-empty' );

			if ( empty ) {
				empty.remove();
			}
		}

		function addAreaRow( areasRows, areaName ) {
			var row = document.createElement( 'span' );
			row.className = 'dak-area-chip';
			row.setAttribute( 'data-area-row', '' );

			var input = document.createElement( 'input' );
			input.type = 'text';
			input.className = 'dak-area-name';
			input.placeholder = 'Area';
			input.setAttribute( 'list', 'dak-area-datalist' );
			input.value = areaName || '';

			var removeButton = document.createElement( 'button' );
			removeButton.type = 'button';
			removeButton.className = 'dak-awards-remove';
			removeButton.setAttribute( 'data-remove-area', '' );
			removeButton.setAttribute( 'aria-label', 'Remove area' );
			removeButton.textContent = '×';

			row.appendChild( input );
			row.appendChild( removeButton );
			areasRows.appendChild( row );

			return row;
		}

		function addCityRow( citiesRows, cityName, areaNames ) {
			var row = document.createElement( 'div' );
			row.className = 'dak-city-row';
			row.setAttribute( 'data-city-row', '' );

			var nameInput = document.createElement( 'input' );
			nameInput.type = 'text';
			nameInput.className = 'dak-city-name';
			nameInput.placeholder = 'City name';
			nameInput.setAttribute( 'list', 'dak-city-datalist' );
			nameInput.value = cityName || '';

			var areasRows = document.createElement( 'div' );
			areasRows.className = 'dak-areas-rows';
			areasRows.setAttribute( 'data-areas-rows', '' );

			( areaNames || [] ).forEach( function ( areaName ) {
				addAreaRow( areasRows, areaName );
			} );

			var addAreaButton = document.createElement( 'button' );
			addAreaButton.type = 'button';
			addAreaButton.className = 'dak-button dak-button-secondary dak-button-sm';
			addAreaButton.setAttribute( 'data-add-area', '' );
			addAreaButton.textContent = '+ Add Area';

			var removeCityButton = document.createElement( 'button' );
			removeCityButton.type = 'button';
			removeCityButton.className = 'dak-awards-remove';
			removeCityButton.setAttribute( 'data-remove-city', '' );
			removeCityButton.setAttribute( 'aria-label', 'Remove city' );
			removeCityButton.textContent = '×';

			row.appendChild( nameInput );
			row.appendChild( areasRows );
			row.appendChild( addAreaButton );
			row.appendChild( removeCityButton );
			citiesRows.appendChild( row );

			return row;
		}

		function addCountryRow( countryName, cities ) {
			removeEmptyState();

			var row = document.createElement( 'div' );
			row.className = 'dak-country-row';
			row.setAttribute( 'data-country-row', '' );

			var header = document.createElement( 'div' );
			header.className = 'dak-country-row-header';

			var nameInput = document.createElement( 'input' );
			nameInput.type = 'text';
			nameInput.className = 'dak-country-name';
			nameInput.placeholder = 'Country name';
			nameInput.setAttribute( 'list', 'dak-country-datalist' );
			nameInput.value = countryName || '';

			var removeCountryButton = document.createElement( 'button' );
			removeCountryButton.type = 'button';
			removeCountryButton.className = 'dak-awards-remove';
			removeCountryButton.setAttribute( 'data-remove-country', '' );
			removeCountryButton.setAttribute( 'aria-label', 'Remove country' );
			removeCountryButton.textContent = '×';

			header.appendChild( nameInput );
			header.appendChild( removeCountryButton );

			var citiesRows = document.createElement( 'div' );
			citiesRows.className = 'dak-cities-rows';
			citiesRows.setAttribute( 'data-cities-rows', '' );

			( cities || [] ).forEach( function ( city ) {
				addCityRow( citiesRows, city.name, ( city.areas || [] ).map( function ( area ) { return area.name; } ) );
			} );

			var addCityButton = document.createElement( 'button' );
			addCityButton.type = 'button';
			addCityButton.className = 'dak-button dak-button-secondary dak-button-sm';
			addCityButton.setAttribute( 'data-add-city', '' );
			addCityButton.textContent = '+ Add City';

			row.appendChild( header );
			row.appendChild( citiesRows );
			row.appendChild( addCityButton );
			countriesRows.appendChild( row );

			return row;
		}

		addCountryButton.addEventListener( 'click', function () {
			addCountryRow();
		} );

		if ( loadDefaultsButton ) {
			loadDefaultsButton.addEventListener( 'click', function () {
				var defaults = ( window.dakLocations && window.dakLocations.defaultCountries ) || [];

				if ( ! defaults.length ) {
					return;
				}

				if ( ! window.confirm( 'Add ' + defaults.length + ' default countries below the current list? You can review, edit, or remove any of them before saving.' ) ) {
					return;
				}

				defaults.forEach( function ( country ) {
					addCountryRow( country.name, country.cities );
				} );
			} );
		}

		countriesRows.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-add-city]' ) ) {
				var countryRow = event.target.closest( '[data-country-row]' );
				var citiesRows = countryRow && countryRow.querySelector( '[data-cities-rows]' );

				if ( citiesRows ) {
					addCityRow( citiesRows );
				}

				return;
			}

			if ( event.target.closest( '[data-add-area]' ) ) {
				var cityRow = event.target.closest( '[data-city-row]' );
				var areasRows = cityRow && cityRow.querySelector( '[data-areas-rows]' );

				if ( areasRows ) {
					addAreaRow( areasRows );
				}

				return;
			}

			var removeCountry = event.target.closest( '[data-remove-country]' );

			if ( removeCountry ) {
				var toRemoveCountry = removeCountry.closest( '[data-country-row]' );

				if ( toRemoveCountry ) {
					toRemoveCountry.remove();
				}

				return;
			}

			var removeCity = event.target.closest( '[data-remove-city]' );

			if ( removeCity ) {
				var toRemoveCity = removeCity.closest( '[data-city-row]' );

				if ( toRemoveCity ) {
					toRemoveCity.remove();
				}

				return;
			}

			var removeArea = event.target.closest( '[data-remove-area]' );

			if ( removeArea ) {
				var toRemoveArea = removeArea.closest( '[data-area-row]' );

				if ( toRemoveArea ) {
					toRemoveArea.remove();
				}
			}
		} );

		/**
		 * Walks the DOM and serializes it into the `name[]` / `cities[]`
		 * text-block pairs the AJAX handler expects.
		 *
		 * @return {{names: string[], citiesBlocks: string[]}}
		 */
		function serialize() {
			var names = [];
			var citiesBlocks = [];

			countriesRows.querySelectorAll( '[data-country-row]' ).forEach( function ( countryRow ) {
				var countryName = countryRow.querySelector( '.dak-country-name' ).value.trim();

				if ( '' === countryName ) {
					return;
				}

				var lines = [];

				countryRow.querySelectorAll( '[data-city-row]' ).forEach( function ( cityRow ) {
					var cityName = cityRow.querySelector( '.dak-city-name' ).value.trim();

					if ( '' === cityName ) {
						return;
					}

					var areaNames = Array.prototype.map.call( cityRow.querySelectorAll( '.dak-area-name' ), function ( input ) {
						return input.value.trim();
					} ).filter( function ( areaName ) {
						return '' !== areaName;
					} );

					lines.push( areaNames.length ? cityName + ': ' + areaNames.join( ', ' ) : cityName );
				} );

				names.push( countryName );
				citiesBlocks.push( lines.join( '\n' ) );
			} );

			return { names: names, citiesBlocks: citiesBlocks };
		}

		if ( saveButton ) {
			saveButton.addEventListener( 'click', function () {
				hide( 'dak-locations-error' );
				hide( 'dak-locations-success' );
				saveButton.disabled = true;

				var serialized = serialize();

				var formData = new FormData();
				formData.append( 'action', 'doctor_ak_admin_locations_save' );
				formData.append( 'nonce', window.dakLocations.nonce );

				serialized.names.forEach( function ( name ) {
					formData.append( 'name[]', name );
				} );

				serialized.citiesBlocks.forEach( function ( block ) {
					formData.append( 'cities[]', block );
				} );

				fetch( window.dakLocations.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
					.then( function ( response ) { return response.json(); } )
					.then( function ( result ) {
						saveButton.disabled = false;

						if ( result.success ) {
							show( 'dak-locations-success', ( result.data && result.data.message ) || 'Saved.' );
							return;
						}

						show( 'dak-locations-error', ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
					} )
					.catch( function () {
						saveButton.disabled = false;
						show( 'dak-locations-error', 'Something went wrong. Please try again.' );
					} );
			} );
		}

		function hide( id ) {
			var el = document.getElementById( id );

			if ( el ) {
				el.classList.add( 'dak-hidden' );
			}
		}

		function show( id, message ) {
			var el = document.getElementById( id );

			if ( el ) {
				el.textContent = message;
				el.classList.remove( 'dak-hidden' );
			}
		}
	} );
} )();
