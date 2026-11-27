<?php
/**
 * One SEO page per public service at /service/{slug}/ — see Seo_Pages.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Pages
 *
 * A "service" here is what the public Services pages show: every active
 * service row with the same name, across all the doctors who offer it,
 * grouped into one (Services::grouped_active_for_public_directory()). So
 * the key is the lower-cased service name — one "Colonoscopy" page however
 * many doctors offer it, and it survives individual rows being added or
 * removed. The profile renderer still takes a row ID (?service_id=), so
 * request_args() passes the group's current representative row.
 */
class Service_Pages extends Seo_Pages {

	const POST_TYPE           = 'dak_service';
	const NAME                = 'service';
	const REWRITE_SLUG        = 'service';
	const META_KEY            = '_dak_service_key';
	const SHORTCODE           = 'service_profile_view';
	const DIRECTORY_SHORTCODE = 'services_directory';
	const QUERY_ARG           = 'service_id';
	const VERSION             = '1.0.0';
	const MENU_POSITION       = 22;
	const MENU_ICON           = 'dashicons-heart';

	/**
	 * Per-request data: groups (key => group), doctors (key => doctor IDs),
	 * keys (service row ID => key).
	 *
	 * @var array|null
	 */
	private static $data = null;

	/* ---------------------------------------------------------------------
	 * Public API used around the plugin
	 * ------------------------------------------------------------------- */

	/**
	 * A service's public URL: its own page when published, else
	 * /service-detail-page/?service_id=N.
	 *
	 * @param int    $service_id Any service row ID of the service.
	 * @param array  $args       Extra query args.
	 * @param string $name       The service's name, when the caller has it (saves a lookup).
	 * @return string
	 */
	public static function url( $service_id, array $args = array(), $name = '' ) {
		$key = '' !== (string) $name ? self::key_for_name( $name ) : self::key_for_service_id( (int) $service_id );

		return static::link( $key, $args, (int) $service_id );
	}

	/**
	 * The key for a service name.
	 *
	 * @param string $name Service name.
	 * @return string
	 */
	public static function key_for_name( $name ) {
		return mb_strtolower( trim( (string) $name ) );
	}

	/**
	 * The key of the service a row belongs to, or ''.
	 *
	 * @param int $service_id Service row ID.
	 * @return string
	 */
	private static function key_for_service_id( $service_id ) {
		$data = self::data();

		if ( isset( $data['keys'][ $service_id ] ) ) {
			return $data['keys'][ $service_id ];
		}

		$service = $service_id > 0 ? Services::find_for_public_profile( $service_id ) : null;

		return $service ? self::key_for_name( $service['name'] ) : '';
	}

	/**
	 * Groups, doctors and row → key map, built once per request.
	 *
	 * @return array
	 */
	private static function data() {
		if ( null !== self::$data ) {
			return self::$data;
		}

		$data = array(
			'groups'  => array(),
			'doctors' => array(),
			'keys'    => array(),
		);

		foreach ( Services::grouped_active_for_public_directory() as $group ) {
			$data['groups'][ self::key_for_name( $group['name'] ) ] = $group;
		}

		foreach ( Services::active_for_public_directory() as $row ) {
			$key = self::key_for_name( $row['name'] );

			$data['keys'][ (int) $row['id'] ]                     = $key;
			$data['doctors'][ $key ][ (int) $row['doctor_id'] ] = (int) $row['doctor_id'];
		}

		self::$data = $data;

		return $data;
	}

	/**
	 * Re-reads the services (they may have just changed), then syncs.
	 *
	 * @return array Counts, see Seo_Pages::sync_all().
	 */
	public static function sync_all() {
		self::$data = null;

		return parent::sync_all();
	}

	/* ---------------------------------------------------------------------
	 * Seo_Pages
	 * ------------------------------------------------------------------- */

	/** @inheritDoc */
	protected static function labels() {
		return array(
			'name'     => __( 'Service Pages', 'doctor-ak-portal' ),
			'singular' => __( 'Service Page', 'doctor-ak-portal' ),
			'menu'     => __( 'Service Pages (SEO)', 'doctor-ak-portal' ),
		);
	}

	/** @inheritDoc */
	protected static function public_keys() {
		return array_map( 'strval', array_keys( self::data()['groups'] ) );
	}

	/** @inheritDoc */
	protected static function request_args( $key ) {
		$groups = self::data()['groups'];

		return isset( $groups[ $key ] ) ? array( 'service_id' => (int) $groups[ $key ]['id'] ) : array( 'service_id' => 0 );
	}

	/** @inheritDoc */
	protected static function key_from_old_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup.
		$service_id = isset( $_GET['service_id'] ) ? absint( $_GET['service_id'] ) : 0;

