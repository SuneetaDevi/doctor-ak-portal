<?php
/**
 * Template: Admin "Add/Edit Clinic" modal for the "Clinic" master list —
 * Country/City/Area (cascading, from Locations::get_all()) plus Name/
 * Address/Phone/Email, via Clinic_Location_Handler's admin AJAX endpoints.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-clinic-location-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-clinic-location-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-clinic-location-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-clinic-location-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-clinic-location-modal-title"><?php esc_html_e( 'Add Clinic', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-clinic-location-general-error" role="alert"></div>

		<input type="hidden" id="dak-admin-clinic-location-id" value="0">

		<div class="dak-field">
			<label for="dak-admin-clinic-location-name"><?php esc_html_e( 'Clinic Name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-clinic-location-name" placeholder="<?php esc_attr_e( 'e.g. Chughtai Lab', 'doctor-ak-portal' ); ?>">
			<span class="dak-field-error" data-field="name"></span>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-clinic-location-country"><?php esc_html_e( 'Country', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-clinic-location-country"></select>
				<span class="dak-field-error" data-field="country"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-clinic-location-city"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-clinic-location-city"></select>
				<span class="dak-field-error" data-field="city"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-clinic-location-area"><?php esc_html_e( 'Area', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-clinic-location-area"></select>
				<span class="dak-field-error" data-field="area"></span>
			</div>
		</div>

		<div class="dak-field">
			<label for="dak-admin-clinic-location-address"><?php esc_html_e( 'Address (optional)', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-clinic-location-address">
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-clinic-location-phone"><?php esc_html_e( 'Phone (optional)', 'doctor-ak-portal' ); ?></label>
				<input type="tel" id="dak-admin-clinic-location-phone">
				<span class="dak-field-error" data-field="phone"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-clinic-location-email"><?php esc_html_e( 'Contact Email (optional)', 'doctor-ak-portal' ); ?></label>
				<input type="email" id="dak-admin-clinic-location-email">
				<span class="dak-field-error" data-field="contact_email"></span>
			</div>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-clinic-location-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Clinic', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
