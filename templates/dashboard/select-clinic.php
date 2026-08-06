<?php
/**
 * Template: Mandatory "select your clinic" screen — shown to a patient in
 * place of their normal dashboard right after they register and log in
 * (and on every visit thereafter until they've picked one), so every
 * patient ends up registered under exactly one clinic from the
 * admin-managed Clinic_Locations directory. Country/City/Area filters help
 * them find the nearest one. Has no bearing on video consultations, which
 * remain clinic-agnostic.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $clinic_locations Master clinic list, see Clinic_Locations::get_all().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-auth-page">
	<div class="dak-auth-card dak-select-clinic-card">
		<div class="dak-auth-header">
			<h1><?php esc_html_e( 'Select Your Clinic', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( "Before you continue, let us know which clinic you'd like to be registered under. Filter by country and area to find the one nearest you.", 'doctor-ak-portal' ); ?></p>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-select-clinic-general-error" role="alert"></div>

		<?php if ( empty( $clinic_locations ) ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No clinics have been added yet. Please contact us to complete your registration.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<form id="dak-select-clinic-form" novalidate>
				<div class="dak-field-row">
					<div class="dak-field">
						<label for="dak-select-clinic-country"><?php esc_html_e( 'Country', 'doctor-ak-portal' ); ?></label>
						<select id="dak-select-clinic-country"></select>
					</div>
					<div class="dak-field">
						<label for="dak-select-clinic-city"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?></label>
						<select id="dak-select-clinic-city"></select>
					</div>
					<div class="dak-field">
						<label for="dak-select-clinic-area"><?php esc_html_e( 'Area', 'doctor-ak-portal' ); ?></label>
						<select id="dak-select-clinic-area"></select>
					</div>
				</div>

				<div class="dak-field">
					<label for="dak-select-clinic-clinic"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></label>
					<select id="dak-select-clinic-clinic"></select>
					<span class="dak-field-error" data-field="clinic_location_id"></span>
				</div>

				<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-select-clinic-submit">
					<span class="dak-button-label"><?php esc_html_e( 'Continue', 'doctor-ak-portal' ); ?></span>
				</button>
			</form>
		<?php endif; ?>
	</div>
</div>
