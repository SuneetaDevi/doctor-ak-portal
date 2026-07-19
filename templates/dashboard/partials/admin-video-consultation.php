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

$dak_video_pricing_icons = array(
	'edit' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( "Each doctor's fixed video-consultation price, with an optional time-limited discount.", 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<section class="dak-dashboard-card dak-admin-users-card">
<?php if ( empty( $pricing_rows ) ) : ?>
	<p class="dak-empty-state"><?php esc_html_e( 'No doctor accounts yet.', 'doctor-ak-portal' ); ?></p>
<?php else : ?>
	<div class="dak-table-scroll">
		<table class="dak-admin-users-table dak-admin-sessions-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Base Price', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Discount', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Discount Ends', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Current Price', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
					<th class="dak-admin-users-actions-col"><?php esc_html_e( 'Action', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pricing_rows as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
							<strong><?php echo esc_html( sprintf( 'Dr. %s', $row['doctor']['name'] ) ); ?></strong><br>
							<span class="dak-clinic-card-meta"><?php echo esc_html( $row['doctor']['email'] ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'Base Price', 'doctor-ak-portal' ); ?>">
							<?php echo $row['base_price'] > 0 ? esc_html( 'PKR' . number_format( $row['base_price'], 0 ) . '/-' ) : esc_html__( 'Not set', 'doctor-ak-portal' ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Discount', 'doctor-ak-portal' ); ?>">
							<?php echo $row['discount_percent'] > 0 ? esc_html( $row['discount_percent'] . '%' ) : '—'; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Discount Ends', 'doctor-ak-portal' ); ?>">
							<?php echo '' !== $row['discount_ends_at'] ? esc_html( $row['discount_ends_at'] ) : '—'; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Current Price', 'doctor-ak-portal' ); ?>">
							<?php echo $row['final_price'] > 0 ? esc_html( 'PKR' . number_format( $row['final_price'], 0 ) . '/-' ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
							<?php if ( $row['discount_active'] ) : ?>
								<span class="dak-status-badge is-active"><?php esc_html_e( 'Discount Active', 'doctor-ak-portal' ); ?></span>
							<?php elseif ( $row['discount_percent'] > 0 ) : ?>
								<span class="dak-status-badge is-disabled"><?php esc_html_e( 'Discount Expired', 'doctor-ak-portal' ); ?></span>
							<?php else : ?>
								<span class="dak-status-badge is-pending"><?php esc_html_e( 'No Discount', 'doctor-ak-portal' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="dak-admin-users-actions-col">
							<div class="dak-admin-users-actions">
								<button
									type="button"
									class="dak-icon-button"
									data-admin-video-pricing-edit
									data-doctor-id="<?php echo esc_attr( $row['doctor']['id'] ); ?>"
									data-price="<?php echo esc_attr( $row['base_price'] ); ?>"
									data-discount-percent="<?php echo esc_attr( $row['discount_percent'] ); ?>"
									data-discount-ends-at="<?php echo esc_attr( $row['discount_ends_at'] ); ?>"
									title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_video_pricing_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
</section>
