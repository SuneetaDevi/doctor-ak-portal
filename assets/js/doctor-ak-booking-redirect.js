/**
 * Doctor AK Portal — site-wide "Book Appointment" trigger.
 *
 * Every element carrying `data-dak-book-appointment` (header nav, doctor
 * directory cards, a doctor's profile-view page, the patient dashboard)
 * navigates away instead of opening a popup. One naming a specific doctor
 * (`data-doctor-id`, e.g. from that doctor's card/profile) goes straight to
 * the booking page with the doctor/type carried over as query args. A
 * generic trigger with no doctor — header nav, homepage, footer, patient
 * dashboard "Book Now" — goes to the doctors directory instead, so the
 * visitor picks a doctor there rather than on a doctor-picker step inside
 * the booking wizard.
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

		if ( ! doctorId ) {
			if ( window.dakBookingRedirect.directoryUrl ) {
				window.location.href = window.dakBookingRedirect.directoryUrl;
			}

			return;
		}

		var bookingType = trigger.getAttribute( 'data-booking-type' );
		var params = [ 'doctor_id=' + encodeURIComponent( doctorId ) ];

		if ( bookingType ) {
			params.push( 'type=' + encodeURIComponent( bookingType ) );
		}

		var separator = window.dakBookingRedirect.pageUrl.indexOf( '?' ) === -1 ? '?' : '&';
		var target = window.dakBookingRedirect.pageUrl + separator + params.join( '&' );

		window.location.href = target;
	} );
} )();
