/**
 * Doctor AK Portal — Admin "Doctor Sessions" table.
 *
 * Add/Edit now happens on its own full-screen page (see
 * admin-session-form-screen.php / doctor-ak-admin-session-form.js) rather
 * than a modal, so this file only wires the table's Delete action —
 * doctor_ak_admin_clinic_delete — checked against the admin nonce.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAdminSessions ) {
			return;
		}

		wireDelete();
	} );

	function wireDelete() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-session-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this clinic session? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_clinic_delete' );
			formData.append( 'nonce', window.dakAdminSessions.nonce );
			formData.append( 'clinic_id', trigger.getAttribute( 'data-clinic-id' ) );

			fetch( window.dakAdminSessions.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					window.alert( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					window.alert( 'Something went wrong. Please try again.' );
				} );
		} );
	}
} )();
