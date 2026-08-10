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
			shared.initMultiSelect( document.getElementById( 'dak-admin-user-clinic-locations' ), { placeholder: 'Select clinics…' } );
		}

		var countrySelect = document.getElementById( 'dak-admin-user-country' );
		var citySelect = document.getElementById( 'dak-admin-user-city' );
		var areaSelect = document.getElementById( 'dak-admin-user-area' );

		if ( window.dakCityArea && countrySelect && citySelect && areaSelect ) {
			window.dakCityArea.wire(
				countrySelect,
				citySelect,
				areaSelect,
				window.dakAdminUsers.locations,
				countrySelect.getAttribute( 'data-current' ) || '',
				citySelect.getAttribute( 'data-current' ) || '',
				areaSelect.getAttribute( 'data-current' ) || ''
			);
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
		wireRevenueSplit();

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submitForm();
		} );

		/**
		 * Disables the Doctor's Share field for a Salary-model doctor (their
		 * share is always 0 — see Revenue_Split), and keeps a live "hospital
		 * keeps X%" hint in sync with whatever's typed for a Commission doctor.
		 */
		function wireRevenueSplit() {
			var modelSelect = document.getElementById( 'dak-admin-user-payment-model' );
			var shareInput = document.getElementById( 'dak-admin-user-doctor-share-percent' );
			var hospitalHint = document.getElementById( 'dak-admin-user-hospital-share-hint' );

			if ( ! modelSelect || ! shareInput ) {
				return;
			}

			function update() {
				var isSalary = 'salary' === modelSelect.value;
				shareInput.disabled = isSalary;

				if ( ! hospitalHint ) {
					return;
				}

				var doctorPercent = isSalary ? 0 : ( parseFloat( shareInput.value ) || 0 );
				hospitalHint.textContent = 'Hospital keeps ' + ( 100 - doctorPercent ) + '%.';
			}

			modelSelect.addEventListener( 'change', update );
			shareInput.addEventListener( 'input', update );
			update();
		}

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
			var dischargeTrigger = event.target.closest( '[data-admin-toggle-discharge]' );
			var deleteTrigger = event.target.closest( '[data-admin-delete-user]' );

			if ( toggleTrigger ) {
				handleToggleStatus( toggleTrigger );
			} else if ( dischargeTrigger ) {
				handleToggleDischarge( dischargeTrigger );
			} else if ( deleteTrigger ) {
				handleDelete( deleteTrigger );
			}
		} );
	}

	/**
	 * Toggles a patient between discharged/readmitted — a simple one-step
	 * confirm-then-toggle, unlike handleToggleStatus()'s doctor-appointment-
	 * cancellation dance (discharge has no such side effects to weigh).
	 *
	 * @param {HTMLElement} trigger The row's Discharge/Readmit button.
	 */
	function handleToggleDischarge( trigger ) {
		var isDischarged = '1' === trigger.getAttribute( 'data-is-discharged' );
		var confirmMessage = isDischarged
			? 'Readmit this patient? They will no longer be marked as discharged.'
			: 'Discharge this patient? This just marks their course of treatment as finished — it does not affect their account access.';

		if ( ! window.confirm( confirmMessage ) ) {
			return;
		}

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_admin_toggle_discharge' );
		formData.append( 'nonce', dakAdminUsers.nonce );
		formData.append( 'user_id', trigger.getAttribute( 'data-user-id' ) );

		fetch( dakAdminUsers.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( result.success ) {
					window.location.reload();
					return;
				}

				window.alert( ( result.data && result.data.message ) || dakAdminUsers.genericError );
			} )
			.catch( function () {
				window.alert( dakAdminUsers.genericError );
			} );
	}

	function handleToggleStatus( trigger ) {
		var isDisabled = '1' === trigger.getAttribute( 'data-is-disabled' );
		var confirmMessage = isDisabled ? dakAdminUsers.confirmEnable : dakAdminUsers.confirmDisable;

		if ( ! window.confirm( confirmMessage ) ) {
			return;
		}

		submitToggleStatus( trigger, {} );
	}

	/**
	 * POSTs the toggle-status request, with an optional `cancel_appointments`
	 * ('1'/'0') field for the second-step confirmation below. If the doctor
	 * being deactivated has upcoming appointments and that field wasn't sent
	 * yet, the server holds off on actually toggling anything and instead
	 * replies with `needs_confirmation` + a count — we ask the admin whether
	 * to cancel those appointments (emailing patient + doctor and refunding
	 * paid ones) and resubmit with their answer.
	 *
	 * @param {HTMLElement} trigger     The row's Deactivate/Activate button.
	 * @param {Object}      extraFields Additional form fields to send.
	 * @return {void}
	 */
	function submitToggleStatus( trigger, extraFields ) {
		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_admin_toggle_status' );
		formData.append( 'nonce', dakAdminUsers.nonce );
		formData.append( 'user_id', trigger.getAttribute( 'data-user-id' ) );

		Object.keys( extraFields ).forEach( function ( key ) {
			formData.append( key, extraFields[ key ] );
		} );

		fetch( dakAdminUsers.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( ! result.success ) {
					window.alert( ( result.data && result.data.message ) || dakAdminUsers.genericError );
					return;
				}

				if ( result.data && result.data.needs_confirmation ) {
					var count = result.data.appointment_count;
					var cancelPrompt = 1 === count
						? 'This doctor has 1 upcoming appointment. Do you want to cancel it? The patient and doctor will be emailed, and any paid appointment will be refunded.'
						: 'This doctor has ' + count + ' upcoming appointments. Do you want to cancel them? The patients and doctor will be emailed, and any paid appointments will be refunded.';

					submitToggleStatus( trigger, { cancel_appointments: window.confirm( cancelPrompt ) ? '1' : '0' } );
					return;
				}

				if ( '1' === extraFields.cancel_appointments && result.data && result.data.message ) {
					window.alert( result.data.message );
				}

				window.location.reload();
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
