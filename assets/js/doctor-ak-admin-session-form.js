/**
 * Doctor AK Portal — Admin full-screen Add/Edit Session form.
 *
 * Reached via `?section=doctor-sessions&view=form[&clinic_id=X]` (mirrors
 * the Add/Edit Doctor form's page-based pattern instead of a modal). Submits
 * to the same doctor_ak_admin_clinic_save AJAX endpoint the old modal used;
 * on success it redirects back to the Doctor Sessions table.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-admin-session-form' );

		if ( ! form || ! window.dakAdminSessions ) {
			return;
		}

		wireDayToggles();
		wireTypeToggle();
		wireSubmit( form );

		if ( window.dakCityArea ) {
			var countrySelect = document.getElementById( 'dak-admin-session-country' );
			var citySelect = document.getElementById( 'dak-admin-session-city' );
			var areaSelect = document.getElementById( 'dak-admin-session-area' );

			if ( countrySelect && citySelect && areaSelect ) {
				window.dakCityArea.wire(
					countrySelect,
					citySelect,
					areaSelect,
					window.dakAdminSessions.locations,
					countrySelect.getAttribute( 'data-current' ) || '',
					citySelect.getAttribute( 'data-current' ) || '',
					areaSelect.getAttribute( 'data-current' ) || ''
				);
			}
		}
	} );

	/**
	 * Enables/disables each period row's time + slot-duration inputs based
	 * on its own checkbox.
	 */
	function wireDayToggles() {
		document.addEventListener( 'change', function ( event ) {
			if ( ! event.target.classList.contains( 'dak-availability-toggle' ) ) {
				return;
			}

			var row = event.target.closest( '.dak-availability-row' );

			if ( ! row ) {
				return;
			}

			var disabled = ! event.target.checked;
			row.querySelector( '.dak-availability-start' ).disabled = disabled;
			row.querySelector( '.dak-availability-end' ).disabled = disabled;
			row.querySelector( '.dak-clinic-slot-duration' ).disabled = disabled;
		} );
	}

	/**
	 * Hides the Address/Country/City/Area fields when "Video Consultation"
	 * is selected.
	 */
	function wireTypeToggle() {
		var typeSelect = document.getElementById( 'dak-admin-session-type' );

		if ( ! typeSelect ) {
			return;
		}

		typeSelect.addEventListener( 'change', function () {
			document.querySelectorAll( '.dak-admin-session-address-field' ).forEach( function ( field ) {
				field.classList.toggle( 'dak-hidden', 'video' === typeSelect.value );
			} );
		} );
	}

	/**
	 * Reads the Weekly Sessions grid into `sessions[day][period][field]`
	 * FormData entries.
	 *
	 * @param {FormData} formData Target FormData to append to.
	 */
	function appendSessions( formData ) {
		document.querySelectorAll( '#dak-admin-session-grid .dak-clinic-sessions-day[data-day]' ).forEach( function ( dayEl ) {
			var day = dayEl.getAttribute( 'data-day' );

			dayEl.querySelectorAll( '.dak-availability-row[data-period]' ).forEach( function ( row ) {
				var period = row.getAttribute( 'data-period' );
				var enabled = row.querySelector( '.dak-availability-toggle' ).checked;

				formData.append( 'sessions[' + day + '][' + period + '][enabled]', enabled ? '1' : '' );
				formData.append( 'sessions[' + day + '][' + period + '][start]', row.querySelector( '.dak-availability-start' ).value );
				formData.append( 'sessions[' + day + '][' + period + '][end]', row.querySelector( '.dak-availability-end' ).value );
				formData.append( 'sessions[' + day + '][' + period + '][slot_duration_minutes]', row.querySelector( '.dak-clinic-slot-duration' ).value );
			} );
		} );
	}

	function wireSubmit( form ) {
		var submitButton = document.getElementById( 'dak-admin-session-submit' );
		var listUrl = form.getAttribute( 'data-list-url' ) || '';

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();
			submitButton.disabled = true;

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_admin_clinic_save' );
			formData.append( 'nonce', window.dakAdminSessions.nonce );
			appendSessions( formData );

			fetch( window.dakAdminSessions.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitButton.disabled = false;

					if ( result.success ) {
						window.location.href = listUrl || window.location.href;
						return;
					}

					if ( result.data && result.data.errors ) {
						Object.keys( result.data.errors ).forEach( function ( field ) {
							showFieldError( field, result.data.errors[ field ] );
						} );
					} else {
						showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
					}
				} )
				.catch( function () {
					submitButton.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function clearErrors() {
		document.querySelectorAll( '#dak-admin-session-form .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-session-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showFieldError( field, message ) {
		var el = document.querySelector( '#dak-admin-session-form .dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = message;
			return;
		}

		showGeneralError( message );
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-session-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
