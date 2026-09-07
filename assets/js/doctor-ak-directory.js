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

	document.addEventListener( 'DOMContentLoaded', function () {
		var grid = document.getElementById( 'dak-directory-grid' );
		var searchInput = document.getElementById( 'dak-directory-search-input' );
		var specializationSelect = document.getElementById( 'dak-directory-specialization-filter' );
		var countrySelect = document.getElementById( 'dak-directory-country-filter' );
		var citySelect = document.getElementById( 'dak-directory-city-filter' );
		var areaSelect = document.getElementById( 'dak-directory-area-filter' );
		var clinicSelect = document.getElementById( 'dak-directory-clinic-filter' );
		var sortSelect = document.getElementById( 'dak-directory-sort' );
		var videoToggle = document.getElementById( 'dak-directory-video-toggle' );
		var availabilityToggle = document.getElementById( 'dak-directory-availability-toggle' );

		if ( ! grid || ! searchInput ) {
			return;
		}

		var noResults = document.getElementById( 'dak-directory-no-results' );
		var currentPage = 1;

		// A `?city=<slug>` link (the site header's Doctors -> By Location
		// menu) matches directly against each card's own data-search-city —
		// see applyFilters() below — rather than driving the Country -> City
		// cascade, which only populates City once a Country is chosen and so
		// can't be preselected by a single deep link. Captured once here;
		// the moment the visitor actually touches the City <select>
		// themselves, its own value takes over (see matchesCity below).
		var presetCity = window.URLSearchParams
			? ( new URLSearchParams( window.location.search ).get( 'city' ) || '' ).toLowerCase()
			: '';

		wireLocationCascade( countrySelect, citySelect, areaSelect );
		wireClinicAreaDependency( areaSelect, clinicSelect );
		initColumnsToggle( grid );
		initViewToggle( grid );
		initLocationPanel();
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
			var specialization = specializationSelect ? specializationSelect.value : '';
			var country = countrySelect ? countrySelect.value : '';
			var city = ( citySelect && citySelect.value ) ? citySelect.value : presetCity;
			var area = areaSelect ? areaSelect.value : '';
			var clinic = clinicSelect ? clinicSelect.value : '';
			var videoOnly = videoToggle ? videoToggle.classList.contains( 'is-active' ) : false;
			var availableOnly = availabilityToggle ? availabilityToggle.classList.contains( 'is-active' ) : false;

			var matching = cards.filter( function ( card ) {
				var name = card.getAttribute( 'data-search-name' ) || '';
				var specializations = card.getAttribute( 'data-search-specializations' ) || '';
				var countries = card.getAttribute( 'data-search-country' ) || '';
				var cities = card.getAttribute( 'data-search-city' ) || '';
				var areas = card.getAttribute( 'data-search-area' ) || '';
				var clinics = card.getAttribute( 'data-search-clinics' ) || '';

				var matchesQuery = '' === query || name.indexOf( query ) !== -1 || specializations.indexOf( query ) !== -1;
				var matchesSpecialization = '' === specialization || specializations.indexOf( specialization ) !== -1;
				var matchesCountry = '' === country || countries.split( ',' ).indexOf( country ) !== -1;
				var matchesCity = '' === city || cities.split( ',' ).indexOf( city ) !== -1;
				var matchesArea = '' === area || areas.split( ',' ).indexOf( area ) !== -1;
				var matchesClinic = '' === clinic || clinics.indexOf( clinic ) !== -1;
				var matchesVideo = ! videoOnly || '1' === card.getAttribute( 'data-search-video' );
				var matchesAvailable = ! availableOnly || '1' === card.getAttribute( 'data-search-available' );

				return matchesQuery && matchesSpecialization && matchesCountry && matchesCity
					&& matchesArea && matchesClinic && matchesVideo && matchesAvailable;
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

		// A manual pick in the City <select> itself should always win over
		// the `?city=` deep link from here on — clearing presetCity means
		// matchesCity above falls through to citySelect's own (possibly
		// empty, i.e. "All cities") value instead of re-applying the preset.
		if ( citySelect ) {
			citySelect.addEventListener( 'change', function () {
				presetCity = '';
			} );
		}

		[ specializationSelect, countrySelect, citySelect, areaSelect, clinicSelect ].forEach( function ( select ) {
			if ( select ) {
				select.addEventListener( 'change', applyFilters );
			}
		} );

		applyPreselectedFilter( specializationSelect, 'specialization', applyFilters );
		applyPreselectedFilter( clinicSelect, 'clinic', applyFilters );
		applyPreselectedSearch( searchInput, applyFilters );

		// Always run once on load, preset filters or not — unlike the old
		// filter-only version, this also has to paginate the very first
		// render (each applyPreselectedX() call above already re-runs it
		// again on top of this when it actually finds something to preset,
		// which is harmless — just one extra pass over the cards).
		applyFilters();

		/**
		 * Expand/collapse for the Location quick-filter's Country/City/Area
		 * panel (templates/directory/doctors-directory.php). The <select>s
		 * inside already drive applyFilters() via the
		 * [specializationSelect, countrySelect, ...].forEach() above; this
		 * only owns the disclosure itself plus the pill's own state.
		 *
		 * Note the pill's `is-active` class means "a location filter is
		 * applied", NOT "the panel is open" — collapsing the panel would
		 * otherwise hide the fact that the grid is still filtered down to one
		 * city. Open/closed is carried by aria-expanded alone (which is also
		 * what flips the chevron, see doctor-ak-directory.css).
		 */
		function initLocationPanel() {
			var toggle = document.getElementById( 'dak-directory-location-toggle' );
			var panel = document.getElementById( 'dak-directory-location-panel' );

			if ( ! toggle || ! panel ) {
				return;
			}

			toggle.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				setOpen( panel.classList.contains( 'dak-hidden' ) );
			} );

			document.addEventListener( 'click', function ( event ) {
				if ( ! panel.classList.contains( 'dak-hidden' ) && ! panel.contains( event.target ) ) {
					setOpen( false );
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && ! panel.classList.contains( 'dak-hidden' ) ) {
					setOpen( false );
				}
			} );

			// Keep the pill lit for as long as any level of the cascade is
			// narrowed, whatever the panel is doing.
			[ countrySelect, citySelect, areaSelect ].forEach( function ( select ) {
				if ( select ) {
					select.addEventListener( 'change', updatePillState );
				}
			} );

			updatePillState();

			function updatePillState() {
				var isFiltered = Boolean(
					( countrySelect && countrySelect.value )
					|| ( citySelect && citySelect.value )
					|| ( areaSelect && areaSelect.value )
				);

				toggle.classList.toggle( 'is-active', isFiltered );
			}

			function setOpen( open ) {
				panel.classList.toggle( 'dak-hidden', ! open );
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
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
	 * Lands on this page with a filter already chosen — the home page's
	 * "Consult Top Doctors Online" tiles link here as `?specialization=<lowercased
	 * label>`, and the site header's "Doctors -> By Location" menu as
	 * `?clinic=<lowercased name>`, both matching the filter's own option
	 * values. Anything that isn't an option (nothing listed under it, or a
	 * hand-edited URL) is ignored, leaving the unfiltered grid rather than an
	 * empty one.
	 *
	 * @param {HTMLSelectElement} select       The filter <select> (specialization or clinic).
	 * @param {string}            param        Its matching URL query parameter name.
	 * @param {Function}          applyFilters Re-runs the grid filtering.
	 */
	function applyPreselectedFilter( select, param, applyFilters ) {
		if ( ! select || ! window.URLSearchParams ) {
			return;
		}

		var requested = new URLSearchParams( window.location.search ).get( param );

		if ( ! requested ) {
			return;
		}

		select.value = requested.toLowerCase();

		if ( '' !== select.value ) {
			applyFilters();
		}
	}

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

	function findBySlug( list, slug ) {
		return ( list || [] ).filter( function ( entry ) {
			return entry.slug === slug;
		} )[ 0 ];
	}

	/**
	 * Fills a filter <select> with an "All X" first option plus one option
	 * per entry.
	 *
	 * @param {HTMLSelectElement} select      The <select> to fill.
	 * @param {Object[]}          entries     List of `{ slug, name }`.
	 * @param {string}            allLabel    First option's label (e.g. "All cities").
	 * @return {void}
	 */
	function fillFilterSelect( select, entries, allLabel ) {
		select.innerHTML = '';

		var allOption = document.createElement( 'option' );
		allOption.value = '';
		allOption.textContent = allLabel;
		select.appendChild( allOption );

		entries.forEach( function ( entry ) {
			var option = document.createElement( 'option' );
			option.value = entry.slug;
			option.textContent = entry.name;
			select.appendChild( option );
		} );
	}

	/**
	 * Populates the Country filter from the full Locations list, and wires
	 * Country -> City -> Area cascading: picking a Country repopulates City,
	 * picking a City repopulates Area.
	 *
	 * City and Area are *hidden* rather than shown-but-disabled until the
	 * level above them is chosen and actually has entries under it — a
	 * greyed-out control the visitor can't use (and can't tell how to make
	 * usable) is just noise, so each level only appears once it has
	 * something real to offer.
	 *
	 * @param {HTMLSelectElement} countrySelect The Country filter <select>.
	 * @param {HTMLSelectElement} citySelect    The City filter <select>.
	 * @param {HTMLSelectElement} areaSelect    The Area filter <select>.
	 * @return {void}
	 */
	function wireLocationCascade( countrySelect, citySelect, areaSelect ) {
		if ( ! countrySelect || ! citySelect || ! areaSelect || ! window.dakDirectory ) {
			return;
		}

		var locations = window.dakDirectory.locations || [];

		fillFilterSelect( countrySelect, locations, 'All Countries' );

		countrySelect.addEventListener( 'change', function () {
			var country = findBySlug( locations, countrySelect.value );
			var cities = country ? country.cities : [];

			fillFilterSelect( citySelect, cities, 'All Cities' );
			showLevel( citySelect, cities.length > 0 );

			fillFilterSelect( areaSelect, [], 'All Areas' );
			showLevel( areaSelect, false );

			citySelect.dispatchEvent( new Event( 'change' ) );
		} );

		citySelect.addEventListener( 'change', function () {
			var country = findBySlug( locations, countrySelect.value );
			var city = country ? findBySlug( country.cities, citySelect.value ) : null;
			var areas = city ? city.areas : [];

			fillFilterSelect( areaSelect, areas, 'All Areas' );
			showLevel( areaSelect, areas.length > 0 );

			areaSelect.dispatchEvent( new Event( 'change' ) );
		} );
	}

	/**
	 * Shows or hides one level of the location cascade. Kept in sync with
	 * `disabled` as well as visibility so a hidden level can never still be
	 * submitting a stale value or picked up by keyboard/AT navigation.
	 *
	 * @param {HTMLSelectElement} select    The City or Area <select>.
	 * @param {boolean}           available Whether it has real options to offer.
	 * @return {void}
	 */
	function showLevel( select, available ) {
		select.classList.toggle( 'dak-hidden', ! available );
		select.disabled = ! available;
	}

	/**
	 * Narrows the Clinic filter down to only clinics in the selected Area —
	 * clinics with no area on file always stay listed (nothing to exclude
	 * them by). Rebuilds from the server-rendered option list captured once
	 * at load, since the Clinic <select>'s options (and each one's
	 * `data-area`) already come from the page's PHP-rendered markup rather
	 * than window.dakDirectory.locations.
	 *
	 * @param {HTMLSelectElement} areaSelect   The Area filter <select>.
	 * @param {HTMLSelectElement} clinicSelect The Clinic filter <select>.
	 * @return {void}
	 */
	function wireClinicAreaDependency( areaSelect, clinicSelect ) {
		if ( ! areaSelect || ! clinicSelect ) {
			return;
		}

		var allClinics = Array.prototype.slice.call( clinicSelect.options ).map( function ( option ) {
			return { value: option.value, label: option.textContent, area: option.getAttribute( 'data-area' ) || '' };
		} );

		areaSelect.addEventListener( 'change', function () {
			var area = areaSelect.value;
			var previousValue = clinicSelect.value;
			var matches = allClinics.filter( function ( clinic ) {
				return '' === clinic.value || '' === area || clinic.area === area;
			} );

			clinicSelect.innerHTML = '';

			matches.forEach( function ( clinic ) {
				var option = document.createElement( 'option' );
				option.value = clinic.value;
				option.textContent = clinic.label;
				clinicSelect.appendChild( option );
			} );

			clinicSelect.value = matches.some( function ( clinic ) { return clinic.value === previousValue; } ) ? previousValue : '';
			clinicSelect.dispatchEvent( new Event( 'change' ) );
		} );
	}

	/**
	 * Wires the Grid/List view toggle buttons (templates/directory/doctors-directory.php)
	 * — swaps a modifier class on the grid so CSS re-flows each existing card
	 * (see .dak-directory-grid-list in doctor-ak-directory.css), no re-render
	 * needed. Remembers the visitor's last choice in localStorage so it
	 * sticks across visits.
	 *
	 * @param {HTMLElement} grid The doctors grid ("dak-directory-grid").
	 * @return {void}
	 */
	function initViewToggle( grid ) {
		var buttons = document.querySelectorAll( '[data-directory-view]' );

		if ( ! buttons.length ) {
			return;
		}

		var STORAGE_KEY = 'dakDirectoryView';
		var savedView = '';

		try {
			savedView = window.localStorage.getItem( STORAGE_KEY ) || '';
		} catch ( e ) {
			savedView = '';
		}

		if ( 'list' === savedView ) {
			setView( 'list' );
		}

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				setView( button.getAttribute( 'data-directory-view' ) );
			} );
		} );

		function setView( view ) {
			grid.classList.toggle( 'dak-directory-grid-list', 'list' === view );

			buttons.forEach( function ( button ) {
				var isActive = button.getAttribute( 'data-directory-view' ) === view;
				button.classList.toggle( 'is-active', isActive );
				button.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			} );

			// "Cards per row" means nothing in list view (it's always one per
			// row there), so it goes away rather than sitting there inert.
			var columnsToggle = document.getElementById( 'dak-directory-columns-toggle' );

			if ( columnsToggle ) {
				columnsToggle.classList.toggle( 'dak-hidden', 'list' === view );
			}

			try {
				window.localStorage.setItem( STORAGE_KEY, view );
			} catch ( e ) {
				// Private browsing / storage disabled — the choice just won't persist.
			}
		}
	}

	/**
	 * Wires the "cards per row" control (2 / 4 / 6) for grid view — swaps a
	 * modifier class on the grid that CSS turns into that many columns (see
	 * .dak-directory-grid-cols-* in doctor-ak-directory.css, which also caps
	 * the count on narrower screens so 6 never squeezes into a phone), and
	 * remembers the choice across visits the same way the Grid/List toggle
	 * does.
	 *
	 * Worth noting: PAGE_SIZE is 12, which divides evenly by 2, 4 and 6, so
	 * every choice fills complete rows rather than leaving a ragged last one.
	 *
	 * @param {HTMLElement} grid The doctors grid ("dak-directory-grid").
	 * @return {void}
	 */
	function initColumnsToggle( grid ) {
		var buttons = document.querySelectorAll( '[data-directory-columns]' );

		if ( ! buttons.length ) {
			return;
		}

		var STORAGE_KEY = 'dakDirectoryColumns';
		var OPTIONS = [ '2', '4', '6' ];
		var saved = '';

		try {
			saved = window.localStorage.getItem( STORAGE_KEY ) || '';
		} catch ( e ) {
			saved = '';
		}

		setColumns( OPTIONS.indexOf( saved ) === -1 ? '4' : saved );

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				setColumns( button.getAttribute( 'data-directory-columns' ) );
			} );
		} );

		function setColumns( columns ) {
			OPTIONS.forEach( function ( option ) {
				grid.classList.toggle( 'dak-directory-grid-cols-' + option, option === columns );
			} );

			buttons.forEach( function ( button ) {
				var isActive = button.getAttribute( 'data-directory-columns' ) === columns;
				button.classList.toggle( 'is-active', isActive );
				button.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			} );

			try {
				window.localStorage.setItem( STORAGE_KEY, columns );
			} catch ( e ) {
				// Private browsing / storage disabled — the choice just won't persist.
			}
		}
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
