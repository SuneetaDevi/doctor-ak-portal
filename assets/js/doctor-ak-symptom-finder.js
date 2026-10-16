/**
 * Doctor AK Portal — "Find a doctor by symptoms" page.
 *
 * Enter symptoms → confirm anything unclear → doctors in related
 * specialties, nearest first → the normal profile / booking pages.
 *
 * Privacy: everything stays in this page's memory — nothing is sent to the
 * site, written to the URL, cookies or storage, or logged. The location is
 * only requested after "Find doctors", only used to sort by straight-line
 * distance, and forgotten when the page is left. Booking links carry the
 * doctor and clinic only, never symptoms.
 */
( function () {
	'use strict';

	var data = window.dakSymptomFinder;
	var Match = window.DAKSymptomMatch;
	var root = document.querySelector( '[data-dak-sf]' );

	if ( ! data || ! Match || ! root ) {
		return;
	}

	var m = Match.create( data.mappings || [] );
	var input = root.querySelector( '[data-sf-input]' );
	var list = root.querySelector( '[data-sf-suggestions]' );
	var clarify = root.querySelector( '[data-sf-clarify]' );
	var chipsWrap = root.querySelector( '[data-sf-selected-wrap]' );
	var chips = root.querySelector( '[data-sf-chips]' );
	var errorEl = root.querySelector( '[data-sf-error]' );
	var results = root.querySelector( '[data-sf-results]' );
	var live = root.querySelector( '[data-sf-live]' );

	var selected = [];      // mapping ids, in the order added
	var questions = [];     // { phrase, candidates } awaiting a choice
	var unmatched = [];     // phrases with no reliable match
	var suggestions = [];
	var active = -1;
	var searched = false;

	var loc = { status: 'idle', origin: null, place: '', mode: 'auto', city: '', area: '', panel: false };

	/* ------------------------------------------------------------------ */

	function h( tag, attrs, kids ) {
		var el = document.createElement( tag );

		Object.keys( attrs || {} ).forEach( function ( k ) {
			var v = attrs[ k ];

			if ( null === v || undefined === v || false === v ) {
				return;
			}

			if ( 'class' === k ) {
				el.className = v;
			} else if ( 'text' === k ) {
				el.textContent = v;
			} else if ( 'on' === k.slice( 0, 2 ) ) {
				el.addEventListener( k.slice( 2 ), v );
			} else {
				el.setAttribute( k, true === v ? '' : v );
			}
		} );

		( kids || [] ).forEach( function ( kid ) {
			if ( kid ) {
				el.appendChild( 'string' === typeof kid ? document.createTextNode( kid ) : kid );
			}
		} );

		return el;
	}

	function safeUrl( url ) {
		return /^https?:\/\//i.test( String( url || '' ) ) ? url : null;
	}

	function announce( text ) {
		live.textContent = '';
		setTimeout( function () {
			live.textContent = text;
		}, 40 );
	}

	function nameOf( id ) {
		return m.byId[ id ] ? m.byId[ id ].name : '';
	}

	function specialtyLabel( slug ) {
		return ( data.specialtyLabels && data.specialtyLabels[ slug ] ) || slug.replace( /-/g, ' ' );
	}

	function showError( text ) {
		errorEl.textContent = text;
		errorEl.hidden = ! text;
	}

	/* ------------------------------------------------------------------
	 * Symptoms: suggestions, chips, clarification
	 * ---------------------------------------------------------------- */

	function closeList() {
		list.hidden = true;
		list.textContent = '';
		input.setAttribute( 'aria-expanded', 'false' );
		input.removeAttribute( 'aria-activedescendant' );
		active = -1;
	}

	function highlight( index ) {
		var items = list.querySelectorAll( '[role="option"]' );

		Array.prototype.forEach.call( items, function ( li ) {
			li.classList.remove( 'is-active' );
			li.setAttribute( 'aria-selected', 'false' );
		} );

		if ( index < 0 || ! items.length ) {
			active = -1;
			input.removeAttribute( 'aria-activedescendant' );
			return;
		}

		active = Math.max( 0, Math.min( index, items.length - 1 ) );
		items[ active ].classList.add( 'is-active' );
		items[ active ].setAttribute( 'aria-selected', 'true' );
		input.setAttribute( 'aria-activedescendant', items[ active ].id );
	}

	function renderSuggestions() {
		suggestions = m.suggest( input.value, 8 ).filter( function ( s ) {
			return selected.indexOf( s.id ) === -1;
		} );

		list.textContent = '';

		if ( ! suggestions.length ) {
			closeList();
			return;
		}

		suggestions.forEach( function ( s, i ) {
			var li = h( 'li', { role: 'option', id: 'dak-sf-opt-' + i, 'aria-selected': 'false', class: 'dak-sf-option' }, [
				h( 'span', { text: s.name } ),
				s.via ? h( 'small', { text: '“' + s.via + '”' } ) : null,
			] );

			li.addEventListener( 'mousedown', function ( event ) {
				event.preventDefault();
			} );
			li.addEventListener( 'click', function () {
				addSymptom( s.id );
				input.value = '';
				closeList();
				input.focus();
			} );
			list.appendChild( li );
		} );

		list.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
		active = -1;
	}

	function addSymptom( id ) {
		if ( selected.indexOf( id ) === -1 ) {
			selected.push( id );
			announce( nameOf( id ) + ' added' );
		}

		showError( '' );
		renderChips();
		refreshIfSearched();
	}

	function removeSymptom( id ) {
		selected = selected.filter( function ( x ) {
			return x !== id;
		} );
		announce( nameOf( id ) + ' removed' );
		renderChips();
		refreshIfSearched();
	}

	function renderChips() {
		chips.textContent = '';
		chipsWrap.hidden = ! selected.length;

		selected.forEach( function ( id ) {
			chips.appendChild(
				h( 'li', { class: 'dak-sf-chip' }, [
					h( 'span', { text: nameOf( id ) } ),
					h( 'button', {
						type: 'button',
						class: 'dak-sf-chip-remove',
						'aria-label': 'Remove ' + nameOf( id ),
						text: '×',
						onclick: function () {
							removeSymptom( id );
							input.focus();
						},
					} ),
				] )
			);
		} );
	}

	/** Reads typed text: exact matches become chips, the rest is asked about. */
	function readText( text ) {
		if ( ! String( text || '' ).trim() ) {
			return;
		}

		var r = m.analyse( text );

		r.recognized.forEach( addSymptom );

		r.ambiguous.forEach( function ( q ) {
			var left = q.candidates.filter( function ( id ) {
				return selected.indexOf( id ) === -1;
			} );

			if ( left.length ) {
				questions.push( { phrase: q.phrase, candidates: left } );
			}
		} );

		r.unknown.forEach( function ( phrase ) {
			if ( unmatched.indexOf( phrase ) === -1 ) {
				unmatched.push( phrase );
			}
		} );

		input.value = '';
		closeList();
		renderClarify();

		if ( questions.length || unmatched.length ) {
			announce( 'Some of what you typed needs checking. See the questions below the box.' );
		}
	}

	function renderClarify() {
		clarify.textContent = '';
		clarify.hidden = ! questions.length && ! unmatched.length;

		questions.forEach( function ( q, qi ) {
			var box = h( 'div', { class: 'dak-sf-q', role: 'group', 'aria-label': 'Did you mean, for ' + q.phrase } );

			box.appendChild( h( 'p', {}, [ h( 'strong', { text: '“' + q.phrase + '”' } ), ' — did you mean:' ] ) );

			var choices = h( 'div', { class: 'dak-sf-q-choices' } );

			q.candidates.forEach( function ( id ) {
				choices.appendChild( h( 'button', {
					type: 'button',
					class: 'dak-sf-choice',
					text: nameOf( id ),
					onclick: function () {
						questions.splice( qi, 1 );
						addSymptom( id );
						renderClarify();
						input.focus();
					},
				} ) );
			} );

			choices.appendChild( h( 'button', {
				type: 'button',
				class: 'dak-sf-choice is-none',
				text: 'None of these',
				onclick: function () {
					questions.splice( qi, 1 );
					unmatched.push( q.phrase );
					renderClarify();
					input.focus();
				},
			} ) );

			box.appendChild( choices );
			clarify.appendChild( box );
		} );

		if ( unmatched.length ) {
			var note = h( 'div', { class: 'dak-sf-q is-unknown' } );

			note.appendChild( h( 'p', { text: 'We couldn’t match ' + unmatched.map( function ( p ) {
				return '“' + p + '”';
			} ).join( ', ' ) + ' to a symptom in our list. Try different words, or browse doctors yourself.' } ) );
			note.appendChild( h( 'button', {
				type: 'button',
				class: 'dak-sf-link',
				text: 'Dismiss',
				onclick: function () {
					unmatched = [];
					renderClarify();
					input.focus();
				},
			} ) );
			clarify.appendChild( note );
		}
	}

	input.addEventListener( 'input', renderSuggestions );

	input.addEventListener( 'keydown', function ( event ) {
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			if ( list.hidden ) {
				renderSuggestions();
			}

			event.preventDefault();
			highlight( active + ( 'ArrowDown' === event.key ? 1 : -1 ) );
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();

			if ( active > -1 && suggestions[ active ] ) {
				addSymptom( suggestions[ active ].id );
				input.value = '';
				closeList();
			} else {
				readText( input.value );
			}
		} else if ( 'Escape' === event.key ) {
			closeList();
		}
	} );

	input.addEventListener( 'blur', function () {
		setTimeout( closeList, 150 );
	} );

	root.querySelector( '[data-sf-add]' ).addEventListener( 'click', function () {
		readText( input.value );
		input.focus();
	} );

	/* ------------------------------------------------------------------
	 * Location
	 * ---------------------------------------------------------------- */

	function locate() {
		if ( ! navigator.geolocation ) {
			loc.status = 'unavailable';
			renderResults();
			return;
		}

		loc.status = 'locating';
		renderResults();

		navigator.geolocation.getCurrentPosition(
			function ( position ) {
				loc.origin = { lat: position.coords.latitude, lng: position.coords.longitude };
				loc.place = Match.nearbyPlace( loc.origin, data.places );
				loc.status = 'granted';
				loc.mode = 'auto';
				renderResults( 'Doctors sorted by distance from your location.' );
			},
			function ( error ) {
				loc.status = 1 === error.code ? 'denied' : ( 3 === error.code ? 'timeout' : 'unavailable' );
				renderResults();
			},
			{ enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 }
		);
	}

	/** Asks once, after "Find doctors"; never re-asks after a refusal. */
	function startLocation() {
		if ( 'idle' !== loc.status ) {
			return;
		}

		if ( navigator.permissions && navigator.permissions.query ) {
			loc.status = 'locating';
			navigator.permissions.query( { name: 'geolocation' } ).then( function ( p ) {
				if ( 'denied' === p.state ) {
					loc.status = 'denied';
					renderResults();
				} else {
					locate();
				}
			}, locate );
			return;
		}

		locate();
	}

	function rankOptions() {
		if ( 'place' === loc.mode && loc.city ) {
			return { city: loc.city, area: loc.area };
		}

		if ( 'auto' === loc.mode && 'granted' === loc.status ) {
			return { origin: loc.origin };
		}

		return {};
	}

	function cityOf( slug ) {
		return ( data.locations || [] ).filter( function ( c ) {
			return c.slug === slug;
		} )[ 0 ];
	}

	/* ------------------------------------------------------------------
	 * Results
	 * ---------------------------------------------------------------- */

	function refreshIfSearched() {
		if ( searched ) {
			renderResults();
		}
	}

	function formatKm( km ) {
		return km < 1 ? 'Less than 1 km away' : 'About ' + ( km < 10 ? km.toFixed( 1 ) : Math.round( km ) ) + ' km away';
	}

	function avatar( doc ) {
		var src = safeUrl( doc.avatar );

		return src
			? h( 'img', { class: 'dak-sf-photo', src: src, alt: '', loading: 'lazy', width: '64', height: '64' } )
			: h( 'span', { class: 'dak-sf-photo is-initials', 'aria-hidden': 'true', text: doc.name.replace( /^(dr|doctor)\.?\s+/i, '' ).charAt( 0 ) } );
	}

	function card( entry, online ) {
		var doc = entry.doctor;
		var clinic = online ? null : entry.primary;
		var body = h( 'div', { class: 'dak-sf-doc-body' } );
		var profile = safeUrl( doc.profile_url );
		var book = online ? safeUrl( doc.video.booking_url ) : safeUrl( clinic && clinic.booking_url );

		body.appendChild( h( 'h3', { class: 'dak-sf-doc-name', text: doc.name } ) );
		body.appendChild( h( 'p', { class: 'dak-sf-doc-spec', text: entry.matched.map( specialtyLabel ).join( ', ' ) + ( online ? ' · Video consultation' : '' ) } ) );

		if ( clinic ) {
			body.appendChild( h( 'p', { class: 'dak-sf-doc-clinic', text: clinic.label + ( clinic.place ? ' · ' + clinic.place : '' ) } ) );

			if ( 'distance' === entry.mode ) {
				body.appendChild( h( 'p', { class: 'dak-sf-doc-distance', text: null !== clinic.distanceKm ? formatKm( clinic.distanceKm ) + ' (straight-line)' : 'Distance not available for this clinic' } ) );
			}
		}

		var fee = online ? doc.video.fee : clinic.fee;
		var next = online ? doc.video.next_available : clinic.next_available;
		var facts = h( 'dl', { class: 'dak-sf-facts' } );

		if ( fee ) {
			facts.appendChild( h( 'div', {}, [ h( 'dt', { text: online ? 'Video fee' : 'Fee' } ), h( 'dd', { text: fee } ) ] ) );
		}

		if ( next ) {
			facts.appendChild( h( 'div', {}, [ h( 'dt', { text: 'Next available' } ), h( 'dd', { text: next } ) ] ) );
		}

		if ( facts.children.length ) {
			body.appendChild( facts );
		}

		if ( ! online && entry.others.length ) {
			var more = h( 'details', { class: 'dak-sf-more' }, [ h( 'summary', { text: 'Also at ' + entry.others.length + ' other clinic' + ( 1 === entry.others.length ? '' : 's' ) } ) ] );

			entry.others.forEach( function ( c ) {
				var url = safeUrl( c.booking_url );
				more.appendChild( h( 'p', {}, [
					c.label + ( c.place ? ' · ' + c.place : '' ) + ( 'distance' === entry.mode && null !== c.distanceKm ? ' · ' + formatKm( c.distanceKm ) : '' ) + ' ',
					url ? h( 'a', { href: url, text: 'Book here', 'aria-label': 'Book ' + doc.name + ' at ' + c.label } ) : null,
				] ) );
			} );

			body.appendChild( more );
		}

		body.appendChild( h( 'div', { class: 'dak-sf-doc-actions' }, [
			profile ? h( 'a', { class: 'dak-button dak-button-secondary dak-sf-btn', href: profile, text: 'View profile', 'aria-label': 'View profile of ' + doc.name } ) : null,
			book ? h( 'a', { class: 'dak-button dak-button-primary dak-sf-btn', href: book, text: online ? 'Book video consultation' : 'Book appointment', 'aria-label': ( online ? 'Book a video consultation with ' : 'Book an appointment with ' ) + doc.name } ) : null,
		] ) );

		return h( 'article', { class: 'dak-sf-doc' }, [ avatar( doc ), body ] );
	}

	function group( title, note, entries, online ) {
		var wrap = h( 'div', { class: 'dak-sf-group' }, [ h( 'h3', { class: 'dak-sf-group-title', text: title } ), note ? h( 'p', { class: 'dak-sf-hint', text: note } ) : null ] );
		var listEl = h( 'div', { class: 'dak-sf-doc-list' } );
		var shown = 0;

		function more() {
			entries.slice( shown, shown + 6 ).forEach( function ( e ) {
				listEl.appendChild( card( e, online ) );
			} );
			shown = Math.min( entries.length, shown + 6 );
		}

		more();
		wrap.appendChild( listEl );

		if ( entries.length > shown ) {
			var btn = h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Show more doctors', onclick: function () {
				var before = shown;
				more();

				var first = listEl.children[ before ] && listEl.children[ before ].querySelector( 'h3' );

				if ( first ) {
					first.setAttribute( 'tabindex', '-1' );
					first.focus();
				}

				if ( shown >= entries.length ) {
					btn.remove();
				}
			} } );
			wrap.appendChild( btn );
		}

		return wrap;
	}

	function fallbackLinks() {
		var p = h( 'p', { class: 'dak-sf-fallback' } );

		if ( safeUrl( data.gpUrl ) ) {
			p.appendChild( h( 'a', { class: 'dak-button dak-button-secondary dak-sf-btn', href: data.gpUrl, text: 'Browse general physicians' } ) );
		}

		if ( safeUrl( data.directoryUrl ) ) {
			p.appendChild( h( 'a', { class: 'dak-button dak-button-secondary dak-sf-btn', href: data.directoryUrl, text: 'Search all doctors' } ) );
		}

		return p;
	}

	function locationBar() {
		var bar = h( 'div', { class: 'dak-sf-locbar' } );
		var text;

		if ( 'place' === loc.mode && loc.city ) {
			var city = cityOf( loc.city );
			var area = city && loc.area ? city.areas.filter( function ( a ) {
				return a.slug === loc.area;
			} )[ 0 ] : null;
			text = 'Showing doctors in ' + ( area ? area.label + ', ' : '' ) + ( city ? city.label : loc.city ) + ' — not sorted by distance.';
		} else if ( 'auto' === loc.mode && 'locating' === loc.status ) {
			text = 'Finding your location…';
		} else if ( 'auto' === loc.mode && 'granted' === loc.status ) {
			text = 'Sorted by straight-line distance from your location' + ( loc.place ? ' (near ' + loc.place + ')' : '' ) + '.';
		} else {
			text = 'Your location isn’t being used, so doctors aren’t sorted by distance.';
		}

		bar.appendChild( h( 'p', { text: text } ) );
		bar.appendChild( h( 'button', {
			type: 'button',
			class: 'dak-sf-link',
			'aria-expanded': loc.panel ? 'true' : 'false',
			text: 'Change location',
			onclick: function () {
				loc.panel = ! loc.panel;
				renderResults();
				var first = results.querySelector( '.dak-sf-locpanel button, .dak-sf-locpanel select' );

				if ( first && loc.panel ) {
					first.focus();
				}
			},
		} ) );

		return bar;
	}

	function locationPanel( reason ) {
		var panel = h( 'div', { class: 'dak-sf-locpanel' } );

		if ( reason ) {
			panel.appendChild( h( 'p', { text: reason } ) );
		}

		panel.appendChild( h( 'button', {
			type: 'button',
			class: 'dak-button dak-button-secondary dak-sf-btn',
			text: 'granted' === loc.status ? 'Use my current location' : ( 'idle' === loc.status ? 'Use my location' : 'Try my location again' ),
			onclick: function () {
				loc.mode = 'auto';
				loc.panel = false;

				if ( 'granted' === loc.status ) {
					renderResults( 'Doctors sorted by distance from your location.', true );
				} else {
					locate();
				}
			},
		} ) );

		if ( 'denied' === loc.status ) {
			panel.appendChild( h( 'p', { class: 'dak-sf-hint', text: 'Location is blocked for this site. To use it, allow location in your browser’s settings, then try again.' } ) );
		}

		var cities = data.locations || [];

		if ( cities.length ) {
			var citySel = h( 'select', { id: 'dak-sf-city', class: 'dak-sf-input' }, [ h( 'option', { value: '', text: 'Choose a city' } ) ].concat( cities.map( function ( c ) {
				return h( 'option', { value: c.slug, text: c.label, selected: c.slug === loc.city } );
			} ) ) );
			var areaSel = h( 'select', { id: 'dak-sf-area', class: 'dak-sf-input' } );

			function fillAreas() {
				var city = cityOf( citySel.value );
				areaSel.textContent = '';
				areaSel.appendChild( h( 'option', { value: '', text: city ? 'Any area' : 'Choose a city first' } ) );
				( city ? city.areas : [] ).forEach( function ( a ) {
					areaSel.appendChild( h( 'option', { value: a.slug, text: a.label, selected: a.slug === loc.area } ) );
				} );
				areaSel.disabled = ! city || ! city.areas.length;
			}

			citySel.addEventListener( 'change', fillAreas );
			fillAreas();

			panel.appendChild( h( 'div', { class: 'dak-sf-place' }, [
				h( 'div', {}, [ h( 'label', { for: 'dak-sf-city', class: 'dak-sf-label', text: 'City (optional)' } ), citySel ] ),
				h( 'div', {}, [ h( 'label', { for: 'dak-sf-area', class: 'dak-sf-label', text: 'Area (optional)' } ), areaSel ] ),
				h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Show doctors there', onclick: function () {
					if ( ! citySel.value ) {
						citySel.focus();
						return;
					}

					loc.mode = 'place';
					loc.city = citySel.value;
					loc.area = areaSel.value;
					loc.panel = false;
					renderResults( 'Showing doctors in the place you chose.', true );
				} } ),
			] ) );
		}

		panel.appendChild( h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Browse without location', onclick: function () {
			loc.mode = 'none';
			loc.panel = false;
			renderResults( 'Showing doctors without location.', true );
		} } ) );

		return panel;
	}

	/**
	 * @param {string}  message   Announced to screen readers.
	 * @param {boolean} moveFocus The visitor just acted here — focus the heading
	 *                            (never for the location arriving on its own).
	 */
	function renderResults( message, moveFocus ) {
		if ( ! searched ) {
			return;
		}

		// Redrawing removes whatever had focus inside the results; put it
		// back on the heading rather than losing it to the page.
		var focusTitle = !! moveFocus || ( document.activeElement !== document.body && results.contains( document.activeElement ) );
		results.textContent = '';
		results.hidden = false;

		results.appendChild( h( 'h2', { id: 'dak-sf-results-title', tabindex: '-1', text: 'Doctors for your symptoms' } ) );

		var specs = m.specialtiesFor( selected );

		if ( ! specs.length ) {
			results.appendChild( h( 'div', { class: 'dak-sf-empty' }, [
				h( 'p', { text: selected.length ? 'The symptoms you chose aren’t linked to a specialty yet.' : 'We couldn’t reliably match what you entered to symptoms in our list, so we can’t suggest a specialty.' } ),
				h( 'p', { text: 'A general physician can see most symptoms and refer you to a specialist if needed. You can also search all doctors yourself.' } ),
				fallbackLinks(),
			] ) );
			afterRender( message, focusTitle );
			return;
		}

		results.appendChild( h( 'p', { class: 'dak-sf-explain', text: 'Doctors in specialties associated with the symptoms you selected. This is not a diagnosis, and it doesn’t mean a particular doctor is right for you — the doctor will advise you at your appointment.' } ) );

		results.appendChild( h( 'ul', { class: 'dak-sf-specs', 'aria-label': 'Related specialties' }, specs.map( function ( s ) {
			return h( 'li', {}, [ h( 'strong', { text: specialtyLabel( s.slug ) } ), h( 'span', { text: 'linked to ' + s.symptoms.join( ', ' ) } ) ] );
		} ) ) );

		results.appendChild( locationBar() );

		var failed = 'auto' === loc.mode && ( 'denied' === loc.status || 'unavailable' === loc.status || 'timeout' === loc.status );

		if ( loc.panel || failed ) {
			var reason = null;

			if ( failed ) {
				reason = 'denied' === loc.status
					? 'You didn’t share your location, so we can’t sort doctors by distance. You can choose a city or area instead, or browse without location.'
					: ( 'timeout' === loc.status ? 'Finding your location took too long.' : 'Your location isn’t available on this device.' ) + ' You can choose a city or area instead, or browse without location.';
			}

			results.appendChild( locationPanel( reason ) );
		}

		if ( 'auto' === loc.mode && 'locating' === loc.status ) {
			results.appendChild( h( 'p', { class: 'dak-sf-loading', role: 'status', text: 'Finding your location…' } ) );
			afterRender( message, focusTitle );
			return;
		}

		var ranked = Match.rankDoctors( data.doctors || [], specs, rankOptions() );

		ranked.clinic.forEach( function ( e ) {
			e.mode = ranked.mode;
		} );

		if ( ranked.clinic.length ) {
			results.appendChild( group(
				'distance' === ranked.mode ? 'Nearest first' : 'Clinic appointments',
				'distance' === ranked.mode ? 'Distances are straight-line estimates from your location, not travel distances.' : null,
				ranked.clinic,
				false
			) );
		} else {
			results.appendChild( h( 'div', { class: 'dak-sf-empty' }, [
				h( 'p', { text: 'place' === ranked.mode ? 'No doctors in these specialties have a clinic in the place you chose.' : 'No doctors in these specialties are listed yet.' } ),
				'place' === ranked.mode ? h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Show all locations', onclick: function () {
					loc.mode = 'none';
					renderResults( 'Showing doctors in all locations.', true );
				} } ) : null,
				fallbackLinks(),
			] ) );
		}

		if ( ranked.online.length ) {
			results.appendChild( group( 'Online video consultations', 'Talk to a doctor by video. They will tell you if you need to be seen in person.', ranked.online, true ) );
		}

		results.appendChild( h( 'p', { class: 'dak-sf-hint' }, [
			h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Change symptoms', onclick: function () {
				input.focus();
				input.scrollIntoView( { block: 'center' } );
			} } ),
		] ) );

		afterRender( message, focusTitle );
	}

	function afterRender( message, focusTitle ) {
		if ( message ) {
			announce( message );
		}

		if ( focusTitle ) {
			var title = document.getElementById( 'dak-sf-results-title' );

			if ( title ) {
				title.focus();
			}
		}
	}

	root.querySelector( '[data-sf-find]' ).addEventListener( 'click', function () {
		// Anything still in the box counts too.
		if ( input.value.trim() ) {
			readText( input.value );
		}

		if ( ! selected.length && ( questions.length ) ) {
			showError( 'Please answer “did you mean” above, or choose “None of these”.' );
			clarify.querySelector( 'button' ).focus();
			return;
		}

		if ( ! selected.length && ! unmatched.length ) {
			showError( 'Add at least one symptom first.' );
			input.focus();
			return;
		}

		showError( '' );
		searched = true;

		// Only worth asking when there are doctors to sort.
		if ( m.specialtiesFor( selected ).length ) {
			startLocation();
		}

		renderResults( 'Results updated.', true );
		results.scrollIntoView( { block: 'start' } );
	} );
}() );
