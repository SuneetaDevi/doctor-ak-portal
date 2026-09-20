/**
 * Doctor AK Portal — Doctors directory search/filter/sort/pagination.
 *
 * Client-side only: the whole grid is already rendered server-side in one
 * page load, so filtering by name/specialization/location just shows/hides
 * cards, sorting re-orders the actual DOM nodes, and pagination further
 * hides everything outside the current page's slice of whatever currently
 * matches — no AJAX round trip needed for any of it. Country/City/Area
 * cascade from the full admin-managed Locations list
 * (window.dakDirectory.locations, see Locations::get_all()), not just
 * locations a listed doctor happens to have — consistent with every other
 * location picker in the plugin.
 */
( function () {
	'use strict';

	var PAGE_SIZE = 12;

	// Approximate city-centre coordinates for Pakistan's major cities — the
	// "Near me" pill picks the nearest one of these that a listed doctor
	// practises in (same list the home page's location detection uses).
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

	/**
	 * Great-circle distance in km (haversine).
	 */
	function distanceKm( lat1, lng1, lat2, lng2 ) {
		var toRad = Math.PI / 180;
		var dLat = ( lat2 - lat1 ) * toRad;
		var dLng = ( lng2 - lng1 ) * toRad;
		var a = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 )
			+ Math.cos( lat1 * toRad ) * Math.cos( lat2 * toRad ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );

		return 6371 * 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var grid = document.getElementById( 'dak-directory-grid' );
		var searchInput = document.getElementById( 'dak-directory-search-input' );
		var sortSelect = document.getElementById( 'dak-directory-sort' );
		var videoToggle = document.getElementById( 'dak-directory-video-toggle' );
		var availabilityToggle = document.getElementById( 'dak-directory-availability-toggle' );
		var nearMeButton = document.getElementById( 'dak-directory-nearme-toggle' );
		var nearMeStatus = document.getElementById( 'dak-directory-nearme-status' );

		if ( ! grid || ! searchInput ) {
			return;
		}

		var noResults = document.getElementById( 'dak-directory-no-results' );
		var currentPage = 1;

		// A `?city=<slug>` link (footer/header deep links) matches directly
		// against each card's own data-search-city — see applyFilters() below.
		// The "Near me" pill (initNearMe()) overrides it once used.
		var presetCity = window.URLSearchParams
			? ( new URLSearchParams( window.location.search ).get( 'city' ) || '' ).toLowerCase()
			: '';

		// City resolved by the "Near me" pill from the visitor's location, '' when off.
		var nearCity = '';

		// No specialization dropdown on this page — the `?specialization=` deep
		// links (home specialty tiles, header Doctors menu) still filter, via this.
		var presetSpecialization = window.URLSearchParams
			? ( new URLSearchParams( window.location.search ).get( 'specialization' ) || '' ).toLowerCase()
			: '';

		initNearMe();
		initTogglePill( videoToggle, applyFilters );
		initTogglePill( availabilityToggle, applyFilters );
		initSort( grid, sortSelect, applyFilters );
		initPagination( function ( page ) {
			currentPage = page;
			applyFilters( false );
		} );

		/**
		 * Re-evaluates every filter (search, specialization, country/city/
		 * area, clinic, the Video/Availability quick-pick pills) against
		 * every card, then slices whatever still matches down to the
		 * current page — one pass does both, since pagination has to know
		 * the filtered count anyway to know how many pages there are.
		 *
		 * @param {boolean} [resetPage] False only when a page-nav click
		 *   itself triggered this (so the page you just clicked to sticks);
		 *   true (the default) for every real filter/sort change, which
		 *   always jumps back to page 1 — staying on, say, page 4 of a
		 *   search that now only has one page of results would just show
		 *   nothing.
		 */
		function applyFilters( resetPage ) {
			if ( false !== resetPage ) {
				currentPage = 1;
			}

			// Queried live (not captured once) so this also reflects
			// whatever order initSort() last re-arranged the cards into.
			var cards = Array.prototype.slice.call( grid.querySelectorAll( '[data-doctor-card]' ) );

			var query = searchInput.value.trim().toLowerCase();
			var specialization = presetSpecialization;
			var city = nearCity || presetCity;
			var videoOnly = videoToggle ? videoToggle.classList.contains( 'is-active' ) : false;
			var availableOnly = availabilityToggle ? availabilityToggle.classList.contains( 'is-active' ) : false;

			var matching = cards.filter( function ( card ) {
				var name = card.getAttribute( 'data-search-name' ) || '';
				var specializations = card.getAttribute( 'data-search-specializations' ) || '';
				var cities = card.getAttribute( 'data-search-city' ) || '';

				var matchesQuery = '' === query || name.indexOf( query ) !== -1 || specializations.indexOf( query ) !== -1;
				var matchesSpecialization = '' === specialization || specializations.indexOf( specialization ) !== -1;
				var matchesCity = '' === city || cities.split( ',' ).indexOf( city ) !== -1;
				var matchesVideo = ! videoOnly || '1' === card.getAttribute( 'data-search-video' );
				var matchesAvailable = ! availableOnly || '1' === card.getAttribute( 'data-search-available' );

				return matchesQuery && matchesSpecialization && matchesCity
					&& matchesVideo && matchesAvailable;
			} );

			var totalPages = Math.max( 1, Math.ceil( matching.length / PAGE_SIZE ) );

			if ( currentPage > totalPages ) {
				currentPage = totalPages;
			}

			var start = ( currentPage - 1 ) * PAGE_SIZE;
			var end = start + PAGE_SIZE;
			var pageSlice = matching.slice( start, end );

			cards.forEach( function ( card ) {
				card.classList.toggle( 'dak-hidden', pageSlice.indexOf( card ) === -1 );
			} );

			if ( noResults ) {
				noResults.classList.toggle( 'dak-hidden', matching.length > 0 );
			}

			renderPagination( currentPage, totalPages );
		}

		searchInput.addEventListener( 'input', applyFilters );

		if ( presetSpecialization ) {
			applyFilters();
		}
		applyPreselectedSearch( searchInput, applyFilters );

		// Always run once on load, preset filters or not — unlike the old
		// filter-only version, this also has to paginate the very first
		// render (each applyPreselectedX() call above already re-runs it
		// again on top of this when it actually finds something to preset,
		// which is harmless — just one extra pass over the cards).
		applyFilters();

		/**
		 * The "Near me" quick-filter pill: on click asks the browser for the
		 * visitor's position, finds the nearest city (from PK_CITIES below) that
		 * at least one listed doctor actually practises in, and narrows the list
		 * to it; clicking again turns it off. Fails quietly with a short message
		 * when permission is denied or nothing can be matched.
		 */
		function initNearMe() {
			if ( ! nearMeButton ) {
				return;
			}

			var labelEl = nearMeButton.querySelector( '[data-nearme-label]' );
			var defaultLabel = labelEl ? labelEl.textContent : '';

			nearMeButton.addEventListener( 'click', function () {
				if ( nearCity ) {
					setNearCity( '', '' );
					return;
				}

				if ( ! navigator.geolocation ) {
					showStatus( nearMeButton.getAttribute( 'data-msg-unsupported' ) );
					return;
				}

				showStatus( '' );
				nearMeButton.classList.add( 'is-detecting' );

				navigator.geolocation.getCurrentPosition(
					function ( position ) {
						nearMeButton.classList.remove( 'is-detecting' );

						var nearest = findNearestCity( position.coords.latitude, position.coords.longitude );

						if ( ! nearest ) {
							showStatus( nearMeButton.getAttribute( 'data-msg-none' ) );
							return;
						}

						setNearCity( nearest.slug, nearest.label );
					},
					function () {
						nearMeButton.classList.remove( 'is-detecting' );
						showStatus( nearMeButton.getAttribute( 'data-msg-denied' ) );
					},
					{ enableHighAccuracy: false, timeout: 8000, maximumAge: 600000 }
				);
			} );

			function setNearCity( slug, label ) {
				nearCity = slug;
				presetCity = '';
				nearMeButton.classList.toggle( 'is-active', '' !== slug );
				nearMeButton.setAttribute( 'aria-pressed', '' !== slug ? 'true' : 'false' );

				if ( labelEl ) {
					labelEl.textContent = '' !== slug ? nearMeButton.getAttribute( 'data-label-near' ) + ' ' + label : defaultLabel;
				}

				showStatus( '' );
				applyFilters();
			}

			function showStatus( message ) {
				if ( ! nearMeStatus ) {
					return;
				}

				nearMeStatus.textContent = message || '';
				nearMeStatus.classList.toggle( 'dak-hidden', ! message );
			}
		}

		/**
		 * Nearest city — among those a listed doctor practises in AND that
		 * PK_CITIES has coordinates for — to the given point.
		 *
		 * @param {number} lat Visitor latitude.
		 * @param {number} lng Visitor longitude.
		 * @return {{slug: string, label: string}|null}
		 */
		function findNearestCity( lat, lng ) {
			var labels = {};

			( ( window.dakDirectory && window.dakDirectory.locations ) || [] ).forEach( function ( country ) {
				( country.cities || [] ).forEach( function ( city ) {
					labels[ city.slug ] = city.name;
				} );
			} );

			var inUse = {};

			grid.querySelectorAll( '[data-doctor-card]' ).forEach( function ( card ) {
				( card.getAttribute( 'data-search-city' ) || '' ).split( ',' ).forEach( function ( slug ) {
					if ( slug ) {
						inUse[ slug ] = true;
					}
				} );
			} );

			var candidates = Object.keys( inUse ).map( function ( slug ) {
				var label = labels[ slug ] || slug;
				var known = PK_CITIES.filter( function ( city ) {
					return city.name.toLowerCase() === label.toLowerCase();
				} )[ 0 ];

				return known ? { slug: slug, label: label, distance: distanceKm( lat, lng, known.lat, known.lng ) } : null;
			} ).filter( Boolean ).sort( function ( a, b ) {
				return a.distance - b.distance;
			} );

			return candidates.length ? candidates[ 0 ] : null;
		}

		/**
		 * Renders the "1 2 3 … 10" page-number row plus the prev/next
		 * buttons' disabled state (templates/directory/doctors-directory.php)
		 * — hides the whole nav when everything fits on one page.
		 *
		 * @param {number} page  Current 1-based page.
		 * @param {number} total Total number of pages.
		 */
		function renderPagination( page, total ) {
			var nav = document.getElementById( 'dak-directory-pagination' );
			var numbers = document.getElementById( 'dak-directory-page-numbers' );
			var prevBtn = document.getElementById( 'dak-directory-page-prev' );
			var nextBtn = document.getElementById( 'dak-directory-page-next' );

			if ( ! nav || ! numbers || ! prevBtn || ! nextBtn ) {
				return;
			}

			nav.classList.toggle( 'dak-hidden', total <= 1 );

			if ( total <= 1 ) {
				return;
			}

			numbers.innerHTML = '';

			buildPageList( page, total ).forEach( function ( entry ) {
				if ( '…' === entry ) {
					var ellipsis = document.createElement( 'span' );
					ellipsis.className = 'dak-directory-page-ellipsis';
					ellipsis.textContent = '…';
					numbers.appendChild( ellipsis );

					return;
				}

				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'dak-directory-page-number' + ( entry === page ? ' is-active' : '' );
				button.textContent = String( entry );

				if ( entry === page ) {
					button.setAttribute( 'aria-current', 'page' );
				}

				button.addEventListener( 'click', function () {
					currentPage = entry;
					applyFilters( false );
					nav.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				} );

				numbers.appendChild( button );
			} );

			prevBtn.disabled = page <= 1;
			nextBtn.disabled = page >= total;
		}

		/**
		 * First/last page, the current page and one neighbour either side,
		 * with a "…" filling any gap — e.g. for page 1 of 10: 1 2 … 10; for
		 * page 5 of 10: 1 … 4 5 6 … 10.
		 *
		 * @param {number} current
		 * @param {number} total
		 * @return {Array<number|string>}
		 */
		function buildPageList( current, total ) {
			var pages = [];

			for ( var i = 1; i <= total; i++ ) {
				if ( 1 === i || total === i || ( i >= current - 1 && i <= current + 1 ) ) {
					pages.push( i );
				} else if ( '…' !== pages[ pages.length - 1 ] ) {
					pages.push( '…' );
				}
			}

			return pages;
		}

		/**
		 * Wires the prev/next pagination buttons — the page-number buttons
		 * themselves are (re)built fresh each render inside renderPagination()
		 * above, since how many there are changes with the filtered count.
		 *
		 * @param {Function} goToPage Called with the 1-based page to show.
		 */
		function initPagination( goToPage ) {
			var prevBtn = document.getElementById( 'dak-directory-page-prev' );
			var nextBtn = document.getElementById( 'dak-directory-page-next' );

			if ( prevBtn ) {
				prevBtn.addEventListener( 'click', function () {
					if ( ! prevBtn.disabled ) {
						goToPage( currentPage - 1 );
					}
				} );
			}

			if ( nextBtn ) {
				nextBtn.addEventListener( 'click', function () {
					if ( ! nextBtn.disabled ) {
						goToPage( currentPage + 1 );
					}
				} );
			}
		}
	} );

	/**
	 * Lands on this page with a search term already typed — the site
	 * header's Doctors mega-menu search box links here as `?s=<term>`.
	 *
	 * @param {HTMLInputElement} input        The search text input.
	 * @param {Function}         applyFilters Re-runs the grid filtering.
	 */
	function applyPreselectedSearch( input, applyFilters ) {
		if ( ! input || ! window.URLSearchParams ) {
			return;
		}

		var requested = new URLSearchParams( window.location.search ).get( 's' );

		if ( ! requested ) {
			return;
		}

		input.value = requested;
		applyFilters();
	}

	/**
	 * A simple on/off quick-filter pill (Video Consultation, Availability —
	 * templates/directory/doctors-directory.php): toggles its own active
	 * state and re-runs the filters, which read that state directly off the
	 * button's class (see applyFilters()'s videoOnly/availableOnly).
	 *
	 * @param {HTMLElement} button       The pill <button>, or null.
	 * @param {Function}     applyFilters Re-runs the grid filtering.
	 */
	function initTogglePill( button, applyFilters ) {
		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var isActive = ! button.classList.contains( 'is-active' );

			button.classList.toggle( 'is-active', isActive );
			button.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			applyFilters();
		} );
	}

	/**
	 * Wires the Sort <select> (templates/directory/doctors-directory.php) —
	 * physically re-orders the card elements in the DOM (rather than just
	 * re-filtering) so grid/list view, pagination and print/Ctrl+F all see
	 * the same order the visitor chose.
	 *
	 * @param {HTMLElement}       grid         The doctors grid.
	 * @param {HTMLSelectElement} sortSelect   The Sort <select>, or null.
	 * @param {Function}          applyFilters Re-runs filtering/pagination after re-ordering.
	 */
	function initSort( grid, sortSelect, applyFilters ) {
		if ( ! sortSelect ) {
			return;
		}

		sortSelect.addEventListener( 'change', function () {
			var cards = Array.prototype.slice.call( grid.querySelectorAll( '[data-doctor-card]' ) );
			var mode = sortSelect.value;

			cards.sort( function ( a, b ) {
				if ( 'name-asc' === mode ) {
					return ( a.getAttribute( 'data-sort-name' ) || '' ).localeCompare( b.getAttribute( 'data-sort-name' ) || '' );
				}

				if ( 'name-desc' === mode ) {
					return ( b.getAttribute( 'data-sort-name' ) || '' ).localeCompare( a.getAttribute( 'data-sort-name' ) || '' );
				}

				// 'experience-desc', and the default.
				return ( parseInt( b.getAttribute( 'data-sort-experience' ), 10 ) || 0 ) - ( parseInt( a.getAttribute( 'data-sort-experience' ), 10 ) || 0 );
			} );

			// appendChild() on a node already in the document moves it —
			// re-appending every card in the sorted order re-arranges the
			// whole grid without touching any card's own markup.
			cards.forEach( function ( card ) {
				grid.appendChild( card );
			} );

			applyFilters();
		} );
	}
} )();
