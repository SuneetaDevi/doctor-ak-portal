/**
 * Doctor AK Portal — single blog post ([blog_single]):
 *  - "Copy link" button (the share links themselves are plain <a>s).
 *  - the sidebar's table of contents, built from the article's own h2/h3
 *    headings (shown only when there are at least two), with the current
 *    section highlighted as you scroll.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		wireCopyLink();
		buildToc();
	} );

	function wireCopyLink() {
		var button = document.querySelector( '[data-copy-link]' );

		if ( ! button ) {
			return;
		}

		var label = button.querySelector( 'span' );
		var original = label ? label.textContent : '';
		var timer = null;

		button.addEventListener( 'click', function () {
			var url = button.getAttribute( 'data-copy-link' );

			copy( url ).then( function () {
				if ( label ) {
					label.textContent = button.getAttribute( 'data-copied' );
				}

				button.classList.add( 'is-done' );
				window.clearTimeout( timer );
				timer = window.setTimeout( function () {
					if ( label ) {
						label.textContent = original;
					}

					button.classList.remove( 'is-done' );
				}, 2000 );
			} );
		} );
	}

	function copy( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

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

	function buildToc() {
		var article = document.getElementById( 'dak-blog-article' );
		var toc = document.getElementById( 'dak-blog-toc' );
		var list = document.getElementById( 'dak-blog-toc-list' );

		if ( ! article || ! toc || ! list ) {
			return;
		}

		var headings = article.querySelectorAll( 'h2, h3' );

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
			link.textContent = heading.textContent;
			item.appendChild( link );
			list.appendChild( item );
			links.push( { link: link, heading: heading } );
		} );

		toc.classList.remove( 'dak-hidden' );

		function highlight() {
			var current = links[ 0 ];

			links.forEach( function ( entry ) {
				if ( entry.heading.getBoundingClientRect().top < 160 ) {
					current = entry;
				}
			} );

			links.forEach( function ( entry ) {
				entry.link.classList.toggle( 'is-active', entry === current );
			} );
		}

		window.addEventListener( 'scroll', highlight, { passive: true } );
		highlight();
	}
} )();
