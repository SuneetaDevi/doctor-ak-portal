<?php
/**
 * AJAX handlers backing the admin dashboard's "Blogs" section.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Blogs;
use DoctorAKPortal\Includes\Profile_Picture_Uploader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blog_Handler
 *
 * Blogs are administrator-only content (not delegated to Receptionists, per
 * Admin_Dashboard::RECEPTIONIST_ALLOWED_SECTIONS) — unlike Service_Handler,
 * there's no doctor-facing counterpart, just the one admin save/delete pair,
 * gated by the shared admin dashboard nonce (Admin_Dashboard::NONCE_ACTION).
 */
class Blog_Handler {

	/**
	 * Image upload service, for the "Featured Image" field.
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
	 * AJAX handler: creates/updates a blog post.
	 *
	 * @return void
	 */
	public function handle_admin_save_blog() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$fields = Blogs::sanitize_fields_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Blogs::sanitize_fields_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			wp_send_json_error( array( 'errors' => array( 'title' => $fields->get_error_message() ) ) );
		}

		$image_id = $this->resolve_image_id();

		if ( is_wp_error( $image_id ) ) {
			wp_send_json_error( array( 'errors' => array( 'image' => $image_id->get_error_message() ) ) );
		}

		$blog_id = isset( $_POST['blog_id'] ) ? absint( wp_unslash( $_POST['blog_id'] ) ) : 0;

		if ( $blog_id > 0 ) {
			$existing = Blogs::find( $blog_id );

			if ( ! $existing ) {
				wp_send_json_error( array( 'message' => __( 'That post no longer exists.', 'doctor-ak-portal' ) ) );
			}

			$saved = Blogs::update( $blog_id, $fields, $image_id );

			if ( ! $saved ) {
				wp_send_json_error( array( 'message' => __( 'The post could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
			}

			wp_send_json_success(
				array(
					'message' => __( 'Post saved successfully.', 'doctor-ak-portal' ),
					'blog_id' => $blog_id,
				)
			);
		}

		$new_blog_id = Blogs::create( get_current_user_id(), $fields, (int) $image_id );

		if ( ! $new_blog_id ) {
			wp_send_json_error( array( 'message' => __( 'The post could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Post saved successfully.', 'doctor-ak-portal' ),
				'blog_id' => $new_blog_id,
			)
		);
	}

	/**
	 * AJAX handler: deletes a blog post.
	 *
	 * @return void
	 */
	public function handle_admin_delete_blog() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$blog_id  = isset( $_POST['blog_id'] ) ? absint( wp_unslash( $_POST['blog_id'] ) ) : 0;
		$existing = $blog_id > 0 ? Blogs::find( $blog_id ) : null;

		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'That post no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Blogs::delete( $blog_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The post could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Post deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Resolves the Featured Image field's attachment ID for the current
	 * request — a freshly chosen file (if any) is uploaded and takes
	 * precedence over the posted `image_id` (which otherwise just carries
	 * forward whatever the post already had). Mirrors
	 * Service_Handler::resolve_image_id().
	 *
	 * @return int|null|\WP_Error Attachment ID; null when no image_id was
	 *                             posted at all; WP_Error on an invalid upload.
	 */
	private function resolve_image_id() {
		$image_id = isset( $_POST['image_id'] ) ? absint( wp_unslash( $_POST['image_id'] ) ) : null;

		if ( ! empty( $_FILES['image'] ) && UPLOAD_ERR_NO_FILE !== ( $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$uploaded_image_id = $this->image_uploader->upload( $_FILES['image'], get_current_user_id() );

			if ( is_wp_error( $uploaded_image_id ) ) {
				return $uploaded_image_id;
			}

			$image_id = $uploaded_image_id;
		}

		return $image_id;
	}
}
