/**
 * Doctor AK Portal — Admin dashboard "Settings" page: single "Save Settings"
 * button that saves Clinic Branding and Notification Preferences together
 * (each card's own individual save button is suppressed on this page — see
 * admin-settings-section.php / dashboard-settings-tab.php's
 * `show_save_button` — in favor of this one combined action).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var saveButton = document.getElementById( 'dak-admin-settings-save' );

		if ( ! saveButton || ! window.dakClinicBranding || ! window.dakNotifications ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			[ 'dak-clinic-branding-error', 'dak-notification-preferences-error', 'dak-platform-fee-error', 'dak-admin-settings-error', 'dak-admin-settings-success' ].forEach( hide );
			saveButton.disabled = true;

			var requests = [ saveBranding(), savePreferences() ];

			if ( window.dakPlatformFee ) {
				requests.push( savePlatformFee() );
			}

			Promise.all( requests )
				.then( function ( results ) {
					saveButton.disabled = false;

					var failed = results.filter( function ( result ) {
						return ! result.success;
					} );

					if ( 0 === failed.length ) {
						show( 'dak-admin-settings-success', 'Settings saved.' );
						return;
					}

					failed.forEach( function ( result ) {
						show( result.errorElementId, ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
					} );
				} );
		} );

		/**
		 * @return {Promise<Object>} Resolves with the parsed JSON response
		 *   (tagged with errorElementId), never rejects — a network failure
		 *   is reported the same way as a server-side error so Promise.all()
		 *   always waits for both requests to finish.
		 */
		function saveBranding() {
			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_clinic_branding_save' );
			formData.append( 'nonce', window.dakClinicBranding.nonce );
			formData.append( 'clinic_name', valueOf( 'dak-clinic-branding-name' ) );
			formData.append( 'clinic_phone', valueOf( 'dak-clinic-branding-phone' ) );
			formData.append( 'clinic_address', valueOf( 'dak-clinic-branding-address' ) );

			return fetch( window.dakClinicBranding.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					result.errorElementId = 'dak-clinic-branding-error';
					return result;
				} )
				.catch( function () {
					return { success: false, errorElementId: 'dak-clinic-branding-error' };
				} );
		}

		/**
		 * @return {Promise<Object>} See saveBranding().
		 */
		function savePreferences() {
			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_notification_preferences_save' );
			formData.append( 'nonce', window.dakNotifications.nonce );
			formData.append( 'booking', checkedOf( 'dak-notify-booking' ) );
			formData.append( 'paid', checkedOf( 'dak-notify-paid' ) );
			formData.append( 'cancelled', checkedOf( 'dak-notify-cancelled' ) );
			formData.append( 'announcements', checkedOf( 'dak-notify-announcements' ) );

			return fetch( window.dakNotifications.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					result.errorElementId = 'dak-notification-preferences-error';
					return result;
				} )
				.catch( function () {
					return { success: false, errorElementId: 'dak-notification-preferences-error' };
				} );
		}

		/**
		 * @return {Promise<Object>} See saveBranding().
		 */
		function savePlatformFee() {
			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_platform_fee_save' );
			formData.append( 'nonce', window.dakPlatformFee.nonce );
			formData.append( 'fee_percent', valueOf( 'dak-platform-fee-percent' ) );
			formData.append( 'fee_flat', valueOf( 'dak-platform-fee-flat' ) );

			return fetch( window.dakPlatformFee.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					result.errorElementId = 'dak-platform-fee-error';
					return result;
				} )
				.catch( function () {
					return { success: false, errorElementId: 'dak-platform-fee-error' };
				} );
		}

		function valueOf( id ) {
			var el = document.getElementById( id );
			return el ? el.value : '';
		}

		function checkedOf( id ) {
			var el = document.getElementById( id );
			return el && el.checked ? '1' : '0';
		}

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
