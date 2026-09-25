/**
 * Doctor AK Portal — Blog page ([blogs_directory]): the topic filter chips.
 * Clicking a chip shows only the posts whose data-topic matches it ("All
 * topics" shows everything); the cards are already on the page, so this is a
 * plain show/hide with no request.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var chips = document.querySelectorAll( '[data-topic-filter]' );
		var cards = document.querySelectorAll( '.dak-blog-card' );

		if ( ! chips.length || ! cards.length ) {
			return;
		}

		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				var topic = chip.getAttribute( 'data-topic-filter' );

				chips.forEach( function ( other ) {
					var isActive = other === chip;

					other.classList.toggle( 'is-active', isActive );
					other.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
				} );

				cards.forEach( function ( card ) {
					card.classList.toggle( 'dak-hidden', '' !== topic && card.getAttribute( 'data-topic' ) !== topic );
				} );
			} );
		} );
	} );
} )();
