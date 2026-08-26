<?php
/**
 * Template: Admin "Add/Edit Service" modal for the Service Portfolio table
 * — lets an admin create or edit a public service listing (name,
 * description, image, price, clinics, doctors), via
 * Service_Catalog_Handler's AJAX endpoints.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_options   Doctor user ID => { name, is_disabled }, see Admin_Dashboard::doctor_options().
 * @var array $clinic_locations Rows from Clinic_Locations::get_all().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-service-portfolio-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-service-portfolio-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-service-portfolio-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-service-portfolio-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-service-portfolio-modal-title"><?php esc_html_e( 'Add Service', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-service-portfolio-general-error" role="alert"></div>

		<input type="hidden" id="dak-admin-service-portfolio-id" value="0">
		<input type="hidden" id="dak-admin-service-portfolio-image-id" value="0">

		<div class="dak-field">
			<label for="dak-admin-service-portfolio-name"><?php esc_html_e( 'Service Name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-service-portfolio-name" placeholder="<?php esc_attr_e( 'e.g. Intragastric Balloon', 'doctor-ak-portal' ); ?>">
			<span class="dak-field-error" data-field="name"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-portfolio-description"><?php esc_html_e( 'Description', 'doctor-ak-portal' ); ?></label>
			<textarea id="dak-admin-service-portfolio-description" rows="4" placeholder="<?php esc_attr_e( 'What this service is, who it\'s for, what to expect…', 'doctor-ak-portal' ); ?>"></textarea>
			<span class="dak-field-error" data-field="description"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-portfolio-price"><?php esc_html_e( 'Price (PKR)', 'doctor-ak-portal' ); ?></label>
			<input type="number" min="0" step="0.01" id="dak-admin-service-portfolio-price" value="0">
			<span class="dak-field-error" data-field="price"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-portfolio-image"><?php esc_html_e( 'Image', 'doctor-ak-portal' ); ?></label>
			<div class="dak-service-portfolio-image-picker">
				<span class="dak-avatar dak-avatar-lg" id="dak-admin-service-portfolio-image-preview-wrap">
					<img id="dak-admin-service-portfolio-image-preview" src="" alt="" class="dak-hidden">
				</span>
				<input type="file" id="dak-admin-service-portfolio-image" accept="image/jpeg,image/png,image/webp">
			</div>
			<span class="dak-field-error" data-field="image"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-portfolio-clinics"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-service-portfolio-clinics" name="clinic_location_ids[]" multiple>
				<?php foreach ( $clinic_locations as $clinic_location ) : ?>
					<option value="<?php echo esc_attr( $clinic_location['id'] ); ?>"><?php echo esc_html( sprintf( '%1$s — %2$s, %3$s', $clinic_location['name'], $clinic_location['area_label'], $clinic_location['city_label'] ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="dak-field-hint"><?php esc_html_e( 'Clinics where this service is offered — hold Ctrl/Cmd to select more than one.', 'doctor-ak-portal' ); ?></p>
			<?php if ( empty( $clinic_locations ) ) : ?>
				<p class="dak-field-hint"><?php esc_html_e( 'No clinics added yet — add one first from the admin "Clinic" section.', 'doctor-ak-portal' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-portfolio-doctors"><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-service-portfolio-doctors" name="doctor_ids[]" multiple>
				<?php foreach ( $doctor_options as $doctor_id => $doctor_option ) : ?>
					<option value="<?php echo esc_attr( $doctor_id ); ?>" <?php disabled( $doctor_option['is_disabled'] ); ?>><?php echo esc_html( $doctor_option['is_disabled'] ? sprintf( __( '%s (deactivated)', 'doctor-ak-portal' ), $doctor_option['name'] ) : $doctor_option['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="dak-field-hint"><?php esc_html_e( 'Doctors who provide this service — hold Ctrl/Cmd to select more than one.', 'doctor-ak-portal' ); ?></p>
		</div>

		<div class="dak-field">
			<label class="dak-checkbox">
				<input type="checkbox" id="dak-admin-service-portfolio-active" checked>
				<span><?php esc_html_e( 'Active (visible on the public services page)', 'doctor-ak-portal' ); ?></span>
			</label>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-service-portfolio-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Service', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
