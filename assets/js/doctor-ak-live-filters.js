/**
 * Doctor AK Portal — generic "live filter" wiring shared by every filter
 * form on the Admin/Doctor/Patient dashboards (Appointments, Doctors,
 * Patients, Receptionists). Any `<form data-live-filter="...">` auto-submits
 * over AJAX on every select/date change (no "Filter" click, no page reload)
 * and swaps the HTML inside `data-live-filter-target` for the response.
 * `<a data-live-filter-clear>` links (the "Clear"/"Reset filters" links)
 * work the same way, reading their own href's query string as the filters
 * to submit.
 *
 * Required attributes on the form (or clear link):
 * - data-live-filter        AJAX action name.
 * - data-live-filter-target CSS selector of the element whose innerHTML gets replaced.
 * - data-live-filter-nonce  Name of the localized window object holding { ajaxUrl, nonce }.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( 'form[data-live-filter]' ).forEach( wireForm );
		wireClearLinks();
	} );

	function wireForm( form ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submitFilter( form );
		} );

		form.querySelectorAll( 'select, input[type="date"]' ).forEach( function ( field ) {
			field.addEventListener( 'change', function () {
				submitFilter( form );
			} );
		} );
	}

	function wireClearLinks() {
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-live-filter-clear]' );

			if ( ! link ) {
				return;
			}

			event.preventDefault();

			var url = new URL( link.href, window.location.href );
			var formData = new FormData();

			url.searchParams.forEach( function ( value, key ) {
				formData.append( key, value );
			} );

			runFilter(
				link.getAttribute( 'data-live-filter' ),
				link.getAttribute( 'data-live-filter-nonce' ),
				formData,
				link.getAttribute( 'data-live-filter-target' ),
				link.href
			);
		} );
	}

	function submitFilter( form ) {
		var formData = new FormData( form );
		var url = new URL( window.location.href );

		formData.forEach( function ( value, key ) {
			url.searchParams.set( key, value );
		} );

		runFilter(
			form.getAttribute( 'data-live-filter' ),
			form.getAttribute( 'data-live-filter-nonce' ),
			formData,
			form.getAttribute( 'data-live-filter-target' ),
			url.toString()
		);
	}

	function runFilter( action, nonceSource, formData, targetSelector, newUrl ) {
		var target = targetSelector ? document.querySelector( targetSelector ) : null;
		var nonceData = nonceSource ? window[ nonceSource ] : null;

		if ( ! target || ! nonceData || ! action ) {
			return;
		}

		formData.set( 'action', action );
		formData.set( 'nonce', nonceData.nonce );

		target.classList.add( 'dak-is-loading' );

		fetch( nonceData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				target.classList.remove( 'dak-is-loading' );

				if ( result.success && result.data && 'string' === typeof result.data.html ) {
					target.innerHTML = result.data.html;
					target.querySelectorAll( 'form[data-live-filter]' ).forEach( wireForm );

					// The swapped-in HTML has its own fresh doctor/patient
					// <select> nodes — enhance() only runs once at page load,
					// so these need re-enhancing explicitly.
					if ( window.DAKSearchableSelect ) {
						target.querySelectorAll( 'select.dak-select-searchable' ).forEach( window.DAKSearchableSelect.enhance );
					}

					if ( newUrl ) {
						window.history.replaceState( null, '', newUrl );
					}
				}
			} )
			.catch( function () {
				target.classList.remove( 'dak-is-loading' );
			} );
	}
} )();
