/**
 * Doctor AK Portal — Admin full-screen Add/Edit Service form.
 *
 * Reached via `?section=services&view=form[&service_id=X]` (mirrors the
 * Add/Edit Doctor and Add/Edit Session forms' page-based pattern instead of
 * a modal). Submits to the same doctor_ak_admin_service_save AJAX endpoint
 * the old modal used; on success it redirects back to the Services table.
 */
( function () {
	'use strict';

	var selectedImageFile = null;
	var clinicChargesPreset = {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-admin-service-form' );

		if ( ! form || ! window.dakAdminServices ) {
			return;
		}

		var presetField = document.getElementById( 'dak-admin-service-clinic-charges-field' );

		if ( presetField ) {
			try {
				clinicChargesPreset = JSON.parse( presetField.getAttribute( 'data-preset' ) || '{}' );
			} catch ( error ) {
				clinicChargesPreset = {};
			}
		}

		wireImagePicker();
		wireClinicChargeRows();
		renderClinicChargeRows();
		wireSubmit( form );
	} );

	function refreshSearchable( id ) {
		if ( window.DAKSearchableSelect ) {
			window.DAKSearchableSelect.refresh( document.getElementById( id ) );
		}
	}

	function getMultiSelectValues( id ) {
		var select = document.getElementById( id );

		if ( ! select ) {
			return [];
		}

		return Array.prototype.map.call( select.selectedOptions, function ( option ) { return option.value; } );
	}

	function setImagePreview( url ) {
		var preview = document.getElementById( 'dak-admin-service-image-preview' );

		if ( url ) {
			preview.src = url;
			preview.classList.remove( 'dak-hidden' );
		} else {
			preview.src = '';
			preview.classList.add( 'dak-hidden' );
		}
	}

	function wireImagePicker() {
		var input = document.getElementById( 'dak-admin-service-image' );

		if ( ! input ) {
			return;
		}

		input.addEventListener( 'change', function () {
			selectedImageFile = input.files && input.files[ 0 ] ? input.files[ 0 ] : null;

			if ( selectedImageFile ) {
				setImagePreview( URL.createObjectURL( selectedImageFile ) );
			}
		} );
	}

	/**
	 * Builds one price row per currently-selected clinic underneath the
	 * Clinics field — preserves whatever's already typed for a clinic that
	 * stays selected, defaults a newly-selected clinic to
	 * clinicChargesPreset's value (seeded from the server-rendered existing
	 * service when editing) or else the Base Charge field's current value,
	 * and drops rows for deselected clinics.
	 */
	function renderClinicChargeRows() {
		var container = document.getElementById( 'dak-admin-service-clinic-charges' );

		if ( ! container ) {
			return;
		}

		var selectedIds = getMultiSelectValues( 'dak-admin-service-clinics' );
		var existingValues = {};

		container.querySelectorAll( '[data-clinic-charge-row]' ).forEach( function ( row ) {
			var input = row.querySelector( 'input' );

			if ( input ) {
				existingValues[ row.getAttribute( 'data-clinic-charge-row' ) ] = input.value;
			}
		} );

		container.innerHTML = '';

		var baseCharge = document.getElementById( 'dak-admin-service-charge' ).value || '0';
		var clinicsSelect = document.getElementById( 'dak-admin-service-clinics' );

		selectedIds.forEach( function ( clinicId ) {
			var value = Object.prototype.hasOwnProperty.call( existingValues, clinicId )
				? existingValues[ clinicId ]
				: ( Object.prototype.hasOwnProperty.call( clinicChargesPreset, clinicId ) ? clinicChargesPreset[ clinicId ] : baseCharge );

			var option = clinicsSelect ? clinicsSelect.querySelector( 'option[value="' + clinicId + '"]' ) : null;
			var clinicName = option ? ( option.getAttribute( 'data-clinic-name' ) || option.textContent ) : '';

			var row = document.createElement( 'div' );
			row.className = 'dak-clinic-charge-row';
			row.setAttribute( 'data-clinic-charge-row', clinicId );

			var label = document.createElement( 'span' );
			label.className = 'dak-clinic-charge-row-label';
			label.textContent = clinicName;

			var input = document.createElement( 'input' );
			input.type = 'number';
			input.min = '0';
			input.step = '0.01';
			input.className = 'dak-clinic-charge-row-input';
			input.value = value;

			row.appendChild( label );
			row.appendChild( input );
			container.appendChild( row );
		} );
	}

	function getClinicCharges() {
		var charges = {};

		document.querySelectorAll( '#dak-admin-service-clinic-charges [data-clinic-charge-row]' ).forEach( function ( row ) {
			var input = row.querySelector( 'input' );
			charges[ row.getAttribute( 'data-clinic-charge-row' ) ] = input ? input.value : '0';
		} );

		return charges;
	}

	function wireClinicChargeRows() {
		var clinicsSelect = document.getElementById( 'dak-admin-service-clinics' );

		if ( clinicsSelect ) {
			clinicsSelect.addEventListener( 'change', renderClinicChargeRows );
		}
	}

	function wireSubmit( form ) {
		var submitButton = document.getElementById( 'dak-admin-service-submit' );
		var listUrl = form.getAttribute( 'data-list-url' ) || '';

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();

			var doctorIds = getMultiSelectValues( 'dak-admin-service-doctor' );

			if ( ! doctorIds.length ) {
				showFieldError( 'doctor_id', 'Please select at least one doctor.' );
				return;
			}

			submitButton.disabled = true;

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_admin_service_save' );
			formData.append( 'nonce', window.dakAdminServices.nonce );

			doctorIds.forEach( function ( doctorId ) {
				formData.append( 'doctor_ids[]', doctorId );
			} );

			var clinicCharges = getClinicCharges();

			Object.keys( clinicCharges ).forEach( function ( clinicId ) {
				formData.append( 'clinic_charges[' + clinicId + ']', clinicCharges[ clinicId ] );
			} );

			if ( selectedImageFile ) {
				formData.append( 'image', selectedImageFile );
			}

			fetch( window.dakAdminServices.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitButton.disabled = false;

					if ( result.success ) {
						window.location.href = listUrl || window.location.href;
						return;
					}

					showErrors( result );
					scrollToFirstError();
				} )
				.catch( function () {
					submitButton.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
					scrollToFirstError();
				} );
		} );
	}

	function showErrors( result ) {
		if ( result.data && result.data.errors ) {
			var messages = [];

			Object.keys( result.data.errors ).forEach( function ( field ) {
				messages.push( result.data.errors[ field ] );
				showFieldError( field, result.data.errors[ field ] );
			} );

			// The field-specific message above may be far off-screen, so
			// always surface it at the top of the form too.
			showGeneralError( messages.join( ' ' ) );
			return;
		}

		showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
	}

	function clearErrors() {
		document.querySelectorAll( '#dak-admin-service-form .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-service-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showFieldError( field, message ) {
		var el = document.querySelector( '#dak-admin-service-form .dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = message;
			return;
		}

		showGeneralError( message );
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-service-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}

	/**
	 * Scrolls the general error banner into view so a validation failure is
	 * never invisible just because it happened below the fold.
	 */
	function scrollToFirstError() {
		var el = document.getElementById( 'dak-admin-service-general-error' );

		if ( el ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}
} )();
