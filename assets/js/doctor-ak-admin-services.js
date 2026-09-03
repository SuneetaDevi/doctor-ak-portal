/**
 * Doctor AK Portal — Admin "Services" table.
 *
 * Add/Edit now happens on its own full-screen page (see
 * admin-service-form-screen.php / doctor-ak-admin-service-form.js) rather
 * than a modal, so this file only wires the table's Delete action —
 * doctor_ak_admin_service_delete — checked against the admin nonce.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAdminServices ) {
			return;
		}

		wireDelete();
	} );

	function wireDelete() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-service-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this service? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_delete' );
			formData.append( 'nonce', window.dakAdminServices.nonce );
			formData.append( 'service_id', trigger.getAttribute( 'data-service-id' ) );

			fetch( window.dakAdminServices.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
