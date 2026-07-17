<?php
/**
 * Site-wide "Book Appointment" trigger wiring: navigates any
 * `[data-dak-book-appointment]` element to the booking page instead of
 * opening a popup.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Booking_Trigger
 *
 * Every "Book Appointment" button across the site (header nav, doctor
 * directory cards, a doctor's profile-view page, the patient dashboard)
 * already carries `data-dak-book-appointment` plus optional
 * `data-doctor-id`/`data-doctor-name`/`data-video-disabled`/`data-booking-type`
 * attributes — originally read by the popup modal's JS. Rather than touch
 * every template that renders one of those buttons, this keeps the same
 * attribute contract and just redirects the browser to Booking_Page with
 * the doctor/type carried over as query args.
 */
class Booking_Trigger {

	/**
	 * Enqueues the redirect script on every front-end page.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'doctor-ak-portal-booking-redirect',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-booking-redirect.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-booking-redirect.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-booking-redirect',
			'dakBookingRedirect',
			array(
				'pageUrl' => Page_Finder::url_for_shortcode( Booking_Page::SHORTCODE_TAG ),
			)
		);
	}
}
