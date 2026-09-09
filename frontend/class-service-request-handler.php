<?php
/**
 * AJAX handlers for "book without a doctor" service requests — the public
 * submission (from [service_profile_view], see Service_Profile_View) and
 * the admin dashboard's "Service Requests" section (status updates/delete).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Service_Requests;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Service_Request_Handler {

	/**
	 * Nonce action for the public request form.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_service_request';

	/**
	 * AJAX handler: a patient submits a "book without a doctor" request.
	 *
	 * @return void
	 */
	public function handle_submit() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$fields = Service_Requests::sanitize_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Service_Requests::sanitize_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			$field_by_error_code = array(
				'doctor_ak_service_request_service_invalid' => 'service_id',
				'doctor_ak_service_request_requires_doctor'  => 'service_id',
				'doctor_ak_service_request_name_required'    => 'patient_name',
				'doctor_ak_service_request_phone_invalid'    => 'patient_phone',
				'doctor_ak_service_request_email_invalid'    => 'patient_email',
			);
			$field = isset( $field_by_error_code[ $fields->get_error_code() ] ) ? $field_by_error_code[ $fields->get_error_code() ] : 'patient_name';

			wp_send_json_error( array( 'errors' => array( $field => $fields->get_error_message() ) ) );
		}

		$request_id = Service_Requests::create( $fields, get_current_user_id() );

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Your request could not be submitted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		$this->notify_admin( $fields );

		wp_send_json_success( array( 'message' => __( "Thanks — your request has been received. Our team will contact you shortly to arrange it.", 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: admin/receptionist updates a request's status.
	 *
	 * @return void
	 */
	public function handle_admin_update_status() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_services' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$id     = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( $id <= 0 || ! Service_Requests::find( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That request no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Service_Requests::update_status( $id, $status ) ) {
			wp_send_json_error( array( 'message' => __( 'That status could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Request updated.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: admin/receptionist deletes a request.
	 *
	 * @return void
	 */
	public function handle_admin_delete() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_services' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		if ( $id <= 0 || ! Service_Requests::find( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That request no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Service_Requests::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'The request could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Request deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Emails the site admin about a new request — a simple heads-up so
	 * reception doesn't have to keep the dashboard open to notice one, same
	 * spirit as the rest of this plugin's booking notifications. Failure
	 * here (e.g. no mail transport configured) never blocks the request
	 * itself from being saved — the admin dashboard's "Service Requests"
	 * list is always the source of truth.
	 *
	 * @param array $fields Sanitized fields, see Service_Requests::sanitize_from_request().
	 * @return void
	 */
	private function notify_admin( array $fields ) {
		$to = get_option( 'admin_email' );

		if ( ! $to ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: service name. */
			__( 'New service request: %s', 'doctor-ak-portal' ),
			$fields['service_name']
		);

		$lines = array(
			sprintf( /* translators: %s: service name. */ __( 'Service: %s', 'doctor-ak-portal' ), $fields['service_name'] ),
			sprintf( /* translators: %s: patient name. */ __( 'Name: %s', 'doctor-ak-portal' ), $fields['patient_name'] ),
			sprintf( /* translators: %s: patient phone. */ __( 'Phone: %s', 'doctor-ak-portal' ), $fields['patient_phone'] ),
		);

		if ( '' !== $fields['patient_email'] ) {
			$lines[] = sprintf( /* translators: %s: patient email. */ __( 'Email: %s', 'doctor-ak-portal' ), $fields['patient_email'] );
		}

		if ( '' !== $fields['notes'] ) {
			$lines[] = sprintf( /* translators: %s: patient's notes. */ __( 'Notes: %s', 'doctor-ak-portal' ), $fields['notes'] );
		}

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}
}
