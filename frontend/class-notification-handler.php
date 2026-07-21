<?php
/**
 * AJAX handlers backing every dashboard's "Notifications" tab — shared by
 * the doctor, patient, and admin dashboards since they all read/write the
 * same table, ownership-checked to the logged-in user either way.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Notification_Center;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notification_Handler
 */
class Notification_Handler {

	/**
	 * Nonce action shared with every dashboard's notifications JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_notifications';

	/**
	 * AJAX handler: marks one notification read.
	 *
	 * @return void
	 */
	public function handle_mark_read() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'doctor-ak-portal' ) ), 401 );
		}

		$notification_id = isset( $_POST['notification_id'] ) ? absint( wp_unslash( $_POST['notification_id'] ) ) : 0;

		Notification_Center::mark_read( $notification_id, get_current_user_id() );

		wp_send_json_success();
	}

	/**
	 * AJAX handler: marks every one of the logged-in user's notifications read.
	 *
	 * @return void
	 */
	public function handle_mark_all_read() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'doctor-ak-portal' ) ), 401 );
		}

		Notification_Center::mark_all_read( get_current_user_id() );

		wp_send_json_success();
	}
}
