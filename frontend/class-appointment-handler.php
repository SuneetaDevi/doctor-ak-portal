<?php
/**
 * AJAX handlers backing the admin dashboard's "Appointments" section:
 * add/edit/delete, plus a printable appointment slip.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointment_Slip_Pdf;
use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Invoice_Pdf;
use DoctorAKPortal\Includes\Notification_Center;
use DoctorAKPortal\Includes\Swich_Payment;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Appointment_Handler
 *
 * Admin-only: the patient-facing booking flow has its own endpoint
 * (Booking_Handler). Here an administrator can create/edit any appointment
 * end to end (doctor, patient/guest, service, type, date/time, status,
 * payment), delete one, or open a printer-friendly slip for one.
 */
class Appointment_Handler {

	/**
	 * Nonce action shared with the print endpoint's link (built server-side,
	 * so it doesn't need the admin dashboard's JS-localized nonce).
	 *
	 * @var string
	 */
	const PRINT_NONCE_ACTION = 'doctor_ak_admin_appointment_print';

	/**
	 * Nonce action shared with the Billing section's "Download" link.
	 *
	 * @var string
	 */
	const INVOICE_NONCE_ACTION = 'doctor_ak_admin_invoice_download';

	/**
	 * AJAX handler: admin (or a Receptionist with doctor_ak_manage_appointments)
	 * creates/updates an appointment.
	 *
	 * @return void
	 */
	public function handle_admin_save_appointment() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_appointments' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;

		$data = array(
			'doctor_id'      => isset( $_POST['doctor_id'] ) ? absint( wp_unslash( $_POST['doctor_id'] ) ) : 0,
			'patient_id'     => isset( $_POST['patient_id'] ) ? absint( wp_unslash( $_POST['patient_id'] ) ) : 0,
			'guest_name'     => isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '',
			'guest_email'    => isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '',
			'guest_phone'    => isset( $_POST['guest_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_phone'] ) ) : '',
			'type'           => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : Appointments::TYPE_CLINIC,
			'date'           => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
			'time'           => isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '',
			'notes'          => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'service_id'     => isset( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0,
			'status'         => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : Appointments::STATUS_CONFIRMED,
			'payment_status' => isset( $_POST['payment_status'] ) ? sanitize_key( wp_unslash( $_POST['payment_status'] ) ) : Appointments::PAYMENT_STATUS_PENDING,
			'payment_mode'   => isset( $_POST['payment_mode'] ) ? sanitize_key( wp_unslash( $_POST['payment_mode'] ) ) : Appointments::PAYMENT_MODE_MANUAL,
		);

