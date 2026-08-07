<?php
/**
 * AJAX handlers backing the admin dashboard's "Settings" section — the
 * "Clinic branding" card (front-end equivalent of wp-admin's Settings →
 * Footer Settings), for admins who manage the site entirely from the
 * [admin_dashboard] shortcode. (Notification email preferences are a
 * separate, per-account concern — see Notification_Handler::handle_save_preferences().)
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clinic_Branding_Handler {

	/**
	 * Nonce action shared with the admin dashboard's JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_admin_clinic_branding';

	/**
	 * AJAX handler: saves the clinic's name/phone/address.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'doctor-ak-portal' ) ), 403 );
		}

		$name    = isset( $_POST['clinic_name'] ) ? sanitize_text_field( wp_unslash( $_POST['clinic_name'] ) ) : '';
		$phone   = isset( $_POST['clinic_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['clinic_phone'] ) ) : '';
		$address = isset( $_POST['clinic_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['clinic_address'] ) ) : '';

		update_option( Site_Footer::OPTION_CLINIC_NAME, $name );
		update_option( Site_Footer::OPTION_CLINIC_PHONE, $phone );
		update_option( Site_Footer::OPTION_CLINIC_ADDRESS, $address );

		wp_send_json_success( array( 'message' => __( 'Clinic branding saved.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: uploads and stores the clinic's logo. Uses
	 * wp_handle_upload() directly (not the full media library modal) since
	 * this card only ever needs one image, not a picker.
	 *
	 * @return void
	 */
	public function handle_upload_logo() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( empty( $_FILES['logo'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose an image to upload.', 'doctor-ak-portal' ) ) );
		}

		$file = $_FILES['logo']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below by wp_check_filetype_and_ext() inside wp_handle_upload().

		$allowed_types = array(
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
			'svg'  => 'image/svg+xml',
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => $allowed_types,
		);

		$result = wp_handle_upload( $file, $overrides );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		// Delete the previous upload (if any) so re-uploading doesn't leave
		// orphaned files behind in the uploads directory.
		$previous_path = get_option( Site_Footer::OPTION_CLINIC_LOGO_PATH, '' );

		if ( '' !== $previous_path && file_exists( $previous_path ) ) {
			wp_delete_file( $previous_path );
		}

		update_option( Site_Footer::OPTION_CLINIC_LOGO_URL, $result['url'] );
		update_option( Site_Footer::OPTION_CLINIC_LOGO_PATH, $result['file'] );

		wp_send_json_success(
			array(
				'message'  => __( 'Logo updated.', 'doctor-ak-portal' ),
				'logo_url' => $result['url'],
			)
		);
	}

}
