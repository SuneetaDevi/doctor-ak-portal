<?php
/**
 * AJAX handlers backing the admin dashboard's "Appointments" section:
 * add/edit/delete, plus a printable appointment slip.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Template_Loader;

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
	 * AJAX handler: admin creates/updates an appointment.
	 *
	 * @return void
	 */
	public function handle_admin_save_appointment() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
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
			'status'         => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : Appointments::STATUS_PENDING,
			'payment_status' => isset( $_POST['payment_status'] ) ? sanitize_key( wp_unslash( $_POST['payment_status'] ) ) : Appointments::PAYMENT_STATUS_PENDING,
			'payment_mode'   => isset( $_POST['payment_mode'] ) ? sanitize_key( wp_unslash( $_POST['payment_mode'] ) ) : Appointments::PAYMENT_MODE_MANUAL,
		);

		if ( $appointment_id > 0 ) {
			$result = Appointments::update( $appointment_id, $data );
		} else {
			$result = Appointments::create( $data );
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
	 * AJAX handler: admin deletes an appointment.
	 *
	 * @return void
	 */
	public function handle_admin_delete_appointment() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
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
	 * AJAX handler (GET): renders a minimal printer-friendly appointment
	 * slip in a new tab, which the admin table's "Print" button opens.
	 *
	 * @return void
	 */
	public function handle_print() {
		if ( ! current_user_can( 'manage_options' ) ) {
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
		$patient_name = $appointment['patient_id'] > 0 ? self::patient_name_for_print( $appointment['patient_id'] ) : $appointment['guest_name'];

		$template_loader = new Template_Loader();

		echo $template_loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
			'print/appointment-slip.php',
			array(
				'appointment'  => $appointment,
				'patient_name' => '' !== $patient_name ? $patient_name : __( 'Guest', 'doctor-ak-portal' ),
				'doctor_name'  => '' !== $doctor_name ? $doctor_name : ( $doctor ? $doctor->display_name : __( 'Unknown Doctor', 'doctor-ak-portal' ) ),
			)
		);

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
