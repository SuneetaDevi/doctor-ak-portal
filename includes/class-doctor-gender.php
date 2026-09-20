<?php
/**
 * Doctor gender — a simple fixed pair (male/female), stored per doctor and
 * used by the public Doctors directory's "Male Doctor" / "Female Doctor"
 * filter chips.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Gender
 */
class Doctor_Gender {

	/**
	 * User meta key the gender slug is stored under.
	 *
	 * @var string
	 */
	const META_KEY = 'doctor_ak_gender';

	/**
	 * The selectable genders, slug => label.
	 *
	 * @return array
	 */
	public static function get_all() {
		return array(
			'male'   => __( 'Male', 'doctor-ak-portal' ),
			'female' => __( 'Female', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Reads and validates the posted `gender` field.
	 *
	 * @return string A valid slug, or '' when nothing (or something unrecognised) was submitted.
	 */
	public static function sanitize_from_request() {
		$raw = isset( $_POST['gender'] ) ? sanitize_key( wp_unslash( $_POST['gender'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify their own nonce.

		return array_key_exists( $raw, self::get_all() ) ? $raw : '';
	}
}
