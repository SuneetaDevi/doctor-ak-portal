<?php
/**
 * Builds a single appointment's printable slip as a standalone PDF, for the
 * admin Appointments "Print" action.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Appointment_Slip_Pdf
 *
 * Layout (see Pdf_Builder for the shared stationery): the appointment's
 * date, time and visit type large at the top; patient, doctor and place of
 * care; then the booking details with appointment status and payment
 * status on separate lines; the charge; and visit instructions drawn only
 * from existing data. An online visit's meeting link is never printed —
 * patients join from their dashboard, where access is checked.
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
		$row = Appointments::notification_data( $appointment['id'] );
		$row = ! empty( $row ) ? $row : array();
		$get = function ( $key, $default = '' ) use ( $row, $appointment ) {
			if ( isset( $row[ $key ] ) ) {
				return $row[ $key ];
			}

			return isset( $appointment[ $key ] ) ? $appointment[ $key ] : $default;
		};

		$is_video   = Appointments::TYPE_VIDEO === $appointment['type'];
		$is_paid    = Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'];
		$charge     = (float) $appointment['charge'];
		$start      = strtotime( $appointment['date'] . ' ' . $appointment['time'] );
		$type_label = $is_video ? __( 'Online video consultation', 'doctor-ak-portal' ) : __( 'Clinic visit', 'doctor-ak-portal' );
		$service    = '' !== (string) $appointment['service_name'] ? $appointment['service_name'] : $type_label;

		$pdf = new Pdf_Builder( __( 'Appointment Slip', 'doctor-ak-portal' ), sprintf( 'SLIP-%05d', (int) $appointment['id'] ) );

		$pdf->header(
			array(
				array( __( 'Slip no.', 'doctor-ak-portal' ), sprintf( 'SLIP-%05d', (int) $appointment['id'] ) ),
				array( __( 'Appointment', 'doctor-ak-portal' ), sprintf( 'APT-%04d', (int) $appointment['id'] ) ),
				array( __( 'Issued', 'doctor-ak-portal' ), date_i18n( 'd M Y' ) ),
			)
		);

		$pdf->facts(
			array(
				array( __( 'Date', 'doctor-ak-portal' ), false !== $start ? date_i18n( 'D, d M Y', $start ) : (string) $appointment['date'] ),
				array( __( 'Time', 'doctor-ak-portal' ), false !== $start ? date_i18n( 'h:i A', $start ) : (string) $appointment['time'] ),
				array( __( 'Visit type', 'doctor-ak-portal' ), $is_video ? __( 'Online video', 'doctor-ak-portal' ) : __( 'Clinic visit', 'doctor-ak-portal' ) ),
			)
		);

		$pdf->people(
			array(
				array(
					'label' => __( 'Patient', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::patient_lines( $patient_name, (int) $appointment['patient_id'], $get( 'patient_age' ), $get( 'patient_phone' ) ),
				),
				array(
					'label' => __( 'Doctor', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::doctor_lines( (int) $appointment['doctor_id'], $doctor_name ),
				),
				array(
					'label' => $is_video ? __( 'Location', 'doctor-ak-portal' ) : __( 'Clinic', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::place_lines( $appointment['type'], (int) $appointment['clinic_id'], $get( 'clinic_name' ), $get( 'clinic_address' ) ),
				),
			)
		);

		$pdf->section( __( 'Booking details', 'doctor-ak-portal' ), 60 );

		$refund = '';

		if ( '' !== (string) $get( 'refund_status' ) ) {
			$refund = ucfirst( (string) $get( 'refund_status' ) );

			if ( (float) $get( 'refund_amount', 0 ) > 0 ) {
				$refund .= ' · ' . Dashboard_Format::money( $get( 'refund_amount', 0 ) );
			}
		}

		$pdf->details(
			array(
				array( __( 'Service', 'doctor-ak-portal' ), $service, 'strong' ),
				array( __( 'Appointment status', 'doctor-ak-portal' ), (string) $get( 'status_label', ucfirst( str_replace( '_', ' ', (string) $appointment['status'] ) ) ) ),
				array( __( 'Payment status', 'doctor-ak-portal' ), $is_paid ? __( 'Paid', 'doctor-ak-portal' ) : ( $charge > 0 ? __( 'Payment pending', 'doctor-ak-portal' ) : __( 'Nothing to pay', 'doctor-ak-portal' ) ) ),
				array( __( 'Payment method', 'doctor-ak-portal' ), $charge > 0 ? ( Appointments::PAYMENT_MODE_ONLINE === $appointment['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : __( 'At the clinic', 'doctor-ak-portal' ) ) : '' ),
				array( __( 'Transaction reference', 'doctor-ak-portal' ), (string) $get( 'online_order_id' ) ),
				array( __( 'Refund', 'doctor-ak-portal' ), $refund ),
			)
		);

		$pdf->space( 4 );
		$pdf->table(
			array(
				array( 'label' => __( 'Description', 'doctor-ak-portal' ), 'width' => null ),
				array( 'label' => __( 'Amount', 'doctor-ak-portal' ), 'width' => 120, 'align' => 'right' ),
			),
			array(
				array(
					'cells' => array(
						array( 'text' => $service, 'sub' => $service !== $type_label ? $type_label : '' ),
						$charge > 0 ? Dashboard_Format::money( $charge ) : __( 'Free', 'doctor-ak-portal' ),
					),
				),
			)
		);

		$total_rows = array( array( __( 'Total', 'doctor-ak-portal' ), $charge > 0 ? Dashboard_Format::money( $charge ) : __( 'Free', 'doctor-ak-portal' ), 'strong' ) );

		if ( $charge > 0 ) {
			$total_rows[] = array( __( 'Amount paid', 'doctor-ak-portal' ), Dashboard_Format::money( $is_paid ? $charge : 0 ) );
			$total_rows[] = array( __( 'Balance due', 'doctor-ak-portal' ), Dashboard_Format::money( $is_paid ? 0 : $charge ) );
		}

		$pdf->totals( $total_rows );

		$notes = trim( (string) $get( 'notes' ) );

		if ( '' !== $notes ) {
			$pdf->section( __( 'Notes from booking', 'doctor-ak-portal' ) );
			$pdf->paragraph( $notes );
		}

		if ( $is_video && ! in_array( $appointment['status'], array( Appointments::STATUS_CANCELLED, Appointments::STATUS_COMPLETED ), true ) ) {
			$pdf->section( __( 'Joining your video consultation', 'doctor-ak-portal' ) );
			$pdf->paragraph(
				sprintf(
					/* translators: %d: minutes before the start time. */
					__( 'Sign in and open Appointments in your patient dashboard. The Join button appears %d minutes before the start time once payment is complete. For your privacy, the meeting link is not printed on this slip.', 'doctor-ak-portal' ),
					Appointments::VIDEO_JOIN_WINDOW_BEFORE_MINUTES
				)
			);
		}

		return $pdf->render();
	}
}
