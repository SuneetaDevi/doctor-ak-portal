/**
 * Doctor AK Portal — site-wide "Book Appointment" trigger.
 *
 * Every element carrying `data-dak-book-appointment` (header nav, doctor
 * directory cards, a doctor's profile-view page, the patient dashboard)
 * navigates to the booking page instead of opening a popup, carrying over
 * any pre-selected doctor/type via query args.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-dak-book-appointment]' );

		if ( ! trigger || ! window.dakBookingRedirect || ! window.dakBookingRedirect.pageUrl ) {
			return;
		}

		event.preventDefault();

		var doctorId = trigger.getAttribute( 'data-doctor-id' );
		var bookingType = trigger.getAttribute( 'data-booking-type' );
		var params = [];

		if ( doctorId ) {
			params.push( 'doctor_id=' + encodeURIComponent( doctorId ) );
		}

		if ( bookingType ) {
			params.push( 'type=' + encodeURIComponent( bookingType ) );
		}

		var separator = window.dakBookingRedirect.pageUrl.indexOf( '?' ) === -1 ? '?' : '&';
		var target = window.dakBookingRedirect.pageUrl + ( params.length ? separator + params.join( '&' ) : '' );

		window.location.href = target;
	} );
} )();
