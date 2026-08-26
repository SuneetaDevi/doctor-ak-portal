<?php
/**
 * Template: Admin "Add/Edit Service" modal for the Services table — lets an
 * admin create or edit any doctor's service (name/type/category/charge/
 * duration/active), via Service_Handler's admin AJAX endpoints. The
 * Description/Image/Clinics fields here are what feed the public
 * [services_directory]/[service_profile_view] pages (see Services class) —
 * a doctor's own Services tab has no such fields.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_options   Doctor user ID => display name.
 * @var array $categories       Category slug => label, see Specializations::get_all().
 * @var array $clinic_locations Rows from Clinic_Locations::get_all().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-service-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-service-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-service-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-service-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-service-modal-title"><?php esc_html_e( 'Add Service', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-service-general-error" role="alert"></div>

		<input type="hidden" id="dak-admin-service-id" value="0">
		<input type="hidden" id="dak-admin-service-image-id" value="0">

		<div class="dak-field">
			<label for="dak-admin-service-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-service-doctor" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>">
				<option value=""><?php esc_html_e( 'Select a doctor…', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctor_options as $doctor_id => $doctor_option ) : ?>
					<option value="<?php echo esc_attr( $doctor_id ); ?>" <?php disabled( $doctor_option['is_disabled'] ); ?>><?php echo esc_html( $doctor_option['is_disabled'] ? sprintf( __( '%s (deactivated)', 'doctor-ak-portal' ), $doctor_option['name'] ) : $doctor_option['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dak-field-error" data-field="doctor_id"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-name"><?php esc_html_e( 'Service Name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-service-name" placeholder="<?php esc_attr_e( 'e.g. OPD Consultation', 'doctor-ak-portal' ); ?>">
			<span class="dak-field-error" data-field="name"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-category"><?php esc_html_e( 'Category', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-service-category">
				<option value=""><?php esc_html_e( 'None', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $categories as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-service-charge"><?php esc_html_e( 'Charge (PKR)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" step="0.01" id="dak-admin-service-charge" value="0">
				<span class="dak-field-error" data-field="charge"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-service-duration"><?php esc_html_e( 'Duration (minutes)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" max="480" step="1" id="dak-admin-service-duration" value="0">
				<span class="dak-field-error" data-field="duration_minutes"></span>
			</div>
		</div>

		<div class="dak-field">
			<label class="dak-checkbox">
				<input type="checkbox" id="dak-admin-service-active" checked>
				<span><?php esc_html_e( 'Active (visible to patients when booking, and on the public Services page)', 'doctor-ak-portal' ); ?></span>
			</label>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-description"><?php esc_html_e( 'Description', 'doctor-ak-portal' ); ?></label>
			<textarea id="dak-admin-service-description" rows="4" placeholder="<?php esc_attr_e( "What this service is, who it's for, what to expect… (shown on the public Services page)", 'doctor-ak-portal' ); ?>"></textarea>
			<span class="dak-field-error" data-field="description"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-image"><?php esc_html_e( 'Image', 'doctor-ak-portal' ); ?></label>
			<div class="dak-service-portfolio-image-picker">
				<span class="dak-avatar dak-avatar-lg" id="dak-admin-service-image-preview-wrap">
					<img id="dak-admin-service-image-preview" src="" alt="" class="dak-hidden">
				</span>
				<input type="file" id="dak-admin-service-image" accept="image/jpeg,image/png,image/webp">
			</div>
			<p class="dak-field-hint"><?php esc_html_e( 'Shown on the public Services page — optional.', 'doctor-ak-portal' ); ?></p>
			<span class="dak-field-error" data-field="image"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-clinics"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-service-clinics" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Select clinics…', 'doctor-ak-portal' ); ?>" multiple>
				<?php foreach ( $clinic_locations as $clinic_location ) : ?>
					<option value="<?php echo esc_attr( $clinic_location['id'] ); ?>"><?php echo esc_html( sprintf( '%1$s — %2$s, %3$s', $clinic_location['name'], $clinic_location['area_label'], $clinic_location['city_label'] ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="dak-field-hint"><?php esc_html_e( 'Clinics where this service is offered — pick as many as apply. Shown on the public Services page.', 'doctor-ak-portal' ); ?></p>
			<?php if ( empty( $clinic_locations ) ) : ?>
				<p class="dak-field-hint"><?php esc_html_e( 'No clinics added yet — add one first from the admin "Clinic" section.', 'doctor-ak-portal' ); ?></p>
			<?php endif; ?>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-service-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Service', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
