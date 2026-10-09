<?php
/**
 * AJAX endpoints for reports shared with an appointment before the
 * consultation (see Appointment_Reports): list, upload, delete, and a
 * permission-checked file view. Used by the patient, doctor and admin
 * dashboards (assets/js/doctor-ak-appointment-reports.js).
 *
 * Who can do what:
 *  - see the files: the appointment's patient, its doctor, administrators,
 *    and receptionists (limited to their assigned clinics, as in the
 *    Appointments list);
 *  - add files: the same people, until the appointment is completed or
 *    cancelled;
 *  - remove a file: whoever added it (while files can still be added), or
 *    an administrator/receptionist.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointment_Reports;
use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Appointment_Reports_Handler
 */
class Appointment_Reports_Handler {

	/**
	 * Nonce action for every endpoint.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_appointment_reports';

	/**
	 * Loads the "Reports" dialog script on a dashboard page.
	 *
	 * @return void
	 */
	public static function enqueue() {
		wp_enqueue_script(
			'doctor-ak-portal-appointment-reports',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-appointment-reports.js',
			array(),
			\DoctorAKPortal\Includes\Assets::version( 'assets/js/doctor-ak-appointment-reports.js' ),
			true
		);

		wp_localize_script( 'doctor-ak-portal-appointment-reports', 'dakAppointmentReports', self::script_settings() );
	}

