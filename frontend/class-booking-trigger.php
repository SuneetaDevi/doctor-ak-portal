<?php
/**
 * Site-wide "Book Appointment" trigger wiring: navigates any
 * `[data-dak-book-appointment]` element to the booking page (skipping
 * straight to the Service step) when it names a specific doctor, or to the
 * doctors directory to pick one first when it doesn't, instead of opening a
 * popup.
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
 * attribute contract: with a `data-doctor-id` (i.e. triggered against a
 * specific doctor, such as their profile page) it redirects to Booking_Page
 * with the doctor/type carried over as query args; without one (a generic
 * trigger — header nav, homepage, footer, patient dashboard "Book Now") it
 * sends the visitor to the doctors directory to choose a doctor first,
 * rather than dropping them on Booking_Page's own (now rarely-used, only
 * reachable by direct URL) Doctor step.
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
				'pageUrl'      => Page_Finder::url_for_shortcode( Booking_Page::SHORTCODE_TAG ),
				'directoryUrl' => Page_Finder::url_for_shortcode( Doctors_Directory::SHORTCODE_TAG ),
			)
		);
	}
}
