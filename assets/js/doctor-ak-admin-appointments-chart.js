/**
 * Doctor AK Portal — Admin Dashboard overview's "Appointments" clustered bar
 * chart: Day/Week/Month period toggle, re-fetched over AJAX (no page
 * reload). No-ops entirely on any page that doesn't render the chart.
 *
 * Delegated on `document` (rather than bound directly to the chart
 * container) because every period switch replaces the container's own
 * outerHTML with the server's freshly rendered markup — a direct listener
 * on the old node would be discarded along with it.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! document.getElementById( 'dak-appointments-chart' ) || ! window.dakAdminUsers ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '#dak-appointments-chart [data-chart-period]' );

			if ( ! button || button.classList.contains( 'is-active' ) ) {
				return;
			}

			loadPeriod( button.getAttribute( 'data-chart-period' ) );
		} );

		function loadPeriod( period ) {
			var container = document.getElementById( 'dak-appointments-chart' );

			if ( ! container ) {
				return;
			}

			container.classList.add( 'dak-is-loading' );

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_appointments_chart' );
			formData.append( 'nonce', window.dakAdminUsers.nonce );
			formData.append( 'period', period );

			fetch( window.dakAdminUsers.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success && result.data && 'string' === typeof result.data.html ) {
						container.outerHTML = result.data.html;
						return;
					}

					container.classList.remove( 'dak-is-loading' );
				} )
				.catch( function () {
					container.classList.remove( 'dak-is-loading' );
				} );
		}
	} );
} )();
