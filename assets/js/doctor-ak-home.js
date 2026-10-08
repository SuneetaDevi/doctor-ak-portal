/**
 * Doctor AK Portal — Home page ([dak_home]):
 *  - opens the video grid's lightbox modal when a `[data-dak-home-video]`
 *    card is clicked.
 *  - the hero search bar: clicking it opens a "Search everything" popup
 *    (matching the reference design) with a Location row (auto-detected via
 *    the browser's Geolocation API, with a manual "Detect" fallback and a
 *    quick-pick list of the clinic's registered cities) and a free-text
 *    search box that lists matching doctors, services, specialities, and
 *    clinics live, right in the popup, as you type — one grouped-by-category
 *    result list per keystroke, built from window.dakHomeSearch
 *    (wp_localize_script(), see Home_Page::render()), which carries the
 *    full list of each. Enter never submits/navigates from here; picking a
 *    result (or the Search button, for a full directory search with the
 *    typed query/city applied) are the only ways this popup sends you
 *    anywhere.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initVideoModal();
		// Builds the carousel's loop clones before the gallery's
		// IntersectionObserver below queries every [data-gallery-video], so
		// the cloned cards' <video>s get the same scroll-into-view autoplay
		// as the originals.
		initVideoCarousel();
		initVideoGallery();
		initHeroSearch();
	} );

	/**
	 * The video reel's carousel controls — Prev/Next buttons and a dot pager
	 * around the existing scroll-snap track (see .dak-home-videos-grid),
	 * mirroring the homepage doctors slider's own loop/autoplay behavior
	 * (see doctor-ak-featured-doctors.js) rather than a second dependency:
	 * the track's `data-loop` clones every card once so scrolling past the
	 * last one keeps going into the first again, Prev/Next step by exactly
	 * one card, dots jump to a page, and it all autoplays — paused while a
	 * visitor is hovering or has focus inside it, skipped for
	 * prefers-reduced-motion.
	 */
	function initVideoCarousel() {
		var track = document.getElementById( 'dak-home-videos-track' );
		var prev = document.getElementById( 'dak-home-videos-prev' );
		var next = document.getElementById( 'dak-home-videos-next' );
		var slider = track ? track.closest( '.dak-home-videos-slider' ) : null;
		var dotsEl = document.getElementById( 'dak-home-videos-dots' );

		if ( ! track || ! prev || ! next || ! slider ) {
			return;
		}

		var AUTOPLAY_INTERVAL_MS = 4000;
		var loop = track.hasAttribute( 'data-loop' );
		var loopWidth = 0;
		var firstCard = null;
		var firstClone = null;
		var normaliseTimer = null;
		var pageCount = 0;

		// Appends one identical, inert copy of every card, so scrolling past
		// the last original just runs on into the first video again.
		function setupLoop() {
			if ( ! loop ) {
				return;
			}

			var cards = Array.prototype.slice.call( track.querySelectorAll( '.dak-home-video-card' ) );

			if ( cards.length < 2 || track.scrollWidth <= track.clientWidth + 4 ) {
				loop = false;
				return;
			}

			firstCard = cards[ 0 ];

			cards.forEach( function ( card, index ) {
				var clone = card.cloneNode( true );

				clone.setAttribute( 'aria-hidden', 'true' );
				clone.tabIndex = -1;
				track.appendChild( clone );

				if ( 0 === index ) {
					firstClone = clone;
				}
			} );

			measureLoop();
		}

		function measureLoop() {
			if ( loop && firstCard && firstClone ) {
				loopWidth = firstClone.offsetLeft - firstCard.offsetLeft;
			}
		}

		// Once the view has run into the clones, drop back onto the
		// originals — the same picture, so nobody sees it happen.
		function normalise() {
			if ( loop && loopWidth && track.scrollLeft >= loopWidth - 2 ) {
				track.scrollTo( { left: track.scrollLeft - loopWidth, behavior: 'instant' } );
			}
		}

		function cardWidth() {
			var card = track.querySelector( '.dak-home-video-card' );

			if ( ! card ) {
				return track.clientWidth;
			}

			var trackGap = parseFloat( window.getComputedStyle( track ).columnGap || '0' );

			return card.getBoundingClientRect().width + trackGap;
		}

		function atEnd() {
			if ( loop ) {
				return false;
			}

			return track.scrollLeft >= track.scrollWidth - track.clientWidth - 4;
		}

		// Decorative pager dots (the container itself is aria-hidden, same
		// as the doctors slider's) — one per screenful, current one tracking
		// scroll position, clicking one jumps there.
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
					dot.className = 'dak-home-videos-dot';
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
			updateDots();
		}

		function goToPrev() {
			if ( loop && track.scrollLeft < 4 ) {
				track.scrollTo( { left: loopWidth, behavior: 'instant' } );
			}

			track.scrollBy( { left: -cardWidth(), behavior: 'smooth' } );
		}

		function goToNext() {
			normalise();

			// Rewinds to the start once it can't advance a further whole
			// card, rather than stalling there until a visitor scrolls it
			// back manually — that's what keeps autoplay "always moving".
			if ( atEnd() ) {
				track.scrollTo( { left: 0, behavior: 'smooth' } );
				return;
			}

			track.scrollBy( { left: cardWidth(), behavior: 'smooth' } );
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

		var prefersReducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var autoplayTimer = null;

		function startAutoplay() {
			if ( prefersReducedMotion || autoplayTimer || pageCount < 2 ) {
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

		// Only on devices with a real hover: a phone fires a synthetic
		// mouseenter on tap but never a mouseleave, which would stop the
		// autoplay for good after the first touch.
		if ( window.matchMedia && window.matchMedia( '(hover: hover)' ).matches ) {
			slider.addEventListener( 'mouseenter', stopAutoplay );
			slider.addEventListener( 'mouseleave', startAutoplay );
		}
		slider.addEventListener( 'focusin', stopAutoplay );
		slider.addEventListener( 'focusout', startAutoplay );

		startAutoplay();
	}

	/**
	 * The video gallery's clips play by themselves (muted, looping) — but only
	 * while they're actually on screen, so a page with several 10 MB clips isn't
	 * decoding all of them at once, and only starts downloading them as the
	 * visitor scrolls near. Clicking one still opens the lightbox (with sound).
	 * Visitors who ask for reduced motion get still frames instead.
	 */
	function initVideoGallery() {
		var videos = document.querySelectorAll( '[data-gallery-video]' );

		if ( ! videos.length ) {
			return;
		}

		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}

		function play( video ) {
			var promise = video.play();

			if ( promise && promise.catch ) {
				promise.catch( function () {
					// Autoplay blocked — the still first frame and the click-to-watch chip remain.
				} );
			}
		}

		if ( ! ( 'IntersectionObserver' in window ) ) {
			videos.forEach( play );
			return;
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					play( entry.target );
				} else {
					entry.target.pause();
				}
			} );
		}, { threshold: 0.35 } );

		videos.forEach( function ( video ) {
			observer.observe( video );
		} );
	}

	function initVideoModal() {
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
	}

	// Approximate city-centre coordinates for Pakistan's major cities — just
	// enough to find "the nearest one of these to the visitor" from the
	// Geolocation API's lat/lng. Deliberately not tied to the plugin's own
	// registered clinic cities (that list is admin-managed and can be tiny),
	// so a visitor in, say, Multan still resolves to "Multan" even if this
	// clinic has no Multan location yet — applyNearestCity() in
	// initHeroSearch() is what then narrows that down to a city actually
	// offered in the popup's quick-pick list.
	var PK_CITIES = [
		{ name: 'Karachi', lat: 24.8607, lng: 67.0011 },
		{ name: 'Lahore', lat: 31.5497, lng: 74.3436 },
		{ name: 'Islamabad', lat: 33.6844, lng: 73.0479 },
		{ name: 'Rawalpindi', lat: 33.5651, lng: 73.0169 },
		{ name: 'Faisalabad', lat: 31.4504, lng: 73.1350 },
		{ name: 'Multan', lat: 30.1575, lng: 71.5249 },
		{ name: 'Peshawar', lat: 34.0151, lng: 71.5249 },
		{ name: 'Quetta', lat: 30.1798, lng: 66.9750 },
		{ name: 'Hyderabad', lat: 25.3960, lng: 68.3578 },
		{ name: 'Sialkot', lat: 32.4945, lng: 74.5229 },
		{ name: 'Gujranwala', lat: 32.1877, lng: 74.1945 },
		{ name: 'Sukkur', lat: 27.7052, lng: 68.8574 },
		{ name: 'Bahawalpur', lat: 29.3956, lng: 71.6836 },
		{ name: 'Sargodha', lat: 32.0836, lng: 72.6711 },
		{ name: 'Abbottabad', lat: 34.1463, lng: 73.2117 },
		{ name: 'Mardan', lat: 34.1986, lng: 72.0404 },
		{ name: 'Sahiwal', lat: 30.6682, lng: 73.1114 },
		{ name: 'Larkana', lat: 27.5590, lng: 68.2120 },
		{ name: 'Gujrat', lat: 32.5740, lng: 74.0789 },
		{ name: 'Rahim Yar Khan', lat: 28.4202, lng: 70.2952 },
		{ name: 'Sheikhupura', lat: 31.7130, lng: 73.9783 },
		{ name: 'Jhang', lat: 31.2781, lng: 72.3317 },
		{ name: 'Dera Ghazi Khan', lat: 30.0561, lng: 70.6345 },
		{ name: 'Nawabshah', lat: 26.2442, lng: 68.4100 },
		{ name: 'Okara', lat: 30.8081, lng: 73.4460 },
		{ name: 'Mirpur Khas', lat: 25.5268, lng: 69.0113 },
		{ name: 'Kasur', lat: 31.1156, lng: 74.4502 },
		{ name: 'Jhelum', lat: 32.9425, lng: 73.7257 },
		{ name: 'Attock', lat: 33.7666, lng: 72.3667 },
		{ name: 'Kohat', lat: 33.5900, lng: 71.4400 }
	];

	// Static, hardcoded icon glyphs for the search dialog's result rows —
	// never built from server/user data, so safe to set via innerHTML.
	var RESULT_ICONS = {
		service: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l6.5 6.5-7.5 7.5-6.5-6.5V3.5z"/><circle cx="6.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>',
		specialty: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.6 3.4v3.9a3 3 0 0 0 6 0V3.4"/><path d="M4.2 3.4h2.6M10.4 3.4H13"/><path d="M8.6 10.3v1.9a3.6 3.6 0 0 0 7.2 0v-1.4"/><circle cx="15.8" cy="9" r="1.6"/></svg>',
		clinic: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 17.5h13"/><path d="M5 17.5V6.5l5-3 5 3v11"/><path d="M10 8v4M8 10h4"/></svg>',
		chevron: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 4.5l5.5 5.5-5.5 5.5"/></svg>'
	};

	/**
	 * The homepage "Find your care" search dialog.
	 *
	 * - Opens from the hero search bar; moved to the end of <body> once so it
	 *   always stacks above the page and floating chat widgets. Background
	 *   scrolling is locked, focus is kept inside, Escape closes it, and focus
	 *   returns to the trigger.
	 * - Location is a combobox: its city list opens only while the field is
	 *   active, filters as you type, and offers "Any city" and "Use my
	 *   location" (asked for only when clicked). Changing city keeps the
	 *   query and re-filters doctors and clinics.
	 * - The query is matched in the browser against window.dakHomeSearch
	 *   (doctors, services, specialities, clinics — no network request per
	 *   keystroke), rendered as one keyboard-navigable list grouped by type.
	 *   Selecting a row opens its page; Enter with no row selected (or the
	 *   footer button) runs the full doctors search with the chosen city.
	 */
	function initHeroSearch() {
		var trigger = document.getElementById( 'dak-home-hero-search-trigger' );
		var modal = document.getElementById( 'dak-home-search-modal' );

		if ( ! trigger || ! modal ) {
			return;
		}

		var GROUP_LIMIT = 6;
		var data = window.dakHomeSearch || {};
		var labels = data.labels || {};
		var allDoctors = data.doctors || [];
		var allServices = data.services || [];
		var allSpecialties = data.specialties || [];
		var allClinics = data.clinics || [];

		var $ = function ( id ) {
			return document.getElementById( id );
		};

		var triggerLocation = $( 'dak-home-hero-search-trigger-location' );
		var dialog = modal.querySelector( '.dak-hsm-dialog' );
		var form = $( 'dak-home-search-modal-form' );
		var overlay = $( 'dak-home-search-modal-overlay' );
		var closeButton = $( 'dak-home-search-modal-close' );
		var queryInput = $( 'dak-home-search-modal-query-input' );
		var queryClear = $( 'dak-home-search-modal-query-clear' );
		var locationField = modal.querySelector( '.dak-hsm-field-location' );
		var locationInput = $( 'dak-home-search-modal-location-input' );
		var locationClear = $( 'dak-hsm-location-clear' );
		var cityHidden = $( 'dak-home-search-modal-city' );
		var cityPopover = $( 'dak-hsm-city-popover' );
		var cityList = $( 'dak-hsm-city-list' );
		var cityEmpty = $( 'dak-hsm-city-empty' );
		var cityOptions = Array.prototype.slice.call( modal.querySelectorAll( '.dak-hsm-city' ) );
		var locateButton = $( 'dak-home-search-modal-detect' );
		var locateStatus = $( 'dak-hsm-locate-status' );
		var body = $( 'dak-home-search-modal-results' );
		var intro = $( 'dak-hsm-intro' );
		var results = $( 'dak-hsm-results' );
		var empty = $( 'dak-home-search-modal-no-results' );
		var resetButton = $( 'dak-hsm-reset' );
		var anyCityButton = $( 'dak-hsm-any-city' );
		var announcer = $( 'dak-hsm-announcer' );
		var shortcuts = Array.prototype.slice.call( modal.querySelectorAll( '.dak-hsm-shortcut' ) );

		var selectedCity = { slug: '', label: '' };
		var anyCityLabel = labels.anyCity || ( triggerLocation ? triggerLocation.textContent : '' );
		var resultOptions = [];
		var activeResult = -1;
		var activeCity = -1;
		var lastFocus = null;

		// Above every wrapper on the page (a transformed or z-indexed parent
		// would otherwise trap the fixed dialog beneath floating widgets).
		document.body.appendChild( modal );

		trigger.addEventListener( 'click', openModal );
		overlay.addEventListener( 'click', closeModal );
		closeButton.addEventListener( 'click', closeModal );
		modal.addEventListener( 'keydown', onModalKeydown );

		queryInput.addEventListener( 'input', function () {
			render();
		} );
		queryInput.addEventListener( 'keydown', onQueryKeydown );
		queryInput.addEventListener( 'focus', closeCityPopover );

		queryClear.addEventListener( 'click', function () {
			queryInput.value = '';
			render();
			queryInput.focus();
		} );

		resetButton.addEventListener( 'click', function () {
			queryInput.value = '';
			render();
			queryInput.focus();
		} );

		anyCityButton.addEventListener( 'click', function () {
			selectCity( '', '' );
			queryInput.focus();
		} );

		locationInput.addEventListener( 'focus', function () {
			openCityPopover();
			locationInput.select();
		} );
		locationInput.addEventListener( 'click', openCityPopover );
		locationInput.addEventListener( 'input', function () {
			openCityPopover();
			filterCities( locationInput.value );
		} );
		locationInput.addEventListener( 'keydown', onLocationKeydown );

		locationClear.addEventListener( 'click', function () {
			selectCity( '', '' );
			locationInput.focus();
		} );

		// Leaving the Location field (to anywhere outside it) closes the list
		// and puts the field back to the chosen city.
		locationField.addEventListener( 'focusout', function ( event ) {
			if ( ! event.relatedTarget || ! locationField.contains( event.relatedTarget ) ) {
				closeCityPopover();
			}
		} );

		cityOptions.forEach( function ( option ) {
			// mousedown, not click: keeps focus in the field so focusout above
			// doesn't close the list before the choice registers.
			option.addEventListener( 'mousedown', function ( event ) {
				event.preventDefault();
				chooseCityOption( option );
			} );
		} );

		locateButton.addEventListener( 'click', useMyLocation );

		form.addEventListener( 'submit', function () {
			// Submitting is the full doctors search: the query plus the chosen
			// city (the directory reads ?s= and ?city=).
			cityHidden.value = selectedCity.slug;
		} );

		updateLocationUi();

		/* ------------------------------------------------------------------ */
		/* Dialog                                                              */
		/* ------------------------------------------------------------------ */

		function openModal() {
			lastFocus = document.activeElement;
			modal.removeAttribute( 'aria-hidden' );
			modal.classList.add( 'is-open' );
			lockScroll( true );
			trackViewport( true );
			render();

			// Deferred a tick so the now-visible field can take focus.
			setTimeout( function () {
				queryInput.focus();
			}, 0 );
		}

		function closeModal() {
			closeCityPopover();
			modal.setAttribute( 'aria-hidden', 'true' );
			modal.classList.remove( 'is-open' );
			lockScroll( false );
			trackViewport( false );

			// Each opening starts from a clean query; the chosen city stays.
			queryInput.value = '';
			render();

			( lastFocus && lastFocus.focus ? lastFocus : trigger ).focus();
		}

		function lockScroll( lock ) {
			var root = document.documentElement;

			if ( lock ) {
				var scrollbar = window.innerWidth - root.clientWidth;
				root.classList.add( 'dak-hsm-locked' );
				document.body.style.paddingRight = scrollbar > 0 ? scrollbar + 'px' : '';
			} else {
				root.classList.remove( 'dak-hsm-locked' );
				document.body.style.paddingRight = '';
			}
		}

		// Phones: keep the dialog sized to the visible area when the on-screen
		// keyboard opens, so the fields and results aren't hidden behind it.
		function onViewportResize() {
			if ( window.visualViewport ) {
				modal.style.setProperty( '--dak-hsm-vh', window.visualViewport.height + 'px' );
			}
		}

		function trackViewport( on ) {
			if ( ! window.visualViewport ) {
				return;
			}

			if ( on ) {
				onViewportResize();
				window.visualViewport.addEventListener( 'resize', onViewportResize );
			} else {
				window.visualViewport.removeEventListener( 'resize', onViewportResize );
				modal.style.removeProperty( '--dak-hsm-vh' );
			}
		}

		function onModalKeydown( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();

				if ( ! cityPopover.hidden ) {
					closeCityPopover();
					locationInput.focus();
					return;
				}

				closeModal();
				return;
			}

			if ( 'Tab' === event.key ) {
				trapFocus( event );
			}
		}

		function trapFocus( event ) {
			var focusable = Array.prototype.filter.call(
				dialog.querySelectorAll( 'a[href], button, input:not([type="hidden"]), [tabindex]:not([tabindex="-1"])' ),
				function ( el ) {
					return ! el.disabled && null !== el.offsetParent && -1 !== el.tabIndex;
				}
			);

			if ( ! focusable.length ) {
				return;
			}

			var first = focusable[0];
			var last = focusable[ focusable.length - 1 ];

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}

		/* ------------------------------------------------------------------ */
		/* Location                                                            */
		/* ------------------------------------------------------------------ */

		function openCityPopover() {
			if ( ! cityPopover.hidden ) {
				return;
			}

			cityPopover.hidden = false;
			locationInput.setAttribute( 'aria-expanded', 'true' );
			filterCities( '' );
		}

		function closeCityPopover() {
			if ( cityPopover.hidden ) {
				return;
			}

			cityPopover.hidden = true;
			locationInput.setAttribute( 'aria-expanded', 'false' );
			locationInput.removeAttribute( 'aria-activedescendant' );
			activeCity = -1;

			// Back to the chosen city — a half-typed name isn't a selection.
			locationInput.value = selectedCity.label;
			updateLocationUi();
		}

		function visibleCityOptions() {
			return cityOptions.filter( function ( option ) {
				return ! option.hidden;
			} );
		}

		function filterCities( text ) {
			var needle = text.trim().toLowerCase();
			var shown = 0;

			// While the field still shows the chosen city, list every city.
			if ( needle === selectedCity.label.toLowerCase() ) {
				needle = '';
			}

			cityOptions.forEach( function ( option ) {
				var label = option.getAttribute( 'data-city-label' );
				var isAny = '' === option.getAttribute( 'data-city-slug' );
				var visible = '' === needle || ( ! isAny && -1 !== label.toLowerCase().indexOf( needle ) );

				option.hidden = ! visible;
				option.setAttribute( 'aria-selected', option.getAttribute( 'data-city-slug' ) === selectedCity.slug ? 'true' : 'false' );
				shown += visible ? 1 : 0;
			} );

			cityEmpty.hidden = shown > 0;
			cityEmpty.textContent = shown > 0 ? '' : ( labels.noCityMatch || '' );
			setActiveCity( '' === needle ? -1 : 0 );
		}

		function setActiveCity( index ) {
			var options = visibleCityOptions();

			cityOptions.forEach( function ( option ) {
				option.classList.remove( 'is-active' );
			} );

			activeCity = options.length ? Math.max( -1, Math.min( index, options.length - 1 ) ) : -1;

			if ( activeCity >= 0 ) {
				options[ activeCity ].classList.add( 'is-active' );
				locationInput.setAttribute( 'aria-activedescendant', options[ activeCity ].id );
				options[ activeCity ].scrollIntoView( { block: 'nearest' } );
			} else {
				locationInput.removeAttribute( 'aria-activedescendant' );
			}
		}

		function onLocationKeydown( event ) {
			var options = visibleCityOptions();

			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();
				openCityPopover();
				setActiveCity( activeCity + ( 'ArrowDown' === event.key ? 1 : -1 ) );
				return;
			}

			if ( 'Enter' === event.key ) {
				// Never submits the search from here — Enter picks a city.
				event.preventDefault();

				if ( activeCity >= 0 && options[ activeCity ] ) {
					chooseCityOption( options[ activeCity ] );
				} else if ( 1 === options.length ) {
					chooseCityOption( options[0] );
				}
			}
		}

		function chooseCityOption( option ) {
			selectCity( option.getAttribute( 'data-city-slug' ), option.getAttribute( 'data-city-label' ) );
			closeCityPopover();
			queryInput.focus();
		}

		function selectCity( slug, label ) {
			selectedCity = { slug: slug || '', label: slug ? label : '' };
			cityHidden.value = selectedCity.slug;
			locationInput.value = selectedCity.label;
			updateLocationUi();

			// Keeps whatever was typed in the search box and re-filters it.
			render();
		}

		function updateLocationUi() {
			locationClear.hidden = '' === selectedCity.slug;
			locationField.classList.toggle( 'has-value', '' !== selectedCity.slug );

			if ( triggerLocation ) {
				triggerLocation.textContent = selectedCity.slug ? selectedCity.label : anyCityLabel;
			}

			// Specialty shortcuts open the directory filtered to the chosen city.
			shortcuts.forEach( function ( link ) {
				link.href = withParam( link.getAttribute( 'data-base-href' ), 'city', selectedCity.slug );
			} );
		}

		function useMyLocation() {
			if ( ! navigator.geolocation ) {
				setLocateStatus( labels.locateFailed, 'error' );
				return;
			}

			locateButton.disabled = true;
			locateButton.setAttribute( 'aria-busy', 'true' );
			setLocateStatus( labels.locating, '' );

			navigator.geolocation.getCurrentPosition(
				function ( position ) {
					locateDone();

					var option = nearestOfferedCity( position.coords.latitude, position.coords.longitude );

					if ( ! option ) {
						setLocateStatus( labels.locateNone, 'error' );
						return;
					}

					selectCity( option.getAttribute( 'data-city-slug' ), option.getAttribute( 'data-city-label' ) );
					setLocateStatus( '', '' );
					announce( ( labels.locateFound || '%s' ).replace( '%s', option.getAttribute( 'data-city-label' ) ) );
					closeCityPopover();
					queryInput.focus();
				},
				function ( error ) {
					locateDone();
					setLocateStatus( error && 1 === error.code ? labels.locateDenied : labels.locateFailed, 'error' );
				},
				{ enableHighAccuracy: false, timeout: 8000, maximumAge: 600000 }
			);
		}

		function locateDone() {
			locateButton.disabled = false;
			locateButton.removeAttribute( 'aria-busy' );
		}

		function setLocateStatus( text, kind ) {
			locateStatus.textContent = text || '';
			locateStatus.className = 'dak-hsm-locate-status' + ( kind ? ' is-' + kind : '' );
		}

		/**
		 * The offered city (one with our doctors) closest to the visitor, using
		 * approximate city-centre coordinates (PK_CITIES); null when none of
		 * the offered cities is in that list.
		 */
		function nearestOfferedCity( lat, lng ) {
			var best = null;
			var bestDistance = Infinity;

			cityOptions.forEach( function ( option ) {
				var label = ( option.getAttribute( 'data-city-label' ) || '' ).trim().toLowerCase();

				if ( '' === label ) {
					return;
				}

				PK_CITIES.forEach( function ( city ) {
					if ( city.name.toLowerCase() !== label ) {
						return;
					}

					var distance = distanceKm( lat, lng, city.lat, city.lng );

					if ( distance < bestDistance ) {
						bestDistance = distance;
						best = option;
					}
				} );
			} );

			return best;
		}

		/* ------------------------------------------------------------------ */
		/* Results                                                             */
		/* ------------------------------------------------------------------ */

		function render() {
			var raw = queryInput.value;
			var query = raw.trim().toLowerCase();

			queryClear.hidden = '' === raw;
			resultOptions = [];
			activeResult = -1;
			queryInput.removeAttribute( 'aria-activedescendant' );
			results.innerHTML = '';

			if ( '' === query ) {
				intro.hidden = false;
				results.hidden = true;
				empty.hidden = true;
				queryInput.setAttribute( 'aria-expanded', 'false' );
				announce( '' );
				return;
			}

			var city = selectedCity.slug;

			var doctors = allDoctors.filter( function ( doctor ) {
				var matches = contains( doctor.name, query ) || contains( doctor.specialty, query );

				return matches && ( '' === city || ( doctor.citySlugs || [] ).indexOf( city ) !== -1 );
			} );

			// 'keywords' is admin-only text: matched here, never displayed.
			var services = allServices.filter( function ( service ) {
				return contains( service.name, query ) || contains( service.category, query ) || contains( service.keywords, query );
			} );

			var specialties = allSpecialties.filter( function ( specialty ) {
				return contains( specialty.label, query );
			} );

			var clinics = allClinics.filter( function ( clinic ) {
				var matches = contains( clinic.name, query ) || contains( clinic.location, query ) || contains( clinic.keywords, query );

				return matches && ( '' === city || ! clinic.citySlug || clinic.citySlug === city );
			} );

			var total = doctors.length + services.length + specialties.length + clinics.length;

			intro.hidden = true;
			results.hidden = 0 === total;
			empty.hidden = 0 !== total;
			anyCityButton.hidden = '' === city;
			queryInput.setAttribute( 'aria-expanded', total ? 'true' : 'false' );

			if ( 0 === total ) {
				announce( labels.noResults || '' );
				return;
			}

			addGroup( 'doctors', labels.doctors, doctors, function ( doctor ) {
				return buildOption( 'doctor', {
					avatarUrl: doctor.avatarUrl,
					initials: initialsOf( doctor.name || '' ),
					title: doctor.name,
					meta: [ doctor.specialty, doctor.location ],
					url: doctor.url
				}, query );
			} );

			addGroup( 'services', labels.services, services, function ( service ) {
				return buildOption( 'service', { title: service.name, meta: [ service.category ], url: service.url }, query );
			} );

			addGroup( 'specialties', labels.specialties, specialties, function ( specialty ) {
				var count = parseInt( specialty.count, 10 ) || 0;

				return buildOption( 'specialty', {
					title: specialty.label,
					meta: [ count ? ( 1 === count ? labels.doctorCountOne : ( labels.doctorCount || '%d' ).replace( '%d', count ) ) : '' ],
					url: withParam( specialty.url, 'city', city )
				}, query );
			} );

			addGroup( 'clinics', labels.clinics, clinics, function ( clinic ) {
				return buildOption( 'clinic', { title: clinic.name, meta: [ clinic.location ], url: clinic.url }, query );
			} );

			body.scrollTop = 0;
			announce( 1 === total ? labels.resultsCountOne : ( labels.resultsCount || '%d' ).replace( '%d', total ) );
		}

		function addGroup( key, heading, items, build ) {
			if ( ! items.length ) {
				return;
			}

			var shown = items.slice( 0, GROUP_LIMIT );
			var group = document.createElement( 'div' );
			var headingEl = document.createElement( 'div' );
			var title = document.createElement( 'span' );
			var count = document.createElement( 'span' );

			group.className = 'dak-hsm-group dak-hsm-group-' + key;
			group.setAttribute( 'role', 'group' );
			group.setAttribute( 'aria-labelledby', 'dak-hsm-group-' + key );

			headingEl.className = 'dak-hsm-group-heading';
			headingEl.id = 'dak-hsm-group-' + key;
			title.textContent = heading || '';
			count.className = 'dak-hsm-group-count';
			count.textContent = shown.length < items.length
				? ( labels.shownOf || '%1$d of %2$d' ).replace( '%1$d', shown.length ).replace( '%2$d', items.length )
				: String( items.length );

			headingEl.appendChild( title );
			headingEl.appendChild( count );
			group.appendChild( headingEl );

			shown.forEach( function ( item ) {
				var option = build( item );

				if ( option ) {
					group.appendChild( option );
				}
			} );

			results.appendChild( group );
		}

		/**
		 * One result row: a real link (so it can also be opened in a new tab),
		 * exposed as a listbox option for keyboard/screen-reader navigation.
		 * Built from DOM nodes — names are user data, never parsed as markup.
		 */
		function buildOption( type, item, query ) {
			if ( ! item.url ) {
				return null;
			}

			var option = document.createElement( 'a' );
			var index = resultOptions.length;

			option.className = 'dak-hsm-option dak-hsm-option-' + type;
			option.href = item.url;
			option.id = 'dak-hsm-option-' + index;
			option.setAttribute( 'role', 'option' );
			option.setAttribute( 'aria-selected', 'false' );
			option.tabIndex = -1;

			var media = document.createElement( 'span' );
			media.className = 'dak-hsm-option-media';
			media.setAttribute( 'aria-hidden', 'true' );

			if ( 'doctor' === type ) {
				if ( item.avatarUrl ) {
					var img = document.createElement( 'img' );
					img.src = item.avatarUrl;
					img.alt = '';
					img.loading = 'lazy';
					media.appendChild( img );
				} else {
					media.textContent = item.initials;
				}
			} else {
				media.innerHTML = RESULT_ICONS[ type ];
			}

			var text = document.createElement( 'span' );
			text.className = 'dak-hsm-option-text';

			var title = document.createElement( 'span' );
			title.className = 'dak-hsm-option-title';
			appendHighlighted( title, item.title || '', query );
			text.appendChild( title );

			var metaParts = ( item.meta || [] ).filter( function ( part ) {
				return part && String( part ).trim();
			} );

			if ( metaParts.length ) {
				var meta = document.createElement( 'span' );
				meta.className = 'dak-hsm-option-meta';

				metaParts.forEach( function ( part, i ) {
					if ( i > 0 ) {
						meta.appendChild( document.createTextNode( ' · ' ) );
					}

					appendHighlighted( meta, String( part ), query );
				} );

				text.appendChild( meta );
			}

			var arrow = document.createElement( 'span' );
			arrow.className = 'dak-hsm-option-arrow';
			arrow.setAttribute( 'aria-hidden', 'true' );
			arrow.innerHTML = RESULT_ICONS.chevron;

			option.appendChild( media );
			option.appendChild( text );
			option.appendChild( arrow );

			option.addEventListener( 'mousemove', function () {
				if ( activeResult !== index ) {
					setActiveResult( index, false );
				}
			} );

			resultOptions.push( option );

			return option;
		}

		function setActiveResult( index, scroll ) {
			if ( activeResult >= 0 && resultOptions[ activeResult ] ) {
				resultOptions[ activeResult ].classList.remove( 'is-active' );
				resultOptions[ activeResult ].setAttribute( 'aria-selected', 'false' );
			}

			activeResult = index;

			if ( index < 0 || ! resultOptions[ index ] ) {
				activeResult = -1;
				queryInput.removeAttribute( 'aria-activedescendant' );
				return;
			}

			resultOptions[ index ].classList.add( 'is-active' );
			resultOptions[ index ].setAttribute( 'aria-selected', 'true' );
			queryInput.setAttribute( 'aria-activedescendant', resultOptions[ index ].id );

			if ( false !== scroll ) {
				resultOptions[ index ].scrollIntoView( { block: 'nearest' } );
			}
		}

		function onQueryKeydown( event ) {
			if ( ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) && resultOptions.length ) {
				event.preventDefault();

				var next = activeResult + ( 'ArrowDown' === event.key ? 1 : -1 );

				if ( next >= resultOptions.length ) {
					next = 0;
				} else if ( next < -1 ) {
					next = resultOptions.length - 1;
				}

				setActiveResult( next );
				return;
			}

			// Enter opens the highlighted result; with none highlighted, the
			// form submits as the full doctors search.
			if ( 'Enter' === event.key && activeResult >= 0 && resultOptions[ activeResult ] ) {
				event.preventDefault();
				window.location.href = resultOptions[ activeResult ].href;
			}
		}

		/* ------------------------------------------------------------------ */
		/* Helpers                                                             */
		/* ------------------------------------------------------------------ */

		function announce( text ) {
			// Cleared first so the same message (e.g. "3 results") is re-read.
			announcer.textContent = '';

			if ( text ) {
				setTimeout( function () {
					announcer.textContent = text;
				}, 120 );
			}
		}

		function contains( value, query ) {
			return !! value && -1 !== String( value ).toLowerCase().indexOf( query );
		}

		function withParam( url, key, value ) {
			if ( ! url ) {
				return url;
			}

			try {
				var parsed = new URL( url, window.location.href );

				if ( value ) {
					parsed.searchParams.set( key, value );
				} else {
					parsed.searchParams.delete( key );
				}

				return parsed.toString();
			} catch ( e ) {
				return url;
			}
		}

		/**
		 * Appends `text` to `container`, wrapping the first case-insensitive
		 * match of `query` in a <mark>. Text nodes only — the displayed
		 * spelling and spacing are exactly the original's.
		 */
		function appendHighlighted( container, text, query ) {
			var index = query ? text.toLowerCase().indexOf( query ) : -1;

			if ( -1 === index ) {
				container.appendChild( document.createTextNode( text ) );
				return;
			}

			container.appendChild( document.createTextNode( text.slice( 0, index ) ) );

			var mark = document.createElement( 'mark' );
			mark.textContent = text.slice( index, index + query.length );
			container.appendChild( mark );

			container.appendChild( document.createTextNode( text.slice( index + query.length ) ) );
		}

		function initialsOf( name ) {
			var parts = name.trim().split( /\s+/ ).filter( Boolean );
			var initials = parts.slice( 0, 2 ).map( function ( part ) {
				return part.charAt( 0 ).toUpperCase();
			} ).join( '' );

			return initials || '?';
		}
	}

	// Haversine great-circle distance in kilometres — plenty accurate for
	// "which of ~30 city centres is closest", no need for anything fancier
	// than a spherical Earth approximation here.
	function distanceKm( lat1, lng1, lat2, lng2 ) {
		var R = 6371;
		var dLat = toRad( lat2 - lat1 );
		var dLng = toRad( lng2 - lng1 );
		var a = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 )
			+ Math.cos( toRad( lat1 ) ) * Math.cos( toRad( lat2 ) ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );

		return R * 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
	}

	function toRad( degrees ) {
		return degrees * ( Math.PI / 180 );
	}
} )();
