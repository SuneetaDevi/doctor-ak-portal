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

		// The Amount column's own text ("PKR 12,345") needs roughly this
		// much reserved space to its left — same margin Doctor_Statement_Pdf's
		// fit_text() leaves for a right-aligned neighbor.
		$description_max_width = ( $right - $left ) - 90;

		foreach ( $bill_items as $item ) {
			// 'amount' is already the final, post-discount figure (see
			// Encounter_Bill_Items::decode_row()) — note the discount
			// alongside the description instead of a separate column, so a
			// discounted line's math is still visible on the printed bill.
			$description = ( isset( $item['discount_percent'] ) && $item['discount_percent'] > 0 )
				? sprintf(
					/* translators: 1: item description, 2: discount percent, 3: original pre-discount amount. */
					__( '%1$s (%2$s%% off PKR %3$s)', 'doctor-ak-portal' ),
					$item['description'],
					rtrim( rtrim( number_format( (float) $item['discount_percent'], 2 ), '0' ), '.' ),
					number_format( (float) $item['original_amount'], 0 )
				)
				: $item['description'];

			$description = self::fit_text( $description, 'F1', 10, $description_max_width );

			$stream .= self::draw_table_row(
				array(
					array( 'x' => $left, 'text' => $description ),
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

	/**
	 * Truncates text with '...' if it's estimated to overflow $max_width —
	 * same avg-char-width estimate Doctor_Statement_Pdf's fit_text() uses,
	 * duplicated here rather than shared since Pdf_Document itself has no
	 * text-fitting helper. Guards a discounted line's now-longer description
	 * (name + "(X% off PKR Y)") from overlapping the right-aligned Amount
	 * column.
	 *
	 * @param string $text      Text to fit.
	 * @param string $font      'F1' (regular) or 'F2' (bold).
	 * @param int    $size      Font size.
	 * @param float  $max_width Available width, in PDF points.
	 * @return string
	 */
	private static function fit_text( $text, $font, $size, $max_width ) {
		if ( $max_width <= 0 ) {
			return $text;
		}

		$bold            = 'F2' === $font;
		$avg_char_width  = $size * ( $bold ? 0.60 : 0.52 );
		$estimated_width = self::mb_strlen_safe( $text ) * $avg_char_width;

		if ( $estimated_width <= $max_width ) {
			return $text;
		}

		$max_chars = max( 1, (int) floor( $max_width / $avg_char_width ) - 3 );

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_chars ) . '...' : substr( $text, 0, $max_chars ) . '...';
	}
}
