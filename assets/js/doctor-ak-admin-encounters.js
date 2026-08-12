/**
 * Doctor AK Portal — Admin dashboard "Encounters" list: Delete row action.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAdminEncounters ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-encounter-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( window.dakAdminEncounters.confirmDelete ) ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_delete_encounter' );
			formData.append( 'nonce', window.dakAdminEncounters.nonce );
			formData.append( 'encounter_id', trigger.getAttribute( 'data-encounter-id' ) );

			fetch( window.dakAdminEncounters.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || window.dakAdminEncounters.genericError );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( window.dakAdminEncounters.genericError );
				} );
		} );
	} );
} )();
