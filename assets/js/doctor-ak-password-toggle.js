/**
 * Doctor AK Portal — Show/hide password toggle.
 *
 * Wires up any `.dak-password-toggle` button placed inside a
 * `.dak-password-field` wrapper to flip its sibling input between
 * type="password" and type="text".
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.dak-password-toggle' ).forEach( function ( button ) {
			var field = button.closest( '.dak-password-field' );
			var input = field ? field.querySelector( 'input' ) : null;

			if ( ! input ) {
				return;
			}

			button.addEventListener( 'click', function () {
				var isVisible = 'text' === input.type;

				input.type = isVisible ? 'password' : 'text';
				field.classList.toggle( 'is-visible', ! isVisible );
				button.setAttribute( 'aria-label', isVisible ? 'Show password' : 'Hide password' );
			} );
		} );
	} );
} )();
