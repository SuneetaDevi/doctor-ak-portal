<?php
/**
 * Backs the [doctor_profile_view] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Profile_View
 *
 * A deliberately minimal, public, read-only profile page (the "View
 * Profile" destination from the directory), showing only what's already
 * collected at registration. No editing, reviews, or bio field — those are
 * a later phase.
 */
class Doctor_Profile_View {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'doctor_profile_view';

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
	 * Enqueues assets only on pages containing [doctor_profile_view].
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
		$doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public profile lookup.
		$doctor    = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
			return $this->template_loader->get_template( 'directory/doctor-profile-view.php', array( 'doctor' => null ) );
		}

		$specialization_slugs = (array) get_user_meta( $doctor->ID, 'doctor_ak_specializations', true );
		$all_specializations   = Specializations::get_all();

		$specialization_labels = array_map(
			function ( $slug ) use ( $all_specializations ) {
				return isset( $all_specializations[ $slug ] ) ? $all_specializations[ $slug ] : $slug;
			},
			$specialization_slugs
		);

		$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
		$display_name = '' !== $display_name ? $display_name : $doctor->display_name;

		return $this->template_loader->get_template(
			'directory/doctor-profile-view.php',
			array(
				'doctor'                => array(
					'id'                    => $doctor->ID,
					'name'                  => $display_name,
					'avatar_url'            => self::avatar_url( $doctor->ID ),
					'specialization_labels' => $specialization_labels,
					'clinics'               => Clinics::get_for_doctor( $doctor->ID ),
					'years_experience'      => get_user_meta( $doctor->ID, 'doctor_ak_years_experience', true ),
					'video_consultation'    => Clinics::doctor_has_active_video_clinic( $doctor->ID ),
				),
			)
		);
	}

	/**
	 * Resolves a doctor's uploaded profile picture, falling back to a
	 * generic avatar if they haven't uploaded one.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private static function avatar_url( $doctor_id ) {
		$picture_id = (int) get_user_meta( $doctor_id, 'doctor_ak_profile_picture_id', true );

		if ( $picture_id > 0 ) {
			$url = wp_get_attachment_image_url( $picture_id, 'medium' );

			if ( $url ) {
				return $url;
			}
		}

		return get_avatar_url( $doctor_id, array( 'size' => 200 ) );
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
