/**
 * Dashboard → Reviews: the "Share a review link" card.
 *
 * Picking a doctor shows their link; "Copy link" copies it, "Share on
 * WhatsApp" opens WhatsApp with a ready message, and "Reset link" (a normal
 * form POST) asks first, since the old link stops working.
 */
( function () {
	'use strict';

	function init() {
		var card = document.querySelector( '[data-dak-review-share]' );

		if ( ! card ) {
			return;
		}

		var select   = card.querySelector( '[data-dak-review-share-doctor]' );
		var input    = card.querySelector( '[data-dak-review-share-link]' );
		var note     = card.querySelector( '[data-dak-review-share-note]' );
		var copyBtn  = card.querySelector( '[data-dak-review-copy]' );
		var whatsApp = card.querySelector( '[data-dak-review-whatsapp]' );
		var reset    = card.querySelector( '[data-dak-review-reset]' );
		var resetId  = card.querySelector( '[data-dak-review-reset-doctor]' );
		var status   = card.querySelector( '[data-dak-review-copy-status]' );

		if ( ! select || ! input ) {
			return;
		}

		var resetBtn = reset ? reset.querySelector( 'button[type="submit"]' ) : null;

		function current() {
			var option = select.options[ select.selectedIndex ];

			return {
				id: select.value,
				link: option ? option.getAttribute( 'data-link' ) || '' : '',
				name: option ? option.getAttribute( 'data-name' ) || '' : ''
			};
		}

		function update() {
			var doc = current();
			var has = '' !== doc.link;

			input.value = doc.link;

			if ( note ) {
				note.hidden = '' === doc.id || has;
			}

			if ( copyBtn ) {
				copyBtn.disabled = ! has;
			}

			if ( resetBtn ) {
				resetBtn.disabled = ! has;
			}

			if ( resetId ) {
				resetId.value = doc.id;
			}

			if ( whatsApp ) {
				var message = ( whatsApp.getAttribute( 'data-message' ) || '%2$s' ).replace( '%1$s', doc.name ).replace( '%2$s', doc.link );

				whatsApp.classList.toggle( 'is-disabled', ! has );
				whatsApp.setAttribute( 'aria-disabled', has ? 'false' : 'true' );
				whatsApp.href = has ? 'https://wa.me/?text=' + encodeURIComponent( message ) : '#';
			}

			if ( status ) {
				status.textContent = '';
			}
		}

		function copied( ok ) {
			if ( ! status ) {
				return;
			}

			status.textContent = status.getAttribute( ok ? 'data-copied' : 'data-copy-failed' ) || '';
		}

		select.addEventListener( 'change', update );

		input.addEventListener( 'focus', function () {
			input.select();
		} );

		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function () {
				var link = input.value;

				if ( ! link ) {
					return;
				}

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( link ).then( function () {
						copied( true );
					}, function () {
						input.select();
						copied( document.execCommand && document.execCommand( 'copy' ) );
					} );
					return;
				}

				input.focus();
				input.select();
				copied( !! ( document.execCommand && document.execCommand( 'copy' ) ) );
			} );
		}

		if ( whatsApp ) {
			whatsApp.addEventListener( 'click', function ( event ) {
				if ( whatsApp.classList.contains( 'is-disabled' ) ) {
					event.preventDefault();
				}
			} );
		}

		if ( reset ) {
			reset.addEventListener( 'submit', function ( event ) {
				if ( ! resetId || ! resetId.value || ! window.confirm( reset.getAttribute( 'data-confirm' ) || '' ) ) {
					event.preventDefault();
				}
			} );
		}

		update();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
