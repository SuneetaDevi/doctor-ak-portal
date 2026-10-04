<?php
/**
 * Read-only clinic data shared by the public pages — the home page's clinic
 * preview, the [clinics_directory] finder and each [clinic_profile_view]
 * page — so all three count clinics, cities and doctors the same way.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinic_Public_Data
 *
 * Every published clinic is one Clinic_Locations row. A doctor practises at
 * it through their own Clinics row (type physical, clinic_location_id set) —
 * a different id space: booking takes the doctor's Clinics row id, while the
 * public clinic pages take the Clinic_Locations id. Only active (not
 * deactivated) doctors count, matching who the clinic page actually lists.
 */
class Clinic_Public_Data {

	/**
	 * A phone number shared by at least this many clinic locations is the
	 * central booking line, not a clinic's own number.
	 *
	 * @var int
	 */
	const SHARED_PHONE_THRESHOLD = 3;

	/**
	 * Per-request cache of locations().
	 *
	 * @var array|null
	 */
	private static $locations = null;

	/**
	 * Every published clinic, alphabetical, each with its active doctors.
	 *
	 * @return array List of Clinic_Locations rows plus:
	 *     @type array  $doctor_rows   The active doctors' Clinics rows at this location, one per doctor.
	 *     @type int    $doctor_count  Number of active doctors listed here.
	 *     @type string $address_short Address without a leading repeat of the clinic name.
	 *     @type string $place         "Area, City" (or whichever of the two is set).
	 *     @type string $phone_kind    'central', 'clinic', or '' when there's no phone.
	 *     @type string $phone_href    tel: link target, or ''.
	 *     @type string $profile_url   This clinic's [clinic_profile_view] URL, or ''.
	 */
	public static function locations() {
		if ( null !== self::$locations ) {
			return self::$locations;
		}

		$active      = array_flip( Appointments::active_doctor_ids() );
		$by_location = array();

		foreach ( Clinics::get_for_doctors( array_keys( $active ) ) as $doctor_clinics ) {
			foreach ( $doctor_clinics as $clinic ) {
				$location_id = (int) $clinic['clinic_location_id'];

				if ( Clinics::TYPE_PHYSICAL !== $clinic['type'] || $location_id < 1 ) {
					continue;
				}

				// One entry per doctor, even if a doctor somehow has two rows
				// aligned to the same location — keep their first.
				if ( ! isset( $by_location[ $location_id ][ $clinic['doctor_id'] ] ) ) {
					$by_location[ $location_id ][ $clinic['doctor_id'] ] = $clinic;
				}
			}
		}

		$all         = Clinic_Locations::get_all();
		$profile_url = Page_Finder::url_for_shortcode( Clinic_Profile_View::SHORTCODE_TAG );
		$central     = self::central_phone_digits( $all );
		$locations   = array();

		foreach ( $all as $location ) {
			$doctor_rows = isset( $by_location[ $location['id'] ] ) ? array_values( $by_location[ $location['id'] ] ) : array();
			$digits      = self::digits( $location['phone'] );

			$location['doctor_rows']   = $doctor_rows;
			$location['doctor_count']  = count( $doctor_rows );
			$location['address_short'] = self::address_without_name( $location['address'], $location['name'] );
			$location['place']         = implode( ', ', array_filter( array( $location['area_label'], $location['city_label'] ) ) );
			$location['phone_kind']    = '' === $digits ? '' : ( isset( $central[ $digits ] ) ? 'central' : 'clinic' );
			$location['phone_href']    = '' === $digits ? '' : 'tel:' . preg_replace( '/[^0-9+]/', '', $location['phone'] );
			$location['profile_url']   = $profile_url ? add_query_arg( 'clinic_id', $location['id'], $profile_url ) : '';

			$locations[] = $location;
		}

		self::$locations = $locations;

		return $locations;
	}

	/**
	 * One published clinic by its Clinic_Locations id, or null.
	 *
	 * @param int $location_id Clinic_Locations row id.
	 * @return array|null Same shape as one locations() row.
	 */
	public static function find( $location_id ) {
		foreach ( self::locations() as $location ) {
			if ( (int) $location['id'] === (int) $location_id ) {
				return $location;
			}
		}

		return null;
	}

