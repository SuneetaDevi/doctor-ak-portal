/**
 * Doctor AK Portal — Admin "Doctor Sessions" table.
 *
 * Lets an administrator edit or delete any doctor's clinic/session row,
 * reusing the exact same AJAX endpoints the doctor-facing Clinics tab uses
 * (doctor_ak_admin_clinic_save/_delete — see Clinic_Handler), just checked
 * against the admin nonce instead of the doctor's own.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-admin-session-modal' );

		if ( ! modal || ! window.dakAdminSessions ) {
			return;
		}

		wireDayToggles();
		wireTypeToggle();
		wireModalClose( modal );
		wireAdd( modal );
		wireEdit( modal );
		wireSave( modal );
		wireDelete();
	} );

	/**
	 * Resets every modal field back to a blank state.
	 */
	function resetModalFields() {
		document.getElementById( 'dak-admin-session-clinic-id' ).value = '0';
		document.getElementById( 'dak-admin-session-doctor' ).value = '';
		document.getElementById( 'dak-admin-session-type' ).value = 'physical';
		document.getElementById( 'dak-admin-session-name' ).value = '';
		document.getElementById( 'dak-admin-session-address' ).value = '';
		document.getElementById( 'dak-admin-session-phone' ).value = '';
		document.getElementById( 'dak-admin-session-email' ).value = '';
		document.getElementById( 'dak-admin-session-address-field' ).classList.remove( 'dak-hidden' );

		document.querySelectorAll( '#dak-admin-session-grid .dak-availability-row' ).forEach( function ( row ) {
			row.querySelector( '.dak-availability-toggle' ).checked = false;
			row.querySelector( '.dak-availability-start' ).value = '';
			row.querySelector( '.dak-availability-start' ).disabled = true;
			row.querySelector( '.dak-availability-end' ).value = '';
			row.querySelector( '.dak-availability-end' ).disabled = true;
			row.querySelector( '.dak-clinic-slot-duration' ).value = '';
			row.querySelector( '.dak-clinic-slot-duration' ).disabled = true;
		} );
	}

	/**
	 * "+ Add Session" opens the modal blank so the admin can pick a doctor
	 * and create a brand-new clinic/session for them.
	 *
	 * @param {HTMLElement} modal Edit modal element.
	 */
	function wireAdd( modal ) {
		var addButton = document.getElementById( 'dak-admin-session-add' );

		if ( ! addButton ) {
			return;
		}

		addButton.addEventListener( 'click', function () {
			clearErrors();
			resetModalFields();
			setModalTitle( 'Add Session' );
			openModal( modal );
		} );
	}

	function setModalTitle( text ) {
		var title = document.getElementById( 'dak-admin-session-modal-title' );

		if ( title ) {
			title.textContent = text;
		}
	}

	function wireDayToggles() {
		document.addEventListener( 'change', function ( event ) {
			if ( ! event.target.classList.contains( 'dak-availability-toggle' ) ) {
				return;
			}

			var row = event.target.closest( '.dak-availability-row' );

			if ( ! row ) {
				return;
			}

			var disabled = ! event.target.checked;
			row.querySelector( '.dak-availability-start' ).disabled = disabled;
			row.querySelector( '.dak-availability-end' ).disabled = disabled;
			row.querySelector( '.dak-clinic-slot-duration' ).disabled = disabled;
		} );
	}

	function wireTypeToggle() {
		var typeSelect = document.getElementById( 'dak-admin-session-type' );
		var addressField = document.getElementById( 'dak-admin-session-address-field' );

		if ( ! typeSelect || ! addressField ) {
			return;
		}

		typeSelect.addEventListener( 'change', function () {
			addressField.classList.toggle( 'dak-hidden', 'video' === typeSelect.value );
		} );
	}

	function wireModalClose( modal ) {
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-admin-session-modal-close]' ) ) {
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

	/**
	 * Opens the modal pre-filled from the clicked row's data-* attributes.
	 *
	 * @param {HTMLElement} modal Edit modal element.
	 */
	function wireEdit( modal ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-session-edit]' );

			if ( ! trigger ) {
				return;
			}

			clearErrors();
			setModalTitle( 'Edit Clinic Session' );

			document.getElementById( 'dak-admin-session-clinic-id' ).value = trigger.getAttribute( 'data-clinic-id' ) || '0';
			document.getElementById( 'dak-admin-session-doctor' ).value = trigger.getAttribute( 'data-doctor-id' ) || '';
			document.getElementById( 'dak-admin-session-type' ).value = trigger.getAttribute( 'data-type' ) || 'physical';
			document.getElementById( 'dak-admin-session-name' ).value = trigger.getAttribute( 'data-name' ) || '';
			document.getElementById( 'dak-admin-session-address' ).value = trigger.getAttribute( 'data-address' ) || '';
			document.getElementById( 'dak-admin-session-phone' ).value = trigger.getAttribute( 'data-phone' ) || '';
			document.getElementById( 'dak-admin-session-email' ).value = trigger.getAttribute( 'data-contact-email' ) || '';

			document.getElementById( 'dak-admin-session-address-field' ).classList.toggle(
				'dak-hidden',
				'video' === document.getElementById( 'dak-admin-session-type' ).value
			);

			var sessions = {};

			try {
				sessions = JSON.parse( trigger.getAttribute( 'data-sessions' ) || '{}' );
			} catch ( e ) {
				sessions = {};
			}

			document.querySelectorAll( '#dak-admin-session-grid .dak-availability-row' ).forEach( function ( row ) {
				var day = row.getAttribute( 'data-day' );
				var entry = sessions[ day ] || { enabled: false, start: '', end: '', slot_duration_minutes: '' };
				var toggle = row.querySelector( '.dak-availability-toggle' );
				var start = row.querySelector( '.dak-availability-start' );
				var end = row.querySelector( '.dak-availability-end' );
				var slot = row.querySelector( '.dak-clinic-slot-duration' );

				toggle.checked = !! entry.enabled;
				start.value = entry.start || '';
				end.value = entry.end || '';
				slot.value = entry.slot_duration_minutes || '';
				start.disabled = ! toggle.checked;
				end.disabled = ! toggle.checked;
				slot.disabled = ! toggle.checked;
			} );

			openModal( modal );
		} );
	}

	function wireSave( modal ) {
		var saveButton = document.getElementById( 'dak-admin-session-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearErrors();

			var doctorId = document.getElementById( 'dak-admin-session-doctor' ).value;

			if ( ! doctorId ) {
				var doctorError = document.querySelector( '.dak-field-error[data-field="doctor_id"]' );

				if ( doctorError ) {
					doctorError.textContent = 'Please select a doctor.';
				}

				return;
			}

			saveButton.disabled = true;

			var sessions = {};

			document.querySelectorAll( '#dak-admin-session-grid .dak-availability-row' ).forEach( function ( row ) {
				var day = row.getAttribute( 'data-day' );

				sessions[ day ] = {
					enabled: row.querySelector( '.dak-availability-toggle' ).checked ? '1' : '',
					start: row.querySelector( '.dak-availability-start' ).value,
					end: row.querySelector( '.dak-availability-end' ).value,
					slot_duration_minutes: row.querySelector( '.dak-clinic-slot-duration' ).value,
				};
			} );

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_clinic_save' );
			formData.append( 'nonce', window.dakAdminSessions.nonce );
			formData.append( 'clinic_id', document.getElementById( 'dak-admin-session-clinic-id' ).value );
			formData.append( 'doctor_id', doctorId );
			formData.append( 'type', document.getElementById( 'dak-admin-session-type' ).value );
			formData.append( 'name', document.getElementById( 'dak-admin-session-name' ).value );
			formData.append( 'address', document.getElementById( 'dak-admin-session-address' ).value );
			formData.append( 'phone', document.getElementById( 'dak-admin-session-phone' ).value );
			formData.append( 'contact_email', document.getElementById( 'dak-admin-session-email' ).value );

			Object.keys( sessions ).forEach( function ( day ) {
				var entry = sessions[ day ];
				formData.append( 'sessions[' + day + '][enabled]', entry.enabled );
				formData.append( 'sessions[' + day + '][start]', entry.start );
				formData.append( 'sessions[' + day + '][end]', entry.end );
				formData.append( 'sessions[' + day + '][slot_duration_minutes]', entry.slot_duration_minutes );
			} );

			fetch( window.dakAdminSessions.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
			var trigger = event.target.closest( '[data-admin-session-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this clinic session? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_clinic_delete' );
			formData.append( 'nonce', window.dakAdminSessions.nonce );
			formData.append( 'clinic_id', trigger.getAttribute( 'data-clinic-id' ) );

			fetch( window.dakAdminSessions.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
		document.querySelectorAll( '#dak-admin-session-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-session-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-session-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
