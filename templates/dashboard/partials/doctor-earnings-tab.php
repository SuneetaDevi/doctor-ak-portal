<?php
/**
 * Template: Doctor dashboard "Earnings" tab — this doctor's own clinic+video
 * revenue ledger (Revenue_Ledger), broken out per clinic (never merged, even
 * though they're all this doctor's), with video consultations always their
 * own line, plus their settlement history (Settlement_Manager — read-only
 * here; settlements are created/resolved by an admin from Billing).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $earnings      Appointments::doctor_revenue_summary() — { total, this_month, today, invoice_count, current_split }.
 * @var array $outstanding   Revenue_Ledger::outstanding_for_doctor( $user->ID ) — { video_earnings, clinic_obligations, platform_fees, closing_balance }.
 * @var array $ledger        This doctor's rows from Revenue_Ledger::all_flat_for_admin().
 * @var array $clinics_by_id This doctor's clinic ID => clinic name.
 * @var array $settlements   Settlement_Manager::for_doctor( $user->ID ) rows, newest first.
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

$dak_clinic_totals = array();
$dak_video_total   = 0.0;

foreach ( $ledger as $dak_row ) {
	if ( 0 === $dak_row['clinic_id'] ) {
		$dak_video_total += $dak_row['doctor_amount'];
		continue;
	}
	if ( ! isset( $dak_clinic_totals[ $dak_row['clinic_id'] ] ) ) {
		$dak_clinic_totals[ $dak_row['clinic_id'] ] = 0.0;
	}
	$dak_clinic_totals[ $dak_row['clinic_id'] ] += $dak_row['doctor_amount'];
}
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Earnings', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Your own share of every paid appointment — kept separate per clinic, and video consultations always on their own.', 'doctor-ak-portal' ); ?></p>
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
					__( "You keep %1\$s%% of every payment by default; the clinic keeps the remaining %2\$s%%. Some clinics may have their own agreed override. Contact the clinic administrator to change this.", 'doctor-ak-portal' ),
					number_format( (float) $earnings['current_split']['doctor_share_percent'], 1 ),
					number_format( (float) $earnings['current_split']['hospital_share_percent'], 1 )
				)
			);
			?>
		</p>
	<?php endif; ?>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Earnings by clinic', 'doctor-ak-portal' ); ?></h2>
	</div>
	<?php if ( empty( $dak_clinic_totals ) && 0.0 === $dak_video_total ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No earnings recorded yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $dak_clinic_totals as $dak_clinic_id => $dak_clinic_amount ) : ?>
			<div class="dak-admin-record-row">
				<div class="dak-admin-record-row-main">
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( isset( $clinics_by_id[ $dak_clinic_id ] ) ? $clinics_by_id[ $dak_clinic_id ] : __( 'Clinic', 'doctor-ak-portal' ) ); ?></strong>
					</span>
					<span class="dak-admin-record-row-amount">PKR <?php echo esc_html( number_format( $dak_clinic_amount, 0 ) ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
		<?php if ( 0.0 !== $dak_video_total ) : ?>
			<div class="dak-admin-record-row">
				<div class="dak-admin-record-row-main">
					<span class="dak-admin-record-row-info">
						<strong><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></strong>
					</span>
					<span class="dak-admin-record-row-amount">PKR <?php echo esc_html( number_format( $dak_video_total, 0 ) ); ?></span>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Settlement with the clinic', 'doctor-ak-portal' ); ?></h2>
	</div>
	<p class="dak-field-hint"><?php esc_html_e( 'For your clinic (cash-collected) appointments, you already hold the payment and owe the clinic its share. For your online-collected/video appointments, the clinic already holds the payment and owes you your share. This is your currently outstanding, unsettled balance.', 'doctor-ak-portal' ); ?></p>

	<div class="dak-admin-record-row">
		<div class="dak-admin-record-row-main">
			<span class="dak-admin-record-row-info">
				<span class="dak-admin-record-row-id">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: video earnings owed to the doctor, 2: clinic obligations owed by the doctor. */
							__( 'Owed to you: PKR %1$s &middot; You owe: PKR %2$s', 'doctor-ak-portal' ),
							number_format( $outstanding['video_earnings'], 0 ),
							number_format( $outstanding['clinic_obligations'], 0 )
						)
					);
					?>
				</span>
			</span>

			<span class="dak-admin-record-row-tags">
				<?php if ( $outstanding['closing_balance'] > 0.01 ) : ?>
					<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Clinic owes you', 'doctor-ak-portal' ); ?></span>
				<?php elseif ( $outstanding['closing_balance'] < -0.01 ) : ?>
					<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-disabled"><?php esc_html_e( 'You owe clinic', 'doctor-ak-portal' ); ?></span>
				<?php else : ?>
					<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Settled', 'doctor-ak-portal' ); ?></span>
				<?php endif; ?>
			</span>

			<span class="dak-admin-record-row-amount">PKR <?php echo esc_html( number_format( abs( $outstanding['closing_balance'] ), 0 ) ); ?></span>
		</div>
	</div>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Settlement history', 'doctor-ak-portal' ); ?></h2>
	</div>
	<?php if ( empty( $settlements ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No settlements recorded yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $settlements as $dak_settlement ) : ?>
			<div class="dak-admin-record-row">
				<div class="dak-admin-record-row-main">
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( sprintf( '%1$s &ndash; %2$s', $dak_settlement['period_start'], $dak_settlement['period_end'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() applied before the sprintf(). ?></strong>
					</span>
					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline<?php echo 'pending' === $dak_settlement['settlement_status'] ? '' : ' dak-status-pill-is-active'; ?>">
							<?php echo esc_html( ucfirst( $dak_settlement['settlement_status'] ) ); ?>
						</span>
					</span>
					<span class="dak-admin-record-row-amount">PKR <?php echo esc_html( number_format( abs( $dak_settlement['closing_balance'] ), 0 ) ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Ledger', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $ledger ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( "You don't have any paid appointments yet.", 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $ledger as $dak_row ) : ?>
			<div class="dak-admin-record-row">
				<div class="dak-admin-record-row-main">
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $dak_row['description'] ); ?></strong>
						<span class="dak-admin-record-row-id">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: clinic name or "Video Consultation", 2: transaction date. */
									__( '%1$s &middot; %2$s', 'doctor-ak-portal' ),
									0 === $dak_row['clinic_id'] ? __( 'Video Consultation', 'doctor-ak-portal' ) : ( isset( $clinics_by_id[ $dak_row['clinic_id'] ] ) ? $clinics_by_id[ $dak_row['clinic_id'] ] : __( 'Clinic', 'doctor-ak-portal' ) ),
									$dak_row['transaction_date']
								)
							);
							?>
						</span>
					</span>

					<span class="dak-admin-record-row-tags">
						<?php if ( $dak_row['settlement_id'] > 0 ) : ?>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Settled', 'doctor-ak-portal' ); ?></span>
						<?php else : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Unsettled', 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-amount"><?php echo esc_html( 'PKR ' . number_format( $dak_row['gross_amount'], 0 ) ); ?></span>
				</div>

				<div class="dak-admin-record-row-secondary">
					<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Split:', 'doctor-ak-portal' ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php echo esc_html( sprintf( /* translators: %s: doctor's share amount. */ __( 'Your share: PKR %s', 'doctor-ak-portal' ), number_format( $dak_row['doctor_amount'], 0 ) ) ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( /* translators: %s: clinic's share amount. */ __( "Clinic's share: PKR %s", 'doctor-ak-portal' ), number_format( $dak_row['clinic_amount'], 0 ) ) ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
