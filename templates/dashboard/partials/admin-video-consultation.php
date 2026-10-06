<?php
/**
 * Template: "Video Consultation" admin table — every doctor's fixed
 * video-consultation price and optional time-limited discount.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $pricing_rows Rows from Video_Pricing::all_flat_for_admin().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Each doctor’s video consultation price and any time-limited discount.', 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<section class="dak-results" aria-labelledby="dak-video-pricing-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-video-pricing-title"><?php esc_html_e( 'Pricing by doctor', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $pricing_rows ) ) ); ?></span></h2>
	</div>

	<?php if ( empty( $pricing_rows ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctor accounts yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-video-pricing-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Discount', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Discount ends', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pricing_rows as $row ) : ?>
						<tr data-row>
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( sprintf( 'Dr. %s', $row['doctor']['name'] ) ); ?></span>
									<span class="dak-cell-sub dak-cell-email"><?php echo \DoctorAKPortal\Includes\Dashboard_Format::email_html( $row['doctor']['email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside email_html(). ?></span>
								</span>
							</td>
							<td class="dak-col-num" data-label="<?php esc_attr_e( 'Price', 'doctor-ak-portal' ); ?>">
								<?php // A price of 0 (or none saved) books video visits at PKR 0 — free, no payment step — so say exactly that. ?>
								<span class="dak-cell-stack">
									<?php if ( $row['discount_active'] ) : ?>
										<span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $row['final_price'], __( 'Free', 'doctor-ak-portal' ) ) ); ?></span>
										<s class="dak-cell-sub"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $row['base_price'] ) ); ?></s>
									<?php elseif ( (float) $row['base_price'] > 0 ) : ?>
										<span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $row['base_price'] ) ); ?></span>
									<?php else : ?>
										<span class="dak-cell-strong"><?php esc_html_e( 'Free', 'doctor-ak-portal' ); ?></span>
										<span class="dak-cell-sub"><?php esc_html_e( 'No price set', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Discount', 'doctor-ak-portal' ); ?>">
								<?php if ( $row['discount_active'] ) : ?>
									<span class="dak-status-pill dak-status-pill-is-active"><?php echo esc_html( sprintf( /* translators: %d: discount percent. */ __( '%d%% off', 'doctor-ak-portal' ), $row['discount_percent'] ) ); ?></span>
								<?php elseif ( $row['discount_percent'] > 0 ) : ?>
									<span class="dak-cell-sub"><?php echo esc_html( sprintf( /* translators: %d: discount percent. */ __( '%d%% — expired', 'doctor-ak-portal' ), $row['discount_percent'] ) ); ?></span>
								<?php else : ?>
									<span class="dak-cell-sub"><?php esc_html_e( 'None', 'doctor-ak-portal' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Discount ends', 'doctor-ak-portal' ); ?>">
								<?php if ( '' !== $row['discount_ends_at'] ) : ?>
									<span class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::datetime( $row['discount_ends_at'], $row['discount_ends_at'] ) ); ?></span>
								<?php elseif ( $row['discount_percent'] > 0 ) : ?>
									<?php esc_html_e( 'No end date', 'doctor-ak-portal' ); ?>
								<?php else : ?>
									<span class="dak-cell-sub">—</span>
								<?php endif; ?>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<button
										type="button"
										class="dak-text-action"
										data-admin-video-pricing-edit
										data-doctor-id="<?php echo esc_attr( $row['doctor']['id'] ); ?>"
										data-price="<?php echo esc_attr( $row['base_price'] ); ?>"
										data-discount-percent="<?php echo esc_attr( $row['discount_percent'] ); ?>"
										data-discount-ends-at="<?php echo esc_attr( $row['discount_ends_at'] ); ?>"
										data-instant-lead-hours="<?php echo esc_attr( $row['booking_rules']['instant_lead_hours'] ); ?>"
										data-instant-surcharge="<?php echo esc_attr( $row['booking_rules']['instant_surcharge'] ); ?>"
										data-cancel-refund-hours="<?php echo esc_attr( $row['booking_rules']['cancel_refund_hours'] ); ?>"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: doctor name. */ __( 'Edit video pricing for Dr. %s', 'doctor-ak-portal' ), $row['doctor']['name'] ) ); ?>"
									><?php esc_html_e( 'Edit pricing', 'doctor-ak-portal' ); ?></button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
</div>