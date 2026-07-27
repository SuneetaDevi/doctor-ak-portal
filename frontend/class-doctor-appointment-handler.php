<?php
/**
 * AJAX handler backing the doctor dashboard's "Mark as Completed" appointment
 * action.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Appointment_Handler
 *
 * Ownership-checked against the logged-in doctor — a doctor can only
 * complete their own appointments, never anyone else's, regardless of what
 * appointment_id is posted (see Appointments::mark_completed()).
 */
class Doctor_Appointment_Handler {

	/**
	 * Nonce action shared with the doctor dashboard's JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_doctor_appointments';

	/**
	 * AJAX handler: marks an appointment belonging to the logged-in doctor
	 * as completed.
	 *
	 * @return void
	 */
	public function handle_mark_completed() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'appointments' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Appointments page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;

		if ( ! Appointments::mark_completed( $appointment_id, get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'That appointment could not be found or marked completed.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Appointment marked as completed.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: reschedules an appointment belonging to the logged-in
	 * doctor to a new date/time.
	 *
	 * @return void
	 */
	public function handle_reschedule() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'appointments' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Appointments page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id > 0 ? Appointments::find( $appointment_id ) : array();

		if ( empty( $appointment ) || (int) $appointment['doctor_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'That appointment could not be found.', 'doctor-ak-portal' ) ) );
		}

		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';

		$result = Appointments::reschedule( $appointment_id, $date, $time );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Appointment rescheduled.', 'doctor-ak-portal' ) ) );
	}
}
