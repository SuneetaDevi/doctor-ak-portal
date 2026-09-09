/**
 * Doctor AK Portal — Admin "Service Requests" section.
 *
 * Lets an administrator/receptionist update a request's status or delete
 * it, via Service_Request_Handler's admin AJAX endpoints
 * (doctor_ak_admin_service_request_status/_delete).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var list = document.getElementById( 'dak-service-requests-list' );

		if ( ! list || ! window.dakAdminServiceRequests ) {
			return;
		}

		list.addEventListener( 'change', function ( event ) {
			var select = event.target.closest( '[data-service-request-status]' );

			if ( ! select ) {
				return;
			}

			select.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_request_status' );
			formData.append( 'nonce', window.dakAdminServiceRequests.nonce );
			formData.append( 'id', select.getAttribute( 'data-id' ) );
			formData.append( 'status', select.value );

			fetch( window.dakAdminServiceRequests.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					select.disabled = false;

					if ( ! result.success ) {
						window.alert( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
					}
				} )
				.catch( function () {
					select.disabled = false;
					window.alert( 'Something went wrong. Please try again.' );
				} );
		} );

		list.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-service-request-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this request? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_request_delete' );
			formData.append( 'nonce', window.dakAdminServiceRequests.nonce );
			formData.append( 'id', trigger.getAttribute( 'data-id' ) );

			fetch( window.dakAdminServiceRequests.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
	} );
} )();
