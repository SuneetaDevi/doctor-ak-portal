<?php
/**
 * Builds an encounter's bill as a standalone PDF file.
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
 * Class Encounter_Bill_Pdf
 *
 * Builds this one bill layout on top of Pdf_Document's dependency-free
 * PDF-writing primitives (see that class for why). Unlike Invoice_Pdf
 * (which only ever bills the appointment's own charge), an encounter's
 * bill starts with that same charge as its first line, then lists whatever
 * extra Encounter_Bill_Items rows were added during the visit, then a
 * total — see Encounter_Bill_Items::total_for_encounter().
 */
class Encounter_Bill_Pdf extends Pdf_Document {

	/**
	 * Builds the bill PDF for one encounter.
	 *
	 * @param array $appointment Row from Appointments::notification_data() (or ::get(), with patient_name/doctor_name resolved).
	 * @param array $encounter   Row from Encounters::find().
	 * @param array $bill_items  Rows from Encounter_Bill_Items::for_encounter().
	 * @return string Raw PDF file bytes.
	 */
	public static function build( array $appointment, array $encounter, array $bill_items ) {
		$clinic_name    = get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' );
		$clinic_address = get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' );
		$clinic_phone   = get_option( Site_Footer::OPTION_CLINIC_PHONE, '' );
		$bill_number    = sprintf( 'BILL-%05d', (int) $encounter['id'] );
		$visit_date     = date_i18n( 'd/m/Y h:i A', strtotime( $encounter['checked_in_at'] ) );

		$service_label = '' !== $appointment['service_name'] ? $appointment['service_name'] : $appointment['type_label'];
		$appt_charge   = (float) $appointment['charge'];

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

		// "BILL" block, top-right.
		$bill_block_y = self::PAGE_HEIGHT - 60;
		$stream      .= self::draw_text_right( $right, $bill_block_y, 'F2', 14, __( 'BILL', 'doctor-ak-portal' ) );
		$stream      .= self::draw_text_right( $right, $bill_block_y - 16, 'F1', 9, $bill_number, 0.4 );
		$stream      .= self::draw_text_right( $right, $bill_block_y - 28, 'F1', 9, $visit_date, 0.4 );

		$y = min( $y, $bill_block_y - 28 ) - 40;

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

		$total = 0.0;

		if ( $appt_charge > 0 ) {
			$stream .= self::draw_table_row(
				array(
					array( 'x' => $left, 'text' => $service_label ),
					array(
						'x'     => $right,
						'text'  => 'PKR ' . number_format( $appt_charge, 0 ),
						'align' => 'right',
					),
				),
				$y,
				'F1',
				10
			);
			$y     -= 18;
			$total += $appt_charge;
		}

		foreach ( $bill_items as $item ) {
			$stream .= self::draw_table_row(
				array(
					array( 'x' => $left, 'text' => $item['description'] ),
					array(
						'x'     => $right,
						'text'  => 'PKR ' . number_format( (float) $item['amount'], 0 ),
						'align' => 'right',
					),
				),
				$y,
				'F1',
				10
			);
			$y     -= 18;
			$total += (float) $item['amount'];
		}

		$y -= 4;
		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 20;

		$stream .= self::draw_text_right( $right - 90, $y, 'F2', 11, __( 'Total', 'doctor-ak-portal' ) );
		$stream .= self::draw_text_right( $right, $y, 'F2', 11, 'PKR ' . number_format( $total, 0 ) );
		$y      -= 40;

		$stream .= self::draw_text( $left, $y, 'F1', 9, __( 'Thank you for choosing us — we look forward to seeing you.', 'doctor-ak-portal' ), 0.4 );

		return self::assemble_single_page( $stream, $logo );
	}
}
