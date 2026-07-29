/**
 * Doctor AK Portal — Patient dashboard "Request Refund" modal.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakRequestRefund ) {
			return;
		}

		var modal = document.getElementById( 'dak-request-refund-modal' );

		if ( ! modal ) {
			return;
		}

		var idField = document.getElementById( 'dak-request-refund-appointment-id' );
		var reasonField = document.getElementById( 'dak-request-refund-reason' );
		var errorEl = document.getElementById( 'dak-request-refund-error' );
		var saveButton = document.getElementById( 'dak-request-refund-save' );

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-request-refund]' );

			if ( trigger ) {
				idField.value = trigger.getAttribute( 'data-appointment-id' ) || '0';
				reasonField.value = '';
				clearError();
				openModal();
				return;
			}

			if ( event.target.closest( '[data-dak-request-refund-close]' ) ) {
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

			if ( '' === reasonField.value.trim() ) {
				showError( 'Please tell us why you\'re requesting a refund.' );
				return;
			}

			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_patient_request_refund' );
			formData.append( 'nonce', window.dakRequestRefund.nonce );
			formData.append( 'appointment_id', idField.value );
			formData.append( 'reason', reasonField.value );

			fetch( window.dakRequestRefund.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						window.location.reload();
						return;
					}

					showError( ( result.data && result.data.message ) || window.dakRequestRefund.genericError );
				} )
				.catch( function () {
					saveButton.disabled = false;
					showError( window.dakRequestRefund.genericError );
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
