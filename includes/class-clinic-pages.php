<?php
/**
 * One SEO page per clinic location at /clinic/{slug}/ — see Seo_Pages.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

use DoctorAKPortal\Frontend\Clinic_Public_Data;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinic_Pages
 *
 * Key: the clinic location ID (the admin "Clinic" list). Public: every
 * clinic the public Clinics pages list (Clinic_Public_Data::locations()).
 * When duplicate clinics are merged, the removed clinic's page is deleted
 * and its URL becomes an old slug of the kept clinic's page, so WordPress
 * redirects the old address there.
 */
class Clinic_Pages extends Seo_Pages {

	const POST_TYPE           = 'dak_clinic';
	const NAME                = 'clinic';
	const REWRITE_SLUG        = 'clinic';
	const META_KEY            = '_dak_clinic_location_id';
	const SHORTCODE           = 'clinic_profile_view';
	const DIRECTORY_SHORTCODE = 'clinics_directory';
	const QUERY_ARG           = 'clinic_id';
	const VERSION             = '1.0.0';
	const MENU_POSITION       = 23;
	const MENU_ICON           = 'dashicons-location';

	/**
	 * A clinic's public URL: its own page when published, else
	 * /clinic-details/?clinic_id=N.
	 *
	 * @param int   $location_id Clinic location ID.
	 * @param array $args        Extra query args.
	 * @return string
	 */
	public static function url( $location_id, array $args = array() ) {
		return static::link( (string) (int) $location_id, $args );
	}

	/**
	 * After duplicate clinics are merged: the removed clinics' URLs become
	 * old slugs of the kept clinic's page (WordPress redirects them), then
	 * their pages are deleted. Hooked to 'doctor_ak_clinic_locations_merged'.
	 *
	 * @param int   $keep_id   Kept clinic location ID.
	 * @param int[] $merge_ids Removed clinic location IDs.
	 * @return void
	 */
	public static function on_merge( $keep_id, $merge_ids ) {
		static::sync_key( (string) (int) $keep_id );

		$posts   = static::posts();
		$keep    = (string) (int) $keep_id;
		$keep_id = isset( $posts[ $keep ] ) ? $posts[ $keep ]['id'] : 0;

		foreach ( (array) $merge_ids as $merge_id ) {
			$merge = (string) (int) $merge_id;

			if ( empty( $posts[ $merge ] ) ) {
				continue;
			}

			$old = get_post( $posts[ $merge ]['id'] );

			if ( $keep_id && $old && '' !== $old->post_name ) {
				add_post_meta( $keep_id, '_wp_old_slug', $old->post_name );
			}

			static::delete_page( $merge );
		}

		static::queue_all();
	}

	/* ---------------------------------------------------------------------
	 * Seo_Pages
	 * ------------------------------------------------------------------- */

