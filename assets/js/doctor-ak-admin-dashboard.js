/**
 * Doctor AK Portal — Administrator dashboard (Users: Doctors/Patients).
 *
 * Handles the Add/Edit modal and the AJAX create/update/deactivate/delete
 * calls for the accounts table. On success, each action simply reloads the
 * page rather than patching the table client-side — the table is otherwise
 * fully server-rendered. No-ops entirely on sections that don't render a
 * modal or a table (Dashboard overview, and the placeholder sections).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initModal();
		initRowActions();
	} );

	/**
	 * Wires up the Add/Edit modal: open/close, populating it for edits, and
	 * submitting the form over AJAX.
	 */
	function initModal() {
		var modal = document.getElementById( 'dak-admin-user-modal' );
		var form = document.getElementById( 'dak-admin-user-form' );

		if ( ! modal || ! form ) {
			return;
		}

		var addButton = document.getElementById( 'dak-admin-add-user' );
		var title = document.getElementById( 'dak-admin-user-modal-title' );
		var generalError = document.getElementById( 'dak-admin-user-modal-general-error' );
		var passwordField = document.getElementById( 'dak-admin-user-password' );
		var passwordHint = document.getElementById( 'dak-admin-user-password-hint' );
		var roleInput = document.getElementById( 'dak-admin-user-role' );
		var idInput = document.getElementById( 'dak-admin-user-id' );
		var submitButton = document.getElementById( 'dak-admin-user-submit' );

		if ( addButton ) {
			addButton.addEventListener( 'click', function () {
				openForCreate( addButton.getAttribute( 'data-role' ) );
			} );
		}

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-admin-modal-close]' ) ) {
				closeModal();
			}

			var editTrigger = event.target.closest( '[data-admin-edit-user]' );

			if ( editTrigger ) {
				openForEdit( editTrigger );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal.classList.contains( 'is-open' ) ) {
				closeModal();
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submitForm();
		} );

		function openForCreate( role ) {
			form.reset();
			clearFieldErrors();
			hideGeneralError();
			idInput.value = '0';
			roleInput.value = role;
			passwordField.required = true;
			passwordHint.textContent = '';
			title.textContent = 'doctor' === role ? 'Add Doctor' : 'Add Patient';
			openModal();
		}

		function openForEdit( trigger ) {
			form.reset();
			clearFieldErrors();
			hideGeneralError();

			idInput.value = trigger.getAttribute( 'data-user-id' );
			document.getElementById( 'dak-admin-user-first-name' ).value = trigger.getAttribute( 'data-first-name' ) || '';
			document.getElementById( 'dak-admin-user-last-name' ).value = trigger.getAttribute( 'data-last-name' ) || '';
			document.getElementById( 'dak-admin-user-email' ).value = trigger.getAttribute( 'data-email' ) || '';

			var select = document.getElementById( 'dak-admin-user-specializations' );

			if ( select ) {
				var specializations = ( trigger.getAttribute( 'data-specializations' ) || '' ).split( ',' ).filter( Boolean );

				Array.prototype.forEach.call( select.options, function ( option ) {
					option.selected = specializations.indexOf( option.value ) !== -1;
				} );
			}

			passwordField.required = false;
			passwordHint.textContent = 'Leave blank to keep the current password.';
			title.textContent = roleInput.value === 'doctor' ? 'Edit Doctor' : 'Edit Patient';
			openModal();
		}

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

		function clearFieldErrors() {
			form.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
				el.textContent = '';
			} );
		}

		function showFieldError( field, message ) {
			var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );

			if ( el ) {
				el.textContent = message;
			}
		}

		function hideGeneralError() {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}

		function showGeneralError( message ) {
			generalError.textContent = message;
			generalError.classList.remove( 'dak-hidden' );
		}

		function submitForm() {
			clearFieldErrors();
			hideGeneralError();

			submitButton.disabled = true;

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_admin_save_user' );
			formData.append( 'nonce', dakAdminUsers.nonce );

			fetch( dakAdminUsers.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					if ( result.data && result.data.errors ) {
						Object.keys( result.data.errors ).forEach( function ( field ) {
							showFieldError( field, result.data.errors[ field ] );
						} );
					} else {
						showGeneralError( ( result.data && result.data.message ) || dakAdminUsers.genericError );
					}

					submitButton.disabled = false;
				} )
				.catch( function () {
					showGeneralError( dakAdminUsers.genericError );
					submitButton.disabled = false;
				} );
		}
	}

	/**
	 * Wires up each row's Deactivate/Activate and Delete buttons.
	 */
	function initRowActions() {
		document.addEventListener( 'click', function ( event ) {
			var toggleTrigger = event.target.closest( '[data-admin-toggle-status]' );
			var deleteTrigger = event.target.closest( '[data-admin-delete-user]' );

			if ( toggleTrigger ) {
				handleToggleStatus( toggleTrigger );
			} else if ( deleteTrigger ) {
				handleDelete( deleteTrigger );
			}
		} );
	}

	function handleToggleStatus( trigger ) {
		var isDisabled = '1' === trigger.getAttribute( 'data-is-disabled' );
		var confirmMessage = isDisabled ? dakAdminUsers.confirmEnable : dakAdminUsers.confirmDisable;

		if ( ! window.confirm( confirmMessage ) ) {
			return;
		}

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_admin_toggle_status' );
		formData.append( 'nonce', dakAdminUsers.nonce );
		formData.append( 'user_id', trigger.getAttribute( 'data-user-id' ) );

		fetch( dakAdminUsers.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( result.success ) {
					window.location.reload();
				} else {
					window.alert( ( result.data && result.data.message ) || dakAdminUsers.genericError );
				}
			} )
			.catch( function () {
				window.alert( dakAdminUsers.genericError );
			} );
	}

	function handleDelete( trigger ) {
		if ( ! window.confirm( dakAdminUsers.confirmDelete ) ) {
			return;
		}

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_admin_delete_user' );
		formData.append( 'nonce', dakAdminUsers.nonce );
		formData.append( 'user_id', trigger.getAttribute( 'data-user-id' ) );

		fetch( dakAdminUsers.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( result.success ) {
					window.location.reload();
				} else {
					window.alert( ( result.data && result.data.message ) || dakAdminUsers.genericError );
				}
			} )
			.catch( function () {
				window.alert( dakAdminUsers.genericError );
			} );
	}
} )();
