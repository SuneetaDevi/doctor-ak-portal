<?php
/**
 * AJAX handlers backing the admin dashboard's "Clinic" section — the master
 * Clinic_Locations list doctors get aligned to from "Doctor Sessions".
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Clinic_Locations;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clinic_Location_Handler {

	/**
	 * AJAX handler: creates/updates a clinic-location.
	 *
	 * @return void
	 */
	public function handle_admin_save() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_clinics' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		if ( $id > 0 && ! Clinic_Locations::find( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That clinic no longer exists.', 'doctor-ak-portal' ) ) );
		}

		$fields = Clinic_Locations::sanitize_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Clinic_Locations::sanitize_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			$field_by_error_code = array(
				'doctor_ak_clinic_location_name_required'    => 'name',
				'doctor_ak_clinic_location_country_required' => 'country',
				'doctor_ak_clinic_location_city_required'    => 'city',
				'doctor_ak_clinic_location_area_required'    => 'area',
				'doctor_ak_clinic_location_phone_invalid'    => 'phone',
				'doctor_ak_clinic_location_email_invalid'    => 'contact_email',
			);
			$field = isset( $field_by_error_code[ $fields->get_error_code() ] ) ? $field_by_error_code[ $fields->get_error_code() ] : 'name';

			wp_send_json_error( array( 'errors' => array( $field => $fields->get_error_message() ) ) );
		}

		$existing = Clinic_Locations::find_duplicate( $fields, $id );

		if ( $existing ) {
			wp_send_json_error(
				array(
					'errors' => array(
						'name' => sprintf(
							/* translators: %s: name of the clinic that already exists. */
							__( '"%s" already exists in this area. Edit that clinic instead of adding it again.', 'doctor-ak-portal' ),
							$existing['name']
						),
					),
				)
			);
		}

		if ( $id > 0 ) {
			$saved = Clinic_Locations::update( $id, $fields );
		} else {
			$saved = Clinic_Locations::create( $fields );
		}

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'The clinic could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Clinic saved successfully.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: deletes a clinic-location.
	 *
	 * @return void
	 */
	public function handle_admin_delete() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_clinics' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		if ( $id <= 0 || ! Clinic_Locations::find( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That clinic no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Clinic_Locations::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'The clinic could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Clinic deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: merges duplicate clinic-locations into one, see
	 * Clinic_Locations::merge().
	 *
	 * @return void
	 */
	public function handle_admin_merge() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_clinics' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$keep_id   = isset( $_POST['keep_id'] ) ? absint( wp_unslash( $_POST['keep_id'] ) ) : 0;
		$merge_ids = isset( $_POST['merge_ids'] ) && is_array( $_POST['merge_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['merge_ids'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint() applied to each.
			: array();

		$result = Clinic_Locations::merge( $keep_id, $merge_ids );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: number of duplicates removed, 2: doctor schedules moved, 3: services updated, 4: patients moved. */
					__( 'Merged %1$d duplicate(s). Moved %2$d doctor schedule(s), %3$d service price setting(s) and %4$d patient(s) to the kept clinic.', 'doctor-ak-portal' ),
					$result['merged'],
					$result['doctor_schedules'],
					$result['services'],
					$result['patients']
				),
			)
		);
	}
}
