/**
 * Doctor AK Portal — "Notifications" tab, shared by the doctor, patient, and
 * admin dashboards.
 *
 * Clicking an unread notification marks it read in place (no reload);
 * "Mark all as read" does the same for every row at once, via
 * Notification_Handler's AJAX endpoints (doctor_ak_notification_mark_read /
 * doctor_ak_notification_mark_all_read).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakNotifications ) {
			return;
		}

		wireRowClick();
		wireMarkAllRead();
	} );

	function wireRowClick() {
		document.addEventListener( 'click', function ( event ) {
			var row = event.target.closest( '[data-mark-read]' );

			if ( ! row ) {
				return;
			}

			markRead( row.getAttribute( 'data-notification-id' ), function () {
				row.classList.remove( 'is-unread' );
				row.removeAttribute( 'data-mark-read' );

				var dot = row.querySelector( '.dak-notification-dot' );

				if ( dot ) {
					dot.remove();
				}

				updateBadges( -1 );
			} );
		} );
	}

	function wireMarkAllRead() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '#dak-notifications-mark-all-read' );

			if ( ! trigger ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_notification_mark_all_read' );
			formData.append( 'nonce', window.dakNotifications.nonce );

			fetch( window.dakNotifications.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function () {
					window.location.reload();
				} )
				.catch( function () {
					trigger.disabled = false;
				} );
		} );
	}

	function markRead( notificationId, onSuccess ) {
		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_notification_mark_read' );
		formData.append( 'nonce', window.dakNotifications.nonce );
		formData.append( 'notification_id', notificationId );

		fetch( window.dakNotifications.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( result.success ) {
					onSuccess();
				}
			} );
	}

	/**
	 * Updates the sidebar's Notifications unread-count badge specifically —
	 * scoped by ID so it never touches unrelated nav badges (e.g. the
	 * Appointments count).
	 *
	 * @param {number} delta Amount to change the badge count by.
	 */
	function updateBadges( delta ) {
		// Every unread badge (sidebar + topbar bell) carries the real count
		// in data-unread-count — its visible text is capped at "99+" (see
		// templates/dashboard/partials/unread-badge.php), so parsing the
		// text would undercount. The visually hidden label right after
		// each badge is what screen readers hear; keep it in step too.
		var badges = document.querySelectorAll( '[data-unread-count]' );

		badges.forEach( function ( badge ) {
			var current = parseInt( badge.getAttribute( 'data-unread-count' ), 10 );
			var count = Math.max( 0, ( isNaN( current ) ? parseInt( badge.textContent, 10 ) || 0 : current ) + delta );
			var label = badge.nextElementSibling && badge.nextElementSibling.hasAttribute( 'data-unread-label' ) ? badge.nextElementSibling : null;

			if ( 0 === count ) {
				badge.remove();

				if ( label ) {
					label.remove();
				}

				return;
			}

			badge.setAttribute( 'data-unread-count', String( count ) );
			badge.textContent = count > 99 ? '99+' : String( count );

			if ( label ) {
				label.textContent = '(' + count + ' unread)';
			}
		} );
	}
} )();
