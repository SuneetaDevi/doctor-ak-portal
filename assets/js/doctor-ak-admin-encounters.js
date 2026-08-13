/**
 * Doctor AK Portal — Admin dashboard "Encounters" list: Delete row action,
 * and the "Add Encounter" modal (Clinic -> Doctor -> Patient).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAdminEncounters ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-encounter-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( window.dakAdminEncounters.confirmDelete ) ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_delete_encounter' );
			formData.append( 'nonce', window.dakAdminEncounters.nonce );
			formData.append( 'encounter_id', trigger.getAttribute( 'data-encounter-id' ) );

			fetch( window.dakAdminEncounters.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || window.dakAdminEncounters.genericError );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( window.dakAdminEncounters.genericError );
				} );
		} );

		wireAddEncounterModal();
	} );

	function wireAddEncounterModal() {
		var modal = document.getElementById( 'dak-admin-add-encounter-modal' );
		var addButton = document.getElementById( 'dak-admin-add-encounter' );

		if ( ! modal || ! addButton ) {
			return;
		}

		var clinicSelect = document.getElementById( 'dak-admin-add-encounter-clinic' );
		var doctorSelect = document.getElementById( 'dak-admin-add-encounter-doctor' );
		var patientSelect = document.getElementById( 'dak-admin-add-encounter-patient' );
		var saveButton = document.getElementById( 'dak-admin-add-encounter-save' );
		var patientOptionsHtml = patientSelect.innerHTML;

		addButton.addEventListener( 'click', function () {
			clearErrors();
			resetModal();
			openModal( modal );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-admin-add-encounter-modal-close]' ) ) {
				closeModal( modal );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal.classList.contains( 'is-open' ) ) {
				closeModal( modal );
			}
		} );

		clinicSelect.addEventListener( 'change', function () {
			var clinicLocationId = clinicSelect.value;
			var doctors = ( window.dakAdminEncounters.doctorsByClinicLocation || {} )[ clinicLocationId ] || [];

			doctorSelect.innerHTML = '';
			patientSelect.innerHTML = patientOptionsHtml;
			patientSelect.value = '0';
			patientSelect.disabled = true;
			updateSaveState();

			if ( '0' === clinicLocationId || ! doctors.length ) {
				var emptyOption = document.createElement( 'option' );
				emptyOption.value = '0';
				emptyOption.textContent = '0' === clinicLocationId ? 'Select a clinic first…' : window.dakAdminEncounters.noDoctorsMessage;
				doctorSelect.appendChild( emptyOption );
				doctorSelect.disabled = true;
				return;
			}

			var placeholder = document.createElement( 'option' );
			placeholder.value = '0';
			placeholder.textContent = 'Select a doctor…';
			doctorSelect.appendChild( placeholder );

			doctors.forEach( function ( doctor ) {
				var option = document.createElement( 'option' );
				option.value = doctor.id;
				option.textContent = doctor.name;
				doctorSelect.appendChild( option );
			} );

			doctorSelect.disabled = false;
		} );

		doctorSelect.addEventListener( 'change', function () {
			patientSelect.disabled = '0' === doctorSelect.value;
			updateSaveState();
		} );

		patientSelect.addEventListener( 'change', updateSaveState );

		saveButton.addEventListener( 'click', function () {
			clearErrors();
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_create_encounter' );
			formData.append( 'nonce', window.dakAdminEncounters.nonce );
			formData.append( 'clinic_location_id', clinicSelect.value );
			formData.append( 'doctor_id', doctorSelect.value );
			formData.append( 'patient_id', patientSelect.value );

			fetch( window.dakAdminEncounters.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success && result.data && result.data.encounter_id ) {
						var separator = window.dakAdminEncounters.encounterUrl.indexOf( '?' ) > -1 ? '&' : '?';
						window.location.href = window.dakAdminEncounters.encounterUrl + separator + 'encounter_id=' + result.data.encounter_id;
						return;
					}

					saveButton.disabled = false;
					showGeneralError( errorsToMessage( result ) );
				} )
				.catch( function () {
					saveButton.disabled = false;
					showGeneralError( window.dakAdminEncounters.genericError );
				} );
		} );

		function updateSaveState() {
			saveButton.disabled = '0' === clinicSelect.value || '0' === doctorSelect.value || '0' === patientSelect.value || patientSelect.disabled;
		}

		function resetModal() {
			clinicSelect.value = '0';
			doctorSelect.innerHTML = '<option value="0">Select a clinic first…</option>';
			doctorSelect.disabled = true;
			patientSelect.innerHTML = patientOptionsHtml;
			patientSelect.value = '0';
			patientSelect.disabled = true;
			saveButton.disabled = true;
		}
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

	function errorsToMessage( result ) {
		if ( result.data && result.data.errors ) {
			return Object.keys( result.data.errors ).map( function ( field ) { return result.data.errors[ field ]; } ).join( ' ' );
		}

		return ( result.data && result.data.message ) || window.dakAdminEncounters.genericError;
	}

	function clearErrors() {
		document.querySelectorAll( '#dak-admin-add-encounter-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-add-encounter-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-add-encounter-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
