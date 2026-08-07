/**
 * Doctor AK Portal — dashboard topbar search box (Admin: Doctors + Patients
 * + Appointments; Doctor: their own Patients + Appointments). Debounced
 * live search with a click-to-jump results dropdown; each result links to
 * its row via the existing `#dak-user-{id}` / `#dak-patient-{id}` /
 * `#dak-appointment-{id}` anchors (`:target` highlight already built for
 * notification deep-links).
 */
( function () {
	'use strict';

	var GROUP_LABELS = {
		doctors: 'Doctors',
		patients: 'Patients',
		appointments: 'Appointments',
	};

	var DEBOUNCE_MS = 300;
	var MIN_QUERY_LENGTH = 2;

	document.addEventListener( 'DOMContentLoaded', function () {
		var input = document.getElementById( 'dak-dashboard-topbar-search' );
		var results = document.getElementById( 'dak-dashboard-topbar-search-results' );

		if ( ! input || ! results ) {
			return;
		}

		var action = input.getAttribute( 'data-live-search' );
		var nonceSource = input.getAttribute( 'data-live-search-nonce' );
		var groups = ( input.getAttribute( 'data-live-search-groups' ) || '' ).split( ',' ).filter( Boolean );
		var debounceTimer = null;
		var requestId = 0;

		input.addEventListener( 'input', function () {
			var query = input.value.trim();

			clearTimeout( debounceTimer );

			if ( query.length < MIN_QUERY_LENGTH ) {
				hideResults();
				return;
			}

			debounceTimer = setTimeout( function () {
				runSearch( query );
			}, DEBOUNCE_MS );
		} );

		input.addEventListener( 'focus', function () {
			if ( results.children.length > 0 && input.value.trim().length >= MIN_QUERY_LENGTH ) {
				showResults();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( event.target !== input && ! results.contains( event.target ) ) {
				hideResults();
			}
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				hideResults();
				input.blur();
			}
		} );

		function runSearch( query ) {
			var nonceData = nonceSource ? window[ nonceSource ] : null;

			if ( ! nonceData || ! action ) {
				return;
			}

			var thisRequest = ++requestId;
			var formData = new FormData();
			formData.append( 'action', action );
			formData.append( 'nonce', nonceData.nonce );
			formData.append( 'query', query );

			fetch( nonceData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( thisRequest !== requestId ) {
						return; // A newer keystroke's request already landed — discard this stale one.
					}

					if ( result.success && result.data ) {
						renderResults( result.data, query );
					}
				} )
				.catch( function () {
					if ( thisRequest === requestId ) {
						hideResults();
					}
				} );
		}

		function renderResults( data, query ) {
			results.innerHTML = '';

			var totalCount = 0;

			groups.forEach( function ( groupKey ) {
				var items = data[ groupKey ];

				if ( ! items || 0 === items.length ) {
					return;
				}

				totalCount += items.length;

				var groupEl = document.createElement( 'div' );
				groupEl.className = 'dak-search-results-group';

				var heading = document.createElement( 'div' );
				heading.className = 'dak-search-results-group-label';
				heading.textContent = GROUP_LABELS[ groupKey ] || groupKey;
				groupEl.appendChild( heading );

				items.forEach( function ( item ) {
					var link = document.createElement( 'a' );
					link.className = 'dak-search-result-item';
					link.href = item.url || '#';

					var label = document.createElement( 'span' );
					label.className = 'dak-search-result-label';
					label.textContent = item.label;
					link.appendChild( label );

					if ( item.sublabel ) {
						var sublabel = document.createElement( 'span' );
						sublabel.className = 'dak-search-result-sublabel';
						sublabel.textContent = item.sublabel;
						link.appendChild( sublabel );
					}

					groupEl.appendChild( link );
				} );

				results.appendChild( groupEl );
			} );

			if ( 0 === totalCount ) {
				var empty = document.createElement( 'div' );
				empty.className = 'dak-search-results-empty';
				empty.textContent = 'No results found for "' + query + '"';
				results.appendChild( empty );
			}

			showResults();
		}

		function showResults() {
			results.classList.remove( 'dak-hidden' );
		}

		function hideResults() {
			results.classList.add( 'dak-hidden' );
		}
	} );
} )();
