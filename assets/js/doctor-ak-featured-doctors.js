/**
 * Doctor AK Portal — Homepage doctors slider ([featured_doctors] shortcode).
 *
 * The track is a plain horizontal-scroll + scroll-snap container (see
 * doctor-ak-featured-doctors.css) — no external carousel library. The
 * prev/next buttons just scroll it by one slide's width, and disable
 * themselves at either end.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var track = document.getElementById( 'dak-featured-doctors-track' );
		var prev = document.getElementById( 'dak-featured-doctors-prev' );
		var next = document.getElementById( 'dak-featured-doctors-next' );

		if ( ! track || ! prev || ! next ) {
			return;
		}

		function slideWidth() {
			var slide = track.querySelector( '.dak-featured-doctors-slide' );

			if ( ! slide ) {
				return track.clientWidth;
			}

			var trackGap = parseFloat( window.getComputedStyle( track ).columnGap || '0' );

			return slide.getBoundingClientRect().width + trackGap;
		}

		function updateNavState() {
			var maxScroll = track.scrollWidth - track.clientWidth;

			prev.disabled = track.scrollLeft <= 4;
			next.disabled = track.scrollLeft >= maxScroll - 4;
		}

		prev.addEventListener( 'click', function () {
			track.scrollBy( { left: -slideWidth(), behavior: 'smooth' } );
		} );

		next.addEventListener( 'click', function () {
			track.scrollBy( { left: slideWidth(), behavior: 'smooth' } );
		} );

		track.addEventListener( 'scroll', updateNavState );
		window.addEventListener( 'resize', updateNavState );

		updateNavState();
	} );
} )();
