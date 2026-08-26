<?php
/**
 * Backs the [service_profile_view] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Service_Catalog;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Profile_View
 *
 * A public, read-only detail page for one Service_Catalog entry — reached
 * via `?service_id=` on whichever page contains [service_profile_view]
 * (found dynamically by Page_Finder, same pattern as Doctor_Profile_View).
 * Shows the service's price against every clinic it's offered at and every
 * doctor who provides it, then a "Book Appointment" button into the normal
 * booking flow (pre-selecting the doctor when the service has exactly one).
 */
class Service_Profile_View {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'service_profile_view';

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader $template_loader Template loader.
	 */
	public function __construct( Template_Loader $template_loader ) {
		$this->template_loader = $template_loader;
	}

	/**
	 * Enqueues assets only on pages containing [service_profile_view].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_profile_view_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directory.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-directory.css' )
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$service_id = isset( $_GET['service_id'] ) ? absint( $_GET['service_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public lookup.
		$service    = $service_id > 0 ? Service_Catalog::find( $service_id ) : null;

		if ( $service && ! $service['active'] ) {
			$service = null;
		}

		$booking_url = Page_Finder::url_for_shortcode( 'book_appointment' );

		// Only preselect a doctor on the booking page when this service has
		// exactly one — with several, the patient still needs to choose
		// which of them they want, same as arriving at booking any other way.
		if ( $service && 1 === count( $service['doctors'] ) && $booking_url ) {
			$booking_url = add_query_arg( 'doctor_id', $service['doctors'][0]['id'], $booking_url );
		}

		return $this->template_loader->get_template(
			'directory/service-profile-view.php',
			array(
				'service'       => $service,
				'directory_url' => Page_Finder::url_for_shortcode( 'services_directory' ),
				'booking_url'   => $booking_url,
			)
		);
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * profile-view shortcode.
	 *
	 * @return bool
	 */
	private function is_profile_view_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
