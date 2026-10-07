<?php
/**
 * Builds an encounter's bill as a standalone PDF file.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounter_Bill_Pdf
 *
 * An itemised bill for one visit: the appointment's own charge first (when
 * there is one), then every Encounter_Bill_Items row added during the
 * visit with its rate, discount and final amount, then the totals. The
 * total is the same sum this bill always used (see
 * Encounter_Bill_Items::total_for_encounter()).
 *
 * Amount paid follows the system's own status rules rather than any new
 * calculation: the appointment's charge counts as paid when the
 * appointment's payment status is Paid, and the extra items count as paid
 * once the encounter is closed — closing checks the patient out, marks the
 * appointment paid and posts those extras to billing (see
 * Encounters::close() and Revenue_Ledger::post_for_encounter_extra()).
 */
class Encounter_Bill_Pdf extends Pdf_Document {

	/**
	 * Builds the bill PDF for one encounter.
	 *
	 * @param array $appointment Row from Appointments::notification_data().
	 * @param array $encounter   Row from Encounters::find().
	 * @param array $bill_items  Rows from Encounter_Bill_Items::for_encounter().
	 * @return string Raw PDF file bytes.
	 */
	public static function build( array $appointment, array $encounter, array $bill_items ) {
		$number        = sprintf( 'BILL-%05d', (int) $encounter['id'] );
		$is_video      = Appointments::TYPE_VIDEO === $appointment['type'];
		$service_label = '' !== $appointment['service_name'] ? $appointment['service_name'] : $appointment['type_label'];
		$appt_charge   = (float) $appointment['charge'];
		$visit_ts      = strtotime( (string) $encounter['checked_in_at'] );
		$is_closed     = Encounters::STATUS_CLOSED === $encounter['status'];
		$appt_paid     = Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'];

		$pdf = new Pdf_Builder( __( 'Patient Bill', 'doctor-ak-portal' ), $number );

		$pdf->header(
			array(
				array( __( 'Bill no.', 'doctor-ak-portal' ), $number ),
				array( __( 'Encounter', 'doctor-ak-portal' ), sprintf( 'ENC-%04d', (int) $encounter['id'] ) ),
				array( __( 'Visit date', 'doctor-ak-portal' ), false !== $visit_ts ? date_i18n( 'd M Y', $visit_ts ) : '' ),
				array( __( 'Issued', 'doctor-ak-portal' ), date_i18n( 'd M Y' ) ),
			)
		);

		$pdf->people(
			array(
				array(
					'label' => __( 'Billed to', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::patient_lines( $appointment['patient_name'], (int) $appointment['patient_id'], isset( $appointment['patient_age'] ) ? $appointment['patient_age'] : '', isset( $appointment['patient_phone'] ) ? $appointment['patient_phone'] : '' ),
				),
				array(
					'label' => __( 'Doctor', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::doctor_lines( (int) $appointment['doctor_id'], $appointment['doctor_name'] ),
				),
				array(
					'label' => $is_video ? __( 'Location', 'doctor-ak-portal' ) : __( 'Clinic', 'doctor-ak-portal' ),
					'lines' => Pdf_Builder::place_lines( $appointment['type'], (int) $appointment['clinic_id'], $appointment['clinic_name'], $appointment['clinic_address'] ),
				),
			)
		);

		$rows       = array();
		$total      = 0.0;
		$gross      = 0.0;
		$extras     = 0.0;
		$line_count = 0;

		if ( $appt_charge > 0 ) {
			++$line_count;
			$rows[] = array(
				'cells' => array(
					(string) $line_count,
					array(
						'text' => $service_label,
						/* translators: %s: appointment reference. */
						'sub'  => sprintf( __( 'Booked consultation · %s', 'doctor-ak-portal' ), sprintf( 'APT-%04d', (int) $appointment['id'] ) ),
					),
					Dashboard_Format::money( $appt_charge ),
					'—',
					Dashboard_Format::money( $appt_charge ),
				),
			);
			$total += $appt_charge;
			$gross += $appt_charge;
		}

		foreach ( $bill_items as $item ) {
			++$line_count;
			$has_discount = isset( $item['discount_percent'] ) && (float) $item['discount_percent'] > 0;

			$rows[] = array(
				'cells' => array(
					(string) $line_count,
					$item['description'],
					Dashboard_Format::money( $item['original_amount'] ),
					$has_discount ? rtrim( rtrim( number_format( (float) $item['discount_percent'], 2 ), '0' ), '.' ) . '%' : '—',
					Dashboard_Format::money( $item['amount'] ),
				),
			);
			$total  += (float) $item['amount'];
			$extras += (float) $item['amount'];
			$gross  += (float) $item['original_amount'];
		}

		$pdf->section( __( 'Charges', 'doctor-ak-portal' ), 60 );

		if ( empty( $rows ) ) {
			$pdf->paragraph( __( 'No charges were recorded for this visit.', 'doctor-ak-portal' ), 'muted' );
		} else {
			$pdf->table(
				array(
					array( 'label' => '#', 'width' => 24 ),
					array( 'label' => __( 'Description', 'doctor-ak-portal' ), 'width' => null ),
					array( 'label' => __( 'Rate', 'doctor-ak-portal' ), 'width' => 90, 'align' => 'right' ),
					array( 'label' => __( 'Discount', 'doctor-ak-portal' ), 'width' => 64, 'align' => 'right' ),
					array( 'label' => __( 'Amount', 'doctor-ak-portal' ), 'width' => 96, 'align' => 'right' ),
				),
				$rows
			);
		}

		$paid    = ( $appt_paid ? max( 0.0, $appt_charge ) : 0.0 ) + ( $is_closed ? $extras : 0.0 );
		$paid    = min( $paid, $total );
		$balance = max( 0.0, $total - $paid );

		$totals = array();

		if ( $gross - $total > 0.004 ) {
			$totals[] = array( __( 'Subtotal', 'doctor-ak-portal' ), Dashboard_Format::money( $gross ) );
			$totals[] = array( __( 'Discounts', 'doctor-ak-portal' ), '– ' . Dashboard_Format::money( $gross - $total ) );
		}

		$totals[] = array( __( 'Total', 'doctor-ak-portal' ), Dashboard_Format::money( $total ), 'strong' );
		$totals[] = array( __( 'Amount paid', 'doctor-ak-portal' ), Dashboard_Format::money( $paid ) );
		$totals[] = array( __( 'Balance due', 'doctor-ak-portal' ), Dashboard_Format::money( $balance ) );

		$pdf->totals( $totals );

		if ( $total <= 0 ) {
			$status = __( 'Nothing to pay', 'doctor-ak-portal' );
		} elseif ( $balance <= 0.004 ) {
			$status = __( 'Paid in full', 'doctor-ak-portal' );
		} elseif ( $paid > 0 ) {
			$status = __( 'Partly paid', 'doctor-ak-portal' );
		} else {
			$status = __( 'Unpaid', 'doctor-ak-portal' );
		}

		$pdf->section( __( 'Payment details', 'doctor-ak-portal' ), 40 );
		$pdf->details(
			array(
				array( __( 'Payment status', 'doctor-ak-portal' ), $status, 'strong' ),
				array( __( 'Payment method', 'doctor-ak-portal' ), $paid > 0 ? ( Appointments::PAYMENT_MODE_ONLINE === $appointment['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : __( 'At the clinic', 'doctor-ak-portal' ) ) : '' ),
				array( __( 'Transaction reference', 'doctor-ak-portal' ), isset( $appointment['online_order_id'] ) ? (string) $appointment['online_order_id'] : '' ),
				array( __( 'Visit status', 'doctor-ak-portal' ), $is_closed ? __( 'Checked out', 'doctor-ak-portal' ) : __( 'Visit in progress', 'doctor-ak-portal' ) ),
			)
		);

		$pdf->space( 8 );
		$pdf->paragraph( __( 'Thank you for choosing us — we look forward to seeing you.', 'doctor-ak-portal' ), 'muted' );

		return $pdf->render();
	}
}
