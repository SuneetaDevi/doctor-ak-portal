/**
 * Doctor AK Portal — Login form behaviour.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-login-form' );

		if ( ! form ) {
			return;
		}

		var submitBtn = document.getElementById( 'dak-login-submit' );
		var successAlert = document.getElementById( 'dak-login-success' );
		var errorAlert = document.getElementById( 'dak-login-error' );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			successAlert.classList.add( 'dak-hidden' );
			errorAlert.classList.add( 'dak-hidden' );

			submitBtn.disabled = true;
			submitBtn.classList.add( 'is-loading' );

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_login' );
			formData.append( 'nonce', dakLogin.nonce );

			fetch( dakLogin.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						successAlert.textContent = result.data.message;
						successAlert.classList.remove( 'dak-hidden' );

						if ( result.data.redirect ) {
							window.location.href = result.data.redirect;
							return;
						}

						submitBtn.disabled = false;
						submitBtn.classList.remove( 'is-loading' );
					} else {
						submitBtn.disabled = false;
						submitBtn.classList.remove( 'is-loading' );
						errorAlert.textContent = ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
						errorAlert.classList.remove( 'dak-hidden' );
					}
				} )
				.catch( function () {
					submitBtn.disabled = false;
					submitBtn.classList.remove( 'is-loading' );
					errorAlert.textContent = 'Something went wrong. Please try again.';
					errorAlert.classList.remove( 'dak-hidden' );
				} );
		} );
	} );
} )();
