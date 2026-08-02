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
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $revenue['today'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Revenue today', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_billing_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $revenue['this_month'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'This month', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_billing_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $revenue['total'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'All-time', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_billing_icons['download']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $revenue['invoice_count'] ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Invoices issued', 'doctor-ak-portal' ); ?></span>
	</div>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
	</div>

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
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $billing_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Paid appointments', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $invoices ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No paid appointments match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $invoices as $row ) : ?>
			<div class="dak-admin-record-row">
				<div class="dak-admin-record-row-main">
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( sprintf( 'INV-%05d', $row['id'] ) . ' &middot; ' . ( '' !== $row['service_name'] ? $row['service_name'] : $row['type_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() already applied to both pieces before concatenation. ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( 'Dr. %1$s &middot; %2$s', $row['doctor_name'], $row['date'] ) ); ?></span>
					</span>

					<span class="dak-admin-record-row-tags">
						<?php if ( 'processed' === $row['refund_status'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Refunded', 'doctor-ak-portal' ); ?></span>
						<?php else : ?>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Paid', 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-amount"><?php echo esc_html( 'PKR ' . number_format( (float) $row['charge'], 0 ) ); ?></span>

					<span class="dak-admin-record-row-actions">
						<a
							class="dak-button dak-button-secondary dak-button-sm"
							href="<?php echo esc_url( \DoctorAKPortal\Frontend\Appointment_Handler::invoice_download_url( $row['id'] ) ); ?>"
						>
							<span class="dak-nav-icon"><?php echo $dak_billing_icons['download']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Download Invoice', 'doctor-ak-portal' ); ?>
						</a>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
