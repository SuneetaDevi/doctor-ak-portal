/**
 * Doctor AK Portal — Patient dashboard.
 *
 * Wires the "Pay Now" and "Cancel" actions on upcoming-appointment rows,
 * via Patient_Appointment_Handler's AJAX endpoints
 * (doctor_ak_patient_pay_now / doctor_ak_patient_cancel_appointment).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakPatientDashboard ) {
			return;
		}

		wirePayNow();
		wireCancel();
	} );

	function wirePayNow() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-pay-now]' );

			if ( ! trigger ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_patient_pay_now' );
			formData.append( 'nonce', window.dakPatientDashboard.nonce );
			formData.append( 'appointment_id', trigger.getAttribute( 'data-appointment-id' ) );

			fetch( window.dakPatientDashboard.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success && result.data && result.data.payment_url ) {
						window.location.href = result.data.payment_url;
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || window.dakPatientDashboard.genericError );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( window.dakPatientDashboard.genericError );
				} );
		} );
	}

	function wireCancel() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-cancel-appointment]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( window.dakPatientDashboard.confirmCancel ) ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_patient_cancel_appointment' );
			formData.append( 'nonce', window.dakPatientDashboard.nonce );
			formData.append( 'appointment_id', trigger.getAttribute( 'data-appointment-id' ) );

			fetch( window.dakPatientDashboard.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || window.dakPatientDashboard.genericError );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( window.dakPatientDashboard.genericError );
				} );
		} );
	}
} )();