	/**
	 * Settings the dashboards' script needs (window.dakAppointmentReports).
	 *
	 * @return array
	 */
	public static function script_settings() {
		return array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
			'maxFiles' => Appointment_Reports::MAX_FILES,
			'maxBytes' => Appointment_Reports::MAX_FILE_SIZE,
			'accept'   => '.pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp',
			'strings'  => array(
				'title'        => __( 'Reports', 'doctor-ak-portal' ),
				'intro'        => __( 'Share lab results, scans or previous prescriptions so your doctor can review them during the consultation.', 'doctor-ak-portal' ),
				'introStaff'   => __( 'Reports shared for this appointment before the consultation.', 'doctor-ak-portal' ),
				'add'          => __( 'Add reports', 'doctor-ak-portal' ),
				'drop'         => __( 'or drop files here', 'doctor-ak-portal' ),
				/* translators: 1: max number of files, 2: max size in MB. */
				'limits'       => sprintf( __( 'PDF, JPG, PNG or WebP · up to %2$d MB each · up to %1$d files', 'doctor-ak-portal' ), Appointment_Reports::MAX_FILES, Appointment_Reports::MAX_FILE_SIZE / 1048576 ),
				'empty'        => __( 'No reports have been shared yet.', 'doctor-ak-portal' ),
				'emptyClosed'  => __( 'No reports were shared for this appointment.', 'doctor-ak-portal' ),
				'closed'       => __( 'This appointment is closed, so reports can no longer be added.', 'doctor-ak-portal' ),
				'loading'      => __( 'Loading…', 'doctor-ak-portal' ),
				'uploading'    => __( 'Uploading…', 'doctor-ak-portal' ),
				/* translators: %s: file name. */
				'uploaded'     => __( '%s added.', 'doctor-ak-portal' ),
				'open'         => __( 'Open', 'doctor-ak-portal' ),
				'remove'       => __( 'Remove', 'doctor-ak-portal' ),
				/* translators: %s: file name. */
				'confirmRemove' => __( 'Remove %s?', 'doctor-ak-portal' ),
				/* translators: %s: file name. */
				'removed'      => __( '%s removed.', 'doctor-ak-portal' ),
				'close'        => __( 'Close', 'doctor-ak-portal' ),
				/* translators: %s: file name. */
				'tooLarge'     => __( '%s is larger than the size limit.', 'doctor-ak-portal' ),
				/* translators: %s: file name. */
				'badType'      => __( '%s is not a PDF, JPG, PNG or WebP file.', 'doctor-ak-portal' ),
				'genericError' => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
			),
		);
	}

	/**
	 * AJAX: the reports on an appointment, and what the user may do.
	 *
	 * @return void
	 */
	public function handle_list() {
		$appointment = $this->authorized_appointment();

		wp_send_json_success( self::view_model( $appointment ) );
	}

	/**
	 * AJAX: adds one or more files (`reports[]`) to an appointment.
	 *
	 * @return void
	 */
	public function handle_upload() {
		$appointment = $this->authorized_appointment();
		$access      = self::access( $appointment );

		if ( ! self::can_add( $appointment ) ) {
			wp_send_json_error( array( 'message' => __( 'This appointment is closed, so reports can no longer be added.', 'doctor-ak-portal' ) ) );
		}

		$files  = self::uploaded_files();
		$errors = array();
		$added  = array();

		if ( empty( $files ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was received.', 'doctor-ak-portal' ) ) );
		}

		foreach ( $files as $file ) {
			$result = Appointment_Reports::add( $appointment['id'], $file, get_current_user_id(), $access );

			if ( is_wp_error( $result ) ) {
				/* translators: 1: file name, 2: reason. */
				$errors[] = sprintf( __( '%1$s: %2$s', 'doctor-ak-portal' ), sanitize_file_name( (string) $file['name'] ), $result->get_error_message() );
			} else {
				$added[] = $result['name'];
			}
		}

		$data           = self::view_model( $appointment );
		$data['added']  = $added;
		$data['errors'] = $errors;

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: removes one report.
	 *
	 * @return void
	 */
	public function handle_delete() {
		$appointment = $this->authorized_appointment();
		$report_id   = isset( $_POST['report_id'] ) ? absint( wp_unslash( $_POST['report_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorized_appointment().
		$report      = $report_id > 0 ? Appointment_Reports::find( $report_id ) : null;

		if ( ! $report || $report['appointment_id'] !== (int) $appointment['id'] ) {
			wp_send_json_error( array( 'message' => __( 'That report could not be found.', 'doctor-ak-portal' ) ) );
		}

		if ( ! self::can_remove( $appointment, $report ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		Appointment_Reports::delete( $report_id );

		wp_send_json_success( self::view_model( $appointment ) );
	}

	/**
	 * AJAX (GET): streams one report file to a permitted viewer — PDFs and
	 * images open in the browser; never cached.
	 *
	 * @return void
	 */
	public function handle_view() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'This link has expired. Please reopen the report from your dashboard.', 'doctor-ak-portal' ), '', array( 'response' => 403 ) );
		}

		$report_id   = isset( $_GET['report_id'] ) ? absint( wp_unslash( $_GET['report_id'] ) ) : 0;
		$report      = $report_id > 0 ? Appointment_Reports::find( $report_id ) : null;
		$appointment = $report ? Appointments::find( $report['appointment_id'] ) : array();

		if ( ! $report || empty( $appointment ) || '' === self::access( $appointment ) ) {
			wp_die( esc_html__( 'You do not have permission to view this report.', 'doctor-ak-portal' ), '', array( 'response' => 403 ) );
		}

		$path = Appointment_Reports::file_path( $report );

		if ( '' === $path ) {
			wp_die( esc_html__( 'This report file is no longer available.', 'doctor-ak-portal' ), '', array( 'response' => 404 ) );
		}

		$download = ! empty( $_GET['download'] );
		$name     = str_replace( array( '"', "\r", "\n" ), '', $report['name'] );

		nocache_headers();
		header( 'Content-Type: ' . $report['mime_type'] );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . $name . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; plugin-types application/pdf" );
		header( 'Referrer-Policy: no-referrer' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a private file to an authorised viewer.
		exit;
	}

	/**
	 * Report counts for appointment list badges.
	 *
	 * @param array $appointment_ids Appointment IDs.
	 * @return array appointment_id => count.
	 */
	public static function counts( array $appointment_ids ) {
		return Appointment_Reports::counts_for( $appointment_ids );
	}

	/**
	 * What the current user is to an appointment: 'staff', 'doctor',
	 * 'patient', or '' (no access).
	 *
	 * @param array $appointment Appointments::find() row.
	 * @return string
	 */
	public static function access( array $appointment ) {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 || empty( $appointment ) ) {
			return '';
		}

		if ( current_user_can( 'manage_options' ) ) {
			return 'staff';
		}

		if ( current_user_can( 'doctor_ak_manage_appointments' ) && self::receptionist_can_see( $appointment ) ) {
			return 'staff';
		}

		if ( (int) $appointment['doctor_id'] === $user_id ) {
			return 'doctor';
		}

		if ( (int) $appointment['patient_id'] > 0 && (int) $appointment['patient_id'] === $user_id ) {
			return 'patient';
		}

		return '';
	}

	/**
	 * A receptionist assigned to particular clinics only reaches those
	 * clinics' appointments (video ones are open to all) — the same rule as
	 * the admin Appointments list.
	 *
	 * @param array $appointment Appointment row.
	 * @return bool
	 */
	private static function receptionist_can_see( array $appointment ) {
		$user = wp_get_current_user();

		if ( ! in_array( Roles::RECEPTIONIST_ROLE, (array) $user->roles, true ) ) {
			return true;
		}

		$assigned = array_values( array_filter( array_map( 'intval', (array) get_user_meta( $user->ID, Clinic_Locations::RECEPTIONIST_META_KEY, true ) ) ) );

		if ( empty( $assigned ) || empty( $appointment['clinic_id'] ) ) {
			return true;
		}

		$clinic = Clinics::find( $appointment['clinic_id'] );

		return $clinic && in_array( (int) $clinic['clinic_location_id'], $assigned, true );
	}

	/**
	 * Files can be added until the appointment is completed or cancelled.
	 *
	 * @param array $appointment Appointment row.
	 * @return bool
	 */
	private static function can_add( array $appointment ) {
		return '' !== self::access( $appointment )
			&& ! in_array( $appointment['status'], array( Appointments::STATUS_COMPLETED, Appointments::STATUS_CANCELLED ), true );
	}

	/**
	 * Staff can remove any file; others only their own, while files can
	 * still be added.
	 *
	 * @param array $appointment Appointment row.
	 * @param array $report      Report.
	 * @return bool
	 */
	private static function can_remove( array $appointment, array $report ) {
		if ( 'staff' === self::access( $appointment ) ) {
			return true;
		}

		return self::can_add( $appointment ) && $report['uploaded_by'] === get_current_user_id();
	}

	/**
	 * The modal's data: reports with view links and permissions.
	 *
	 * @param array $appointment Appointment row.
	 * @return array
	 */
	private static function view_model( array $appointment ) {
		$access  = self::access( $appointment );
		$user_id = get_current_user_id();
		$nonce   = wp_create_nonce( self::NONCE_ACTION );
		$reports = array();

		foreach ( Appointment_Reports::for_appointment( $appointment['id'] ) as $report ) {
			if ( $report['uploaded_by'] === $user_id ) {
				$by = __( 'You', 'doctor-ak-portal' );
			} elseif ( 'patient' === $report['uploader_role'] ) {
				$by = __( 'Patient', 'doctor-ak-portal' );
			} elseif ( 'doctor' === $report['uploader_role'] ) {
				/* translators: %s: doctor's name. */
				$by = '' !== $report['uploader_name'] ? sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $report['uploader_name'] ) : __( 'Doctor', 'doctor-ak-portal' );
			} else {
				$by = __( 'Clinic staff', 'doctor-ak-portal' );
			}

			$timestamp = strtotime( $report['created_at'] );
			$url       = add_query_arg(
				array(
					'action'    => 'doctor_ak_appointment_report_view',
					'report_id' => $report['id'],
					'nonce'     => $nonce,
				),
				admin_url( 'admin-ajax.php' )
			);

			$reports[] = array(
				'id'         => $report['id'],
				'name'       => $report['name'],
				'type'       => 'application/pdf' === $report['mime_type'] ? 'pdf' : 'image',
				'size'       => size_format( $report['size'], 1 ),
				'by'         => $by,
				'when'       => $timestamp ? date_i18n( 'd M Y, h:i A', $timestamp ) : '',
				'url'        => $url,
				'canRemove'  => self::can_remove( $appointment, $report ),
			);
		}

		return array(
			'appointmentId' => (int) $appointment['id'],
			'access'        => $access,
			'canAdd'        => self::can_add( $appointment ),
			'reports'       => $reports,
			'count'         => count( $reports ),
			'remaining'     => max( 0, Appointment_Reports::MAX_FILES - count( $reports ) ),
		);
	}

	/**
	 * Verifies the nonce and returns the requested appointment, or ends the
	 * request when the user has no access to it.
	 *
	 * @return array
	 */
	private function authorized_appointment() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id > 0 ? Appointments::find( $appointment_id ) : array();

		if ( empty( $appointment ) ) {
			wp_send_json_error( array( 'message' => __( 'That appointment could not be found.', 'doctor-ak-portal' ) ) );
		}

		if ( '' === self::access( $appointment ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		return $appointment;
	}

	/**
	 * Normalises $_FILES['reports'] (single or multiple) into a list.
	 *
	 * @return array
	 */
	private static function uploaded_files() {
		if ( empty( $_FILES['reports'] ) || ! is_array( $_FILES['reports'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorized_appointment().
			return array();
		}

		$raw = $_FILES['reports']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated per file in Appointment_Reports::add().

		if ( ! is_array( $raw['name'] ) ) {
			return array( $raw );
		}

		$files = array();

		foreach ( array_keys( $raw['name'] ) as $i ) {
			$files[] = array(
				'name'     => $raw['name'][ $i ],
				'type'     => $raw['type'][ $i ],
				'tmp_name' => $raw['tmp_name'][ $i ],
				'error'    => $raw['error'][ $i ],
				'size'     => $raw['size'][ $i ],
			);
		}

		return array_slice( $files, 0, Appointment_Reports::MAX_FILES );
	}
}
