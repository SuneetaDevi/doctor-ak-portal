<?php
/**
 * AJAX handlers backing the admin dashboard's "Settings" section — the
 * "Home page videos" card (uploads self-hosted video files shown on the
 * [dak_home] page).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Home_Videos;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Home_Videos_Handler
 */
class Home_Videos_Handler {

	/**
	 * Nonce action shared with the admin dashboard's JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_admin_home_videos';

	/**
	 * AJAX handler: uploads one video file and returns its URL. Uses
	 * wp_handle_upload() directly (same pattern as
	 * Clinic_Branding_Handler::handle_upload_logo()) — the caller is
	 * responsible for including the returned URL in the row set it submits
	 * to handle_save().
	 *
	 * @return void
	 */
	public function handle_upload_video() {
		$this->guard();

		if ( empty( $_FILES['video'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a video to upload.', 'doctor-ak-portal' ) ) );
		}

		$file = $_FILES['video']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below by wp_check_filetype_and_ext() inside wp_handle_upload().

		$allowed_types = array(
			'mp4'  => 'video/mp4',
			'webm' => 'video/webm',
			'mov'  => 'video/quicktime',
			'ogg'  => 'video/ogg',
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $allowed_types,
			)
		);

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		wp_send_json_success( array( 'video_url' => $result['url'] ) );
	}

	/**
	 * AJAX handler: uploads one poster/thumbnail image and returns its URL.
	 *
	 * @return void
	 */
	public function handle_upload_poster() {
		$this->guard();

		if ( empty( $_FILES['poster'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose an image to upload.', 'doctor-ak-portal' ) ) );
		}

		$file = $_FILES['poster']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below by wp_check_filetype_and_ext() inside wp_handle_upload().

		$allowed_types = array(
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $allowed_types,
			)
		);

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		wp_send_json_success( array( 'poster_url' => $result['url'] ) );
	}

	/**
	 * AJAX handler: replaces the saved video row set with whatever the
	 * editor currently shows (title + already-uploaded video/poster URLs
	 * for each row).
	 *
	 * @return void
	 */
	public function handle_save() {
		$this->guard();

		$rows = array();

		if ( isset( $_POST['rows'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['rows'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash() applied, decoded value is sanitized field-by-field in Home_Videos::save().

			if ( is_array( $decoded ) ) {
				$rows = $decoded;
			}
		}

		Home_Videos::save( $rows );

		wp_send_json_success( array( 'message' => __( 'Home page videos saved.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Shared nonce + capability check for every handler in this class.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'doctor-ak-portal' ) ), 403 );
		}
	}
}
