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
use DoctorAKPortal\Includes\Encounter_Prescriptions;
use DoctorAKPortal\Includes\Encounter_Problems;
use DoctorAKPortal\Includes\Encounters;
use DoctorAKPortal\Includes\Medicines;
use DoctorAKPortal\Includes\Prescription_Pdf;

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
	 * AJAX handler: adds a Problem row to an encounter.
	 *
	 * @return void
	 */
	public function handle_add_problem() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();
		self::require_open( $encounter );

		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( '' === $description ) {
			wp_send_json_error( array( 'message' => __( 'Please describe the problem.', 'doctor-ak-portal' ) ) );
		}

		$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		Encounter_Problems::add( $encounter['id'], $description, $notes );

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
		self::require_open( $encounter );

		$problem_id = isset( $_POST['problem_id'] ) ? absint( wp_unslash( $_POST['problem_id'] ) ) : 0;

		Encounter_Problems::delete( $problem_id, $encounter['id'] );

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: adds a Prescription row to an encounter. medicine_id may
	 * be 0 (the doctor typed the medicine name directly instead of picking
	 * from the Medicines list).
	 *
	 * @return void
	 */
	public function handle_add_prescription() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();
		self::require_open( $encounter );

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

		Encounter_Prescriptions::add(
			$encounter['id'],
			array(
				'medicine_id'   => $medicine_id,
				'medicine_name' => $medicine_name,
				'dosage'        => isset( $_POST['dosage'] ) ? sanitize_text_field( wp_unslash( $_POST['dosage'] ) ) : '',
				'frequency'     => isset( $_POST['frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['frequency'] ) ) : '',
				'duration'      => isset( $_POST['duration'] ) ? sanitize_text_field( wp_unslash( $_POST['duration'] ) ) : '',
				'instructions'  => isset( $_POST['instructions'] ) ? sanitize_text_field( wp_unslash( $_POST['instructions'] ) ) : '',
			)
		);

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
		self::require_open( $encounter );

		$prescription_id = isset( $_POST['prescription_id'] ) ? absint( wp_unslash( $_POST['prescription_id'] ) ) : 0;

		Encounter_Prescriptions::delete( $prescription_id, $encounter['id'] );

		wp_send_json_success( self::encounter_view_model( $encounter ) );
	}

	/**
	 * AJAX handler: adds a Bill item row to an encounter.
	 *
	 * @return void
	 */
	public function handle_add_bill_item() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$encounter = self::authorized_encounter_from_request();
		self::require_open( $encounter );

		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$amount      = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;

		if ( '' === $description ) {
			wp_send_json_error( array( 'message' => __( 'Please describe this charge.', 'doctor-ak-portal' ) ) );
		}

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid amount.', 'doctor-ak-portal' ) ) );
		}

		Encounter_Bill_Items::add( $encounter['id'], $description, $amount );

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
		self::require_open( $encounter );

		$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;

		Encounter_Bill_Items::delete( $item_id, $encounter['id'] );

		wp_send_json_success( self::encounter_view_model( $encounter ) );
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
		header( 'Content-Disposition: attachment; filename="prescription-' . $encounter_id . '.pdf"' );
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
		header( 'Content-Disposition: attachment; filename="bill-' . $encounter_id . '.pdf"' );
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
	 * Halts the request with a JSON error if the given encounter is already
	 * closed — every mutation (adding a problem/prescription/bill item)
	 * only makes sense on an open encounter.
	 *
	 * @param array $encounter Decoded encounter row.
	 * @return void
	 */
	private static function require_open( array $encounter ) {
		if ( Encounters::STATUS_OPEN !== $encounter['status'] ) {
			wp_send_json_error( array( 'message' => __( 'This encounter is already closed.', 'doctor-ak-portal' ) ) );
		}
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
			'medicines'     => Medicines::active_for_doctor( $encounter['doctor_id'] ),
			'prescription_pdf_url' => self::prescription_pdf_download_url( $encounter['id'] ),
			'bill_pdf_url'          => self::bill_pdf_download_url( $encounter['id'] ),
		);
	}
}
