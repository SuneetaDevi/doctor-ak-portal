<?php
/**
 * Reports a patient (or the doctor / clinic staff) shares with an
 * appointment before the consultation — lab results, scans, previous
 * prescriptions — so the doctor can look at them during the visit, which
 * matters most for online video consultations.
 *
 * Unlike encounter reports (Encounter_Reports, public Media Library files),
 * these are patient-supplied medical documents, so they are kept out of the
 * Media Library in a private uploads folder (direct web access denied,
 * random file names) and only ever served through
 * Appointment_Reports_Handler::handle_view(), which checks who is asking.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Appointment_Reports
 */
class Appointment_Reports {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_appointment_reports';

	/**
	 * Private folder inside wp-content/uploads.
	 *
	 * @var string
	 */
	const DIRECTORY = 'dak-private/appointment-reports';

	/**
	 * Largest accepted file (10 MB, same as encounter reports).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 10 * 1024 * 1024;

	/**
	 * Most files one appointment can hold.
	 *
	 * @var int
	 */
	const MAX_FILES = 10;

	/**
	 * Accepted types: PDFs and photos of paper reports.
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
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Absolute path of the private folder, created (with web access
	 * denied) on first use.
	 *
	 * @return string|\WP_Error
	 */
	private static function directory() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'doctor_ak_report_storage', __( 'Reports can’t be stored right now. Please try again later.', 'doctor-ak-portal' ) );
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::DIRECTORY;

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'doctor_ak_report_storage', __( 'Reports can’t be stored right now. Please try again later.', 'doctor-ak-portal' ) );
		}

		// Deny direct access on Apache/LiteSpeed; the random names below
		// keep files unguessable on servers that ignore .htaccess.
		$guards = array(
			$dir . '/.htaccess'                  => "Require all denied\nDeny from all\n",
			$dir . '/index.php'                  => "<?php\n// Silence is golden.\n",
			dirname( $dir ) . '/.htaccess'       => "Require all denied\nDeny from all\n",
			dirname( $dir ) . '/index.php'       => "<?php\n// Silence is golden.\n",
		);

		foreach ( $guards as $path => $contents ) {
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny guard files.
			}
		}

		return $dir;
	}

	/**
	 * Validates and stores one uploaded file for an appointment.
	 *
	 * @param int    $appointment_id Appointment post ID.
	 * @param array  $file           One $_FILES entry.
	 * @param int    $user_id        Uploader.
	 * @param string $role           'patient', 'doctor' or 'staff'.
	 * @return array|\WP_Error The stored report (see for_appointment()) or an error.
	 */
	public static function add( $appointment_id, array $file, $user_id, $role ) {
		global $wpdb;

		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== ( isset( $file['error'] ) ? $file['error'] : UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'doctor_ak_upload_error', __( 'No valid file was uploaded.', 'doctor-ak-portal' ) );
		}

		if ( (int) $file['size'] > self::MAX_FILE_SIZE ) {
			return new \WP_Error( 'doctor_ak_upload_too_large', __( 'Each report must be smaller than 10 MB.', 'doctor-ak-portal' ) );
		}

		if ( self::count_for( $appointment_id ) >= self::MAX_FILES ) {
			/* translators: %d: maximum number of files. */
			return new \WP_Error( 'doctor_ak_upload_limit', sprintf( __( 'An appointment can have up to %d reports. Remove one to add another.', 'doctor-ak-portal' ), self::MAX_FILES ) );
		}

		// Checks the real file contents, not just the name.
		$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::ALLOWED_MIME_TYPES );

		if ( empty( $type['ext'] ) || empty( $type['type'] ) ) {
			return new \WP_Error( 'doctor_ak_upload_invalid_type', __( 'Please upload a PDF, JPG, PNG or WebP file.', 'doctor-ak-portal' ) );
		}

		$dir = self::directory();

		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$stored = (int) $appointment_id . '-' . wp_generate_password( 24, false, false ) . '.' . $type['ext'];

		if ( ! move_uploaded_file( $file['tmp_name'], $dir . '/' . $stored ) ) {
			return new \WP_Error( 'doctor_ak_upload_failed', __( 'The report could not be saved. Please try again.', 'doctor-ak-portal' ) );
		}

		$original = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		$original = '' !== $original ? $original : 'report.' . $type['ext'];

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'appointment_id' => (int) $appointment_id,
				'file_name'      => $stored,
				'original_name'  => mb_substr( $original, 0, 190 ),
				'mime_type'      => $type['type'],
				'file_size'      => (int) $file['size'],
				'uploaded_by'    => (int) $user_id,
				'uploader_role'  => $role,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_delete_file( $dir . '/' . $stored );

			return new \WP_Error( 'doctor_ak_upload_failed', __( 'The report could not be saved. Please try again.', 'doctor-ak-portal' ) );
		}

		return self::find( (int) $wpdb->insert_id );
	}

	/**
	 * One report by ID.
	 *
	 * @param int $report_id Report ID.
	 * @return array|null
	 */
	public static function find( $report_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $report_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Every report on an appointment, oldest first.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return array List of { id, appointment_id, name, mime_type, size, uploaded_by, uploader_role, uploader_name, created_at }.
	 */
	public static function for_appointment( $appointment_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE appointment_id = %d ORDER BY id ASC', (int) $appointment_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	/**
	 * Number of reports on one appointment.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return int
	 */
	public static function count_for( $appointment_id ) {
		$counts = self::counts_for( array( $appointment_id ) );

		return isset( $counts[ (int) $appointment_id ] ) ? $counts[ (int) $appointment_id ] : 0;
	}

	/**
	 * Report counts for many appointments in one query (list badges).
	 *
	 * @param int[] $appointment_ids Appointment post IDs.
	 * @return array appointment_id => count (appointments with none are absent).
	 */
	public static function counts_for( array $appointment_ids ) {
		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $appointment_ids ) ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare( 'SELECT appointment_id, COUNT(*) AS total FROM ' . self::table_name() . " WHERE appointment_id IN ({$placeholders}) GROUP BY appointment_id", $ids ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table name and placeholders only.
			ARRAY_A
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['appointment_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Absolute path of a report's file, or '' if it is missing.
	 *
	 * @param array $report A report from find().
	 * @return string
	 */
	public static function file_path( array $report ) {
		$dir = self::directory();

		if ( is_wp_error( $dir ) || '' === $report['file_name'] || basename( $report['file_name'] ) !== $report['file_name'] ) {
			return '';
		}

		$path = $dir . '/' . $report['file_name'];

		return is_file( $path ) ? $path : '';
	}

	/**
	 * Deletes a report and its file.
	 *
	 * @param int $report_id Report ID.
	 * @return bool
	 */
	public static function delete( $report_id ) {
		global $wpdb;

		$report = self::find( $report_id );

		if ( ! $report ) {
			return false;
		}

		$path = self::file_path( $report );

		if ( '' !== $path ) {
			wp_delete_file( $path );
		}

		return false !== $wpdb->delete( self::table_name(), array( 'id' => (int) $report_id ), array( '%d' ) );
	}

	/**
	 * Row → view model.
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	private static function decode( array $row ) {
		$uploader = (int) $row['uploaded_by'] > 0 ? get_userdata( (int) $row['uploaded_by'] ) : false;
		$name     = '';

		if ( $uploader ) {
			$name = trim( $uploader->first_name . ' ' . $uploader->last_name );
			$name = '' !== $name ? $name : $uploader->display_name;
		}

		return array(
			'id'             => (int) $row['id'],
			'appointment_id' => (int) $row['appointment_id'],
			'file_name'      => (string) $row['file_name'],
			'name'           => (string) $row['original_name'],
			'mime_type'      => (string) $row['mime_type'],
			'size'           => (int) $row['file_size'],
			'uploaded_by'    => (int) $row['uploaded_by'],
			'uploader_role'  => (string) $row['uploader_role'],
			'uploader_name'  => $name,
			'created_at'     => (string) $row['created_at'],
		);
	}
}
