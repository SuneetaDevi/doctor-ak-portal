<?php
/**
 * Template: "Billing" admin section — the doctor+clinic-wise revenue
 * ledger (Revenue_Ledger) and settlement panel (Settlement_Manager).
 * Every clinic is broken out separately, even for the same doctor, and
 * video consultations are always their own line (clinic_id = 0) — see
 * Revenue_Calculator's docblock for the accounting model this reflects.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array      $balances          Rows from Revenue_Ledger::balances_by_doctor_and_clinic(), each augmented with 'clinic_name'/'doctor_name' — the first-view "who owes whom" summary, grouped by doctor.
 * @var array      $summary           Revenue_Ledger::summary() for the active filters.
 * @var array      $doctor_options    Doctor user ID => { name, is_disabled }.
 * @var array      $clinics_by_doctor Doctor user ID => list of decoded Clinics rows.
 * @var array      $settlements       Settlement_Manager::all_flat_for_admin() rows (filtered to the selected doctor, if any).
 * @var array|null $outstanding       Revenue_Ledger::outstanding_for_doctor() for the selected doctor, or null if no doctor filter is active.
 * @var string     $billing_url       Unfiltered URL of this section, for the filter form and "Clear" link.
 * @var array      $filters           Active filter values: doctor_id, clinic_id, date_from, date_to.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_icons = array(
	'money'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2v7.6M12.2 8.1c0-1-1-1.6-2.2-1.6s-2.2.6-2.2 1.5c0 2.2 4.4 1 4.4 3.2 0 .9-1 1.5-2.2 1.5s-2.2-.6-2.2-1.6"/></svg>',
	'video'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M17.5 7.5 12.5 10l5 2.5z"/></svg>',
	'clinic'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 10 3l7 5.5V17H3z"/><path d="M8 17v-5h4v5"/></svg>',
	'balance'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3v14M4 7h12M6 7l-2.5 5a2.5 2.5 0 0 0 5 0zM16.5 7 14 12a2.5 2.5 0 0 0 5 0z"/></svg>',
);

$dak_has_filters = array_filter( $filters, function ( $value ) { return '' !== $value && 0 !== $value; } );
$dak_selected_doctor = ! empty( $filters['doctor_id'] ) ? (int) $filters['doctor_id'] : 0;

$dak_clinic_choices = array();
foreach ( $clinics_by_doctor as $dak_doctor_id => $dak_doctor_clinics ) {
	foreach ( $dak_doctor_clinics as $dak_clinic_row ) {
		if ( 'physical' !== $dak_clinic_row['type'] ) {
			continue;
		}
		$dak_clinic_choices[ $dak_clinic_row['id'] ] = sprintf(
			'%1$s — %2$s',
			isset( $doctor_options[ $dak_doctor_id ] ) ? $doctor_options[ $dak_doctor_id ]['name'] : '',
			$dak_clinic_row['name']
		);
	}
}
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Billing', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( "Doctor + clinic-wise revenue ledger — each clinic's earnings stay separate, even for the same doctor, and video consultations are always accounted on their own.", 'doctor-ak-portal' ); ?></p>
</div>

<section class="dak-dashboard-statistics">
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_icons['money']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $summary['gross_total'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Gross collected', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $summary['video_gross'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Video consultations', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_icons['clinic']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $summary['clinic_gross'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Physical clinic visits', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_icons['money']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $summary['platform_fees'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Platform/gateway fees', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_icons['money']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $summary['doctor_earnings'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( "Doctors' total share", 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_icons['balance']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( abs( $summary['outstanding_balance'] ) ) ); ?></span>
		<span class="dak-stat-label"><?php echo esc_html( $summary['outstanding_balance'] >= 0 ? __( 'Outstanding — payable to doctors', 'doctor-ak-portal' ) : __( 'Outstanding — receivable from doctors', 'doctor-ak-portal' ) ); ?></span>
	</div>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Balances by Doctor & Clinic', 'doctor-ak-portal' ); ?></h2>
	</div>
	<p class="dak-field-hint"><?php esc_html_e( "Each doctor's clinics (and video consultations) are kept separate — never merged into one figure. Positive = clinic owes the doctor, negative = doctor owes the clinic.", 'doctor-ak-portal' ); ?></p>

	<?php if ( empty( $balances ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No outstanding balances match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php
		$dak_balances_by_doctor = array();
		foreach ( $balances as $dak_balance_row ) {
			$dak_balances_by_doctor[ $dak_balance_row['doctor_id'] ][] = $dak_balance_row;
		}
		?>
		<?php foreach ( $dak_balances_by_doctor as $dak_doctor_id => $dak_doctor_balances ) : ?>
			<div class="dak-billing-doctor-group">
				<div class="dak-billing-doctor-header">
					<strong><?php echo esc_html( $dak_doctor_balances[0]['doctor_name'] ); ?></strong>
					<a
						class="dak-button dak-button-secondary dak-button-sm"
						href="<?php echo esc_url( \DoctorAKPortal\Frontend\Settlement_Handler::statement_download_url( $dak_doctor_id, $filters['date_from'], $filters['date_to'] ) ); ?>"
						target="_blank"
						rel="noopener"
					>
						<?php esc_html_e( 'Download Statement', 'doctor-ak-portal' ); ?>
					</a>
				</div>

				<?php foreach ( $dak_doctor_balances as $dak_row ) : ?>
					<div class="dak-admin-record-row">
						<div class="dak-admin-record-row-main">
							<span class="dak-admin-record-row-info">
								<strong><?php echo esc_html( $dak_row['clinic_name'] ); ?></strong>
								<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( /* translators: %d: number of paid appointments. */ _n( '%d appointment', '%d appointments', $dak_row['appointment_count'], 'doctor-ak-portal' ), $dak_row['appointment_count'] ) ); ?></span>
							</span>

							<span class="dak-admin-record-row-tags">
								<?php if ( $dak_row['balance'] > 0.01 ) : ?>
									<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Clinic owes doctor', 'doctor-ak-portal' ); ?></span>
								<?php elseif ( $dak_row['balance'] < -0.01 ) : ?>
									<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-disabled"><?php esc_html_e( 'Doctor owes clinic', 'doctor-ak-portal' ); ?></span>
								<?php else : ?>
									<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Settled', 'doctor-ak-portal' ); ?></span>
								<?php endif; ?>
							</span>

							<span class="dak-admin-record-row-amount">PKR <?php echo esc_html( number_format( abs( $dak_row['balance'] ), 0 ) ); ?></span>

							<span class="dak-admin-record-row-actions">
								<button
									type="button"
									class="dak-button dak-button-secondary dak-button-sm dak-billing-view-details"
									data-doctor-id="<?php echo esc_attr( $dak_doctor_id ); ?>"
									data-clinic-id="<?php echo esc_attr( $dak_row['clinic_id'] ); ?>"
									data-doctor-name="<?php echo esc_attr( $dak_row['doctor_name'] ); ?>"
									data-clinic-name="<?php echo esc_attr( $dak_row['clinic_name'] ); ?>"
								><?php esc_html_e( 'View Details', 'doctor-ak-portal' ); ?></button>
							</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>

