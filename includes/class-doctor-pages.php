<?php
/**
 * One SEO page per public doctor at /doctor/{slug}/ — see Seo_Pages.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

use DoctorAKPortal\Frontend\Doctor_Profile_View;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Pages
 *
 * Key: the doctor's user ID. Public: an approved, active doctor (the same
 * rule the profile page itself uses). The page lists the doctor's bio,
 * specialities, expertise, clinics and fees for Yoast's analysis; visitors
 * see the normal [doctor_profile_view] profile.
 */
class Doctor_Pages extends Seo_Pages {

	const POST_TYPE           = 'dak_doctor';
	const NAME                = 'doctor';
	const REWRITE_SLUG        = 'doctor';
	const META_KEY            = '_dak_doctor_id';
	const SHORTCODE           = 'doctor_profile_view';
	const DIRECTORY_SHORTCODE = 'doctors_directory';
	const QUERY_ARG           = 'doctor_id';
	const VERSION             = '1.1.0';
	const MENU_POSITION       = 21;
	const MENU_ICON           = 'dashicons-id-alt';

	/**
	 * Per-request cache for facts().
	 *
	 * @var array
	 */
	private static $facts = array();

	/* ---------------------------------------------------------------------
	 * Public API used around the plugin
	 * ------------------------------------------------------------------- */

	/**
	 * A doctor's public profile URL: their own page when published, else
	 * /doctor-profile-view/?doctor_id=N.
	 *
	 * @param int   $doctor_id Doctor user ID.
	 * @param array $args      Extra query args (e.g. a review token).
	 * @return string
	 */
	public static function url( $doctor_id, array $args = array() ) {
		return static::link( (string) (int) $doctor_id, $args );
	}

	/**
	 * Whether this request is a doctor page.
	 *
	 * @return bool
	 */
	public static function is_doctor_page() {
		return static::is_entity_page();
	}

	/* ---------------------------------------------------------------------
	 * Seo_Pages
	 * ------------------------------------------------------------------- */

	/** @inheritDoc */
	protected static function labels() {
		return array(
			'name'     => __( 'Doctor Pages', 'doctor-ak-portal' ),
			'singular' => __( 'Doctor Page', 'doctor-ak-portal' ),
			'menu'     => __( 'Doctor Pages (SEO)', 'doctor-ak-portal' ),
		);
	}

	/** @inheritDoc */
	protected static function public_keys() {
		$keys = array();

		foreach ( get_users( array( 'role' => Roles::DOCTOR_ROLE, 'fields' => 'ID', 'number' => -1 ) ) as $doctor_id ) {
			if ( Appointments::is_public_doctor( (int) $doctor_id ) ) {
				$keys[] = (string) $doctor_id;
			}
		}

		return $keys;
	}

