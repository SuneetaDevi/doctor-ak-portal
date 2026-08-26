<?php
/**
 * Backs the [services_directory] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Services;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Services_Directory
 *
 * A public, unauthenticated grid of every active Services row (the same
 * rows the admin/doctor "Services" section already manages — see the
 * Services class), each card linking to that service's own
 * [service_profile_view] detail page — the same directory/profile-view
 * split already used for Doctors (see Doctors_Directory/Doctor_Profile_View).
 */
class Services_Directory {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'services_directory';

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
	 * Enqueues directory assets only on pages containing [services_directory].
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
		$services_html = array_map(
			function ( $service ) {
				return $this->template_loader->get_template( 'directory/service-card.php', $this->card_data( $service ) );
			},
			Services::active_for_public_directory()
		);

		return $this->template_loader->get_template(
			'directory/services-directory.php',
			array( 'services_html' => $services_html )
		);
	}

	/**
	 * Builds a single service card's view-model.
	 *
	 * @param array $service Decoded Services row.
	 * @return array
	 */
	private function card_data( array $service ) {
		$doctor              = get_userdata( $service['doctor_id'] );
		$doctor_display_name = trim( ( $doctor ? $doctor->first_name : '' ) . ' ' . ( $doctor ? $doctor->last_name : '' ) );

		$service['doctor_name']  = '' !== $doctor_display_name ? $doctor_display_name : ( $doctor ? $doctor->display_name : '' );
		$service['profile_url']  = add_query_arg( 'service_id', $service['id'], Page_Finder::url_for_shortcode( 'service_profile_view' ) );

		return $service;
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
