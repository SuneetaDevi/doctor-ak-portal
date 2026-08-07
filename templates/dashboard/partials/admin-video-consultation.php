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

if ( ! function_exists( 'dak_video_pricing_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for an avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_video_pricing_initials( $name ) {
		$words    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			if ( '' !== $word ) {
				$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
			}
		}

		return '' !== $initials ? $initials : '?';
	}
endif;
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Pricing across all doctors', 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Pricing overview', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $pricing_rows ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctor accounts yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $pricing_rows as $row ) : ?>
			<div class="dak-admin-record-row">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo esc_html( dak_video_pricing_initials( $row['doctor']['name'] ) ); ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( sprintf( 'Dr. %s', $row['doctor']['name'] ) ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( $row['doctor']['email'] ); ?></span>
					</span>

					<span class="dak-video-pricing-amounts">
						<?php if ( $row['discount_active'] ) : ?>
							<s class="dak-price-original"><?php echo esc_html( 'PKR ' . number_format( $row['base_price'], 0 ) ); ?></s>
							<strong class="dak-price-sale"><?php echo esc_html( 'PKR ' . number_format( $row['final_price'], 0 ) ); ?></strong>
						<?php else : ?>
							<strong class="dak-price-sale"><?php echo $row['base_price'] > 0 ? esc_html( 'PKR ' . number_format( $row['base_price'], 0 ) ) : esc_html__( 'Not set', 'doctor-ak-portal' ); ?></strong>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-tags">
						<?php if ( $row['discount_active'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-disabled"><?php echo esc_html( sprintf( '%d%% off', $row['discount_percent'] ) ); ?></span>
						<?php elseif ( $row['discount_percent'] > 0 ) : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Discount expired', 'doctor-ak-portal' ); ?></span>
						<?php else : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'No discount', 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-meta">
						<?php
						if ( '' !== $row['discount_ends_at'] ) {
							echo esc_html( sprintf( /* translators: %s: discount end date. */ __( 'Ends: %s', 'doctor-ak-portal' ), $row['discount_ends_at'] ) );
						} elseif ( $row['discount_percent'] > 0 ) {
							esc_html_e( 'Never ends', 'doctor-ak-portal' );
						} else {
							echo esc_html( sprintf( /* translators: %s: em dash, no discount configured. */ __( 'Ends: %s', 'doctor-ak-portal' ), '—' ) );
						}
						?>
					</span>

					<span class="dak-admin-record-row-actions">
						<button
							type="button"
							class="dak-icon-button"
							data-admin-video-pricing-edit
							data-doctor-id="<?php echo esc_attr( $row['doctor']['id'] ); ?>"
							data-price="<?php echo esc_attr( $row['base_price'] ); ?>"
							data-discount-percent="<?php echo esc_attr( $row['discount_percent'] ); ?>"
							data-discount-ends-at="<?php echo esc_attr( $row['discount_ends_at'] ); ?>"
							data-instant-lead-hours="<?php echo esc_attr( $row['booking_rules']['instant_lead_hours'] ); ?>"
							data-instant-surcharge="<?php echo esc_attr( $row['booking_rules']['instant_surcharge'] ); ?>"
							data-cancel-refund-hours="<?php echo esc_attr( $row['booking_rules']['cancel_refund_hours'] ); ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_video_pricing_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
