/**
 * Doctor AK Portal — Forgot-password request & reset-password behaviour.
 *
 * Only one of the two forms below exists in the DOM on any given page
 * load (the template decides based on the `key`/`login` query args), so
 * each init function safely no-ops if its form isn't present.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initForgotPasswordForm();
		initResetPasswordForm();
	} );

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

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			successAlert.classList.add( 'dak-hidden' );
			errorAlert.classList.add( 'dak-hidden' );
			submitBtn.disabled = true;
			submitBtn.classList.add( 'is-loading' );

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_request_password_reset' );
			formData.append( 'nonce', dakForgotPassword.nonce );

			fetch( dakForgotPassword.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitBtn.disabled = false;
					submitBtn.classList.remove( 'is-loading' );

					if ( result.success ) {
						successAlert.textContent = result.data.message;
						successAlert.classList.remove( 'dak-hidden' );
						form.reset();
					} else {
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

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			clearFieldErrors( form );
			successAlert.classList.add( 'dak-hidden' );
			errorAlert.classList.add( 'dak-hidden' );

			var password = form.querySelector( '[name="password"]' ).value;
			var confirmPassword = form.querySelector( '[name="confirm_password"]' ).value;

			if ( password !== confirmPassword ) {
				showFieldError( form, 'confirm_password', 'Passwords do not match.' );
				return;
			}

			submitBtn.disabled = true;
			submitBtn.classList.add( 'is-loading' );

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_reset_password' );
			formData.append( 'nonce', dakForgotPassword.nonce );

			fetch( dakForgotPassword.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitBtn.disabled = false;
					submitBtn.classList.remove( 'is-loading' );

					if ( result.success ) {
						successAlert.textContent = result.data.message;
						successAlert.classList.remove( 'dak-hidden' );
						form.reset();

						if ( result.data.redirect ) {
							setTimeout( function () {
								window.location.href = result.data.redirect;
							}, 1500 );
						}
					} else if ( result.data && result.data.errors ) {
						Object.keys( result.data.errors ).forEach( function ( field ) {
							showFieldError( form, field, result.data.errors[ field ] );
						} );
					} else {
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
	}

	/**
	 * Displays a message under the given field's error slot.
	 *
	 * @param {HTMLFormElement} form    Form containing the field.
	 * @param {string}          field   Field name matching `data-field`.
	 * @param {string}          message Error message to display.
	 */
	function showFieldError( form, field, message ) {
		var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = message;
			el.classList.add( 'is-visible' );
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
	}
} )();
