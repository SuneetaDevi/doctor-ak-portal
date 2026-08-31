/**
 * Doctor AK Portal — Admin dashboard "Settings" page: "Google reviews" card
 * (see templates/dashboard/partials/admin-settings-section.php).
 *
 * Its own standalone "Save & Refresh" button rather than joining the page's
 * combined "Save Settings" action — saving here also triggers a live API
 * call server-side (Google_Reviews::refresh_now()) so a wrong Place ID/key
 * is reported immediately instead of silently failing the next time the
 * Home page loads.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var saveButton = document.getElementById( 'dak-google-reviews-save' );

		if ( ! saveButton || ! window.dakGoogleReviews ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			hide( 'dak-google-reviews-error' );
			hide( 'dak-google-reviews-success' );
			saveButton.disabled = true;
			saveButton.textContent = 'Saving…';

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_google_reviews_save' );
			formData.append( 'nonce', window.dakGoogleReviews.nonce );
			formData.append( 'place_id', valueOf( 'dak-google-reviews-place-id' ) );
			formData.append( 'api_key', valueOf( 'dak-google-reviews-api-key' ) );

			fetch( window.dakGoogleReviews.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;
					saveButton.textContent = 'Save & Refresh';

					if ( result.success ) {
						show( 'dak-google-reviews-success', result.data.message );
					} else {
						show( 'dak-google-reviews-error', ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
					}
				} )
				.catch( function () {
					saveButton.disabled = false;
					saveButton.textContent = 'Save & Refresh';
					show( 'dak-google-reviews-error', 'Something went wrong. Please try again.' );
				} );
		} );

		function valueOf( id ) {
			var el = document.getElementById( id );
			return el ? el.value : '';
		}

		function hide( id ) {
			var el = document.getElementById( id );

			if ( el ) {
				el.classList.add( 'dak-hidden' );
			}
		}

		function show( id, message ) {
			var el = document.getElementById( id );

			if ( el ) {
				el.textContent = message;
				el.classList.remove( 'dak-hidden' );
			}
		}
	} );
} )();
