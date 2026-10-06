<?php
/**
 * Template: Patient dashboard "Payments" tab — every appointment the
 * patient has actually paid for, most recent first.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $rows        List of rows, see Appointments::patient_dashboard_row().
 * @var float  $total_paid  Sum of every row's charge.
 * @var string $booking_url URL of the booking page, for the empty state's CTA.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-list-page">
<div class="dak-summary-grid dak-payments-summary">
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Total paid', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $total_paid ) ); ?></strong>
		<span class="dak-summary-card-sub">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of payments. */
					_n( 'across %d payment', 'across %d payments', count( $rows ), 'doctor-ak-portal' ),
					count( $rows )
				)
			);
			?>
		</span>
	</div>
</div>

<section class="dak-results" aria-labelledby="dak-patient-payments-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-patient-payments-title"><?php esc_html_e( 'Payment history', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></span></h2>
	</div>
	<?php if ( empty( $rows ) ) : ?>
		<div class="dak-empty-state">
			<p><?php esc_html_e( "You haven't made any payments yet.", 'doctor-ak-portal' ); ?></p>
			<?php if ( $booking_url ) : ?>
				<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $booking_url ); ?>"><?php esc_html_e( 'Book an appointment', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-patient-payments-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Appointment date', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Doctor & service', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( 'Amount', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php $dak_ts = strtotime( $row['date'] . ' ' . $row['time'] ); ?>
						<tr data-row>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Appointment date', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( false !== $dak_ts ? date_i18n( 'd M Y', $dak_ts ) : $row['date'] ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( false !== $dak_ts ? date_i18n( 'h:i A', $dak_ts ) : $row['time'] ); ?></span>
								</span>
							</td>
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Doctor & service', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( sprintf( 'Dr. %s', $row['doctor_name'] ) ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( '' !== $row['service_name'] ? $row['service_name'] : $row['type_label'] ); ?></span>
								</span>
							</td>
							<td class="dak-col-num" data-label="<?php esc_attr_e( 'Amount', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $row['charge'] ) ); ?></span>
									<?php if ( ! empty( $row['is_instant'] ) && (float) $row['surcharge'] > 0 ) : ?>
										<span class="dak-cell-note"><?php echo esc_html( sprintf( /* translators: %s: surcharge amount. */ __( 'Includes %s instant booking fee', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $row['surcharge'] ) ) ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>"><span class="dak-status-pill dak-status-pill-is-active"><?php esc_html_e( 'Paid', 'doctor-ak-portal' ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
</div>