<?php
/**
 * AJAX handlers backing the admin dashboard's "Service Portfolio" section —
 * the public-facing service catalog (see Service_Catalog), distinct from
 * Service_Handler (the per-doctor bookable line-items used at checkout).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Profile_Picture_Uploader;
use DoctorAKPortal\Includes\Service_Catalog;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Catalog_Handler
 *
 * Administrator-only — the portfolio is a marketing/content feature, not a
 * front-desk task, so (unlike the Doctors/Patients tables) it isn't opened
 * up to a Receptionist via `doctor_ak_manage_services` or similar.
 */
class Service_Catalog_Handler {

	/**
	 * Image upload service (reused as-is from doctor profile-picture
	 * uploads — the validation/Media-Library logic is identical, just for
	 * a different attachment).
	 *
	 * @var Profile_Picture_Uploader
	 */
	private $image_uploader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Profile_Picture_Uploader $image_uploader Image upload service.
	 */
	public function __construct( Profile_Picture_Uploader $image_uploader ) {
		$this->image_uploader = $image_uploader;
	}

	/**
	 * AJAX handler: creates/updates a service-catalog entry. Accepts an
	 * optional `image` file upload (multipart form data); when omitted on
	 * an edit, the existing image (identified by the posted `image_id`,
	 * which the admin JS keeps in sync with whatever the last successful
	 * upload/edit returned) is kept as-is.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		if ( $id > 0 && ! Service_Catalog::find( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That service no longer exists.', 'doctor-ak-portal' ) ) );
		}

		$fields = Service_Catalog::sanitize_fields_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Service_Catalog::sanitize_fields_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			$field_by_error_code = array(
				'doctor_ak_service_catalog_name_required'  => 'name',
				'doctor_ak_service_catalog_price_invalid'  => 'price',
			);
			$field = isset( $field_by_error_code[ $fields->get_error_code() ] ) ? $field_by_error_code[ $fields->get_error_code() ] : 'name';

			wp_send_json_error( array( 'errors' => array( $field => $fields->get_error_message() ) ) );
		}

		$image_id = isset( $_POST['image_id'] ) ? absint( wp_unslash( $_POST['image_id'] ) ) : 0;

		if ( ! empty( $_FILES['image'] ) && UPLOAD_ERR_NO_FILE !== ( $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$uploaded_image_id = $this->image_uploader->upload( $_FILES['image'], get_current_user_id() );

			if ( is_wp_error( $uploaded_image_id ) ) {
				wp_send_json_error( array( 'errors' => array( 'image' => $uploaded_image_id->get_error_message() ) ) );
			}

			$image_id = $uploaded_image_id;
		}

		if ( $id > 0 ) {
			$saved = Service_Catalog::update( $id, $fields, $image_id );
		} else {
			$saved = Service_Catalog::create( $fields, $image_id );
		}

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'The service could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Service saved successfully.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: deletes a service-catalog entry.
	 *
	 * @return void
	 */
	public function handle_delete() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		if ( $id <= 0 || ! Service_Catalog::find( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That service no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Service_Catalog::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'The service could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Service deleted.', 'doctor-ak-portal' ) ) );
	}
}
