/**
 * Doctor AK Portal — Doctor dashboard "Mark as Completed", "Mark Paid",
 * "Pay Now", and "Cancel" appointment actions.
 *
 * Wires the buttons on both the "Upcoming Appointments" widget
 * (doctor-appointment-row.php) and the Appointments tab
 * (doctor-appointments-list.php) to Doctor_Appointment_Handler's AJAX
 * endpoints (doctor_ak_doctor_mark_completed / doctor_ak_doctor_mark_paid /
 * doctor_ak_doctor_pay_now / doctor_ak_doctor_cancel_appointment).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakDoctorAppointments ) {
			return;
		}

		wireAction( '[data-mark-completed]', 'doctor_ak_doctor_mark_completed', function () {
			return window.dakDoctorAppointments.confirmMessage;
		} );

		wireAction( '[data-doctor-mark-paid]', 'doctor_ak_doctor_mark_paid', function () {
			return window.dakDoctorAppointments.confirmMarkPaidMessage;
		} );

		wireAction( '[data-doctor-cancel-appointment]', 'doctor_ak_doctor_cancel_appointment', function () {
			return window.dakDoctorAppointments.confirmCancelMessage;
		} );

		wirePayNow();
	} );

	/**
	 * "Pay Now" redirects the browser to the real Swich payment URL (same
	 * as the patient dashboard's own Pay Now) instead of reloading the
	 * page, so it can't reuse wireAction()'s reload-on-success behaviour.
	 */
	function wirePayNow() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-doctor-pay-now]' );

			if ( ! trigger ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_doctor_pay_now' );
			formData.append( 'nonce', window.dakDoctorAppointments.nonce );
			formData.append( 'appointment_id', trigger.getAttribute( 'data-appointment-id' ) );

			fetch( window.dakDoctorAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success && result.data && result.data.payment_url ) {
						window.location.href = result.data.payment_url;
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
	}

	function wireAction( selector, action, confirmMessage ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( selector );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( confirmMessage() ) ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', action );
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
	}
} )();
