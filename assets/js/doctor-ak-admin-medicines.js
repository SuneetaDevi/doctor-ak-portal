/**
 * Doctor AK Portal — Admin "Medicines" table.
 *
 * Lets an administrator add, edit, or delete any doctor's medicine, via
 * Medicine_Handler's admin AJAX endpoints (doctor_ak_admin_medicine_save/_delete).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-admin-medicine-modal' );

		if ( ! modal || ! window.dakAdminMedicines ) {
			return;
		}

		wireModalClose( modal );
		wireAdd( modal );
		wireEdit( modal );
		wireSave( modal );
		wireDelete();
	} );

	function resetModalFields() {
		document.getElementById( 'dak-admin-medicine-id' ).value = '0';
		document.getElementById( 'dak-admin-medicine-doctor' ).value = '';
		document.getElementById( 'dak-admin-medicine-name' ).value = '';
		document.getElementById( 'dak-admin-medicine-default-dosage' ).value = '';
		document.getElementById( 'dak-admin-medicine-active' ).checked = true;
	}

	function wireAdd( modal ) {
		var addButton = document.getElementById( 'dak-admin-medicine-add' );

		if ( ! addButton ) {
			return;
		}

		addButton.addEventListener( 'click', function () {
			clearErrors();
			resetModalFields();
			setModalTitle( 'Add Medicine' );
			openModal( modal );
		} );
	}

	function setModalTitle( text ) {
		var title = document.getElementById( 'dak-admin-medicine-modal-title' );

		if ( title ) {
			title.textContent = text;
		}
	}

	function wireModalClose( modal ) {
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-admin-medicine-modal-close]' ) ) {
				closeModal( modal );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal.classList.contains( 'is-open' ) ) {
				closeModal( modal );
			}
		} );
	}

	function openModal( modal ) {
		modal.classList.add( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'dak-modal-open' );
	}

	function closeModal( modal ) {
		modal.classList.remove( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'dak-modal-open' );
	}

	function wireEdit( modal ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-medicine-edit]' );

			if ( ! trigger ) {
				return;
			}

			clearErrors();
			setModalTitle( 'Edit Medicine' );

			document.getElementById( 'dak-admin-medicine-id' ).value = trigger.getAttribute( 'data-medicine-id' ) || '0';
			document.getElementById( 'dak-admin-medicine-doctor' ).value = trigger.getAttribute( 'data-doctor-id' ) || '';
			document.getElementById( 'dak-admin-medicine-name' ).value = trigger.getAttribute( 'data-name' ) || '';
			document.getElementById( 'dak-admin-medicine-default-dosage' ).value = trigger.getAttribute( 'data-default-dosage' ) || '';
			document.getElementById( 'dak-admin-medicine-active' ).checked = '1' === trigger.getAttribute( 'data-active' );

			openModal( modal );
		} );
	}

	function wireSave( modal ) {
		var saveButton = document.getElementById( 'dak-admin-medicine-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearErrors();

			var doctorId = document.getElementById( 'dak-admin-medicine-doctor' ).value;

			if ( ! doctorId ) {
				var doctorError = document.querySelector( '.dak-field-error[data-field="doctor_id"]' );

				if ( doctorError ) {
					doctorError.textContent = 'Please select a doctor.';
				}

				return;
			}

			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_medicine_save' );
			formData.append( 'nonce', window.dakAdminMedicines.nonce );
			formData.append( 'medicine_id', document.getElementById( 'dak-admin-medicine-id' ).value );
			formData.append( 'doctor_id', doctorId );
			formData.append( 'name', document.getElementById( 'dak-admin-medicine-name' ).value );
			formData.append( 'default_dosage', document.getElementById( 'dak-admin-medicine-default-dosage' ).value );
			formData.append( 'active', document.getElementById( 'dak-admin-medicine-active' ).checked ? '1' : '' );

			fetch( window.dakAdminMedicines.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						window.location.reload();
						return;
					}

					showGeneralError( errorsToMessage( result ) );
				} )
				.catch( function () {
					saveButton.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function wireDelete() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-medicine-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this medicine? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_medicine_delete' );
			formData.append( 'nonce', window.dakAdminMedicines.nonce );
			formData.append( 'medicine_id', trigger.getAttribute( 'data-medicine-id' ) );

			fetch( window.dakAdminMedicines.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					window.alert( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					window.alert( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function errorsToMessage( result ) {
		if ( result.data && result.data.errors ) {
			return Object.keys( result.data.errors ).map( function ( field ) { return result.data.errors[ field ]; } ).join( ' ' );
		}

		return ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
	}

	function clearErrors() {
		document.querySelectorAll( '#dak-admin-medicine-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-medicine-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-medicine-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
