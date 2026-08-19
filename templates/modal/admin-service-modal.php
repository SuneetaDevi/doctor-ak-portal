<?php
/**
 * Template: Admin "Add/Edit Service" modal for the Services table — lets an
 * admin create or edit any doctor's service (name/type/category/charge/
 * duration/active), via Service_Handler's admin AJAX endpoints.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_options Doctor user ID => display name.
 * @var array $categories     Category slug => label, see Specializations::get_all().
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
				<span><?php esc_html_e( 'Active (visible to patients when booking)', 'doctor-ak-portal' ); ?></span>
			</label>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-service-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Service', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
