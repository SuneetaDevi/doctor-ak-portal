/**
 * Doctor AK Portal — site-wide "Book Appointment" trigger.
 *
 * Every element carrying `data-dak-book-appointment` (header nav, doctor
 * directory cards, a doctor's profile-view page, the patient dashboard)
 * navigates away instead of opening a popup. One naming a specific doctor
 * (`data-doctor-id`, e.g. from that doctor's card/profile) goes straight to
 * the booking page with the doctor/type carried over as query args, landing
 * past its own Doctor step. A generic trigger with no doctor — header nav,
 * homepage quick-access cards, patient dashboard quick actions — goes to the
 * booking page too, but with no doctor_id, so it lands on that page's own
 * Doctor step (searchable/filterable there — see doctor-ak-booking-page.js)
 * rather than a separate directory page; a `data-booking-type` still carries
 * over on its own (e.g. `?type=video`) so that step can preselect it.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-dak-book-appointment]' );

		if ( ! trigger || ! window.dakBookingRedirect || ! window.dakBookingRedirect.pageUrl ) {
			return;
		}

		event.preventDefault();

		var doctorId    = trigger.getAttribute( 'data-doctor-id' );
		var bookingType = trigger.getAttribute( 'data-booking-type' );
		var params      = [];

		if ( doctorId ) {
			params.push( 'doctor_id=' + encodeURIComponent( doctorId ) );
		}

		if ( bookingType ) {
			params.push( 'type=' + encodeURIComponent( bookingType ) );
		}

		if ( ! params.length ) {
			window.location.href = window.dakBookingRedirect.pageUrl;

			return;
		}

		var separator = window.dakBookingRedirect.pageUrl.indexOf( '?' ) === -1 ? '?' : '&';
		var target = window.dakBookingRedirect.pageUrl + separator + params.join( '&' );

		window.location.href = target;
	} );
} )();
