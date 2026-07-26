<?php
/**
 * AJAX handler backing the doctor dashboard's "+ Add Patient" modal.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Authentication;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Patient_Handler
 *
 * Lets a logged-in doctor create a new patient account directly from their
 * dashboard — e.g. for a walk-in patient who hasn't self-registered.
 * Deliberately add-only (no edit/delete): patients aren't "owned" by a
 * doctor, and editing/deactivating existing accounts stays an admin-only
 * action (see Admin_User_Handler) to avoid one doctor changing another
 * doctor's patient's details.
 */
class Doctor_Patient_Handler {

	/**
	 * Nonce action for the doctor dashboard's "Add Patient" modal.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_doctor_add_patient';

	/**
	 * AJAX handler: creates a new patient account.
	 *
	 * @return void
	 */
	public function handle_add_patient() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		$errors = array();

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone      = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';

		if ( '' === $first_name ) {
			$errors['first_name'] = __( 'First name is required.', 'doctor-ak-portal' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$errors['email'] = __( 'Please provide a valid email address.', 'doctor-ak-portal' );
		} elseif ( email_exists( $email ) ) {
			$errors['email'] = __( 'An account with that email address already exists.', 'doctor-ak-portal' );
		}

		if ( '' === $phone ) {
			$errors['phone_number'] = __( 'Phone number is required.', 'doctor-ak-portal' );
		} elseif ( ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $phone ) ) {
			$errors['phone_number'] = __( 'Please provide a valid phone number.', 'doctor-ak-portal' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		$user_login = self::unique_username_from_email( $email );
		$password   = wp_generate_password( 20, true, true );

		$authentication = new Authentication();
		$patient_id      = $authentication->register_user(
			array(
				'user_login'   => $user_login,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
			),
			Roles::PATIENT_ROLE
		);

		if ( is_wp_error( $patient_id ) ) {
			wp_send_json_error( array( 'message' => $patient_id->get_error_message() ) );
		}

		update_user_meta( $patient_id, 'doctor_ak_phone_number', $phone );

		wp_new_user_notification( $patient_id, null, 'user' );

		wp_send_json_success( array( 'message' => __( 'Patient added successfully.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Derives a unique WordPress username from an email address's local
	 * part, matching Admin_User_Handler's own copy of this logic.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function unique_username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );

		if ( '' === $base ) {
			$base = 'user';
		}

		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			++$suffix;
			$username = $base . $suffix;
		}

		return $username;
	}
}
