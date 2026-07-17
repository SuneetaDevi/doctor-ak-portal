<?php
/**
 * Handles secure profile picture uploads into the WordPress Media Library.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Profile_Picture_Uploader
 *
 * Registration happens before a doctor account exists, so the standard
 * wp-admin media picker (which requires a logged-in `upload_files` user)
 * is not available. This class performs a direct, validated upload into
 * the Media Library instead, usable by both anonymous registrants and
 * logged-in users editing their profile (Phase 6).
 */
class Profile_Picture_Uploader {

	/**
	 * Maximum accepted upload size, in bytes (5 MB).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 5 * 1024 * 1024;

	/**
	 * Mime types accepted for profile pictures.
	 *
	 * @var array
	 */
	const ALLOWED_MIME_TYPES = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
	);

	/**
	 * Validates and uploads a single file into the Media Library.
	 *
	 * @param array $file  A single entry from $_FILES, e.g. $_FILES['profile_picture'].
	 * @param int   $owner User ID to attach the resulting attachment to (0 for guests).
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function upload( array $file, $owner = 0 ) {
		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new \WP_Error( 'doctor_ak_upload_error', __( 'No valid file was uploaded.', 'doctor-ak-portal' ) );
		}

		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return new \WP_Error( 'doctor_ak_upload_too_large', __( 'Profile pictures must be smaller than 5 MB.', 'doctor-ak-portal' ) );
		}

		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::ALLOWED_MIME_TYPES );

		if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) ) {
			return new \WP_Error( 'doctor_ak_upload_invalid_type', __( 'Please upload a JPG, PNG or WebP image.', 'doctor-ak-portal' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		add_filter( 'upload_mimes', array( $this, 'restrict_mime_types' ) );
		$overrides = array(
			'test_form' => false,
			'mimes'     => self::ALLOWED_MIME_TYPES,
		);
		$uploaded  = wp_handle_upload( $file, $overrides );
		remove_filter( 'upload_mimes', array( $this, 'restrict_mime_types' ) );

		if ( isset( $uploaded['error'] ) ) {
			return new \WP_Error( 'doctor_ak_upload_failed', $uploaded['error'] );
		}

		$attachment = array(
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
			'post_author'    => absint( $owner ),
		);

		$attachment_id = wp_insert_attachment( $attachment, $uploaded['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		return $attachment_id;
	}

	/**
	 * Restricts uploads to image types for this request only.
	 *
	 * @param array $mimes Existing allowed mime types.
	 * @return array
	 */
	public function restrict_mime_types( $mimes ) {
		return self::ALLOWED_MIME_TYPES;
	}

	/**
	 * Confirms an attachment ID is a valid, unclaimed (guest-uploaded) image
	 * before it is permanently linked to a newly registered user.
	 *
	 * @param int $attachment_id Attachment ID posted from the client.
	 * @return bool
	 */
	public function is_unclaimed_image( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return false;
		}

		return 0 === (int) get_post_field( 'post_author', $attachment_id );
	}

	/**
	 * Transfers ownership of a guest-uploaded attachment to the new user.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       New owner's user ID.
	 * @return void
	 */
	public function claim( $attachment_id, $user_id ) {
		wp_update_post(
			array(
				'ID'          => absint( $attachment_id ),
				'post_author' => absint( $user_id ),
			)
		);
	}
}
