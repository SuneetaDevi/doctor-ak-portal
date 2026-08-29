<?php
/**
 * AJAX handlers backing the clinical Encounter workflow: check-in, adding
 * Problems/Prescriptions/Bill items, closing (checkout), and downloading
 * the Prescription/Bill PDFs. Shared by the admin (and Receptionist) and
 * doctor dashboards — see can_manage_appointment()/can_manage_encounter()
 * for the authorization rule both audiences share.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Encounter_Bill_Items;
use DoctorAKPortal\Includes\Encounter_Bill_Pdf;
use DoctorAKPortal\Includes\Encounter_Report_Uploader;
use DoctorAKPortal\Includes\Encounter_Reports;
use DoctorAKPortal\Includes\Encounter_Prescriptions;
use DoctorAKPortal\Includes\Encounter_Problems;
use DoctorAKPortal\Includes\Encounters;
use DoctorAKPortal\Includes\Medicines;
use DoctorAKPortal\Includes\Prescription_Pdf;
use DoctorAKPortal\Includes\Revenue_Ledger;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounter_Handler
 */
class Encounter_Handler {

	/**
	 * Nonce action shared by every dashboard's Encounter UI (admin, doctor).
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_encounter';

	/**
	 * Nonce action for the Prescription PDF download link.
	 *
	 * @var string
	 */
	const PRESCRIPTION_PDF_NONCE_ACTION = 'doctor_ak_prescription_pdf_download';

	/**
	 * Nonce action for the Bill PDF download link.
	 *
	 * @var string
	 */
	const BILL_PDF_NONCE_ACTION = 'doctor_ak_encounter_bill_pdf_download';

	/**
	 * Report file upload service.
	 *
	 * @var Encounter_Report_Uploader
	 */
	private $report_uploader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Encounter_Report_Uploader $report_uploader Upload service.
	 */
	public function __construct( Encounter_Report_Uploader $report_uploader ) {
		$this->report_uploader = $report_uploader;
	}

	/**
	 * AJAX handler: checks a patient in — opens (or resumes) an encounter
	 * for their appointment.
	 *
	 * @return void
	 */
	public function handle_check_in() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$appointment    = $appointment_id > 0 ? Appointments::find( $appointment_id ) : array();

		if ( empty( $appointment ) ) {
			wp_send_json_error( array( 'message' => __( 'That appointment could not be found.', 'doctor-ak-portal' ) ) );
		}

