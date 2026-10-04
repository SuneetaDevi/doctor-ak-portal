/**
 * Doctor AK Portal — shared script for the clinics directory and clinic
 * pages (see Public_Pages::enqueue_assets()).
 *
 * Keeps Elementor's first-load promotional popup off these pages. The popup
 * is a site-wide Elementor template that opens over the page on every
 * visit and would cover the clinic cards and booking buttons here.
 *
 * This runs as soon as it loads — it is printed in the footer after the
 * popup's markup and before Elementor's own frontend scripts — so removing
 * the markup here means Elementor never registers the popup at all (no
 * flash, no focus trap). Every other page keeps it.
 */
( function () {
	'use strict';

	var popups = document.querySelectorAll( '[data-elementor-type="popup"]' );

	Array.prototype.forEach.call( popups, function ( popup ) {
		popup.parentNode.removeChild( popup );
	} );
}() );
