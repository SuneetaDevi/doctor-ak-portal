/**
 * Doctor AK Portal — Login form behaviour.
 *
 * One shared sign-in for every account type; the server decides where the
 * account lands (Login_Handler). Checks the two fields are filled in, shows
 * errors next to them, and prevents duplicate submissions.
 */
( function () {
	'use strict';

	var GENERIC_ERROR = 'We couldn’t sign you in just now. Please check your connection and try again.';

	function setLoading( button, loading ) {
		var label = button.querySelector( '.dak-button-label' );

		if ( loading ) {
			button.setAttribute( 'data-idle-label', label.textContent );
			label.textContent = button.getAttribute( 'data-loading-label' ) || label.textContent;
		} else if ( button.getAttribute( 'data-idle-label' ) ) {
			label.textContent = button.getAttribute( 'data-idle-label' );
		}

		button.disabled = loading;
		button.classList.toggle( 'is-loading', loading );
		button.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
	}

	function fieldError( form, name, message ) {
		var input = form.querySelector( '[name="' + name + '"]' );
		var slot = form.querySelector( '.dak-field-error[data-field="' + name + '"]' );

		if ( slot ) {
			slot.textContent = message;
			slot.classList.toggle( 'is-visible', '' !== message );
		}

		if ( input ) {
			input.setAttribute( 'aria-invalid', '' !== message ? 'true' : 'false' );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-login-form' );

		if ( ! form ) {
			return;
		}

		var submitBtn = document.getElementById( 'dak-login-submit' );
		var successAlert = document.getElementById( 'dak-login-success' );
		var errorAlert = document.getElementById( 'dak-login-error' );
		var busy = false;

		function showError( message ) {
			errorAlert.textContent = message;
			errorAlert.classList.remove( 'dak-hidden' );
			errorAlert.focus();
		}

		// Clear a field's error as soon as it's corrected.
		form.addEventListener( 'input', function ( event ) {
			if ( event.target.name && event.target.value ) {
				fieldError( form, event.target.name, '' );
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( busy ) {
				return;
			}

			successAlert.classList.add( 'dak-hidden' );
			errorAlert.classList.add( 'dak-hidden' );

			var login = form.querySelector( '[name="login"]' );
			var password = form.querySelector( '[name="password"]' );
			var firstInvalid = null;

			fieldError( form, 'login', '' === login.value.trim() ? 'Enter your username or email.' : '' );
			fieldError( form, 'password', '' === password.value ? 'Enter your password.' : '' );

			if ( '' === login.value.trim() ) {
				firstInvalid = login;
			} else if ( '' === password.value ) {
				firstInvalid = password;
			}

			if ( firstInvalid ) {
				firstInvalid.focus();
				return;
			}

			busy = true;
			setLoading( submitBtn, true );

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_login' );
			formData.append( 'nonce', dakLogin.nonce );

			fetch( dakLogin.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result && result.success ) {
						successAlert.textContent = result.data.message;
						successAlert.classList.remove( 'dak-hidden' );

						if ( result.data.redirect ) {
							window.location.href = result.data.redirect;
							return;
						}
					} else {
						showError( ( result && result.data && result.data.message ) || GENERIC_ERROR );
					}

					busy = false;
					setLoading( submitBtn, false );
				} )
				.catch( function () {
					busy = false;
					setLoading( submitBtn, false );
					showError( GENERIC_ERROR );
				} );
		} );
	} );
} )();
