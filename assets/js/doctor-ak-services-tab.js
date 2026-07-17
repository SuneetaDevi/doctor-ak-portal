/**
 * Doctor AK Portal — Doctor dashboard "Services" tab.
 *
 * Lets a logged-in doctor add, edit, or delete their own services, via
 * Service_Handler's doctor-facing AJAX endpoints
 * (doctor_ak_service_save/_delete).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-service-modal' );

		if ( ! modal || ! window.dakServicesTab ) {
			return;
		}

		wireModalClose( modal );
		wireAdd( modal );
		wireEdit( modal );
		wireSave( modal );
		wireDelete();
	} );

	function resetModalFields() {
		document.getElementById( 'dak-service-id' ).value = '0';
		document.getElementById( 'dak-service-type' ).value = 'clinic';
		document.getElementById( 'dak-service-name' ).value = '';
		document.getElementById( 'dak-service-category' ).value = '';
		document.getElementById( 'dak-service-charge' ).value = '0';
		document.getElementById( 'dak-service-duration' ).value = '0';
		document.getElementById( 'dak-service-active' ).checked = true;
	}

	function wireAdd( modal ) {
		var addButton = document.getElementById( 'dak-service-add' );

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
		var title = document.getElementById( 'dak-service-modal-title' );

		if ( title ) {
			title.textContent = text;
		}
	}

	function wireModalClose( modal ) {
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-service-modal-close]' ) ) {
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
			var trigger = event.target.closest( '[data-service-edit]' );

			if ( ! trigger ) {
				return;
			}

			clearErrors();
			setModalTitle( 'Edit Service' );

			document.getElementById( 'dak-service-id' ).value = trigger.getAttribute( 'data-service-id' ) || '0';
			document.getElementById( 'dak-service-type' ).value = trigger.getAttribute( 'data-type' ) || 'clinic';
			document.getElementById( 'dak-service-name' ).value = trigger.getAttribute( 'data-name' ) || '';
			document.getElementById( 'dak-service-category' ).value = trigger.getAttribute( 'data-category' ) || '';
			document.getElementById( 'dak-service-charge' ).value = trigger.getAttribute( 'data-charge' ) || '0';
			document.getElementById( 'dak-service-duration' ).value = trigger.getAttribute( 'data-duration-minutes' ) || '0';
			document.getElementById( 'dak-service-active' ).checked = '1' === trigger.getAttribute( 'data-active' );

			openModal( modal );
		} );
	}

	function wireSave( modal ) {
		var saveButton = document.getElementById( 'dak-service-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearErrors();
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_service_save' );
			formData.append( 'nonce', window.dakServicesTab.nonce );
			formData.append( 'service_id', document.getElementById( 'dak-service-id' ).value );
			formData.append( 'type', document.getElementById( 'dak-service-type' ).value );
			formData.append( 'name', document.getElementById( 'dak-service-name' ).value );
			formData.append( 'category', document.getElementById( 'dak-service-category' ).value );
			formData.append( 'charge', document.getElementById( 'dak-service-charge' ).value );
			formData.append( 'duration_minutes', document.getElementById( 'dak-service-duration' ).value );
			formData.append( 'active', document.getElementById( 'dak-service-active' ).checked ? '1' : '' );

			fetch( window.dakServicesTab.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
			var trigger = event.target.closest( '[data-service-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this service? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_service_delete' );
			formData.append( 'nonce', window.dakServicesTab.nonce );
			formData.append( 'service_id', trigger.getAttribute( 'data-service-id' ) );

			fetch( window.dakServicesTab.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
		document.querySelectorAll( '#dak-service-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-service-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-service-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
