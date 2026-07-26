<?php
/**
 * Template: Admin "Edit Session" modal for the Doctor Sessions table —
 * lets an admin edit any doctor's clinic (name/address/contact) and its
 * weekly session schedule, reusing the same save/delete AJAX endpoints as
 * the doctor-facing Clinics tab (see Clinic_Handler).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $session_days   Day slug => label, see Clinics::session_days().
 * @var array $doctor_options Doctor user ID => display name, for the "Add Session" doctor picker.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-session-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-session-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-session-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-session-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-session-modal-title"><?php esc_html_e( 'Edit Clinic Session', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-session-general-error" role="alert"></div>

		<input type="hidden" id="dak-admin-session-clinic-id" value="0">

		<div class="dak-field">
			<label for="dak-admin-session-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-session-doctor">
				<option value=""><?php esc_html_e( 'Select a doctor…', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctor_options as $doctor_id => $doctor_name ) : ?>
					<option value="<?php echo esc_attr( $doctor_id ); ?>"><?php echo esc_html( sprintf( 'Dr. %s', $doctor_name ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dak-field-error" data-field="doctor_id"></span>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-session-type"><?php esc_html_e( 'Clinic Type', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-session-type">
					<option value="physical"><?php esc_html_e( 'Physical Clinic', 'doctor-ak-portal' ); ?></option>
					<option value="video"><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></option>
				</select>
			</div>
			<div class="dak-field">
				<label for="dak-admin-session-name"><?php esc_html_e( 'Clinic Name', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-admin-session-name">
				<span class="dak-field-error" data-field="name"></span>
			</div>
		</div>

		<div class="dak-field" id="dak-admin-session-address-field">
			<label for="dak-admin-session-address"><?php esc_html_e( 'Address', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-session-address">
			<span class="dak-field-error" data-field="address"></span>
		</div>

		<div class="dak-field-row" id="dak-admin-session-city-area-row">
			<div class="dak-field">
				<label for="dak-admin-session-city"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-session-city"></select>
				<span class="dak-field-error" data-field="city"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-session-area"><?php esc_html_e( 'Area', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-session-area"></select>
				<span class="dak-field-error" data-field="area"></span>
			</div>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-session-phone"><?php esc_html_e( 'Phone (optional)', 'doctor-ak-portal' ); ?></label>
				<input type="tel" id="dak-admin-session-phone">
			</div>
			<div class="dak-field">
				<label for="dak-admin-session-email"><?php esc_html_e( 'Contact Email (optional)', 'doctor-ak-portal' ); ?></label>
				<input type="email" id="dak-admin-session-email">
			</div>
		</div>

		<div class="dak-field">
			<span class="dak-field-label"><?php esc_html_e( 'Weekly Sessions', 'doctor-ak-portal' ); ?></span>
			<div class="dak-availability-grid dak-clinic-sessions-grid" id="dak-admin-session-grid">
				<div class="dak-availability-row dak-availability-row-header">
					<span></span>
					<span class="dak-availability-col-label"><?php esc_html_e( 'Start', 'doctor-ak-portal' ); ?></span>
					<span></span>
					<span class="dak-availability-col-label"><?php esc_html_e( 'End', 'doctor-ak-portal' ); ?></span>
					<span class="dak-availability-col-label"><?php esc_html_e( 'Slot (min)', 'doctor-ak-portal' ); ?></span>
				</div>
				<?php foreach ( $session_days as $slug => $label ) : ?>
					<div class="dak-availability-row" data-day="<?php echo esc_attr( $slug ); ?>">
						<label class="dak-checkbox">
							<input type="checkbox" class="dak-availability-toggle">
							<span><?php echo esc_html( $label ); ?></span>
						</label>
						<input type="time" class="dak-availability-start" aria-label="<?php esc_attr_e( 'Start time', 'doctor-ak-portal' ); ?>" disabled>
						<span class="dak-availability-sep">&ndash;</span>
						<input type="time" class="dak-availability-end" aria-label="<?php esc_attr_e( 'End time', 'doctor-ak-portal' ); ?>" disabled>
						<input type="number" min="5" max="240" class="dak-clinic-slot-duration" aria-label="<?php esc_attr_e( 'Slot duration in minutes', 'doctor-ak-portal' ); ?>" placeholder="<?php esc_attr_e( 'min', 'doctor-ak-portal' ); ?>" disabled>
					</div>
				<?php endforeach; ?>
			</div>
			<span class="dak-field-error" data-field="sessions"></span>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-session-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Changes', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
