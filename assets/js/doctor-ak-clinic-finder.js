/**
 * Doctor AK Portal — clinic finder ([clinics_directory]).
 *
 * The server already rendered every clinic card, with non-matching ones
 * hidden for whatever ?q=&city=&area=&sort= the URL carried. This filters
 * the same complete list live as the visitor types or picks — with exactly
 * the same rules as Clinics_Directory::matches() — and keeps the URL in step
 * (history.replaceState), so Back from a clinic page restores the results.
 * City drives which areas the Area select offers.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( '[data-clinic-finder]' );
		var grid = document.getElementById( 'dak-cf-grid' );

		if ( ! form || ! grid ) {
			return;
		}

		var q = document.getElementById( 'dak-cf-q' );
		var city = document.getElementById( 'dak-cf-city' );
		var area = document.getElementById( 'dak-cf-area' );
		var sort = document.getElementById( 'dak-cf-sort' );
		var countEl = document.getElementById( 'dak-cf-count' );
		var activeEl = document.getElementById( 'dak-cf-active' );
		var clearBtn = document.getElementById( 'dak-cf-clear' );
		var emptyEl = document.getElementById( 'dak-cf-empty' );
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '[data-clinic-card]' ) );
		var strings = parseJson( form.getAttribute( 'data-strings' ) ) || {};
		var areas = parseJson( form.getAttribute( 'data-areas' ) ) || {};
		var timer = null;

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			apply();
		} );

		q.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( apply, 120 );
		} );

		city.addEventListener( 'change', function () {
			fillAreas( city.value, '' );
			apply();
		} );

		area.addEventListener( 'change', apply );
		sort.addEventListener( 'change', apply );

		// Chip "×" links, "Clear filters" and the empty state's reset all
		// work as plain links without JavaScript; here they update in place.
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-clear], #dak-cf-clear' );

			if ( ! link || ! link.closest( '.dak-pub-clinics' ) ) {
				return;
			}

			event.preventDefault();

			var what = link.getAttribute( 'data-clear' ) || 'all';

			if ( 'q' === what || 'all' === what ) {
				q.value = '';
			}

			if ( 'city' === what || 'all' === what ) {
				city.value = '';
				fillAreas( '', '' );
			}

			if ( 'area' === what ) {
				area.value = '';
			}

			apply();

			// The control that was clicked may have just been removed.
			( 'city' === what ? city : ( 'area' === what ? area : q ) ).focus();
		} );

		/**
		 * Rebuilds the Area select for one city, keeping `keep` selected if
		 * that city has it.
		 */
		function fillAreas( citySlug, keep ) {
			var list = citySlug && areas[ citySlug ] ? areas[ citySlug ] : [];

			area.innerHTML = '';
			area.appendChild( option( '', list.length ? strings.allAreas : strings.chooseCity ) );

			list.forEach( function ( row ) {
				var opt = option( row.slug, row.label );

				opt.selected = row.slug === keep;
				area.appendChild( opt );
			} );

			area.disabled = ! list.length;
		}

		function option( value, label ) {
			var opt = document.createElement( 'option' );

			opt.value = value;
			opt.textContent = label || '';

			return opt;
		}

		function apply() {
			var words = q.value.trim().toLowerCase().split( /\s+/ ).filter( Boolean );
			var visible = 0;

			sortCards( sort.value );

			cards.forEach( function ( card ) {
				var ok = ( ! city.value || card.getAttribute( 'data-city' ) === city.value ) &&
					( ! area.value || card.getAttribute( 'data-area' ) === area.value ) &&
					words.every( function ( word ) {
						return -1 !== ( card.getAttribute( 'data-search' ) || '' ).indexOf( word );
					} );

				card.hidden = ! ok;

				if ( ok ) {
					visible++;
				}
			} );

			var filtered = words.length > 0 || !! city.value || !! area.value;

			countEl.textContent = filtered
				? format( 1 === cards.length ? strings.showingOne : strings.showingMany, [ visible, cards.length ] )
				: format( 1 === cards.length ? strings.totalOne : strings.totalMany, [ cards.length ] );

			emptyEl.classList.toggle( 'dak-hidden', visible > 0 );
			clearBtn.classList.toggle( 'dak-hidden', ! filtered );
			renderChips();
			syncUrl();
		}

		function sortCards( key ) {
			var ordered = cards.slice().sort( function ( a, b ) {
				var an = a.getAttribute( 'data-name' ) || '';
				var bn = b.getAttribute( 'data-name' ) || '';
				var byName = an < bn ? -1 : ( an > bn ? 1 : 0 );

				if ( 'name-za' === key ) {
					return -byName;
				}

				if ( 'doctors' === key ) {
					var diff = parseInt( b.getAttribute( 'data-doctors' ), 10 ) - parseInt( a.getAttribute( 'data-doctors' ), 10 );

					if ( diff ) {
						return diff;
					}
				}

				return byName;
			} );

			ordered.forEach( function ( card ) {
				grid.appendChild( card );
			} );
		}

		function renderChips() {
			activeEl.innerHTML = '';

			if ( q.value.trim() ) {
				activeEl.appendChild( chip( '“' + q.value.trim() + '”', 'q', strings.removeSearch ) );
			}

			if ( city.value ) {
				activeEl.appendChild( chip( selectedLabel( city ), 'city', strings.removeCity ) );
			}

			if ( area.value ) {
				activeEl.appendChild( chip( selectedLabel( area ), 'area', strings.removeArea ) );
			}
		}

		function chip( label, clear, srLabel ) {
			var link = document.createElement( 'a' );
			var sr = document.createElement( 'span' );

			link.className = 'pub-chip';
			link.href = form.getAttribute( 'action' );
			link.setAttribute( 'data-clear', clear );
			link.appendChild( document.createTextNode( label + ' ' ) );
			link.insertAdjacentHTML( 'beforeend', '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M5.5 5.5l9 9M14.5 5.5l-9 9"/></svg>' );
			sr.className = 'pub-sr-only';
			sr.textContent = srLabel || '';
			link.appendChild( sr );

			return link;
		}

		function selectedLabel( select ) {
			var opt = select.options[ select.selectedIndex ];

			return opt ? opt.textContent : '';
		}

		function syncUrl() {
			if ( ! window.history || ! window.history.replaceState || ! window.URL ) {
				return;
			}

			var url = new URL( window.location.href );

			setParam( url, 'q', q.value.trim() );
			setParam( url, 'city', city.value );
			setParam( url, 'area', area.value );
			setParam( url, 'sort', 'name' === sort.value ? '' : sort.value );

			window.history.replaceState( window.history.state, '', url.toString() );
		}

		function setParam( url, key, value ) {
			if ( value ) {
				url.searchParams.set( key, value );
			} else {
				url.searchParams.delete( key );
			}
		}
	} );

	function format( template, values ) {
		var i = 0;

		return String( template || '' ).replace( /%(\d\$)?s/g, function ( match, position ) {
			var index = position ? parseInt( position, 10 ) - 1 : i++;

			return String( values[ index ] );
		} );
	}

	function parseJson( raw ) {
		try {
			return JSON.parse( raw || 'null' );
		} catch ( e ) {
			return null;
		}
	}
}() );