	/** @inheritDoc */
	protected static function labels() {
		return array(
			'name'     => __( 'Clinic Pages', 'doctor-ak-portal' ),
			'singular' => __( 'Clinic Page', 'doctor-ak-portal' ),
			'menu'     => __( 'Clinic Pages (SEO)', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Clinic_Public_Data::locations() keyed by ID.
	 *
	 * @return array
	 */
	private static function locations() {
		$out = array();

		foreach ( Clinic_Public_Data::locations() as $location ) {
			$out[ (string) (int) $location['id'] ] = $location;
		}

		return $out;
	}

	/** @inheritDoc */
	protected static function public_keys() {
		return array_map( 'strval', array_keys( self::locations() ) );
	}

	/** @inheritDoc */
	protected static function is_public( $key ) {
		return null !== Clinic_Public_Data::find( (int) $key );
	}

	/** @inheritDoc */
	protected static function request_args( $key ) {
		return array( 'clinic_id' => (int) $key );
	}

	/** @inheritDoc */
	protected static function key_from_old_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup.
		$clinic_id = isset( $_GET['clinic_id'] ) ? absint( $_GET['clinic_id'] ) : 0;

		return $clinic_id > 0 ? (string) $clinic_id : '';
	}

	/** @inheritDoc */
	protected static function source_label() {
		return __( 'the clinic\'s details', 'doctor-ak-portal' );
	}

	/** @inheritDoc */
	protected static function yoast_defaults() {
		return array(
			'%%title%%, %%dak_clinic_place%% – Doctors & Appointments %%sep%% %%sitename%%',
			'Book an appointment at %%title%%, %%dak_clinic_place%%. See %%dak_clinic_doctors%%, specialities, timings, fees and directions, and book online.',
		);
	}

	/** @inheritDoc */
	protected static function yoast_variables() {
		$fact = function ( $field, $fallback = '' ) {
			return function ( $key ) use ( $field, $fallback ) {
				$f     = self::facts( $key );
				$value = $f ? $f[ $field ] : '';

				return '' !== (string) $value ? $value : $fallback;
			};
		};

		return array(
			'dak_clinic_place'       => array( __( 'Area and city, e.g. "Clifton, Karachi".', 'doctor-ak-portal' ), $fact( 'place', __( 'Pakistan', 'doctor-ak-portal' ) ) ),
			'dak_clinic_area'        => array( __( 'The clinic\'s area.', 'doctor-ak-portal' ), $fact( 'area' ) ),
			'dak_clinic_city'        => array( __( 'The clinic\'s city.', 'doctor-ak-portal' ), $fact( 'city', __( 'Pakistan', 'doctor-ak-portal' ) ) ),
			'dak_clinic_address'     => array( __( 'The clinic\'s street address.', 'doctor-ak-portal' ), $fact( 'address' ) ),
			'dak_clinic_doctors'     => array( __( 'How many doctors practise there, e.g. "12 doctors".', 'doctor-ak-portal' ), $fact( 'doctors_label', __( 'our doctors', 'doctor-ak-portal' ) ) ),
			'dak_clinic_specialties' => array( __( 'Up to three specialities available there.', 'doctor-ak-portal' ), $fact( 'specialties_label' ) ),
		);
	}

	/**
	 * What a clinic's page and Yoast variables say about it, or null.
	 *
	 * @param string $key Clinic location ID.
	 * @return array|null
	 */
	private static function facts( $key ) {
		$location = Clinic_Public_Data::find( (int) $key );

		if ( ! $location ) {
			return null;
		}

		$doctors     = array();
		$specialties = array();

		foreach ( $location['doctor_rows'] as $row ) {
			$f         = Doctor_Pages::facts( (int) $row['doctor_id'] );
			$doctors[] = '' !== $f['specialty'] ? $f['title'] . ' — ' . $f['specialty'] : $f['title'];

			foreach ( $f['specialties'] as $label ) {
				$specialties[ mb_strtolower( $label ) ] = $label;
			}
		}

		$count = count( $doctors );

		return array(
			'name'              => (string) $location['name'],
			'place'             => (string) $location['place'],
			'area'              => (string) $location['area_label'],
			'city'              => (string) $location['city_label'],
			'address'           => (string) $location['address'],
			'phone'             => (string) $location['phone'],
			'doctors'           => $doctors,
			'specialties'       => array_values( $specialties ),
			/* translators: %d: number of doctors. */
			'doctors_label'     => $count > 0 ? sprintf( _n( '%d doctor', '%d doctors', $count, 'doctor-ak-portal' ), $count ) : '',
			'specialties_label' => $specialties ? wp_sprintf( '%l', array_slice( array_values( $specialties ), 0, 3 ) ) : '',
		);
	}

	/** @inheritDoc */
	protected static function build( $key ) {
		$f     = self::facts( $key );
		$lines = array();

		/* translators: %s: clinic name. */
		$lines[] = '<h2>' . esc_html( sprintf( __( 'About %s', 'doctor-ak-portal' ), $f['name'] ) ) . '</h2>';

		$sentence = '' !== $f['place']
			/* translators: 1: clinic name, 2: "Area, City". */
			? sprintf( __( '%1$s is a clinic in %2$s', 'doctor-ak-portal' ), $f['name'], $f['place'] )
			/* translators: %s: clinic name. */
			: sprintf( __( '%s is one of our clinics', 'doctor-ak-portal' ), $f['name'] );

		if ( '' !== $f['doctors_label'] ) {
			/* translators: %s: e.g. "12 doctors". */
			$sentence .= sprintf( __( ' where %s see patients', 'doctor-ak-portal' ), $f['doctors_label'] );
		}

		$sentence .= '. ' . __( 'Book your appointment online.', 'doctor-ak-portal' );
		$lines[]   = '<p>' . esc_html( $sentence ) . '</p>';

		if ( '' !== $f['address'] ) {
			$lines[] = '<h2>' . esc_html__( 'Address', 'doctor-ak-portal' ) . '</h2>';
			$lines[] = '<p>' . esc_html( implode( ', ', array_filter( array( $f['address'], $f['place'] ) ) ) ) . '</p>';
		}

		if ( $f['specialties'] ) {
			$lines[] = '<h2>' . esc_html__( 'Specialities available', 'doctor-ak-portal' ) . '</h2>';
			$lines[] = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $f['specialties'] ) ) . '</li></ul>';
		}

		if ( $f['doctors'] ) {
			/* translators: %s: clinic name. */
			$lines[] = '<h2>' . esc_html( sprintf( __( 'Doctors at %s', 'doctor-ak-portal' ), $f['name'] ) ) . '</h2>';
			$lines[] = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $f['doctors'] ) ) . '</li></ul>';
		}

		return array(
			'title'        => $f['name'],
			'slug'         => sanitize_title( implode( ' ', array_filter( array( $f['name'], $f['area'], $f['city'] ) ) ) ),
			'content'      => implode( "\n\n", $lines ),
			'excerpt'      => wp_html_excerpt( $sentence, 300, '…' ),
			'thumbnail_id' => 0,
		);
	}
}