	/**
	 * Cities with at least one published clinic, most clinics first, each
	 * with the areas that actually have one.
	 *
	 * @return array List of { slug, label, count, areas: [ { slug, label, count } ] }.
	 */
	public static function cities() {
		$cities = array();

		foreach ( self::locations() as $location ) {
			if ( '' === $location['city'] ) {
				continue;
			}

			$city = $location['city'];

			if ( ! isset( $cities[ $city ] ) ) {
				$cities[ $city ] = array(
					'slug'  => $city,
					'label' => '' !== $location['city_label'] ? $location['city_label'] : $city,
					'count' => 0,
					'areas' => array(),
				);
			}

			++$cities[ $city ]['count'];

			if ( '' !== $location['area'] ) {
				$area = $location['area'];

				if ( ! isset( $cities[ $city ]['areas'][ $area ] ) ) {
					$cities[ $city ]['areas'][ $area ] = array(
						'slug'  => $area,
						'label' => '' !== $location['area_label'] ? $location['area_label'] : $area,
						'count' => 0,
					);
				}

				++$cities[ $city ]['areas'][ $area ]['count'];
			}
		}

		foreach ( $cities as &$city ) {
			$areas = array_values( $city['areas'] );

			usort(
				$areas,
				function ( $a, $b ) {
					return strcasecmp( $a['label'], $b['label'] );
				}
			);

			$city['areas'] = $areas;
		}
		unset( $city );

		$cities = array_values( $cities );

		usort(
			$cities,
			function ( $a, $b ) {
				return $b['count'] - $a['count'] ?: strcasecmp( $a['label'], $b['label'] );
			}
		);

		return $cities;
	}

	/**
	 * "Karachi, Hyderabad and Quetta" — every city with a published clinic,
	 * most clinics first, for headings and summaries.
	 *
	 * @return string '' when no clinic has a city set.
	 */
	public static function city_list_label() {
		$labels = wp_list_pluck( self::cities(), 'label' );

		if ( count( $labels ) < 2 ) {
			return implode( '', $labels );
		}

		$last = array_pop( $labels );

		/* translators: 1: comma-separated city names, 2: the last city name. */
		return sprintf( __( '%1$s and %2$s', 'doctor-ak-portal' ), implode( ', ', $labels ), $last );
	}

	/**
	 * Days and hours a doctor sees patients at one clinic, from that
	 * doctor's own Clinics row — days sharing the same hours are grouped,
	 * e.g. "Mon, Wed · 5:00 pm – 9:00 pm".
	 *
	 * @param array $clinic_row One Clinics::decode_row() row.
	 * @return string[] One line per group, empty when no day is enabled.
	 */
	public static function schedule_lines( array $clinic_row ) {
		$short_days = array(
			'monday'    => __( 'Mon', 'doctor-ak-portal' ),
			'tuesday'   => __( 'Tue', 'doctor-ak-portal' ),
			'wednesday' => __( 'Wed', 'doctor-ak-portal' ),
			'thursday'  => __( 'Thu', 'doctor-ak-portal' ),
			'friday'    => __( 'Fri', 'doctor-ak-portal' ),
			'saturday'  => __( 'Sat', 'doctor-ak-portal' ),
			'sunday'    => __( 'Sun', 'doctor-ak-portal' ),
		);
		$groups     = array();

		foreach ( Clinics::session_days() as $day_slug => $day_label ) {
			if ( empty( $clinic_row['sessions'][ $day_slug ] ) ) {
				continue;
			}

			$ranges = array();

			foreach ( $clinic_row['sessions'][ $day_slug ] as $period ) {
				if ( ! empty( $period['enabled'] ) && '' !== $period['start'] && '' !== $period['end'] ) {
					$ranges[] = self::time_label( $period['start'] ) . ' – ' . self::time_label( $period['end'] );
				}
			}

			if ( empty( $ranges ) ) {
				continue;
			}

			$key = implode( ', ', $ranges );

			$groups[ $key ][] = isset( $short_days[ $day_slug ] ) ? $short_days[ $day_slug ] : $day_label;
		}

		$lines = array();

		foreach ( $groups as $ranges => $days ) {
			$lines[] = implode( ', ', $days ) . ' · ' . $ranges;
		}

		return $lines;
	}

	/**
	 * Lowest fee among a doctor's active clinic services that are offered at
	 * one clinic location — the location's own price where one is set,
	 * otherwise the service's standard price (a service with a non-empty
	 * clinic_charges map is offered only at the locations it lists, the same
	 * rule Appointments::resolve_services() applies when booking).
	 *
	 * @param int $doctor_id   Doctor's user ID.
	 * @param int $location_id Clinic_Locations row id.
	 * @return float 0 when no priced service is offered there.
	 */
	public static function lowest_fee_at( $doctor_id, $location_id ) {
		$lowest = 0.0;

		foreach ( Services::active_for_doctor( $doctor_id, 'clinic' ) as $service ) {
			$charges = isset( $service['clinic_charges'] ) && is_array( $service['clinic_charges'] ) ? $service['clinic_charges'] : array();

			if ( ! empty( $charges ) && ! array_key_exists( $location_id, $charges ) ) {
				continue;
			}

			$fee = ! empty( $charges ) && '' !== (string) $charges[ $location_id ] ? (float) $charges[ $location_id ] : (float) $service['charge'];

			if ( $fee > 0 && ( 0.0 === $lowest || $fee < $lowest ) ) {
				$lowest = $fee;
			}
		}

		return $lowest;
	}

