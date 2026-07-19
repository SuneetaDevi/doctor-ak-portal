/**
 * Doctor AK Portal — Admin "Video Consultation" table.
 *
 * Lets an administrator set/override any doctor's fixed video-consultation
 * price and discount, via Video_Pricing_Handler's admin AJAX endpoint
 * (doctor_ak_admin_video_pricing_save).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-admin-video-pricing-modal' );

		if ( ! modal || ! window.dakAdminVideoPricing ) {
			return;
		}

		wireModalClose( modal );
		wireEdit( modal );
		wireSave( modal );
	} );

	function wireModalClose( modal ) {
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-admin-video-pricing-modal-close]' ) ) {
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
			var trigger = event.target.closest( '[data-admin-video-pricing-edit]' );

			if ( ! trigger ) {
				return;
			}

			clearErrors();

			document.getElementById( 'dak-admin-video-pricing-doctor' ).value = trigger.getAttribute( 'data-doctor-id' ) || '';
			document.getElementById( 'dak-admin-video-pricing-price' ).value = trigger.getAttribute( 'data-price' ) || '0';
			document.getElementById( 'dak-admin-video-pricing-discount-percent' ).value = trigger.getAttribute( 'data-discount-percent' ) || '0';

			var discountEndsAt = trigger.getAttribute( 'data-discount-ends-at' ) || '';
			document.getElementById( 'dak-admin-video-pricing-discount-ends-at' ).value = discountEndsAt ? discountEndsAt.replace( ' ', 'T' ) : '';

			openModal( modal );
		} );
	}

	function wireSave( modal ) {
		var saveButton = document.getElementById( 'dak-admin-video-pricing-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearErrors();

			var doctorId = document.getElementById( 'dak-admin-video-pricing-doctor' ).value;

			if ( ! doctorId ) {
				var doctorError = document.querySelector( '.dak-field-error[data-field="doctor_id"]' );

				if ( doctorError ) {
					doctorError.textContent = 'Please select a doctor.';
				}

				return;
			}

			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_video_pricing_save' );
			formData.append( 'nonce', window.dakAdminVideoPricing.nonce );
			formData.append( 'doctor_id', doctorId );
			formData.append( 'price', document.getElementById( 'dak-admin-video-pricing-price' ).value );
			formData.append( 'discount_percent', document.getElementById( 'dak-admin-video-pricing-discount-percent' ).value );
			formData.append( 'discount_ends_at', document.getElementById( 'dak-admin-video-pricing-discount-ends-at' ).value );

			fetch( window.dakAdminVideoPricing.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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

	function errorsToMessage( result ) {
		if ( result.data && result.data.errors ) {
			return Object.keys( result.data.errors ).map( function ( field ) { return result.data.errors[ field ]; } ).join( ' ' );
		}

		return ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
	}

	function clearErrors() {
		document.querySelectorAll( '#dak-admin-video-pricing-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-video-pricing-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-video-pricing-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
