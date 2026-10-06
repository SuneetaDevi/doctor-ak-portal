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
<?php
$dak_surcharge     = (float) $pricing['instant_surcharge'];
$dak_lead_hours    = (float) $pricing['instant_lead_hours'];
$dak_refund_hours  = (float) $pricing['cancel_refund_hours'];
$dak_hours_label   = function ( $hours ) {
	return number_format_i18n( $hours, floor( $hours ) === $hours ? 0 : 1 );
};
$dak_initial_price = (float) $effective['final_price'];
?>
<div class="dak-list-page dak-form-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Your video consultation price, any time-limited discount, and booking/cancellation rules.', 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<div class="dak-form-columns">
	<section class="dak-form-card" aria-labelledby="dak-video-pricing-title">
		<div class="dak-form-card-header">
			<h2 id="dak-video-pricing-title"><?php esc_html_e( 'Pricing', 'doctor-ak-portal' ); ?></h2>
			<p><?php esc_html_e( 'Applies to all video bookings. A price of 0 means video visits are booked free.', 'doctor-ak-portal' ); ?></p>
		</div>
		<div class="dak-form-card-body">
			<div class="dak-alert dak-alert-success dak-hidden" id="dak-video-pricing-success" role="status"></div>
			<div class="dak-alert dak-alert-error dak-hidden" id="dak-video-pricing-general-error" role="alert"></div>

			<h3 class="dak-form-subhead"><?php esc_html_e( 'Price and discount', 'doctor-ak-portal' ); ?></h3>
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
					<label for="dak-video-pricing-discount-ends-date"><?php esc_html_e( 'Discount ends on', 'doctor-ak-portal' ); ?> <span class="dak-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></span></label>
					<input type="date" id="dak-video-pricing-discount-ends-date" value="<?php echo esc_attr( $dak_discount_ends_date ); ?>">
				</div>
				<div class="dak-field">
					<label for="dak-video-pricing-discount-ends-time"><?php esc_html_e( 'Discount ends at', 'doctor-ak-portal' ); ?> <span class="dak-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></span></label>
					<input type="time" id="dak-video-pricing-discount-ends-time" value="<?php echo esc_attr( $dak_discount_ends_time ); ?>">
				</div>
			</div>
			<span class="dak-field-error" data-field="discount_ends_at"></span>
			<p class="dak-field-hint"><?php esc_html_e( 'Set the discount to 0% to charge the full price. Leave the end date blank to apply the discount indefinitely ("Never ends").', 'doctor-ak-portal' ); ?></p>

			<h3 class="dak-form-subhead"><?php esc_html_e( 'Booking rules', 'doctor-ak-portal' ); ?></h3>
			<div class="dak-field-row">
				<div class="dak-field">
					<label for="dak-video-pricing-instant-lead-hours"><?php esc_html_e( 'Instant booking window (hours)', 'doctor-ak-portal' ); ?></label>
					<input type="number" min="0" max="72" step="0.5" id="dak-video-pricing-instant-lead-hours" value="<?php echo esc_attr( $pricing['instant_lead_hours'] ); ?>">
					<span class="dak-field-error" data-field="instant_lead_hours"></span>
				</div>
				<div class="dak-field">
					<label for="dak-video-pricing-instant-surcharge"><?php esc_html_e( 'Instant-booking surcharge (PKR)', 'doctor-ak-portal' ); ?></label>
					<input type="number" min="0" step="0.01" id="dak-video-pricing-instant-surcharge" value="<?php echo esc_attr( $pricing['instant_surcharge'] ); ?>">
					<span class="dak-field-error" data-field="instant_surcharge"></span>
				</div>
			</div>
			<p class="dak-field-hint"><?php esc_html_e( 'A booking made less than this many hours before the appointment counts as "instant" and gets the surcharge. Leave at 0 to turn this off.', 'doctor-ak-portal' ); ?></p>

			<div class="dak-field dak-field-narrow">
				<label for="dak-video-pricing-cancel-refund-hours"><?php esc_html_e( 'Refund window (hours before start)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" max="720" step="0.5" id="dak-video-pricing-cancel-refund-hours" value="<?php echo esc_attr( $pricing['cancel_refund_hours'] ); ?>">
				<span class="dak-field-error" data-field="cancel_refund_hours"></span>
				<p class="dak-field-hint"><?php esc_html_e( '0 means a cancellation is refund-eligible any time before the appointment starts.', 'doctor-ak-portal' ); ?></p>
			</div>
		</div>
		<div class="dak-form-card-footer">
			<button type="button" class="dak-button dak-button-primary" id="dak-video-pricing-save">
				<span class="dak-button-label"><?php esc_html_e( 'Save pricing', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	</section>

	<div class="dak-form-aside">
		<section class="dak-form-card" aria-labelledby="dak-video-preview-title">
			<div class="dak-form-card-header">
				<h2 id="dak-video-preview-title"><?php esc_html_e( 'Patient preview', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'What patients see while booking — updates as you type.', 'doctor-ak-portal' ); ?></p>
			</div>
			<div class="dak-form-card-body">
				<div class="dak-video-pricing-preview" id="dak-video-pricing-preview" aria-live="polite">
					<span class="dak-price-discount-badge dak-hidden" id="dak-video-pricing-preview-badge"></span>
					<div class="dak-video-pricing-amounts">
						<s class="dak-price-original dak-hidden" id="dak-video-pricing-preview-original"></s>
					</div>
					<?php // Rendered server-side too, so the preview is never blank before the script runs; a zero price says what it means. ?>
					<div class="dak-video-pricing-preview-sale" id="dak-video-pricing-preview-sale"><?php echo esc_html( $dak_initial_price > 0 ? \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_initial_price ) : __( 'Free', 'doctor-ak-portal' ) ); ?></div>
					<p class="dak-video-pricing-preview-caption" id="dak-video-pricing-preview-caption"><?php echo $dak_initial_price > 0 ? esc_html__( 'per video consultation', 'doctor-ak-portal' ) : ( (float) $effective['base_price'] > 0 ? esc_html__( 'Free while the current discount lasts', 'doctor-ak-portal' ) : esc_html__( 'No price set — video visits are booked at no charge', 'doctor-ak-portal' ) ); ?></p>
					<p class="dak-video-pricing-preview-countdown dak-hidden" id="dak-video-pricing-preview-countdown"></p>
				</div>
			</div>
		</section>

		<section class="dak-form-card" aria-labelledby="dak-video-rules-title">
			<div class="dak-form-card-header">
				<h2 id="dak-video-rules-title"><?php esc_html_e( 'Rules summary', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'Your saved rules.', 'doctor-ak-portal' ); ?></p>
			</div>
			<div class="dak-form-card-body">
				<ul class="dak-rule-list">
					<li>
						<?php
						if ( $dak_surcharge > 0 && $dak_lead_hours > 0 ) {
							/* translators: 1: surcharge amount, 2: hours. */
							echo esc_html( sprintf( __( 'Instant booking (less than %2$s hours ahead) adds %1$s.', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_surcharge ), $dak_hours_label( $dak_lead_hours ) ) );
						} else {
							esc_html_e( 'No instant-booking surcharge.', 'doctor-ak-portal' );
						}
						?>
					</li>
					<li>
						<?php
						if ( $dak_refund_hours > 0 ) {
							/* translators: %s: hours. */
							echo esc_html( sprintf( __( 'Full refund if cancelled %s or more hours before the start.', 'doctor-ak-portal' ), $dak_hours_label( $dak_refund_hours ) ) );
						} else {
							esc_html_e( 'Full refund if cancelled any time before the start.', 'doctor-ak-portal' );
						}
						?>
					</li>
					<li><?php esc_html_e( 'A discount applies automatically at checkout.', 'doctor-ak-portal' ); ?></li>
				</ul>
			</div>
		</section>
	</div>
</div>
</div>