/**
 * Doctor AK Portal — Forgot-password request & reset-password behaviour.
 *
 * Only one of the two forms below exists in the DOM on any given page
 * load (the template decides based on the `key`/`login` query args), so
 * each init function safely no-ops if its form isn't present. The request
 * form always shows the server's neutral message, which never says whether
 * an account exists.
 */
( function () {
	'use strict';

	var GENERIC_ERROR = 'Something went wrong. Please check your connection and try again.';
	var MIN_PASSWORD = 8; // Same rule as Forgot_Password_Handler::handle_reset_password().

	document.addEventListener( 'DOMContentLoaded', function () {
		initForgotPasswordForm();
		initResetPasswordForm();
	} );

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

	function showAlert( el, message ) {
		el.textContent = message;
		el.classList.remove( 'dak-hidden' );
		el.focus();
	}

	/**
	 * Wires up the "request a reset link" form.
	 */
	function initForgotPasswordForm() {
		var form = document.getElementById( 'dak-forgot-password-form' );

		if ( ! form ) {
			return;
		}

		var submitBtn = document.getElementById( 'dak-forgot-password-submit' );
		var successAlert = document.getElementById( 'dak-forgot-password-success' );
		var errorAlert = document.getElementById( 'dak-forgot-password-error' );
		var input = form.querySelector( '[name="login_or_email"]' );
		var busy = false;

		input.addEventListener( 'input', function () {
			if ( input.value.trim() ) {
				showFieldError( form, 'login_or_email', '' );
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( busy ) {
				return;
			}

			successAlert.classList.add( 'dak-hidden' );
			errorAlert.classList.add( 'dak-hidden' );

			if ( '' === input.value.trim() ) {
				showFieldError( form, 'login_or_email', 'Enter your username or email.' );
				input.focus();
				return;
			}

			busy = true;
			setLoading( submitBtn, true );

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_request_password_reset' );
			formData.append( 'nonce', dakForgotPassword.nonce );

			fetch( dakForgotPassword.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					busy = false;
					setLoading( submitBtn, false );

					if ( result && result.success ) {
						form.reset();
						showAlert( successAlert, result.data.message );
					} else {
						showAlert( errorAlert, ( result && result.data && result.data.message ) || GENERIC_ERROR );
					}
				} )
				.catch( function () {
					busy = false;
					setLoading( submitBtn, false );
					showAlert( errorAlert, GENERIC_ERROR );
				} );
		} );
	}

	/**
	 * Wires up the "choose a new password" form.
	 */
	function initResetPasswordForm() {
		var form = document.getElementById( 'dak-reset-password-form' );

		if ( ! form ) {
			return;
		}

		var submitBtn = document.getElementById( 'dak-reset-submit' );
		var successAlert = document.getElementById( 'dak-reset-success' );
		var errorAlert = document.getElementById( 'dak-reset-error' );
		var busy = false;

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( busy ) {
				return;
			}

			clearFieldErrors( form );
			successAlert.classList.add( 'dak-hidden' );
			errorAlert.classList.add( 'dak-hidden' );

			var passwordInput = form.querySelector( '[name="password"]' );
			var confirmInput = form.querySelector( '[name="confirm_password"]' );

			if ( passwordInput.value.length < MIN_PASSWORD ) {
				showFieldError( form, 'password', 'Use at least ' + MIN_PASSWORD + ' characters.' );
				passwordInput.focus();
				return;
			}

			if ( passwordInput.value !== confirmInput.value ) {
				showFieldError( form, 'confirm_password', 'The passwords don’t match.' );
				confirmInput.focus();
				return;
			}

			busy = true;
			setLoading( submitBtn, true );

			var formData = new FormData( form );
			formData.delete( 'dak_username_hint' );
			formData.append( 'action', 'doctor_ak_reset_password' );
			formData.append( 'nonce', dakForgotPassword.nonce );

			fetch( dakForgotPassword.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result && result.success ) {
						form.reset();
						showAlert( successAlert, result.data.message );

						if ( result.data.redirect ) {
							setTimeout( function () {
								window.location.href = result.data.redirect;
							}, 1500 );
						}

						return;
					}

					busy = false;
					setLoading( submitBtn, false );

					if ( result && result.data && result.data.errors ) {
						var first = null;

						Object.keys( result.data.errors ).forEach( function ( field ) {
							showFieldError( form, field, result.data.errors[ field ] );
							first = first || form.querySelector( '[name="' + field + '"]' );
						} );

						if ( first ) {
							first.focus();
						}
					} else {
						showAlert( errorAlert, ( result && result.data && result.data.message ) || GENERIC_ERROR );
					}
				} )
				.catch( function () {
					busy = false;
					setLoading( submitBtn, false );
					showAlert( errorAlert, GENERIC_ERROR );
				} );
		} );
	}

	/**
	 * Displays (or clears, with '') a message under the given field.
	 *
	 * @param {HTMLFormElement} form    Form containing the field.
	 * @param {string}          field   Field name matching `data-field`.
	 * @param {string}          message Error message to display.
	 */
	function showFieldError( form, field, message ) {
		var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );
		var input = form.querySelector( '[name="' + field + '"]' );

		if ( el ) {
			el.textContent = message;
			el.classList.toggle( 'is-visible', '' !== message );
		}

		if ( input ) {
			input.setAttribute( 'aria-invalid', '' !== message ? 'true' : 'false' );
		}
	}

	/**
	 * Clears every field-level error message in the form.
	 *
	 * @param {HTMLFormElement} form Form to clear.
	 */
	function clearFieldErrors( form ) {
		form.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
			el.classList.remove( 'is-visible' );
		} );
		form.querySelectorAll( '[aria-invalid="true"]' ).forEach( function ( el ) {
			el.setAttribute( 'aria-invalid', 'false' );
		} );
	}
} )();
