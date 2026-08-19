<?php
/**
 * AJAX handlers backing the admin dashboard's Billing "Settlement" panel —
 * creating a settlement for a doctor's outstanding revenue-ledger balance,
 * and marking one Paid/Received. Admin-only (settlements move real money;
 * a doctor only ever sees their own settlement history, read-only, on
 * their own Earnings tab).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Doctor_Statement_Pdf;
use DoctorAKPortal\Includes\Revenue_Calculator;
use DoctorAKPortal\Includes\Revenue_Ledger;
use DoctorAKPortal\Includes\Settlement_Manager;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settlement_Handler {

	/**
	 * Nonce action for the "Download statement" link (a GET request, not an
	 * AJAX POST — mirrors Appointment_Handler::INVOICE_NONCE_ACTION's pattern).
	 *
	 * @var string
	 */
	const STATEMENT_NONCE_ACTION = 'doctor_ak_admin_statement_download';

	/**
	 * Builds the "Download statement" URL for one doctor's Billing summary
	 * row — a GET link (not an AJAX POST) so it can be a plain anchor,
	 * mirroring Appointment_Handler::invoice_download_url().
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $date_from 'YYYY-MM-DD', or '' for no lower bound.
	 * @param string $date_to   'YYYY-MM-DD', or '' for no upper bound.
	 * @return string
	 */
	public static function statement_download_url( $doctor_id, $date_from = '', $date_to = '' ) {
		return add_query_arg(
			array(
				'action'    => 'doctor_ak_admin_statement_download',
				'doctor_id' => (int) $doctor_id,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'nonce'     => wp_create_nonce( self::STATEMENT_NONCE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * AJAX handler: the per-service breakdown for one doctor+clinic pairing
	 * (the Billing summary's "View Details" panel).
	 *
	 * @return void
	 */
	public function handle_get_doctor_clinic_details() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( wp_unslash( $_POST['doctor_id'] ) ) : 0;
		$clinic_id = isset( $_POST['clinic_id'] ) ? (int) wp_unslash( $_POST['clinic_id'] ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- cast to int immediately after.
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		$doctor = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor ) {
			wp_send_json_error( array( 'message' => __( 'That doctor could not be found.', 'doctor-ak-portal' ) ) );
		}

		$clinic_name = __( 'Video Consultation', 'doctor-ak-portal' );

		if ( $clinic_id > 0 ) {
			$clinic      = Clinics::find( $clinic_id );
			$clinic_name = $clinic ? $clinic['name'] : __( 'Clinic', 'doctor-ak-portal' );
		}

		wp_send_json_success(
			array(
				'clinic_name' => $clinic_name,
				'items'       => Revenue_Ledger::service_breakdown_for_doctor_clinic( $doctor_id, $clinic_id, $date_from, $date_to ),
			)
		);
	}

	/**
	 * AJAX handler (GET): streams one doctor's revenue statement PDF — every
	 * clinic (and video) they earned from in the given period as separate
	 * line items, plus an appointments list. See Doctor_Statement_Pdf.
	 *
	 * @return void
	 */
	public function handle_download_statement() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'doctor-ak-portal' ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::STATEMENT_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) );
		}

		$doctor_id = isset( $_GET['doctor_id'] ) ? absint( wp_unslash( $_GET['doctor_id'] ) ) : 0;
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		$doctor = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor ) {
			wp_die( esc_html__( 'That doctor could not be found.', 'doctor-ak-portal' ) );
		}

		$balances = Revenue_Ledger::balances_by_doctor_and_clinic(
			array(
				'doctor_id' => $doctor_id,
				'date_from' => $date_from,
				'date_to'   => $date_to,
			)
		);

		$line_items = array_map(
			function ( $row ) {
				if ( 0 === $row['clinic_id'] ) {
					$row['label'] = __( 'Video Consultation', 'doctor-ak-portal' );
					return $row;
				}
				$clinic       = Clinics::find( $row['clinic_id'] );
				$row['label'] = $clinic ? $clinic['name'] : __( 'Clinic', 'doctor-ak-portal' );
				return $row;
			},
			$balances
		);

		$ledger_rows = Revenue_Ledger::all_flat_for_admin(
			array(
				'doctor_id' => $doctor_id,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'number'    => 100000,
			)
		);

		$period_label = '' !== $date_from || '' !== $date_to
			? trim( $date_from . ' – ' . $date_to, ' –' )
			: __( 'All time', 'doctor-ak-portal' );

		$pdf_bytes         = Doctor_Statement_Pdf::build( $doctor, $line_items, $ledger_rows, $period_label );
		$statement_number  = sprintf( 'STMT-%05d-%s', $doctor_id, gmdate( 'Ymd' ) );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $statement_number . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_bytes ) );

		echo $pdf_bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw binary PDF bytes, not HTML output.

		exit;
	}

	/**
	 * AJAX handler: saves the video platform/gateway fee (percentage + flat)
	 * applied to video_consultation ledger entries. Part of the admin
	 * dashboard's unified "Save Settings" button.
	 *
	 * @return void
	 */
	public function handle_save_platform_fee() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$percent = isset( $_POST['fee_percent'] ) ? (float) wp_unslash( $_POST['fee_percent'] ) : 0;
		$flat    = isset( $_POST['fee_flat'] ) ? (float) wp_unslash( $_POST['fee_flat'] ) : 0;

		if ( $percent < 0 || $percent > 100 ) {
			wp_send_json_error( array( 'errors' => array( 'fee_percent' => __( 'Percentage must be between 0 and 100.', 'doctor-ak-portal' ) ) ) );
		}

		if ( $flat < 0 ) {
			wp_send_json_error( array( 'errors' => array( 'fee_flat' => __( 'Flat fee cannot be negative.', 'doctor-ak-portal' ) ) ) );
		}

		Revenue_Calculator::save_video_platform_fee_settings( $percent, $flat );

		wp_send_json_success( array( 'message' => __( 'Platform fee settings saved.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: creates a settlement covering a doctor's currently
	 * outstanding revenue-ledger balance within a period.
	 *
	 * @return void
	 */
	public function handle_create() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( wp_unslash( $_POST['doctor_id'] ) ) : 0;
		$doctor    = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor ) {
			wp_send_json_error( array( 'message' => __( 'That doctor could not be found.', 'doctor-ak-portal' ) ) );
		}

		$period_start = isset( $_POST['period_start'] ) ? sanitize_text_field( wp_unslash( $_POST['period_start'] ) ) : '';
		$period_end   = isset( $_POST['period_end'] ) ? sanitize_text_field( wp_unslash( $_POST['period_end'] ) ) : '';

		if ( '' === $period_start || '' === $period_end || $period_start > $period_end ) {
			wp_send_json_error( array( 'errors' => array( 'period_end' => __( 'Please choose a valid date range.', 'doctor-ak-portal' ) ) ) );
		}

		$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		$result = Settlement_Manager::create( $doctor_id, $period_start, $period_end, $notes );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Settlement created.', 'doctor-ak-portal' ), 'settlement_id' => $result ) );
	}

	/**
	 * AJAX handler: marks a settlement Paid (platform paid the doctor).
	 *
	 * @return void
	 */
	public function handle_mark_paid() {
		$this->mark_resolved( 'mark_paid' );
	}

	/**
	 * AJAX handler: marks a settlement Received (platform collected from the doctor).
	 *
	 * @return void
	 */
	public function handle_mark_received() {
		$this->mark_resolved( 'mark_received' );
	}

	/**
	 * Shared implementation for handle_mark_paid()/handle_mark_received().
	 *
	 * @param string $method Settlement_Manager method to call — 'mark_paid' or 'mark_received'.
	 * @return void
	 */
	private function mark_resolved( $method ) {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$settlement_id = isset( $_POST['settlement_id'] ) ? absint( wp_unslash( $_POST['settlement_id'] ) ) : 0;

		$result = Settlement_Manager::$method( $settlement_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Settlement updated.', 'doctor-ak-portal' ) ) );
	}
}
