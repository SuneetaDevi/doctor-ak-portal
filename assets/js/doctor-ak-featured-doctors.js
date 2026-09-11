/**
 * Doctor AK Portal — Homepage doctors slider ([featured_doctors] shortcode).
 *
 * The track is a plain horizontal-scroll + scroll-snap container (see
 * doctor-ak-featured-doctors.css) — no external carousel library. The
 * prev/next buttons just scroll it by one slide's width, and disable
 * themselves at either end. It also autoplays (advancing on its own, then
 * looping back to the start once it reaches the end) — paused while a
 * visitor is hovering or has focus inside it, and skipped entirely for
 * prefers-reduced-motion. The "Video Consultation" pill (same pill/toggle
 * pattern as the doctors directory's own quick filters — see
 * doctor-ak-directory.js's initTogglePill()/applyFilters()) just hides
 * non-matching slides client-side; every card here (directory/
 * home-doctor-card.php, not the same partial the directory grid uses) still
 * carries its own data-search-video attribute for exactly this.
 */
( function () {
	'use strict';

	var AUTOPLAY_INTERVAL_MS = 4000;

	document.addEventListener( 'DOMContentLoaded', function () {
		var track = document.getElementById( 'dak-featured-doctors-track' );
		var prev = document.getElementById( 'dak-featured-doctors-prev' );
		var next = document.getElementById( 'dak-featured-doctors-next' );
		var slider = track ? track.closest( '.dak-featured-doctors-slider' ) : null;
		var videoToggle = document.getElementById( 'dak-featured-doctors-video-toggle' );
		var noResults = document.getElementById( 'dak-featured-doctors-no-results' );

		if ( ! track || ! prev || ! next || ! slider ) {
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

		function atEnd() {
			return track.scrollLeft >= track.scrollWidth - track.clientWidth - 4;
		}

		function updateNavState() {
			prev.disabled = track.scrollLeft <= 4;
			next.disabled = atEnd();

			track.classList.toggle( 'dak-fade-end', ! next.disabled );
		}

		function goToPrev() {
			track.scrollBy( { left: -slideWidth(), behavior: 'smooth' } );
		}

		function goToNext() {
			// Rewinds to the start once it can't advance a further whole
			// slide, rather than stalling there until a visitor scrolls it
			// back manually — that's what keeps autoplay "always moving".
			if ( atEnd() ) {
				track.scrollTo( { left: 0, behavior: 'smooth' } );
				return;
			}

			track.scrollBy( { left: slideWidth(), behavior: 'smooth' } );
		}

		prev.addEventListener( 'click', function () {
			goToPrev();
			restartAutoplay();
		} );

		next.addEventListener( 'click', function () {
			goToNext();
			restartAutoplay();
		} );

		track.addEventListener( 'scroll', updateNavState );
		window.addEventListener( 'resize', updateNavState );

		updateNavState();

		/**
		 * Hides slides whose card doesn't offer video consultation while the
		 * pill is active — same "hide the non-matching ones" idea as the
		 * directory's applyFilters(), just without the search/specialization/
		 * location filters this slider doesn't have controls for. Scrolls
		 * back to the start on every toggle so a previously-mid-scroll
		 * position doesn't land on a now-hidden slide, and re-syncs the nav
		 * buttons/autoplay since scrollWidth just changed.
		 */
		function applyVideoFilter() {
			var videoOnly = videoToggle ? videoToggle.classList.contains( 'is-active' ) : false;
			var visibleCount = 0;

			track.querySelectorAll( '.dak-featured-doctors-slide' ).forEach( function ( slide ) {
				var card = slide.querySelector( '[data-search-video]' );
				var matches = ! videoOnly || ( card && '1' === card.getAttribute( 'data-search-video' ) );

				slide.classList.toggle( 'dak-hidden', ! matches );

				if ( matches ) {
					visibleCount++;
				}
			} );

			if ( noResults ) {
				noResults.classList.toggle( 'dak-hidden', visibleCount > 0 );
			}

			track.scrollTo( { left: 0 } );
			updateNavState();
			restartAutoplay();
		}

		if ( videoToggle ) {
			videoToggle.addEventListener( 'click', function () {
				var isActive = ! videoToggle.classList.contains( 'is-active' );

				videoToggle.classList.toggle( 'is-active', isActive );
				videoToggle.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
				applyVideoFilter();
			} );
		}

		var prefersReducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var autoplayTimer = null;

		function startAutoplay() {
			if ( prefersReducedMotion || autoplayTimer ) {
				return;
			}

			var visibleSlideCount = track.querySelectorAll( '.dak-featured-doctors-slide:not(.dak-hidden)' ).length;

			if ( visibleSlideCount < 2 ) {
				return;
			}

			autoplayTimer = window.setInterval( goToNext, AUTOPLAY_INTERVAL_MS );
		}

		function stopAutoplay() {
			if ( autoplayTimer ) {
				window.clearInterval( autoplayTimer );
				autoplayTimer = null;
			}
		}

		function restartAutoplay() {
			stopAutoplay();
			startAutoplay();
		}

		slider.addEventListener( 'mouseenter', stopAutoplay );
		slider.addEventListener( 'mouseleave', startAutoplay );
		slider.addEventListener( 'focusin', stopAutoplay );
		slider.addEventListener( 'focusout', startAutoplay );

		startAutoplay();
	} );
} )();
