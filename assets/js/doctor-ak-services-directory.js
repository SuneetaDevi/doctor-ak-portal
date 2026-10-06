/**
 * Doctor AK Portal — Services directory (templates/directory/services-directory.php).
 *
 * Every eligible service is in the page. Search and the category filter
 * are applied together to the full list, and only then is the "Show more"
 * window (12, 24, …) cut from the matches — so the count ("Showing 12 of
 * 36 services") and the cards always agree, and a non-matching card is
 * actually removed from view (the `hidden` attribute, which the stylesheet
 * enforces for these cards).
 *
 * State (q, category, show) is kept in the URL with history.replaceState,
 * so Back from a service page returns to the same list.
 */
( function () {
	'use strict';

	var STEP = 12;

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
		var root = document.querySelector( '[data-dak-dir-services]' );
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

		var items = Array.prototype.slice.call( root.querySelectorAll( '[data-dak-dir-service]' ) );
		var buttons = Array.prototype.slice.call( root.querySelectorAll( '[data-dak-svc-category]' ) );
		var search = root.querySelector( '#dak-svc-q' );
		var clearSearch = root.querySelector( '[data-dak-dir-clear-search]' );
		var countEl = root.querySelector( '[data-dak-dir-count]' );
		var empty = root.querySelector( '[data-dak-dir-empty]' );
		var moreWrap = root.querySelector( '[data-dak-dir-more-wrap]' );
		var more = root.querySelector( '[data-dak-dir-more]' );
		var clearAll = Array.prototype.slice.call( root.querySelectorAll( '[data-dak-dir-clear-all]' ) );
		var category = '';
		var limit = STEP;
		var timer = 0;

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

		function setCategory( slug ) {
			var known = buttons.some( function ( button ) {
				return button.getAttribute( 'data-dak-svc-category' ) === slug;
			} );

			category = known ? slug : '';

			buttons.forEach( function ( button ) {
				var on = button.getAttribute( 'data-dak-svc-category' ) === category;

				button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				button.classList.toggle( 'is-active', on );
			} );
		}

		function apply() {
			var q = search ? search.value.trim().toLowerCase() : '';
			var matching = items.filter( function ( item ) {
				return ( '' === category || item.getAttribute( 'data-category' ) === category )
					&& ( '' === q || ( item.getAttribute( 'data-search' ) || '' ).indexOf( q ) !== -1 );
			} );

			items.forEach( function ( item ) {
				var index = matching.indexOf( item );

				item.hidden = -1 === index || index >= limit;
			} );

			var shown = Math.min( limit, matching.length );
			var text;

			if ( 0 === matching.length ) {
				text = strings.none;
			} else if ( 1 === matching.length ) {
				text = strings.showingOne;
			} else if ( shown === matching.length ) {
				text = fmt( 'showingAll', number( matching.length ) );
			} else {
				text = fmt( 'showing', [ number( shown ), number( matching.length ) ] );
			}

			if ( category ) {
				var active = buttons.filter( function ( button ) {
					return button.getAttribute( 'data-dak-svc-category' ) === category;
				} )[ 0 ];

				if ( active ) {
					text = fmt( 'inCategory', [ active.getAttribute( 'data-label' ), text ] );
				}
			}

			if ( countEl && countEl.textContent !== text ) {
				countEl.textContent = text;
			}

			if ( empty ) {
				empty.hidden = matching.length > 0;
			}

			if ( moreWrap && more ) {
				var remaining = matching.length - shown;

				moreWrap.hidden = remaining <= 0;
				more.textContent = fmt( 'more', number( Math.min( STEP, remaining ) ) );
			}

			if ( clearSearch ) {
				clearSearch.hidden = '' === q;
			}

			clearAll.forEach( function ( button ) {
				if ( ! button.closest( '[data-dak-dir-empty]' ) ) {
					button.hidden = '' === q && '' === category;
				}
			} );

			writeUrl( q );
		}

		function writeUrl( q ) {
			if ( ! window.history || ! window.history.replaceState || ! window.URLSearchParams ) {
				return;
			}

			var params = new URLSearchParams( window.location.search );

			params.delete( 'q' );
			params.delete( 'category' );
			params.delete( 'show' );

			if ( q ) {
				params.set( 'q', search.value.trim() );
			}
			if ( category ) {
				params.set( 'category', category );
			}
			if ( limit > STEP ) {
				params.set( 'show', limit );
			}

			var query = params.toString();

			window.history.replaceState( window.history.state, '', window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash );
		}

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				setCategory( button.getAttribute( 'data-dak-svc-category' ) );
				limit = STEP;
				apply();
			} );
		} );

		if ( search ) {
			search.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( function () {
					limit = STEP;
					apply();
				}, 150 );
			} );

			search.form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				window.clearTimeout( timer );
				limit = STEP;
				apply();
			} );
		}

		if ( clearSearch ) {
			clearSearch.addEventListener( 'click', function () {
				search.value = '';
				limit = STEP;
				apply();
				search.focus();
			} );
		}

		clearAll.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				if ( search ) {
					search.value = '';
				}

				setCategory( '' );
				limit = STEP;
				apply();

				if ( search ) {
					search.focus();
				}
			} );
		} );

		if ( more ) {
			more.addEventListener( 'click', function () {
				// The first newly shown card takes focus, so keyboard users
				// continue from where the list grew.
				var firstNew = items.filter( function ( item ) {
					return ! item.hidden;
				} ).length;

				limit += STEP;
				apply();

				var visible = items.filter( function ( item ) {
					return ! item.hidden;
				} );
				var target = visible[ firstNew ] ? visible[ firstNew ].querySelector( '.dak-dir-service-title a' ) : null;

				if ( target ) {
					target.focus();
				}
			} );
		}

		// Restore from the URL (Back from a service page, or a shared link).
		if ( window.URLSearchParams ) {
			var params = new URLSearchParams( window.location.search );

			if ( search && ( params.get( 'q' ) || params.get( 's' ) ) ) {
				search.value = params.get( 'q' ) || params.get( 's' );
			}

			setCategory( params.get( 'category' ) || '' );
			limit = Math.max( STEP, Math.ceil( ( parseInt( params.get( 'show' ), 10 ) || STEP ) / STEP ) * STEP );
		}

		apply();
	}
}() );
