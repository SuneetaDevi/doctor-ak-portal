/**
 * Doctor AK Portal — Join/Start video call.
 *
 * Any element carrying `data-join-video-call` + `data-room-url="..."` (the
 * Join/Start call buttons in the admin, receptionist, doctor and patient
 * dashboards) opens the Jitsi Meet room in a NEW browser tab, so the
 * dashboard stays open behind the call. A call in its own tab also avoids
 * the embedded-frame problems Jitsi has (its sign-in step for whoever
 * starts the call, e.g. Google, refuses to load inside an iframe).
 *
 * The tab is opened straight from the click, which browsers allow. If a
 * strict popup blocker still stops it, a small notice with a link appears
 * instead — the call never replaces the dashboard page.
 */
( function () {
	'use strict';

	var notice = null;

	document.addEventListener( 'DOMContentLoaded', function () {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-join-video-call]' );

			if ( ! trigger ) {
				return;
			}

			event.preventDefault();

			var roomUrl = trigger.getAttribute( 'data-room-url' );

			if ( ! roomUrl ) {
				return;
			}

			openCallTab( roomUrl );
		} );
	} );

	/**
	 * Opens the call in a new tab, detached from this page.
	 *
	 * @param {string} roomUrl The Jitsi Meet room URL.
	 */
	function openCallTab( roomUrl ) {
		var callTab = window.open( roomUrl, '_blank' );

		if ( callTab ) {
			// The call page gets no handle back to the dashboard.
			try {
				callTab.opener = null;
			} catch ( e ) {
				// Cross-origin already; nothing to detach.
			}

			hideNotice();
			return;
		}

		showNotice( roomUrl );
	}

	/**
	 * Popup blocked: a dismissible notice with a plain link the user can
	 * click to open the call in a new tab themselves.
	 *
	 * @param {string} roomUrl The Jitsi Meet room URL.
	 */
	function showNotice( roomUrl ) {
		if ( ! notice ) {
			notice = document.createElement( 'div' );
			notice.className = 'dak-video-call-notice';
			notice.setAttribute( 'role', 'alert' );

			var text = document.createElement( 'span' );
			text.textContent = 'Your browser blocked the call window. ';

			var link = document.createElement( 'a' );
			link.className = 'dak-video-call-notice-link';
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = 'Open the video call';
			link.addEventListener( 'click', hideNotice );

			var close = document.createElement( 'button' );
			close.type = 'button';
			close.className = 'dak-video-call-notice-close';
			close.setAttribute( 'aria-label', 'Dismiss' );
			close.innerHTML = '&times;';
			close.addEventListener( 'click', hideNotice );

			notice.appendChild( text );
			notice.appendChild( link );
			notice.appendChild( close );
			document.body.appendChild( notice );
		}

		notice.querySelector( '.dak-video-call-notice-link' ).href = roomUrl;
		notice.hidden = false;
		notice.querySelector( '.dak-video-call-notice-link' ).focus();
	}

	function hideNotice() {
		if ( notice ) {
			notice.hidden = true;
		}
	}
} )();