		return $service_id > 0 ? self::key_for_service_id( $service_id ) : '';
	}

	/** @inheritDoc */
	protected static function source_label() {
		return __( 'the service settings', 'doctor-ak-portal' );
	}

	/** @inheritDoc */
	protected static function yoast_defaults() {
		return array(
			'%%title%% – Doctors, Fees & Online Booking %%sep%% %%sitename%%',
			'Book %%title%% online at %%sitename%%. Compare %%dak_service_doctors%%, see fees and clinic timings, and book your appointment in minutes.',
		);
	}

	/** @inheritDoc */
	protected static function yoast_variables() {
		$group = function ( $key ) {
			$groups = self::data()['groups'];

			return isset( $groups[ $key ] ) ? $groups[ $key ] : null;
		};

		return array(
			'dak_service_category' => array(
				__( 'The service category, e.g. Endoscopy.', 'doctor-ak-portal' ),
				function ( $key ) use ( $group ) {
					$g = $group( $key );
					return $g ? (string) $g['category_label'] : '';
				},
			),
			'dak_service_price'    => array(
				__( 'The price shown on the service page, e.g. "From Rs. 2,000" (empty if not set).', 'doctor-ak-portal' ),
				function ( $key ) use ( $group ) {
					$g = $group( $key );
					return $g && isset( $g['public_price'] ) && in_array( $g['public_price']['state'], array( 'paid', 'range' ), true ) ? (string) $g['public_price']['label'] : '';
				},
			),
			'dak_service_doctors'  => array(
				__( 'How many doctors offer it, e.g. "5 doctors".', 'doctor-ak-portal' ),
				function ( $key ) {
					$count = count( self::doctor_ids( $key ) );
					/* translators: %d: number of doctors. */
					return sprintf( _n( '%d doctor', '%d doctors', max( 1, $count ), 'doctor-ak-portal' ), max( 1, $count ) );
				},
			),
			'dak_service_discount' => array(
				__( 'The biggest discount running now, e.g. "Up to 20% off" (empty if none).', 'doctor-ak-portal' ),
				function ( $key ) use ( $group ) {
					$g = $group( $key );
					/* translators: %d: discount percentage. */
					return $g && (int) $g['discount_percent'] > 0 ? sprintf( __( 'Up to %d%% off', 'doctor-ak-portal' ), (int) $g['discount_percent'] ) : '';
				},
			),
		);
	}

	/**
	 * Doctor IDs offering a service.
	 *
	 * @param string $key Service key.
	 * @return int[]
	 */
	private static function doctor_ids( $key ) {
		$doctors = self::data()['doctors'];

		return isset( $doctors[ $key ] ) ? array_values( $doctors[ $key ] ) : array();
	}

	/** @inheritDoc */
	protected static function build( $key ) {
		$groups = self::data()['groups'];
		$g      = $groups[ $key ];
		$lines  = array();

		$description = trim( wp_strip_all_tags( (string) $g['description'] ) );

		if ( '' !== $description ) {
			$lines[] = '<p>' . esc_html( $description ) . '</p>';
		}

		$doctors = array();

		foreach ( self::doctor_ids( $key ) as $doctor_id ) {
			$f         = Doctor_Pages::facts( $doctor_id );
			$doctors[] = '' !== $f['specialty'] ? $f['title'] . ' — ' . $f['specialty'] : $f['title'];
		}

		/* translators: %s: service name. */
		$lines[] = '<h2>' . esc_html( sprintf( __( 'About %s', 'doctor-ak-portal' ), $g['name'] ) ) . '</h2>';
		$lines[] = '<p>' . esc_html( self::summary_sentence( $g, count( $doctors ) ) ) . '</p>';

		if ( $doctors ) {
			/* translators: %s: service name. */
			$lines[] = '<h2>' . esc_html( sprintf( __( 'Doctors offering %s', 'doctor-ak-portal' ), $g['name'] ) ) . '</h2>';
			$lines[] = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $doctors ) ) . '</li></ul>';
		}

		$price = isset( $g['public_price'] ) && in_array( $g['public_price']['state'], array( 'paid', 'range' ), true ) ? (string) $g['public_price']['label'] : '';

		if ( '' !== $price || (int) $g['discount_percent'] > 0 ) {
			$lines[] = '<h2>' . esc_html__( 'Fees and booking', 'doctor-ak-portal' ) . '</h2>';

			if ( '' !== $price ) {
				/* translators: %s: price, e.g. "From Rs. 2,000". */
				$lines[] = '<p>' . esc_html( sprintf( __( 'Fee: %s. Book online in a few clicks.', 'doctor-ak-portal' ), $price ) ) . '</p>';
			}

			if ( (int) $g['discount_percent'] > 0 ) {
				/* translators: %d: discount percentage. */
				$lines[] = '<p>' . esc_html( sprintf( __( 'Save up to %d%% — the discount is applied automatically when you book.', 'doctor-ak-portal' ), (int) $g['discount_percent'] ) ) . '</p>';
			}
		}

		$image_id = '' !== (string) $g['image_url'] ? (int) attachment_url_to_postid( $g['image_url'] ) : 0;

		return array(
			'title'        => $g['name'],
			'slug'         => sanitize_title( $g['name'] ),
			'content'      => implode( "\n\n", $lines ),
			'excerpt'      => wp_html_excerpt( '' !== $description ? $description : self::summary_sentence( $g, count( $doctors ) ), 300, '…' ),
			'thumbnail_id' => $image_id,
		);
	}

	/**
	 * "Colonoscopy (Endoscopy) is offered by 5 verified doctors at
	 * Doctoraklohana.com, with online booking."
	 *
	 * @param array $g       Service group.
	 * @param int   $doctors How many doctors offer it.
	 * @return string
	 */
	private static function summary_sentence( array $g, $doctors ) {
		$name = '' !== (string) $g['category_label'] ? sprintf( '%s (%s)', $g['name'], $g['category_label'] ) : $g['name'];

		return sprintf(
			/* translators: 1: service name, 2: number of doctors, 3: site name. */
			_n( '%1$s is offered by %2$d verified doctor at %3$s, with online booking.', '%1$s is offered by %2$d verified doctors at %3$s, with online booking.', max( 1, $doctors ), 'doctor-ak-portal' ),
			$name,
			max( 1, $doctors ),
			get_bloginfo( 'name' )
		);
	}
}
