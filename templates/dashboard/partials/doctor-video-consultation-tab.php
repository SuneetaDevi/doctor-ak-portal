<?php
/**
 * Template: Doctor dashboard "Video Consultation" tab — the doctor's own
 * fixed video-consultation price, with an optional time-limited percentage
 * discount. Patients see this price directly when booking a video
 * appointment (no service list, see Booking_Page).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $pricing   Raw settings, see Video_Pricing::get_for_doctor().
 * @var array $effective Computed current price, see Video_Pricing::effective_price_for_doctor().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_discount_ends_date = '';
$dak_discount_ends_time = '';

if ( '' !== $pricing['discount_ends_at'] ) {
	$dak_discount_ends_parts = explode( ' ', $pricing['discount_ends_at'], 2 );
	$dak_discount_ends_date  = isset( $dak_discount_ends_parts[0] ) ? $dak_discount_ends_parts[0] : '';
	$dak_discount_ends_time  = isset( $dak_discount_ends_parts[1] ) ? $dak_discount_ends_parts[1] : '';
}
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Pricing, discounts and cancellation rules', 'doctor-ak-portal' ); ?></p>
</div>

<div class="dak-dashboard-grid">
	<section class="dak-dashboard-card">
		<div class="dak-dashboard-card-header">
			<div>
				<h2><?php esc_html_e( 'Pricing', 'doctor-ak-portal' ); ?></h2>
				<p class="dak-notifications-card-subtitle"><?php esc_html_e( 'Applies to all video bookings', 'doctor-ak-portal' ); ?></p>
			</div>
		</div>

		<div class="dak-alert dak-alert-success dak-hidden" id="dak-video-pricing-success" role="status"></div>
		<div class="dak-alert dak-alert-error dak-hidden" id="dak-video-pricing-general-error" role="alert"></div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-video-pricing-price"><?php esc_html_e( 'Base price (PKR)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" step="0.01" id="dak-video-pricing-price" value="<?php echo esc_attr( $pricing['price'] ); ?>">
				<span class="dak-field-error" data-field="price"></span>
			</div>
			<div class="dak-field">
				<label for="dak-video-pricing-discount-percent"><?php esc_html_e( 'Discount (%)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" max="100" step="1" id="dak-video-pricing-discount-percent" value="<?php echo esc_attr( $pricing['discount_percent'] ); ?>">
				<span class="dak-field-error" data-field="discount_percent"></span>
			</div>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-video-pricing-discount-ends-date"><?php esc_html_e( 'Discount ends on', 'doctor-ak-portal' ); ?></label>
				<input type="date" id="dak-video-pricing-discount-ends-date" value="<?php echo esc_attr( $dak_discount_ends_date ); ?>">
			</div>
			<div class="dak-field">
				<label for="dak-video-pricing-discount-ends-time"><?php esc_html_e( 'Discount ends at', 'doctor-ak-portal' ); ?></label>
				<input type="time" id="dak-video-pricing-discount-ends-time" value="<?php echo esc_attr( $dak_discount_ends_time ); ?>">
			</div>
			<span class="dak-field-error" data-field="discount_ends_at"></span>
		</div>
		<p class="dak-field-hint"><?php esc_html_e( 'Leave the discount at 0% (or clear the end date) to charge the full price.', 'doctor-ak-portal' ); ?></p>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-video-pricing-instant-surcharge"><?php esc_html_e( 'Instant-booking surcharge (PKR)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" step="0.01" id="dak-video-pricing-instant-surcharge" value="<?php echo esc_attr( $pricing['instant_surcharge'] ); ?>">
				<span class="dak-field-error" data-field="instant_surcharge"></span>
			</div>
			<div class="dak-field">
				<label for="dak-video-pricing-cancel-refund-hours"><?php esc_html_e( 'Refund window (hours before)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" max="720" step="0.5" id="dak-video-pricing-cancel-refund-hours" value="<?php echo esc_attr( $pricing['cancel_refund_hours'] ); ?>">
				<span class="dak-field-error" data-field="cancel_refund_hours"></span>
			</div>
		</div>

		<div class="dak-field">
			<label for="dak-video-pricing-instant-lead-hours"><?php esc_html_e( 'Instant Booking Window (hours)', 'doctor-ak-portal' ); ?></label>
			<input type="number" min="0" max="72" step="0.5" id="dak-video-pricing-instant-lead-hours" value="<?php echo esc_attr( $pricing['instant_lead_hours'] ); ?>">
			<span class="dak-field-error" data-field="instant_lead_hours"></span>
			<p class="dak-field-hint"><?php esc_html_e( 'A booking made less than this many hours before the appointment counts as "instant" and gets the surcharge above. Leave at 0 to turn this off.', 'doctor-ak-portal' ); ?></p>
		</div>

		<button type="button" class="dak-button dak-button-primary" id="dak-video-pricing-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save pricing', 'doctor-ak-portal' ); ?></span>
		</button>
	</section>

	<div class="dak-video-pricing-sidebar">
		<section class="dak-dashboard-card">
			<div class="dak-dashboard-card-header">
				<div>
					<h2><?php esc_html_e( 'Patient preview', 'doctor-ak-portal' ); ?></h2>
					<p class="dak-notifications-card-subtitle"><?php esc_html_e( 'What patients see while booking', 'doctor-ak-portal' ); ?></p>
				</div>
			</div>

			<div class="dak-video-pricing-preview" id="dak-video-pricing-preview">
				<span class="dak-price-discount-badge dak-hidden" id="dak-video-pricing-preview-badge"></span>
				<div class="dak-video-pricing-amounts">
					<s class="dak-price-original dak-hidden" id="dak-video-pricing-preview-original"></s>
				</div>
				<div class="dak-video-pricing-preview-sale" id="dak-video-pricing-preview-sale"></div>
				<p class="dak-video-pricing-preview-caption"><?php esc_html_e( 'per video consultation', 'doctor-ak-portal' ); ?></p>
				<p class="dak-video-pricing-preview-countdown dak-hidden" id="dak-video-pricing-preview-countdown"></p>
			</div>
		</section>

		<section class="dak-dashboard-card">
			<div class="dak-dashboard-card-header">
				<h2><?php esc_html_e( 'Rules summary', 'doctor-ak-portal' ); ?></h2>
			</div>

			<div class="dak-video-pricing-rule">
				<span class="dak-video-pricing-rule-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2 3 12h6l-1 6 8-10h-6l1-6z"/></svg></span>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: instant surcharge amount. */
							__( 'Instant booking adds PKR%s to the total.', 'doctor-ak-portal' ),
							number_format( (float) $pricing['instant_surcharge'], 0 )
						)
					);
					?>
				</p>
			</div>

			<div class="dak-video-pricing-rule">
				<span class="dak-video-pricing-rule-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7"/><path d="M10 6v4l3 2"/></svg></span>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: refund window in hours. */
							__( 'Full refund if cancelled %s+ hours before start.', 'doctor-ak-portal' ),
							number_format( (float) $pricing['cancel_refund_hours'], (float) $pricing['cancel_refund_hours'] === floor( (float) $pricing['cancel_refund_hours'] ) ? 0 : 1 )
						)
					);
					?>
				</p>
			</div>

			<div class="dak-video-pricing-rule">
				<span class="dak-video-pricing-rule-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10a6 6 0 1 1 1.8 4.3"/><path d="M4 14v-3.5H7.5"/></svg></span>
				<p><?php esc_html_e( 'Discount applies automatically at checkout.', 'doctor-ak-portal' ); ?></p>
			</div>
		</section>
	</div>
</div>
