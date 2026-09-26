<?php
/**
 * AJAX handler for submitting a doctor review.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Doctor_Reviews;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Review_Handler
 */
class Doctor_Review_Handler {

	const NONCE_ACTION = 'doctor_ak_submit_review';

	/**
	 * Saves the logged-in patient's review.
	 *
	 * @return void
	 */
	public function handle_submit() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$patient_id = get_current_user_id();
		$doctor_id  = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		$rating     = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
		$comment    = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';

		$doctor = $doctor_id ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'That doctor could not be found.', 'doctor-ak-portal' ) ) );
		}

		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a star rating.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Doctor_Reviews::can_review( $patient_id, $doctor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You can review a doctor after a completed appointment with them.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! Doctor_Reviews::save( $patient_id, $doctor_id, $rating, $comment ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save your review. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Thank you for your review!', 'doctor-ak-portal' ) ) );
	}
}
