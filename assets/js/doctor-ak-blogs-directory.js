/**
 * Doctor AK Portal — Blog page ([blogs_directory]).
 *
 * Every published post's card is already on the page; this script:
 *  - filters them by the search box (title, topic and excerpt) and by topic
 *    (a quick chip, or any topic from the "All topics" panel — one topic at a
 *    time, as before);
 *  - shows the matching cards a page at a time, with "Load more" (focus moves
 *    to the first newly shown article);
 *  - keeps the result count, "Clear filters" and the no-results message in
 *    step.
 * Without JavaScript every card shows, unfiltered.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var grid = document.getElementById( 'dak-blogs-directory-grid' );

		if ( ! grid ) {
			return;
		}

		var cards      = Array.prototype.slice.call( grid.querySelectorAll( '.dak-blog-card' ) );
		var pageSize   = parseInt( grid.getAttribute( 'data-page-size' ), 10 ) || 9;
		var search     = document.getElementById( 'dak-blog-search' );
		var countEl    = document.getElementById( 'dak-blog-results-count' );
		var resetBar   = document.getElementById( 'dak-blog-reset' );
		var noResults  = document.getElementById( 'dak-blog-noresults' );
		var more       = document.getElementById( 'dak-blog-more' );
		var moreBtn    = document.getElementById( 'dak-blog-more-btn' );
		var picked     = document.querySelector( '.dak-blog-chip-picked' );
		var toggle     = document.getElementById( 'dak-blog-alltopics-toggle' );
		var panel      = document.getElementById( 'dak-blog-alltopics-panel' );
		var topicFind  = document.getElementById( 'dak-blog-topic-search' );
		var topicEmpty = document.querySelector( '.dak-blog-topic-empty' );
		var i18n       = {};

		try {
			i18n = JSON.parse( document.getElementById( 'dak-blog-i18n' ).textContent );
		} catch ( e ) {
			i18n = {};
		}

		var state = { topic: '', query: '', shown: pageSize };
		var matches = cards;

		function format( template ) {
			var args = Array.prototype.slice.call( arguments, 1 );
			var index = 0;

			return String( template || '' )
				.replace( /%(\d+)\$[sd]/g, function ( m, n ) {
					return args[ parseInt( n, 10 ) - 1 ];
				} )
				.replace( /%[sd]/g, function () {
					return args[ index++ ];
				} );
		}

		function topicLabel( slug ) {
			var option = document.querySelector( '.dak-blog-topic-option[data-topic-filter="' + slug + '"]' );
			var chip = document.querySelector( '.dak-blog-chip[data-topic-filter="' + slug + '"]:not(.dak-blog-chip-picked)' );

			if ( option ) {
				return option.getAttribute( 'data-topic-label' );
			}

			return chip ? chip.textContent.trim() : slug;
		}

		function render( revealFrom ) {
			var words = state.query.split( /\s+/ ).filter( Boolean );

			matches = cards.filter( function ( card ) {
				var haystack = card.getAttribute( 'data-search' ) || '';

				if ( state.topic && card.getAttribute( 'data-topic' ) !== state.topic ) {
					return false;
				}

				return words.every( function ( word ) {
					return haystack.indexOf( word ) !== -1;
				} );
			} );

			cards.forEach( function ( card ) {
				card.hidden = true;
			} );

			matches.forEach( function ( card, index ) {
				card.hidden = index >= state.shown;
			} );

			var shown = Math.min( state.shown, matches.length );
			var filtered = !! ( state.topic || state.query );
			var text;

			if ( matches.length > shown ) {
				text = format( i18n.showing, shown, matches.length );
			} else {
				text = format( 1 === matches.length ? i18n.countOne : i18n.countMany, matches.length );
			}

			if ( state.topic ) {
				text += ' ' + format( i18n.inTopic, topicLabel( state.topic ) );
			}

			if ( state.query ) {
				text += ' ' + format( i18n.forQuery, state.query );
			}

			if ( countEl ) {
				countEl.textContent = text;
			}

			if ( resetBar ) {
				resetBar.hidden = ! filtered;
			}

			if ( noResults ) {
				noResults.hidden = matches.length > 0;
			}

			grid.hidden = 0 === matches.length;

			if ( more ) {
				more.hidden = matches.length <= shown;
			}

			if ( 'number' === typeof revealFrom && matches[ revealFrom ] ) {
				var link = matches[ revealFrom ].querySelector( '.dak-blog-card-title a' );

				if ( link ) {
					link.focus();
				}
			}
		}

		function setTopic( slug ) {
			state.topic = slug || '';
			state.shown = pageSize;

			var hasChip = false;

			document.querySelectorAll( '.dak-blog-chip:not(.dak-blog-chip-picked)' ).forEach( function ( chip ) {
				var active = chip.getAttribute( 'data-topic-filter' ) === state.topic;

				hasChip = hasChip || ( active && '' !== state.topic );
				chip.classList.toggle( 'is-active', active );
				chip.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );

			document.querySelectorAll( '.dak-blog-topic-option' ).forEach( function ( option ) {
				var active = option.getAttribute( 'data-topic-filter' ) === state.topic;

				option.classList.toggle( 'is-active', active );
				option.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );

			// A topic without its own chip appears as one extra, selected chip;
			// pressing it clears the topic.
			if ( picked ) {
				picked.hidden = hasChip || '' === state.topic;
				picked.textContent = '';

				if ( ! picked.hidden ) {
					picked.appendChild( document.createTextNode( topicLabel( state.topic ) + ' ' ) );

					var x = document.createElement( 'span' );

					x.setAttribute( 'aria-hidden', 'true' );
					x.textContent = '×';
					picked.appendChild( x );
				}
			}

			render();
		}

		function openPanel( open ) {
			if ( ! panel || ! toggle ) {
				return;
			}

			panel.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			// It opens under the button, left-aligned; when the button sits
			// near the right edge, align it to the button's right instead so
			// it never runs off the filter box.
			panel.classList.remove( 'is-flipped' );

			if ( open ) {
				var box = document.getElementById( 'dak-blog-filters' ).getBoundingClientRect();

				if ( panel.getBoundingClientRect().right > box.right ) {
					panel.classList.add( 'is-flipped' );
				}
			}

			if ( open && topicFind ) {
				topicFind.focus();
			}
		}

		function resetAll() {
			if ( search ) {
				search.value = '';
			}

			state.query = '';
			openPanel( false );
			setTopic( '' );
		}

		document.querySelectorAll( '.dak-blog-chip' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				setTopic( chip.classList.contains( 'dak-blog-chip-picked' ) ? '' : chip.getAttribute( 'data-topic-filter' ) );
			} );
		} );

		document.querySelectorAll( '.dak-blog-topic-option' ).forEach( function ( option ) {
			option.addEventListener( 'click', function () {
				var slug = option.getAttribute( 'data-topic-filter' );

				setTopic( state.topic === slug ? '' : slug );
				openPanel( false );

				if ( toggle ) {
					toggle.focus();
				}
			} );
		} );

		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				openPanel( panel.hidden );
			} );
		}

		if ( panel ) {
			panel.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key ) {
					openPanel( false );
					toggle.focus();
				}
			} );

			document.addEventListener( 'click', function ( event ) {
				if ( ! panel.hidden && ! panel.contains( event.target ) && ! toggle.contains( event.target ) ) {
					openPanel( false );
				}
			} );
		}

		if ( topicFind ) {
			topicFind.addEventListener( 'input', function () {
				var needle = topicFind.value.trim().toLowerCase();
				var visible = 0;

				document.querySelectorAll( '.dak-blog-topic-option' ).forEach( function ( option ) {
					var hit = '' === needle || option.getAttribute( 'data-topic-label' ).toLowerCase().indexOf( needle ) !== -1;

					option.parentNode.hidden = ! hit;
					visible += hit ? 1 : 0;
				} );

				if ( topicEmpty ) {
					topicEmpty.hidden = visible > 0;
				}
			} );
		}

		if ( search ) {
			var timer = null;

			search.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( function () {
					state.query = search.value.trim().toLowerCase();
					state.shown = pageSize;
					render();
				}, 150 );
			} );
		}

		if ( resetBar ) {
			resetBar.addEventListener( 'click', resetAll );
		}

		document.querySelectorAll( '[data-blog-reset]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				resetAll();

				if ( search ) {
					search.focus();
				}
			} );
		} );

		if ( moreBtn ) {
			moreBtn.addEventListener( 'click', function () {
				var from = state.shown;

				state.shown += pageSize;
				render( from );
			} );
		}

		render();
	} );
} )();