		if ( ! self::can_manage_appointment( (int) $appointment['doctor_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$clinic_id = isset( $_POST['clinic_id'] ) ? absint( wp_unslash( $_POST['clinic_id'] ) ) : 0;

		// No clinic chosen and the appointment doesn't already have one —
		// default to the doctor's only physical clinic, if they have
		// exactly one; leave at 0 (none) if they have several (front-desk
		// picks manually via clinic_id) or none (video-only doctor).
		if ( 0 === $clinic_id && 0 === (int) $appointment['clinic_id'] ) {
			$physical_clinics = array_values(
				array_filter(
					Clinics::get_for_doctor( $appointment['doctor_id'] ),
					function ( $clinic ) {
						return Clinics::TYPE_PHYSICAL === $clinic['type'];
					}
				)
			);

			if ( 1 === count( $physical_clinics ) ) {
				$clinic_id = $physical_clinics[0]['id'];
			}
		}

		$encounter_id = Encounters::check_in( $appointment_id, $appointment['doctor_id'], $appointment['patient_id'], $clinic_id, get_current_user_id() );

		if ( is_wp_error( $encounter_id ) ) {
			wp_send_json_error( array( 'message' => $encounter_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Patient checked in.', 'doctor-ak-portal' ),
				'encounter_id' => $encounter_id,
			)
		);
	}

	/**
	 * AJAX handler: creates a brand-new "walk-in" encounter with no
	 * pre-existing appointment — admin/receptionist picks a Clinic, then a
	 * Doctor practicing there, then a registered Patient (see the
	 * Encounters list's "Add Encounter" modal). Every encounter still needs
	 * a real Appointments record underneath it (Prescription/Bill PDFs,
	 * admin_row_data(), etc. all read from one), so this creates a
	 * same-moment clinic appointment behind the scenes first, then
	 * immediately checks it in via the normal check-in path.
	 *
	 * @return void
	 */
	public function handle_create_encounter() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id           = isset( $_POST['doctor_id'] ) ? absint( wp_unslash( $_POST['doctor_id'] ) ) : 0;
		$patient_id          = isset( $_POST['patient_id'] ) ? absint( wp_unslash( $_POST['patient_id'] ) ) : 0;
		$clinic_location_id  = isset( $_POST['clinic_location_id'] ) ? absint( wp_unslash( $_POST['clinic_location_id'] ) ) : 0;

		if ( ! self::can_manage_appointment( $doctor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
			wp_send_json_error( array( 'errors' => array( 'doctor_id' => __( 'Please choose a valid doctor.', 'doctor-ak-portal' ) ) ) );
		}

		$patient = $patient_id > 0 ? get_userdata( $patient_id ) : false;

		if ( ! $patient || ! in_array( Roles::PATIENT_ROLE, (array) $patient->roles, true ) ) {
			wp_send_json_error( array( 'errors' => array( 'patient_id' => __( 'Please choose a valid patient.', 'doctor-ak-portal' ) ) ) );
		}

		// Resolve the doctor's OWN Clinics row aligned to the chosen master
		// clinic location — that row's id is what
		// doctor_ak_appointment_clinic_id actually stores (see
		// Appointments::check_in()), not the Clinic_Locations id itself.
		$clinic_id = 0;

		if ( $clinic_location_id > 0 ) {
			foreach ( Clinics::get_for_doctor( $doctor_id ) as $clinic ) {
				if ( Clinics::TYPE_PHYSICAL === $clinic['type'] && (int) $clinic['clinic_location_id'] === $clinic_location_id ) {
					$clinic_id = $clinic['id'];
					break;
				}
			}
		}

		if ( 0 === $clinic_id ) {
			wp_send_json_error( array( 'errors' => array( 'clinic_location_id' => __( 'This doctor does not practice at the chosen clinic.', 'doctor-ak-portal' ) ) ) );
		}

		// A minute in the future, not "now" — check_in()'s overdue guard
		// rejects an appointment whose start time has already passed by the
		// time it actually runs, and "now" would already be in the past by
		// the time this request reaches that check.
		$today = current_time( 'Y-m-d' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- building a local date/time for a new appointment, not doing UTC math.
		$time  = date_i18n( 'H:i', current_time( 'timestamp' ) + MINUTE_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- building a near-future local time, not doing UTC math.

		$appointment_id = Appointments::create(
			array(
				'doctor_id'      => $doctor_id,
				'patient_id'     => $patient_id,
				'type'           => Appointments::TYPE_CLINIC,
				'date'           => $today,
				'time'           => $time,
				'notes'          => __( 'Walk-in — added directly from the Encounters list.', 'doctor-ak-portal' ),
				'clinic_id'      => $clinic_id,
				'status'         => Appointments::STATUS_CONFIRMED,
				'payment_status' => Appointments::PAYMENT_STATUS_PAID,
				'payment_mode'   => Appointments::PAYMENT_MODE_MANUAL,
				'admin_override' => true,
			)
		);

		if ( is_wp_error( $appointment_id ) ) {
			wp_send_json_error( array( 'message' => $appointment_id->get_error_message() ) );
		}

		$encounter_id = Encounters::check_in( $appointment_id, $doctor_id, $patient_id, $clinic_id, get_current_user_id() );

		if ( is_wp_error( $encounter_id ) ) {
			wp_send_json_error( array( 'message' => $encounter_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Encounter opened.', 'doctor-ak-portal' ),
				'encounter_id' => $encounter_id,
			)
		);
	}

	/**
	 * AJAX handler: closes an encounter and checks the patient out.
	 *
	 * @return void
	 */
	public function handle_close_encounter() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		$result = Encounters::close( $encounter['id'], get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Encounter closed — patient checked out.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: returns the full view-model for an encounter (its own
	 * data, the appointment summary, problems, prescriptions, bill items,
	 * running total, and the doctor's active medicines list) — used to
	 * render the detail screen and to re-render it in place after every
	 * add/delete, instead of a full page reload.
	 *
	 * @return void
	 */
	public function handle_get_encounter() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: adds a Problem row to an encounter, or — when
	 * `problem_id` is a row already on this encounter — updates it in
	 * place instead (same "add or edit through one endpoint" pattern
	 * Admin_User_Handler::handle_save_user() uses).
	 *
	 * @return void
	 */
	public function handle_add_problem() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( '' === $description ) {
			wp_send_json_error( array( 'message' => __( 'Please describe the problem.', 'doctor-ak-portal' ) ) );
		}

		$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		$problem_id = isset( $_POST['problem_id'] ) ? absint( wp_unslash( $_POST['problem_id'] ) ) : 0;

		if ( $problem_id > 0 ) {
			Encounter_Problems::update( $problem_id, $encounter['id'], $description, $notes );
		} else {
			Encounter_Problems::add( $encounter['id'], $description, $notes );
		}

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: deletes a Problem row from an encounter.
	 *
	 * @return void
	 */
	public function handle_delete_problem() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		$problem_id = isset( $_POST['problem_id'] ) ? absint( wp_unslash( $_POST['problem_id'] ) ) : 0;

		Encounter_Problems::delete( $problem_id, $encounter['id'] );

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: adds a Prescription row to an encounter, or — when
	 * `prescription_id` is a row already on this encounter — updates it in
	 * place instead (same "add or edit through one endpoint" pattern
	 * Admin_User_Handler::handle_save_user() uses). medicine_id may be 0
	 * (the doctor typed the medicine name directly instead of picking from
	 * the Medicines list).
	 *
	 * @return void
	 */
	public function handle_add_prescription() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		$medicine_id   = isset( $_POST['medicine_id'] ) ? absint( wp_unslash( $_POST['medicine_id'] ) ) : 0;
		$medicine_name = isset( $_POST['medicine_name'] ) ? sanitize_text_field( wp_unslash( $_POST['medicine_name'] ) ) : '';

		if ( $medicine_id > 0 ) {
			$medicine = Medicines::find( $medicine_id );

			if ( $medicine ) {
				$medicine_name = $medicine['name'];
			}
		}

		if ( '' === $medicine_name ) {
			wp_send_json_error( array( 'message' => __( 'Please choose or type a medicine.', 'doctor-ak-portal' ) ) );
		}

		// Auto-learn: a name typed straight into the field (not picked from
		// a suggestion) is matched case-insensitively against this doctor's
		// list and the shared common list, or added as a new entry — so it
		// shows up as a suggestion next time, with no separate "save this
		// medicine" step or management screen.
		if ( 0 === $medicine_id ) {
			$medicine_id = Medicines::find_or_create_by_name( $medicine_name, $encounter['doctor_id'] );
		}

		$fields = array(
			'medicine_id'   => $medicine_id,
			'medicine_name' => $medicine_name,
			'dosage'        => isset( $_POST['dosage'] ) ? sanitize_text_field( wp_unslash( $_POST['dosage'] ) ) : '',
			'frequency'     => isset( $_POST['frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['frequency'] ) ) : '',
			'duration'      => isset( $_POST['duration'] ) ? sanitize_text_field( wp_unslash( $_POST['duration'] ) ) : '',
			'instructions'  => isset( $_POST['instructions'] ) ? sanitize_text_field( wp_unslash( $_POST['instructions'] ) ) : '',
		);

		$prescription_id = isset( $_POST['prescription_id'] ) ? absint( wp_unslash( $_POST['prescription_id'] ) ) : 0;

		if ( $prescription_id > 0 ) {
			Encounter_Prescriptions::update( $prescription_id, $encounter['id'], $fields );
		} else {
			Encounter_Prescriptions::add( $encounter['id'], $fields );
		}

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: deletes a Prescription row from an encounter.
	 *
	 * @return void
	 */
	public function handle_delete_prescription() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		$prescription_id = isset( $_POST['prescription_id'] ) ? absint( wp_unslash( $_POST['prescription_id'] ) ) : 0;

		Encounter_Prescriptions::delete( $prescription_id, $encounter['id'] );

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: adds a Bill item row to an encounter — either a charge
	 * for one of the doctor's own Services (service_id > 0, description/
	 * amount are taken from the service record itself, same "snapshot"
	 * pattern handle_add_prescription() uses for a picked medicine) or a
	 * free-typed one-off charge (service_id = 0). An optional discount
	 * (0-100%) applies to this one line item — see Encounter_Bill_Items's
	 * own docblock for how it's applied.
	 *
	 * @return void
	 */
	public function handle_add_bill_item() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		$service_id       = isset( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0;
		$description      = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$amount           = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;
		$discount_percent = isset( $_POST['discount_percent'] ) ? (float) wp_unslash( $_POST['discount_percent'] ) : 0;

		if ( $service_id > 0 ) {
			$service = Services::find( $service_id );

			if ( $service && (int) $service['doctor_id'] === (int) $encounter['doctor_id'] ) {
				$description = $service['name'];
				$amount      = (float) $service['charge'];
			}
		}

		if ( '' === $description ) {
			wp_send_json_error( array( 'message' => __( 'Please describe this charge.', 'doctor-ak-portal' ) ) );
		}

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid amount.', 'doctor-ak-portal' ) ) );
		}

		if ( $discount_percent < 0 || $discount_percent > 100 ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid discount between 0 and 100%.', 'doctor-ak-portal' ) ) );
		}

		Encounter_Bill_Items::add( $encounter['id'], $description, $amount, $discount_percent );

		// A closed encounter's extra charges were already posted to the
		// revenue ledger once, at close time (Revenue_Ledger::
		// post_for_encounter_extra(), hooked to 'doctor_ak_encounter_closed')
		// — editing the bill afterwards needs to explicitly re-sync that
		// posted figure so billing doesn't drift from what's actually
		// charged. A still-open encounter's bill items are left alone here;
		// they'll be posted for the first time when it does close.
		if ( Encounters::STATUS_CLOSED === $encounter['status'] ) {
			Revenue_Ledger::resync_encounter_extra( $encounter['id'], $encounter['appointment_id'] );
		}

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: deletes a Bill item row from an encounter.
	 *
	 * @return void
	 */
	public function handle_delete_bill_item() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;

		Encounter_Bill_Items::delete( $item_id, $encounter['id'] );

		// See the matching comment in handle_add_bill_item() — keeps a
		// closed encounter's posted ledger figure in sync after a bill edit.
		if ( Encounters::STATUS_CLOSED === $encounter['status'] ) {
			Revenue_Ledger::resync_encounter_extra( $encounter['id'], $encounter['appointment_id'] );
		}

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: uploads a report file (lab result, scan, etc.) and
	 * attaches it to an encounter.
	 *
	 * @return void
	 */
	public function handle_upload_report() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		if ( empty( $_FILES['report'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was received.', 'doctor-ak-portal' ) ) );
		}

		$attachment_id = $this->report_uploader->upload( $_FILES['report'], get_current_user_id() );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		Encounter_Reports::add( $encounter['id'], $attachment_id, get_current_user_id() );

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: deletes a report file from an encounter.
	 *
	 * @return void
	 */
	public function handle_delete_report() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		$report_id = isset( $_POST['report_id'] ) ? absint( wp_unslash( $_POST['report_id'] ) ) : 0;

		Encounter_Reports::delete( $report_id, $encounter['id'] );

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: permanently deletes an encounter (admin/Receptionist —
	 * the encounters list, not the doctor-facing detail screen).
	 *
	 * @return void
	 */
	public function handle_delete_encounter() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();

		if ( ! Encounters::delete( $encounter['id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The encounter could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Encounter deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Builds the "Download Prescription PDF" URL for one encounter.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return string
	 */
	public static function prescription_pdf_download_url( $encounter_id ) {
		return add_query_arg(
			array(
				'action'       => 'doctor_ak_prescription_pdf_download',
				'encounter_id' => (int) $encounter_id,
				'nonce'        => wp_create_nonce( self::PRESCRIPTION_PDF_NONCE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * AJAX handler (GET): streams an encounter's prescription as a PDF download.
	 *
	 * @return void
	 */
	public function handle_download_prescription_pdf() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::PRESCRIPTION_PDF_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) );
		}

		$encounter_id = isset( $_GET['encounter_id'] ) ? absint( wp_unslash( $_GET['encounter_id'] ) ) : 0;
		$encounter    = $encounter_id > 0 ? Encounters::find( $encounter_id ) : null;

		if ( empty( $encounter ) || ! self::can_manage_encounter( $encounter ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'doctor-ak-portal' ) );
		}

		$appointment = Appointments::notification_data( $encounter['appointment_id'] );

		if ( empty( $appointment ) ) {
			wp_die( esc_html__( 'That appointment could not be found.', 'doctor-ak-portal' ) );
		}

		$pdf_bytes = Prescription_Pdf::build(
			$appointment,
			$encounter,
			Encounter_Problems::for_encounter( $encounter_id ),
			Encounter_Prescriptions::for_encounter( $encounter_id )
		);

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="prescription-' . $encounter_id . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_bytes ) );

		echo $pdf_bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw binary PDF bytes, not HTML output.

		exit;
	}

	/**
	 * Builds the "Download Bill PDF" URL for one encounter.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return string
	 */
	public static function bill_pdf_download_url( $encounter_id ) {
		return add_query_arg(
			array(
				'action'       => 'doctor_ak_encounter_bill_pdf_download',
				'encounter_id' => (int) $encounter_id,
				'nonce'        => wp_create_nonce( self::BILL_PDF_NONCE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * AJAX handler (GET): streams an encounter's bill as a PDF download.
	 *
	 * @return void
	 */
	public function handle_download_bill_pdf() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::BILL_PDF_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) );
		}

		$encounter_id = isset( $_GET['encounter_id'] ) ? absint( wp_unslash( $_GET['encounter_id'] ) ) : 0;
		$encounter    = $encounter_id > 0 ? Encounters::find( $encounter_id ) : null;

		if ( empty( $encounter ) || ! self::can_manage_encounter( $encounter ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'doctor-ak-portal' ) );
		}

		$appointment = Appointments::notification_data( $encounter['appointment_id'] );

		if ( empty( $appointment ) ) {
			wp_die( esc_html__( 'That appointment could not be found.', 'doctor-ak-portal' ) );
		}

		$pdf_bytes = Encounter_Bill_Pdf::build(
			$appointment,
			$encounter,
			Encounter_Bill_Items::for_encounter( $encounter_id )
		);

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="bill-' . $encounter_id . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_bytes ) );

		echo $pdf_bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw binary PDF bytes, not HTML output.

		exit;
	}

	/**
	 * Loads and authorizes the encounter named by $_POST['encounter_id'],
	 * halting the request with a JSON error if it doesn't exist or the
	 * current user can't manage it. Shared by every POST handler above that
	 * acts on an existing encounter.
	 *
	 * @return array Decoded encounter row.
	 */
	private static function authorized_encounter_from_request() {
		$encounter_id = isset( $_POST['encounter_id'] ) ? absint( wp_unslash( $_POST['encounter_id'] ) ) : 0;
		$encounter    = $encounter_id > 0 ? Encounters::find( $encounter_id ) : null;

		if ( empty( $encounter ) ) {
			wp_send_json_error( array( 'message' => __( 'That encounter could not be found.', 'doctor-ak-portal' ) ) );
		}

		if ( ! self::can_manage_encounter( $encounter ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		return $encounter;
	}

	/**
	 * Whether the current user may check a patient in / manage an encounter
	 * for a given doctor's appointment — the treating doctor themselves, or
	 * an administrator/Receptionist (same audience already trusted to
	 * manage appointments generally, see doctor_ak_manage_appointments).
	 *
	 * @param int $doctor_id Appointment's/encounter's doctor ID.
	 * @return bool
	 */
	private static function can_manage_appointment( $doctor_id ) {
		return current_user_can( 'manage_options' )
			|| current_user_can( 'doctor_ak_manage_appointments' )
			|| (int) $doctor_id === get_current_user_id();
	}

	/**
	 * @param array $encounter Decoded encounter row.
	 * @return bool
	 */
	private static function can_manage_encounter( array $encounter ) {
		return self::can_manage_appointment( $encounter['doctor_id'] );
	}

	/**
	 * The full view-model for the encounter detail screen — see
	 * handle_get_encounter()'s docblock.
	 *
	 * @param array $encounter Decoded encounter row.
	 * @return array
	 */
	private static function encounter_view_model( array $encounter ) {
		$appointment = Appointments::notification_data( $encounter['appointment_id'] );
		$clinic      = $encounter['clinic_id'] > 0 ? Clinics::find( $encounter['clinic_id'] ) : null;

		$bill_items = Encounter_Bill_Items::for_encounter( $encounter['id'] );
		$bill_total = (float) ( isset( $appointment['charge'] ) ? $appointment['charge'] : 0 ) + Encounter_Bill_Items::total_for_encounter( $encounter['id'] );

		$services = array_values(
			array_filter(
				Services::get_for_doctor( $encounter['doctor_id'], null ),
				function ( $service ) {
					return ! empty( $service['active'] );
				}
			)
		);

		return array(
			'encounter'     => array(
				'id'            => $encounter['id'],
				'status'        => $encounter['status'],
				'clinic_name'   => $clinic ? $clinic['name'] : '',
				'checked_in_at' => $encounter['checked_in_at'],
				'legacy_note'   => $encounter['legacy_note'],
			),
			'appointment'   => $appointment,
			'problems'      => Encounter_Problems::for_encounter( $encounter['id'] ),
			'prescriptions' => Encounter_Prescriptions::for_encounter( $encounter['id'] ),
			'bill_items'    => $bill_items,
			'bill_total'    => $bill_total,
			'services'      => $services,
			'reports'       => Encounter_Reports::for_encounter( $encounter['id'] ),
			'medicines'     => Medicines::active_for_doctor( $encounter['doctor_id'] ),
			'prescription_pdf_url' => self::prescription_pdf_download_url( $encounter['id'] ),
			'bill_pdf_url'          => self::bill_pdf_download_url( $encounter['id'] ),
		);
	}
}
