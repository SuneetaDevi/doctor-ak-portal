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

		wireCityAreaSelectsIn( list );
	} );

	/**
	 * Wires every Country/City/Area select trio inside a container (the
	 * whole list on page load, or a single freshly cloned card) via the
	 * shared window.dakCityArea helper, plus the Clinic picker each trio
	 * filters (mirrors the admin "Doctor Sessions" form's picker).
	 *
	 * @param {HTMLElement} container Element to search within.
	 */
	function wireCityAreaSelectsIn( container ) {
		if ( ! window.dakCityArea || ! window.dakClinics ) {
			return;
		}

		container.querySelectorAll( '.dak-clinic-card' ).forEach( function ( card ) {
			var countrySelect = card.querySelector( '.dak-clinic-country-select' );
			var citySelect = card.querySelector( '.dak-clinic-city-select' );
			var areaSelect = card.querySelector( '.dak-clinic-area-select' );
			var clinicLocationSelect = card.querySelector( '.dak-clinic-location-select' );

			if ( ! countrySelect || ! citySelect || ! areaSelect || countrySelect.getAttribute( 'data-wired' ) ) {
				return;
			}

			countrySelect.setAttribute( 'data-wired', '1' );

			window.dakCityArea.wire(
				countrySelect,
				citySelect,
				areaSelect,
				window.dakClinics.locations,
				countrySelect.getAttribute( 'data-clinic-country' ) || '',
				citySelect.getAttribute( 'data-clinic-city' ) || '',
				areaSelect.getAttribute( 'data-clinic-area' ) || ''
			);

			if ( clinicLocationSelect ) {
				var preselectClinicLocationId = clinicLocationSelect.getAttribute( 'data-clinic-location' ) || '';

				renderClinicLocationOptions( clinicLocationSelect, countrySelect, citySelect, areaSelect, preselectClinicLocationId );

				[ countrySelect, citySelect, areaSelect ].forEach( function ( select ) {
					select.addEventListener( 'change', function () {
						renderClinicLocationOptions( clinicLocationSelect, countrySelect, citySelect, areaSelect, '' );
					} );
				} );
			}
		} );
	}

	/**
	 * Fills a card's Clinic <select> from window.dakClinics.clinicLocations,
	 * filtered to whichever Country/City/Area are currently selected in that
	 * same card — narrowing as the doctor picks a more specific location.
	 *
	 * @param {HTMLSelectElement} clinicLocationSelect The Clinic <select>.
	 * @param {HTMLSelectElement} countrySelect        The Country <select>.
	 * @param {HTMLSelectElement} citySelect           The City <select>.
	 * @param {HTMLSelectElement} areaSelect            The Area <select>.
	 * @param {string}            preselectId           Clinic-location ID to pre-select, if any.
	 */
	function renderClinicLocationOptions( clinicLocationSelect, countrySelect, citySelect, areaSelect, preselectId ) {
		var allClinicLocations = ( window.dakClinics && window.dakClinics.clinicLocations ) || [];
		var country = countrySelect ? countrySelect.value : '';
		var city = citySelect ? citySelect.value : '';
		var area = areaSelect ? areaSelect.value : '';

		var filtered = allClinicLocations.filter( function ( clinicLocation ) {
			return ( ! country || clinicLocation.country === country )
				&& ( ! city || clinicLocation.city === city )
				&& ( ! area || clinicLocation.area === area );
		} );

		clinicLocationSelect.innerHTML = '';

		var placeholderOption = document.createElement( 'option' );
		placeholderOption.value = '';
		placeholderOption.textContent = filtered.length ? 'Select a clinic…' : 'No clinics for this area yet';
		clinicLocationSelect.appendChild( placeholderOption );

		filtered.forEach( function ( clinicLocation ) {
			var option = document.createElement( 'option' );
			option.value = String( clinicLocation.id );
			option.textContent = clinicLocation.name + ' — ' + clinicLocation.area_label + ', ' + clinicLocation.city_label;

			if ( String( clinicLocation.id ) === String( preselectId ) ) {
				option.selected = true;
			}

			clinicLocationSelect.appendChild( option );
		} );
	}

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

			if ( ! card ) {
				return;
			}

			var isVideo = 'video' === event.target.value;

			card.querySelectorAll( '.dak-clinic-address-field' ).forEach( function ( field ) {
				field.classList.toggle( 'dak-hidden', isVideo );
			} );

			card.querySelectorAll( '.dak-clinic-video-name-field' ).forEach( function ( field ) {
				field.classList.toggle( 'dak-hidden', ! isVideo );
			} );
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
			wireCityAreaSelectsIn( list );
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

		card.querySelectorAll( '.dak-clinic-sessions-day[data-day]' ).forEach( function ( dayEl ) {
			var day = dayEl.getAttribute( 'data-day' );
			sessions[ day ] = {};

			dayEl.querySelectorAll( '.dak-availability-row[data-period]' ).forEach( function ( row ) {
				var period = row.getAttribute( 'data-period' );
				var enabled = row.querySelector( '.dak-availability-toggle' ).checked;

				sessions[ day ][ period ] = {
					enabled: enabled ? '1' : '',
					start: row.querySelector( '.dak-availability-start' ).value,
					end: row.querySelector( '.dak-availability-end' ).value,
					slot_duration_minutes: row.querySelector( '.dak-clinic-slot-duration' ).value,
				};
			} );
		} );

		var clinicLocationSelect = card.querySelector( '.dak-clinic-location-select' );

		return {
			clinic_id: card.getAttribute( 'data-clinic-id' ) || '0',
			type: card.querySelector( '.dak-clinic-type-select' ).value,
			name: card.querySelector( '.dak-clinic-name-input' ).value,
			clinic_location_id: clinicLocationSelect ? clinicLocationSelect.value : '',
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
			formData.append( 'clinic_location_id', payload.clinic_location_id );
			formData.append( 'phone', payload.phone );
			formData.append( 'contact_email', payload.contact_email );

			Object.keys( payload.sessions ).forEach( function ( day ) {
				Object.keys( payload.sessions[ day ] ).forEach( function ( period ) {
					var entry = payload.sessions[ day ][ period ];
					formData.append( 'sessions[' + day + '][' + period + '][enabled]', entry.enabled );
					formData.append( 'sessions[' + day + '][' + period + '][start]', entry.start );
					formData.append( 'sessions[' + day + '][' + period + '][end]', entry.end );
					formData.append( 'sessions[' + day + '][' + period + '][slot_duration_minutes]', entry.slot_duration_minutes );
				} );
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
