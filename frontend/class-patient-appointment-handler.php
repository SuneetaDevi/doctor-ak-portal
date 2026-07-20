<?php
/**
 * AJAX handlers backing the patient dashboard's "Pay Now" and "Cancel"
 * appointment actions.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Swich_Payment;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Patient_Appointment_Handler
 *
 * Both actions are ownership-checked against the logged-in patient — a
 * patient can only pay for or cancel their own appointments, never anyone
 * else's, regardless of what appointment_id is posted.
 */
class Patient_Appointment_Handler {

	/**
	 * Nonce action shared with the patient dashboard's JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_patient_dashboard';

	/**
	 * AJAX handler: cancels an appointment belonging to the logged-in patient.
	 *
	 * @return void
	 */
	public function handle_cancel_appointment() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::PATIENT_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a patient.', 'doctor-ak-portal' ) ), 401 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$result         = Appointments::cancel( $appointment_id, get_current_user_id() );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'That appointment could not be found or cancelled.', 'doctor-ak-portal' ) ) );
		}

		$message = $result['refund_eligible']
			? __( "Appointment cancelled. You're within the refund window, so our team will process your refund shortly.", 'doctor-ak-portal' )
			: __( "Appointment cancelled. This was after the doctor's refund window, so no refund applies.", 'doctor-ak-portal' );

		wp_send_json_success( array( 'message' => $message, 'refund_eligible' => $result['refund_eligible'] ) );
	}

	/**
	 * AJAX handler: resumes payment for a pending-payment appointment
	 * belonging to the logged-in patient, returning the Swich payment URL
	 * for the JS to redirect to.
	 *
	 * @return void
	 */
	public function handle_pay_now() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::PATIENT_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a patient.', 'doctor-ak-portal' ) ), 401 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id > 0 ? Appointments::find( $appointment_id ) : array();

		if ( empty( $appointment ) || (int) $appointment['patient_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'That appointment could not be found.', 'doctor-ak-portal' ) ) );
		}

		if ( Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'] ) {
			wp_send_json_error( array( 'message' => __( 'This appointment is already paid.', 'doctor-ak-portal' ) ) );
		}

		if ( (float) $appointment['charge'] <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'There is nothing to pay for this appointment.', 'doctor-ak-portal' ) ) );
		}

		$payment_url = Swich_Payment::build_payment_url( $appointment_id );

		if ( is_wp_error( $payment_url ) ) {
			wp_send_json_error( array( 'message' => $payment_url->get_error_message() ) );
		}

		wp_send_json_success( array( 'payment_url' => $payment_url ) );
	}
}
