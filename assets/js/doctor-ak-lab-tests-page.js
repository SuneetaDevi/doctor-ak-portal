/**
 * Doctor AK Portal — the public "Book a lab test" page
 * (templates/directory/lab-tests-page.php).
 *
 * Picking: one tap on Add (or a Popular chip) adds a test; the search box
 * filters the list by name, other names and sample. The picked tests are
 * kept in the address (?tests=1,2) so logging in brings the visitor back
 * with the same tests.
 *
 * Booking: labs offering every picked test are compared by price (the
 * cheapest chosen), labs missing some are shown greyed with what's
 * missing; home collection only where the chosen lab does it (fee added);
 * today's ended time windows can't be picked. A patient books here
 * (Lab_Tests_Page::handle_book) and lands on their Lab Tests tab; a visitor
 * gets "Log in to book" pointing back to this page.
 */
( function () {
	'use strict';

	var settings = window.dakLabsPage || {};
	var labels = settings.labels || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '[data-dak-labs-page]' );
		var dataEl = document.getElementById( 'dak-labs-data' );

		if ( ! root || ! dataEl ) {
			return;
		}

		var data;

		try {
			data = JSON.parse( dataEl.textContent );
		} catch ( e ) {
			return;
		}

		var tests = {};
		var labs = data.labs;
		var picked = [];
		var hour = parseInt( root.getAttribute( 'data-hour' ), 10 ) || 0;
		var viewer = root.getAttribute( 'data-viewer' );
		var pageUrl = root.getAttribute( 'data-page-url' ) || window.location.href.split( '?' )[0];
		var loginUrl = root.getAttribute( 'data-login-url' );

		data.tests.forEach( function ( test ) {
			tests[ test.id ] = test;
		} );

		( data.picked || [] ).forEach( function ( id ) {
			if ( tests[ id ] && -1 === picked.indexOf( id ) ) {
				picked.push( id );
			}
		} );

		var book = root.querySelector( '[data-dak-labs-book]' );
		var form = root.querySelector( '[data-dak-labs-form]' );
		var choices = root.querySelector( '[data-dak-labs-choices]' );
		var rest = root.querySelector( '[data-dak-labs-rest]' );
		var pickedList = root.querySelector( '[data-dak-labs-picked]' );
		var cart = root.querySelector( '[data-dak-labs-cart]' );
		var cartText = root.querySelector( '[data-dak-labs-cart-text]' );
		var total = root.querySelector( '[data-dak-labs-total]' );
		var error = root.querySelector( '[data-dak-labs-error]' );
		var address = root.querySelector( '[data-dak-labs-address]' );
		var login = root.querySelector( '[data-dak-labs-login]' );
		var submit = root.querySelector( '[data-dak-labs-submit]' );
		var chosenLab = 0;

		function money( amount ) {
			return 'PKR ' + Math.round( amount ).toLocaleString( 'en-US' );
		}

		function fill( template, value ) {
			return String( template || '' ).replace( '%d', value ).replace( '%s', value );
		}

		// The labs for the picked tests: full coverage first, cheapest first.
		function quotes() {
			return labs.map( function ( lab ) {
				var subtotal = 0;
				var missing = [];

				picked.forEach( function ( id ) {
					var price = tests[ id ].prices[ lab.id ];

					if ( undefined === price ) {
						missing.push( tests[ id ].name );
					} else {
						subtotal += price;
					}
				} );

				return { lab: lab, subtotal: subtotal, missing: missing, offers: picked.length - missing.length };
			} ).filter( function ( quote ) {
				return quote.offers > 0;
			} ).sort( function ( a, b ) {
				if ( ! a.missing.length !== ! b.missing.length ) {
					return a.missing.length ? 1 : -1;
				}

				return a.subtotal - b.subtotal;
			} );
		}

		function renderChoices() {
			var list = quotes();
			var covering = list.filter( function ( quote ) {
				return ! quote.missing.length;
			} );
			var keep = covering.some( function ( quote ) {
				return quote.lab.id === chosenLab;
			} );

			if ( ! keep ) {
				chosenLab = covering.length ? covering[0].lab.id : 0;
			}

			choices.innerHTML = '';

			if ( ! covering.length ) {
				var none = document.createElement( 'p' );
				none.className = 'dak-labs-nolab';
				none.textContent = labels.noLab;
				choices.appendChild( none );
			}

			list.forEach( function ( quote, index ) {
				var lab = quote.lab;
				var full = ! quote.missing.length;
				var item = document.createElement( full ? 'label' : 'div' );
				item.className = 'dak-labs-choice' + ( full ? '' : ' is-unavailable' );

				var body = '<span class="dak-labs-choice-body"><span class="dak-labs-choice-top"><strong></strong>' + ( full ? '<span class="dak-labs-choice-price"></span>' : '' ) + '</span><span class="dak-labs-choice-place"></span><span class="dak-labs-choice-tags"></span></span>';

				if ( full ) {
					item.innerHTML = '<input type="radio" name="lab_id">' + body;

					var radio = item.querySelector( 'input' );
					radio.value = lab.id;
					radio.checked = lab.id === chosenLab;
					item.querySelector( '.dak-labs-choice-price' ).textContent = money( quote.subtotal );
				} else {
					item.innerHTML = body;
				}

				item.querySelector( 'strong' ).textContent = lab.name;
				item.querySelector( '.dak-labs-choice-place' ).textContent = full ? lab.place : fill( labels.missing, quote.missing.join( ', ' ) );

				var tags = item.querySelector( '.dak-labs-choice-tags' );

				if ( full ) {
					if ( 0 === index && covering.length > 1 ) {
						addTag( tags, labels.lowest, 'is-best' );
					}

					addTag( tags, lab.home ? ( lab.homeFee > 0 ? fill( labels.homeFee, money( lab.homeFee ) ) : labels.homeFree ) : labels.labOnly, lab.home ? '' : 'is-muted' );
				}

				choices.appendChild( item );
			} );

			if ( rest ) {
				rest.hidden = ! covering.length;
			}

			update();
		}

		function addTag( container, text, extra ) {
			var tag = document.createElement( 'span' );
			tag.className = 'dak-labs-tag' + ( extra ? ' ' + extra : '' );
			tag.textContent = text;
			container.appendChild( tag );
		}

		function currentLab() {
			for ( var i = 0; i < labs.length; i++ ) {
				if ( labs[ i ].id === chosenLab ) {
					return labs[ i ];
				}
			}

			return null;
		}

		// Collection type, address, total and today's windows.
		function update() {
			var lab = currentLab();
			var home = form.querySelector( 'input[name="collection"][value="home"]' );
			var atLab = form.querySelector( 'input[name="collection"][value="lab"]' );

			home.disabled = ! lab || ! lab.home;

			if ( home.disabled && home.checked ) {
				atLab.checked = true;
			}

			address.hidden = ! home.checked;

			var amount = 0;

			picked.forEach( function ( id ) {
				if ( lab && undefined !== tests[ id ].prices[ lab.id ] ) {
					amount += tests[ id ].prices[ lab.id ];
				}
			} );

			if ( lab && home.checked ) {
				amount += lab.homeFee;
			}

			total.textContent = money( amount );

			var day = form.querySelector( 'input[name="date"]:checked' );
			var isToday = day && '1' === day.getAttribute( 'data-today' );
			var firstOpen = null;

			Array.prototype.forEach.call( form.querySelectorAll( 'input[name="time_window"]' ), function ( input ) {
				input.disabled = isToday && hour >= ( parseInt( input.getAttribute( 'data-ends' ), 10 ) || 24 );

				if ( ! input.disabled && ! firstOpen ) {
					firstOpen = input;
				}
			} );

			var chosenWindow = form.querySelector( 'input[name="time_window"]:checked' );

			if ( ( ! chosenWindow || chosenWindow.disabled ) && firstOpen ) {
				firstOpen.checked = true;
			}

			if ( submit ) {
				submit.disabled = ! lab;
			}
		}

		// Everything that depends on which tests are picked.
		function sync() {
			Array.prototype.forEach.call( root.querySelectorAll( '[data-dak-labs-toggle]' ), function ( button ) {
				var on = -1 !== picked.indexOf( parseInt( button.getAttribute( 'data-dak-labs-toggle' ), 10 ) );

				button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				button.classList.toggle( 'is-on', on );

				if ( button.classList.contains( 'dak-labs-add' ) ) {
					button.textContent = on ? ( labels.added || 'Added' ) : ( labels.add || 'Add' );
				}
			} );

			Array.prototype.forEach.call( root.querySelectorAll( '[data-dak-labs-test]' ), function ( row ) {
				row.classList.toggle( 'is-picked', -1 !== picked.indexOf( parseInt( row.getAttribute( 'data-dak-labs-test' ), 10 ) ) );
			} );

			book.hidden = ! picked.length;
			cart.hidden = ! picked.length;

			var from = 0;

			picked.forEach( function ( id ) {
				from += tests[ id ].from;
			} );

			cartText.textContent = ( 1 === picked.length ? labels.test : fill( labels.tests, picked.length ) ) + ' · ' + fill( labels.from, money( from ) );

			pickedList.innerHTML = '';

			picked.forEach( function ( id ) {
				var li = document.createElement( 'li' );
				li.innerHTML = '<span></span><button type="button" class="dak-labs-remove">×</button>';
				li.querySelector( 'span' ).textContent = tests[ id ].name;
				li.querySelector( 'button' ).setAttribute( 'data-dak-labs-toggle', id );
				li.querySelector( 'button' ).setAttribute( 'aria-label', 'Remove ' + tests[ id ].name );
				pickedList.appendChild( li );
			} );

			if ( picked.length ) {
				// A new pick after a booking: back to the form.
				form.hidden = false;
				root.querySelector( '[data-dak-labs-success]' ).hidden = true;
				renderChoices();
			}

			// Keep the picks in the address, and in the login link's way back —
			// built from the browser's own address (same origin, whatever the
			// site's configured URL says), keeping any other query args.
			var query = window.location.search.replace( /^\?/, '' ).split( '&' ).filter( function ( part ) {
				return part && 0 !== part.indexOf( 'tests=' );
			} );

			if ( picked.length ) {
				query.push( 'tests=' + picked.join( ',' ) );
			}

			var back = ( 'file:' === window.location.protocol ? pageUrl.split( '?' )[0] : window.location.origin + window.location.pathname ) + ( query.length ? '?' + query.join( '&' ) : '' );

			if ( window.history && window.history.replaceState && 'file:' !== window.location.protocol ) {
				try {
					window.history.replaceState( null, '', back + window.location.hash );
				} catch ( e ) {
					// Not essential: the login link below still carries the picks.
				}
			}

			if ( login && loginUrl ) {
				login.href = loginUrl + ( -1 === loginUrl.indexOf( '?' ) ? '?' : '&' ) + 'redirect_to=' + encodeURIComponent( back + '#dak-labs-book' );
			}
		}

		root.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest( '[data-dak-labs-toggle]' );

			if ( ! toggle ) {
				return;
			}

			var id = parseInt( toggle.getAttribute( 'data-dak-labs-toggle' ), 10 );
			var at = picked.indexOf( id );

			if ( -1 === at ) {
				picked.push( id );
			} else {
				picked.splice( at, 1 );
			}

			sync();
		} );

		form.addEventListener( 'change', function ( event ) {
			if ( 'lab_id' === event.target.name ) {
				chosenLab = parseInt( event.target.value, 10 );
			}

			update();
		} );

		// Search.
		var search = root.querySelector( '[data-dak-labs-search]' );
		var noMatch = root.querySelector( '[data-dak-labs-nomatch]' );

		if ( search ) {
			search.addEventListener( 'input', function () {
				var words = search.value.toLowerCase().trim().split( /\s+/ ).filter( Boolean );
				var shown = 0;

				Array.prototype.forEach.call( root.querySelectorAll( '[data-dak-labs-test]' ), function ( row ) {
					var text = row.getAttribute( 'data-search' ) || '';
					var match = words.every( function ( word ) {
						return -1 !== text.indexOf( word );
					} );

					row.hidden = ! match;
					shown += match ? 1 : 0;
				} );

				noMatch.hidden = shown > 0;
			} );
		}

		// Book.
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( 'guest' === viewer || ! submit ) {
				return;
			}

			var body = new FormData();
			body.append( 'action', 'doctor_ak_public_lab_book' );
			body.append( 'nonce', settings.nonce || '' );
			body.append( 'tests', picked.join( ',' ) );

			Array.prototype.forEach.call( form.elements, function ( field ) {
				if ( field.name && ! field.disabled && ( ( 'radio' !== field.type ) || field.checked ) ) {
					body.append( field.name, field.value );
				}
			} );

			error.hidden = true;
			submit.disabled = true;
			submit.setAttribute( 'data-label', submit.textContent );
			submit.textContent = labels.booking || 'Booking…';

			fetch( settings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					if ( result && result.success ) {
						if ( result.data && result.data.redirect ) {
							window.location.href = result.data.redirect;
							return;
						}

						// Nowhere to send them (e.g. a doctor): confirm here and start over.
						var success = root.querySelector( '[data-dak-labs-success]' );

						if ( success ) {
							success.textContent = ( result.data && result.data.message ) || 'Booked!';
							success.hidden = false;
						}

						picked = [];
						submit.disabled = false;
						submit.textContent = submit.getAttribute( 'data-label' );
						sync();
						book.hidden = false;
						form.hidden = true;

						if ( success ) {
							success.scrollIntoView( { block: 'center' } );
						}

						return;
					}

					fail( result && result.data && result.data.message );
				} )
				.catch( function () {
					fail();
				} );

			function fail( message ) {
				error.textContent = message || settings.genericError || 'Something went wrong. Please try again.';
				error.hidden = false;
				submit.disabled = false;
				submit.textContent = submit.getAttribute( 'data-label' );
			}
		} );

		sync();
	} );
} )();
