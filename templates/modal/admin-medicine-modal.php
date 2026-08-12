<?php
/**
 * Template: Admin "Add/Edit Medicine" modal for the Medicines table — lets
 * an admin create or edit any doctor's medicine (name/default dosage/
 * active), via Medicine_Handler's admin AJAX endpoints.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_options Doctor user ID => { name, is_disabled }.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-medicine-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-medicine-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-medicine-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-medicine-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-medicine-modal-title"><?php esc_html_e( 'Add Medicine', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-medicine-general-error" role="alert"></div>

		<input type="hidden" id="dak-admin-medicine-id" value="0">

		<div class="dak-field">
			<label for="dak-admin-medicine-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-medicine-doctor">
				<option value=""><?php esc_html_e( 'Select a doctor…', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctor_options as $doctor_id => $doctor_option ) : ?>
					<option value="<?php echo esc_attr( $doctor_id ); ?>" <?php disabled( $doctor_option['is_disabled'] ); ?>><?php echo esc_html( $doctor_option['is_disabled'] ? sprintf( __( '%s (deactivated)', 'doctor-ak-portal' ), $doctor_option['name'] ) : $doctor_option['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dak-field-error" data-field="doctor_id"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-medicine-name"><?php esc_html_e( 'Medicine Name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-medicine-name" placeholder="<?php esc_attr_e( 'e.g. Amoxicillin', 'doctor-ak-portal' ); ?>">
			<span class="dak-field-error" data-field="name"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-medicine-default-dosage"><?php esc_html_e( 'Default Dosage (optional)', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-medicine-default-dosage" placeholder="<?php esc_attr_e( 'e.g. 500mg', 'doctor-ak-portal' ); ?>">
		</div>

		<div class="dak-field">
			<label class="dak-checkbox">
				<input type="checkbox" id="dak-admin-medicine-active" checked>
				<span><?php esc_html_e( 'Active (selectable when prescribing)', 'doctor-ak-portal' ); ?></span>
			</label>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-medicine-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Medicine', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
