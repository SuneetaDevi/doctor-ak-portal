/**
 * Doctor AK Portal — Settings tab's "Notifications" card, shared by the
 * Admin, Doctor, Receptionist and Patient dashboards. Saves the logged-in
 * account's own preference for whether they receive "appointment booked" /
 * "payment received" / "cancelled" emails — reuses the same ajaxUrl/nonce
 * (`window.dakNotifications`) already localized for the Notifications tab's
 * mark-read actions, since this is the same per-account, no-special-
 * capability-required endpoint family.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var saveButton = document.getElementById( 'dak-notification-preferences-save' );

		if ( ! saveButton || ! window.dakNotifications ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			hide( 'dak-notification-preferences-error' );
			hide( 'dak-notification-preferences-success' );
			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_notification_preferences_save' );
			formData.append( 'nonce', window.dakNotifications.nonce );
			formData.append( 'booking', document.getElementById( 'dak-notify-booking' ).checked ? '1' : '0' );
			formData.append( 'paid', document.getElementById( 'dak-notify-paid' ).checked ? '1' : '0' );
			formData.append( 'cancelled', document.getElementById( 'dak-notify-cancelled' ).checked ? '1' : '0' );
			formData.append( 'announcements', document.getElementById( 'dak-notify-announcements' ).checked ? '1' : '0' );

			fetch( window.dakNotifications.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						show( 'dak-notification-preferences-success', ( result.data && result.data.message ) || 'Saved.' );
						return;
					}

					show( 'dak-notification-preferences-error', ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					saveButton.disabled = false;
					show( 'dak-notification-preferences-error', 'Something went wrong. Please try again.' );
				} );
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
} )();
