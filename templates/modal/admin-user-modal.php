<?php
/**
 * Template: Add/Edit Doctor or Patient modal for the admin dashboard.
 *
 * Rendered once inside admin-dashboard.php and toggled by
 * assets/js/doctor-ak-admin-dashboard.js; used for both creating a new
 * account and editing an existing one (the JS swaps labels/values and
 * shows/hides the password + role fields as needed).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $role            Roles::DOCTOR_ROLE or Roles::PATIENT_ROLE for the active section.
 * @var array  $specializations All specialization slug => label.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-user-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-user-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-user-modal-title"><?php esc_html_e( 'Add Doctor', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-user-modal-general-error" role="alert"></div>

		<form id="dak-admin-user-form" novalidate>
			<input type="hidden" name="user_id" id="dak-admin-user-id" value="0">
			<input type="hidden" name="role" id="dak-admin-user-role" value="<?php echo esc_attr( $role ); ?>">

			<div class="dak-field-row">
				<div class="dak-field">
					<label for="dak-admin-user-first-name"><?php esc_html_e( 'First Name', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-admin-user-first-name" name="first_name" required>
					<span class="dak-field-error" data-field="first_name"></span>
				</div>
				<div class="dak-field">
					<label for="dak-admin-user-last-name"><?php esc_html_e( 'Last Name', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-admin-user-last-name" name="last_name" required>
					<span class="dak-field-error" data-field="last_name"></span>
				</div>
			</div>

			<div class="dak-field">
				<label for="dak-admin-user-email"><?php esc_html_e( 'Email Address', 'doctor-ak-portal' ); ?></label>
				<input type="email" id="dak-admin-user-email" name="email" required autocomplete="off">
				<span class="dak-field-error" data-field="email"></span>
			</div>

			<div class="dak-field">
				<label for="dak-admin-user-specializations"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-user-specializations" name="specializations[]" multiple required>
					<?php foreach ( $specializations as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="dak-field-error" data-field="specializations"></span>
			</div>

			<div class="dak-field" id="dak-admin-user-password-field">
				<label for="dak-admin-user-password"><?php esc_html_e( 'Password', 'doctor-ak-portal' ); ?></label>
				<input type="password" id="dak-admin-user-password" name="password" autocomplete="new-password">
				<span class="dak-field-hint" id="dak-admin-user-password-hint"></span>
				<span class="dak-field-error" data-field="password"></span>
			</div>

			<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-admin-user-submit">
				<span class="dak-button-label"><?php esc_html_e( 'Save', 'doctor-ak-portal' ); ?></span>
			</button>
		</form>
	</div>
</div>
