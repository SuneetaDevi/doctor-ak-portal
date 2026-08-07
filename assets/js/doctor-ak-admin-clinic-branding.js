/**
 * Doctor AK Portal — Admin dashboard "Settings" section: Clinic branding
 * (name/phone/address/logo) form. (Notification preferences live in the
 * shared assets/js/doctor-ak-notification-preferences.js instead — they're
 * a per-account setting shared with the Doctor/Patient/Receptionist
 * dashboards, not admin-only.)
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakClinicBranding ) {
			return;
		}

		wireBrandingForm();
		wireLogoUpload();
	} );

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

	function wireBrandingForm() {
		var saveButton = document.getElementById( 'dak-clinic-branding-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			hide( 'dak-clinic-branding-error' );
			hide( 'dak-clinic-branding-success' );
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_clinic_branding_save' );
			formData.append( 'nonce', window.dakClinicBranding.nonce );
			formData.append( 'clinic_name', document.getElementById( 'dak-clinic-branding-name' ).value );
			formData.append( 'clinic_phone', document.getElementById( 'dak-clinic-branding-phone' ).value );
			formData.append( 'clinic_address', document.getElementById( 'dak-clinic-branding-address' ).value );

			fetch( window.dakClinicBranding.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						show( 'dak-clinic-branding-success', ( result.data && result.data.message ) || 'Saved.' );
						return;
					}

					show( 'dak-clinic-branding-error', ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					saveButton.disabled = false;
					show( 'dak-clinic-branding-error', 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function wireLogoUpload() {
		var button = document.getElementById( 'dak-clinic-branding-logo-button' );
		var input = document.getElementById( 'dak-clinic-branding-logo-input' );
		var preview = document.getElementById( 'dak-clinic-branding-logo-preview' );

		if ( ! button || ! input || ! preview ) {
			return;
		}

		button.addEventListener( 'click', function () {
			input.click();
		} );

		input.addEventListener( 'change', function () {
			if ( ! input.files || ! input.files[ 0 ] ) {
				return;
			}

			hide( 'dak-clinic-branding-error' );
			hide( 'dak-clinic-branding-success' );
			button.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_clinic_branding_upload_logo' );
			formData.append( 'nonce', window.dakClinicBranding.nonce );
			formData.append( 'logo', input.files[ 0 ] );

			fetch( window.dakClinicBranding.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					button.disabled = false;

					if ( result.success ) {
						preview.innerHTML = '';

						var img = document.createElement( 'img' );
						img.src = result.data.logo_url;
						preview.appendChild( img );

						show( 'dak-clinic-branding-success', ( result.data && result.data.message ) || 'Saved.' );
						return;
					}

					show( 'dak-clinic-branding-error', ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					button.disabled = false;
					show( 'dak-clinic-branding-error', 'Something went wrong. Please try again.' );
				} );
		} );
	}
} )();
