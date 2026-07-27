/**
 * Doctor AK Portal — Doctor dashboard "Add/Edit Patient" modal.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-doctor-add-patient-modal' );
		var openButton = document.getElementById( 'dak-doctor-add-patient-open' );

		if ( ! modal || ! window.dakDoctorAddPatient ) {
			return;
		}

		var idField = document.getElementById( 'dak-doctor-add-patient-id' );
		var titleText = document.getElementById( 'dak-doctor-add-patient-title-text' );
		var saveLabel = document.getElementById( 'dak-doctor-add-patient-save-label' );
		var hint = document.getElementById( 'dak-doctor-add-patient-hint' );
		var clinicField = document.getElementById( 'dak-doctor-add-patient-clinic-field' );

		if ( openButton ) {
			openButton.addEventListener( 'click', function () {
				clearErrors();
				resetFields();
				openModal();
			} );
		}

		document.addEventListener( 'click', function ( event ) {
			var editButton = event.target.closest( '[data-dak-edit-patient]' );

			if ( editButton ) {
				clearErrors();
				resetFields();
				openEditModal( editButton );
				openModal();
				return;
			}

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

			var patientId = idField ? parseInt( idField.value, 10 ) : 0;
			var isEdit = patientId > 0;

			var formData = new FormData();
			formData.append( 'action', isEdit ? 'doctor_ak_doctor_edit_patient' : 'doctor_ak_doctor_add_patient' );
			formData.append( 'nonce', window.dakDoctorAddPatient.nonce );
			formData.append( 'first_name', document.getElementById( 'dak-doctor-add-patient-first-name' ).value );
			formData.append( 'last_name', document.getElementById( 'dak-doctor-add-patient-last-name' ).value );
			formData.append( 'email', document.getElementById( 'dak-doctor-add-patient-email' ).value );
			formData.append( 'phone_number', document.getElementById( 'dak-doctor-add-patient-phone' ).value );

			if ( isEdit ) {
				formData.append( 'patient_id', patientId );
			} else {
				var clinicSelect = document.getElementById( 'dak-doctor-add-patient-clinic' );

				if ( clinicSelect ) {
					formData.append( 'clinic_id', clinicSelect.value );
				}
			}

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
			if ( idField ) {
				idField.value = '0';
			}

			document.getElementById( 'dak-doctor-add-patient-first-name' ).value = '';
			document.getElementById( 'dak-doctor-add-patient-last-name' ).value = '';
			document.getElementById( 'dak-doctor-add-patient-email' ).value = '';
			document.getElementById( 'dak-doctor-add-patient-phone' ).value = '';

			var clinicSelect = document.getElementById( 'dak-doctor-add-patient-clinic' );

			if ( clinicSelect ) {
				clinicSelect.value = '0';
			}

			if ( titleText ) {
				titleText.textContent = 'Add Patient';
			}

			if ( saveLabel ) {
				saveLabel.textContent = 'Add Patient';
			}

			if ( hint ) {
				hint.classList.remove( 'dak-hidden' );
			}

			if ( clinicField ) {
				clinicField.classList.remove( 'dak-hidden' );
			}
		}

		function openEditModal( editButton ) {
			if ( idField ) {
				idField.value = editButton.getAttribute( 'data-patient-id' ) || '0';
			}

			document.getElementById( 'dak-doctor-add-patient-first-name' ).value = editButton.getAttribute( 'data-first-name' ) || '';
			document.getElementById( 'dak-doctor-add-patient-last-name' ).value = editButton.getAttribute( 'data-last-name' ) || '';
			document.getElementById( 'dak-doctor-add-patient-email' ).value = editButton.getAttribute( 'data-email' ) || '';
			document.getElementById( 'dak-doctor-add-patient-phone' ).value = editButton.getAttribute( 'data-phone' ) || '';

			if ( titleText ) {
				titleText.textContent = 'Edit Patient';
			}

			if ( saveLabel ) {
				saveLabel.textContent = 'Save Changes';
			}

			if ( hint ) {
				hint.classList.add( 'dak-hidden' );
			}

			if ( clinicField ) {
				clinicField.classList.add( 'dak-hidden' );
			}
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
