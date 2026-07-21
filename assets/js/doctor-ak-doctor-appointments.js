/**
 * Doctor AK Portal — Doctor dashboard "Mark as Completed" appointment action.
 *
 * Wires the button on both the "Upcoming Appointments" widget
 * (doctor-appointment-row.php) and the Appointments tab
 * (doctor-appointments-list.php) to Doctor_Appointment_Handler's AJAX
 * endpoint (doctor_ak_doctor_mark_completed).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakDoctorAppointments ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-mark-completed]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( window.dakDoctorAppointments.confirmMessage ) ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_doctor_mark_completed' );
			formData.append( 'nonce', window.dakDoctorAppointments.nonce );
			formData.append( 'appointment_id', trigger.getAttribute( 'data-appointment-id' ) );

			fetch( window.dakDoctorAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || window.dakDoctorAppointments.genericError );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( window.dakDoctorAppointments.genericError );
				} );
		} );
	} );
} )();
