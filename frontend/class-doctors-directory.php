<?php
/**
 * Backs the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
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

		// Quick-filter chips (discount / fee) and the Experience filter —
		// the thresholds the template offers, each with how many doctors
		// it would show.
		$discount_slabs   = array( 'any' => 0, '30' => 0, '50' => 0 );
		$fee_slabs        = array( '500' => 0, '1000' => 0, '1500' => 0 );
		$experience_slabs = array( '5' => 0, '10' => 0, '15' => 0, '20' => 0 );

		foreach ( $cards as $card ) {
			$discount = isset( $card['best_discount_percent'] ) ? (int) $card['best_discount_percent'] : 0;

			foreach ( $discount_slabs as $slab => $count ) {
				$discount_slabs[ $slab ] += ( 'any' === $slab ? $discount > 0 : $discount >= (int) $slab ) ? 1 : 0;
			}

			foreach ( $fee_slabs as $slab => $count ) {
				$fee_slabs[ $slab ] += ( null !== $card['lowest_fee'] && $card['lowest_fee'] <= (int) $slab ) ? 1 : 0;
			}

			foreach ( $experience_slabs as $slab => $count ) {
				$experience_slabs[ $slab ] += ( null !== $card['experience_years'] && $card['experience_years'] >= (int) $slab ) ? 1 : 0;
			}
		}

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

		// Step one of the page: a bare visit ("View all doctors" in the header,
		// the home page, a profile's breadcrumb) opens the specialty picker;
		// picking one opens the doctor list below already filtered to it.
		// Any filter in the URL (a header search, ?specialization=, ?city=…)
		// or ?view=all ("Show all doctors") goes straight to the list.
		$page_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$page_url = '' !== $page_url ? $page_url : (string) get_permalink();

		foreach ( $specialities as &$speciality ) {
			$speciality['url']      = add_query_arg( 'specialization', rawurlencode( $speciality['slug'] ), $page_url );
			$speciality['icon']     = self::specialty_icon( $speciality['slug'] );
			$speciality['keywords'] = self::specialty_keywords( $speciality['slug'] );
		}
		unset( $speciality );

		if ( ! empty( $specialities ) && ! self::has_list_request() ) {
			return $this->template_loader->get_template(
				'directory/doctors-specialty-picker.php',
				array(
					'specialities'    => array_values( $specialities ),
					'doctors_count'   => count( $cards ),
					'page_url'        => $page_url,
					'all_doctors_url' => add_query_arg( 'view', 'all', $page_url ),
				)
			);
		}

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
				'discount_slabs'   => $discount_slabs,
				'fee_slabs'        => $fee_slabs,
				'experience_slabs' => $experience_slabs,
				'doctors_count' => count( $cards ),
				'specialties_url' => ! empty( $specialities ) ? $page_url : '',
			)
		);
	}

	/**
	 * Whether the URL asks for the doctor list itself rather than the
	 * specialty picker: ?view=all, or any filter doctor-ak-directory.js
	 * reads (it keeps ?view=all in the URL whenever no filter is set, so
	 * Back from a profile returns to the list, not the picker).
	 *
	 * @return bool
	 */
	private static function has_list_request() {
		$keys = array( 'view', 'q', 's', 'specialization', 'visit', 'availability', 'experience', 'gender', 'city', 'discount', 'fee', 'sort', 'page' );

		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display choice.
			if ( isset( $_GET[ $key ] ) && '' !== trim( (string) wp_unslash( $_GET[ $key ] ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Plain-English words a patient might type for a specialty ("heart" for
	 * Cardiologist), matched by the picker's search alongside the label.
	 *
	 * @param string $slug Lowercase specialty label.
	 * @return string Space-separated keywords, or ''.
	 */
	private static function specialty_keywords( $slug ) {
		$words = array(
			'cardio'    => 'heart chest pain blood pressure',
			'neuro'     => 'brain nerves headache migraine seizure stroke',
			'psych'     => 'mind mental health anxiety depression stress sleep',
			'gastro'    => 'stomach liver digestion acidity abdomen',
			'\bdent'    => 'teeth tooth gums mouth',
			'ophthal'   => 'eye eyes vision sight',
			'orthop'    => 'bones joints back knee fracture',
			'rheumat'   => 'joints arthritis',
			'pulmon'    => 'lungs chest breathing asthma cough',
			'pediatric' => 'child children baby kids',
			'paediatric' => 'child children baby kids',
			'gyneco'    => 'women pregnancy',
			'obstetric' => 'women pregnancy',
			'dermat'    => 'skin hair acne allergy',
			'nephro'    => 'kidney kidneys',
			'\burolog'  => 'kidney urine bladder',
			'\bent\b'   => 'ear nose throat',
			'otolaryng' => 'ear nose throat',
			'endocrin'  => 'diabetes thyroid hormones',
			'diabet'    => 'diabetes sugar',
			'general p' => 'fever flu checkup family',
			'family'    => 'fever flu checkup',
		);
		$found = array();

		// Patterns, not plain substrings, so "ENT" matches the word and not
		// the "ent" inside "dentist", and "urolog" isn't found in "neurologist".
		foreach ( $words as $needle => $keywords ) {
			if ( preg_match( '/' . $needle . '/u', $slug ) ) {
				$found[] = $keywords;
			}
		}

		return implode( ' ', $found );
	}

	/**
	 * A body-part glyph for a specialty (same set and matching as the home
	 * page's "Consult online" tiles), picked from the label so every
	 * specialization — including custom ones — gets something sensible.
	 *
	 * @param string $slug Lowercase specialty label.
	 * @return string Static SVG markup.
	 */
	private static function specialty_icon( $slug ) {
		$open  = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
		$icons = array(
			'heart'       => '<path d="M10 16.2S3.8 12.4 3.8 8.1A3.1 3.1 0 0 1 10 6.3a3.1 3.1 0 0 1 6.2 1.8c0 4.3-6.2 8.1-6.2 8.1z"/>',
			'brain'       => '<path d="M9.2 4.2a2 2 0 0 0-3.4 1.2 2 2 0 0 0-.9 3.3 2 2 0 0 0 1 3.2 2 2 0 0 0 3.3 1.4z"/><path d="M10.8 4.2a2 2 0 0 1 3.4 1.2 2 2 0 0 1 .9 3.3 2 2 0 0 1-1 3.2 2 2 0 0 1-3.3 1.4z"/><path d="M10 4.2v11.6"/>',
			'stomach'     => '<path d="M7.5 3.8v3.9c0 2.3 1.5 3.3 3.2 3.6 1.9.3 2.9 1.2 2.9 2.6a2.6 2.6 0 0 1-5.2.2"/><path d="M5.6 3.8h3.8"/>',
			'tooth'       => '<path d="M6.4 3.8c1.1 0 1.4.6 3.6.6s2.5-.6 3.6-.6c.9 0 1.4.9 1.4 2.3 0 1.8-.9 2.7-1.3 4.8-.3 1.7-.5 4.2-1.6 4.2-.9 0-.9-2.1-1.2-3.6-.2-.8-.5-1.2-.9-1.2s-.7.4-.9 1.2c-.3 1.5-.3 3.6-1.2 3.6-1.1 0-1.3-2.5-1.6-4.2C5.9 8.8 5 7.9 5 6.1c0-1.4.5-2.3 1.4-2.3z"/>',
			'eye'         => '<path d="M2.5 10S5.6 5.5 10 5.5 17.5 10 17.5 10 14.4 14.5 10 14.5 2.5 10 2.5 10z"/><circle cx="10" cy="10" r="2.2"/>',
			'bone'        => '<path d="M7 13l6-6"/><circle cx="5.6" cy="14.4" r="2.1"/><circle cx="14.4" cy="5.6" r="2.1"/>',
			'lungs'       => '<path d="M10 3.5v6.2"/><path d="M10 9.7c0-1.2-.9-2-2-2-2 0-3.5 2.6-3.5 5.4 0 2 .6 3.4 1.8 3.4 1.4 0 3.7-1.3 3.7-3.2z"/><path d="M10 9.7c0-1.2.9-2 2-2 2 0 3.5 2.6 3.5 5.4 0 2-.6 3.4-1.8 3.4-1.4 0-3.7-1.3-3.7-3.2z"/>',
			'baby'        => '<circle cx="10" cy="6.8" r="3.4"/><path d="M8.7 6.2h.01M11.3 6.2h.01"/><path d="M4.6 16.8c.8-2.7 2.9-4.3 5.4-4.3s4.6 1.6 5.4 4.3"/>',
			'skin'        => '<path d="M10 3.4s4.4 4.6 4.4 7.3a4.4 4.4 0 0 1-8.8 0C5.6 8 10 3.4 10 3.4z"/><path d="M8.4 11.2h.01M10.6 12.8h.01"/>',
			'kidney'      => '<path d="M8.2 4.2c2.2 0 3.8 2.2 3.8 5.6s-1.6 6-3.8 6-3.4-2-3.4-5.7 1.2-5.9 3.4-5.9z"/><path d="M12 9.8h3.6"/>',
			'stethoscope' => '<path d="M5.6 3.4v3.9a3 3 0 0 0 6 0V3.4"/><path d="M4.2 3.4h2.6M10.4 3.4H13"/><path d="M8.6 10.3v1.9a3.6 3.6 0 0 0 7.2 0v-1.4"/><circle cx="15.8" cy="9" r="1.6"/>',
		);
		$matches = array(
			'cardio'    => 'heart',
			'neuro'     => 'brain',
			'psych'     => 'brain',
			'gastro'    => 'stomach',
			'\bdent'    => 'tooth',
			'ophthal'   => 'eye',
			'orthop'    => 'bone',
			'rheumat'   => 'bone',
			'pulmon'    => 'lungs',
			'pediatric' => 'baby',
			'obstetric' => 'baby',
			'gyneco'    => 'baby',
			'dermat'    => 'skin',
			'nephro'    => 'kidney',
			'\burolog'  => 'kidney',
		);
		$icon    = 'stethoscope';

		// Patterns for the same reason as specialty_keywords().
		foreach ( $matches as $needle => $name ) {
			if ( preg_match( '/' . $needle . '/u', $slug ) ) {
				$icon = $name;
				break;
			}
		}

		return $open . $icons[ $icon ] . '</svg>';
	}

	/**
	 * Every listed doctor with the directory's details plus a per-clinic
	 * breakdown (see with_directory_details()'s $per_clinic) — for the
	 * Symptom Checker, which shows each doctor at their nearest clinic.
	 *
	 * @return array doctor_cards_data() rows with directory details and 'clinic_details'.
	 */
	public function cards_with_clinic_details() {
		$cards = $this->doctor_cards_data();

		return empty( $cards ) ? array() : $this->with_directory_details( $cards, true );
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
	 * With $per_clinic, each card also gets 'clinic_details': one entry per
	 * physical clinic with that clinic's own fee, next open slot, booking
	 * link (clinic pre-selected), map link and — when the shared clinic has
	 * map coordinates — its latitude/longitude.
	 *
	 * @param array $cards      doctor_cards_data() rows.
	 * @param bool  $per_clinic Add the per-clinic breakdown.
	 * @return array
	 */
	private function with_directory_details( array $cards, $per_clinic = false ) {
		$doctor_ids         = wp_list_pluck( $cards, 'id' );
		$clinics_by_doctor  = Clinics::get_for_doctors( $doctor_ids );
		$services_by_doctor = array();

		foreach ( Services::active_for_public_directory() as $service ) {
			$services_by_doctor[ $service['doctor_id'] ][] = $service;
		}

		$today       = current_time( 'Y-m-d' );
		$booked      = self::booked_slots( $today, gmdate( 'Y-m-d', strtotime( $today . ' +6 days' ) ) );
		$booking_url = Page_Finder::url_for_shortcode( 'book_appointment' );
		$coordinates = array();

		if ( $per_clinic ) {
			foreach ( Clinic_Locations::get_all() as $location ) {
				if ( null !== $location['latitude'] && null !== $location['longitude'] ) {
					$coordinates[ (int) $location['id'] ] = array( $location['latitude'], $location['longitude'] );
				}
			}
		}

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
			// Same "% OFF · Video consult" chip as the home page's doctor cards.
			$card['video_discount_percent'] = $video_pricing && $video_pricing['discount_active'] && (float) $video_pricing['base_price'] > 0 ? (int) $video_pricing['discount_percent'] : 0;

			// Largest discount currently running on any of this doctor's
			// clinic services — for the directory's discount chips and the
			// card's "% OFF · Services" tag.
			$service_discount = 0;

			if ( $offers_clinic ) {
				foreach ( $services as $service ) {
					if ( ! empty( $service['discount_active'] ) ) {
						$service_discount = max( $service_discount, (int) $service['discount_percent'] );
					}
				}
			}

			$card['service_discount_percent'] = $service_discount;
			$card['best_discount_percent']    = max( $service_discount, $card['video_discount_percent'] );

			// The lowest fee a patient can actually book with this doctor
			// (clinic or video, discounts applied) — for the fee chips. null
			// when no fee is configured, so such a doctor never matches a
			// "Up to PKR …" filter by accident.
			$fee_amounts = array();

			foreach ( array( $card['clinic_fee'], $card['video_fee'] ) as $fee ) {
				if ( $fee && 'unset' !== $fee['state'] && (float) $fee['amount'] > 0 ) {
					$fee_amounts[] = (float) $fee['amount'];
				}
			}

			$card['lowest_fee'] = ! empty( $fee_amounts ) ? min( $fee_amounts ) : null;
			$card['next_available']     = $next ? self::slot_label( $next, $today ) : '';
			$card['availability']       = $next ? ( $today === $next['date'] ? 'today' : 'week' ) : '';
			$card['next_at']            = $next ? $next['date'] . ' ' . $next['time'] : '';
			$card['clinic_booking_url'] = $booking_url && $offers_clinic ? add_query_arg( array( 'doctor_id' => $doctor_id, 'type' => 'clinic' ), $booking_url ) : '';
			$card['video_booking_url']  = $booking_url && $offers_video ? add_query_arg( array( 'doctor_id' => $doctor_id, 'type' => 'video' ), $booking_url ) : '';

			if ( $per_clinic ) {
				$card['clinic_details'] = array();

				foreach ( $physical as $clinic ) {
					$slot        = self::next_open_slot( array( $clinic ), 'clinic', $doctor_booked, $today );
					$location_id = (int) $clinic['clinic_location_id'];

					$card['clinic_details'][] = array(
						'id'             => (int) $clinic['id'],
						'name'           => '' !== $clinic['name'] ? $clinic['name'] : $clinic['address'],
						'place'          => implode( ', ', array_filter( array( $clinic['area_label'], $clinic['city_label'] ) ) ),
						'city'           => $clinic['city'],
						'city_label'     => $clinic['city_label'],
						'area'           => $clinic['area'],
						'area_label'     => $clinic['area_label'],
						'map_url'        => Clinics::map_url( $clinic ),
						'lat'            => isset( $coordinates[ $location_id ] ) ? $coordinates[ $location_id ][0] : null,
						'lng'            => isset( $coordinates[ $location_id ] ) ? $coordinates[ $location_id ][1] : null,
						'fee'            => self::clinic_fee( $services, array( $clinic ) ),
						'next_available' => $slot ? self::slot_label( $slot, $today ) : '',
						'next_at'        => $slot ? $slot['date'] . ' ' . $slot['time'] : '',
						'booking_url'    => $booking_url ? add_query_arg(
							array(
								'doctor_id' => $doctor_id,
								'type'      => 'clinic',
								'clinic_id' => (int) $clinic['id'],
							),
							$booking_url
						) : '',
					);
				}

				$video_slot = $offers_video ? self::next_open_slot( $clinics, 'video', $doctor_booked, $today ) : null;

				$card['video_next_available'] = $video_slot ? self::slot_label( $video_slot, $today ) : '';
			}
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
				'meta_query' => \DoctorAKPortal\Includes\Appointments::public_doctor_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no better lookup available; only approved, active doctors are public.
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
				'meta_query' => \DoctorAKPortal\Includes\Appointments::public_doctor_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no better lookup available; only approved, active doctors are public.
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
