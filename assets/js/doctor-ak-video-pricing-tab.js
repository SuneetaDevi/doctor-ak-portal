/**
 * Doctor AK Portal — Doctor dashboard "Video Consultation" tab.
 *
 * Lets a logged-in doctor set their fixed video-consultation price and an
 * optional time-limited discount, via Video_Pricing_Handler's doctor-facing
 * AJAX endpoint (doctor_ak_video_pricing_save).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var saveButton = document.getElementById( 'dak-video-pricing-save' );

		if ( ! saveButton || ! window.dakVideoPricingTab ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearErrors();
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_video_pricing_save' );
			formData.append( 'nonce', window.dakVideoPricingTab.nonce );
			formData.append( 'price', document.getElementById( 'dak-video-pricing-price' ).value );
			formData.append( 'discount_percent', document.getElementById( 'dak-video-pricing-discount-percent' ).value );
			formData.append( 'discount_ends_at', document.getElementById( 'dak-video-pricing-discount-ends-at' ).value );

			fetch( window.dakVideoPricingTab.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
	} );

	function errorsToMessage( result ) {
		if ( result.data && result.data.errors ) {
			return Object.keys( result.data.errors ).map( function ( field ) { return result.data.errors[ field ]; } ).join( ' ' );
		}

		return ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
	}

	function clearErrors() {
		document.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-video-pricing-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-video-pricing-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}
} )();
