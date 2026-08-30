<?php
/**
 * AJAX handlers backing the doctor dashboard's appointment actions: Mark as
 * Completed, Mark Paid, Pay Now, Reschedule, Cancel.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Swich_Payment;

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

		$result = Appointments::mark_completed( $appointment_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Appointment marked as completed.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: marks an appointment belonging to the logged-in doctor
	 * as paid — lets a doctor who collected payment directly (e.g. cash at
	 * the clinic) record it themselves, the same "Mark Paid" action admin
	 * has (see Appointment_Handler::handle_mark_paid()), scoped to their
	 * own appointments only.
	 *
	 * @return void
	 */
	public function handle_mark_paid() {
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

		if ( Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'] ) {
			wp_send_json_error( array( 'message' => __( 'This appointment is already marked paid.', 'doctor-ak-portal' ) ) );
		}

		Appointments::mark_paid( $appointment_id );

		wp_send_json_success( array( 'message' => __( 'Payment marked as received.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: returns the real Swich payment-gateway URL for an
	 * online-mode, pending-payment appointment belonging to the logged-in
	 * doctor — the same URL the patient's own "Pay Now" uses (see
	 * Patient_Appointment_Handler::handle_pay_now()), for the doctor to
	 * complete on the patient's behalf while they're at the clinic.
	 *
	 * @return void
	 */
	public function handle_pay_now() {
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

	/**
	 * AJAX handler: cancels an appointment belonging to the logged-in doctor.
	 *
	 * @return void
	 */
	public function handle_cancel_appointment() {
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
		$result         = Appointments::cancel_by_doctor( $appointment_id, get_current_user_id() );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'That appointment could not be found or cancelled.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Appointment cancelled.', 'doctor-ak-portal' ) ) );
	}
}
