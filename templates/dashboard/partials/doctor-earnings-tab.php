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
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Earnings', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Your own share of every paid appointment — kept separate per clinic, and video consultations always on their own.', 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<div class="dak-summary-grid">
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Your earnings today', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $earnings['today'] ) ); ?></strong>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'This month', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $earnings['this_month'] ) ); ?></strong>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'All-time', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $earnings['total'] ) ); ?></strong>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Paid appointments', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( number_format_i18n( $earnings['invoice_count'] ) ); ?></strong>
	</div>
</div>

<div class="dak-alert dak-alert-info dak-earnings-split-note" role="note">
	<strong><?php esc_html_e( 'Your revenue split:', 'doctor-ak-portal' ); ?></strong>
	<?php if ( $dak_is_salary ) : ?>
		<?php esc_html_e( "You're on a salary — every payment you collect counts fully as clinic revenue rather than being split with you. Contact the clinic administrator if you believe this should be different.", 'doctor-ak-portal' ); ?>
	<?php else : ?>
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
	<?php endif; ?>
</div>

<div class="dak-form-columns dak-earnings-columns">
	<section class="dak-results" aria-labelledby="dak-earnings-by-clinic-title">
		<div class="dak-results-tools">
			<h2 class="dak-results-title" id="dak-earnings-by-clinic-title"><?php esc_html_e( 'Earnings by clinic', 'doctor-ak-portal' ); ?></h2>
		</div>
		<?php if ( empty( $dak_clinic_totals ) && 0.0 === $dak_video_total ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No earnings recorded yet.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<div class="dak-data-table-wrap">
				<table class="dak-data-table dak-ui-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Clinic / visit type', 'doctor-ak-portal' ); ?></th>
							<th scope="col" class="dak-col-num"><?php esc_html_e( 'Your earnings', 'doctor-ak-portal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $dak_clinic_totals as $dak_clinic_id => $dak_clinic_amount ) : ?>
							<tr data-row>
								<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Clinic', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong"><?php echo esc_html( isset( $clinics_by_id[ $dak_clinic_id ] ) ? $clinics_by_id[ $dak_clinic_id ] : __( 'Clinic', 'doctor-ak-portal' ) ); ?></span></td>
								<td class="dak-col-num" data-label="<?php esc_attr_e( 'Your earnings', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_clinic_amount ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( 0.0 !== $dak_video_total ) : ?>
							<tr data-row>
								<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Clinic', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong"><?php esc_html_e( 'Video consultations', 'doctor-ak-portal' ); ?></span></td>
								<td class="dak-col-num" data-label="<?php esc_attr_e( 'Your earnings', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_video_total ) ); ?></span></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>

	<?php
	if ( $outstanding['closing_balance'] > 0.01 ) {
		$dak_out = array( __( 'Owed to you', 'doctor-ak-portal' ), 'dak-status-pill-is-active' );
	} elseif ( $outstanding['closing_balance'] < -0.01 ) {
		$dak_out = array( __( 'You owe', 'doctor-ak-portal' ), 'dak-status-pill-is-pending' );
	} else {
		$dak_out = array( __( 'Settled', 'doctor-ak-portal' ), 'dak-status-pill-is-neutral' );
	}
	?>
	<section class="dak-form-card" aria-labelledby="dak-earnings-settlement-title">
		<div class="dak-form-card-header">
			<h2 id="dak-earnings-settlement-title"><?php esc_html_e( 'Settlement with the clinic', 'doctor-ak-portal' ); ?></h2>
			<p><?php esc_html_e( 'Clinic (cash-collected) visits: you hold the payment and owe the clinic its share. Online/video visits: the clinic holds the payment and owes you your share.', 'doctor-ak-portal' ); ?></p>
		</div>
		<div class="dak-form-card-body">
			<dl class="dak-detail-list dak-settlement-figures">
				<div><dt><?php esc_html_e( 'Owed to you (video / online)', 'doctor-ak-portal' ); ?></dt><dd class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $outstanding['video_earnings'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'You owe (clinic visits)', 'doctor-ak-portal' ); ?></dt><dd class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $outstanding['clinic_obligations'] ) ); ?></dd></div>
				<div class="is-total"><dt><?php esc_html_e( 'Currently outstanding', 'doctor-ak-portal' ); ?></dt><dd><span class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( abs( $outstanding['closing_balance'] ) ) ); ?></span> <span class="dak-status-pill <?php echo esc_attr( $dak_out[1] ); ?>"><?php echo esc_html( $dak_out[0] ); ?></span></dd></div>
			</dl>
		</div>
	</section>
