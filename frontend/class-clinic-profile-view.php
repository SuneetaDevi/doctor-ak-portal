<?php
/**
 * Backs the [clinic_profile_view] shortcode.
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
 * Class Clinic_Profile_View
 *
 * A public, read-only detail page for one Clinic_Locations row, reached via
 * `?clinic_id=` on whichever page contains [clinic_profile_view] (found
 * dynamically by Page_Finder, same pattern as Doctor_Profile_View/
 * Service_Profile_View). Lists every doctor aligned to this clinic (see
 * Clinics::get_by_clinic_location()), reusing Doctors_Directory's own card
 * view-model/template so a doctor looks identical here as on the main
 * Doctors directory.
 */
class Clinic_Profile_View {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'clinic_profile_view';

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Doctors directory controller — supplies this page's "Doctors at this
	 * clinic" cards (same view-model/template the main directory uses).
	 *
	 * @var Doctors_Directory
	 */
	private $doctors_directory;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader   $template_loader   Template loader.
	 * @param Doctors_Directory $doctors_directory Doctors directory controller.
	 */
	public function __construct( Template_Loader $template_loader, Doctors_Directory $doctors_directory ) {
		$this->template_loader   = $template_loader;
		$this->doctors_directory = $doctors_directory;
	}

	/**
	 * Enqueues assets only on pages containing [clinic_profile_view].
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
		$clinic_location_id = isset( $_GET['clinic_id'] ) ? absint( $_GET['clinic_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public lookup.
		$clinic              = $clinic_location_id > 0 ? Clinic_Locations::find( $clinic_location_id ) : null;
		$doctors_html        = array();

		if ( $clinic ) {
			$doctor_ids = wp_list_pluck( Clinics::get_by_clinic_location( $clinic_location_id ), 'doctor_id' );

			$doctors_html = array_map(
				function ( $card ) {
					return $this->template_loader->get_template( 'directory/doctor-card.php', $card );
				},
				$this->doctors_directory->doctor_cards_data_for_ids( $doctor_ids )
			);
		}

		return $this->template_loader->get_template(
			'directory/clinic-profile-view.php',
			array(
				'clinic'        => $clinic,
				'doctors_html'  => $doctors_html,
				'directory_url' => Page_Finder::url_for_shortcode( 'clinics_directory' ),
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
