/**
 * Doctor AK Portal — In-page video call modal.
 *
 * Any element carrying `data-join-video-call` + `data-room-url="..."`
 * (see templates/dashboard/partials/patient-appointment-row.php and
 * doctor-appointment-row.php) opens the Jitsi Meet room in an iframe inside
 * this page instead of a new browser tab. The modal is built once, lazily,
 * on first use — nothing is added to the DOM (and no iframe is created,
 * so no camera/mic permission prompt fires) until a "Join Call"/"Start
 * Call" button is actually clicked.
 *
 * Jitsi's public meet.jit.si server sometimes requires whoever starts a
 * call to log in via a third-party account (Google, GitHub, ...) — those
 * providers' login pages refuse to load inside an iframe (their own
 * anti-clickjacking policy, not something this plugin can change). So the
 * modal always shows a visible "Open in a new tab instead" link alongside
 * the iframe as an escape hatch, in case that login step gets stuck.
 */
( function () {
	'use strict';

	var modal       = null;
	var iframe       = null;
	var fallbackLink = null;

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

			openModal( roomUrl );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal && modal.classList.contains( 'is-open' ) ) {
				closeModal();
			}
		} );
	} );

	function ensureModal() {
		if ( modal ) {
			return;
		}

		modal = document.createElement( 'div' );
		modal.className = 'dak-video-call-modal';
		modal.setAttribute( 'aria-hidden', 'true' );

		var overlay = document.createElement( 'div' );
		overlay.className = 'dak-video-call-modal-overlay';
		overlay.addEventListener( 'click', closeModal );

		var dialog = document.createElement( 'div' );
		dialog.className = 'dak-video-call-modal-dialog';
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );

		var header = document.createElement( 'div' );
		header.className = 'dak-video-call-modal-header';

		fallbackLink = document.createElement( 'a' );
		fallbackLink.className = 'dak-link';
		fallbackLink.target = '_blank';
		fallbackLink.rel = 'noopener';
		fallbackLink.textContent = ( window.dakVideoCall && window.dakVideoCall.newTabLabel ) || 'Trouble joining? Open in a new tab instead';

		var closeButton = document.createElement( 'button' );
		closeButton.type = 'button';
		closeButton.className = 'dak-video-call-modal-close';
		closeButton.setAttribute( 'aria-label', 'Close' );
		closeButton.innerHTML = '&times;';
		closeButton.addEventListener( 'click', closeModal );

		header.appendChild( fallbackLink );
		header.appendChild( closeButton );

		iframe = document.createElement( 'iframe' );
		iframe.className = 'dak-video-call-modal-iframe';
		iframe.setAttribute( 'allow', 'camera; microphone; fullscreen; display-capture; autoplay; clipboard-write' );
		iframe.setAttribute( 'allowfullscreen', 'true' );

		dialog.appendChild( header );
		dialog.appendChild( iframe );

		modal.appendChild( overlay );
		modal.appendChild( dialog );
		document.body.appendChild( modal );
	}

	function openModal( roomUrl ) {
		ensureModal();

		fallbackLink.href = roomUrl;
		iframe.src        = roomUrl;

		modal.classList.add( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'dak-modal-open' );
	}

	function closeModal() {
		if ( ! modal ) {
			return;
		}

		modal.classList.remove( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'dak-modal-open' );

		// Tears down the Jitsi session and releases the camera/mic instead
		// of leaving it connected in a hidden iframe.
		iframe.src = 'about:blank';
	}
} )();
