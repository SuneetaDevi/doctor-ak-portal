<?php
/**
 * Builds the patient's payment receipt as a standalone PDF file, attached to
 * the payment email (see Notifications::send_invoice()) and downloadable
 * from the admin Billing section.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Invoice_Pdf
 *
 * A paid appointment's document is a "Payment Receipt"; should it ever be
 * built for an unpaid one, the same layout is titled "Invoice" and shows the
 * balance due instead — the two are never confused. The figures are the
 * same ones this document always used: the pre-discount price is the base
 * charge plus any instant-booking surcharge, a discount line appears only
 * when a discount actually applied, and the total is the appointment's
 * charge.
 */
class Invoice_Pdf extends Pdf_Document {

	/**
	 * Builds the receipt (or invoice) PDF for one appointment.
	 *
	 * @param array $appointment Row from Appointments::notification_data().
	 * @return string Raw PDF file bytes.
	 */
	public static function build( array $appointment ) {
		$is_paid          = Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'];
		$is_video         = Appointments::TYPE_VIDEO === $appointment['type'];
		$number           = sprintf( 'INV-%05d', (int) $appointment['id'] );
		$service_label    = '' !== $appointment['service_name'] ? $appointment['service_name'] : $appointment['type_label'];
		$charge           = (float) $appointment['charge'];
		$base             = (float) $appointment['base_charge'];
		$surcharge        = (float) $appointment['surcharge'];
		$base_charge      = $base + $surcharge;
		$discount_percent = (int) $appointment['discount_percent'];
		$has_discount     = $discount_percent > 0 && $base_charge > $charge;
		$start            = strtotime( $appointment['date'] . ' ' . $appointment['time'] );
		$visit_line       = implode(
			'  ·  ',
			array_filter(
				array(
					false !== $start ? date_i18n( 'd M Y, h:i A', $start ) : '',
					$is_video ? __( 'Online video consultation', 'doctor-ak-portal' ) : __( 'Clinic visit', 'doctor-ak-portal' ),
				)
			)
		);

		$title = $is_paid ? __( 'Payment Receipt', 'doctor-ak-portal' ) : __( 'Invoice', 'doctor-ak-portal' );
		$pdf   = new Pdf_Builder( $title, $number );

		$pdf->header(
			array(
				array( $is_paid ? __( 'Receipt no.', 'doctor-ak-portal' ) : __( 'Invoice no.', 'doctor-ak-portal' ), $number ),
				array( __( 'Appointment', 'doctor-ak-portal' ), sprintf( 'APT-%04d', (int) $appointment['id'] ) ),
				array( __( 'Issued', 'doctor-ak-portal' ), date_i18n( 'd M Y' ) ),
			)
		);

		$pdf->people(
			array(
				array(
					'label' => $is_paid ? __( 'Received from', 'doctor-ak-portal' ) : __( 'Billed to', 'doctor-ak-portal' ),
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

		// Itemise the surcharge only when the stored figures add up to it.
		$itemised = $has_discount || ( $surcharge > 0 && abs( $base_charge - $charge ) < 0.005 );
		$rows     = array();

		$rows[] = array(
			'cells' => array(
				array( 'text' => $service_label, 'sub' => $visit_line ),
				Dashboard_Format::money( $itemised ? $base : $charge ),
			),
		);

		if ( $itemised && $surcharge > 0 ) {
			$rows[] = array( 'cells' => array( __( 'Instant booking surcharge', 'doctor-ak-portal' ), Dashboard_Format::money( $surcharge ) ) );
		}

		$pdf->section( __( 'Services', 'doctor-ak-portal' ), 60 );
		$pdf->table(
			array(
				array( 'label' => __( 'Description', 'doctor-ak-portal' ), 'width' => null ),
				array( 'label' => __( 'Amount', 'doctor-ak-portal' ), 'width' => 120, 'align' => 'right' ),
			),
			$rows
		);

		$totals = array();

		if ( $has_discount ) {
			$totals[] = array( __( 'Subtotal', 'doctor-ak-portal' ), Dashboard_Format::money( $base_charge ) );
			/* translators: %d: discount percent. */
			$totals[] = array( sprintf( __( 'Discount (%d%%)', 'doctor-ak-portal' ), $discount_percent ), '– ' . Dashboard_Format::money( $base_charge - $charge ) );
		}

		$totals[] = array( __( 'Total', 'doctor-ak-portal' ), Dashboard_Format::money( $charge ), 'strong' );
		$totals[] = array( __( 'Amount paid', 'doctor-ak-portal' ), Dashboard_Format::money( $is_paid ? $charge : 0 ) );
		$totals[] = array( __( 'Balance due', 'doctor-ak-portal' ), Dashboard_Format::money( $is_paid ? 0 : $charge ) );

		$pdf->totals( $totals );

		$refund = '';

		if ( ! empty( $appointment['refund_status'] ) ) {
			$refund = ucfirst( (string) $appointment['refund_status'] );

			if ( (float) $appointment['refund_amount'] > 0 ) {
				$refund .= ' · ' . Dashboard_Format::money( $appointment['refund_amount'] );
			}
		}

		$pdf->section( __( 'Payment details', 'doctor-ak-portal' ), 40 );
		$pdf->details(
			array(
				array( __( 'Payment status', 'doctor-ak-portal' ), $is_paid ? __( 'Paid', 'doctor-ak-portal' ) : __( 'Payment pending', 'doctor-ak-portal' ), 'strong' ),
				array( __( 'Payment method', 'doctor-ak-portal' ), Appointments::PAYMENT_MODE_ONLINE === $appointment['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : __( 'At the clinic', 'doctor-ak-portal' ) ),
				array( __( 'Transaction reference', 'doctor-ak-portal' ), isset( $appointment['online_order_id'] ) ? (string) $appointment['online_order_id'] : '' ),
				array( __( 'Refund', 'doctor-ak-portal' ), $refund ),
			)
		);

		$pdf->space( 8 );
		$pdf->paragraph( __( 'Thank you for choosing us — we look forward to seeing you.', 'doctor-ak-portal' ), 'muted' );

		return $pdf->render();
	}
}
