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
 *
 * A track with `data-loop` (the home page's) becomes an endless loop: its
 * slides are cloned once, and whenever the view reaches the clones it jumps
 * (instantly, invisibly — they're identical) back onto the originals. So it
 * keeps moving the same way forever — after the last doctor the first one
 * follows — instead of rewinding backwards.
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

		var loop = track.hasAttribute( 'data-loop' );
		var loopWidth = 0;
		var firstSlide = null;
		var firstClone = null;
		var normaliseTimer = null;

		// Appends one identical, inert copy of every slide, so scrolling past
		// the last original just runs on into the first doctor again.
		function setupLoop() {
			if ( ! loop ) {
				return;
			}

			var slides = Array.prototype.slice.call( track.querySelectorAll( '.dak-featured-doctors-slide' ) );

			if ( slides.length < 2 || track.scrollWidth <= track.clientWidth + 4 ) {
				loop = false;
				return;
			}

			firstSlide = slides[ 0 ];

			slides.forEach( function ( slide, index ) {
				var clone = slide.cloneNode( true );

				clone.setAttribute( 'aria-hidden', 'true' );
				clone.querySelectorAll( 'a, button' ).forEach( function ( el ) {
					el.tabIndex = -1;
				} );
				track.appendChild( clone );

				if ( 0 === index ) {
					firstClone = clone;
				}
			} );

			measureLoop();
		}

		function measureLoop() {
			if ( loop && firstSlide && firstClone ) {
				loopWidth = firstClone.offsetLeft - firstSlide.offsetLeft;
			}
		}

		// Once the view has run into the clones, drop back onto the
		// originals — the same picture, so nobody sees it happen.
		function normalise() {
			if ( loop && loopWidth && track.scrollLeft >= loopWidth - 2 ) {
				track.scrollTo( { left: track.scrollLeft - loopWidth, behavior: 'instant' } );
			}
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
			if ( loop ) {
				return false;
			}

			return track.scrollLeft >= track.scrollWidth - track.clientWidth - 4;
		}

		var dotsEl = document.getElementById( 'dak-featured-doctors-dots' );
		var pageCount = 0;

		// Optional pager dots (the home page's doctors section): one per
		// screenful of slides, the current one tracking the scroll position;
		// clicking one jumps there.
		function buildDots() {
			if ( ! dotsEl ) {
				return;
			}

			var maxScroll = track.scrollWidth - track.clientWidth;

			if ( loop && loopWidth ) {
				pageCount = Math.ceil( loopWidth / track.clientWidth );
			} else {
				pageCount = maxScroll <= 4 ? 0 : Math.ceil( track.scrollWidth / track.clientWidth );
			}
			dotsEl.innerHTML = '';

			for ( var i = 0; i < pageCount; i++ ) {
				( function ( index ) {
					var dot = document.createElement( 'button' );
					dot.type = 'button';
					dot.className = 'dak-home-doctors-dot';
					dot.tabIndex = -1;
					dot.addEventListener( 'click', function () {
						var max = track.scrollWidth - track.clientWidth;
						var target = loop && loopWidth ? loopWidth * ( index / pageCount ) : ( pageCount > 1 ? max * ( index / ( pageCount - 1 ) ) : 0 );

						normalise();
						track.scrollTo( { left: target, behavior: 'smooth' } );
						restartAutoplay();
					} );
					dotsEl.appendChild( dot );
				}( i ) );
			}
		}

		function updateDots() {
			if ( ! dotsEl || ! pageCount ) {
				return;
			}

			var maxScroll = Math.max( 1, track.scrollWidth - track.clientWidth );
			var active = pageCount > 1 ? Math.round( ( track.scrollLeft / maxScroll ) * ( pageCount - 1 ) ) : 0;

			if ( loop && loopWidth ) {
				active = Math.round( ( ( track.scrollLeft % loopWidth ) / loopWidth ) * pageCount ) % pageCount;
			}

			Array.prototype.forEach.call( dotsEl.children, function ( dot, index ) {
				dot.classList.toggle( 'is-active', index === active );
			} );
		}

		function updateNavState() {
			prev.disabled = ! loop && track.scrollLeft <= 4;
			next.disabled = atEnd();

			track.classList.toggle( 'dak-fade-end', ! next.disabled );
			updateDots();
		}

		function goToPrev() {
			if ( loop && track.scrollLeft < 4 ) {
				track.scrollTo( { left: loopWidth, behavior: 'instant' } );
			}

			track.scrollBy( { left: -slideWidth(), behavior: 'smooth' } );
		}

		function goToNext() {
			normalise();

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

		track.addEventListener( 'scroll', function () {
			updateNavState();

			if ( loop ) {
				window.clearTimeout( normaliseTimer );
				normaliseTimer = window.setTimeout( normalise, 150 );
			}
		} );
		window.addEventListener( 'resize', function () {
			measureLoop();
			buildDots();
			updateNavState();
		} );

		setupLoop();
		buildDots();
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
			buildDots();
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
