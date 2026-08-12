<?php
/**
 * AJAX handlers backing the admin dashboard's "Medicines" section (and, if
 * ever exposed on the doctor dashboard, a doctor's own list).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Medicines;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Medicine_Handler
 *
 * Doctors manage only their own medicines (ownership enforced on every
 * request); administrators (or a Receptionist with doctor_ak_manage_services)
 * can add/edit/delete any doctor's medicine from the admin "Medicines"
 * section, reusing the exact same save/delete logic. Mirrors
 * Service_Handler's dual doctor/admin endpoint pattern.
 */
class Medicine_Handler {

	/**
	 * Nonce action for the doctor-facing Medicines list, if ever exposed.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_medicines';

	/**
	 * AJAX handler: creates/updates a medicine belonging to the logged-in doctor.
	 *
	 * @return void
	 */
	public function handle_save_medicine() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		$this->process_save( get_current_user_id(), get_current_user_id() );
	}

	/**
	 * AJAX handler: deletes a medicine belonging to the logged-in doctor.
	 *
	 * @return void
	 */
	public function handle_delete_medicine() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		$this->process_delete( get_current_user_id() );
	}

	/**
	 * AJAX handler: admin (or a Receptionist with doctor_ak_manage_services)
	 * creates/updates a medicine for any doctor.
	 *
	 * @return void
	 */
	public function handle_admin_save_medicine() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_services' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( wp_unslash( $_POST['doctor_id'] ) ) : 0;

		if ( $doctor_id <= 0 || ! get_userdata( $doctor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That doctor no longer exists.', 'doctor-ak-portal' ) ) );
		}

		$this->process_save( $doctor_id, null );
	}

	/**
	 * AJAX handler: admin (or a Receptionist with doctor_ak_manage_services)
	 * deletes any doctor's medicine.
	 *
	 * @return void
	 */
	public function handle_admin_delete_medicine() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_services' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$this->process_delete( null );
	}

	/**
	 * Shared save logic for both the doctor-facing and admin-facing endpoints.
	 *
	 * @param int      $owner_doctor_id Doctor ID the medicine is created under (for new medicines).
	 * @param int|null $ownership_check Doctor ID an existing medicine must belong to, or null to skip the check (admin context).
	 * @return void
	 */
	private function process_save( $owner_doctor_id, $ownership_check ) {
		$medicine_id = isset( $_POST['medicine_id'] ) ? absint( wp_unslash( $_POST['medicine_id'] ) ) : 0;

		if ( $medicine_id > 0 ) {
			$existing = Medicines::find( $medicine_id );

			if ( ! $existing || ( null !== $ownership_check && (int) $existing['doctor_id'] !== (int) $ownership_check ) ) {
				wp_send_json_error( array( 'message' => __( 'That medicine no longer exists.', 'doctor-ak-portal' ) ) );
			}
		}

		$fields = Medicines::sanitize_fields_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Medicines::sanitize_fields_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			wp_send_json_error( array( 'errors' => array( 'name' => $fields->get_error_message() ) ) );
		}

		if ( $medicine_id > 0 ) {
			$saved = Medicines::update( $medicine_id, $fields, $ownership_check );
		} else {
			$saved = Medicines::create( $owner_doctor_id, $fields );
		}

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'The medicine could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Medicine saved successfully.', 'doctor-ak-portal' ),
				'medicine_id' => $medicine_id > 0 ? $medicine_id : $saved,
			)
		);
	}

	/**
	 * Shared delete logic for both the doctor-facing and admin-facing endpoints.
	 *
	 * @param int|null $ownership_check Doctor ID the medicine must belong to, or null to skip the check (admin context).
	 * @return void
	 */
	private function process_delete( $ownership_check ) {
		$medicine_id = isset( $_POST['medicine_id'] ) ? absint( wp_unslash( $_POST['medicine_id'] ) ) : 0;
		$existing    = $medicine_id > 0 ? Medicines::find( $medicine_id ) : null;

		if ( ! $existing || ( null !== $ownership_check && (int) $existing['doctor_id'] !== (int) $ownership_check ) ) {
			wp_send_json_error( array( 'message' => __( 'That medicine no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Medicines::delete( $medicine_id, $ownership_check ) ) {
			wp_send_json_error( array( 'message' => __( 'The medicine could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Medicine deleted.', 'doctor-ak-portal' ) ) );
	}
}
