<?php
/**
 * PMDC (Pakistan Medical & Dental Council) registration numbers for doctors.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pmdc
 *
 * Every doctor registering gives their PMDC registration number. It must be
 * verified before the account can be approved (Doctor_Requests_Handler),
 * and doctors can't log in until approved (Authentication::login()) — so a
 * doctor only gets in once their number has been verified.
 *
 * How it's verified:
 * - PMDC publishes no API for third parties (only the "Search for Doctors
 *   Online" form on pmdc.pk), so by default an administrator checks the
 *   number there — name, status and licence validity — and clicks
 *   "Mark verified" in Doctor Requests. Who verified it, and when, is kept.
 * - If an automatic verifier becomes available (an official PMDC API, or an
 *   approved third-party service), hook it into the
 *   `doctor_ak_pmdc_verify` filter. It then runs at registration: a number
 *   it rejects stops the registration, one it confirms is marked verified.
 */
class Pmdc {

	const META_NUMBER      = 'doctor_ak_pmdc_number';
	const META_STATUS      = 'doctor_ak_pmdc_status';
	const META_VERIFIED_BY = 'doctor_ak_pmdc_verified_by';
	const META_VERIFIED_AT = 'doctor_ak_pmdc_verified_at';
	const META_METHOD      = 'doctor_ak_pmdc_verification_method';

	const STATUS_UNVERIFIED = 'unverified';
	const STATUS_VERIFIED   = 'verified';

	/** PMDC's public "Search for Doctors Online" page. */
	const LOOKUP_URL = 'https://pmdc.pk/';

	/**
	 * Comparable form of a number: upper case, no spaces.
	 *
	 * @param string $raw Typed number.
	 * @return string
	 */
	public static function normalize( $raw ) {
		return strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( (string) $raw ) ) );
	}

	/**
	 * Checks a typed number: present, plausible, and not already used by
	 * another account. PMDC doesn't publish a single number format (older
	 * numbers carry suffixes such as "-P" or "-D"), so this only rejects
	 * what clearly can't be one; the real check is the verification step.
	 *
	 * @param string $raw        Typed number.
	 * @param int    $ignore_uid User whose own number doesn't count as a duplicate.
	 * @return string|\WP_Error Normalized number, or the reason it's refused.
	 */
	public static function validate( $raw, $ignore_uid = 0 ) {
		$number = self::normalize( $raw );

		if ( '' === $number ) {
			return new \WP_Error( 'doctor_ak_pmdc_required', __( 'Please enter your PMDC registration number.', 'doctor-ak-portal' ) );
		}

		if ( ! preg_match( '/^[A-Z0-9][A-Z0-9\-\/]{2,19}$/', $number ) || ! preg_match( '/\d/', $number ) ) {
			return new \WP_Error( 'doctor_ak_pmdc_format', __( 'That doesn’t look like a PMDC registration number. Enter it exactly as it appears on your PMDC certificate, e.g. 12345-P.', 'doctor-ak-portal' ) );
		}

		$existing = self::find_user_by_number( $number );

		if ( $existing && (int) $existing !== (int) $ignore_uid ) {
			return new \WP_Error( 'doctor_ak_pmdc_taken', __( 'An account is already registered with this PMDC number. If it’s yours, please log in or contact us.', 'doctor-ak-portal' ) );
		}

		return $number;
	}

	/**
	 * The user (if any) already holding a number.
	 *
	 * @param string $number Normalized number.
	 * @return int User ID, or 0.
	 */
	public static function find_user_by_number( $number ) {
		$users = get_users(
			array(
				'meta_key'   => self::META_NUMBER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		return empty( $users ) ? 0 : (int) $users[0];
	}

	/**
	 * Runs the automatic verifier, if one is configured.
	 *
	 * @param string $number  Normalized number.
	 * @param array  $context { first_name, last_name, email } — for verifiers that match the name.
	 * @return true|null|\WP_Error true = confirmed, null = no verifier (manual check), WP_Error = rejected.
	 */
	public static function verify_automatically( $number, array $context = array() ) {
		/**
		 * Filters the result of verifying a PMDC registration number.
		 *
		 * Return true when the number is confirmed valid (and matches the
		 * doctor), a WP_Error explaining why it was rejected, or leave null
		 * when you can't tell (e.g. the service is down) — the number then
		 * goes to an administrator to check by hand.
		 *
		 * @param true|null|\WP_Error $result  Default null (no verifier).
		 * @param string              $number  Normalized PMDC number.
		 * @param array               $context { first_name, last_name, email }.
		 */
		$result = apply_filters( 'doctor_ak_pmdc_verify', null, $number, $context );

		if ( true === $result || null === $result ) {
			return $result;
		}

		return is_wp_error( $result ) ? $result : new \WP_Error( 'doctor_ak_pmdc_not_verified', __( 'We couldn’t verify this PMDC registration number. Please check it and try again.', 'doctor-ak-portal' ) );
	}

	/**
	 * Stores a new doctor's number and its verification state.
	 *
	 * @param int       $user_id  Doctor's user ID.
	 * @param string    $number   Normalized number.
	 * @param true|null $verified Result of verify_automatically().
	 * @return void
	 */
	public static function save_for_new_doctor( $user_id, $number, $verified ) {
		update_user_meta( $user_id, self::META_NUMBER, $number );

		if ( true === $verified ) {
			self::record_verified( $user_id, 0, 'automatic' );
		} else {
			update_user_meta( $user_id, self::META_STATUS, self::STATUS_UNVERIFIED );
		}
	}

	/**
	 * Marks a doctor's number verified.
	 *
	 * @param int    $user_id  Doctor's user ID.
	 * @param int    $admin_id Who checked it (0 for an automatic check).
	 * @param string $method   'manual' or 'automatic'.
	 * @return void
	 */
	public static function record_verified( $user_id, $admin_id, $method = 'manual' ) {
		update_user_meta( $user_id, self::META_STATUS, self::STATUS_VERIFIED );
		update_user_meta( $user_id, self::META_VERIFIED_BY, (int) $admin_id );
		update_user_meta( $user_id, self::META_VERIFIED_AT, current_time( 'mysql' ) );
		update_user_meta( $user_id, self::META_METHOD, 'automatic' === $method ? 'automatic' : 'manual' );
	}

	/**
	 * A doctor's PMDC details for display.
	 *
	 * @param int $user_id Doctor's user ID.
	 * @return array { number, verified: bool, verified_at, verified_by_name, method }
	 */
	public static function for_doctor( $user_id ) {
		$by   = (int) get_user_meta( $user_id, self::META_VERIFIED_BY, true );
		$user = $by ? get_userdata( $by ) : false;

		return array(
			'number'           => (string) get_user_meta( $user_id, self::META_NUMBER, true ),
			'verified'         => self::STATUS_VERIFIED === get_user_meta( $user_id, self::META_STATUS, true ),
			'verified_at'      => (string) get_user_meta( $user_id, self::META_VERIFIED_AT, true ),
			'verified_by_name' => $user ? $user->display_name : '',
			'method'           => (string) get_user_meta( $user_id, self::META_METHOD, true ),
		);
	}
}
