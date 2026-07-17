<?php
/**
 * Weekly availability sanitization and storage helper.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Availability
 *
 * Converts posted weekly-availability form data into a validated array and
 * back into the JSON structure stored in user meta. Shared by registration
 * (Phase 3) and the standalone Availability Management screen (Phase 6).
 */
class Availability {

	/**
	 * User meta key the availability JSON is stored under.
	 *
	 * @var string
	 */
	const META_KEY = 'doctor_ak_availability';

	/**
	 * Days of the week, in display order, keyed by slug.
	 *
	 * @return array
	 */
	public static function days() {
		return array(
			'monday'    => __( 'Monday', 'doctor-ak-portal' ),
			'tuesday'   => __( 'Tuesday', 'doctor-ak-portal' ),
			'wednesday' => __( 'Wednesday', 'doctor-ak-portal' ),
			'thursday'  => __( 'Thursday', 'doctor-ak-portal' ),
			'friday'    => __( 'Friday', 'doctor-ak-portal' ),
			'saturday'  => __( 'Saturday', 'doctor-ak-portal' ),
			'sunday'    => __( 'Sunday', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Returns an availability structure with every day disabled.
	 *
	 * @return array
	 */
	public static function empty_schedule() {
		$schedule = array();

		foreach ( self::days() as $slug => $label ) {
			$schedule[ $slug ] = array(
				'enabled' => false,
				'start'   => '',
				'end'     => '',
			);
		}

		return $schedule;
	}

	/**
	 * Validates and sanitizes the `availability` portion of a request.
	 *
	 * Expects the shape produced by fields named `availability[day][enabled]`,
	 * `availability[day][start]` and `availability[day][end]`.
	 *
	 * @param array $posted_availability Raw `availability` sub-array from the request.
	 * @return array|\WP_Error Sanitized schedule, or WP_Error on invalid input.
	 */
	public static function sanitize_from_request( array $posted_availability ) {
		$schedule = array();

		foreach ( self::days() as $slug => $label ) {
			$day = isset( $posted_availability[ $slug ] ) && is_array( $posted_availability[ $slug ] )
				? $posted_availability[ $slug ]
				: array();

			$enabled = ! empty( $day['enabled'] );

			if ( ! $enabled ) {
				$schedule[ $slug ] = array(
					'enabled' => false,
					'start'   => '',
					'end'     => '',
				);
				continue;
			}

			$start = isset( $day['start'] ) ? sanitize_text_field( wp_unslash( $day['start'] ) ) : '';
			$end   = isset( $day['end'] ) ? sanitize_text_field( wp_unslash( $day['end'] ) ) : '';

			if ( ! self::is_valid_time( $start ) || ! self::is_valid_time( $end ) ) {
				/* translators: %s: day of the week. */
				return new \WP_Error( 'doctor_ak_invalid_availability_time', sprintf( __( 'Please provide a valid start and end time for %s.', 'doctor-ak-portal' ), $label ) );
			}

			if ( $start >= $end ) {
				/* translators: %s: day of the week. */
				return new \WP_Error( 'doctor_ak_invalid_availability_range', sprintf( __( 'The end time must be after the start time for %s.', 'doctor-ak-portal' ), $label ) );
			}

			$schedule[ $slug ] = array(
				'enabled' => true,
				'start'   => $start,
				'end'     => $end,
			);
		}

		return $schedule;
	}

	/**
	 * Validates a time string in 24-hour HH:MM format.
	 *
	 * @param string $time Time string.
	 * @return bool
	 */
	private static function is_valid_time( $time ) {
		return (bool) preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time );
	}

	/**
	 * Encodes a schedule array as JSON for storage in user meta.
	 *
	 * @param array $schedule Sanitized schedule array.
	 * @return string
	 */
	public static function to_json( array $schedule ) {
		return wp_json_encode( $schedule );
	}

	/**
	 * Decodes a schedule previously stored in user meta.
	 *
	 * @param string $json JSON string from user meta.
	 * @return array
	 */
	public static function from_json( $json ) {
		$decoded = json_decode( (string) $json, true );

		if ( ! is_array( $decoded ) ) {
			return self::empty_schedule();
		}

		return wp_parse_args( $decoded, self::empty_schedule() );
	}

	/**
	 * Saves a sanitized schedule to a user's meta.
	 *
	 * @param int   $user_id  User ID.
	 * @param array $schedule Sanitized schedule array.
	 * @return void
	 */
	public static function save( $user_id, array $schedule ) {
		update_user_meta( $user_id, self::META_KEY, self::to_json( $schedule ) );
	}

	/**
	 * Retrieves a user's saved schedule.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get( $user_id ) {
		return self::from_json( get_user_meta( $user_id, self::META_KEY, true ) );
	}
}
