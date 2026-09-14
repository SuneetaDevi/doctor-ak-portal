<?php
/**
 * A patient's date of birth — validation for the field, and the age
 * computed from it for display on appointment listings.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Patient_Age
 *
 * A patient's date of birth is stored as a single 'Y-m-d' string in
 * `META_KEY` user meta — collected at registration (Registration_Handler)
 * and when an admin/receptionist adds or edits a patient (Admin_User_Handler),
 * editable afterward from the patient's own profile (Profile_Handler). Only
 * ever set for a real, registered patient — a guest booking has no user
 * account to store it on, so age_for() returns '' for guests (see
 * Appointments::admin_row_data()/doctor_dashboard_row(), which pass 0 for a
 * guest's patient_id).
 */
class Patient_Age {

	/**
	 * User meta key a patient's date of birth is stored under.
	 *
	 * @var string
	 */
	const META_KEY = 'doctor_ak_date_of_birth';

	/**
	 * Validates and sanitizes a posted date-of-birth string.
	 *
	 * @param string $raw Raw posted value, already unslashed by the caller.
	 * @return string|\WP_Error Sanitized 'Y-m-d' string, or WP_Error on invalid/missing input.
	 */
	public static function sanitize_from_request( $raw ) {
		$value = sanitize_text_field( $raw );

		if ( '' === $value ) {
			return new \WP_Error( 'doctor_ak_dob_required', __( 'Please provide a date of birth.', 'doctor-ak-portal' ) );
		}

		$parsed = \DateTime::createFromFormat( 'Y-m-d', $value );

		// createFromFormat() silently accepts overflow input (e.g.
		// "2024-02-30" quietly becomes March 2nd) — re-formatting the parsed
		// result and comparing it back to what was posted catches that.
		if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $value ) {
			return new \WP_Error( 'doctor_ak_dob_invalid', __( 'Please provide a valid date of birth.', 'doctor-ak-portal' ) );
		}

		if ( $parsed > new \DateTime( current_time( 'Y-m-d' ) ) ) {
			return new \WP_Error( 'doctor_ak_dob_future', __( 'Date of birth cannot be in the future.', 'doctor-ak-portal' ) );
		}

		return $value;
	}

	/**
	 * A patient's current age in whole years, or '' if they have no user
	 * account (a guest booking) or no date of birth on file yet (an account
	 * created before this field existed).
	 *
	 * @param int $patient_id Patient's user ID, or 0 for a guest.
	 * @return int|string
	 */
	public static function age_for( $patient_id ) {
		if ( $patient_id <= 0 ) {
			return '';
		}

		$dob = get_user_meta( $patient_id, self::META_KEY, true );

		if ( '' === $dob ) {
			return '';
		}

		$parsed = \DateTime::createFromFormat( 'Y-m-d', $dob );

		if ( ! $parsed ) {
			return '';
		}

		$today = new \DateTime( current_time( 'Y-m-d' ) );

		return $today->diff( $parsed )->y;
	}
}
