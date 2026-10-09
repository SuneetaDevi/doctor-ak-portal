<?php
/**
 * Backs the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Services;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Template_Loader;
use DoctorAKPortal\Includes\Video_Pricing;

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
			'doctor-ak-portal-directories',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directories.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-directories.css' )
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
		$cards = $this->doctor_cards_data();

		if ( ! empty( $cards ) ) {
			$cards = $this->with_directory_details( $cards );
		}

		// Most experienced first (the Sort control's default), so the very
		// first paint already matches it: years descending, doctors with no
		// experience on file after everyone who has it, then by name.
		usort( $cards, array( __CLASS__, 'compare_by_experience' ) );

		$specialities = array();
		$cities       = array();
		$facets       = array(
			'clinic' => 0,
			'video'  => 0,
			'today'  => 0,
			'week'   => 0,
			'male'   => 0,
			'female' => 0,
		);

		foreach ( $cards as $card ) {
			// A doctor with several specialities counts once under each —
			// the filter shows every doctor who has the chosen one.
			foreach ( $card['specialization_labels'] as $label ) {
				$key = mb_strtolower( $label );

				if ( ! isset( $specialities[ $key ] ) ) {
					$specialities[ $key ] = array(
						'slug'  => $key,
						'label' => $label,
						'count' => 0,
					);
				}

				++$specialities[ $key ]['count'];
			}

			foreach ( $card['city_options'] as $slug => $label ) {
				if ( ! isset( $cities[ $slug ] ) ) {
					$cities[ $slug ] = array(
						'slug'  => $slug,
						'label' => $label,
						'count' => 0,
					);
				}

				++$cities[ $slug ]['count'];
			}

			$facets['clinic'] += $card['offers_clinic'] ? 1 : 0;
			$facets['video']  += $card['video_consultation'] ? 1 : 0;
			$facets['today']  += 'today' === $card['availability'] ? 1 : 0;
			$facets['week']   += '' !== $card['availability'] ? 1 : 0;
			$facets['male']   += 'male' === $card['gender'] ? 1 : 0;
			$facets['female'] += 'female' === $card['gender'] ? 1 : 0;
		}

		uasort(
			$specialities,
			function ( $a, $b ) {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		uasort(
			$cities,
			function ( $a, $b ) {
				return $b['count'] - $a['count'] ?: strcasecmp( $a['label'], $b['label'] );
			}
		);

		$doctors_html = array_map(
			function ( $card ) {
				return $this->template_loader->get_template( 'directory/doctor-directory-card.php', $card );
			},
			$cards
		);

		return $this->template_loader->get_template(
			'directory/doctors-directory.php',
			array(
				'doctors_html'  => $doctors_html,
				'specialities'  => array_values( $specialities ),
				'cities'        => array_values( $cities ),
				'facets'        => $facets,
				'doctors_count' => count( $cards ),
			)
		);
	}

	/**
	 * Sort order for the directory: most years of experience first, doctors
	 * with no experience on file last, then alphabetical — the same order
	 * doctor-ak-directory.js applies for "Most experienced".
	 *
	 * @param array $a Card.
	 * @param array $b Card.
	 * @return int
	 */
	public static function compare_by_experience( $a, $b ) {
		$a_years = isset( $a['experience_years'] ) ? $a['experience_years'] : null;
		$b_years = isset( $b['experience_years'] ) ? $b['experience_years'] : null;

		if ( $a_years !== $b_years ) {
			if ( null === $a_years ) {
				return 1;
			}

			if ( null === $b_years ) {
				return -1;
			}

			return $b_years - $a_years;
		}

		return strcasecmp( $a['name'], $b['name'] ) ?: ( $a['id'] - $b['id'] );
	}

	/**
	 * "Dr. Name" for display, without doubling a title the stored name
	 * already starts with ("Dr. Dr. …"). The stored name is never changed.
	 *
	 * @param string $name Doctor's stored display name.
	 * @return string
	 */
	public static function name_with_title( $name ) {
		$name = trim( (string) $name );

		if ( preg_match( '/^(dr|doctor|prof|professor)\b\.?\s*/i', $name ) ) {
			return $name;
		}

		/* translators: %s: doctor's name. */
		return sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $name );
	}

	/**
	 * Adds what only the directory page shows to each card — loaded in bulk
	 * (one clinics query, one services query, one appointments query)
	 * rather than per doctor: every physical location, the visit types the
	 * doctor really offers, fees from their configured services / video
	 * pricing, and the next open slot in the coming week from their session
	 * hours minus paid bookings (the same rule the booking page applies).
	 *
	 * @param array $cards doctor_cards_data() rows.
	 * @return array
	 */
	private function with_directory_details( array $cards ) {
		$doctor_ids         = wp_list_pluck( $cards, 'id' );
		$clinics_by_doctor  = Clinics::get_for_doctors( $doctor_ids );
		$services_by_doctor = array();

		foreach ( Services::active_for_public_directory() as $service ) {
			$services_by_doctor[ $service['doctor_id'] ][] = $service;
		}

		$today       = current_time( 'Y-m-d' );
		$booked      = self::booked_slots( $today, gmdate( 'Y-m-d', strtotime( $today . ' +6 days' ) ) );
		$booking_url = Page_Finder::url_for_shortcode( 'book_appointment' );

		foreach ( $cards as &$card ) {
			$doctor_id = $card['id'];
			$clinics   = isset( $clinics_by_doctor[ $doctor_id ] ) ? $clinics_by_doctor[ $doctor_id ] : array();
			$physical  = array_values(
				array_filter(
					$clinics,
					function ( $clinic ) {
						return Clinics::TYPE_PHYSICAL === $clinic['type'];
					}
				)
			);

			$locations    = array();
			$city_options = array();

			foreach ( $physical as $clinic ) {
				$locations[] = array(
					'name'  => '' !== $clinic['name'] ? $clinic['name'] : $clinic['address'],
					'place' => implode( ', ', array_filter( array( $clinic['area_label'], $clinic['city_label'] ) ) ),
				);

				if ( '' !== $clinic['city'] ) {
					$city_options[ $clinic['city'] ] = '' !== $clinic['city_label'] ? $clinic['city_label'] : ucwords( str_replace( '-', ' ', $clinic['city'] ) );
				}
			}

			// No clinic city on file: the profile-level city the Location
			// filter already fell back to (card_data()'s city_slugs).
			if ( empty( $city_options ) ) {
				foreach ( $card['city_slugs'] as $slug ) {
					$city_options[ $slug ] = ucwords( str_replace( '-', ' ', $slug ) );
				}
			}

			$offers_clinic = ! empty( $physical );
			$offers_video  = ! empty( $card['video_consultation'] );
			$services      = isset( $services_by_doctor[ $doctor_id ] ) ? $services_by_doctor[ $doctor_id ] : array();
			$doctor_booked = isset( $booked[ $doctor_id ] ) ? $booked[ $doctor_id ] : array();
			$next          = null;

			foreach ( array( 'clinic' => $offers_clinic, 'video' => $offers_video ) as $type => $offered ) {
				if ( ! $offered ) {
					continue;
				}

				$slot = self::next_open_slot( $clinics, $type, $doctor_booked, $today );

				if ( $slot && ( null === $next || $slot['date'] . $slot['time'] < $next['date'] . $next['time'] ) ) {
					$next = $slot;
				}
			}

			$video_pricing = $offers_video ? Video_Pricing::effective_price_for_doctor( $doctor_id ) : null;

			$card['display_name']       = self::name_with_title( $card['name'] );
			$card['experience_years']   = is_numeric( $card['years_experience'] ) && (int) $card['years_experience'] > 0 ? (int) $card['years_experience'] : null;
			$card['locations']          = $locations;
			$card['city_options']       = $city_options;
			$card['offers_clinic']      = $offers_clinic;
			$card['clinic_fee']         = $offers_clinic ? self::clinic_fee( $services, $physical ) : null;
			$card['video_fee']          = $video_pricing ? Services::public_price( array( (float) $video_pricing['final_price'] ), __( 'View fees', 'doctor-ak-portal' ) ) : null;
			$card['next_available']     = $next ? self::slot_label( $next, $today ) : '';
			$card['availability']       = $next ? ( $today === $next['date'] ? 'today' : 'week' ) : '';
			$card['next_at']            = $next ? $next['date'] . ' ' . $next['time'] : '';
			$card['clinic_booking_url'] = $booking_url && $offers_clinic ? add_query_arg( array( 'doctor_id' => $doctor_id, 'type' => 'clinic' ), $booking_url ) : '';
			$card['video_booking_url']  = $booking_url && $offers_video ? add_query_arg( array( 'doctor_id' => $doctor_id, 'type' => 'video' ), $booking_url ) : '';
		}
		unset( $card );

		return $cards;
	}

	/**
	 * A clinic visit's fee from the doctor's active clinic services, the
	 * same rule the doctor's profile uses: a service scoped to specific
	 * clinics counts at those of this doctor's clinics it lists (at each
	 * one's own price), an unscoped one at its flat charge.
	 *
	 * @param array $services The doctor's active clinic services.
	 * @param array $physical The doctor's physical clinic rows.
	 * @return array Services::public_price() result.
	 */
	private static function clinic_fee( array $services, array $physical ) {
		$location_ids = array_values( array_filter( array_map( 'intval', wp_list_pluck( $physical, 'clinic_location_id' ) ) ) );
		$charges      = array();

		foreach ( $services as $service ) {
			if ( ! empty( $service['clinic_charges'] ) ) {
				foreach ( $service['clinic_charges'] as $location_id => $charge ) {
					if ( in_array( (int) $location_id, $location_ids, true ) ) {
						$charges[] = (float) $charge;
					}
				}

				continue;
			}

			$charges[] = (float) $service['charge'];
		}

		return Services::public_price( $charges, __( 'View fees', 'doctor-ak-portal' ) );
	}

	/**
	 * Taken appointment times per doctor and date in a date range — the
	 * same "one doctor, one appointment per slot" rule the booking page
	 * applies (Appointments::occupied_slots()). One query for every doctor.
	 *
	 * @param string $from 'Y-m-d'.
	 * @param string $to   'Y-m-d'.
	 * @return array doctor_id => date => time => appointment ID
	 */
	private static function booked_slots( $from, $to ) {
		return Appointments::occupied_slots( array(), $from, $to );
	}

	/**
	 * The first open slot for one visit type in the 7 days from today:
	 * the doctor's session grid for that day, minus paid bookings and,
	 * today, times already past.
	 *
	 * @param array  $clinics The doctor's clinic rows.
	 * @param string $type    'clinic' or 'video'.
	 * @param array  $booked  date => time => true for this doctor.
	 * @param string $today   'Y-m-d' (site time).
	 * @return array|null { date, time, type }
	 */
	private static function next_open_slot( array $clinics, $type, array $booked, $today ) {
		$now = current_time( 'H:i' );

		for ( $offset = 0; $offset < 7; $offset++ ) {
			$date = gmdate( 'Y-m-d', strtotime( $today . ' +' . $offset . ' days' ) );

			foreach ( Clinics::slot_grid_from_clinics( $clinics, $type, $date ) as $time ) {
				if ( isset( $booked[ $date ][ $time ] ) || ( $date === $today && $time <= $now ) ) {
					continue;
				}

				return array(
					'date' => $date,
					'time' => $time,
					'type' => $type,
				);
			}
		}

		return null;
	}

	/**
	 * "Today, 4:30 pm · Clinic" / "Tomorrow, …" / "Thu 9 Oct, …".
	 *
	 * @param array  $slot  next_open_slot() result.
	 * @param string $today 'Y-m-d'.
	 * @return string
	 */
	private static function slot_label( array $slot, $today ) {
		$timestamp = strtotime( $slot['date'] . ' ' . $slot['time'] );
		$time      = date_i18n( get_option( 'time_format', 'g:i a' ), $timestamp );

		if ( $slot['date'] === $today ) {
			$day = __( 'Today', 'doctor-ak-portal' );
		} elseif ( gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) ) === $slot['date'] ) {
			$day = __( 'Tomorrow', 'doctor-ak-portal' );
		} else {
			$day = date_i18n( 'D j M', $timestamp );
		}

		$mode = 'video' === $slot['type'] ? __( 'Video', 'doctor-ak-portal' ) : __( 'Clinic', 'doctor-ak-portal' );

		/* translators: 1: day ("Today", "Tomorrow", "Thu 9 Oct"), 2: time, 3: "Clinic" or "Video". */
		return sprintf( __( '%1$s, %2$s · %3$s', 'doctor-ak-portal' ), $day, $time, $mode );
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
	 * The same directory-card view-model as doctor_cards_data(), but for a
	 * specific, already-known set of doctor IDs instead of every doctor —
	 * used by Clinic_Profile_View to list the doctors practicing at one
	 * clinic (see Clinics::get_by_clinic_location()) without the N+1-query
	 * cost of fetching every doctor site-wide just to filter them down
	 * afterward.
	 *
	 * @param int[] $doctor_ids Doctor user IDs.
	 * @return array Same shape as doctor_cards_data(), ordered by display name — doctors not found, not the Doctor role, or deactivated are silently dropped.
	 */
	public function doctor_cards_data_for_ids( array $doctor_ids ) {
		$doctor_ids = array_values( array_unique( array_filter( array_map( 'absint', $doctor_ids ) ) ) );

		if ( empty( $doctor_ids ) ) {
			return array();
		}

		$query = new \WP_User_Query(
			array(
				'include'    => $doctor_ids,
				'role'       => Roles::DOCTOR_ROLE,
				'orderby'    => 'display_name',
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

		// Only real specializations — a stray free-typed value in a
		// doctor's meta (e.g. a procedure/condition) isn't one, and would
		// otherwise show up as its own filter chip.
		$specialization_labels = array_values(
			array_filter(
				array_map(
					function ( $slug ) use ( $all_specializations ) {
						return isset( $all_specializations[ $slug ] ) ? $all_specializations[ $slug ] : '';
					},
					$specialization_slugs
				)
			)
		);

		$clinics = Clinics::get_for_doctor( $doctor->ID );

		$is_available = (bool) array_filter(
			$clinics,
			function ( $clinic ) {
				return ! empty( $clinic['enabled_days'] );
			}
		);

		$primary_clinic_location      = '';
		$primary_clinic_city_label    = '';
		$primary_clinic_country_label = '';
		$extra_clinic_count           = 0;
		$clinic_labels                = array();
		$clinic_areas                 = array();
		$country_slugs                = array();
		$city_slugs                   = array();
		$area_slugs                   = array();

		foreach ( $clinics as $clinic ) {
			if ( Clinics::TYPE_PHYSICAL !== $clinic['type'] ) {
				continue;
			}

			if ( '' === $primary_clinic_location ) {
				$primary_clinic_location      = '' !== $clinic['name'] ? $clinic['name'] : $clinic['address'];
				$primary_clinic_city_label    = $clinic['city_label'];
				$primary_clinic_country_label = $clinic['country_label'];
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
			'gender'                => (string) get_user_meta( $doctor->ID, 'doctor_ak_gender', true ),
			'clinic_location'       => $primary_clinic_location,
			'clinic_city_label'     => $primary_clinic_city_label,
			'clinic_country_label'  => $primary_clinic_country_label,
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
