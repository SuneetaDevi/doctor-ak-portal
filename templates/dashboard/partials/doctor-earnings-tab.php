<?php
/**
 * Template: Doctor dashboard "Earnings" tab — this doctor's own share (see
 * Revenue_Split) of every one of their paid appointments: today/this
 * month/all-time totals, their current payment model/split, and a
 * per-appointment breakdown.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $earnings Rows from Appointments::doctor_revenue_summary() — { total, this_month, today, invoice_count, current_split }.
 * @var array $net_dues Rows from Appointments::net_dues_for_doctor() — { owed_by_doctor, owed_to_doctor, net }, what this doctor owes the clinic (cash-collected appointments) netted against what the clinic owes them (online-collected appointments).
 * @var array $invoices This doctor's paid appointments, see Appointments::all_for_admin().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_earnings_icons = array(
	'money'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2v7.6M12.2 8.1c0-1-1-1.6-2.2-1.6s-2.2.6-2.2 1.5c0 2.2 4.4 1 4.4 3.2 0 .9-1 1.5-2.2 1.5s-2.2-.6-2.2-1.6"/></svg>',
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'receipt'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h10v14l-2-1.3L11 17l-2-1.3L7 17l-2-1.3z"/><path d="M7.5 7h5M7.5 10h5"/></svg>',
);

$dak_is_salary = \DoctorAKPortal\Includes\Revenue_Split::MODEL_SALARY === $earnings['current_split']['payment_model'];
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Earnings', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Your own share of every paid appointment, OPD or video consultation.', 'doctor-ak-portal' ); ?></p>
</div>

<section class="dak-dashboard-statistics">
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_earnings_icons['money']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $earnings['today'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Your earnings today', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_earnings_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $earnings['this_month'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'This month', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_earnings_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $earnings['total'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'All-time', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_earnings_icons['receipt']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $earnings['invoice_count'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Paid appointments', 'doctor-ak-portal' ); ?></span>
	</div>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Your revenue split', 'doctor-ak-portal' ); ?></h2>
	</div>
	<?php if ( $dak_is_salary ) : ?>
		<p class="dak-field-hint"><?php esc_html_e( "You're on a salary — every payment you collect counts fully as clinic revenue rather than being split with you. Contact the clinic administrator if you believe this should be different.", 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<p class="dak-field-hint">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: doctor's share percent, 2: hospital's share percent. */
					__( "You keep %1\$s%% of every payment; the clinic keeps the remaining %2\$s%%. Contact the clinic administrator to change this.", 'doctor-ak-portal' ),
					number_format( (float) $earnings['current_split']['doctor_share_percent'], 1 ),
					number_format( (float) $earnings['current_split']['hospital_share_percent'], 1 )
				)
			);
			?>
		</p>
	<?php endif; ?>
</section>

<?php if ( ! $dak_is_salary ) : ?>
<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Settlement with the clinic', 'doctor-ak-portal' ); ?></h2>
	</div>
	<p class="dak-field-hint"><?php esc_html_e( 'For your clinic (cash-collected) appointments, you already hold the payment and owe the clinic its share. For your online-collected appointments, the clinic already holds the payment and owes you your share. The two net into one figure.', 'doctor-ak-portal' ); ?></p>

	<div class="dak-admin-record-row">
		<div class="dak-admin-record-row-main">
			<span class="dak-admin-record-row-info">
				<span class="dak-admin-record-row-id">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: amount owed to the clinic, 2: amount owed by the clinic. */
							__( 'You owe clinic: PKR %1$s &middot; Clinic owes you: PKR %2$s', 'doctor-ak-portal' ),
							number_format( (float) $net_dues['owed_by_doctor'], 0 ),
							number_format( (float) $net_dues['owed_to_doctor'], 0 )
						)
					);
					?>
				</span>
			</span>

			<span class="dak-admin-record-row-tags">
				<?php if ( $net_dues['net'] > 0.01 ) : ?>
					<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Clinic owes you', 'doctor-ak-portal' ); ?></span>
				<?php elseif ( $net_dues['net'] < -0.01 ) : ?>
					<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-disabled"><?php esc_html_e( 'You owe clinic', 'doctor-ak-portal' ); ?></span>
				<?php else : ?>
					<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Settled', 'doctor-ak-portal' ); ?></span>
				<?php endif; ?>
			</span>

			<span class="dak-admin-record-row-amount">PKR <?php echo esc_html( number_format( abs( (float) $net_dues['net'] ), 0 ) ); ?></span>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Paid appointments', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $invoices ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( "You don't have any paid appointments yet.", 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $invoices as $row ) : ?>
			<?php $dak_row_split = \DoctorAKPortal\Includes\Revenue_Split::split( $row['doctor_id'], $row['charge'] ); ?>
			<div class="dak-admin-record-row">
				<div class="dak-admin-record-row-main">
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( '' !== $row['service_name'] ? $row['service_name'] : $row['type_label'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( /* translators: 1: patient name, 2: appointment date/time. */ __( '%1$s &middot; %2$s', 'doctor-ak-portal' ), $row['patient_name'], $row['datetime_label'] ) ); ?></span>
					</span>

					<span class="dak-admin-record-row-tags">
						<?php if ( 'processed' === $row['refund_status'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Refunded', 'doctor-ak-portal' ); ?></span>
						<?php else : ?>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Paid', 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-amount"><?php echo esc_html( 'PKR ' . number_format( (float) $row['charge'], 0 ) ); ?></span>
				</div>

				<div class="dak-admin-record-row-secondary">
					<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Split:', 'doctor-ak-portal' ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php echo esc_html( sprintf( /* translators: %s: doctor's share amount. */ __( 'Your share: PKR %s', 'doctor-ak-portal' ), number_format( $dak_row_split['doctor_share'], 0 ) ) ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( /* translators: %s: hospital's share amount. */ __( "Hospital's share: PKR %s", 'doctor-ak-portal' ), number_format( $dak_row_split['hospital_share'], 0 ) ) ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
