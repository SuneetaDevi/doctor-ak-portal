<?php
/**
 * Handles the global booking modal's AJAX submission.
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
 * Class Booking_Handler
 *
 * Registered for both wp_ajax_doctor_ak_book_appointment and
 * wp_ajax_nopriv_doctor_ak_book_appointment, since guest bookings must work
 * while logged out. When the visitor is logged in as a patient, their
 * identity is always taken from the session — client-submitted name/email
 * for a logged-in patient is never trusted.
 */
class Booking_Handler {

	/**
	 * Nonce action shared with Booking_Modal.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_booking';

	/**
	 * AJAX handler: validates and saves a new appointment request.
	 *
	 * @return void
	 */
	public function handle_book_appointment() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		$type      = ( isset( $_POST['type'] ) && 'video' === $_POST['type'] ) ? 'video' : 'clinic';
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time      = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$notes     = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		$errors = array();

		if ( $doctor_id <= 0 ) {
			$errors['doctor_id'] = __( 'Please choose a doctor.', 'doctor-ak-portal' );
		}

		if ( '' === $date ) {
			$errors['date'] = __( 'Please choose an appointment date.', 'doctor-ak-portal' );
		}

		if ( '' === $time ) {
			$errors['time'] = __( 'Please choose an appointment time.', 'doctor-ak-portal' );
		}

		$current_user         = wp_get_current_user();
		$is_logged_in_patient = is_user_logged_in() && in_array( Roles::PATIENT_ROLE, (array) $current_user->roles, true );

		$patient_id  = 0;
		$guest_name  = '';
		$guest_email = '';
		$guest_phone = '';

		if ( $is_logged_in_patient ) {
			$patient_id = $current_user->ID;
		} else {
			$guest_name  = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '';
			$guest_email = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';
			$guest_phone = isset( $_POST['guest_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_phone'] ) ) : '';

			if ( '' === $guest_name ) {
				$errors['guest_name'] = __( 'Please enter your name.', 'doctor-ak-portal' );
			}

			if ( '' === $guest_email || ! is_email( $guest_email ) ) {
				$errors['guest_email'] = __( 'Please enter a valid email address.', 'doctor-ak-portal' );
			}
		}

		if ( 'video' === $type && empty( $errors ) ) {
			$phone_for_payment = $is_logged_in_patient ? get_user_meta( $current_user->ID, 'doctor_ak_phone_number', true ) : $guest_phone;

			if ( '' === Swich_Payment::normalize_msisdn( $phone_for_payment ) ) {
				if ( $is_logged_in_patient ) {
					wp_send_json_error( array( 'message' => __( 'Please add a valid mobile number (e.g. 03xxxxxxxxx) to your profile before booking an online video consultation.', 'doctor-ak-portal' ) ) );
				}

				$errors['guest_phone'] = __( 'A valid mobile number (e.g. 03xxxxxxxxx) is required to pay for an online video consultation.', 'doctor-ak-portal' );
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		$appointment_id = Appointments::create(
			array(
				'doctor_id'   => $doctor_id,
				'patient_id'  => $patient_id,
				'guest_name'  => $guest_name,
				'guest_email' => $guest_email,
				'guest_phone' => $guest_phone,
				'type'        => $type,
				'date'        => $date,
				'time'        => $time,
				'notes'       => $notes,
			)
		);

		if ( is_wp_error( $appointment_id ) ) {
			wp_send_json_error( array( 'message' => $appointment_id->get_error_message() ) );
		}

		$appointment = Appointments::find( $appointment_id );
		$payment_url = '';

		if ( Appointments::STATUS_PENDING_PAYMENT === $appointment['status'] ) {
			$payment_url = Swich_Payment::build_payment_url( $appointment_id );

			if ( is_wp_error( $payment_url ) ) {
				wp_send_json_error( array( 'message' => $payment_url->get_error_message() ) );
			}
		}

		$message = $is_logged_in_patient
			? __( 'Your appointment request has been received. You can track it from your dashboard.', 'doctor-ak-portal' )
			: __( "Your appointment request has been received. We'll contact you shortly to confirm.", 'doctor-ak-portal' );

		wp_send_json_success(
			array(
				'message'        => $message,
				'appointment_id' => $appointment_id,
				'payment_url'    => $payment_url,
			)
		);
	}
}
