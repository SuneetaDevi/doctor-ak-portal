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
 * @var array      $clinic_location_groups Clinic filter options — location key ('loc:<clinic_location_id>' or 'row:<clinics id>') => { label, clinic_ids }, alphabetical by label. One entry per physical clinic regardless of how many doctors practise there (see Admin_Dashboard's billing section) — the Doctor filter next to it already covers "by doctor".
 * @var array      $settlements       Settlement_Manager::all_flat_for_admin() rows (filtered to the selected doctor, if any).
 * @var array|null $outstanding       Revenue_Ledger::outstanding_for_doctor() for the selected doctor, or null if no doctor filter is active.
 * @var string     $billing_url       Unfiltered URL of this section, for the filter form and "Clear" link.
 * @var string     $view              Active grouping for the balances list: 'doctor' (default) or 'clinic'.
 * @var array      $filters           Active filter values: doctor_id, clinic_id (a $clinic_location_groups key, '-1' for video-only, or ''), date_from, date_to.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_has_filters     = array_filter( $filters, function ( $value ) { return '' !== $value && 0 !== $value; } );
$dak_selected_doctor = ! empty( $filters['doctor_id'] ) ? (int) $filters['doctor_id'] : 0;
$dak_money           = array( '\DoctorAKPortal\Includes\Dashboard_Format', 'money' );

/**
 * Direction of a balance as text + badge: positive = the platform owes the
 * doctor, negative = the doctor owes the platform (a receivable — not an
 * error, so amber rather than red). Thresholds unchanged.
 */
$dak_direction = function ( $balance, $payable_label, $receivable_label ) {
	if ( $balance > 0.01 ) {
		return array( $payable_label, 'dak-status-pill-is-active' );
	}

	if ( $balance < -0.01 ) {
		return array( $receivable_label, 'dak-status-pill-is-pending' );
	}

	return array( __( 'Settled', 'doctor-ak-portal' ), 'dak-status-pill-is-neutral' );
};
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Billing', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( "Doctor + clinic-wise revenue ledger — each clinic's earnings stay separate, even for the same doctor, and video consultations are always accounted on their own.", 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<div class="dak-summary-grid dak-billing-summary">
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Gross collected', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( call_user_func( $dak_money, $summary['gross_total'] ) ); ?></strong>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Video consultations', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( call_user_func( $dak_money, $summary['video_gross'] ) ); ?></strong>
		<span class="dak-summary-card-sub"><?php esc_html_e( 'Gross', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Physical clinic visits', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( call_user_func( $dak_money, $summary['clinic_gross'] ) ); ?></strong>
		<span class="dak-summary-card-sub"><?php esc_html_e( 'Gross', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Platform / gateway fees', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( call_user_func( $dak_money, $summary['platform_fees'] ) ); ?></strong>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( "Doctors' total share", 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( call_user_func( $dak_money, $summary['doctor_earnings'] ) ); ?></strong>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php echo esc_html( $summary['outstanding_balance'] >= 0 ? __( 'Outstanding — payable to doctors', 'doctor-ak-portal' ) : __( 'Outstanding — receivable from doctors', 'doctor-ak-portal' ) ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( call_user_func( $dak_money, abs( $summary['outstanding_balance'] ) ) ); ?></strong>
	</div>
</div>

<div class="dak-list-toolbar">
	<form method="get" action="<?php echo esc_url( $billing_url ); ?>" class="dak-list-filters">
		<input type="hidden" name="section" value="billing">
		<?php if ( 'clinic' === $view ) : ?>
			<input type="hidden" name="view" value="clinic">
		<?php endif; ?>

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
				<?php foreach ( $clinic_location_groups as $dak_location_key => $dak_clinic_group ) : ?>
					<option value="<?php echo esc_attr( $dak_location_key ); ?>" <?php selected( (string) $filters['clinic_id'] === $dak_location_key ); ?>><?php echo esc_html( $dak_clinic_group['label'] ); ?></option>
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

		<div class="dak-list-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
			<?php if ( ! empty( $dak_has_filters ) ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $billing_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</div>

<?php
$dak_view_base_args = array_filter(
	array(
		'doctor_id' => $filters['doctor_id'],
		'clinic_id' => $filters['clinic_id'],
		'date_from' => $filters['date_from'],
		'date_to'   => $filters['date_to'],
	),
	function ( $value ) {
		return '' !== $value && 0 !== $value;
	}
);
$dak_is_clinic_view = 'clinic' === $view;
$dak_groups         = array();

foreach ( $balances as $dak_balance_row ) {
	$dak_groups[ $dak_is_clinic_view ? $dak_balance_row['clinic_id'] : $dak_balance_row['doctor_id'] ][] = $dak_balance_row;
}
?>
<section class="dak-results" aria-labelledby="dak-billing-balances-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-billing-balances-title"><?php esc_html_e( 'Balances by doctor & clinic', 'doctor-ak-portal' ); ?></h2>
		<nav class="dak-segmented" aria-label="<?php esc_attr_e( 'Group balances by', 'doctor-ak-portal' ); ?>">
			<a class="dak-segmented-item<?php echo ! $dak_is_clinic_view ? ' is-active' : ''; ?>"<?php echo ! $dak_is_clinic_view ? ' aria-current="page"' : ''; ?> href="<?php echo esc_url( add_query_arg( array_merge( $dak_view_base_args, array( 'section' => 'billing', 'view' => 'doctor' ) ), $billing_url ) ); ?>"><?php esc_html_e( 'By doctor', 'doctor-ak-portal' ); ?></a>
			<a class="dak-segmented-item<?php echo $dak_is_clinic_view ? ' is-active' : ''; ?>"<?php echo $dak_is_clinic_view ? ' aria-current="page"' : ''; ?> href="<?php echo esc_url( add_query_arg( array_merge( $dak_view_base_args, array( 'section' => 'billing', 'view' => 'clinic' ) ), $billing_url ) ); ?>"><?php esc_html_e( 'By clinic', 'doctor-ak-portal' ); ?></a>
		</nav>
	</div>
	<p class="dak-results-note"><?php esc_html_e( "Each doctor's clinics (and video consultations) are kept separate — never merged into one figure. Payable = the platform owes the doctor; receivable = the doctor owes the platform.", 'doctor-ak-portal' ); ?></p>

	<?php if ( empty( $balances ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No outstanding balances match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-billing-table">
				<thead>
					<tr>
						<th scope="col"><?php echo $dak_is_clinic_view ? esc_html__( 'Doctor', 'doctor-ak-portal' ) : esc_html__( 'Clinic / visit type', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Paid appointments', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Direction', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( 'Balance', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<?php foreach ( $dak_groups as $dak_group_id => $dak_group_rows ) : ?>
					<tbody>
						<tr class="dak-data-table-group">
							<th scope="colgroup" colspan="5">
								<span class="dak-group-head">
									<span><?php echo esc_html( $dak_is_clinic_view ? $dak_group_rows[0]['clinic_name'] : $dak_group_rows[0]['doctor_name'] ); ?></span>
									<?php if ( ! $dak_is_clinic_view ) : ?>
										<a class="dak-text-action" href="<?php echo esc_url( \DoctorAKPortal\Frontend\Settlement_Handler::statement_download_url( $dak_group_id, $filters['date_from'], $filters['date_to'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download statement', 'doctor-ak-portal' ); ?></a>
									<?php endif; ?>
								</span>
							</th>
						</tr>
						<?php foreach ( $dak_group_rows as $dak_row ) : ?>
							<?php list( $dak_dir_label, $dak_dir_class ) = $dak_direction( $dak_row['balance'], __( 'Payable to doctor', 'doctor-ak-portal' ), __( 'Receivable from doctor', 'doctor-ak-portal' ) ); ?>
							<tr data-row>
								<td class="dak-col-primary" data-label="<?php echo $dak_is_clinic_view ? esc_attr__( 'Doctor', 'doctor-ak-portal' ) : esc_attr__( 'Clinic / visit type', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-strong"><?php echo esc_html( $dak_is_clinic_view ? $dak_row['doctor_name'] : $dak_row['clinic_name'] ); ?></span>
								</td>
								<td class="is-tabular" data-label="<?php esc_attr_e( 'Paid appointments', 'doctor-ak-portal' ); ?>"><?php echo esc_html( number_format_i18n( $dak_row['appointment_count'] ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Direction', 'doctor-ak-portal' ); ?>"><span class="dak-status-pill <?php echo esc_attr( $dak_dir_class ); ?>"><?php echo esc_html( $dak_dir_label ); ?></span></td>
								<td class="dak-col-num" data-label="<?php esc_attr_e( 'Balance', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong"><?php echo esc_html( call_user_func( $dak_money, abs( $dak_row['balance'] ) ) ); ?></span></td>
								<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
									<div class="dak-row-actions">
										<button
											type="button"
											class="dak-text-action dak-billing-view-details"
											data-doctor-id="<?php echo esc_attr( $dak_row['doctor_id'] ); ?>"
											data-clinic-id="<?php echo esc_attr( $dak_row['clinic_id'] ); ?>"
											data-doctor-name="<?php echo esc_attr( $dak_row['doctor_name'] ); ?>"
											data-clinic-name="<?php echo esc_attr( $dak_row['clinic_name'] ); ?>"
										><?php esc_html_e( 'View details', 'doctor-ak-portal' ); ?></button>
										<?php if ( $dak_is_clinic_view ) : ?>
											<a class="dak-text-action" href="<?php echo esc_url( \DoctorAKPortal\Frontend\Settlement_Handler::statement_download_url( $dak_row['doctor_id'], $filters['date_from'], $filters['date_to'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Statement', 'doctor-ak-portal' ); ?></a>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
		</div>
	<?php endif; ?>
</section>

<div class="dak-portal dak-modal" id="dak-billing-details-modal" aria-hidden="true">
	<div class="dak-modal-overlay" id="dak-billing-details-overlay"></div>

	<div class="dak-modal-dialog dak-modal-dialog-form" role="dialog" aria-modal="true" aria-labelledby="dak-billing-details-title">
		<div class="dak-modal-header">
			<h2 id="dak-billing-details-title"><?php esc_html_e( 'Details', 'doctor-ak-portal' ); ?></h2>
			<button type="button" class="dak-modal-close" id="dak-billing-details-close" aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
		</div>

		<div class="dak-modal-body" id="dak-billing-details-body">
			<p class="dak-empty-state"><?php esc_html_e( 'Loading…', 'doctor-ak-portal' ); ?></p>
		</div>
	</div>
</div>

<?php if ( $dak_selected_doctor > 0 && null !== $outstanding ) : ?>
	<?php list( $dak_out_label, $dak_out_class ) = $dak_direction( $outstanding['closing_balance'], __( 'Payable to doctor', 'doctor-ak-portal' ), __( 'Receivable from doctor', 'doctor-ak-portal' ) ); ?>
	<section class="dak-form-card dak-billing-settlement" id="dak-billing-settlement-panel" data-doctor-id="<?php echo esc_attr( $dak_selected_doctor ); ?>" aria-labelledby="dak-billing-settlement-title">
		<div class="dak-form-card-header">
			<h2 id="dak-billing-settlement-title"><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Settlement — %s', 'doctor-ak-portal' ), $doctor_options[ $dak_selected_doctor ]['name'] ) ); ?></h2>
			<p><?php esc_html_e( 'What is currently outstanding (not yet settled) for this doctor, and a new settlement for a period.', 'doctor-ak-portal' ); ?></p>
		</div>
		<div class="dak-form-card-body">
			<div class="dak-alert dak-alert-error dak-hidden" id="dak-billing-settlement-error" role="alert"></div>
			<div class="dak-alert dak-alert-success dak-hidden" id="dak-billing-settlement-success" role="status"></div>

			<dl class="dak-detail-list dak-settlement-figures">
				<div><dt><?php esc_html_e( 'Video earnings (owed to doctor)', 'doctor-ak-portal' ); ?></dt><dd class="is-tabular"><?php echo esc_html( call_user_func( $dak_money, $outstanding['video_earnings'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Clinic obligations (owed by doctor)', 'doctor-ak-portal' ); ?></dt><dd class="is-tabular"><?php echo esc_html( call_user_func( $dak_money, $outstanding['clinic_obligations'] ) ); ?></dd></div>
				<div class="is-total"><dt><?php esc_html_e( 'Currently outstanding', 'doctor-ak-portal' ); ?></dt><dd><span class="is-tabular"><?php echo esc_html( call_user_func( $dak_money, abs( $outstanding['closing_balance'] ) ) ); ?></span> <span class="dak-status-pill <?php echo esc_attr( $dak_out_class ); ?>"><?php echo esc_html( $dak_out_label ); ?></span></dd></div>
			</dl>

			<?php if ( 0.0 !== $outstanding['closing_balance'] ) : ?>
				<form id="dak-billing-create-settlement-form" class="dak-list-filters dak-settlement-form">
					<div class="dak-field">
						<label for="dak-settlement-period-start"><?php esc_html_e( 'Period start', 'doctor-ak-portal' ); ?></label>
						<input type="date" id="dak-settlement-period-start" value="<?php echo esc_attr( '' !== $filters['date_from'] ? $filters['date_from'] : gmdate( 'Y-m-01' ) ); ?>" required>
					</div>
					<div class="dak-field">
						<label for="dak-settlement-period-end"><?php esc_html_e( 'Period end', 'doctor-ak-portal' ); ?></label>
						<input type="date" id="dak-settlement-period-end" value="<?php echo esc_attr( '' !== $filters['date_to'] ? $filters['date_to'] : gmdate( 'Y-m-d' ) ); ?>" required>
					</div>
					<div class="dak-field is-search">
						<label for="dak-settlement-notes"><?php esc_html_e( 'Notes', 'doctor-ak-portal' ); ?> <span class="dak-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></span></label>
						<input type="text" id="dak-settlement-notes">
					</div>
					<div class="dak-list-filter-actions">
						<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Create settlement', 'doctor-ak-portal' ); ?></button>
					</div>
				</form>
			<?php endif; ?>
		</div>
	</section>

	<section class="dak-results" aria-labelledby="dak-billing-history-title">
		<div class="dak-results-tools">
			<h2 class="dak-results-title" id="dak-billing-history-title"><?php esc_html_e( 'Settlement history', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $settlements ) ) ); ?></span></h2>
		</div>

		<?php if ( empty( $settlements ) ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No settlements recorded yet for this doctor.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<div class="dak-data-table-wrap">
				<table class="dak-data-table dak-ui-table dak-settlements-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Period', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Notes', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
							<th scope="col" class="dak-col-num"><?php esc_html_e( 'Amount', 'doctor-ak-portal' ); ?></th>
							<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $settlements as $dak_settlement ) : ?>
							<?php $dak_is_pending = \DoctorAKPortal\Includes\Settlement_Manager::STATUS_PENDING === $dak_settlement['settlement_status']; ?>
							<tr data-row>
								<td class="dak-col-primary dak-col-nowrap" data-label="<?php esc_attr_e( 'Period', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $dak_settlement['period_start'], $dak_settlement['period_start'] ) . ' – ' . \DoctorAKPortal\Includes\Dashboard_Format::date( $dak_settlement['period_end'], $dak_settlement['period_end'] ) ); ?></span>
								</td>
								<td data-label="<?php esc_attr_e( 'Notes', 'doctor-ak-portal' ); ?>"><?php if ( '' !== $dak_settlement['notes'] ) : ?><?php echo esc_html( $dak_settlement['notes'] ); ?><?php else : ?><span class="dak-cell-sub"><?php esc_html_e( 'No notes', 'doctor-ak-portal' ); ?></span><?php endif; ?></td>
								<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>"><span class="dak-status-pill <?php echo $dak_is_pending ? 'dak-status-pill-is-pending' : 'dak-status-pill-is-active'; ?>"><?php echo esc_html( ucfirst( $dak_settlement['settlement_status'] ) ); ?></span></td>
								<td class="dak-col-num" data-label="<?php esc_attr_e( 'Amount', 'doctor-ak-portal' ); ?>"><span class="dak-cell-strong"><?php echo esc_html( call_user_func( $dak_money, abs( $dak_settlement['closing_balance'] ) ) ); ?></span></td>
								<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
									<?php if ( $dak_is_pending ) : ?>
										<div class="dak-row-actions">
											<?php if ( in_array( $dak_settlement['settlement_direction'], array( \DoctorAKPortal\Includes\Settlement_Manager::DIRECTION_PAYABLE_TO_DOCTOR, \DoctorAKPortal\Includes\Settlement_Manager::DIRECTION_SETTLED ), true ) ) : ?>
												<button type="button" class="dak-text-action dak-billing-mark-paid" data-settlement-id="<?php echo esc_attr( $dak_settlement['id'] ); ?>"><?php esc_html_e( 'Mark paid', 'doctor-ak-portal' ); ?></button>
											<?php endif; ?>
											<?php if ( in_array( $dak_settlement['settlement_direction'], array( \DoctorAKPortal\Includes\Settlement_Manager::DIRECTION_RECEIVABLE_FROM_DOCTOR, \DoctorAKPortal\Includes\Settlement_Manager::DIRECTION_SETTLED ), true ) ) : ?>
												<button type="button" class="dak-text-action dak-billing-mark-received" data-settlement-id="<?php echo esc_attr( $dak_settlement['id'] ); ?>"><?php esc_html_e( 'Mark received', 'doctor-ak-portal' ); ?></button>
											<?php endif; ?>
										</div>
									<?php else : ?>
										<span class="dak-cell-sub"><?php esc_html_e( 'Done', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>
</div>