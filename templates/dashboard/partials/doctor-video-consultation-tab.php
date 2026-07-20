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
?>
<div class="dak-alert dak-alert-success dak-hidden" id="dak-video-pricing-success" role="status"></div>
<div class="dak-alert dak-alert-error dak-hidden" id="dak-video-pricing-general-error" role="alert"></div>

<?php if ( $effective['base_price'] > 0 ) : ?>
	<p class="dak-field-hint">
		<?php if ( $effective['discount_active'] ) : ?>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: discounted price, 2: original price, 3: discount end date/time. */
					__( 'Currently PKR%1$s (%2$s%% off PKR%3$s), until %4$s.', 'doctor-ak-portal' ),
					number_format( $effective['final_price'], 0 ),
					$effective['discount_percent'],
					number_format( $effective['base_price'], 0 ),
					$effective['discount_ends_at']
				)
			);
			?>
		<?php else : ?>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: price. */
					__( 'Currently PKR%s.', 'doctor-ak-portal' ),
					number_format( $effective['base_price'], 0 )
				)
			);
			?>
		<?php endif; ?>
	</p>
<?php else : ?>
	<p class="dak-field-hint"><?php esc_html_e( "You haven't set a video consultation price yet — patients will be able to book a free video consultation until you do.", 'doctor-ak-portal' ); ?></p>
<?php endif; ?>

<div class="dak-field">
	<label for="dak-video-pricing-price"><?php esc_html_e( 'Video Consultation Price (PKR)', 'doctor-ak-portal' ); ?></label>
	<input type="number" min="0" step="0.01" id="dak-video-pricing-price" value="<?php echo esc_attr( $pricing['price'] ); ?>">
	<span class="dak-field-error" data-field="price"></span>
</div>

<div class="dak-field-row">
	<div class="dak-field">
		<label for="dak-video-pricing-discount-percent"><?php esc_html_e( 'Discount (%)', 'doctor-ak-portal' ); ?></label>
		<input type="number" min="0" max="100" step="1" id="dak-video-pricing-discount-percent" value="<?php echo esc_attr( $pricing['discount_percent'] ); ?>">
		<span class="dak-field-error" data-field="discount_percent"></span>
	</div>
	<div class="dak-field">
		<label for="dak-video-pricing-discount-ends-at"><?php esc_html_e( 'Discount Ends At', 'doctor-ak-portal' ); ?></label>
		<input type="datetime-local" id="dak-video-pricing-discount-ends-at" value="<?php echo esc_attr( str_replace( ' ', 'T', $pricing['discount_ends_at'] ) ); ?>">
		<span class="dak-field-error" data-field="discount_ends_at"></span>
	</div>
</div>
<p class="dak-field-hint"><?php esc_html_e( 'Leave the discount at 0% (or clear the end date) to charge the full price.', 'doctor-ak-portal' ); ?></p>

<hr class="dak-field-divider">

<h3 class="dak-dashboard-subheading"><?php esc_html_e( 'Booking Rules', 'doctor-ak-portal' ); ?></h3>
<p class="dak-field-hint"><?php esc_html_e( 'These apply to all your appointments — clinic visits and video consultations.', 'doctor-ak-portal' ); ?></p>

<div class="dak-field-row">
	<div class="dak-field">
		<label for="dak-video-pricing-instant-lead-hours"><?php esc_html_e( 'Instant Booking Window (hours)', 'doctor-ak-portal' ); ?></label>
		<input type="number" min="0" max="72" step="0.5" id="dak-video-pricing-instant-lead-hours" value="<?php echo esc_attr( $pricing['instant_lead_hours'] ); ?>">
		<span class="dak-field-error" data-field="instant_lead_hours"></span>
		<p class="dak-field-hint"><?php esc_html_e( 'A booking made less than this many hours before the appointment counts as "instant" and gets the surcharge below. Leave at 0 to turn this off.', 'doctor-ak-portal' ); ?></p>
	</div>
	<div class="dak-field">
		<label for="dak-video-pricing-instant-surcharge"><?php esc_html_e( 'Instant Booking Surcharge (PKR)', 'doctor-ak-portal' ); ?></label>
		<input type="number" min="0" step="0.01" id="dak-video-pricing-instant-surcharge" value="<?php echo esc_attr( $pricing['instant_surcharge'] ); ?>">
		<span class="dak-field-error" data-field="instant_surcharge"></span>
	</div>
</div>

<div class="dak-field">
	<label for="dak-video-pricing-cancel-refund-hours"><?php esc_html_e( 'Cancellation Refund Window (hours before appointment)', 'doctor-ak-portal' ); ?></label>
	<input type="number" min="0" max="720" step="0.5" id="dak-video-pricing-cancel-refund-hours" value="<?php echo esc_attr( $pricing['cancel_refund_hours'] ); ?>">
	<span class="dak-field-error" data-field="cancel_refund_hours"></span>
	<p class="dak-field-hint"><?php esc_html_e( 'A patient who cancels at least this many hours before their appointment is told they qualify for a refund; cancelling later is not. Leave at 0 to always allow a refund up until the appointment starts.', 'doctor-ak-portal' ); ?></p>
</div>

<button type="button" class="dak-button dak-button-primary" id="dak-video-pricing-save">
	<span class="dak-button-label"><?php esc_html_e( 'Save Video Pricing', 'doctor-ak-portal' ); ?></span>
</button>