	/**
	 * An address with a leading repeat of the clinic's own name removed
	 * (addresses are often entered starting with the name). Display only —
	 * the stored address is never changed.
	 *
	 * @param string $address Stored address.
	 * @param string $name    Clinic name.
	 * @return string
	 */
	public static function address_without_name( $address, $name ) {
		$address = trim( (string) $address );
		$name    = trim( (string) $name );

		if ( '' !== $name && 0 === stripos( $address, $name ) ) {
			$address = ltrim( substr( $address, strlen( $name ) ), " \t,-–—" );
		}

		return $address;
	}

	/**
	 * The central booking line to offer patients — the number shared by the
	 * most clinic locations (the one patients already see on most clinics),
	 * or the footer's contact phone when no number is shared that widely.
	 *
	 * @return array|null { display, href }, or null when there is none.
	 */
	public static function booking_line() {
		$counts = array();

		foreach ( self::locations() as $location ) {
			$digits = self::digits( $location['phone'] );

			if ( '' !== $digits ) {
				$counts[ $digits ] = isset( $counts[ $digits ] ) ? $counts[ $digits ] + 1 : 1;
			}
		}

		arsort( $counts );

		$digits = '';

		if ( ! empty( $counts ) && reset( $counts ) >= self::SHARED_PHONE_THRESHOLD ) {
			$digits = (string) key( $counts );
		} else {
			$digits = self::digits( get_option( Site_Footer::OPTION_PHONE, '0303-3638304' ) );
		}

		if ( '' === $digits ) {
			return null;
		}

		return array(
			'display' => self::format_phone( $digits ),
			'href'    => 'tel:' . $digits,
		);
	}

	/**
	 * Groups a phone number's digits for reading — "03343646307" becomes
	 * "0334 3646307". Display only; anything that isn't a plain 11-digit
	 * local mobile number is shown as entered.
	 *
	 * @param string $phone Phone as entered.
	 * @return string
	 */
	public static function format_phone( $phone ) {
		$digits = self::digits( $phone );

		if ( 11 === strlen( $digits ) && 0 === strpos( $digits, '03' ) ) {
			return substr( $digits, 0, 4 ) . ' ' . substr( $digits, 4 );
		}

		return trim( (string) $phone );
	}

	/**
	 * Digits of the site's central booking numbers: the footer's contact
	 * phone setting, the header's phone, and any number shared by several
	 * clinic locations at once.
	 *
	 * @param array $all_locations Clinic_Locations::get_all() rows.
	 * @return array digits => true.
	 */
	private static function central_phone_digits( array $all_locations ) {
		$central = array();
		$counts  = array();

		foreach ( $all_locations as $location ) {
			$digits = self::digits( $location['phone'] );

			if ( '' !== $digits ) {
				$counts[ $digits ] = isset( $counts[ $digits ] ) ? $counts[ $digits ] + 1 : 1;
			}
		}

		foreach ( $counts as $digits => $count ) {
			if ( $count >= self::SHARED_PHONE_THRESHOLD ) {
				$central[ $digits ] = true;
			}
		}

		// Same default the footer itself falls back to (Site_Footer::prepare_data()).
		$footer_digits = self::digits( get_option( Site_Footer::OPTION_PHONE, '0303-3638304' ) );

		if ( '' !== $footer_digits ) {
			$central[ $footer_digits ] = true;
		}

		return $central;
	}

	/**
	 * A phone number's digits in local form (a leading 92 country code
	 * becomes 0), so "0334 3646307" and "+92 334 3646307" compare equal.
	 *
	 * @param string $phone Phone as entered.
	 * @return string
	 */
	private static function digits( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );

		if ( 0 === strpos( $digits, '92' ) && strlen( $digits ) > 10 ) {
			$digits = '0' . substr( $digits, 2 );
		}

		return $digits;
	}

	/**
	 * "17:00" → "5:00 pm" in the site's time format.
	 *
	 * @param string $time 'HH:MM'.
	 * @return string
	 */
	private static function time_label( $time ) {
		$timestamp = strtotime( '2000-01-01 ' . $time );

		return $timestamp ? date_i18n( get_option( 'time_format', 'g:i a' ), $timestamp ) : $time;
	}
}
