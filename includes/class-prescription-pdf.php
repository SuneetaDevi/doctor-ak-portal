<?php
/**
 * Builds an encounter's prescription as a standalone PDF file.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prescription_Pdf
 *
 * Clinical readability first: patient, prescribing doctor and place of care
 * at the top; the recorded problems with their notes; then the medicines
 * table (medicine, dose, frequency, duration) with each medicine's
 * instructions on a full-width line beneath it, so long instructions wrap
 * instead of being squeezed or cut off. Every clinical value is printed
 * exactly as recorded. The signature line is a place to sign by hand — no
 * signature, stamp or registration number is printed unless the record
 * holds one (it currently has no such fields).
 */
class Prescription_Pdf extends Pdf_Document {

	/**
	 * Builds the prescription PDF for one encounter.
	 *
	 * @param array $appointment   Row from Appointments::notification_data() — or, when that appointment no longer exists, at least patient_name/doctor_name.
	 * @param array $encounter     Row from Encounters::find().
	 * @param array $problems      Rows from Encounter_Problems::for_encounter().
	 * @param array $prescriptions Rows from Encounter_Prescriptions::for_encounter().
	 * @return string Raw PDF file bytes.
	 */
	public static function build( array $appointment, array $encounter, array $problems, array $prescriptions ) {
		$get = function ( $key, $default = '' ) use ( $appointment ) {
			return isset( $appointment[ $key ] ) ? $appointment[ $key ] : $default;
		};

		$encounter_ref = sprintf( 'ENC-%04d', (int) $encounter['id'] );
		$visit_ts      = strtotime( (string) $encounter['checked_in_at'] );
		$doctor_id     = (int) $get( 'doctor_id', isset( $encounter['doctor_id'] ) ? $encounter['doctor_id'] : 0 );
		$patient_id    = (int) $get( 'patient_id', isset( $encounter['patient_id'] ) ? $encounter['patient_id'] : 0 );
		$clinic_id     = (int) $get( 'clinic_id', isset( $encounter['clinic_id'] ) ? $encounter['clinic_id'] : 0 );
		$type          = (string) $get( 'type', $clinic_id > 0 ? Appointments::TYPE_CLINIC : '' );

		$pdf = new Pdf_Builder( __( 'Prescription', 'doctor-ak-portal' ), $encounter_ref );

		$pdf->header(
			array(
				array( __( 'Encounter', 'doctor-ak-portal' ), $encounter_ref ),
				array( __( 'Visit date', 'doctor-ak-portal' ), false !== $visit_ts ? date_i18n( 'd M Y, h:i A', $visit_ts ) : '' ),
				array( __( 'Issued', 'doctor-ak-portal' ), date_i18n( 'd M Y' ) ),
			)
		);

		$doctor_lines = Pdf_Builder::doctor_lines( $doctor_id, $get( 'doctor_name' ) );

		$pdf->people(
			array(
				array(
					'label' => __( 'Patient', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::patient_lines( $get( 'patient_name' ), $patient_id, $get( 'patient_age' ), $get( 'patient_phone' ) ),
				),
				array(
					'label' => __( 'Prescribing doctor', 'doctor-ak-portal' ),
					'lines' => $doctor_lines,
				),
				array(
					'label' => Appointments::TYPE_VIDEO === $type ? __( 'Location', 'doctor-ak-portal' ) : __( 'Clinic', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::place_lines( $type, $clinic_id, $get( 'clinic_name' ), $get( 'clinic_address' ) ),
				),
			)
		);

		$pdf->section( __( 'Diagnosis & problems', 'doctor-ak-portal' ) );

		if ( empty( $problems ) ) {
			$pdf->paragraph( __( 'No problems recorded for this visit.', 'doctor-ak-portal' ), 'muted' );
		} else {
			foreach ( array_values( $problems ) as $index => $problem ) {
				$pdf->list_item( ( $index + 1 ) . '.', $problem['description'], 'strong' );

				if ( '' !== trim( (string) $problem['notes'] ) ) {
					$pdf->paragraph( $problem['notes'], 'body', $pdf->indent_x(), $pdf->width - 18 );
				} else {
					$pdf->space( 4 );
				}
			}
		}

		$pdf->space( 6 );
		$pdf->section( __( 'Medicines (Rx)', 'doctor-ak-portal' ), 60 );

		if ( empty( $prescriptions ) ) {
			$pdf->paragraph( __( 'No medicines prescribed.', 'doctor-ak-portal' ), 'muted' );
		} else {
			$rows = array();

			foreach ( array_values( $prescriptions ) as $index => $prescription ) {
				$rows[] = array(
					'cells'      => array(
						(string) ( $index + 1 ),
						array( 'text' => $prescription['medicine_name'], 'bold' => true ),
						$prescription['dosage'],
						$prescription['frequency'],
						$prescription['duration'],
					),
					'note'       => $prescription['instructions'],
					'note_label' => __( 'Instructions', 'doctor-ak-portal' ),
				);
			}

			$pdf->table(
				array(
					array( 'label' => '#', 'width' => 24 ),
					array( 'label' => __( 'Medicine', 'doctor-ak-portal' ), 'width' => null ),
					array( 'label' => __( 'Dose', 'doctor-ak-portal' ), 'width' => 92 ),
					array( 'label' => __( 'Frequency', 'doctor-ak-portal' ), 'width' => 100 ),
					array( 'label' => __( 'Duration', 'doctor-ak-portal' ), 'width' => 80 ),
				),
				$rows
			);
		}

		$qualification = isset( $doctor_lines[1] ) && 'body' === $doctor_lines[1]['style'] ? $doctor_lines[1]['text'] : '';
		$pdf->signature( $doctor_lines[0]['text'], $qualification );

		return $pdf->render();
	}
}