		if ( $appointment_id > 0 ) {
			$result = Appointments::update( $appointment_id, $data );
		} else {
			// Lets the admin immediately choose Paid (they collected payment
			// themselves) or Pending (the patient will pay later from their
			// own dashboard) when booking on a patient's behalf — see the
			// admin_override branch in Appointments::create().
			$data['admin_override'] = true;
			$result                 = Appointments::create( $data );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'        => __( 'Appointment saved successfully.', 'doctor-ak-portal' ),
				'appointment_id' => $appointment_id > 0 ? $appointment_id : $result,
			)
		);
	}

	/**
	 * AJAX handler: marks an appointment's payment as received — a narrow
	 * "front-desk" action (unlike handle_admin_save_appointment(), it can't
	 * re-target the doctor/patient/date or touch anything except payment
	 * status), so it's also allowed for a Receptionist, not just a full
	 * Administrator.
	 *
	 * @return void
	 */
	public function handle_mark_paid() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_payments' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id > 0 ? Appointments::find( $appointment_id ) : null;

		if ( empty( $appointment ) ) {
			wp_send_json_error( array( 'message' => __( 'That appointment no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'] ) {
			wp_send_json_error( array( 'message' => __( 'This appointment is already marked paid.', 'doctor-ak-portal' ) ) );
		}

		Appointments::mark_paid( $appointment_id );

		wp_send_json_success( array( 'message' => __( 'Payment marked as received.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: sends a patient a one-off in-app reminder about an
	 * upcoming appointment — used by the Appointments list's row action and
	 * bulk "Send reminder" action (the JS calls this once per selected
	 * appointment). Same narrow front-desk audience as handle_mark_paid().
	 *
	 * @return void
	 */
	public function handle_send_reminder() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_payments' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id > 0 ? Appointments::find( $appointment_id ) : null;

		if ( empty( $appointment ) ) {
			wp_send_json_error( array( 'message' => __( 'That appointment no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Notification_Center::notify_reminder( $appointment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This appointment has no registered patient to remind.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Reminder sent.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: returns the real Swich payment-gateway URL for an
	 * online-mode, pending-payment appointment — the same URL a patient
	 * would use from their own dashboard's "Pay Now" (see
	 * Patient_Appointment_Handler::handle_pay_now()), for a Receptionist/
	 * Administrator to complete on the patient's behalf (e.g. over the
	 * phone, or in person at reception). Allowed for the same narrow
	 * "front-desk" audience as handle_mark_paid().
	 *
	 * @return void
	 */
	public function handle_pay_now() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_payments' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id > 0 ? Appointments::find( $appointment_id ) : null;

		if ( empty( $appointment ) ) {
			wp_send_json_error( array( 'message' => __( 'That appointment no longer exists.', 'doctor-ak-portal' ) ) );
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
	 * AJAX handler: admin (or a Receptionist with doctor_ak_manage_appointments)
	 * deletes an appointment.
	 *
	 * @return void
	 */
	public function handle_admin_delete_appointment() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_appointments' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;

		if ( $appointment_id <= 0 || empty( Appointments::find( $appointment_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'That appointment no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Appointments::delete( $appointment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The appointment could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Appointment deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: admin saves a completed appointment's clinical visit
	 * note (the "Encounters" section's log).
	 *
	 * @return void
	 */
	public function handle_admin_save_encounter_note() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$note           = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		$result = Appointments::save_encounter_note( $appointment_id, $note );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Note saved.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: admin processes a patient's refund request — calls
	 * Swich's refund API for the real transaction (POST
	 * /gateway/payin/v2.0/purchase/refund) and, only if that succeeds,
	 * records it via Appointments::mark_refund_processed(). Amount defaults
	 * to the full charge but the admin can send a lower one for a partial
	 * refund, per Swich's API.
	 *
	 * @return void
	 */
	public function handle_admin_process_refund() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id > 0 ? Appointments::find( $appointment_id ) : array();

		if ( empty( $appointment ) ) {
			wp_send_json_error( array( 'message' => __( 'That appointment no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( Appointments::REFUND_STATUS_REQUESTED !== $appointment['refund_status'] ) {
			wp_send_json_error( array( 'message' => __( 'This appointment has no pending refund request.', 'doctor-ak-portal' ) ) );
		}

		$amount = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : (float) $appointment['refund_amount'];

		if ( $amount <= 0 || $amount > (float) $appointment['charge'] ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid refund amount, up to the original charge.', 'doctor-ak-portal' ) ) );
		}

		$order_id = get_post_meta( $appointment_id, Swich_Payment::META_ORDER_ID, true );
		$result   = Swich_Payment::refund( $order_id, $appointment['refund_reason'], $amount );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		Appointments::mark_refund_processed( $appointment_id, $amount );

		wp_send_json_success( array( 'message' => __( 'Refund processed successfully.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Builds the "Print" URL for a single appointment slip.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return string
	 */
	public static function print_url( $appointment_id ) {
		return add_query_arg(
			array(
				'action'         => 'doctor_ak_admin_appointment_print',
				'appointment_id' => (int) $appointment_id,
				'nonce'          => wp_create_nonce( self::PRINT_NONCE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * AJAX handler (GET): streams a single appointment's slip as a PDF (same
	 * structured layout as Invoice_Pdf/Encounter_Bill_Pdf), which the admin
	 * table's "Print" button opens in a new tab.
	 *
	 * @return void
	 */
	public function handle_print() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_appointments' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'doctor-ak-portal' ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::PRINT_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) );
		}

		$appointment_id = isset( $_GET['appointment_id'] ) ? absint( wp_unslash( $_GET['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id ? Appointments::find( $appointment_id ) : array();

		if ( empty( $appointment ) ) {
			wp_die( esc_html__( 'Appointment not found.', 'doctor-ak-portal' ) );
		}

		$doctor       = $appointment['doctor_id'] > 0 ? get_userdata( $appointment['doctor_id'] ) : false;
		$doctor_name  = $doctor ? trim( $doctor->first_name . ' ' . $doctor->last_name ) : '';
		$doctor_name  = '' !== $doctor_name ? $doctor_name : ( $doctor ? $doctor->display_name : __( 'Unknown Doctor', 'doctor-ak-portal' ) );
		$patient_name = $appointment['patient_id'] > 0 ? self::patient_name_for_print( $appointment['patient_id'] ) : $appointment['guest_name'];
		$patient_name = '' !== $patient_name ? $patient_name : __( 'Guest', 'doctor-ak-portal' );

		$pdf_bytes   = Appointment_Slip_Pdf::build( $appointment, $patient_name, $doctor_name );
		$slip_number = sprintf( 'SLIP-%05d', $appointment_id );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $slip_number . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_bytes ) );

		echo $pdf_bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw binary PDF bytes, not HTML output.

		exit;
	}

	/**
	 * Builds the "Download Invoice" URL for a single paid appointment, used
	 * by the admin Billing section.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return string
	 */
	public static function invoice_download_url( $appointment_id ) {
		return add_query_arg(
			array(
				'action'         => 'doctor_ak_admin_invoice_download',
				'appointment_id' => (int) $appointment_id,
				'nonce'          => wp_create_nonce( self::INVOICE_NONCE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * AJAX handler (GET): streams a paid appointment's invoice PDF (the
	 * same one emailed at payment time, see Invoice_Pdf/Notifications::send_invoice())
	 * as a download, for the admin Billing section's "Download" action.
	 *
	 * @return void
	 */
	public function handle_download_invoice() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_payments' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'doctor-ak-portal' ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::INVOICE_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) );
		}

		$appointment_id = isset( $_GET['appointment_id'] ) ? absint( wp_unslash( $_GET['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id ? Appointments::notification_data( $appointment_id ) : array();

		if ( empty( $appointment ) || Appointments::PAYMENT_STATUS_PAID !== $appointment['payment_status'] ) {
			wp_die( esc_html__( 'That invoice could not be found.', 'doctor-ak-portal' ) );
		}

		$pdf_bytes      = Invoice_Pdf::build( $appointment );
		$invoice_number = sprintf( 'INV-%05d', $appointment_id );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $invoice_number . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_bytes ) );

		echo $pdf_bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw binary PDF bytes, not HTML output.

		exit;
	}

	/**
	 * @param int $patient_id Patient's user ID.
	 * @return string
	 */
	private static function patient_name_for_print( $patient_id ) {
		$user = get_userdata( $patient_id );

		if ( ! $user ) {
			return '';
		}

		$name = trim( $user->first_name . ' ' . $user->last_name );

		return '' !== $name ? $name : $user->display_name;
	}
}
