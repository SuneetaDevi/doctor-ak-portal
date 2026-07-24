<?php
/**
 * Backs the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctors_Directory
 *
 * A public, unauthenticated grid of every registered doctor, each card
 * linking to their (minimal, v1) public profile and to the booking page
 * (Booking_Page) with that doctor pre-selected.
 */
class Doctors_Directory {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'doctors_directory';

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
	 * Enqueues directory assets only on pages containing [doctors_directory].
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

		wp_enqueue_script(
			'doctor-ak-portal-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-directory.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-directory.js' ),
			true
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$cards                 = $this->doctor_cards_data();
		$doctors_html          = array();
		$used_specializations = array();
		$all_specializations   = Specializations::get_all();
		$used_locations        = array();
		$used_clinics          = array();

		foreach ( $cards as $card ) {
			$doctors_html[] = $this->template_loader->get_template( 'directory/doctor-card.php', $card );

			foreach ( $card['specialization_slugs'] as $slug ) {
				if ( isset( $all_specializations[ $slug ] ) ) {
					$used_specializations[ $slug ] = $all_specializations[ $slug ];
				}
			}

			foreach ( $card['location_labels'] as $label ) {
				$used_locations[ $label ] = $label;
			}

			foreach ( $card['clinic_labels'] as $label ) {
				$used_clinics[ $label ] = $label;
			}
		}

		asort( $used_specializations );
		asort( $used_locations );
		asort( $used_clinics );

		return $this->template_loader->get_template(
			'directory/doctors-directory.php',
			array(
				'doctors_html'    => $doctors_html,
				'specializations' => $used_specializations,
				'locations'       => $used_locations,
				'clinics'         => $used_clinics,
			)
		);
	}

	/**
	 * Every registered doctor's directory-card view-model (see card_data()),
	 * ordered by display name. Reused by Featured_Doctors for the homepage
	 * slider so both shortcodes render the exact same card shape/data.
	 *
	 * @param int $limit Max number of doctors to return, or 0 for all.
	 * @return array
	 */
	public function doctor_cards_data( $limit = 0 ) {
		$query = new \WP_User_Query(
			array(
				'role'    => Roles::DOCTOR_ROLE,
				'orderby' => 'display_name',
				'number'  => $limit > 0 ? $limit : 0,
			)
		);

		return array_map( array( $this, 'card_data' ), $query->get_results() );
	}

	/**
	 * Builds a single doctor card's view-model.
	 *
	 * @param \WP_User $doctor Doctor user.
	 * @return array
	 */
	private function card_data( \WP_User $doctor ) {
		$specialization_slugs = (array) get_user_meta( $doctor->ID, 'doctor_ak_specializations', true );
		$all_specializations   = Specializations::get_all();

		$specialization_labels = array_map(
			function ( $slug ) use ( $all_specializations ) {
				return isset( $all_specializations[ $slug ] ) ? $all_specializations[ $slug ] : $slug;
			},
			$specialization_slugs
		);

		$clinics = Clinics::get_for_doctor( $doctor->ID );

		$is_available = (bool) array_filter(
			$clinics,
			function ( $clinic ) {
				return ! empty( $clinic['enabled_days'] );
			}
		);

		$primary_clinic_location = '';
		$extra_clinic_count      = 0;
		$location_labels         = array();
		$clinic_labels           = array();

		foreach ( $clinics as $clinic ) {
			if ( Clinics::TYPE_PHYSICAL !== $clinic['type'] ) {
				continue;
			}

			if ( '' === $primary_clinic_location ) {
				$primary_clinic_location = '' !== $clinic['address'] ? $clinic['address'] : $clinic['name'];
			} else {
				++$extra_clinic_count;
			}

			if ( '' !== $clinic['address'] ) {
				$location_labels[] = $clinic['address'];
			}

			if ( '' !== $clinic['name'] ) {
				$clinic_labels[] = $clinic['name'];
			}
		}

		$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
		$display_name = '' !== $display_name ? $display_name : $doctor->display_name;

		return array(
			'id'                    => $doctor->ID,
			'name'                  => $display_name,
			'avatar_url'            => self::avatar_url( $doctor->ID ),
			'specialization_slugs'  => $specialization_slugs,
			'specialization_labels' => $specialization_labels,
			'clinic_location'       => $primary_clinic_location,
			'extra_clinic_count'    => $extra_clinic_count,
			'location_labels'       => $location_labels,
			'clinic_labels'         => $clinic_labels,
			'is_available'          => $is_available,
			'video_consultation'    => Clinics::doctor_has_active_video_clinic( $doctor->ID ),
			'profile_url'           => add_query_arg( 'doctor_id', $doctor->ID, Page_Finder::url_for_shortcode( 'doctor_profile_view' ) ),
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
	 * directory shortcode.
	 *
	 * @return bool
	 */
	private function is_directory_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
