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
<section class="dak-dashboard-card dak-patient-payments-summary">
	<span class="dak-patient-payments-summary-label"><?php esc_html_e( 'Total Paid', 'doctor-ak-portal' ); ?></span>
	<span class="dak-patient-payments-summary-amount"><?php echo esc_html( 'PKR' . number_format( $total_paid, 0 ) ); ?></span>
	<span class="dak-patient-payments-summary-count">
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
</section>

<section class="dak-dashboard-card dak-patient-payments-list-card">
	<?php if ( empty( $rows ) ) : ?>
		<div class="dak-patient-empty-appointments">
			<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="8" y="18" width="48" height="34" rx="4"/><path d="M8 27h48"/><path d="M16 38h10M16 45h6"/></svg>
			<p><?php esc_html_e( "You haven't made any payments yet.", 'doctor-ak-portal' ); ?></p>
			<?php if ( $booking_url ) : ?>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( $booking_url ); ?>"><?php esc_html_e( 'Book an appointment', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<ul class="dak-patient-payments-list">
			<?php foreach ( $rows as $row ) : ?>
				<li class="dak-patient-payment-row">
					<span class="dak-patient-appt-avatar">
						<?php if ( $row['doctor_avatar_url'] ) : ?>
							<img src="<?php echo esc_url( $row['doctor_avatar_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo esc_html( mb_strtoupper( mb_substr( $row['doctor_name'], 0, 1 ) ) ); ?>
						<?php endif; ?>
					</span>

					<div class="dak-patient-payment-info">
						<strong><?php echo esc_html( sprintf( 'Dr. %s', $row['doctor_name'] ) ); ?></strong>
						<span class="dak-patient-payment-meta">
							<?php
							$date_timestamp = strtotime( $row['date'] );
							echo esc_html( $date_timestamp ? date_i18n( 'D, M j, Y', $date_timestamp ) : $row['date'] );
							?>
							&middot; <?php echo esc_html( '' !== $row['service_name'] ? $row['service_name'] : $row['type_label'] ); ?>
						</span>
						<?php if ( ! empty( $row['is_instant'] ) && (float) $row['surcharge'] > 0 ) : ?>
							<span class="dak-patient-payment-meta dak-patient-payment-surcharge-note">
								<?php echo esc_html( sprintf( /* translators: %s: surcharge amount. */ __( 'Includes PKR%s instant booking fee', 'doctor-ak-portal' ), number_format( (float) $row['surcharge'], 0 ) ) ); ?>
							</span>
						<?php endif; ?>
					</div>

					<div class="dak-patient-payment-amount-wrap">
						<span class="dak-patient-payment-amount"><?php echo esc_html( 'PKR' . number_format( (float) $row['charge'], 0 ) ); ?></span>
						<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Paid', 'doctor-ak-portal' ); ?></span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
