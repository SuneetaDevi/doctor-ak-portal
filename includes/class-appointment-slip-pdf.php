<?php
/**
 * Builds a single appointment's printable "slip" as a standalone PDF — same
 * structured layout as Invoice_Pdf/Encounter_Bill_Pdf (logo header, a
 * reference/date block, a meta table, then a description+amount line and
 * total) instead of the old plain-HTML print page, for the admin
 * Appointments table's "Print" action.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

use DoctorAKPortal\Frontend\Site_Footer;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Appointment_Slip_Pdf
 *
 * Builds this one slip layout on top of Pdf_Document's dependency-free
 * PDF-writing primitives (see that class for why — no Composer/vendor
 * setup, so no general-purpose PDF library like Dompdf/mPDF is available).
 */
class Appointment_Slip_Pdf extends Pdf_Document {

	/**
	 * Builds the slip PDF for one appointment.
	 *
	 * @param array  $appointment  Row from Appointments::find().
	 * @param string $patient_name Resolved patient/guest display name.
	 * @param string $doctor_name  Resolved doctor display name (no "Dr." prefix).
	 * @return string Raw PDF file bytes.
	 */
	public static function build( array $appointment, $patient_name, $doctor_name ) {
		$clinic_name    = get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' );
		$clinic_address = get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' );
		$clinic_phone   = get_option( Site_Footer::OPTION_CLINIC_PHONE, '' );
		$slip_number    = sprintf( 'SLIP-%05d', (int) $appointment['id'] );
		$slip_date      = date_i18n( get_option( 'date_format' ) );

		$type_label         = Appointments::TYPE_VIDEO === $appointment['type'] ? __( 'Online Video Consultation', 'doctor-ak-portal' ) : __( 'Clinic Appointment', 'doctor-ak-portal' );
		$service_label      = '' !== $appointment['service_name'] ? $appointment['service_name'] : $type_label;
		$charge             = (float) $appointment['charge'];
		$is_paid            = Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'];
		$payment_mode_label = Appointments::PAYMENT_MODE_ONLINE === $appointment['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : __( 'Manual', 'doctor-ak-portal' );
		$datetime_label     = date_i18n( 'd/m/Y h:i A', strtotime( $appointment['date'] . ' ' . $appointment['time'] ) );
		$amount_label       = $charge > 0 ? 'PKR ' . number_format( $charge, 0 ) : __( 'Free', 'doctor-ak-portal' );

		$logo = self::load_logo_jpeg();

		$left   = 50;
		$right  = self::PAGE_WIDTH - 50;
		$y      = self::PAGE_HEIGHT - 60;
		$stream = '';

		$text_x = $left;

		if ( $logo ) {
			$stream .= self::draw_image( 'Im1', $left, $y - $logo['height'] + 10, $logo['width'], $logo['height'] );
			$text_x  = $left + $logo['width'] + 15;
		}

		$stream .= self::draw_text( $text_x, $y, 'F2', 14, $clinic_name );
		$y      -= 16;

		if ( '' !== $clinic_address ) {
			$stream .= self::draw_text( $text_x, $y, 'F1', 9, $clinic_address, 0.4 );
			$y      -= 12;
		}

		if ( '' !== $clinic_phone ) {
			$stream .= self::draw_text( $text_x, $y, 'F1', 9, $clinic_phone, 0.4 );
			$y      -= 12;
		}

		// "APPOINTMENT SLIP" block, top-right.
		$header_block_y = self::PAGE_HEIGHT - 60;
		$stream        .= self::draw_text_right( $right, $header_block_y, 'F2', 14, __( 'APPOINTMENT SLIP', 'doctor-ak-portal' ) );
		$stream        .= self::draw_text_right( $right, $header_block_y - 16, 'F1', 9, $slip_number, 0.4 );
		$stream        .= self::draw_text_right( $right, $header_block_y - 28, 'F1', 9, $slip_date, 0.4 );

		$y = min( $y, $header_block_y - 28 ) - 40;

		$stream .= self::draw_text( $left, $y, 'F1', 8, strtoupper( __( 'Patient', 'doctor-ak-portal' ) ), 0.4 );
		$y      -= 14;
		$stream .= self::draw_text( $left, $y, 'F2', 11, $patient_name );
		$y      -= 28;

		// Meta rows — label left, value right.
		$meta_rows = array(
			array( __( 'Doctor', 'doctor-ak-portal' ), sprintf( /* translators: %s: doctor's display name. */ __( 'Dr. %s', 'doctor-ak-portal' ), $doctor_name ) ),
			array( __( 'Type', 'doctor-ak-portal' ), $type_label ),
			array( __( 'Date & Time', 'doctor-ak-portal' ), $datetime_label ),
			array( __( 'Payment Mode', 'doctor-ak-portal' ), $payment_mode_label ),
			array( __( 'Payment Status', 'doctor-ak-portal' ), $is_paid ? __( 'Paid', 'doctor-ak-portal' ) : __( 'Pending', 'doctor-ak-portal' ) ),
		);

		foreach ( $meta_rows as $meta_row ) {
			$stream .= self::draw_text( $left, $y, 'F1', 9, $meta_row[0], 0.45 );
			$stream .= self::draw_text_right( $right, $y, 'F2', 9, $meta_row[1] );
			$y      -= 17;
		}

		$y -= 8;

		// Description/amount table, matching Invoice_Pdf/Encounter_Bill_Pdf.
		$stream .= self::draw_line( $left, $y, $right, $y, 0.1, 0.1, 0.1, 1.2 );
		$y      -= 14;
		$stream .= self::draw_text( $left, $y, 'F2', 9, strtoupper( __( 'Description', 'doctor-ak-portal' ) ) );
		$stream .= self::draw_text_right( $right, $y, 'F2', 9, strtoupper( __( 'Amount', 'doctor-ak-portal' ) ) );
		$y      -= 8;
		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 16;

		$stream .= self::draw_text( $left, $y, 'F1', 10, $service_label );
		$stream .= self::draw_text_right( $right, $y, 'F1', 10, $amount_label );
		$y      -= 16;

		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 20;

		$stream .= self::draw_text_right( $right - 90, $y, 'F2', 11, __( 'Total', 'doctor-ak-portal' ) );
		$stream .= self::draw_text_right( $right, $y, 'F2', 11, $amount_label );
		$y      -= 40;

		$stream .= self::draw_text( $left, $y, 'F1', 9, __( 'Thank you for choosing us — we look forward to seeing you.', 'doctor-ak-portal' ), 0.4 );

		return self::assemble_single_page( $stream, $logo );
	}
}
