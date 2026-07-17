<?php
/**
 * Template: Global booking modal (Clinic Appointment / Online Video
 * Consultation), rendered once at the end of every front-end page.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_options Doctor user ID => display name, for the doctor <select>.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-booking-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-booking-modal-title">
		<button type="button" class="dak-modal-close" data-dak-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<span class="dak-eyebrow"><?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?></span>
			<h2 id="dak-booking-modal-title"><?php esc_html_e( 'Book a Consultation', 'doctor-ak-portal' ); ?></h2>
			<p><?php esc_html_e( "Enter your details and we'll take care of the booking request.", 'doctor-ak-portal' ); ?></p>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-booking-error" role="alert"></div>
		<div class="dak-alert dak-alert-success dak-hidden" id="dak-booking-success" role="status"></div>

		<form id="dak-booking-form" novalidate>
			<input type="hidden" name="doctor_id" id="dak-booking-doctor-id" value="">

			<div class="dak-field" id="dak-booking-doctor-field">
				<label for="dak-booking-doctor-select"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
				<select id="dak-booking-doctor-select">
					<option value=""><?php esc_html_e( 'Select a doctor…', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $doctor_options as $doctor_id => $doctor_name ) : ?>
						<option
							value="<?php echo esc_attr( $doctor_id ); ?>"
							data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $doctor_name ) ); ?>"
							<?php if ( ! \DoctorAKPortal\Includes\Clinics::doctor_has_active_video_clinic( $doctor_id ) ) : ?>data-video-disabled="1"<?php endif; ?>
						><?php echo esc_html( sprintf( 'Dr. %s', $doctor_name ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="dak-field-error" data-field="doctor_id"></span>
			</div>

			<p class="dak-booking-doctor-name dak-hidden" id="dak-booking-doctor-name"></p>

			<div class="dak-booking-tabs" role="tablist">
				<button type="button" class="dak-booking-tab is-active" data-type="clinic" role="tab" aria-selected="true"><?php esc_html_e( 'Clinic Appointment', 'doctor-ak-portal' ); ?></button>
				<button type="button" class="dak-booking-tab" data-type="video" role="tab" aria-selected="false"><?php esc_html_e( 'Online Video Consultation', 'doctor-ak-portal' ); ?></button>
			</div>
			<input type="hidden" name="type" id="dak-booking-type" value="clinic">
			<p class="dak-field-hint dak-hidden" id="dak-booking-video-unavailable"><?php esc_html_e( 'This doctor does not offer online video consultations.', 'doctor-ak-portal' ); ?></p>

			<div class="dak-field-row">
				<div class="dak-field">
					<label for="dak-booking-date"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></label>
					<input type="date" id="dak-booking-date" name="date" required>
					<span class="dak-field-error" data-field="date"></span>
				</div>
				<div class="dak-field">
					<label for="dak-booking-time"><?php esc_html_e( 'Time', 'doctor-ak-portal' ); ?></label>
					<input type="time" id="dak-booking-time" name="time" required>
					<span class="dak-field-error" data-field="time"></span>
				</div>
			</div>

			<div id="dak-booking-identity-loggedin" class="dak-hidden">
				<div class="dak-field">
					<label><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-booking-loggedin-name" disabled>
				</div>
				<div class="dak-field-row">
					<div class="dak-field">
						<label><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?></label>
						<input type="text" id="dak-booking-loggedin-email" disabled>
					</div>
					<div class="dak-field">
						<label><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?></label>
						<input type="text" id="dak-booking-loggedin-phone" disabled>
					</div>
				</div>
			</div>

			<div id="dak-booking-identity-choice" class="dak-hidden">
				<p class="dak-field-hint"><?php esc_html_e( 'Log in or register for faster booking, or continue without an account.', 'doctor-ak-portal' ); ?></p>
				<div class="dak-booking-identity-choices">
					<a class="dak-button dak-button-secondary" id="dak-booking-login-link" href="#"><?php esc_html_e( 'Log In', 'doctor-ak-portal' ); ?></a>
					<a class="dak-button dak-button-secondary" id="dak-booking-register-link" href="#"><?php esc_html_e( 'Register', 'doctor-ak-portal' ); ?></a>
					<button type="button" class="dak-button dak-button-secondary" id="dak-booking-guest-toggle"><?php esc_html_e( 'Continue as Guest', 'doctor-ak-portal' ); ?></button>
				</div>
			</div>

			<div id="dak-booking-identity-guest" class="dak-hidden">
				<div class="dak-field">
					<label for="dak-booking-guest-name"><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-booking-guest-name" name="guest_name">
					<span class="dak-field-error" data-field="guest_name"></span>
				</div>
				<div class="dak-field-row">
					<div class="dak-field">
						<label for="dak-booking-guest-email"><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?></label>
						<input type="email" id="dak-booking-guest-email" name="guest_email">
						<span class="dak-field-error" data-field="guest_email"></span>
					</div>
					<div class="dak-field">
						<label for="dak-booking-guest-phone"><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?></label>
						<input type="tel" id="dak-booking-guest-phone" name="guest_phone">
						<span class="dak-field-error" data-field="guest_phone"></span>
					</div>
				</div>
			</div>

			<div class="dak-field">
				<label for="dak-booking-notes"><?php esc_html_e( 'Notes (optional)', 'doctor-ak-portal' ); ?></label>
				<textarea id="dak-booking-notes" name="notes" rows="2"></textarea>
			</div>

			<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-booking-submit">
				<span class="dak-button-label"><?php esc_html_e( 'Book Consultation', 'doctor-ak-portal' ); ?></span>
			</button>
		</form>
	</div>
</div>
