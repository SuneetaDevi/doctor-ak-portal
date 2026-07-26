/**
 * Doctor AK Portal — Doctor dashboard "+ Add Patient" modal.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-doctor-add-patient-modal' );
		var openButton = document.getElementById( 'dak-doctor-add-patient-open' );

		if ( ! modal || ! openButton || ! window.dakDoctorAddPatient ) {
			return;
		}

		openButton.addEventListener( 'click', function () {
			clearErrors();
			resetFields();
			openModal();
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-add-patient-close]' ) ) {
				closeModal();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal.classList.contains( 'is-open' ) ) {
				closeModal();
			}
		} );

		var saveButton = document.getElementById( 'dak-doctor-add-patient-save' );

		saveButton.addEventListener( 'click', function () {
			clearErrors();
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_doctor_add_patient' );
			formData.append( 'nonce', window.dakDoctorAddPatient.nonce );
			formData.append( 'first_name', document.getElementById( 'dak-doctor-add-patient-first-name' ).value );
			formData.append( 'last_name', document.getElementById( 'dak-doctor-add-patient-last-name' ).value );
			formData.append( 'email', document.getElementById( 'dak-doctor-add-patient-email' ).value );
			formData.append( 'phone_number', document.getElementById( 'dak-doctor-add-patient-phone' ).value );

			fetch( window.dakDoctorAddPatient.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						window.location.reload();
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
					saveButton.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
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

		function resetFields() {
			document.getElementById( 'dak-doctor-add-patient-first-name' ).value = '';
			document.getElementById( 'dak-doctor-add-patient-last-name' ).value = '';
			document.getElementById( 'dak-doctor-add-patient-email' ).value = '';
			document.getElementById( 'dak-doctor-add-patient-phone' ).value = '';
		}

		function clearErrors() {
			modal.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
				el.textContent = '';
			} );

			var generalError = document.getElementById( 'dak-doctor-add-patient-general-error' );

			if ( generalError ) {
				generalError.textContent = '';
				generalError.classList.add( 'dak-hidden' );
			}
		}

		function showFieldError( field, message ) {
			var el = modal.querySelector( '.dak-field-error[data-field="' + field + '"]' );

			if ( el ) {
				el.textContent = message;
			}
		}

		function showGeneralError( message ) {
			var el = document.getElementById( 'dak-doctor-add-patient-general-error' );

			if ( el ) {
				el.textContent = message;
				el.classList.remove( 'dak-hidden' );
			}
		}
	} );
} )();
