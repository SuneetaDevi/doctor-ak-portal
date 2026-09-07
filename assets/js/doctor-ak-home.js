/**
 * Doctor AK Portal — Home page ([dak_home]):
 *  - opens the video grid's lightbox modal when a `[data-dak-home-video]`
 *    card is clicked.
 *  - the hero search bar: clicking it opens a "Search for doctors" popup
 *    (matching the reference design) with a Location row (auto-detected via
 *    the browser's Geolocation API, with a manual "Detect" fallback and a
 *    quick-pick list of the clinic's registered cities) and a free-text
 *    search box that lists matching doctors live, right in the popup, as you
 *    type — window.dakHomeSearch.doctors (wp_localize_script(), see
 *    Home_Page::render()) is the full doctor list this filters client-side.
 *    Enter never submits/navigates from here; picking a result (or the
 *    Search button, for a full directory search with both filters applied)
 *    are the only ways this popup sends you anywhere.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initVideoModal();
		initHeroSearch();
	} );

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

	/**
	 * Wires the hero search trigger to the "Search for doctors" popup: opens
	 * it (and runs the first location detection) on click, closes on the
	 * overlay/×/Escape, and handles both ways the Location field gets set —
	 * auto-detect and clicking one of the city quick-picks.
	 */
	function initHeroSearch() {
		var trigger = document.getElementById( 'dak-home-hero-search-trigger' );
		var triggerLocation = document.getElementById( 'dak-home-hero-search-trigger-location' );
		var modal = document.getElementById( 'dak-home-search-modal' );

		if ( ! trigger || ! modal ) {
			return;
		}

		var RESULTS_LIMIT = 8;

		var overlay = document.getElementById( 'dak-home-search-modal-overlay' );
		var closeButton = document.getElementById( 'dak-home-search-modal-close' );
		var queryInput = document.getElementById( 'dak-home-search-modal-query-input' );
		var queryClearButton = document.getElementById( 'dak-home-search-modal-query-clear' );
		var locationInput = document.getElementById( 'dak-home-search-modal-location-input' );
		var cityHidden = document.getElementById( 'dak-home-search-modal-city' );
		var detectButton = document.getElementById( 'dak-home-search-modal-detect' );
		var citiesContainer = document.getElementById( 'dak-home-search-modal-cities' );
		var cityButtons = document.querySelectorAll( '.dak-home-search-modal-city' );
		var resultsContainer = document.getElementById( 'dak-home-search-modal-results' );
		var resultsList = document.getElementById( 'dak-home-search-modal-results-list' );
		var noResultsEl = document.getElementById( 'dak-home-search-modal-no-results' );
		var allDoctors = ( window.dakHomeSearch && window.dakHomeSearch.doctors ) || [];
		var hasDetectedOnce = false;
		// Whatever the Location field showed before any typing/detecting —
		// the server-rendered (and already-translated) "Any city" — restored
		// when the field is cleared back out, rather than a hardcoded string.
		var defaultLocationLabel = triggerLocation ? triggerLocation.textContent : '';
		var locationPlaceholder = locationInput ? locationInput.placeholder : '';

		trigger.addEventListener( 'click', openModal );
		overlay.addEventListener( 'click', closeModal );
		closeButton.addEventListener( 'click', closeModal );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! modal.hasAttribute( 'aria-hidden' ) ) {
				closeModal();
			}
		} );

		cityButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				selectCity( button.getAttribute( 'data-city-slug' ), button.getAttribute( 'data-city-label' ) );
			} );
		} );

		if ( detectButton ) {
			if ( navigator.geolocation ) {
				detectButton.addEventListener( 'click', detectLocation );
			} else {
				detectButton.disabled = true;
			}
		}

		if ( queryInput ) {
			queryInput.addEventListener( 'input', function () {
				renderResults( queryInput.value );
			} );

			// The popup's own live results (or the Search button below, for a
			// deliberate full-directory search) are the only ways out of
			// here — Enter used to submit the form and jump straight to the
			// directory page, which is easy to trigger by accident mid-typo.
			queryInput.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
				}
			} );
		}

		if ( queryClearButton ) {
			queryClearButton.addEventListener( 'click', function () {
				queryInput.value = '';
				queryInput.focus();
				renderResults( '' );
			} );
		}

		if ( locationInput ) {
			locationInput.addEventListener( 'input', handleLocationInput );

			// Same reasoning as the query field above — typing a city and
			// hitting Enter shouldn't submit the form out from under the
			// visitor while they're still typing.
			locationInput.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
				}
			} );
		}

		function openModal() {
			modal.removeAttribute( 'aria-hidden' );
			modal.classList.add( 'is-open' );

			if ( queryInput ) {
				// Deferred a tick: at the exact instant this click handler
				// runs, the browser still considers the popup hidden (the
				// aria-hidden removal above hasn't been style-recalculated
				// yet), so a synchronous focus() here is silently ignored.
				setTimeout( function () {
					queryInput.focus();
				}, 0 );
			}

			if ( ! hasDetectedOnce ) {
				hasDetectedOnce = true;
				detectLocation();
			}
		}

		function closeModal() {
			modal.setAttribute( 'aria-hidden', 'true' );
			modal.classList.remove( 'is-open' );

			// Reopening always starts from a clean search — Location stays
			// as last set (detected or picked), since that's meant to
			// persist across searches.
			if ( queryInput ) {
				queryInput.value = '';
				renderResults( '' );
			}
		}

		function selectCity( slug, label ) {
			if ( cityHidden ) {
				cityHidden.value = slug;
			}

			if ( locationInput ) {
				locationInput.value = label;
			}

			if ( triggerLocation ) {
				triggerLocation.textContent = label;
			}

			cityButtons.forEach( function ( button ) {
				button.classList.remove( 'dak-hidden' );
				button.classList.toggle( 'is-selected', button.getAttribute( 'data-city-slug' ) === slug );
			} );

			// Picking a city re-filters an already-typed doctor search down
			// to that city immediately, rather than waiting for the next
			// keystroke — a no-op (still shows the quick-pick list) while
			// the search box is empty.
			if ( queryInput ) {
				renderResults( queryInput.value );
			}
		}

		/**
		 * Free-typing in the Location field (it's no longer read-only —
		 * Detect and the quick-pick list are conveniences, not the only way
		 * in): filters the quick-pick list down to matching cities as a
		 * lightweight autocomplete, and resolves the hidden `city` slug the
		 * moment what's typed exactly matches one of them (case-insensitive)
		 * — an unmatched, in-progress fragment just falls back to searching
		 * every city rather than submitting a stale/wrong slug.
		 */
		function handleLocationInput() {
			var query = locationInput.value.trim().toLowerCase();

			// Editing Location is what the quick-pick list is for — bring it
			// back to the front even if a doctor search was showing results.
			if ( resultsContainer ) {
				resultsContainer.classList.add( 'dak-hidden' );
			}

			if ( citiesContainer ) {
				citiesContainer.classList.remove( 'dak-hidden' );
			}

			var exactMatch = null;

			cityButtons.forEach( function ( button ) {
				var label = button.getAttribute( 'data-city-label' ) || '';
				var labelLower = label.toLowerCase();
				var isVisible = '' === query || labelLower.indexOf( query ) !== -1;

				button.classList.toggle( 'dak-hidden', ! isVisible );
				button.classList.toggle( 'is-selected', '' !== query && labelLower === query );

				if ( labelLower === query ) {
					exactMatch = button;
				}
			} );

			if ( cityHidden ) {
				cityHidden.value = exactMatch ? exactMatch.getAttribute( 'data-city-slug' ) : '';
			}

			if ( triggerLocation ) {
				triggerLocation.textContent = '' === query
					? defaultLocationLabel
					: ( exactMatch ? exactMatch.getAttribute( 'data-city-label' ) : locationInput.value );
			}

			// Typing out a city's full name (rather than clicking it) is
			// still a real selection, and clearing the field back to empty
			// is just as definitively "any city" — both re-filter an
			// already-typed doctor search immediately, the same way
			// selectCity() does. A fragment mid-typed that matches neither
			// is left alone (still just showing the quick-pick suggestions
			// above) rather than flickering the results through a
			// momentarily-wrong filter on every keystroke.
			if ( ( exactMatch || '' === query ) && queryInput && '' !== queryInput.value.trim() ) {
				renderResults( queryInput.value );
			}
		}

		function detectLocation() {
			if ( ! navigator.geolocation ) {
				return;
			}

			if ( detectButton ) {
				detectButton.classList.add( 'is-detecting' );
			}

			if ( locationInput ) {
				locationInput.placeholder = locationInput.getAttribute( 'data-detecting-placeholder' );
			}

			navigator.geolocation.getCurrentPosition(
				function ( position ) {
					if ( detectButton ) {
						detectButton.classList.remove( 'is-detecting' );
					}

					if ( locationInput ) {
						locationInput.placeholder = locationPlaceholder;
					}

					applyNearestCity( position.coords.latitude, position.coords.longitude );
				},
				function () {
					// Permission denied, unavailable, or timed out — the
					// city quick-picks (and typing a city in by hand) cover
					// the rest, so this fails silently rather than showing
					// an error.
					if ( detectButton ) {
						detectButton.classList.remove( 'is-detecting' );
					}

					if ( locationInput ) {
						locationInput.placeholder = locationPlaceholder;
					}
				},
				{ enableHighAccuracy: false, timeout: 8000, maximumAge: 600000 }
			);
		}

		function applyNearestCity( lat, lng ) {
			var nearest = PK_CITIES
				.map( function ( city ) {
					return { name: city.name, distance: distanceKm( lat, lng, city.lat, city.lng ) };
				} )
				.sort( function ( a, b ) {
					return a.distance - b.distance;
				} );

			for ( var i = 0; i < nearest.length; i++ ) {
				var button = findCityButtonByLabel( nearest[ i ].name );

				if ( button ) {
					selectCity( button.getAttribute( 'data-city-slug' ), button.getAttribute( 'data-city-label' ) );
					return;
				}
			}
		}

		function findCityButtonByLabel( label ) {
			var target = label.trim().toLowerCase();

			for ( var i = 0; i < cityButtons.length; i++ ) {
				if ( cityButtons[ i ].getAttribute( 'data-city-label' ).trim().toLowerCase() === target ) {
					return cityButtons[ i ];
				}
			}

			return null;
		}

		/**
		 * Filters window.dakHomeSearch.doctors by name/specialty — and, if a
		 * city is currently selected in the Location field, to just the
		 * doctors practising there — and renders the "Doctors" results list.
		 * Swaps out the city quick-picks while a search is in progress, and
		 * back once the query is cleared.
		 *
		 * @param {string} rawQuery Current value of the search input.
		 */
		function renderResults( rawQuery ) {
			var query = rawQuery.trim().toLowerCase();
			var selectedCity = cityHidden ? cityHidden.value : '';

			if ( queryClearButton ) {
				queryClearButton.classList.toggle( 'dak-hidden', '' === query );
			}

			if ( '' === query ) {
				if ( resultsContainer ) {
					resultsContainer.classList.add( 'dak-hidden' );
				}

				if ( citiesContainer ) {
					citiesContainer.classList.remove( 'dak-hidden' );
				}

				return;
			}

			if ( citiesContainer ) {
				citiesContainer.classList.add( 'dak-hidden' );
			}

			if ( ! resultsContainer || ! resultsList ) {
				return;
			}

			resultsContainer.classList.remove( 'dak-hidden' );
			resultsList.innerHTML = '';

			var matches = allDoctors.filter( function ( doctor ) {
				var name = ( doctor.name || '' ).toLowerCase();
				var specialty = ( doctor.specialty || '' ).toLowerCase();
				var matchesQuery = name.indexOf( query ) !== -1 || specialty.indexOf( query ) !== -1;
				var matchesCity = '' === selectedCity || ( doctor.citySlugs || [] ).indexOf( selectedCity ) !== -1;

				return matchesQuery && matchesCity;
			} ).slice( 0, RESULTS_LIMIT );

			if ( noResultsEl ) {
				noResultsEl.classList.toggle( 'dak-hidden', matches.length > 0 );
			}

			matches.forEach( function ( doctor ) {
				resultsList.appendChild( buildResultRow( doctor, query ) );
			} );
		}

		/**
		 * Builds one clickable result row — avatar (or initials), name with
		 * the matched substring highlighted, specialty underneath. Built with
		 * DOM nodes rather than innerHTML string-building since doctor names
		 * are real user-submitted data, not markup this file should trust.
		 *
		 * @param {Object} doctor { name, specialty, avatarUrl, url }.
		 * @param {string} query  Lowercased search query to highlight within the name.
		 * @return {HTMLElement}
		 */
		function buildResultRow( doctor, query ) {
			var row = document.createElement( 'button' );
			row.type = 'button';
			row.className = 'dak-home-search-modal-result';

			row.addEventListener( 'click', function () {
				if ( doctor.url ) {
					window.location.href = doctor.url;
				}
			} );

			var avatar = document.createElement( 'span' );
			avatar.className = 'dak-home-search-modal-result-avatar';

			if ( doctor.avatarUrl ) {
				var img = document.createElement( 'img' );
				img.src = doctor.avatarUrl;
				img.alt = '';
				avatar.appendChild( img );
			} else {
				avatar.textContent = initialsOf( doctor.name || '' );
			}

			var body = document.createElement( 'span' );
			body.className = 'dak-home-search-modal-result-body';

			var nameEl = document.createElement( 'strong' );
			appendHighlighted( nameEl, doctor.name || '', query );
			body.appendChild( nameEl );

			if ( doctor.specialty ) {
				var specialtyEl = document.createElement( 'span' );
				specialtyEl.textContent = doctor.specialty;
				body.appendChild( specialtyEl );
			}

			row.appendChild( avatar );
			row.appendChild( body );

			return row;
		}

		/**
		 * Appends `text` to `container` as text nodes, wrapping the first
		 * case-insensitive match of `query` in a <mark> — plain DOM
		 * manipulation throughout, so nothing in `text` is ever parsed as
		 * markup.
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
