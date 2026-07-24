/**
 * Doctor AK Portal — Admin "Doctor Requests" tab.
 *
 * Approve/reject a pending doctor registration via
 * Doctor_Requests_Handler's AJAX endpoints
 * (doctor_ak_admin_approve_doctor/_reject_doctor).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAdminDoctorRequests ) {
			return;
		}

		wireAction( '[data-doctor-request-approve]', 'doctor_ak_admin_approve_doctor', function () {
			return window.dakAdminDoctorRequests.confirmApprove;
		} );

		wireAction( '[data-doctor-request-reject]', 'doctor_ak_admin_reject_doctor', function () {
			return window.dakAdminDoctorRequests.confirmReject;
		} );
	} );

	function wireAction( selector, action, confirmMessage ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( selector );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( confirmMessage() ) ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', action );
			formData.append( 'nonce', window.dakAdminDoctorRequests.nonce );
			formData.append( 'user_id', trigger.getAttribute( 'data-user-id' ) );

			fetch( window.dakAdminDoctorRequests.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						// Reload rather than just removing the row so the
						// sidebar's "Doctor Requests" badge count (and the
						// Doctors tab, once approved) reflect reality too.
						window.location.reload();
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || window.dakAdminDoctorRequests.genericError );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( window.dakAdminDoctorRequests.genericError );
				} );
		} );
	}
} )();
