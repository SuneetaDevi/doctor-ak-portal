/**
 * Doctor AK Portal — [service_profile_view]'s "Request This Service" form,
 * shown instead of the doctor-picker/booking flow when a service has
 * requires_doctor = 0 (see Service_Profile_View, service-profile-view.php).
 * Submits to Service_Request_Handler::handle_submit()
 * (doctor_ak_service_request_submit) — no date/time slot is ever picked,
 * since there's no doctor schedule to pick one from; this just captures who
 * to contact and about what.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-service-request-form' );

		if ( ! form || ! window.dakServiceProfile ) {
			return;
		}

		var submitButton = document.getElementById( 'dak-service-request-submit' );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();

			if ( submitButton ) {
				submitButton.disabled = true;
			}

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_service_request_submit' );
			formData.append( 'nonce', window.dakServiceProfile.nonce );

			fetch( window.dakServiceProfile.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( submitButton ) {
						submitButton.disabled = false;
					}

					if ( result.success ) {
						form.reset();
						form.hidden = true;
						showSuccess( ( result.data && result.data.message ) || 'Your request has been received.' );
						return;
					}

					if ( result.data && result.data.errors ) {
						Object.keys( result.data.errors ).forEach( function ( field ) {
							showFieldError( field, result.data.errors[ field ] );
						} );
						return;
					}

					showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					if ( submitButton ) {
						submitButton.disabled = false;
					}

					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );

		function clearErrors() {
			form.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
				el.textContent = '';
			} );

			hide( 'dak-service-request-general-error' );
		}

		function showFieldError( field, message ) {
			var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );

			if ( el ) {
				el.textContent = message;
				return;
			}

			showGeneralError( message );
		}

		function showGeneralError( message ) {
			show( 'dak-service-request-general-error', message );
		}

		function showSuccess( message ) {
			show( 'dak-service-request-success', message );
		}

		function show( id, message ) {
			var el = document.getElementById( id );

			if ( el ) {
				el.textContent = message;
				el.classList.remove( 'dak-hidden' );
			}
		}

		function hide( id ) {
			var el = document.getElementById( id );

			if ( el ) {
				el.classList.add( 'dak-hidden' );
			}
		}
	} );
} )();
