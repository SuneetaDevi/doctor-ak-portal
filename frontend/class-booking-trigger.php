<?php
/**
 * Site-wide "Book Appointment" trigger wiring: navigates any
 * `[data-dak-book-appointment]` element to the booking page, skipping
 * straight to its Service step when it names a specific doctor, or landing
 * on its own searchable/filterable Doctor step (see
 * doctor-ak-booking-page.js) when it doesn't, instead of opening a popup.
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
 * with the doctor/type carried over as query args, landing past its Doctor
 * step; without one (a generic trigger — header nav, homepage, footer,
 * patient dashboard quick actions) it still goes to Booking_Page, but with
 * no doctor_id, so the visitor picks one on that page's own Doctor step.
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
