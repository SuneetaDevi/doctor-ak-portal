/**
 * Doctor AK Portal — clinic page ([clinic_profile_view]).
 *
 * - Doctor name search + specialty filter (rendered only for clinics with
 *   several doctors).
 * - The "Clinics" breadcrumb returns to the finder with the same search and
 *   filters when that's where the visitor came from.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		restoreFinderLink();
		initDoctorFilter();
	} );

	function restoreFinderLink() {
		var link = document.querySelector( '[data-clinics-back]' );

		if ( ! link || ! document.referrer || ! window.URL ) {
			return;
		}

		try {
			var from = new URL( document.referrer );
			var target = new URL( link.href );

			if ( from.origin === target.origin && from.pathname === target.pathname ) {
				link.href = from.toString();
			}
		} catch ( e ) {
			// Unparseable referrer — keep the plain link.
		}
	}

	function initDoctorFilter() {
		var box = document.querySelector( '[data-doctor-filter]' );

		if ( ! box ) {
			return;
		}

		var q = document.getElementById( 'dak-cp-q' );
		var specialty = document.getElementById( 'dak-cp-specialty' );
		var countEl = document.getElementById( 'dak-cp-count' );
		var emptyEl = document.getElementById( 'dak-cp-empty' );
		var reset = document.getElementById( 'dak-cp-reset' );
		var doctors = Array.prototype.slice.call( document.querySelectorAll( '[data-doctor]' ) );
		var total = doctors.length;

		q.addEventListener( 'input', apply );

		if ( specialty ) {
			specialty.addEventListener( 'change', apply );
		}

		if ( reset ) {
			reset.addEventListener( 'click', function () {
				q.value = '';

				if ( specialty ) {
					specialty.value = '';
				}

				apply();
				q.focus();
			} );
		}

		function apply() {
			var words = q.value.trim().toLowerCase().replace( /^dr\.?\s*/, '' ).split( /\s+/ ).filter( Boolean );
			var spec = specialty ? specialty.value : '';
			var shown = 0;

			doctors.forEach( function ( card ) {
				var name = card.getAttribute( 'data-name' ) || '';
				var specs = ( card.getAttribute( 'data-specialties' ) || '' ).split( '|' );
				var ok = words.every( function ( word ) {
					return -1 !== name.indexOf( word );
				} ) && ( ! spec || -1 !== specs.indexOf( spec ) );

				card.hidden = ! ok;

				if ( ok ) {
					shown++;
				}
			} );

			var filtered = words.length > 0 || !! spec;

			countEl.textContent = filtered
				? format( countEl.getAttribute( 'data-one' ), [ shown, total ] )
				: format( countEl.getAttribute( 'data-all' ), [ total ] );

			emptyEl.classList.toggle( 'dak-hidden', shown > 0 );
		}
	}

	function format( template, values ) {
		var i = 0;

		return String( template || '' ).replace( /%(\d\$)?s/g, function ( match, position ) {
			return String( values[ position ? parseInt( position, 10 ) - 1 : i++ ] );
		} );
	}
}() );
