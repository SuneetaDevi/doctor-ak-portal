<?php
/**
 * Template: "Billing" admin section — revenue stat cards plus every paid
 * appointment (an "invoice"), filterable by date range, each with a
 * "Download" action for its PDF invoice (the same one emailed at payment
 * time, see Invoice_Pdf/Notifications::send_invoice()).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $invoices    Rows from Appointments::all_for_admin( [ 'payment_status' => 'paid', ... ] ).
 * @var array  $revenue     { total, this_month, today, invoice_count }, see Appointments::revenue_summary().
 * @var string $billing_url Unfiltered URL of this section, for the filter form and "Clear" link.
 * @var array  $filters     Active filter values: date_from, date_to.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_billing_icons = array(
	'money'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2v7.6M12.2 8.1c0-1-1-1.6-2.2-1.6s-2.2.6-2.2 1.5c0 2.2 4.4 1 4.4 3.2 0 .9-1 1.5-2.2 1.5s-2.2-.6-2.2-1.6"/></svg>',
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'receipt'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h10v14l-2-1.3L11 17l-2-1.3L7 17l-2-1.3z"/><path d="M7.5 7h5M7.5 10h5"/></svg>',
	'download' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3v10M6.5 9.5 10 13l3.5-3.5"/><path d="M4 15.5h12"/></svg>',
);

$dak_has_filters = '' !== $filters['date_from'] || '' !== $filters['date_to'];
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Billing', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Revenue from every paid appointment, with a downloadable PDF invoice for each.', 'doctor-ak-portal' ); ?></p>
</div>

<section class="dak-dashboard-statistics">
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_billing_icons['money']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $revenue['total'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Total Revenue', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_billing_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $revenue['this_month'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'This Month', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_billing_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $revenue['today'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Today', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_billing_icons['receipt']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $revenue['invoice_count'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Total Invoices', 'doctor-ak-portal' ); ?></span>
	</div>
</section>

<section class="dak-dashboard-card">
	<form method="get" action="<?php echo esc_url( $billing_url ); ?>" class="dak-field-row">
		<input type="hidden" name="section" value="billing">

		<div class="dak-field">
			<label for="dak-admin-billing-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-billing-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-billing-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-billing-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

		<div class="dak-admin-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $billing_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</section>

<section class="dak-dashboard-card dak-admin-users-card">
<?php if ( empty( $invoices ) ) : ?>
	<p class="dak-empty-state"><?php esc_html_e( 'No paid appointments match these filters.', 'doctor-ak-portal' ); ?></p>
<?php else : ?>
	<div class="dak-table-scroll">
		<table class="dak-admin-users-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Invoice', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Payment Mode', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'doctor-ak-portal' ); ?></th>
					<th class="dak-admin-users-actions-col"><?php esc_html_e( 'Action', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $invoices as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Invoice', 'doctor-ak-portal' ); ?>"><?php echo esc_html( sprintf( 'INV-%05d', $row['id'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Patient', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['patient_name'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>"><?php echo esc_html( sprintf( 'Dr. %s', $row['doctor_name'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Date', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['date'] . ' ' . $row['time'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Payment Mode', 'doctor-ak-portal' ); ?>">
							<?php echo esc_html( 'online' === $row['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : __( 'Manual', 'doctor-ak-portal' ) ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Amount', 'doctor-ak-portal' ); ?>"><?php echo esc_html( 'PKR' . number_format( (float) $row['charge'], 0 ) . '/-' ); ?></td>
						<td class="dak-admin-users-actions-col">
							<a
								class="dak-icon-button"
								href="<?php echo esc_url( \DoctorAKPortal\Frontend\Appointment_Handler::invoice_download_url( $row['id'] ) ); ?>"
								title="<?php esc_attr_e( 'Download Invoice', 'doctor-ak-portal' ); ?>"
								aria-label="<?php esc_attr_e( 'Download Invoice', 'doctor-ak-portal' ); ?>"
							><?php echo $dak_billing_icons['download']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
</section>
