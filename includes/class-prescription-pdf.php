<?php
/**
 * Builds an encounter's prescription as a standalone PDF file.
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
 * Class Prescription_Pdf
 *
 * Builds this one prescription layout on top of Pdf_Document's
 * dependency-free PDF-writing primitives (see that class for why).
 */
class Prescription_Pdf extends Pdf_Document {

	/**
	 * Builds the prescription PDF for one encounter.
	 *
	 * @param array $appointment   Row from Appointments::notification_data() (or ::get(), with patient_name/doctor_name resolved).
	 * @param array $encounter     Row from Encounters::find().
	 * @param array $problems      Rows from Encounter_Problems::for_encounter().
	 * @param array $prescriptions Rows from Encounter_Prescriptions::for_encounter().
	 * @return string Raw PDF file bytes.
	 */
	public static function build( array $appointment, array $encounter, array $problems, array $prescriptions ) {
		$clinic_name    = get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' );
		$clinic_address = get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' );
		$clinic_phone   = get_option( Site_Footer::OPTION_CLINIC_PHONE, '' );
		$visit_date     = date_i18n( 'd/m/Y h:i A', strtotime( $encounter['checked_in_at'] ) );

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

		// "Rx" block, top-right.
		$rx_block_y = self::PAGE_HEIGHT - 60;
		$stream    .= self::draw_text_right( $right, $rx_block_y, 'F2', 18, __( 'Rx', 'doctor-ak-portal' ) );
		$stream    .= self::draw_text_right( $right, $rx_block_y - 20, 'F1', 9, $visit_date, 0.4 );

		$y = min( $y, $rx_block_y - 20 ) - 30;

		$stream .= self::draw_line( $left, $y, $right, $y, 0.1, 0.1, 0.1, 1.2 );
		$y      -= 16;

		$stream .= self::draw_text( $left, $y, 'F1', 8, strtoupper( __( 'Patient', 'doctor-ak-portal' ) ), 0.4 );
		$stream .= self::draw_text_right( $right, $y, 'F1', 8, strtoupper( __( 'Doctor', 'doctor-ak-portal' ) ), 0.4 );
		$y      -= 14;
		$stream .= self::draw_text( $left, $y, 'F2', 11, $appointment['patient_name'] );
		/* translators: %s: doctor's display name. */
		$stream .= self::draw_text_right( $right, $y, 'F2', 11, sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $appointment['doctor_name'] ) );
		$y      -= 30;

		if ( ! empty( $problems ) ) {
			$stream .= self::draw_text( $left, $y, 'F2', 10, strtoupper( __( 'Diagnosis', 'doctor-ak-portal' ) ) );
			$y      -= 14;

			foreach ( $problems as $problem ) {
				$line = $problem['description'];

				if ( '' !== $problem['notes'] ) {
					$line .= ' — ' . $problem['notes'];
				}

				$stream .= self::draw_text( $left, $y, 'F1', 9, '• ' . $line, 0.2 );
				$y      -= 13;
			}

			$y -= 14;
		}

		$stream .= self::draw_text( $left, $y, 'F2', 10, strtoupper( __( 'Medicines', 'doctor-ak-portal' ) ) );
		$y      -= 8;
		$y      -= 8;
		$stream .= self::draw_line( $left, $y, $right, $y, 0.1, 0.1, 0.1, 1.2 );
		$y      -= 14;

		$col_name         = $left;
		$col_dosage        = $left + 150;
		$col_frequency     = $left + 240;
		$col_duration      = $left + 340;
		$col_instructions  = $left + 410;

		$stream .= self::draw_table_row(
			array(
				array( 'x' => $col_name, 'text' => strtoupper( __( 'Medicine', 'doctor-ak-portal' ) ) ),
				array( 'x' => $col_dosage, 'text' => strtoupper( __( 'Dosage', 'doctor-ak-portal' ) ) ),
				array( 'x' => $col_frequency, 'text' => strtoupper( __( 'Frequency', 'doctor-ak-portal' ) ) ),
				array( 'x' => $col_duration, 'text' => strtoupper( __( 'Duration', 'doctor-ak-portal' ) ) ),
				array( 'x' => $col_instructions, 'text' => strtoupper( __( 'Notes', 'doctor-ak-portal' ) ) ),
			),
			$y,
			'F2',
			8
		);
		$y -= 6;
		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 16;

		if ( empty( $prescriptions ) ) {
			$stream .= self::draw_text( $left, $y, 'F1', 9, __( 'No medicines prescribed.', 'doctor-ak-portal' ), 0.4 );
			$y      -= 16;
		} else {
			foreach ( $prescriptions as $prescription ) {
				$stream .= self::draw_table_row(
					array(
						array( 'x' => $col_name, 'text' => $prescription['medicine_name'] ),
						array( 'x' => $col_dosage, 'text' => $prescription['dosage'] ),
						array( 'x' => $col_frequency, 'text' => $prescription['frequency'] ),
						array( 'x' => $col_duration, 'text' => $prescription['duration'] ),
						array( 'x' => $col_instructions, 'text' => $prescription['instructions'] ),
					),
					$y,
					'F1',
					9
				);
				$y -= 18;
			}
		}

		$y -= 30;
		$stream .= self::draw_line( $right - 160, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 12;
		/* translators: %s: doctor's display name. */
		$stream .= self::draw_text_right( $right, $y, 'F1', 9, sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $appointment['doctor_name'] ), 0.4 );

		return self::assemble_single_page( $stream, $logo );
	}
}
