<?php
/**
 * Template: "Add Encounter" modal on the Encounters list — opens a walk-in
 * encounter with no pre-existing appointment. Asks for Clinic first, then
 * Doctor (filtered to that clinic, client-side — see
 * Admin_Dashboard::doctors_by_clinic_location()), then Patient, via
 * Encounter_Handler::handle_create_encounter().
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $clinic_locations Rows from Clinic_Locations::get_all().
 * @var array $patient_options  Patient user ID => display name, see Appointments::patient_options().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-add-encounter-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-add-encounter-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-add-encounter-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-add-encounter-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-add-encounter-modal-title"><?php esc_html_e( 'Add Encounter', 'doctor-ak-portal' ); ?></h2>
		</div>

		<p class="dak-field-hint"><?php esc_html_e( "For a walk-in patient with no existing appointment — pick the clinic, then the doctor seeing them, then the patient.", 'doctor-ak-portal' ); ?></p>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-add-encounter-general-error" role="alert"></div>

		<div class="dak-field">
			<label for="dak-admin-add-encounter-clinic"><?php esc_html_e( '1. Clinic', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-add-encounter-clinic">
				<option value="0"><?php esc_html_e( 'Select a clinic…', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $clinic_locations as $dak_clinic_location ) : ?>
					<?php
					$dak_clinic_label = implode(
						', ',
						array_filter( array( $dak_clinic_location['name'], $dak_clinic_location['area_label'], $dak_clinic_location['city_label'] ) )
					);
					?>
					<option value="<?php echo esc_attr( $dak_clinic_location['id'] ); ?>"><?php echo esc_html( $dak_clinic_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dak-field-error" data-field="clinic_location_id"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-add-encounter-doctor"><?php esc_html_e( '2. Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-add-encounter-doctor" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>" disabled>
				<option value="0"><?php esc_html_e( 'Select a clinic first…', 'doctor-ak-portal' ); ?></option>
			</select>
			<span class="dak-field-error" data-field="doctor_id"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-add-encounter-patient"><?php esc_html_e( '3. Patient', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-add-encounter-patient" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search patients…', 'doctor-ak-portal' ); ?>" disabled>
				<option value="0"><?php esc_html_e( 'Select a doctor first…', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $patient_options as $dak_patient_id => $dak_patient_name ) : ?>
					<option value="<?php echo esc_attr( $dak_patient_id ); ?>"><?php echo esc_html( $dak_patient_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dak-field-error" data-field="patient_id"></span>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-add-encounter-save" disabled>
			<span class="dak-button-label"><?php esc_html_e( 'Check In & Open Encounter', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
