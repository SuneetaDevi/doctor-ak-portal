<?php
/**
 * Builds one doctor's revenue statement — every clinic (and video
 * consultations) they earned from in a period, as separate line items,
 * plus the full appointments list — as a standalone PDF, for the admin
 * Billing screen's per-doctor "Download" action.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Statement_Pdf
 *
 * The figures are unchanged: each location line's average charge is its
 * gross total over its appointment count; gross collected is the sum of the
 * location lines; platform/gateway charges and the doctor's net share are
 * summed over the period's ledger rows. The appointments list now runs onto
 * further pages (headings repeated) instead of stopping after a fixed
 * number of rows.
 */
class Doctor_Statement_Pdf extends Pdf_Document {

	/**
	 * Money with a refund/reversal shown as "– PKR 2,500" (same style as the
	 * other documents' discount lines) rather than "PKR -2,500".
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function signed_money( $amount ) {
		return $amount < 0 ? '– ' . Dashboard_Format::money( abs( $amount ) ) : Dashboard_Format::money( $amount );
	}

	/**
	 * Builds the statement PDF for one doctor.
	 *
	 * @param \WP_User $doctor       The doctor.
	 * @param array    $line_items   Rows from Revenue_Ledger::balances_by_doctor_and_clinic(), filtered to this doctor — each augmented with a 'label' key by the caller.
	 * @param array    $ledger_rows  This doctor's rows from Revenue_Ledger::all_flat_for_admin(), for the appointments list.
	 * @param string   $period_label e.g. '2026-09-01 – 2026-09-30', or 'All time'.
	 * @return string Raw PDF file bytes.
	 */
	public static function build( \WP_User $doctor, array $line_items, array $ledger_rows, $period_label ) {
		$doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
		$doctor_name = '' !== $doctor_name ? $doctor_name : $doctor->display_name;

		$statement_number = sprintf( 'STMT-%05d-%s', $doctor->ID, gmdate( 'Ym' ) );

		// Stored dates in the period label read as "01 Sep 2026".
		$period = preg_replace_callback(
			'/\d{4}-\d{2}-\d{2}/',
			function ( $match ) {
				return Dashboard_Format::date( $match[0], $match[0] );
			},
			(string) $period_label
		);

		$pdf = new Pdf_Builder( __( 'Revenue Statement', 'doctor-ak-portal' ), $statement_number );

		$pdf->header(
			array(
				array( __( 'Statement no.', 'doctor-ak-portal' ), $statement_number ),
				array( __( 'Period', 'doctor-ak-portal' ), $period ),
				array( __( 'Issued', 'doctor-ak-portal' ), date_i18n( 'd M Y' ) ),
			)
		);

		$doctor_lines = Pdf_Builder::doctor_lines( $doctor->ID, $doctor_name );

		if ( '' !== (string) $doctor->user_email ) {
			$doctor_lines[] = array( 'text' => $doctor->user_email, 'style' => 'muted' );
		}

		$gross_total   = 0.0;
		$platform_fees = 0.0;
		$doctor_total  = 0.0;
		$appointments  = 0;

		foreach ( $line_items as $item ) {
			$gross_total  += $item['gross_total'];
			$appointments += (int) $item['appointment_count'];
		}

		foreach ( $ledger_rows as $row ) {
			// A reversal takes the doctor's share (and the fee) back.
			$sign           = Revenue_Ledger::TRANSACTION_REFUND === $row['transaction_type'] ? -1 : 1;
			$platform_fees += $sign * $row['platform_fee'];
			$doctor_total  += $sign * $row['doctor_amount'];
		}

		$pdf->people(
			array(
				array(
					'label' => __( 'Doctor', 'doctor-ak-portal' ),
					'lines' => $doctor_lines,
				),
				array(
					'label' => __( 'Statement period', 'doctor-ak-portal' ),
					'lines' => array(
						array( 'text' => $period, 'style' => 'strong' ),
						/* translators: %d: number of appointments. */
						array( 'text' => sprintf( _n( '%d appointment', '%d appointments', $appointments, 'doctor-ak-portal' ), $appointments ), 'style' => 'muted' ),
					),
				),
			)
		);

		$pdf->section( __( 'Charges by location', 'doctor-ak-portal' ), 60 );

		$rows = array();

		foreach ( $line_items as $item ) {
			$charges = isset( $item['charges'] ) ? $item['charges'] : array();

			// Without itemised charges (older caller), the clinic line stands
			// alone with its per-visit charge.
			if ( empty( $charges ) ) {
				$unit_price = $item['appointment_count'] > 0 ? $item['gross_total'] / $item['appointment_count'] : 0.0;

				$rows[] = array(
					'cells' => array(
						array( 'text' => $item['label'] ),
						Dashboard_Format::money( $unit_price ),
						(string) $item['appointment_count'],
						Dashboard_Format::money( $item['gross_total'] ),
					),
				);
				continue;
			}

			// The clinic (or video consultations) as a subtotal line, then each
			// charge billed there: services, extra visit charges, refunds.
			$rows[] = array(
				'cells' => array(
					array( 'text' => $item['label'], 'bold' => true ),
					'',
					array( 'text' => (string) $item['appointment_count'], 'bold' => true ),
					array( 'text' => Dashboard_Format::money( $item['gross_total'] ), 'bold' => true ),
				),
			);

			foreach ( $charges as $charge ) {
				$rows[] = array(
					'cells' => array(
						array( 'text' => $charge['label'], 'indent' => 14 ),
						self::signed_money( $charge['unit_amount'] ),
						(string) $charge['quantity'],
						self::signed_money( $charge['total_amount'] ),
					),
				);
			}
		}

		if ( $platform_fees > 0 ) {
			$rows[] = array( 'cells' => array( __( 'Platform / gateway charges', 'doctor-ak-portal' ), '', '', Dashboard_Format::money( $platform_fees ) ) );
		}

		if ( empty( $rows ) ) {
			$pdf->paragraph( __( 'No earnings recorded in this period.', 'doctor-ak-portal' ), 'muted' );
		} else {
			$pdf->table(
				array(
					array( 'label' => __( 'Location / charge', 'doctor-ak-portal' ), 'width' => null ),
					array( 'label' => __( 'Unit charge', 'doctor-ak-portal' ), 'width' => 96, 'align' => 'right' ),
					array( 'label' => __( 'Qty', 'doctor-ak-portal' ), 'width' => 50, 'align' => 'right' ),
					array( 'label' => __( 'Amount', 'doctor-ak-portal' ), 'width' => 104, 'align' => 'right' ),
				),
				$rows
			);
		}

		$pdf->totals(
			array(
				array( __( 'Gross collected', 'doctor-ak-portal' ), Dashboard_Format::money( $gross_total ) ),
				array( __( "Doctor's net share", 'doctor-ak-portal' ), Dashboard_Format::money( $doctor_total ), 'strong' ),
			)
		);

		// One line per appointment: its consultation and any extra charges
		// billed during the visit add up to one amount.
		$by_appointment = array();

		foreach ( $ledger_rows as $row ) {
			if ( Revenue_Ledger::TRANSACTION_REFUND === $row['transaction_type'] ) {
				continue;
			}

			$key = (int) $row['appointment_id'];

			if ( ! isset( $by_appointment[ $key ] ) ) {
				$by_appointment[ $key ] = array(
					'row'    => $row,
					'amount' => 0.0,
				);
			}

			$by_appointment[ $key ]['amount'] += $row['gross_amount'];

			if ( $row['transaction_date'] < $by_appointment[ $key ]['row']['transaction_date'] ) {
				$by_appointment[ $key ]['row']['transaction_date'] = $row['transaction_date'];
			}
		}

		$list = array();

		foreach ( $by_appointment as $entry ) {
			$row         = $entry['row'];
			$appointment = Appointments::notification_data( $row['appointment_id'] );

			if ( empty( $appointment ) ) {
				continue;
			}

			if ( 0 === (int) $row['clinic_id'] ) {
				$location = __( 'Video consultation', 'doctor-ak-portal' );
			} else {
				$location = '' !== $appointment['clinic_name'] ? $appointment['clinic_name'] : $appointment['type_label'];
			}

			$list[] = array(
				'cells' => array(
					Dashboard_Format::date( $row['transaction_date'], (string) $row['transaction_date'] ),
					array( 'text' => $appointment['patient_name'], 'sub' => sprintf( 'APT-%04d', (int) $row['appointment_id'] ) ),
					$location,
					Dashboard_Format::money( $entry['amount'] ),
				),
			);
		}

		$pdf->section( __( 'Appointments', 'doctor-ak-portal' ), 60 );

		if ( empty( $list ) ) {
			$pdf->paragraph( __( 'No appointments in this period.', 'doctor-ak-portal' ), 'muted' );
		} else {
			$pdf->table(
				array(
					array( 'label' => __( 'Date', 'doctor-ak-portal' ), 'width' => 78 ),
					array( 'label' => __( 'Patient', 'doctor-ak-portal' ), 'width' => 160 ),
					array( 'label' => __( 'Location', 'doctor-ak-portal' ), 'width' => null ),
					array( 'label' => __( 'Amount', 'doctor-ak-portal' ), 'width' => 96, 'align' => 'right' ),
				),
				$list
			);
		}

		return $pdf->render();
	}
}
