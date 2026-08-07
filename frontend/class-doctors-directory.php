<?php
/**
 * Backs the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Locations;
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
			'doctor-ak-portal-city-area-select',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-city-area-select.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-city-area-select.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-directory.js',
			array( 'doctor-ak-portal-city-area-select' ),
			Assets::version( 'assets/js/doctor-ak-directory.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-directory',
			'dakDirectory',
			array(
				'locations' => Locations::get_all(),
			)
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$cards                = $this->doctor_cards_data();
		$doctors_html         = array();
		$used_specializations = array();
		$all_specializations  = Specializations::get_all();
		$used_clinics         = array();

		foreach ( $cards as $card ) {
			$doctors_html[] = $this->template_loader->get_template( 'directory/doctor-card.php', $card );

			foreach ( $card['specialization_slugs'] as $slug ) {
				$used_specializations[ $slug ] = isset( $all_specializations[ $slug ] ) ? $all_specializations[ $slug ] : $slug;
			}

			foreach ( $card['clinic_areas'] as $label => $area ) {
				if ( ! isset( $used_clinics[ $label ] ) || '' === $used_clinics[ $label ] ) {
					$used_clinics[ $label ] = $area;
				}
			}
		}

		asort( $used_specializations );
		ksort( $used_clinics );

		// Country/City/Area are rendered as empty <select>s and populated/
		// cascaded client-side from the full admin-managed Locations list
		// (see enqueue_assets()'s 'locations' localization and
		// assets/js/doctor-ak-directory.js), the same pattern every other
		// location picker in the plugin uses — rather than pre-filtering to
		// only locations a listed doctor happens to have.
		return $this->template_loader->get_template(
			'directory/doctors-directory.php',
			array(
				'doctors_html'    => $doctors_html,
				'specializations' => $used_specializations,
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
				'role'       => Roles::DOCTOR_ROLE,
				'orderby'    => 'display_name',
				'number'     => $limit > 0 ? $limit : 0,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no better lookup available; excludes deactivated doctors from every public listing.
					'relation' => 'OR',
					array(
						'key'     => 'doctor_ak_account_disabled',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'doctor_ak_account_disabled',
						'value'   => 'yes',
						'compare' => '!=',
					),
				),
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
		$clinic_labels           = array();
		$clinic_areas            = array();
		$country_slugs           = array();
		$city_slugs              = array();
		$area_slugs              = array();

		foreach ( $clinics as $clinic ) {
			if ( Clinics::TYPE_PHYSICAL !== $clinic['type'] ) {
				continue;
			}

			if ( '' === $primary_clinic_location ) {
				$primary_clinic_location = '' !== $clinic['address'] ? $clinic['address'] : $clinic['name'];
			} else {
				++$extra_clinic_count;
			}

			if ( '' !== $clinic['name'] ) {
				$clinic_labels[] = $clinic['name'];

				// Directory filter bar: lets the Clinic <select> narrow down
				// to only clinics in the selected Area (see doctors-directory.php
				// / doctor-ak-directory.js). Keeps the first area seen for a
				// given clinic name if it somehow appears more than once.
				if ( ! isset( $clinic_areas[ $clinic['name'] ] ) ) {
					$clinic_areas[ $clinic['name'] ] = $clinic['area'];
				}
			}

			if ( '' !== $clinic['country'] ) {
				$country_slugs[] = $clinic['country'];
			}

			if ( '' !== $clinic['city'] ) {
				$city_slugs[] = $clinic['city'];
			}

			if ( '' !== $clinic['area'] ) {
				$area_slugs[] = $clinic['area'];
			}
		}

		// No physical clinic has a country/city/area set yet (or no
		// physical clinic at all) — fall back to the doctor's own
		// profile-level location so they still show up under the
		// directory's location filter.
		if ( empty( $country_slugs ) ) {
			$profile_country = get_user_meta( $doctor->ID, 'doctor_ak_country', true );
			$profile_city    = get_user_meta( $doctor->ID, 'doctor_ak_city', true );
			$profile_area    = get_user_meta( $doctor->ID, 'doctor_ak_area', true );

			if ( '' !== $profile_country ) {
				$country_slugs[] = $profile_country;
			}

			if ( '' !== $profile_city ) {
				$city_slugs[] = $profile_city;
			}

			if ( '' !== $profile_area ) {
				$area_slugs[] = $profile_area;
			}
		}

		$country_slugs = array_values( array_unique( $country_slugs ) );
		$city_slugs    = array_values( array_unique( $city_slugs ) );
		$area_slugs    = array_values( array_unique( $area_slugs ) );

		$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
		$display_name = '' !== $display_name ? $display_name : $doctor->display_name;

		return array(
			'id'                    => $doctor->ID,
			'name'                  => $display_name,
			'avatar_url'            => self::avatar_url( $doctor->ID ),
			'specialization_slugs'  => $specialization_slugs,
			'specialization_labels' => $specialization_labels,
			'years_experience'      => get_user_meta( $doctor->ID, 'doctor_ak_years_experience', true ),
			'clinic_location'       => $primary_clinic_location,
			'extra_clinic_count'    => $extra_clinic_count,
			'country_slugs'         => $country_slugs,
			'city_slugs'            => $city_slugs,
			'area_slugs'            => $area_slugs,
			'clinic_labels'         => $clinic_labels,
			'clinic_areas'          => $clinic_areas,
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
