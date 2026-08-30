/**
 * Doctor AK Portal — Home page ([dak_home]): opens the video grid's
 * lightbox modal when a `[data-dak-home-video]` card is clicked.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-home-video-modal' );

		if ( ! modal ) {
			return;
		}

		var overlay = document.getElementById( 'dak-home-video-modal-overlay' );
		var closeButton = document.getElementById( 'dak-home-video-modal-close' );
		var player = document.getElementById( 'dak-home-video-modal-player' );
		var titleEl = document.getElementById( 'dak-home-video-modal-title' );

		document.querySelectorAll( '[data-dak-home-video]' ).forEach( function ( card ) {
			card.addEventListener( 'click', function () {
				openModal( card.getAttribute( 'data-video-url' ), card.getAttribute( 'data-video-title' ) );
			} );
		} );

		overlay.addEventListener( 'click', closeModal );
		closeButton.addEventListener( 'click', closeModal );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! modal.hasAttribute( 'aria-hidden' ) ) {
				closeModal();
			}
		} );

		function openModal( videoUrl, title ) {
			if ( ! videoUrl ) {
				return;
			}

			player.src = videoUrl;
			modal.removeAttribute( 'aria-hidden' );
			modal.classList.add( 'is-open' );

			if ( title ) {
				titleEl.textContent = title;
				titleEl.classList.remove( 'dak-hidden' );
			} else {
				titleEl.classList.add( 'dak-hidden' );
			}

			player.play().catch( function () {
				// Autoplay can be blocked by the browser — the visible
				// controls let the visitor start playback manually.
			} );
		}

		function closeModal() {
			modal.setAttribute( 'aria-hidden', 'true' );
			modal.classList.remove( 'is-open' );
			player.pause();
			player.removeAttribute( 'src' );
			player.load();
		}
	} );
} )();
