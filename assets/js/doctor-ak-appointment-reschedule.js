/**
 * Doctor AK Portal — "Reschedule Appointment" modal, shared by the doctor
 * and patient dashboards. Which AJAX action to call (doctor vs patient) is
 * supplied by whichever dashboard localized `dakAppointmentReschedule`.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAppointmentReschedule ) {
			return;
		}

		var modal = document.getElementById( 'dak-reschedule-appointment-modal' );

		if ( ! modal ) {
			return;
		}

		var idField   = document.getElementById( 'dak-reschedule-appointment-id' );
		var dateField = document.getElementById( 'dak-reschedule-appointment-date' );
		var timeField = document.getElementById( 'dak-reschedule-appointment-time' );
		var errorEl   = document.getElementById( 'dak-reschedule-appointment-error' );
		var saveButton = document.getElementById( 'dak-reschedule-appointment-save' );

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-reschedule-appointment]' );

			if ( trigger ) {
				idField.value = trigger.getAttribute( 'data-appointment-id' ) || '0';
				dateField.value = trigger.getAttribute( 'data-date' ) || '';
				timeField.value = trigger.getAttribute( 'data-time' ) || '';
				clearError();
				openModal();
				return;
			}

			if ( event.target.closest( '[data-dak-reschedule-close]' ) ) {
				closeModal();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal.classList.contains( 'is-open' ) ) {
				closeModal();
			}
		} );

		saveButton.addEventListener( 'click', function () {
			clearError();
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', window.dakAppointmentReschedule.action );
			formData.append( 'nonce', window.dakAppointmentReschedule.nonce );
			formData.append( 'appointment_id', idField.value );
			formData.append( 'date', dateField.value );
			formData.append( 'time', timeField.value );

			fetch( window.dakAppointmentReschedule.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						window.location.reload();
						return;
					}

					showError( ( result.data && result.data.message ) || window.dakAppointmentReschedule.genericError );
				} )
				.catch( function () {
					saveButton.disabled = false;
					showError( window.dakAppointmentReschedule.genericError );
				} );
		} );

		function openModal() {
			modal.classList.add( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'dak-modal-open' );
		}

		function closeModal() {
			modal.classList.remove( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'dak-modal-open' );
		}

		function clearError() {
			if ( errorEl ) {
				errorEl.textContent = '';
				errorEl.classList.add( 'dak-hidden' );
			}
		}

		function showError( message ) {
			if ( errorEl ) {
				errorEl.textContent = message;
				errorEl.classList.remove( 'dak-hidden' );
			}
		}
	} );
} )();
