/**
 * Doctor AK Portal — "Clinics" dashboard tab.
 *
 * Every clinic card (existing or freshly cloned from the <template>) is
 * wired through document-level event delegation, so newly added cards work
 * without any extra per-card setup. Fields are read directly off the DOM
 * (not via <form>/name attributes) since several cards with identical field
 * shapes can be on the page at once.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var list = document.getElementById( 'dak-clinics-list' );

		if ( ! list ) {
			return;
		}

		wireDayToggles();
		wireTypeToggle();
		wireAddClinic();
		wireEditToggle();
		wireCancel();
		wireSave();
		wireDelete();
	} );

	/**
	 * Enables/disables each session row's time + slot-duration inputs based
	 * on its day checkbox.
	 */
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

	/**
	 * Hides the Address field when "Video Consultation" is selected.
	 */
	function wireTypeToggle() {
		document.addEventListener( 'change', function ( event ) {
			if ( ! event.target.classList.contains( 'dak-clinic-type-select' ) ) {
				return;
			}

			var card = event.target.closest( '.dak-clinic-card' );
			var addressField = card ? card.querySelector( '.dak-clinic-address-field' ) : null;

			if ( addressField ) {
				addressField.classList.toggle( 'dak-hidden', 'video' === event.target.value );
			}
		} );
	}

	/**
	 * "+ Add Clinic" clones the blank <template> card and shows its form.
	 */
	function wireAddClinic() {
		var addButton = document.getElementById( 'dak-clinic-add' );
		var list = document.getElementById( 'dak-clinics-list' );
		var template = document.getElementById( 'dak-clinic-card-template' );

		if ( ! addButton || ! list || ! template ) {
			return;
		}

		addButton.addEventListener( 'click', function () {
			var emptyState = document.getElementById( 'dak-clinics-empty-state' );

			if ( emptyState ) {
				emptyState.remove();
			}

			var clone = template.content.cloneNode( true );
			list.appendChild( clone );
		} );
	}

	/**
	 * Toggles a card between its summary view and its edit form.
	 */
	function wireEditToggle() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-clinic-edit-toggle]' );

			if ( ! trigger ) {
				return;
			}

			var card = trigger.closest( '.dak-clinic-card' );

			if ( ! card ) {
				return;
			}

			card.querySelector( '.dak-clinic-card-summary' ).classList.add( 'dak-hidden' );
			card.querySelector( '.dak-clinic-card-form' ).classList.remove( 'dak-hidden' );
		} );
	}

	/**
	 * Cancels editing: for an existing clinic, reverts to the summary view;
	 * for a brand-new (unsaved) card, removes it entirely.
	 */
	function wireCancel() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-clinic-cancel]' );

			if ( ! trigger ) {
				return;
			}

			var card = trigger.closest( '.dak-clinic-card' );

			if ( ! card ) {
				return;
			}

			var clinicId = card.getAttribute( 'data-clinic-id' );

			if ( ! clinicId || '0' === clinicId ) {
				card.remove();
				maybeShowEmptyState();
				return;
			}

			card.querySelector( '.dak-clinic-card-form' ).classList.add( 'dak-hidden' );
			card.querySelector( '.dak-clinic-card-summary' ).classList.remove( 'dak-hidden' );
		} );
	}

	/**
	 * Reads a card's form fields into the AJAX payload shape the backend
	 * expects (Clinics::sanitize_clinic_fields_from_request()/
	 * sanitize_sessions_from_request()).
	 *
	 * @param {HTMLElement} card Clinic card element.
	 * @return {Object}
	 */
	function collectClinicPayload( card ) {
		var sessions = {};

		card.querySelectorAll( '.dak-availability-row[data-day]' ).forEach( function ( row ) {
			var day = row.getAttribute( 'data-day' );
			var enabled = row.querySelector( '.dak-availability-toggle' ).checked;

			sessions[ day ] = {
				enabled: enabled ? '1' : '',
				start: row.querySelector( '.dak-availability-start' ).value,
				end: row.querySelector( '.dak-availability-end' ).value,
				slot_duration_minutes: row.querySelector( '.dak-clinic-slot-duration' ).value,
			};
		} );

		return {
			clinic_id: card.getAttribute( 'data-clinic-id' ) || '0',
			type: card.querySelector( '.dak-clinic-type-select' ).value,
			name: card.querySelector( '.dak-clinic-name-input' ).value,
			address: card.querySelector( '.dak-clinic-address-input' ).value,
			phone: card.querySelector( '.dak-clinic-phone-input' ).value,
			contact_email: card.querySelector( '.dak-clinic-email-input' ).value,
			sessions: sessions,
		};
	}

	/**
	 * Saves a clinic (create or update) over AJAX.
	 */
	function wireSave() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-clinic-save]' );

			if ( ! trigger || ! window.dakClinics ) {
				return;
			}

			var card = trigger.closest( '.dak-clinic-card' );

			if ( ! card ) {
				return;
			}

			clearCardErrors( card );
			trigger.disabled = true;

			var payload = collectClinicPayload( card );
			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_clinic_save' );
			formData.append( 'nonce', window.dakClinics.nonce );
			formData.append( 'clinic_id', payload.clinic_id );
			formData.append( 'type', payload.type );
			formData.append( 'name', payload.name );
			formData.append( 'address', payload.address );
			formData.append( 'phone', payload.phone );
			formData.append( 'contact_email', payload.contact_email );

			Object.keys( payload.sessions ).forEach( function ( day ) {
				var entry = payload.sessions[ day ];
				formData.append( 'sessions[' + day + '][enabled]', entry.enabled );
				formData.append( 'sessions[' + day + '][start]', entry.start );
				formData.append( 'sessions[' + day + '][end]', entry.end );
				formData.append( 'sessions[' + day + '][slot_duration_minutes]', entry.slot_duration_minutes );
			} );

			fetch( window.dakClinics.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					trigger.disabled = false;

					if ( result.success ) {
						window.location.reload();
						return;
					}

					if ( result.data && result.data.errors ) {
						showCardErrors( card, result.data.errors );
					} else {
						showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
					}
				} )
				.catch( function () {
					trigger.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	/**
	 * Deletes a clinic over AJAX after confirmation.
	 */
	function wireDelete() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-clinic-delete]' );

			if ( ! trigger || ! window.dakClinics ) {
				return;
			}

			var card = trigger.closest( '.dak-clinic-card' );
			var clinicId = card ? card.getAttribute( 'data-clinic-id' ) : '0';

			if ( ! clinicId || '0' === clinicId ) {
				return;
			}

			if ( ! window.confirm( 'Delete this clinic? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_clinic_delete' );
			formData.append( 'nonce', window.dakClinics.nonce );
			formData.append( 'clinic_id', clinicId );

			fetch( window.dakClinics.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function clearCardErrors( card ) {
		card.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var formError = card.querySelector( '[data-clinic-form-error]' );

		if ( formError ) {
			formError.textContent = '';
			formError.classList.add( 'dak-hidden' );
		}
	}

	function showCardErrors( card, errors ) {
		Object.keys( errors ).forEach( function ( field ) {
			var el = card.querySelector( '.dak-field-error[data-field="' + field + '"]' );

			if ( el ) {
				el.textContent = errors[ field ];
				return;
			}

			var formError = card.querySelector( '[data-clinic-form-error]' );

			if ( formError ) {
				formError.textContent = errors[ field ];
				formError.classList.remove( 'dak-hidden' );
			}
		} );
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-clinics-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}

	function maybeShowEmptyState() {
		var list = document.getElementById( 'dak-clinics-list' );

		if ( list && ! list.querySelector( '.dak-clinic-card' ) && ! document.getElementById( 'dak-clinics-empty-state' ) ) {
			var empty = document.createElement( 'p' );
			empty.className = 'dak-empty-state';
			empty.id = 'dak-clinics-empty-state';
			empty.textContent = "You haven't added any clinics yet. Add one below.";
			list.appendChild( empty );
		}
	}
} )();
