/**
 * Doctor AK Portal — Doctor dashboard "Video Consultation" tab.
 *
 * Lets a logged-in doctor set their fixed video-consultation price and an
 * optional time-limited discount, via Video_Pricing_Handler's doctor-facing
 * AJAX endpoint (doctor_ak_video_pricing_save). Also drives the live
 * "Patient preview" card, recomputed as the doctor edits the fields.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		wireSave();
		wirePreview();
	} );

	function wireSave() {
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
			formData.append( 'discount_ends_at', combinedDiscountEndsAt() );
			formData.append( 'instant_lead_hours', document.getElementById( 'dak-video-pricing-instant-lead-hours' ).value );
			formData.append( 'instant_surcharge', document.getElementById( 'dak-video-pricing-instant-surcharge' ).value );
			formData.append( 'cancel_refund_hours', document.getElementById( 'dak-video-pricing-cancel-refund-hours' ).value );

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
	}

	/**
	 * Combines the separate "ends on" date + "ends at" time fields into the
	 * 'YYYY-MM-DD HH:MM' string the backend expects.
	 *
	 * @return {string}
	 */
	function combinedDiscountEndsAt() {
		var dateField = document.getElementById( 'dak-video-pricing-discount-ends-date' );
		var timeField = document.getElementById( 'dak-video-pricing-discount-ends-time' );
		var date = dateField ? dateField.value : '';
		var time = timeField ? timeField.value : '';

		if ( ! date ) {
			return '';
		}

		return date + ' ' + ( time || '23:59' );
	}

	/**
	 * Recomputes the "Patient preview" card from the current (unsaved)
	 * field values, live as the doctor edits them, and ticks the discount
	 * countdown every 30 seconds.
	 */
	function wirePreview() {
		var preview = document.getElementById( 'dak-video-pricing-preview' );

		if ( ! preview ) {
			return;
		}

		var fieldIds = [
			'dak-video-pricing-price',
			'dak-video-pricing-discount-percent',
			'dak-video-pricing-discount-ends-date',
			'dak-video-pricing-discount-ends-time',
		];

		fieldIds.forEach( function ( id ) {
			var field = document.getElementById( id );

			if ( field ) {
				field.addEventListener( 'input', updatePreview );
			}
		} );

		updatePreview();
		setInterval( updatePreview, 30000 );

		function updatePreview() {
			var price = parseFloat( document.getElementById( 'dak-video-pricing-price' ).value ) || 0;
			var discountPercent = parseInt( document.getElementById( 'dak-video-pricing-discount-percent' ).value, 10 ) || 0;
			var endsAt = combinedDiscountEndsAt();
			var endsAtTime = endsAt ? new Date( endsAt.replace( ' ', 'T' ) ).getTime() : 0;
			var now = Date.now();
			var discountActive = discountPercent > 0 && endsAtTime > now;
			var finalPrice = discountActive ? Math.round( price * ( 100 - discountPercent ) / 100 ) : price;

			var badge = document.getElementById( 'dak-video-pricing-preview-badge' );
			var original = document.getElementById( 'dak-video-pricing-preview-original' );
			var sale = document.getElementById( 'dak-video-pricing-preview-sale' );
			var countdown = document.getElementById( 'dak-video-pricing-preview-countdown' );

			if ( sale ) {
				sale.textContent = 'PKR ' + finalPrice.toLocaleString();
			}

			if ( badge ) {
				badge.classList.toggle( 'dak-hidden', ! discountActive );
				badge.textContent = discountPercent + '% off';
			}

			if ( original ) {
				original.classList.toggle( 'dak-hidden', ! discountActive );
				original.textContent = 'PKR ' + price.toLocaleString();
			}

			if ( countdown ) {
				countdown.classList.toggle( 'dak-hidden', ! discountActive );

				if ( discountActive ) {
					countdown.textContent = 'Discount ends in ' + formatCountdown( endsAtTime - now );
				}
			}
		}
	}

	/**
	 * Formats a millisecond duration as "Xd Yh Zm".
	 *
	 * @param {number} ms Milliseconds remaining.
	 * @return {string}
	 */
	function formatCountdown( ms ) {
		var totalMinutes = Math.max( 0, Math.floor( ms / 60000 ) );
		var days = Math.floor( totalMinutes / ( 60 * 24 ) );
		var hours = Math.floor( ( totalMinutes % ( 60 * 24 ) ) / 60 );
		var minutes = totalMinutes % 60;

		return days + 'd ' + hours + 'h ' + minutes + 'm';
	}

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
