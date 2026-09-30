/**
 * Doctor AK Portal — public doctor profile page's selectable Services list.
 *
 * Every service is listed with all of its clinics always visible
 * underneath it (see templates/directory/doctor-profile-view.php) — no
 * expand/collapse. Clicking a clinic row (nested under a service, or the
 * standalone "Online Video Consultation" row) selects it (single-select
 * across the whole list) and updates the sidebar booking card to that row's
 * own fee, enabling the (initially disabled) "Book Appointment" button
 * there. That button carries `data-dak-book-appointment` like every other
 * one on the site, so once enabled the site-wide
 * doctor-ak-booking-redirect.js still handles the actual click-through,
 * reading whichever `data-booking-type`/`data-service-id`/`data-clinic-id`
 * this file has just set on it — carrying the service (and clinic, when one
 * applies) the patient just picked here straight into the booking wizard,
 * instead of being asked again there (see
 * Booking_Page::resolved_selection()).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var list = document.getElementById( 'dak-profile-services-list' );
		var button = document.getElementById( 'dak-profile-booking-button' );

		if ( ! list || ! button ) {
			return;
		}

		var rows = Array.prototype.slice.call( list.querySelectorAll( '[data-clinic-select]' ) );

		rows.forEach( function ( row ) {
			row.addEventListener( 'click', function () {
				selectClinic( row );
			} );

			row.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key || ' ' === event.key ) {
					event.preventDefault();
					selectClinic( row );
				}
			} );
		} );

		function selectClinic( selectedRow ) {
			rows.forEach( function ( row ) {
				var isSelected = row === selectedRow;
				row.classList.toggle( 'is-selected', isSelected );
				row.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );
			} );

			var feeLabel = selectedRow.getAttribute( 'data-fee-label' ) || '';
			var feeBox = document.getElementById( 'dak-profile-booking-fee' );
			var feeLabelEl = document.getElementById( 'dak-profile-booking-fee-label' );
			var feeAmountEl = document.getElementById( 'dak-profile-booking-fee-amount' );
			var hint = document.getElementById( 'dak-profile-booking-hint' );

			if ( feeBox && feeLabelEl && feeAmountEl ) {
				if ( feeLabel ) {
					feeLabelEl.textContent = 'Fee';
					feeAmountEl.textContent = feeLabel;
					feeBox.classList.remove( 'dak-hidden' );
				} else {
					feeBox.classList.add( 'dak-hidden' );
				}
			}

			if ( hint ) {
				hint.classList.add( 'dak-hidden' );
			}

			button.removeAttribute( 'disabled' );
			button.removeAttribute( 'title' );
			button.setAttribute( 'data-booking-type', selectedRow.getAttribute( 'data-booking-type' ) || '' );
			button.setAttribute( 'data-service-id', selectedRow.getAttribute( 'data-service-id' ) || '' );
			button.setAttribute( 'data-clinic-id', selectedRow.getAttribute( 'data-clinic-id' ) || '' );
		}
	} );
} )();
