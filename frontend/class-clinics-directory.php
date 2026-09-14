<?php
/**
 * Backs the [clinics_directory] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinics_Directory
 *
 * A public, unauthenticated grid — one card per Clinic_Locations row (the
 * admin-managed physical-location list already shown on the home page's
 * "Visit Us" section and grouped by city in the footer), each linking to
 * that clinic's own [clinic_profile_view] page — same directory/profile-view
 * split already used for Doctors/Services/Blogs.
 */
class Clinics_Directory {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'clinics_directory';

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
	 * Enqueues directory assets only on pages containing [clinics_directory].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_directory_page() ) {
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
		$profile_url = Page_Finder::url_for_shortcode( 'clinic_profile_view' );

		$clinics_html = array_map(
			function ( $clinic_location ) use ( $profile_url ) {
				return $this->template_loader->get_template( 'directory/clinic-card.php', $this->card_data( $clinic_location, $profile_url ) );
			},
			Clinic_Locations::get_all()
		);

		return $this->template_loader->get_template(
			'directory/clinics-directory.php',
			array( 'clinics_html' => $clinics_html )
		);
	}

	/**
	 * Builds a single clinic card's view-model.
	 *
	 * @param array  $clinic_location One Clinic_Locations::decode_row() row.
	 * @param string $profile_url     Base [clinic_profile_view] URL, or ''.
	 * @return array
	 */
	private function card_data( array $clinic_location, $profile_url ) {
		$clinic_location['doctor_count'] = count( Clinics::get_by_clinic_location( $clinic_location['id'] ) );
		$clinic_location['profile_url']  = $profile_url ? add_query_arg( 'clinic_id', $clinic_location['id'], $profile_url ) : '';

		return $clinic_location;
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * directory shortcode.
	 *
	 * @return bool
	 */
	private function is_directory_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
