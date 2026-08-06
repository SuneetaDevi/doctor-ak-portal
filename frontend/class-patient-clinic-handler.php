<?php
/**
 * AJAX handler backing the patient's mandatory "select your clinic" screen.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Patient_Clinic_Handler
 *
 * Lets a logged-in patient set their single "home clinic" (Clinic_Locations
 * ::PATIENT_META_KEY) — shown once, right after registration/login, before
 * they can reach the rest of their dashboard (see
 * Patient_Dashboard::render()). Purely an association for
 * branding/directory purposes; never touched by, or required for, booking a
 * video consultation.
 */
class Patient_Clinic_Handler {

	/**
	 * Nonce action for the patient's clinic-selection screen.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_patient_select_clinic';

	/**
	 * AJAX handler: saves the logged-in patient's chosen home clinic.
	 *
	 * @return void
	 */
	public function handle_select_clinic() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::PATIENT_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a patient.', 'doctor-ak-portal' ) ), 401 );
		}

		$clinic_location_id = isset( $_POST['clinic_location_id'] ) ? absint( wp_unslash( $_POST['clinic_location_id'] ) ) : 0;

		if ( $clinic_location_id <= 0 || ! Clinic_Locations::find( $clinic_location_id ) ) {
			wp_send_json_error( array( 'errors' => array( 'clinic_location_id' => __( 'Please select a clinic.', 'doctor-ak-portal' ) ) ) );
		}

		update_user_meta( get_current_user_id(), Clinic_Locations::PATIENT_META_KEY, $clinic_location_id );

		wp_send_json_success( array( 'message' => __( 'Clinic saved.', 'doctor-ak-portal' ) ) );
	}
}
