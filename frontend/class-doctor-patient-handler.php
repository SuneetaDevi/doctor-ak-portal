<?php
/**
 * AJAX handlers backing the doctor dashboard's Patients tab and "Add
 * Patient" modal.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Authentication;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Patient_Handler
 *
 * Lets a logged-in doctor create a new patient account directly from their
 * dashboard — e.g. for a walk-in patient who hasn't self-registered —
 * optionally tagging which of the doctor's own clinics they were added at,
 * and edit the basic details of a patient who "belongs" to them
 * (Appointments::is_doctors_patient() — added by them, or has an
 * appointment with them). A doctor can never touch another doctor's
 * patient; editing any other account (deactivating, changing password,
 * etc.) stays admin-only (see Admin_User_Handler).
 */
class Doctor_Patient_Handler {

	/**
	 * Nonce action for the doctor dashboard's Add/Edit Patient modal.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_doctor_add_patient';

	/**
	 * AJAX handler: creates a new patient account, optionally tagged with
	 * one of the doctor's own clinics.
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

		$doctor_id = get_current_user_id();

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'patients' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Patients page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$errors = array();

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone      = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';
		$clinic_id  = isset( $_POST['clinic_id'] ) ? absint( wp_unslash( $_POST['clinic_id'] ) ) : 0;

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

		// Optional — but if given, must actually be one of this doctor's own
		// clinics (never trust a posted ID blindly).
		if ( $clinic_id > 0 && '' === Clinics::clinic_name_for_doctor( $doctor_id, $clinic_id ) ) {
			$errors['clinic_id'] = __( 'Please choose one of your own clinics.', 'doctor-ak-portal' );
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
		update_user_meta( $patient_id, 'doctor_ak_added_by_doctor', $doctor_id );

		if ( $clinic_id > 0 ) {
			update_user_meta( $patient_id, 'doctor_ak_added_by_clinic', $clinic_id );
		}

		wp_new_user_notification( $patient_id, null, 'user' );

		wp_send_json_success( array( 'message' => __( 'Patient added successfully.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: updates the basic details of a patient belonging to the
	 * logged-in doctor (Appointments::is_doctors_patient()). No password/
	 * account-status changes here — those stay admin-only.
	 *
	 * @return void
	 */
	public function handle_edit_patient() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		$doctor_id = get_current_user_id();

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'patients' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Patients page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$patient_id = isset( $_POST['patient_id'] ) ? absint( wp_unslash( $_POST['patient_id'] ) ) : 0;
		$patient    = $patient_id > 0 ? get_userdata( $patient_id ) : false;

		if ( ! $patient || ! in_array( Roles::PATIENT_ROLE, (array) $patient->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'That patient could not be found.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Appointments::is_doctors_patient( $doctor_id, $patient_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You can only edit your own patients.', 'doctor-ak-portal' ) ), 403 );
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
		} elseif ( strtolower( $email ) !== strtolower( $patient->user_email ) && email_exists( $email ) ) {
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

		$updated = wp_update_user(
			array(
				'ID'           => $patient_id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'user_email'   => $email,
			)
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ) );
		}

		update_user_meta( $patient_id, 'doctor_ak_phone_number', $phone );

		wp_send_json_success( array( 'message' => __( 'Patient updated successfully.', 'doctor-ak-portal' ) ) );
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
