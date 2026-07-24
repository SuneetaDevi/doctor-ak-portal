<?php
/**
 * Handles the admin dashboard's "Doctor Requests" tab actions.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Requests_Handler
 *
 * Approve/reject actions for pending doctor registrations (see
 * Registration_Handler::handle_register(), which sets a new doctor's
 * 'doctor_ak_registration_status' user meta to 'pending' instead of
 * activating them immediately). Gated by the same 'manage_options'
 * capability and Admin_Dashboard::NONCE_ACTION nonce every other admin
 * dashboard AJAX handler uses.
 */
class Doctor_Requests_Handler {

	/**
	 * AJAX handler: approves a pending doctor account.
	 *
	 * @return void
	 */
	public function handle_approve() {
		$doctor = $this->authorized_pending_doctor();

		update_user_meta( $doctor->ID, 'doctor_ak_registration_status', 'approved' );

		/**
		 * Fires once an admin approves a pending doctor account.
		 * Notifications/Notification_Center hook this to alert the doctor.
		 *
		 * @param int $doctor_id Approved doctor's user ID.
		 */
		do_action( 'doctor_ak_doctor_approved', $doctor->ID );

		wp_send_json_success( array( 'message' => __( 'Doctor approved.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: rejects a pending doctor account. The account isn't
	 * deleted (it may hold uploaded documents/meta worth keeping for
	 * reference) — it's just permanently blocked from logging in.
	 *
	 * @return void
	 */
	public function handle_reject() {
		$doctor = $this->authorized_pending_doctor();

		update_user_meta( $doctor->ID, 'doctor_ak_registration_status', 'rejected' );

		wp_send_json_success( array( 'message' => __( 'Doctor request rejected.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Shared nonce/capability/ownership checks for both actions above.
	 * Ends the request with a JSON error if any check fails.
	 *
	 * @return \WP_User The pending doctor, once every check has passed.
	 */
	private function authorized_pending_doctor() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$doctor    = $doctor_id > 0 ? get_user_by( 'id', $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'That account no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( 'pending' !== get_user_meta( $doctor->ID, 'doctor_ak_registration_status', true ) ) {
			wp_send_json_error( array( 'message' => __( 'That request has already been handled.', 'doctor-ak-portal' ) ) );
		}

		return $doctor;
	}
}
