<?php
/**
 * Builds a patient's whole medical history (every recorded visit) as one
 * printable PDF.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Medical_History_Pdf
 *
 * Same stationery as the prescription (see Pdf_Builder): the patient at the
 * top, then one section per visit, newest first — date, doctor, clinic and
 * whether the visit is still in progress, the problems recorded with their
 * notes, and the medicines prescribed with their instructions. Clinical
 * text is printed exactly as recorded.
 */
class Medical_History_Pdf extends Pdf_Document {

	/**
	 * @param \WP_User $patient    The patient.
	 * @param array    $encounters Encounters (newest first), each with 'appointment', 'problems' and 'prescriptions' added.
	 * @return string Raw PDF bytes.
	 */
	public static function build( \WP_User $patient, array $encounters ) {
		$reference = sprintf( 'P-%03d', $patient->ID );
		$name      = trim( $patient->first_name . ' ' . $patient->last_name );
		$name      = '' !== $name ? $name : $patient->display_name;

		$pdf = new Pdf_Builder( __( 'Medical History', 'doctor-ak-portal' ), $reference );

		$pdf->header(
			array(
				array( __( 'Patient ID', 'doctor-ak-portal' ), $reference ),
				/* translators: %d: number of visits. */
				array( __( 'Visits', 'doctor-ak-portal' ), (string) count( $encounters ) ),
				array( __( 'Issued', 'doctor-ak-portal' ), date_i18n( 'd M Y' ) ),
			)
		);

		$pdf->people(
			array(
				array(
					'label' => __( 'Patient', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::patient_lines( $name, $patient->ID, Patient_Age::age_for( $patient->ID ), get_user_meta( $patient->ID, 'doctor_ak_phone_number', true ) ),
				),
			)
		);

		if ( empty( $encounters ) ) {
			$pdf->paragraph( __( 'No visits have been recorded yet.', 'doctor-ak-portal' ), 'muted' );

			return $pdf->render();
		}

		foreach ( $encounters as $encounter ) {
			$appointment = ! empty( $encounter['appointment'] ) ? $encounter['appointment'] : array();
			$visit_ts    = strtotime( (string) $encounter['checked_in_at'] );
			$is_video    = isset( $appointment['type'] ) && Appointments::TYPE_VIDEO === $appointment['type'];
			$doctor      = isset( $appointment['doctor_name'] ) ? $appointment['doctor_name'] : '';

			if ( '' === $doctor && ! empty( $encounter['doctor_id'] ) ) {
				$user   = get_userdata( (int) $encounter['doctor_id'] );
				$doctor = $user ? trim( $user->first_name . ' ' . $user->last_name ) : '';
				$doctor = ( '' === $doctor && $user ) ? $user->display_name : $doctor;
			}

			$place = $is_video
				? __( 'Online video consultation', 'doctor-ak-portal' )
				: ( ! empty( $appointment['clinic_name'] ) ? $appointment['clinic_name'] : '' );

			$pdf->section(
				sprintf(
					/* translators: 1: visit date, 2: encounter reference. */
					__( 'Visit on %1$s  ·  %2$s', 'doctor-ak-portal' ),
					false !== $visit_ts ? date_i18n( 'd M Y', $visit_ts ) : '—',
					sprintf( 'ENC-%04d', (int) $encounter['id'] )
				),
				70
			);

			$pdf->details(
				array(
					/* translators: %s: doctor's name. */
					array( __( 'Doctor', 'doctor-ak-portal' ), '' !== $doctor ? sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $doctor ) : '' ),
					array( $is_video ? __( 'Location', 'doctor-ak-portal' ) : __( 'Clinic', 'doctor-ak-portal' ), $place ),
					array( __( 'Time', 'doctor-ak-portal' ), false !== $visit_ts ? date_i18n( 'h:i A', $visit_ts ) : '' ),
					array( __( 'Status', 'doctor-ak-portal' ), Encounters::STATUS_OPEN === $encounter['status'] ? __( 'In progress — notes may still be updated', 'doctor-ak-portal' ) : __( 'Completed', 'doctor-ak-portal' ) ),
				),
				110
			);

			$pdf->space( 4 );

			if ( empty( $encounter['problems'] ) ) {
				$pdf->paragraph( __( 'No problems recorded for this visit.', 'doctor-ak-portal' ), 'muted' );
			} else {
				foreach ( array_values( $encounter['problems'] ) as $index => $problem ) {
					$pdf->list_item( ( $index + 1 ) . '.', $problem['description'], 'strong' );

					if ( '' !== trim( (string) $problem['notes'] ) ) {
						$pdf->paragraph( $problem['notes'], 'body', $pdf->indent_x(), $pdf->width - 18 );
					} else {
						$pdf->space( 4 );
					}
				}
			}

			$pdf->space( 4 );

			if ( empty( $encounter['prescriptions'] ) ) {
				$pdf->paragraph( __( 'No medicines prescribed for this visit.', 'doctor-ak-portal' ), 'muted' );
				continue;
			}

			$rows = array();

			foreach ( array_values( $encounter['prescriptions'] ) as $index => $prescription ) {
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

		return $pdf->render();
	}
}