</div>

<section class="dak-results" aria-labelledby="dak-earnings-settlements-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-earnings-settlements-title"><?php esc_html_e( 'Settlement history', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $settlements ) ) ); ?></span></h2>
	</div>
	<?php if ( empty( $settlements ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No settlements recorded yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Period', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( 'Amount', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $settlements as $dak_settlement ) : ?>
						<tr data-row>
							<td class="dak-col-primary dak-col-nowrap" data-label="<?php esc_attr_e( 'Period', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $dak_settlement['period_start'], $dak_settlement['period_start'] ) . ' – ' . \DoctorAKPortal\Includes\Dashboard_Format::date( $dak_settlement['period_end'], $dak_settlement['period_end'] ) ); ?></span></td>
							<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>"><span class="dak-status-pill <?php echo 'pending' === $dak_settlement['settlement_status'] ? 'dak-status-pill-is-pending' : 'dak-status-pill-is-active'; ?>"><?php echo esc_html( ucfirst( $dak_settlement['settlement_status'] ) ); ?></span></td>
							<td class="dak-col-num" data-label="<?php esc_attr_e( 'Amount', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( abs( $dak_settlement['closing_balance'] ) ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<section class="dak-results" aria-labelledby="dak-earnings-ledger-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-earnings-ledger-title"><?php esc_html_e( 'Ledger', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $ledger ) ) ); ?></span></h2>
	</div>
	<?php if ( empty( $ledger ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( "You don't have any paid appointments yet.", 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-ledger-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Description', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( 'Gross', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( 'Your share', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( "Clinic's share", 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Settlement', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ledger as $dak_row ) : ?>
						<tr data-row>
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Description', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( $dak_row['description'] ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( 0 === $dak_row['clinic_id'] ? __( 'Video consultation', 'doctor-ak-portal' ) : ( isset( $clinics_by_id[ $dak_row['clinic_id'] ] ) ? $clinics_by_id[ $dak_row['clinic_id'] ] : __( 'Clinic', 'doctor-ak-portal' ) ) ); ?></span>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Date', 'doctor-ak-portal' ); ?>"><span class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $dak_row['transaction_date'], $dak_row['transaction_date'] ) ); ?></span></td>
							<td class="dak-col-num" data-label="<?php esc_attr_e( 'Gross', 'doctor-ak-portal' ); ?>"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_row['gross_amount'] ) ); ?></td>
							<td class="dak-col-num" data-label="<?php esc_attr_e( 'Your share', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_row['doctor_amount'] ) ); ?></span></td>
							<td class="dak-col-num" data-label="<?php esc_attr_e( "Clinic's share", 'doctor-ak-portal' ); ?>"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_row['clinic_amount'] ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Settlement', 'doctor-ak-portal' ); ?>">
								<?php if ( $dak_row['settlement_id'] > 0 ) : ?>
									<span class="dak-status-pill dak-status-pill-is-active"><?php esc_html_e( 'Settled', 'doctor-ak-portal' ); ?></span>
								<?php else : ?>
									<span class="dak-status-pill dak-status-pill-is-neutral"><?php esc_html_e( 'Unsettled', 'doctor-ak-portal' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
</div>