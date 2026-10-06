/**
 * Doctor AK Portal — "Check In" appointment action, shared by the Admin
 * and Doctor dashboards. Opens (or resumes) a clinical Encounter for the
 * appointment (see Encounter_Handler::handle_check_in()) and redirects to
 * its detail screen on success.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakCheckIn ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-check-in]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( window.dakCheckIn.confirmMessage ) ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_check_in' );
			formData.append( 'nonce', window.dakCheckIn.nonce );
			formData.append( 'appointment_id', trigger.getAttribute( 'data-appointment-id' ) );

			fetch( window.dakCheckIn.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success && result.data && result.data.encounter_id ) {
						var separator = window.dakCheckIn.encounterUrl.indexOf( '?' ) > -1 ? '&' : '?';
						// The list this was clicked in (from=… plus its filters,
						// pre-built server-side — see Encounter_Return), so the
						// encounter's back link returns there, not always to
						// Appointments.
						var returnQuery = trigger.getAttribute( 'data-return-query' );

						window.location.href = window.dakCheckIn.encounterUrl + separator + 'encounter_id=' + result.data.encounter_id + ( returnQuery ? '&' + returnQuery : '' );
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || window.dakCheckIn.genericError );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( window.dakCheckIn.genericError );
				} );
		} );
	} );
} )();
