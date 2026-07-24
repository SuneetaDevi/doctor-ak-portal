<?php
/**
 * A doctor's "Awards & Recognition" list (title + year rows).
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Awards
 *
 * Stored as one JSON-encoded user meta value (a small, admin/doctor-curated
 * list — not worth a dedicated DB table). Shared by Profile_Handler (doctor
 * self-editing their own profile) and Admin_User_Handler (admin editing any
 * doctor), so both save/validate the exact same shape.
 */
class Doctor_Awards {

	/**
	 * User meta key the JSON-encoded award list is stored under.
	 *
	 * @var string
	 */
	const META_KEY = 'doctor_ak_awards';

	/**
	 * Earliest year an award can reasonably be dated.
	 *
	 * @var int
	 */
	const MIN_YEAR = 1950;

	/**
	 * A doctor's current awards.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array List of `array( 'title' => string, 'year' => int )`.
	 */
	public static function get_for_doctor( $doctor_id ) {
		$decoded = json_decode( (string) get_user_meta( $doctor_id, self::META_KEY, true ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Validates and sanitizes the `award_title[]`/`award_year[]` parallel
	 * arrays a request posts (one pair per repeatable row in the awards
	 * editor). Blank trailing rows (both fields empty) are silently
	 * dropped; a row with only one of the two fields filled in is a real
	 * error.
	 *
	 * @param array $errors Reference to the accumulating error list.
	 * @return array List of `array( 'title' => string, 'year' => int )`.
	 */
	public static function sanitize_from_request( array &$errors ) {
		$titles = isset( $_POST['award_title'] ) && is_array( $_POST['award_title'] ) ? wp_unslash( $_POST['award_title'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-item below.
		$years  = isset( $_POST['award_year'] ) && is_array( $_POST['award_year'] ) ? wp_unslash( $_POST['award_year'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-item below.

		$max_year = (int) gmdate( 'Y' ) + 1;
		$awards   = array();

		for ( $i = 0; $i < max( count( $titles ), count( $years ) ); $i++ ) {
			$title = isset( $titles[ $i ] ) ? sanitize_text_field( $titles[ $i ] ) : '';
			$year  = isset( $years[ $i ] ) ? sanitize_text_field( $years[ $i ] ) : '';

			if ( '' === $title && '' === $year ) {
				continue;
			}

			if ( '' === $title || '' === $year || ! ctype_digit( $year ) || (int) $year < self::MIN_YEAR || (int) $year > $max_year ) {
				$errors['awards'] = __( 'Please provide both a title and a valid year for each award, or remove the incomplete row.', 'doctor-ak-portal' );
				continue;
			}

			$awards[] = array(
				'title' => $title,
				'year'  => (int) $year,
			);
		}

		return $awards;
	}

	/**
	 * JSON-encodes an award list for storage.
	 *
	 * @param array $awards List of `array( 'title', 'year' )`.
	 * @return string
	 */
	public static function encode( array $awards ) {
		return wp_json_encode( $awards );
	}
}
