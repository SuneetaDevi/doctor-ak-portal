/**
 * Doctor AK Portal — patient portal's floating "Join call" card.
 *
 * Reads the patient's upcoming video consultations from the dock's
 * data-calls (Appointments::upcoming_video_calls_for_patient(): seconds
 * until each call's join window opens / closes / the appointment starts,
 * measured on the server when the page was built) and shows the card on
 * every portal tab while a window is open — including on a page that was
 * left open since before then — and hides it when the window ends. The
 * patient can shrink it to a small "Join call" button; that choice is
 * remembered for that appointment for the rest of the browser session.
 *
 * Joining uses the shared [data-join-video-call] handler
 * (doctor-ak-video-call.js), which opens the call in a new tab.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var dock = document.querySelector( '[data-call-dock]' );

		if ( ! dock ) {
			return;
		}

		var calls = [];

		try {
			calls = JSON.parse( dock.getAttribute( 'data-calls' ) || '[]' );
		} catch ( e ) {
			calls = [];
		}

		if ( ! calls.length ) {
			return;
		}

		var loadedAt = Date.now();
		var card = dock.querySelector( '[data-call-dock-card]' );
		var pill = dock.querySelector( '[data-call-dock-expand]' );
		var status = dock.querySelector( '[data-call-dock-status]' );
		var detail = dock.querySelector( '[data-call-dock-detail]' );
		var join = dock.querySelector( '[data-call-dock-join]' );
		var shownId = null;

		function storageKey( id ) {
			return 'dakCallDockMin:' + id;
		}

		function isMinimized( id ) {
			try {
				return '1' === window.sessionStorage.getItem( storageKey( id ) );
			} catch ( e ) {
				return false;
			}
		}

		function setMinimized( id, value ) {
			try {
				if ( value ) {
					window.sessionStorage.setItem( storageKey( id ), '1' );
				} else {
					window.sessionStorage.removeItem( storageKey( id ) );
				}
			} catch ( e ) {
				// Private mode etc.: just not remembered.
			}
		}

		/** Seconds since the page was built on the server. */
		function elapsed() {
			return Math.floor( ( Date.now() - loadedAt ) / 1000 );
		}

		/** The call whose window is open now (soonest start), or null. */
		function openCall() {
			var t = elapsed();

			return calls.filter( function ( call ) {
				return call.opensIn - t <= 0 && call.closesIn - t > 0;
			} )[ 0 ] || null;
		}

		function statusText( call ) {
			var minutes = Math.round( ( call.startsIn - elapsed() ) / 60 );

			if ( minutes > 1 ) {
				return 'Your video consultation starts in ' + minutes + ' minutes';
			}

			if ( minutes >= -1 ) {
				return 'Your video consultation is starting now';
			}

			return 'Your video consultation is in progress';
		}

		function showMinimized( minimized ) {
			card.hidden = minimized;
			pill.hidden = ! minimized;
		}

		function render() {
			var call = openCall();

			if ( ! call ) {
				dock.hidden = true;
				document.body.classList.remove( 'dak-has-call-dock' );
				shownId = null;
				return;
			}

			status.textContent = statusText( call );
			detail.textContent = 'with ' + call.doctor + ' · ' + call.time;
			join.setAttribute( 'data-room-url', call.roomUrl );
			join.setAttribute( 'aria-label', 'Join video consultation with ' + call.doctor );

			if ( shownId !== call.id ) {
				shownId = call.id;
				showMinimized( isMinimized( call.id ) );
			}

			dock.hidden = false;
			document.body.classList.add( 'dak-has-call-dock' );
		}

		dock.querySelector( '[data-call-dock-minimize]' ).addEventListener( 'click', function () {
			if ( null !== shownId ) {
				setMinimized( shownId, true );
			}

			showMinimized( true );
			pill.focus();
		} );

		pill.addEventListener( 'click', function () {
			if ( null !== shownId ) {
				setMinimized( shownId, false );
			}

			showMinimized( false );
			join.focus();
		} );

		render();
		// Re-check every 20 seconds: windows open and close, and the
		// "starts in" text counts down. Browsers slow timers in background
		// tabs, so also re-check as soon as the tab is looked at again.
		window.setInterval( render, 20000 );
		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden ) {
				render();
			}
		} );
	} );
}() );