	/** @inheritDoc */
	protected static function is_public( $key ) {
		$user = get_userdata( (int) $key );

		return $user && in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) && Appointments::is_public_doctor( (int) $key );
	}

	/** @inheritDoc */
	protected static function request_args( $key ) {
		return array( 'doctor_id' => (int) $key );
	}

	/** @inheritDoc */
	protected static function key_from_old_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup.
		$doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0;

		return $doctor_id > 0 ? (string) $doctor_id : '';
	}

	/** @inheritDoc */
	protected static function source_label() {
		return __( 'the doctor\'s profile', 'doctor-ak-portal' );
	}

	/** @inheritDoc */
	protected static function yoast_defaults() {
		return array(
			'%%title%% – %%dak_specialty%% in %%dak_city%% %%sep%% Book Appointment %%sep%% %%sitename%%',
			'Book an appointment with %%title%%, %%dak_specialty%% at %%dak_clinics%%, %%dak_city%%. See fees, timings and patient reviews, and book online.',
		);
	}

	/** @inheritDoc */
	protected static function yoast_variables() {
		$fact = function ( $field, $fallback = '' ) {
			return function ( $key ) use ( $field, $fallback ) {
				$f     = Doctor_Pages::facts( (int) $key );
				$value = $f[ $field ];

				if ( is_array( $value ) ) {
					$value = 'clinic_names' === $field ? wp_sprintf( '%l', array_slice( $value, 0, 3 ) ) : implode( ', ', $value );
				}

				return '' !== (string) $value ? $value : $fallback;
			};
		};

		return array(
			'dak_specialty'     => array( __( "The doctor's main speciality, e.g. Gastroenterologist.", 'doctor-ak-portal' ), $fact( 'specialty', __( 'Specialist doctor', 'doctor-ak-portal' ) ) ),
			'dak_specialties'   => array( __( "All of the doctor's specialities.", 'doctor-ak-portal' ), $fact( 'specialties' ) ),
			'dak_city'          => array( __( "City of the doctor's first clinic.", 'doctor-ak-portal' ), $fact( 'city', __( 'Pakistan', 'doctor-ak-portal' ) ) ),
			'dak_clinics'       => array( __( "The doctor's clinics (up to three).", 'doctor-ak-portal' ), $fact( 'clinic_names', __( 'our clinics', 'doctor-ak-portal' ) ) ),
			'dak_experience'    => array( __( 'Years of experience, e.g. "12 years" (empty if not set).', 'doctor-ak-portal' ), $fact( 'experience' ) ),
			'dak_qualification' => array( __( "The doctor's qualifications.", 'doctor-ak-portal' ), $fact( 'qualification' ) ),
			'dak_fee'           => array( __( 'Starting consultation fee, e.g. "Rs. 2,000" (empty if not set).', 'doctor-ak-portal' ), $fact( 'fee' ) ),
		);
	}

	/** @inheritDoc */
	protected static function build( $key ) {
		$doctor_id = (int) $key;

		unset( self::$facts[ $doctor_id ] );
		$f = self::facts( $doctor_id );

		$lines = array();

		if ( '' !== $f['short_description'] ) {
			$lines[] = '<p>' . esc_html( $f['short_description'] ) . '</p>';
		}

		/* translators: %s: doctor's name. */
		$lines[] = '<h2>' . esc_html( sprintf( __( 'About %s', 'doctor-ak-portal' ), $f['title'] ) ) . '</h2>';
		$lines[] = '<p>' . esc_html( self::summary_sentence( $f ) ) . '</p>';

		if ( count( $f['specialties'] ) > 1 ) {
			$lines[] = '<h2>' . esc_html__( 'Specialities', 'doctor-ak-portal' ) . '</h2>';
			$lines[] = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $f['specialties'] ) ) . '</li></ul>';
		}

		if ( '' !== $f['expertise'] ) {
			$lines[] = '<h2>' . esc_html__( 'Areas of expertise', 'doctor-ak-portal' ) . '</h2>';
			$lines[] = wp_kses_post( $f['expertise'] );
		}

		if ( ! empty( $f['clinic_lines'] ) ) {
			$lines[] = '<h2>' . esc_html__( 'Clinics', 'doctor-ak-portal' ) . '</h2>';
			$lines[] = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $f['clinic_lines'] ) ) . '</li></ul>';
		}

		if ( '' !== $f['fee'] || $f['video'] ) {
			$lines[] = '<h2>' . esc_html__( 'Fees and booking', 'doctor-ak-portal' ) . '</h2>';

			if ( '' !== $f['fee'] ) {
				/* translators: %s: starting consultation fee, e.g. "Rs. 2,000". */
				$lines[] = '<p>' . esc_html( sprintf( __( 'Consultation from %s. Book your appointment online.', 'doctor-ak-portal' ), $f['fee'] ) ) . '</p>';
			}

			if ( $f['video'] ) {
				$lines[] = '<p>' . esc_html__( 'Online video consultation is also available.', 'doctor-ak-portal' ) . '</p>';
			}
		}

		$excerpt = '' !== $f['short_description'] ? $f['short_description'] : self::summary_sentence( $f );

		return array(
			'title'        => $f['title'],
			'slug'         => sanitize_title( trim( 'dr ' . $f['name'] . ' ' . $f['specialty'] ) ),
			'content'      => implode( "\n\n", $lines ),
			'excerpt'      => wp_html_excerpt( $excerpt, 300, '…' ),
			'thumbnail_id' => $f['picture_id'],
		);
	}

	/**
	 * "Dr. X is a Gastroenterologist with 12 years of experience, practising
	 * at A and B in Karachi." — whatever parts are known.
	 *
	 * @param array $f facts().
	 * @return string
	 */
	private static function summary_sentence( array $f ) {
		/* translators: 1: doctor's name, 2: speciality. */
		$sentence = sprintf( __( '%1$s is a %2$s', 'doctor-ak-portal' ), $f['title'], '' !== $f['specialty'] ? $f['specialty'] : __( 'specialist doctor', 'doctor-ak-portal' ) );

		if ( '' !== $f['experience'] ) {
			/* translators: %s: e.g. "12 years". */
			$sentence .= sprintf( __( ' with %s of experience', 'doctor-ak-portal' ), $f['experience'] );
		}

		if ( ! empty( $f['clinic_names'] ) ) {
			/* translators: %s: clinic names. */
			$sentence .= sprintf( __( ', practising at %s', 'doctor-ak-portal' ), wp_sprintf( '%l', array_slice( $f['clinic_names'], 0, 3 ) ) );

			if ( '' !== $f['city'] ) {
				/* translators: %s: city. */
				$sentence .= sprintf( __( ' in %s', 'doctor-ak-portal' ), $f['city'] );
			}
		}

		$sentence .= '.';

		if ( '' !== $f['qualification'] ) {
			/* translators: %s: qualifications, e.g. "MBBS, FCPS (Gastroenterology)". */
			$sentence .= ' ' . sprintf( __( 'Qualifications: %s.', 'doctor-ak-portal' ), rtrim( $f['qualification'], '.' ) );
		}

		return $sentence;
	}

	/**
	 * Everything a doctor's page, the Yoast variables, and the service and
	 * clinic pages say about a doctor.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return array
	 */
	public static function facts( $doctor_id ) {
		$doctor_id = (int) $doctor_id;

		if ( isset( self::$facts[ $doctor_id ] ) ) {
			return self::$facts[ $doctor_id ];
		}

		$user = get_userdata( $doctor_id );
		$name = '';

		if ( $user ) {
			$name = trim( $user->first_name . ' ' . $user->last_name );
			$name = '' !== $name ? $name : $user->display_name;
		}

		// "Dr." is added once, whether or not the stored name already has it.
		$name = trim( preg_replace( '/^dr\.?\s+/i', '', (string) $name ) );

		$all_specializations = Specializations::get_all();
		$specialties         = array();

		foreach ( (array) get_user_meta( $doctor_id, 'doctor_ak_specializations', true ) as $slug ) {
			if ( isset( $all_specializations[ $slug ] ) ) {
				$specialties[] = $all_specializations[ $slug ];
			}
		}

		$clinic_names = array();
		$clinic_lines = array();
		$cities       = array();
		$video        = false;

		foreach ( Clinics::get_for_doctor( $doctor_id ) as $clinic ) {
			if ( Clinics::TYPE_PHYSICAL !== $clinic['type'] ) {
				$video = true;
				continue;
			}

			$place          = implode( ', ', array_filter( array( $clinic['area_label'], $clinic['city_label'] ) ) );
			$clinic_names[] = $clinic['name'];
			$clinic_lines[] = '' !== $place ? $clinic['name'] . ' — ' . $place : $clinic['name'];

			if ( '' !== $clinic['city_label'] ) {
				$cities[] = $clinic['city_label'];
			}
		}

		$clinic_names = array_values( array_unique( $clinic_names ) );
		$cities       = array_values( array_unique( $cities ) );
		$years        = (int) get_user_meta( $doctor_id, 'doctor_ak_years_experience', true );
		$fee          = Doctor_Profile_View::starting_fee_summary( $doctor_id );
		$expertise    = get_user_meta( $doctor_id, 'doctor_ak_expertise', true );

		self::$facts[ $doctor_id ] = array(
			'name'              => $name,
			'title'             => '' !== $name ? sprintf( 'Dr. %s', $name ) : __( 'Doctor', 'doctor-ak-portal' ),
			'specialty'         => isset( $specialties[0] ) ? $specialties[0] : '',
			'specialties'       => $specialties,
			'qualification'     => trim( (string) get_user_meta( $doctor_id, 'doctor_ak_qualification', true ) ),
			/* translators: %d: number of years. */
			'experience'        => $years > 0 ? sprintf( _n( '%d year', '%d years', $years, 'doctor-ak-portal' ), $years ) : '',
			'short_description' => trim( wp_strip_all_tags( (string) get_user_meta( $doctor_id, 'doctor_ak_short_description', true ) ) ),
			'expertise'         => is_array( $expertise ) ? implode( ', ', array_map( 'wp_strip_all_tags', $expertise ) ) : trim( (string) $expertise ),
			'clinic_names'      => $clinic_names,
			'clinic_lines'      => $clinic_lines,
			'city'              => isset( $cities[0] ) ? $cities[0] : '',
			'video'             => $video,
			'fee'               => in_array( $fee['state'], array( 'paid', 'range' ), true ) ? $fee['label'] : '',
			'picture_id'        => (int) get_user_meta( $doctor_id, 'doctor_ak_profile_picture_id', true ),
		);

		return self::$facts[ $doctor_id ];
	}

	/* ---------------------------------------------------------------------
	 * Keeping pages in step with doctor data
	 * ------------------------------------------------------------------- */

	/**
	 * Queues a doctor when one of their fields changes. Hooked to
	 * added_/updated_/deleted_user_meta.
	 *
	 * @param int    $meta_id  Unused.
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public static function on_user_meta_change( $meta_id, $user_id, $meta_key ) {
		global $wpdb;

		unset( $meta_id );

		if ( 0 === strpos( $meta_key, 'doctor_ak_' ) || in_array( $meta_key, array( 'first_name', 'last_name', $wpdb->prefix . 'capabilities' ), true ) ) {
			static::queue( $user_id );
		}
	}

	/**
	 * Syncs queued doctors — skipping patients and staff, who share the
	 * doctor_ak_ meta prefix. When a doctor's page appears or disappears,
	 * the service and clinic pages (which list doctors) are re-synced too.
	 * Hooked to 'shutdown'.
	 *
	 * @return void
	 */
	public static function process_queue() {
		$s     = &static::state();
		$all   = $s['queue_all'];
		$queue = $s['queue'];

		$s['queue_all'] = false;
		$s['queue']     = array();

		if ( $all ) {
			static::sync_all();
			return;
		}

		$posts = static::posts();

		foreach ( $queue as $key ) {
			$user = get_userdata( (int) $key );

			if ( ! $user || ( ! in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) && ! isset( $posts[ $key ] ) ) ) {
				continue;
			}

			$result = static::sync_key( $key );

			if ( 'created' === $result || 'unpublished' === $result ) {
				Service_Pages::queue_all();
				Clinic_Pages::queue_all();
			}
		}
	}

	/**
	 * A deleted doctor's page is deleted with them. Hooked to 'delete_user'.
	 *
	 * @param int $user_id User being deleted.
	 * @return void
	 */
	public static function on_delete_user( $user_id ) {
		static::delete_page( (string) (int) $user_id );

		$s = &static::state();
		unset( $s['queue'][ (string) (int) $user_id ] );
	}
}
