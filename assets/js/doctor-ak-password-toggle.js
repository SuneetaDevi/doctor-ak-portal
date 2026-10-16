/**
 * Doctor AK Portal — Show/hide password toggle.
 *
 * Wires up any `.dak-password-toggle` button placed inside a
 * `.dak-password-field` wrapper to flip its sibling input between
 * type="password" and type="text". The button is a real <button>, so it
 * works with Enter/Space; its label always says what it will do next
 * ("Show password" / "Hide password") and aria-pressed reflects the state.
 * Paste and password managers are unaffected.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.dak-password-toggle' ).forEach( function ( button ) {
			var field = button.closest( '.dak-password-field' );
			var input = field ? field.querySelector( 'input' ) : null;
			var text = button.querySelector( '.dak-password-toggle-text' );

			if ( ! input ) {
				return;
			}

			function sync() {
				var isVisible = 'text' === input.type;

				field.classList.toggle( 'is-visible', isVisible );
				button.setAttribute( 'aria-label', isVisible ? 'Hide password' : 'Show password' );
				button.setAttribute( 'aria-pressed', isVisible ? 'true' : 'false' );

				if ( text ) {
					text.textContent = isVisible ? 'Hide' : 'Show';
				}
			}

			button.addEventListener( 'click', function () {
				input.type = 'text' === input.type ? 'password' : 'text';
				sync();
			} );

			sync();
		} );
	} );
} )();
