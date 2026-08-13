<?php
/**
 * Builds the patient payment invoice as a standalone PDF file, attached to
 * the invoice email (see Notifications::send_invoice()).
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
 * Class Invoice_Pdf
 *
 * Builds this one invoice layout on top of Pdf_Document's dependency-free
 * PDF-writing primitives (see that class for why — no Composer/vendor
 * setup, so no general-purpose PDF library like Dompdf/mPDF is available).
 */
class Invoice_Pdf extends Pdf_Document {

	/**
	 * Builds the invoice PDF for one appointment.
	 *
	 * @param array $appointment Row from Appointments::notification_data().
	 * @return string Raw PDF file bytes.
	 */
	public static function build( array $appointment ) {
		$clinic_name    = get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' );
		$clinic_address = get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' );
		$clinic_phone   = get_option( Site_Footer::OPTION_CLINIC_PHONE, '' );
		$invoice_number = sprintf( 'INV-%05d', (int) $appointment['id'] );
		$paid_date      = date_i18n( get_option( 'date_format' ) );
		$service_label    = '' !== $appointment['service_name'] ? $appointment['service_name'] : $appointment['type_label'];
		$charge           = (float) $appointment['charge'];
		$base_charge      = (float) $appointment['base_charge'] + (float) $appointment['surcharge'];
		$discount_percent = (int) $appointment['discount_percent'];
		$has_discount     = $discount_percent > 0 && $base_charge > $charge;

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

		// "INVOICE" block, top-right.
		$invoice_block_y = self::PAGE_HEIGHT - 60;
		$stream         .= self::draw_text_right( $right, $invoice_block_y, 'F2', 14, __( 'INVOICE', 'doctor-ak-portal' ) );
		$stream         .= self::draw_text_right( $right, $invoice_block_y - 16, 'F1', 9, $invoice_number, 0.4 );
		$stream         .= self::draw_text_right( $right, $invoice_block_y - 28, 'F1', 9, $paid_date, 0.4 );

		$y = min( $y, $invoice_block_y - 28 ) - 40;

		$stream .= self::draw_text( $left, $y, 'F1', 8, strtoupper( __( 'Billed To', 'doctor-ak-portal' ) ), 0.4 );
		$y      -= 14;
		$stream .= self::draw_text( $left, $y, 'F2', 11, $appointment['patient_name'] );
		$y      -= 30;

		// Table header.
		$stream .= self::draw_line( $left, $y, $right, $y, 0.1, 0.1, 0.1, 1.2 );
		$y      -= 14;
		$stream .= self::draw_text( $left, $y, 'F2', 9, strtoupper( __( 'Description', 'doctor-ak-portal' ) ) );
		$stream .= self::draw_text_right( $right, $y, 'F2', 9, strtoupper( __( 'Amount', 'doctor-ak-portal' ) ) );
		$y      -= 8;
		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 16;

		/* translators: 1: date/time, 2: doctor's display name. */
		$meta_line = sprintf( __( '%1$s with Dr. %2$s', 'doctor-ak-portal' ), date_i18n( 'd/m/Y h:i A', strtotime( $appointment['date'] . ' ' . $appointment['time'] ) ), $appointment['doctor_name'] );

		// Which clinic the visit was at, or that it was a video consultation
		// — no revenue-split figures here, this line is purely informational
		// for the patient (see Revenue_Ledger/Revenue_Calculator for the
		// internal doctor/clinic split, which stays admin/doctor-only).
		$location_line = Appointments::TYPE_VIDEO === $appointment['type']
			? __( 'Video Consultation', 'doctor-ak-portal' )
			: ( '' !== $appointment['clinic_label'] ? $appointment['clinic_label'] : __( 'Clinic Visit', 'doctor-ak-portal' ) );

		$stream .= self::draw_text( $left, $y, 'F1', 10, $service_label );

		if ( $has_discount ) {
			$stream .= self::draw_text_right_struck( $right, $y, 'F1', 9, 'PKR ' . number_format( $base_charge, 0 ), 0.55 );
			$y      -= 12;
			$stream .= self::draw_text_right( $right, $y, 'F2', 10, 'PKR ' . number_format( $charge, 0 ) );
			$y      -= 11;
			$stream .= self::draw_text_right( $right, $y, 'F1', 8, sprintf( '%d%% %s', $discount_percent, __( 'off', 'doctor-ak-portal' ) ), 0.35 );
			$y      -= 13;
		} else {
			$stream .= self::draw_text_right( $right, $y, 'F1', 10, 'PKR ' . number_format( $charge, 0 ) );
			$y      -= 13;
		}

		$stream .= self::draw_text( $left, $y, 'F1', 8, $meta_line, 0.4 );
		$y      -= 12;

		$stream .= self::draw_text( $left, $y, 'F1', 8, $location_line, 0.4 );
		$y      -= 16;

		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 18;

		if ( $has_discount ) {
			$stream .= self::draw_text_right( $right - 90, $y, 'F1', 9, __( 'You Saved', 'doctor-ak-portal' ), 0.3 );
			$stream .= self::draw_text_right( $right, $y, 'F1', 9, 'PKR ' . number_format( $base_charge - $charge, 0 ), 0.3 );
			$y      -= 16;
		}

		$stream .= self::draw_text_right( $right - 90, $y, 'F2', 11, __( 'Total Paid', 'doctor-ak-portal' ) );
		$stream .= self::draw_text_right( $right, $y, 'F2', 11, 'PKR ' . number_format( $charge, 0 ) );
		$y      -= 40;

		$stream .= self::draw_text( $left, $y, 'F1', 9, __( 'Thank you for choosing us — we look forward to seeing you.', 'doctor-ak-portal' ), 0.4 );

		return self::assemble_single_page( $stream, $logo );
	}
}