<div class="dak-portal dak-modal" id="dak-billing-details-modal" aria-hidden="true">
	<div class="dak-modal-overlay" id="dak-billing-details-overlay"></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-billing-details-title">
		<button type="button" class="dak-modal-close" id="dak-billing-details-close" aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-billing-details-title"><?php esc_html_e( 'Details', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div id="dak-billing-details-body">
			<p class="dak-empty-state"><?php esc_html_e( 'Loading…', 'doctor-ak-portal' ); ?></p>
		</div>
	</div>
</div>

<section class="dak-dashboard-card dak-appt-filters-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
	</div>

	<form method="get" action="<?php echo esc_url( $billing_url ); ?>" class="dak-appt-filters-form">
		<input type="hidden" name="section" value="billing">

		<div class="dak-field">
			<label for="dak-billing-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-billing-doctor" name="doctor_id" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>">
				<option value=""><?php esc_html_e( 'All doctors', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctor_options as $dak_doctor_id => $dak_doctor_option ) : ?>
					<option value="<?php echo esc_attr( $dak_doctor_id ); ?>" <?php selected( (int) $filters['doctor_id'] === (int) $dak_doctor_id ); ?>><?php echo esc_html( $dak_doctor_option['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-billing-clinic"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></label>
			<select id="dak-billing-clinic" name="clinic_id">
				<option value=""><?php esc_html_e( 'All clinics', 'doctor-ak-portal' ); ?></option>
				<option value="-1" <?php selected( '-1' === (string) $filters['clinic_id'] ); ?>><?php esc_html_e( 'Video consultations only', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $dak_clinic_choices as $dak_clinic_id => $dak_clinic_label ) : ?>
					<option value="<?php echo esc_attr( $dak_clinic_id ); ?>" <?php selected( (string) $filters['clinic_id'] === (string) $dak_clinic_id ); ?>><?php echo esc_html( $dak_clinic_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-billing-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-billing-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-billing-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-billing-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

		<div class="dak-admin-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
			<?php if ( ! empty( $dak_has_filters ) ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $billing_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</section>

<?php if ( $dak_selected_doctor > 0 && null !== $outstanding ) : ?>
	<section class="dak-dashboard-card" id="dak-billing-settlement-panel" data-doctor-id="<?php echo esc_attr( $dak_selected_doctor ); ?>">
		<div class="dak-dashboard-card-header">
			<h2><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Settlement — %s', 'doctor-ak-portal' ), $doctor_options[ $dak_selected_doctor ]['name'] ) ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-billing-settlement-error" role="alert"></div>
		<div class="dak-alert dak-alert-success dak-hidden" id="dak-billing-settlement-success" role="status"></div>

		<div class="dak-admin-record-row">
			<div class="dak-admin-record-row-main">
				<span class="dak-admin-record-row-info">
					<strong><?php esc_html_e( 'Currently outstanding (unsettled)', 'doctor-ak-portal' ); ?></strong>
					<span class="dak-admin-record-row-id">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: video earnings owed to the doctor, 2: clinic-visit obligations owed by the doctor. */
								__( 'Video earnings: PKR %1$s &middot; Clinic obligations: PKR %2$s', 'doctor-ak-portal' ),
								number_format( $outstanding['video_earnings'], 0 ),
								number_format( $outstanding['clinic_obligations'], 0 )
							)
						);
						?>
					</span>
				</span>
				<span class="dak-admin-record-row-tags">
					<?php if ( $outstanding['closing_balance'] > 0.01 ) : ?>
						<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Payable to doctor', 'doctor-ak-portal' ); ?></span>
					<?php elseif ( $outstanding['closing_balance'] < -0.01 ) : ?>
						<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-disabled"><?php esc_html_e( 'Receivable from doctor', 'doctor-ak-portal' ); ?></span>
					<?php else : ?>
						<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Settled', 'doctor-ak-portal' ); ?></span>
					<?php endif; ?>
				</span>
				<span class="dak-admin-record-row-amount">PKR <?php echo esc_html( number_format( abs( $outstanding['closing_balance'] ), 0 ) ); ?></span>
			</div>
		</div>

		<?php if ( 0.0 !== $outstanding['closing_balance'] ) : ?>
			<form id="dak-billing-create-settlement-form" class="dak-field-row" style="margin-top: 16px;">
				<div class="dak-field">
					<label for="dak-settlement-period-start"><?php esc_html_e( 'Period start', 'doctor-ak-portal' ); ?></label>
					<input type="date" id="dak-settlement-period-start" value="<?php echo esc_attr( '' !== $filters['date_from'] ? $filters['date_from'] : gmdate( 'Y-m-01' ) ); ?>" required>
				</div>
				<div class="dak-field">
					<label for="dak-settlement-period-end"><?php esc_html_e( 'Period end', 'doctor-ak-portal' ); ?></label>
					<input type="date" id="dak-settlement-period-end" value="<?php echo esc_attr( '' !== $filters['date_to'] ? $filters['date_to'] : gmdate( 'Y-m-d' ) ); ?>" required>
				</div>
				<div class="dak-field">
					<label for="dak-settlement-notes"><?php esc_html_e( 'Notes (optional)', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-settlement-notes">
				</div>
				<div class="dak-admin-filter-actions">
					<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Create Settlement', 'doctor-ak-portal' ); ?></button>
				</div>
			</form>
		<?php endif; ?>
	</section>

	<section class="dak-dashboard-card">
		<div class="dak-dashboard-card-header">
			<h2><?php esc_html_e( 'Settlement history', 'doctor-ak-portal' ); ?></h2>
		</div>

		<?php if ( empty( $settlements ) ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No settlements recorded yet for this doctor.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<?php foreach ( $settlements as $dak_settlement ) : ?>
				<div class="dak-admin-record-row">
					<div class="dak-admin-record-row-main">
						<span class="dak-admin-record-row-info">
							<strong><?php echo esc_html( sprintf( '%1$s &ndash; %2$s', $dak_settlement['period_start'], $dak_settlement['period_end'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() applied before the sprintf(). ?></strong>
							<span class="dak-admin-record-row-id"><?php echo esc_html( '' !== $dak_settlement['notes'] ? $dak_settlement['notes'] : __( 'No notes', 'doctor-ak-portal' ) ); ?></span>
						</span>
						<span class="dak-admin-record-row-tags">
							<span class="dak-status-pill dak-status-pill-outline<?php echo \DoctorAKPortal\Includes\Settlement_Manager::STATUS_PENDING === $dak_settlement['settlement_status'] ? '' : ' dak-status-pill-is-active'; ?>">
								<?php echo esc_html( ucfirst( $dak_settlement['settlement_status'] ) ); ?>
							</span>
						</span>
						<span class="dak-admin-record-row-amount">PKR <?php echo esc_html( number_format( abs( $dak_settlement['closing_balance'] ), 0 ) ); ?></span>
						<?php if ( \DoctorAKPortal\Includes\Settlement_Manager::STATUS_PENDING === $dak_settlement['settlement_status'] ) : ?>
							<span class="dak-admin-record-row-actions">
								<?php if ( in_array( $dak_settlement['settlement_direction'], array( \DoctorAKPortal\Includes\Settlement_Manager::DIRECTION_PAYABLE_TO_DOCTOR, \DoctorAKPortal\Includes\Settlement_Manager::DIRECTION_SETTLED ), true ) ) : ?>
									<button type="button" class="dak-button dak-button-secondary dak-button-sm dak-billing-mark-paid" data-settlement-id="<?php echo esc_attr( $dak_settlement['id'] ); ?>"><?php esc_html_e( 'Mark Paid', 'doctor-ak-portal' ); ?></button>
								<?php endif; ?>
								<?php if ( in_array( $dak_settlement['settlement_direction'], array( \DoctorAKPortal\Includes\Settlement_Manager::DIRECTION_RECEIVABLE_FROM_DOCTOR, \DoctorAKPortal\Includes\Settlement_Manager::DIRECTION_SETTLED ), true ) ) : ?>
									<button type="button" class="dak-button dak-button-secondary dak-button-sm dak-billing-mark-received" data-settlement-id="<?php echo esc_attr( $dak_settlement['id'] ); ?>"><?php esc_html_e( 'Mark Received', 'doctor-ak-portal' ); ?></button>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
<?php endif; ?>
