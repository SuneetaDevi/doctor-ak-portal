/**
 * Doctor AK Portal — Edit Profile behaviour.
 *
 * Depends on doctor-ak-registration.js being loaded first, and reuses its
 * shared `window.DoctorAKPortal` utilities (multi-select, field error
 * display) instead of duplicating them.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var shared = window.DoctorAKPortal || {};

		if ( typeof shared.initMultiSelect === 'function' ) {
			shared.initMultiSelect( document.getElementById( 'dak-profile-specializations' ) );
		}

		initProfilePictureUpload();
		initProfileForm( shared );

		if ( window.dakCityArea && window.dakProfile ) {
			var citySelect = document.getElementById( 'dak-profile-city' );
			var areaSelect = document.getElementById( 'dak-profile-area' );

			if ( citySelect && areaSelect ) {
				window.dakCityArea.wire(
					citySelect,
					areaSelect,
					window.dakProfile.locations,
					citySelect.getAttribute( 'data-current' ) || '',
					areaSelect.getAttribute( 'data-current' ) || ''
				);
			}
		}
	} );

	/**
	 * Uploads a new profile picture immediately and saves it against the
	 * logged-in user (no "claim" step needed, unlike registration).
	 */
	function initProfilePictureUpload() {
		var input = document.getElementById( 'dak-profile-picture-input' );
		var preview = document.getElementById( 'dak-profile-picture-preview' );
		var status = document.getElementById( 'dak-upload-status' );

		if ( ! input || ! preview || ! status ) {
			return;
		}

		input.addEventListener( 'change', function () {
			var file = input.files[0];

			if ( ! file ) {
				return;
			}

			status.textContent = 'Uploading…';

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_profile_upload_picture' );
			formData.append( 'nonce', dakProfile.nonce );
			formData.append( 'profile_picture', file );

			fetch( dakProfile.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						preview.innerHTML = '';

						var img = document.createElement( 'img' );
						img.src = result.data.url;
						img.alt = '';
						preview.appendChild( img );

						status.textContent = '';
					} else {
						status.textContent = ( result.data && result.data.message ) || 'Upload failed. Please try again.';
					}
				} )
				.catch( function () {
					status.textContent = 'Upload failed. Please try again.';
				} );
		} );
	}

	/**
	 * Submits profile changes (and optional password change) over AJAX.
	 *
	 * @param {Object} shared Shared DoctorAKPortal utilities namespace.
	 */
	function initProfileForm( shared ) {
		var form = document.getElementById( 'dak-profile-form' );

		if ( ! form ) {
			return;
		}

		var submitBtn = document.getElementById( 'dak-profile-submit' );
		var successAlert = document.getElementById( 'dak-profile-success' );
		var errorAlert = document.getElementById( 'dak-profile-general-error' );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( typeof shared.clearFieldErrors === 'function' ) {
				shared.clearFieldErrors( form );
			}

			successAlert.classList.add( 'dak-hidden' );
			errorAlert.classList.add( 'dak-hidden' );

			var newPassword = form.querySelector( '[name="new_password"]' ).value;
			var confirmNewPassword = form.querySelector( '[name="confirm_new_password"]' ).value;

			if ( newPassword !== confirmNewPassword ) {
				if ( typeof shared.showFieldError === 'function' ) {
					shared.showFieldError( form, 'confirm_new_password', 'Passwords do not match.' );
				}
				return;
			}

			submitBtn.disabled = true;
			submitBtn.classList.add( 'is-loading' );

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_profile_update' );
			formData.append( 'nonce', dakProfile.nonce );

			fetch( dakProfile.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitBtn.disabled = false;
					submitBtn.classList.remove( 'is-loading' );

					if ( result.success ) {
						successAlert.textContent = result.data.message;
						successAlert.classList.remove( 'dak-hidden' );
						form.querySelector( '[name="current_password"]' ).value = '';
						form.querySelector( '[name="new_password"]' ).value = '';
						form.querySelector( '[name="confirm_new_password"]' ).value = '';
					} else if ( result.data && result.data.errors ) {
						Object.keys( result.data.errors ).forEach( function ( field ) {
							if ( typeof shared.showFieldError === 'function' ) {
								shared.showFieldError( form, field, result.data.errors[ field ] );
							}
						} );
						errorAlert.textContent = 'Please correct the errors below and try again.';
						errorAlert.classList.remove( 'dak-hidden' );
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
} )();
