/**
 * Doctor AK Portal — Admin "Service Portfolio" table.
 *
 * Lets an administrator add, edit, or delete a public service listing, via
 * Service_Catalog_Handler's AJAX endpoints (doctor_ak_admin_service_catalog_save/_delete).
 */
( function () {
	'use strict';

	var selectedImageFile = null;

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-admin-service-portfolio-modal' );

		if ( ! modal || ! window.dakAdminServicePortfolio ) {
			return;
		}

		wireModalClose( modal );
		wireAdd( modal );
		wireEdit( modal );
		wireImagePicker();
		wireSave( modal );
		wireDelete();
	} );

	function setMultiSelectValues( id, values ) {
		var select = document.getElementById( id );

		if ( ! select ) {
			return;
		}

		var stringValues = values.map( function ( value ) { return String( value ); } );

		Array.prototype.forEach.call( select.options, function ( option ) {
			option.selected = -1 !== stringValues.indexOf( option.value );
		} );
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
		document.getElementById( 'dak-admin-service-portfolio-image-id' ).value = '0';
		document.getElementById( 'dak-admin-service-portfolio-image' ).value = '';

		var preview = document.getElementById( 'dak-admin-service-portfolio-image-preview' );
		preview.src = '';
		preview.classList.add( 'dak-hidden' );
	}

	function setImagePreview( url ) {
		var preview = document.getElementById( 'dak-admin-service-portfolio-image-preview' );

		if ( url ) {
			preview.src = url;
			preview.classList.remove( 'dak-hidden' );
		} else {
			preview.src = '';
			preview.classList.add( 'dak-hidden' );
		}
	}

	function resetModalFields() {
		document.getElementById( 'dak-admin-service-portfolio-id' ).value = '0';
		document.getElementById( 'dak-admin-service-portfolio-name' ).value = '';
		document.getElementById( 'dak-admin-service-portfolio-description' ).value = '';
		document.getElementById( 'dak-admin-service-portfolio-price' ).value = '0';
		document.getElementById( 'dak-admin-service-portfolio-active' ).checked = true;
		setMultiSelectValues( 'dak-admin-service-portfolio-clinics', [] );
		setMultiSelectValues( 'dak-admin-service-portfolio-doctors', [] );
		resetImagePreview();
	}

	function wireAdd( modal ) {
		var addButton = document.getElementById( 'dak-admin-service-portfolio-add' );

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
		var title = document.getElementById( 'dak-admin-service-portfolio-modal-title' );

		if ( title ) {
			title.textContent = text;
		}
	}

	function wireModalClose( modal ) {
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-admin-service-portfolio-modal-close]' ) ) {
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
			var trigger = event.target.closest( '[data-admin-service-portfolio-edit]' );

			if ( ! trigger ) {
				return;
			}

			clearErrors();
			setModalTitle( 'Edit Service' );
			resetImagePreview();

			document.getElementById( 'dak-admin-service-portfolio-id' ).value = trigger.getAttribute( 'data-service-id' ) || '0';
			document.getElementById( 'dak-admin-service-portfolio-name' ).value = trigger.getAttribute( 'data-name' ) || '';
			document.getElementById( 'dak-admin-service-portfolio-description' ).value = trigger.getAttribute( 'data-description' ) || '';
			document.getElementById( 'dak-admin-service-portfolio-price' ).value = trigger.getAttribute( 'data-price' ) || '0';
			document.getElementById( 'dak-admin-service-portfolio-active' ).checked = '1' === trigger.getAttribute( 'data-active' );
			document.getElementById( 'dak-admin-service-portfolio-image-id' ).value = trigger.getAttribute( 'data-image-id' ) || '0';

			setImagePreview( trigger.getAttribute( 'data-image-url' ) || '' );

			try {
				setMultiSelectValues( 'dak-admin-service-portfolio-clinics', JSON.parse( trigger.getAttribute( 'data-clinic-location-ids' ) || '[]' ) );
				setMultiSelectValues( 'dak-admin-service-portfolio-doctors', JSON.parse( trigger.getAttribute( 'data-doctor-ids' ) || '[]' ) );
			} catch ( error ) {
				setMultiSelectValues( 'dak-admin-service-portfolio-clinics', [] );
				setMultiSelectValues( 'dak-admin-service-portfolio-doctors', [] );
			}

			openModal( modal );
		} );
	}

	function wireImagePicker() {
		var input = document.getElementById( 'dak-admin-service-portfolio-image' );

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

	function wireSave( modal ) {
		var saveButton = document.getElementById( 'dak-admin-service-portfolio-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearErrors();
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_catalog_save' );
			formData.append( 'nonce', window.dakAdminServicePortfolio.nonce );
			formData.append( 'id', document.getElementById( 'dak-admin-service-portfolio-id' ).value );
			formData.append( 'name', document.getElementById( 'dak-admin-service-portfolio-name' ).value );
			formData.append( 'description', document.getElementById( 'dak-admin-service-portfolio-description' ).value );
			formData.append( 'price', document.getElementById( 'dak-admin-service-portfolio-price' ).value );
			formData.append( 'active', document.getElementById( 'dak-admin-service-portfolio-active' ).checked ? '1' : '' );
			formData.append( 'image_id', document.getElementById( 'dak-admin-service-portfolio-image-id' ).value );

			getMultiSelectValues( 'dak-admin-service-portfolio-clinics' ).forEach( function ( value ) {
				formData.append( 'clinic_location_ids[]', value );
			} );

			getMultiSelectValues( 'dak-admin-service-portfolio-doctors' ).forEach( function ( value ) {
				formData.append( 'doctor_ids[]', value );
			} );

			if ( selectedImageFile ) {
				formData.append( 'image', selectedImageFile );
			}

			fetch( window.dakAdminServicePortfolio.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
			var trigger = event.target.closest( '[data-admin-service-portfolio-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this service? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_catalog_delete' );
			formData.append( 'nonce', window.dakAdminServicePortfolio.nonce );
			formData.append( 'id', trigger.getAttribute( 'data-service-id' ) );

			fetch( window.dakAdminServicePortfolio.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
				var el = document.querySelector( '#dak-admin-service-portfolio-modal .dak-field-error[data-field="' + field + '"]' );

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
		document.querySelectorAll( '#dak-admin-service-portfolio-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-service-portfolio-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-service-portfolio-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
