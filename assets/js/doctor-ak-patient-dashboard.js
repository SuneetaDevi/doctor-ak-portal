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
		wireKebabMenus();
	} );

	/**
	 * Wires the "More actions" kebab button on each appointment row: click
	 * toggles its dropdown, closes any other open one, and closes on an
	 * outside click or Escape.
	 */
	function wireKebabMenus() {
		document.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest( '[data-kebab-toggle]' );

			if ( toggle ) {
				var wrapper = toggle.closest( '.dak-patient-appt-kebab' );

				closeAllKebabMenus( wrapper );

				if ( wrapper ) {
					var isOpen = wrapper.classList.toggle( 'is-open' );
					toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
				}

				return;
			}

			if ( ! event.target.closest( '.dak-patient-appt-kebab-menu' ) ) {
				closeAllKebabMenus();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				closeAllKebabMenus();
			}
		} );
	}

	/**
	 * @param {HTMLElement} [except] A kebab wrapper to leave untouched.
	 */
	function closeAllKebabMenus( except ) {
		document.querySelectorAll( '.dak-patient-appt-kebab.is-open' ).forEach( function ( wrapper ) {
			if ( wrapper === except ) {
				return;
			}

			wrapper.classList.remove( 'is-open' );

			var toggle = wrapper.querySelector( '[data-kebab-toggle]' );

			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

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

			var refundEligible = '1' === trigger.getAttribute( 'data-refund-eligible' );
			var confirmMessage = refundEligible
				? window.dakPatientDashboard.confirmCancelRefundEligible
				: window.dakPatientDashboard.confirmCancelNoRefund;

			if ( ! window.confirm( confirmMessage ) ) {
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
						window.alert( ( result.data && result.data.message ) || window.dakPatientDashboard.genericError );
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
