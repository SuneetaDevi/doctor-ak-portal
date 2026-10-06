/**
 * Doctor AK Portal — Doctors directory (templates/directory/doctors-directory.php).
 *
 * Every doctor is already in the page. On each change this filters the
 * full set (search, specialty, visit type, availability, gender, city),
 * sorts the matches, and only then slices out the current page — so the
 * count ("Showing 1–12 of 53 doctors"), the order and the pages always
 * describe the same list. The initial render runs through the same path,
 * so "Most experienced" is applied from the first paint.
 *
 * The state lives in the URL (history.replaceState: q, specialization,
 * visit, availability, gender, city, sort, page), so Back from a doctor's
 * profile returns to the same filtered page. `?q=` (or the older `?s=`),
 * `?specialization=` and `?city=` links from the header, footer and home
 * page land pre-filtered.
 *
 * Below 1024px the filters are a modal drawer (focus moved in and kept
 * there, Escape / backdrop / close button return focus to "Filters").
 * "Near me" asks for the location only after it is clicked, and a refusal
 * just leaves the ordinary filters working.
 */
( function () {
	'use strict';

	var PAGE_SIZE = 12;

	// Approximate city centres, for "Near me" (same list the home page uses).
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

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
		var root = document.querySelector( '[data-dak-dir-doctors]' );
		var list = root ? root.querySelector( '[data-dak-dir-list]' ) : null;

		if ( ! list ) {
			return;
		}

		var strings = {};

		try {
			strings = JSON.parse( root.getAttribute( 'data-strings' ) || '{}' );
		} catch ( e ) {
			strings = {};
		}

		var $ = function ( sel ) {
			return root.querySelector( sel );
		};
		var $$ = function ( sel ) {
			return Array.prototype.slice.call( root.querySelectorAll( sel ) );
		};

		var items = $$( '[data-dak-dir-doctor]' );
		var search = $( '#dak-dir-q' );
		var clearSearch = $( '[data-dak-dir-clear-search]' );
		var sortSelect = $( '#dak-dir-sort' );
		var citySelect = $( '#dak-dir-city' );
		var countEl = $( '[data-dak-dir-count]' );
		var activeWrap = $( '[data-dak-dir-active]' );
		var chipList = $( '[data-dak-dir-chips]' );
		var empty = $( '[data-dak-dir-empty]' );
		var pager = $( '[data-dak-dir-pagination]' );
		var pageList = $( '[data-dak-dir-pages]' );
		var filterCount = $( '[data-dak-dir-filter-count]' );
		var showButton = $( '[data-dak-dir-show-results]' );
		var page = 1;
		var searchTimer = 0;

		var fmt = function ( key, values ) {
			var list = [].concat( values );
			var next = 0;

			return ( strings[ key ] || '' ).replace( /%(?:(\d)\$)?s/g, function ( match, position ) {
				var value = position ? list[ position - 1 ] : list[ next++ ];

				return undefined === value ? '' : String( value );
			} );
		};

		var number = function ( n ) {
			return Number( n ).toLocaleString();
		};

		/* -------------------------------------------------------- State */

		function radioValue( name ) {
			var checked = root.querySelector( 'input[name="' + name + '"]:checked' );

			return checked ? checked.value : '';
		}

		function checkedValues( name ) {
			return $$( 'input[name="' + name + '"]:checked' ).map( function ( input ) {
				return input.value;
			} );
		}

		function setRadio( name, value ) {
			var target = root.querySelector( 'input[name="' + name + '"][value="' + cssEscape( value ) + '"]' ) || root.querySelector( 'input[name="' + name + '"][value=""]' );

			if ( target ) {
				target.checked = true;
			}
		}

		function setChecks( name, values ) {
			$$( 'input[name="' + name + '"]' ).forEach( function ( input ) {
				input.checked = values.indexOf( input.value ) !== -1;
			} );
		}

		function cssEscape( value ) {
			return String( value ).replace( /["\\]/g, '\\$&' );
		}

		function state() {
			return {
				q: search ? search.value.trim() : '',
				specialty: radioValue( 'specialty' ),
				visit: checkedValues( 'visit' ),
				availability: radioValue( 'availability' ),
				gender: checkedValues( 'gender' ),
				city: citySelect ? citySelect.value : '',
				sort: sortSelect ? sortSelect.value : 'experience'
			};
		}

		/** Reads ?q/?s, ?specialization (label or slug), ?visit, ?availability, ?gender, ?city, ?sort, ?page. */
		function readUrl() {
			if ( ! window.URLSearchParams ) {
				return;
			}

			var params = new URLSearchParams( window.location.search );
			var q = params.get( 'q' ) || params.get( 's' ) || '';

			if ( search && q ) {
				search.value = q;
			}

			var spec = ( params.get( 'specialization' ) || '' ).toLowerCase().replace( /-/g, ' ' ).trim();

			if ( spec ) {
				// Labels are the keys; a slug like "general-physician" matches too.
				var match = $$( 'input[name="specialty"]' ).filter( function ( input ) {
					return input.value === spec || input.value.replace( /-/g, ' ' ) === spec;
				} )[ 0 ];

				if ( match ) {
					match.checked = true;
					revealOption( match );
				}
			}

			setChecks( 'visit', ( params.get( 'visit' ) || '' ).split( ',' ) );
			setRadio( 'availability', params.get( 'availability' ) || '' );
			setChecks( 'gender', ( params.get( 'gender' ) || '' ).split( ',' ) );

			if ( citySelect && params.get( 'city' ) ) {
				var city = params.get( 'city' ).toLowerCase();

				if ( citySelect.querySelector( 'option[value="' + cssEscape( city ) + '"]' ) ) {
					citySelect.value = city;
				}
			}

			if ( sortSelect && params.get( 'sort' ) && sortSelect.querySelector( 'option[value="' + cssEscape( params.get( 'sort' ) ) + '"]' ) ) {
				sortSelect.value = params.get( 'sort' );
			}

			page = Math.max( 1, parseInt( params.get( 'page' ), 10 ) || 1 );
		}

		function writeUrl( s ) {
			if ( ! window.history || ! window.history.replaceState || ! window.URLSearchParams ) {
				return;
			}

			var params = new URLSearchParams( window.location.search );

			[ 'q', 's', 'specialization', 'visit', 'availability', 'gender', 'city', 'sort', 'page' ].forEach( function ( key ) {
				params.delete( key );
			} );

			if ( s.q ) {
				params.set( 'q', s.q );
			}
			if ( s.specialty ) {
				params.set( 'specialization', s.specialty );
			}
			if ( s.visit.length ) {
				params.set( 'visit', s.visit.join( ',' ) );
			}
			if ( s.availability ) {
				params.set( 'availability', s.availability );
			}
			if ( s.gender.length ) {
				params.set( 'gender', s.gender.join( ',' ) );
			}
			if ( s.city ) {
				params.set( 'city', s.city );
			}
			if ( s.sort && 'experience' !== s.sort ) {
				params.set( 'sort', s.sort );
			}
			if ( page > 1 ) {
				params.set( 'page', page );
			}

			var query = params.toString();

			window.history.replaceState( window.history.state, '', window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash );
		}

		/* -------------------------------------------------------- Filter, sort, page */

		function matches( item, s ) {
			var q = s.q.toLowerCase().replace( /^(dr|doctor)\.?\s+/, '' );

			if ( q && ( item.getAttribute( 'data-search' ) || '' ).indexOf( q ) === -1 ) {
				return false;
			}

			if ( s.specialty && ( item.getAttribute( 'data-specialties' ) || '' ).split( '|' ).indexOf( s.specialty ) === -1 ) {
				return false;
			}

			if ( s.visit.length ) {
				var visits = ( item.getAttribute( 'data-visit' ) || '' ).split( ',' );

				if ( ! s.visit.some( function ( v ) {
					return visits.indexOf( v ) !== -1;
				} ) ) {
					return false;
				}
			}

			var availability = item.getAttribute( 'data-availability' ) || '';

			if ( 'today' === s.availability && 'today' !== availability ) {
				return false;
			}

			if ( 'week' === s.availability && '' === availability ) {
				return false;
			}

			if ( s.gender.length && s.gender.indexOf( item.getAttribute( 'data-gender' ) || '' ) === -1 ) {
				return false;
			}

			if ( s.city && ( item.getAttribute( 'data-cities' ) || '' ).split( ',' ).indexOf( s.city ) === -1 ) {
				return false;
			}

			return true;
		}

		function experience( item ) {
			var raw = item.getAttribute( 'data-experience' );

			return '' === raw || null === raw ? null : parseInt( raw, 10 );
		}

		function byName( a, b ) {
			return ( a.getAttribute( 'data-name' ) || '' ).localeCompare( b.getAttribute( 'data-name' ) || '' )
				|| ( parseInt( a.getAttribute( 'data-id' ), 10 ) - parseInt( b.getAttribute( 'data-id' ), 10 ) );
		}

		function byExperience( a, b ) {
			var ea = experience( a );
			var eb = experience( b );

			if ( ea !== eb ) {
				if ( null === ea ) {
					return 1;
				}
				if ( null === eb ) {
					return -1;
				}
				return eb - ea;
			}

			return byName( a, b );
		}

		function compare( mode ) {
			if ( 'name-asc' === mode ) {
				return byName;
			}

			if ( 'name-desc' === mode ) {
				return function ( a, b ) {
					return byName( b, a );
				};
			}

			if ( 'available' === mode ) {
				return function ( a, b ) {
					var na = a.getAttribute( 'data-next-at' ) || '';
					var nb = b.getAttribute( 'data-next-at' ) || '';

					if ( na !== nb ) {
						if ( '' === na ) {
							return 1;
						}
						if ( '' === nb ) {
							return -1;
						}
						return na < nb ? -1 : 1;
					}

					return byExperience( a, b );
				};
			}

			return byExperience;
		}

		function apply( options ) {
			options = options || {};

			var s = state();

			if ( options.resetPage ) {
				page = 1;
			}

			var matching = items.filter( function ( item ) {
				return matches( item, s );
			} ).sort( compare( s.sort ) );

			var pages = Math.max( 1, Math.ceil( matching.length / PAGE_SIZE ) );

			page = Math.min( Math.max( 1, page ), pages );

			var start = ( page - 1 ) * PAGE_SIZE;
			var shown = matching.slice( start, start + PAGE_SIZE );

			// Matching doctors in order first, then the rest (hidden).
			var fragment = document.createDocumentFragment();

			matching.concat( items.filter( function ( item ) {
				return matching.indexOf( item ) === -1;
			} ) ).forEach( function ( item ) {
				item.hidden = shown.indexOf( item ) === -1;
				fragment.appendChild( item );
			} );
			list.appendChild( fragment );

			updateCount( matching.length, start, shown.length );
			renderPager( pages );
			renderChips( s );

			if ( empty ) {
				empty.hidden = matching.length > 0;
			}

			if ( clearSearch ) {
				clearSearch.hidden = '' === s.q;
			}

			if ( showButton ) {
				showButton.textContent = fmt( 'showButton', number( matching.length ) );
			}

			writeUrl( s );
		}

		function updateCount( total, start, shownCount ) {
			if ( ! countEl ) {
				return;
			}

			var text;

			if ( 0 === total ) {
				text = strings.none;
			} else if ( 1 === total ) {
				text = strings.showingOne;
			} else if ( total <= PAGE_SIZE ) {
				text = fmt( 'showingAll', number( total ) );
			} else {
				text = fmt( 'showing', [ number( start + 1 ), number( start + shownCount ), number( total ) ] );
			}

			if ( countEl.textContent !== text ) {
				countEl.textContent = text;
			}
		}

		function renderPager( pages ) {
			if ( ! pager || ! pageList ) {
				return;
			}

			pager.hidden = pages <= 1;
			pageList.textContent = '';

			if ( pages <= 1 ) {
				return;
			}

			pageNumbers( page, pages ).forEach( function ( entry ) {
				var li = document.createElement( 'li' );

				if ( '…' === entry ) {
					li.className = 'dak-dir-page-gap';
					li.textContent = '…';
					li.setAttribute( 'aria-hidden', 'true' );
				} else {
					var button = document.createElement( 'button' );

					button.type = 'button';
					button.className = 'dak-dir-page';
					button.textContent = String( entry );
					button.setAttribute( 'aria-label', fmt( 'page', entry ) );
					button.setAttribute( 'data-dak-dir-goto', entry );

					if ( entry === page ) {
						button.setAttribute( 'aria-current', 'page' );
					}

					li.appendChild( button );
				}

				pageList.appendChild( li );
			} );

			pager.querySelector( '[data-dak-dir-page="prev"]' ).disabled = page <= 1;
			pager.querySelector( '[data-dak-dir-page="next"]' ).disabled = page >= pages;
		}

		function pageNumbers( current, total ) {
			var out = [];

			for ( var i = 1; i <= total; i++ ) {
				if ( 1 === i || total === i || Math.abs( i - current ) <= 1 ) {
					out.push( i );
				} else if ( '…' !== out[ out.length - 1 ] ) {
					out.push( '…' );
				}
			}

			return out;
		}

		function goToPage( target ) {
			page = target;
			apply();

			var top = root.querySelector( '.dak-dir-results' );

			if ( top && top.getBoundingClientRect().top < 0 ) {
				top.scrollIntoView( { block: 'start' } );
			}
		}

		/* -------------------------------------------------------- Active filters */

		function labelFor( input ) {
			return input.getAttribute( 'data-label' ) || input.value;
		}

		function renderChips( s ) {
			var chips = [];

			if ( s.q ) {
				chips.push( { label: fmt( 'searchChip', s.q ), clear: function () {
					search.value = '';
				} } );
			}

			var spec = root.querySelector( 'input[name="specialty"]:checked' );

			if ( spec && spec.value ) {
				chips.push( { label: labelFor( spec ), clear: function () {
					setRadio( 'specialty', '' );
				} } );
			}

			[ 'visit', 'gender' ].forEach( function ( name ) {
				$$( 'input[name="' + name + '"]:checked' ).forEach( function ( input ) {
					chips.push( { label: labelFor( input ), clear: function () {
						input.checked = false;
					} } );
				} );
			} );

			var avail = root.querySelector( 'input[name="availability"]:checked' );

			if ( avail && avail.value ) {
				chips.push( { label: labelFor( avail ), clear: function () {
					setRadio( 'availability', '' );
				} } );
			}

			if ( citySelect && citySelect.value ) {
				var option = citySelect.options[ citySelect.selectedIndex ];

				chips.push( { label: option.getAttribute( 'data-label' ) || option.textContent, clear: function () {
					citySelect.value = '';
					setNearMe( false );
				} } );
			}

			if ( chipList ) {
				chipList.textContent = '';

				chips.forEach( function ( chip ) {
					var li = document.createElement( 'li' );
					var button = document.createElement( 'button' );

					button.type = 'button';
					button.className = 'dak-dir-chip';
					button.setAttribute( 'aria-label', fmt( 'remove', chip.label ) );
					button.innerHTML = '<span></span><svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l8 8M14 6l-8 8"/></svg>';
					button.firstChild.textContent = chip.label;
					button.addEventListener( 'click', function () {
						chip.clear();
						apply( { resetPage: true } );

						// Keep focus somewhere sensible after the chip disappears.
						var next = chipList.querySelector( '.dak-dir-chip' ) || search;

						if ( next ) {
							next.focus();
						}
					} );

					li.appendChild( button );
					chipList.appendChild( li );
				} );
			}

			if ( activeWrap ) {
				activeWrap.hidden = 0 === chips.length;
			}

			// The "Filters" button's badge counts filters, not the search.
			var filters = chips.length - ( s.q ? 1 : 0 );

			if ( filterCount ) {
				filterCount.hidden = 0 === filters;
				filterCount.textContent = filters > 0 ? String( filters ) : '';
			}

			$$( '[data-dak-dir-clear-all]' ).forEach( function ( button ) {
				if ( ! button.closest( '[data-dak-dir-empty]' ) && ! button.closest( '[data-dak-dir-active]' ) ) {
					button.hidden = 0 === chips.length;
				}
			} );
		}

		function clearAll() {
			if ( search ) {
				search.value = '';
			}

			setRadio( 'specialty', '' );
			setChecks( 'visit', [] );
			setRadio( 'availability', '' );
			setChecks( 'gender', [] );

			if ( citySelect ) {
				citySelect.value = '';
			}

			setNearMe( false );
			apply( { resetPage: true } );
		}

		/* -------------------------------------------------------- Specialty list */

		var specFind = $( '[data-dak-dir-spec-find]' );
		var specMore = $( '[data-dak-dir-spec-more]' );
		var specExpanded = false;

		function revealOption( input ) {
			var option = input.closest( '[data-dak-dir-extra]' );

			if ( option && specMore ) {
				setSpecExpanded( true );
			}
		}

		function setSpecExpanded( expanded ) {
			specExpanded = expanded;

			if ( specMore ) {
				specMore.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
				specMore.textContent = expanded ? strings.less : fmt( 'more', specMore.getAttribute( 'data-count' ) );
			}

			filterSpecOptions();
		}

		function filterSpecOptions() {
			var term = specFind ? specFind.value.trim().toLowerCase() : '';

			$$( '[data-dak-dir-spec-option]' ).forEach( function ( option ) {
				var hit = '' === term || ( option.getAttribute( 'data-name' ) || '' ).indexOf( term ) !== -1;
				var extra = option.hasAttribute( 'data-dak-dir-extra' );
				var checked = option.querySelector( 'input' ).checked;

				// While searching, every match shows; otherwise extras only when expanded (or chosen).
				option.hidden = term ? ! hit : ( extra && ! specExpanded && ! checked );
			} );

			if ( specMore ) {
				specMore.hidden = '' !== term;
			}
		}

		if ( specFind ) {
			specFind.addEventListener( 'input', filterSpecOptions );
		}

		if ( specMore ) {
			specMore.addEventListener( 'click', function () {
				setSpecExpanded( ! specExpanded );
			} );
		}

		/* -------------------------------------------------------- Near me */

		var nearMe = $( '[data-dak-dir-nearme]' );
		var nearLabel = $( '[data-dak-dir-nearme-label]' );
		var nearStatus = $( '[data-dak-dir-nearme-status]' );

		function nearStatusText( text ) {
			if ( nearStatus ) {
				nearStatus.textContent = text || '';
				nearStatus.hidden = ! text;
			}
		}

		function setNearMe( on, cityLabel ) {
			if ( ! nearMe ) {
				return;
			}

			nearMe.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			nearMe.classList.toggle( 'is-active', !! on );

			if ( nearLabel ) {
				nearLabel.textContent = on ? fmt( 'nearCity', cityLabel ) : strings.nearMe;
			}

			if ( ! on ) {
				nearStatusText( '' );
			}
		}

		function distanceKm( lat1, lng1, lat2, lng2 ) {
			var r = Math.PI / 180;
			var dLat = ( lat2 - lat1 ) * r;
			var dLng = ( lng2 - lng1 ) * r;
			var a = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) + Math.cos( lat1 * r ) * Math.cos( lat2 * r ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );

			return 6371 * 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
		}

		if ( nearMe && citySelect ) {
			nearMe.addEventListener( 'click', function () {
				if ( 'true' === nearMe.getAttribute( 'aria-pressed' ) ) {
					citySelect.value = '';
					setNearMe( false );
					apply( { resetPage: true } );
					return;
				}

				if ( ! navigator.geolocation ) {
					nearStatusText( strings.unsupported );
					return;
				}

				nearStatusText( strings.locating );
				nearMe.disabled = true;

				navigator.geolocation.getCurrentPosition( function ( position ) {
					nearMe.disabled = false;

					var best = null;

					Array.prototype.forEach.call( citySelect.options, function ( option ) {
						if ( ! option.value ) {
							return;
						}

						var label = ( option.getAttribute( 'data-label' ) || '' ).toLowerCase();
						var known = PK_CITIES.filter( function ( c ) {
							return c.name.toLowerCase() === label;
						} )[ 0 ];

						if ( known ) {
							var d = distanceKm( position.coords.latitude, position.coords.longitude, known.lat, known.lng );

							if ( ! best || d < best.d ) {
								best = { option: option, d: d };
							}
						}
					} );

					if ( ! best ) {
						nearStatusText( strings.noCity );
						return;
					}

					citySelect.value = best.option.value;
					setNearMe( true, best.option.getAttribute( 'data-label' ) );
					nearStatusText( '' );
					apply( { resetPage: true } );
				}, function () {
					nearMe.disabled = false;
					setNearMe( false );
					nearStatusText( strings.denied );
				}, { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 } );
			} );
		}

		/* -------------------------------------------------------- Drawer (small screens) */

		var drawer = $( '[data-dak-dir-filters]' );
		var openButton = $( '[data-dak-dir-open-filters]' );
		var scrim = $( '.dak-dir-scrim' );
		var drawerQuery = window.matchMedia( '(max-width: 1023px)' );
		var drawerOpen = false;

		function openDrawer() {
			if ( ! drawer || ! drawerQuery.matches ) {
				return;
			}

			drawerOpen = true;
			drawer.classList.add( 'is-open' );
			drawer.setAttribute( 'role', 'dialog' );
			drawer.setAttribute( 'aria-modal', 'true' );
			drawer.setAttribute( 'aria-label', drawer.getAttribute( 'data-label' ) || 'Filters' );
			openButton.setAttribute( 'aria-expanded', 'true' );
			document.documentElement.classList.add( 'dak-dir-locked' );

			if ( scrim ) {
				scrim.hidden = false;
			}

			var first = drawer.querySelector( '[data-dak-dir-close-filters]' );

			if ( first ) {
				first.focus();
			}
		}

		function closeDrawer( restore ) {
			if ( ! drawerOpen ) {
				return;
			}

			drawerOpen = false;
			drawer.classList.remove( 'is-open' );
			drawer.removeAttribute( 'role' );
			drawer.removeAttribute( 'aria-modal' );
			drawer.removeAttribute( 'aria-label' );
			openButton.setAttribute( 'aria-expanded', 'false' );
			document.documentElement.classList.remove( 'dak-dir-locked' );

			if ( scrim ) {
				scrim.hidden = true;
			}

			if ( restore && openButton ) {
				openButton.focus();
			}
		}

		if ( openButton ) {
			openButton.addEventListener( 'click', openDrawer );
		}

		$$( '[data-dak-dir-close-filters]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				closeDrawer( true );
			} );
		} );

		var onBreakpoint = function () {
			if ( ! drawerQuery.matches ) {
				closeDrawer( false );
			}
		};

		if ( drawerQuery.addEventListener ) {
			drawerQuery.addEventListener( 'change', onBreakpoint );
		} else if ( drawerQuery.addListener ) {
			drawerQuery.addListener( onBreakpoint );
		}

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! drawerOpen ) {
				return;
			}

			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeDrawer( true );
				return;
			}

			if ( 'Tab' !== event.key ) {
				return;
			}

			var focusables = Array.prototype.filter.call(
				drawer.querySelectorAll( 'button, input, select, a[href]' ),
				function ( el ) {
					return ! el.disabled && el.offsetParent !== null;
				}
			);

			if ( ! focusables.length ) {
				return;
			}

			var first = focusables[ 0 ];
			var last = focusables[ focusables.length - 1 ];

			if ( ! drawer.contains( document.activeElement ) ) {
				event.preventDefault();
				first.focus();
			} else if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );

		/* -------------------------------------------------------- Wiring */

		root.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( target === sortSelect ) {
				apply( { resetPage: true } );
				return;
			}

			if ( target === citySelect ) {
				setNearMe( false );
			}

			if ( target.name && [ 'specialty', 'visit', 'availability', 'gender', 'city' ].indexOf( target.name ) !== -1 ) {
				apply( { resetPage: true } );
			}
		} );

		if ( search ) {
			search.addEventListener( 'input', function () {
				window.clearTimeout( searchTimer );
				searchTimer = window.setTimeout( function () {
					apply( { resetPage: true } );
				}, 150 );
			} );

			search.form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				window.clearTimeout( searchTimer );
				apply( { resetPage: true } );
			} );
		}

		if ( clearSearch ) {
			clearSearch.addEventListener( 'click', function () {
				search.value = '';
				apply( { resetPage: true } );
				search.focus();
			} );
		}

		$$( '[data-dak-dir-clear-all]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				clearAll();

				if ( search ) {
					search.focus();
				}
			} );
		} );

		if ( pager ) {
			pager.addEventListener( 'click', function ( event ) {
				var step = event.target.closest( '[data-dak-dir-page]' );
				var pageButton = event.target.closest( '[data-dak-dir-goto]' );

				if ( step && ! step.disabled ) {
					goToPage( page + ( 'next' === step.getAttribute( 'data-dak-dir-page' ) ? 1 : -1 ) );
				} else if ( pageButton ) {
					goToPage( parseInt( pageButton.getAttribute( 'data-dak-dir-goto' ), 10 ) );
				}
			} );
		}

		// "+N locations" disclosures on the cards.
		list.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest( '[data-dak-dir-disclosure]' );

			if ( ! toggle ) {
				return;
			}

			var panel = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
			var open = 'true' !== toggle.getAttribute( 'aria-expanded' );

			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			if ( panel ) {
				panel.hidden = ! open;
			}
		} );

		readUrl();
		filterSpecOptions();
		apply();
	}
}() );
