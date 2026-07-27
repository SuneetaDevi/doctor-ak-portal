/**
 * Doctor AK Portal — Admin dashboard "Roles & Permissions" section.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-role-permissions-form' );
		var saveButton = document.getElementById( 'dak-role-permissions-save' );

		if ( ! form || ! saveButton || ! window.dakRolePermissions ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			hide( 'dak-role-permissions-error' );
			hide( 'dak-role-permissions-success' );
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_role_permissions_save' );
			formData.append( 'nonce', window.dakRolePermissions.nonce );

			form.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( checkbox ) {
				if ( checkbox.checked ) {
					formData.append( checkbox.name, '1' );
				}
			} );

			fetch( window.dakRolePermissions.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						show( 'dak-role-permissions-success', ( result.data && result.data.message ) || 'Saved.' );
						return;
					}

					show( 'dak-role-permissions-error', ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					saveButton.disabled = false;
					show( 'dak-role-permissions-error', 'Something went wrong. Please try again.' );
				} );
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
	} );
} )();
