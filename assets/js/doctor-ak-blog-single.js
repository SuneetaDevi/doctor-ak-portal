/**
 * Doctor AK Portal — single blog post ([blog_single]):
 *  - "Copy link" button (the share links themselves are plain <a>s), with
 *    the "Link copied" confirmation also announced to screen readers.
 *  - the table of contents, built from the article's own h2/h3 headings
 *    (shown only when there are at least two), with the current section
 *    highlighted as you scroll. A first heading that only repeats the page
 *    title (pasted articles often start with one) is hidden and left out of
 *    the contents — its text is unchanged in the article.
 *  - the contents are a <details>: open beside the article on wide screens,
 *    collapsed above it on narrow ones.
 */
( function () {
	'use strict';

	var WIDE = window.matchMedia ? window.matchMedia( '(min-width: 901px)' ) : null;

	document.addEventListener( 'DOMContentLoaded', function () {
		wireCopyLink();
		buildToc();
	} );

	function headerOffset() {
		var value = parseFloat( window.getComputedStyle( document.documentElement ).getPropertyValue( '--dak-site-header-offset' ) );

		return ( isNaN( value ) ? 76 : value ) + 24;
	}

	function wireCopyLink() {
		var button = document.querySelector( '[data-copy-link]' );

		if ( ! button ) {
			return;
		}

		var label = button.querySelector( 'span' );
		var status = document.querySelector( '[data-copy-status]' );
		var original = label ? label.textContent : '';
		var timer = null;

		button.addEventListener( 'click', function () {
			var url = button.getAttribute( 'data-copy-link' );

			copy( url ).then( function () {
				var done = button.getAttribute( 'data-copied' );

				if ( label ) {
					label.textContent = done;
				}

				if ( status ) {
					status.textContent = done;
				}

				button.classList.add( 'is-done' );
				window.clearTimeout( timer );
				timer = window.setTimeout( function () {
					if ( label ) {
						label.textContent = original;
					}

					if ( status ) {
						status.textContent = '';
					}

					button.classList.remove( 'is-done' );
				}, 2000 );
			} );
		} );
	}

	function copy( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			// Some browsers leave the request pending (no focus, permission
			// prompt) — fall back rather than leave the button silent.
			return new Promise( function ( resolve ) {
				var settled = false;
				var finish = function ( useFallback ) {
					if ( settled ) {
						return;
					}

					settled = true;
					resolve( useFallback ? fallbackCopy( text ) : undefined );
				};

				navigator.clipboard.writeText( text ).then( function () {
					finish( false );
				}, function () {
					finish( true );
				} );
				window.setTimeout( function () {
					finish( true );
				}, 800 );
			} );
		}

		return fallbackCopy( text );
	}

	function fallbackCopy( text ) {
		return new Promise( function ( resolve ) {
			var area = document.createElement( 'textarea' );

			area.value = text;
			area.setAttribute( 'readonly', '' );
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();

			try {
				document.execCommand( 'copy' );
			} catch ( e ) {
				// Nothing more to try.
			}

			document.body.removeChild( area );
			resolve();
		} );
	}

	function normalize( text ) {
		return String( text || '' ).toLowerCase().replace( /[^0-9a-zÀ-￿]+/g, '' );
	}

	// The article's first heading, when nothing but whitespace comes before
	// it and its text is the page title again.
	function hideRepeatedTitle( article ) {
		var title = document.getElementById( 'dak-blog-title' );
		var first = article.querySelector( 'h1, h2, h3' );

		if ( ! title || ! first || normalize( first.textContent ) !== normalize( title.textContent ) ) {
			return null;
		}

		var range = document.createRange();

		range.setStart( article, 0 );
		range.setEndBefore( first );

		if ( '' !== range.toString().trim() ) {
			return null;
		}

		first.classList.add( 'dak-blog-repeated-title' );

		return first;
	}

	function buildToc() {
		var article = document.getElementById( 'dak-blog-article' );
		var toc = document.getElementById( 'dak-blog-toc' );
		var list = document.getElementById( 'dak-blog-toc-list' );

		if ( ! article || ! toc || ! list ) {
			return;
		}

		var repeated = hideRepeatedTitle( article );
		var headings = Array.prototype.filter.call( article.querySelectorAll( 'h2, h3' ), function ( heading ) {
			return heading !== repeated && '' !== heading.textContent.trim();
		} );

		if ( headings.length < 2 ) {
			return;
		}

		var links = [];

		headings.forEach( function ( heading, index ) {
			var id = heading.id || 'section-' + ( index + 1 );
			var item = document.createElement( 'li' );
			var link = document.createElement( 'a' );

			heading.id = id;
			link.href = '#' + id;
			link.textContent = heading.textContent.trim();

			if ( 'H3' === heading.tagName ) {
				item.className = 'is-sub';
			}

			item.appendChild( link );
			list.appendChild( item );
			links.push( { link: link, heading: heading } );
		} );

		toc.classList.remove( 'dak-hidden' );

		// Open beside the article on wide screens, collapsed above it on
		// narrow ones; a reader's own toggle is kept until the layout changes.
		function syncOpen() {
			toc.open = ! WIDE || WIDE.matches;
		}

		syncOpen();

		if ( WIDE ) {
			if ( WIDE.addEventListener ) {
				WIDE.addEventListener( 'change', syncOpen );
			} else if ( WIDE.addListener ) {
				WIDE.addListener( syncOpen );
			}
		}

		var current = null;

		function highlight() {
			var line = headerOffset() + 16;
			var next = links[ 0 ];

			links.forEach( function ( entry ) {
				if ( entry.heading.getBoundingClientRect().top <= line ) {
					next = entry;
				}
			} );

			if ( next === current ) {
				return;
			}

			current = next;

			links.forEach( function ( entry ) {
				var active = entry === current;

				entry.link.classList.toggle( 'is-active', active );

				if ( active ) {
					entry.link.setAttribute( 'aria-current', 'location' );
				} else {
					entry.link.removeAttribute( 'aria-current' );
				}
			} );

			// Keep the highlighted entry in view inside a long, scrolling list
			// (only while the list is beside the article).
			if ( WIDE && WIDE.matches && list.scrollHeight > list.clientHeight ) {
				var box = list.getBoundingClientRect();
				var item = current.link.getBoundingClientRect();

				if ( item.top < box.top || item.bottom > box.bottom ) {
					list.scrollTop += item.top - box.top - list.clientHeight / 3;
				}
			}
		}

		var ticking = false;

		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( function () {
					ticking = false;
					highlight();
				} );
			}
		}, { passive: true } );

		list.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a' ) : null;

			if ( ! link ) {
				return;
			}

			var target = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );

			if ( ! target ) {
				return;
			}

			// Move focus with the jump so keyboard and screen-reader users
			// continue reading from that section.
			window.setTimeout( function () {
				if ( ! target.hasAttribute( 'tabindex' ) ) {
					target.setAttribute( 'tabindex', '-1' );
				}

				target.focus( { preventScroll: true } );
				highlight();
			}, 0 );
		} );

		highlight();
	}
} )();
