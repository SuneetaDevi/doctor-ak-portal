<?php
/**
 * Template: Admin "Edit Video Pricing" modal for the Video Consultation
 * table — lets an admin set/override any doctor's fixed video-consultation
 * price and discount, via Video_Pricing_Handler's admin AJAX endpoint.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_options Doctor user ID => display name.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-video-pricing-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-video-pricing-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-video-pricing-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-video-pricing-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-video-pricing-modal-title"><?php esc_html_e( 'Edit Video Pricing', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-video-pricing-general-error" role="alert"></div>

		<div class="dak-field">
			<label for="dak-admin-video-pricing-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-video-pricing-doctor">
				<option value=""><?php esc_html_e( 'Select a doctor…', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctor_options as $doctor_id => $doctor_name ) : ?>
					<option value="<?php echo esc_attr( $doctor_id ); ?>"><?php echo esc_html( sprintf( 'Dr. %s', $doctor_name ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dak-field-error" data-field="doctor_id"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-video-pricing-price"><?php esc_html_e( 'Video Consultation Price (PKR)', 'doctor-ak-portal' ); ?></label>
			<input type="number" min="0" step="0.01" id="dak-admin-video-pricing-price" value="0">
			<span class="dak-field-error" data-field="price"></span>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-video-pricing-discount-percent"><?php esc_html_e( 'Discount (%)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" max="100" step="1" id="dak-admin-video-pricing-discount-percent" value="0">
				<span class="dak-field-error" data-field="discount_percent"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-video-pricing-discount-ends-at"><?php esc_html_e( 'Discount Ends At', 'doctor-ak-portal' ); ?></label>
				<input type="datetime-local" id="dak-admin-video-pricing-discount-ends-at">
				<span class="dak-field-error" data-field="discount_ends_at"></span>
			</div>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-video-pricing-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Video Pricing', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
