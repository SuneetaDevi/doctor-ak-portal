/**
 * Doctor AK Portal — Admin "Clinic" table (master Clinic_Locations list).
 *
 * Lets an administrator add, edit, or delete a clinic location, via
 * Clinic_Location_Handler's admin AJAX endpoints
 * (doctor_ak_admin_clinic_location_save/_delete).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-admin-clinic-location-modal' );

		if ( ! modal || ! window.dakAdminClinicLocations ) {
			return;
		}

		var countrySelect = document.getElementById( 'dak-admin-clinic-location-country' );
		var citySelect = document.getElementById( 'dak-admin-clinic-location-city' );
		var areaSelect = document.getElementById( 'dak-admin-clinic-location-area' );

		wireModalClose( modal );
		wireAdd( modal );
		wireEdit( modal );
		wireSave( modal );
		wireDelete();

		function wireLocationSelects( selectedCountry, selectedCity, selectedArea ) {
			if ( ! window.dakCityArea || ! countrySelect || ! citySelect || ! areaSelect ) {
				return;
			}

			window.dakCityArea.wire(
				countrySelect,
				citySelect,
				areaSelect,
				window.dakAdminClinicLocations.locations,
				selectedCountry || '',
				selectedCity || '',
				selectedArea || ''
			);
		}

		function resetModalFields() {
			document.getElementById( 'dak-admin-clinic-location-id' ).value = '0';
			document.getElementById( 'dak-admin-clinic-location-name' ).value = '';
			document.getElementById( 'dak-admin-clinic-location-address' ).value = '';
			document.getElementById( 'dak-admin-clinic-location-phone' ).value = '';
			document.getElementById( 'dak-admin-clinic-location-email' ).value = '';
			wireLocationSelects();
		}

		function wireAdd() {
			var addButton = document.getElementById( 'dak-admin-clinic-location-add' );

			if ( ! addButton ) {
				return;
			}

			addButton.addEventListener( 'click', function () {
				clearErrors();
				resetModalFields();
				setModalTitle( 'Add Clinic' );
				openModal( modal );
			} );
		}

		function setModalTitle( text ) {
			var title = document.getElementById( 'dak-admin-clinic-location-modal-title' );

			if ( title ) {
				title.textContent = text;
			}
		}

		function wireModalClose() {
			document.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '[data-dak-admin-clinic-location-modal-close]' ) ) {
					closeModal( modal );
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && modal.classList.contains( 'is-open' ) ) {
					closeModal( modal );
				}
			} );
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

		function wireEdit() {
			document.addEventListener( 'click', function ( event ) {
				var trigger = event.target.closest( '[data-admin-clinic-location-edit]' );

				if ( ! trigger ) {
					return;
				}

				clearErrors();
				setModalTitle( 'Edit Clinic' );

				document.getElementById( 'dak-admin-clinic-location-id' ).value = trigger.getAttribute( 'data-id' ) || '0';
				document.getElementById( 'dak-admin-clinic-location-name' ).value = trigger.getAttribute( 'data-name' ) || '';
				document.getElementById( 'dak-admin-clinic-location-address' ).value = trigger.getAttribute( 'data-address' ) || '';
				document.getElementById( 'dak-admin-clinic-location-phone' ).value = trigger.getAttribute( 'data-phone' ) || '';
				document.getElementById( 'dak-admin-clinic-location-email' ).value = trigger.getAttribute( 'data-contact-email' ) || '';

				wireLocationSelects(
					trigger.getAttribute( 'data-country' ),
					trigger.getAttribute( 'data-city' ),
					trigger.getAttribute( 'data-area' )
				);

				openModal();
			} );
		}

		function wireSave() {
			var saveButton = document.getElementById( 'dak-admin-clinic-location-save' );

			if ( ! saveButton ) {
				return;
			}

			saveButton.addEventListener( 'click', function () {
				clearErrors();
				saveButton.disabled = true;

				var formData = new FormData();
				formData.append( 'action', 'doctor_ak_admin_clinic_location_save' );
				formData.append( 'nonce', window.dakAdminClinicLocations.nonce );
				formData.append( 'id', document.getElementById( 'dak-admin-clinic-location-id' ).value );
				formData.append( 'name', document.getElementById( 'dak-admin-clinic-location-name' ).value );
				formData.append( 'country', countrySelect ? countrySelect.value : '' );
				formData.append( 'city', citySelect ? citySelect.value : '' );
				formData.append( 'area', areaSelect ? areaSelect.value : '' );
				formData.append( 'address', document.getElementById( 'dak-admin-clinic-location-address' ).value );
				formData.append( 'phone', document.getElementById( 'dak-admin-clinic-location-phone' ).value );
				formData.append( 'contact_email', document.getElementById( 'dak-admin-clinic-location-email' ).value );

				fetch( window.dakAdminClinicLocations.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
					.then( function ( response ) { return response.json(); } )
					.then( function ( result ) {
						saveButton.disabled = false;

						if ( result.success ) {
							window.location.reload();
							return;
						}

						if ( result.data && result.data.errors ) {
							Object.keys( result.data.errors ).forEach( function ( field ) {
								showFieldError( field, result.data.errors[ field ] );
							} );
						} else {
							showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
						}
					} )
					.catch( function () {
						saveButton.disabled = false;
						showGeneralError( 'Something went wrong. Please try again.' );
					} );
			} );
		}

		function wireDelete() {
			document.addEventListener( 'click', function ( event ) {
				var trigger = event.target.closest( '[data-admin-clinic-location-delete]' );

				if ( ! trigger ) {
					return;
				}

				if ( ! window.confirm( 'Delete this clinic? This cannot be undone.' ) ) {
					return;
				}

				var formData = new FormData();
				formData.append( 'action', 'doctor_ak_admin_clinic_location_delete' );
				formData.append( 'nonce', window.dakAdminClinicLocations.nonce );
				formData.append( 'id', trigger.getAttribute( 'data-id' ) );

				fetch( window.dakAdminClinicLocations.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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

		function clearErrors() {
			document.querySelectorAll( '#dak-admin-clinic-location-modal .dak-field-error' ).forEach( function ( el ) {
				el.textContent = '';
			} );

			var generalError = document.getElementById( 'dak-admin-clinic-location-general-error' );

			if ( generalError ) {
				generalError.textContent = '';
				generalError.classList.add( 'dak-hidden' );
			}
		}

		function showFieldError( field, message ) {
			var el = document.querySelector( '#dak-admin-clinic-location-modal .dak-field-error[data-field="' + field + '"]' );

			if ( el ) {
				el.textContent = message;
				return;
			}

			showGeneralError( message );
		}

		function showGeneralError( message ) {
			var el = document.getElementById( 'dak-admin-clinic-location-general-error' );

			if ( el ) {
				el.textContent = message;
				el.classList.remove( 'dak-hidden' );
			}
		}
	} );
} )();
