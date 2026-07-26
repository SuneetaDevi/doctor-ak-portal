/**
 * Doctor AK Portal — Administrator dashboard (Users: Doctors/Patients).
 *
 * Handles the full-screen Add/Edit Doctor/Patient form (a normal page state
 * reached via `?section=doctors&view=form[&user_id=X]`, rendered entirely
 * server-side by admin-user-form-screen.php — no modal, no client-side
 * populate/reset dance) plus the accounts table's Deactivate/Activate and
 * Delete row actions. On success, each action simply reloads/redirects
 * rather than patching anything client-side — the table and form are both
 * otherwise fully server-rendered. No-ops entirely on sections that don't
 * render this form or a table (Dashboard overview, and the placeholder
 * sections).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initUserForm();
		initRowActions();
		initSpecialtyTagToggles();
	} );

	/**
	 * Wires the table's "+N" specialization chip — expands/collapses the
	 * extra tags beyond the first two shown by default (see
	 * admin-user-table.php).
	 */
	function initSpecialtyTagToggles() {
		document.querySelectorAll( '[data-specialty-toggle]' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var container = toggle.closest( '[data-specialty-tags]' );

				if ( ! container ) {
					return;
				}

				var expanded = toggle.classList.toggle( 'is-expanded' );

				container.querySelectorAll( '.dak-specialty-tag-extra' ).forEach( function ( tag ) {
					tag.classList.toggle( 'dak-hidden', ! expanded );
				} );

				toggle.textContent = expanded ? toggle.getAttribute( 'data-less-label' ) : toggle.getAttribute( 'data-more-label' );
			} );
		} );
	}

	/**
	 * Wires up the Add/Edit form: photo upload and AJAX submit.
	 */
	function initUserForm() {
		var form = document.getElementById( 'dak-admin-user-form' );

		if ( ! form ) {
			return;
		}

		var shared = window.DoctorAKPortal || {};

		if ( typeof shared.initMultiSelect === 'function' ) {
			shared.initMultiSelect( document.getElementById( 'dak-admin-user-specializations' ), { allowCustom: true } );
		}

		if ( window.dakCityArea ) {
			var citySelect = document.getElementById( 'dak-admin-user-city' );
			var areaSelect = document.getElementById( 'dak-admin-user-area' );

			if ( citySelect && areaSelect ) {
				window.dakCityArea.wire(
					citySelect,
					areaSelect,
					window.dakAdminUsers.locations,
					citySelect.getAttribute( 'data-current' ) || '',
					areaSelect.getAttribute( 'data-current' ) || ''
				);
			}
		}

		var generalError = document.getElementById( 'dak-admin-user-modal-general-error' );
		var idInput = document.getElementById( 'dak-admin-user-id' );
		var submitButton = document.getElementById( 'dak-admin-user-submit' );
		var pictureInput = document.getElementById( 'dak-admin-user-picture-input' );
		var pictureIdInput = document.getElementById( 'dak-admin-user-picture-id' );
		var picturePreview = document.getElementById( 'dak-admin-user-picture-preview' );
		var pictureStatus = document.getElementById( 'dak-admin-user-picture-status' );
		var listUrl = form.getAttribute( 'data-list-url' ) || '';

		wirePictureUpload();

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submitForm();
		} );

		function wirePictureUpload() {
			if ( ! pictureInput ) {
				return;
			}

			pictureInput.addEventListener( 'change', function () {
				var file = pictureInput.files[ 0 ];

				if ( ! file ) {
					return;
				}

				if ( pictureStatus ) {
					pictureStatus.textContent = 'Uploading…';
				}

				var formData = new FormData();
				formData.append( 'action', 'doctor_ak_admin_upload_profile_picture' );
				formData.append( 'nonce', dakAdminUsers.nonce );
				formData.append( 'user_id', idInput.value );
				formData.append( 'profile_picture', file );

				fetch( dakAdminUsers.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
					.then( function ( response ) { return response.json(); } )
					.then( function ( result ) {
						if ( result.success ) {
							pictureIdInput.value = result.data.attachment_id;

							if ( picturePreview ) {
								picturePreview.innerHTML = '<img src="' + result.data.url + '" alt="">';
							}

							if ( pictureStatus ) {
								pictureStatus.textContent = '';
							}

							return;
						}

						if ( pictureStatus ) {
							pictureStatus.textContent = ( result.data && result.data.message ) || dakAdminUsers.genericError;
						}
					} )
					.catch( function () {
						if ( pictureStatus ) {
							pictureStatus.textContent = dakAdminUsers.genericError;
						}
					} );
			} );
		}

		function clearFieldErrors() {
			form.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
				el.textContent = '';
				el.classList.remove( 'is-visible' );
			} );
		}

		function showFieldError( field, message ) {
			var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );

			if ( el ) {
				el.textContent = message;
				el.classList.add( 'is-visible' );
			}
		}

		function hideGeneralError() {
			if ( generalError ) {
				generalError.textContent = '';
				generalError.classList.add( 'dak-hidden' );
			}
		}

		function showGeneralError( message ) {
			if ( generalError ) {
				generalError.textContent = message;
				generalError.classList.remove( 'dak-hidden' );
			}
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
						window.location.href = listUrl || window.location.href;
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
