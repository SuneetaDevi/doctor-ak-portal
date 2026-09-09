<?php
/**
 * AJAX handlers backing the admin dashboard's Services -> "Categories" tab.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Service_Categories;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Service_Category_Handler {

	/**
	 * AJAX handler: adds a new service category.
	 *
	 * @return void
	 */
	public function handle_admin_save() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_services' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$slug  = Service_Categories::add( $label );

		if ( is_wp_error( $slug ) ) {
			wp_send_json_error( array( 'errors' => array( 'label' => $slug->get_error_message() ) ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Category added.', 'doctor-ak-portal' ),
				'slug'    => $slug,
				'label'   => $label,
			)
		);
	}

	/**
	 * AJAX handler: deletes a service category.
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

		$slug   = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$result = Service_Categories::delete( $slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Category deleted.', 'doctor-ak-portal' ) ) );
	}
}
