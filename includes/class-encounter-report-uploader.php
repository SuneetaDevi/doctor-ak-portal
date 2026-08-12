<?php
/**
 * Handles secure encounter-report file uploads into the WordPress Media
 * Library (lab results, scans, etc. — PDFs and images, unlike the
 * image-only Profile_Picture_Uploader).
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounter_Report_Uploader
 */
class Encounter_Report_Uploader {

	/**
	 * Maximum accepted upload size, in bytes (10 MB).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 10 * 1024 * 1024;

	/**
	 * Mime types accepted for report uploads.
	 *
	 * @var array
	 */
	const ALLOWED_MIME_TYPES = array(
		'pdf'      => 'application/pdf',
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
	);

	/**
	 * Validates and uploads a single file into the Media Library.
	 *
	 * @param array $file  A single entry from $_FILES, e.g. $_FILES['report'].
	 * @param int   $owner User ID to attach the resulting attachment to.
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function upload( array $file, $owner = 0 ) {
		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new \WP_Error( 'doctor_ak_upload_error', __( 'No valid file was uploaded.', 'doctor-ak-portal' ) );
		}

		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return new \WP_Error( 'doctor_ak_upload_too_large', __( 'Reports must be smaller than 10 MB.', 'doctor-ak-portal' ) );
		}

		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::ALLOWED_MIME_TYPES );

		if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) ) {
			return new \WP_Error( 'doctor_ak_upload_invalid_type', __( 'Please upload a PDF, JPG, PNG or WebP file.', 'doctor-ak-portal' ) );
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
	 * Restricts uploads to report file types for this request only.
	 *
	 * @param array $mimes Existing allowed mime types.
	 * @return array
	 */
	public function restrict_mime_types( $mimes ) {
		return self::ALLOWED_MIME_TYPES;
	}
}
