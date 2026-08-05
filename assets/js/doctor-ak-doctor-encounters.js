/**
 * Doctor AK Portal — Doctor dashboard "Appointments" tab: saves a visit note
 * for one of the doctor's own completed appointments over AJAX (feeds the
 * patient's Medical History tab).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakDoctorEncounters ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-encounter-save]' );

			if ( ! trigger ) {
				return;
			}

			var appointmentId = trigger.getAttribute( 'data-appointment-id' );
			var row = trigger.closest( '[data-encounter-row]' );
			var textarea = row ? row.querySelector( '.dak-encounter-note-input' ) : null;
			var status = document.getElementById( 'dak-encounter-note-status-' + appointmentId );

			if ( ! textarea ) {
				return;
			}

			trigger.disabled = true;

			if ( status ) {
				status.textContent = '';
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_doctor_save_encounter_note' );
			formData.append( 'nonce', window.dakDoctorEncounters.nonce );
			formData.append( 'appointment_id', appointmentId );
			formData.append( 'note', textarea.value );

			fetch( window.dakDoctorEncounters.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					trigger.disabled = false;

					if ( result.success ) {
						if ( status ) {
							status.textContent = ( result.data && result.data.message ) || 'Saved.';
						}
						return;
					}

					showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					trigger.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );
	} );

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-doctor-encounters-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
