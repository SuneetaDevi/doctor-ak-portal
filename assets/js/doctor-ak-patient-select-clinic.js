/**
 * Doctor AK Portal — Patient "Select Your Clinic" screen.
 *
 * Mirrors the Country/City/Area -> Clinic filtering pattern already used on
 * the doctor's own Clinics tab (see doctor-ak-clinics.js), just for a single
 * top-level clinic select instead of one per clinic card.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-select-clinic-form' );

		if ( ! form || ! window.dakSelectClinic ) {
			return;
		}

		var countrySelect = document.getElementById( 'dak-select-clinic-country' );
		var citySelect = document.getElementById( 'dak-select-clinic-city' );
		var areaSelect = document.getElementById( 'dak-select-clinic-area' );
		var clinicSelect = document.getElementById( 'dak-select-clinic-clinic' );
		var submitButton = document.getElementById( 'dak-select-clinic-submit' );
		var generalError = document.getElementById( 'dak-select-clinic-general-error' );

		if ( window.dakCityArea && countrySelect && citySelect && areaSelect ) {
			window.dakCityArea.wire( countrySelect, citySelect, areaSelect, window.dakSelectClinic.locations, '', '', '' );

			[ countrySelect, citySelect, areaSelect ].forEach( function ( select ) {
				select.addEventListener( 'change', renderClinicOptions );
			} );
		}

		renderClinicOptions();

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();

			if ( ! clinicSelect || ! clinicSelect.value ) {
				showFieldError( 'clinic_location_id', 'Please select a clinic.' );
				return;
			}

			submitButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_patient_select_clinic' );
			formData.append( 'nonce', window.dakSelectClinic.nonce );
			formData.append( 'clinic_location_id', clinicSelect.value );

			fetch( window.dakSelectClinic.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					submitButton.disabled = false;

					if ( result.data && result.data.errors ) {
						Object.keys( result.data.errors ).forEach( function ( field ) {
							showFieldError( field, result.data.errors[ field ] );
						} );
					} else {
						showGeneralError( ( result.data && result.data.message ) || window.dakSelectClinic.genericError );
					}
				} )
				.catch( function () {
					submitButton.disabled = false;
					showGeneralError( window.dakSelectClinic.genericError );
				} );
		} );

		/**
		 * Fills the Clinic select from window.dakSelectClinic.clinicLocations,
		 * filtered to whichever Country/City/Area are currently selected.
		 */
		function renderClinicOptions() {
			if ( ! clinicSelect ) {
				return;
			}

			var allClinicLocations = window.dakSelectClinic.clinicLocations || [];
			var country = countrySelect ? countrySelect.value : '';
			var city = citySelect ? citySelect.value : '';
			var area = areaSelect ? areaSelect.value : '';

			var filtered = allClinicLocations.filter( function ( clinicLocation ) {
				return ( ! country || clinicLocation.country === country )
					&& ( ! city || clinicLocation.city === city )
					&& ( ! area || clinicLocation.area === area );
			} );

			var previousValue = clinicSelect.value;
			clinicSelect.innerHTML = '';

			var placeholderOption = document.createElement( 'option' );
			placeholderOption.value = '';
			placeholderOption.textContent = filtered.length ? 'Select a clinic…' : 'No clinics for this area yet';
			clinicSelect.appendChild( placeholderOption );

			filtered.forEach( function ( clinicLocation ) {
				var option = document.createElement( 'option' );
				option.value = String( clinicLocation.id );
				option.textContent = clinicLocation.name + ' — ' + clinicLocation.area_label + ', ' + clinicLocation.city_label;

				if ( String( clinicLocation.id ) === previousValue ) {
					option.selected = true;
				}

				clinicSelect.appendChild( option );
			} );
		}

		function clearErrors() {
			form.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
				el.textContent = '';
			} );

			if ( generalError ) {
				generalError.textContent = '';
				generalError.classList.add( 'dak-hidden' );
			}
		}

		function showFieldError( field, message ) {
			var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );

			if ( el ) {
				el.textContent = message;
			}
		}

		function showGeneralError( message ) {
			if ( generalError ) {
				generalError.textContent = message;
				generalError.classList.remove( 'dak-hidden' );
			}
		}
	} );
} )();
