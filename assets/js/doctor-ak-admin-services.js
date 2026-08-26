/**
 * Doctor AK Portal — Admin "Services" table.
 *
 * Lets an administrator add, edit, or delete a service, via Service_Handler's
 * admin AJAX endpoints (doctor_ak_admin_service_save/_delete). The Doctor
 * field is a multi-select: adding a new service with several picked creates
 * one row per doctor server-side (see Service_Handler), each with its own
 * per-clinic pricing editable afterward via that row's own Edit.
 */
( function () {
	'use strict';

	var selectedImageFile = null;
	var clinicChargesPreset = {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-admin-service-modal' );

		if ( ! modal || ! window.dakAdminServices ) {
			return;
		}

		wireModalClose( modal );
		wireAdd( modal );
		wireEdit( modal );
		wireImagePicker();
		wireClinicChargeRows();
		wireSave( modal );
		wireDelete();
	} );

	function refreshSearchable( id ) {
		if ( window.DAKSearchableSelect ) {
			window.DAKSearchableSelect.refresh( document.getElementById( id ) );
		}
	}

	function setMultiSelectValues( id, values ) {
		var select = document.getElementById( id );

		if ( ! select ) {
			return;
		}

		var stringValues = values.map( function ( value ) { return String( value ); } );

		Array.prototype.forEach.call( select.options, function ( option ) {
			option.selected = -1 !== stringValues.indexOf( option.value );
		} );

		refreshSearchable( id );
	}

	function getMultiSelectValues( id ) {
		var select = document.getElementById( id );

		if ( ! select ) {
			return [];
		}

		return Array.prototype.map.call( select.selectedOptions, function ( option ) { return option.value; } );
	}

	function resetImagePreview() {
		selectedImageFile = null;
		document.getElementById( 'dak-admin-service-image-id' ).value = '0';
		document.getElementById( 'dak-admin-service-image' ).value = '';

		var preview = document.getElementById( 'dak-admin-service-image-preview' );
		preview.src = '';
		preview.classList.add( 'dak-hidden' );
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
	 * clinicChargesPreset's value (set when opening Edit) or else the Base
	 * Charge field's current value, and drops rows for deselected clinics.
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

	function resetModalFields() {
		document.getElementById( 'dak-admin-service-id' ).value = '0';
		setMultiSelectValues( 'dak-admin-service-doctor', [] );
		document.getElementById( 'dak-admin-service-name' ).value = '';
		document.getElementById( 'dak-admin-service-category' ).value = '';
		document.getElementById( 'dak-admin-service-charge' ).value = '0';
		document.getElementById( 'dak-admin-service-duration' ).value = '0';
		document.getElementById( 'dak-admin-service-active' ).checked = true;
		document.getElementById( 'dak-admin-service-description' ).value = '';
		clinicChargesPreset = {};
		setMultiSelectValues( 'dak-admin-service-clinics', [] );
		renderClinicChargeRows();
		resetImagePreview();
	}

	function wireAdd( modal ) {
		var addButton = document.getElementById( 'dak-admin-service-add' );

		if ( ! addButton ) {
			return;
		}

		addButton.addEventListener( 'click', function () {
			clearErrors();
			resetModalFields();
			setModalTitle( 'Add Service' );
			openModal( modal );
		} );
	}

	function setModalTitle( text ) {
		var title = document.getElementById( 'dak-admin-service-modal-title' );

		if ( title ) {
			title.textContent = text;
		}
	}

	function wireModalClose( modal ) {
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-admin-service-modal-close]' ) ) {
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
			var trigger = event.target.closest( '[data-admin-service-edit]' );

			if ( ! trigger ) {
				return;
			}

			clearErrors();
			setModalTitle( 'Edit Service' );
			resetImagePreview();

			document.getElementById( 'dak-admin-service-id' ).value = trigger.getAttribute( 'data-service-id' ) || '0';
			setMultiSelectValues( 'dak-admin-service-doctor', [ trigger.getAttribute( 'data-doctor-id' ) || '' ] );
			document.getElementById( 'dak-admin-service-name' ).value = trigger.getAttribute( 'data-name' ) || '';
			document.getElementById( 'dak-admin-service-category' ).value = trigger.getAttribute( 'data-category' ) || '';
			document.getElementById( 'dak-admin-service-charge' ).value = trigger.getAttribute( 'data-charge' ) || '0';
			document.getElementById( 'dak-admin-service-duration' ).value = trigger.getAttribute( 'data-duration-minutes' ) || '0';
			document.getElementById( 'dak-admin-service-active' ).checked = '1' === trigger.getAttribute( 'data-active' );
			document.getElementById( 'dak-admin-service-description' ).value = trigger.getAttribute( 'data-description' ) || '';
			document.getElementById( 'dak-admin-service-image-id' ).value = trigger.getAttribute( 'data-image-id' ) || '0';

			setImagePreview( trigger.getAttribute( 'data-image-url' ) || '' );

			try {
				clinicChargesPreset = JSON.parse( trigger.getAttribute( 'data-clinic-charges' ) || '{}' );
			} catch ( error ) {
				clinicChargesPreset = {};
			}

			setMultiSelectValues( 'dak-admin-service-clinics', Object.keys( clinicChargesPreset ) );
			renderClinicChargeRows();

			openModal( modal );
		} );
	}

	function wireSave( modal ) {
		var saveButton = document.getElementById( 'dak-admin-service-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearErrors();

			var doctorIds = getMultiSelectValues( 'dak-admin-service-doctor' );

			if ( ! doctorIds.length ) {
				var doctorError = document.querySelector( '.dak-field-error[data-field="doctor_id"]' );

				if ( doctorError ) {
					doctorError.textContent = 'Please select at least one doctor.';
				}

				return;
			}

			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_save' );
			formData.append( 'nonce', window.dakAdminServices.nonce );
			formData.append( 'service_id', document.getElementById( 'dak-admin-service-id' ).value );

			doctorIds.forEach( function ( doctorId ) {
				formData.append( 'doctor_ids[]', doctorId );
			} );

			formData.append( 'name', document.getElementById( 'dak-admin-service-name' ).value );
			formData.append( 'category', document.getElementById( 'dak-admin-service-category' ).value );
			formData.append( 'charge', document.getElementById( 'dak-admin-service-charge' ).value );
			formData.append( 'duration_minutes', document.getElementById( 'dak-admin-service-duration' ).value );
			formData.append( 'active', document.getElementById( 'dak-admin-service-active' ).checked ? '1' : '' );
			formData.append( 'description', document.getElementById( 'dak-admin-service-description' ).value );
			formData.append( 'image_id', document.getElementById( 'dak-admin-service-image-id' ).value );
			formData.append( 'has_portfolio_fields', '1' );

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
					saveButton.disabled = false;

					if ( result.success ) {
						window.location.reload();
						return;
					}

					showErrors( result );
				} )
				.catch( function () {
					saveButton.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function wireDelete() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-service-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this service? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_delete' );
			formData.append( 'nonce', window.dakAdminServices.nonce );
			formData.append( 'service_id', trigger.getAttribute( 'data-service-id' ) );

			fetch( window.dakAdminServices.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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

	function showErrors( result ) {
		if ( result.data && result.data.errors ) {
			Object.keys( result.data.errors ).forEach( function ( field ) {
				var el = document.querySelector( '#dak-admin-service-modal .dak-field-error[data-field="' + field + '"]' );

				if ( el ) {
					el.textContent = result.data.errors[ field ];
				} else {
					showGeneralError( result.data.errors[ field ] );
				}
			} );

			return;
		}

		showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
	}

	function clearErrors() {
		document.querySelectorAll( '#dak-admin-service-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-service-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-service-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
