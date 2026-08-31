<?php
/**
 * Template: Forgot-password body for the [doctor_forgot_password] shortcode.
 *
 * Renders one of two states depending on the URL:
 * - Default: a form to request a password reset link.
 * - `?key=...&login=...` present and valid: a form to choose a new password.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reset_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$reset_login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
$is_reset_mode = ( '' !== $reset_key && '' !== $reset_login );

$reset_key_valid = false;

if ( $is_reset_mode ) {
	$reset_user      = check_password_reset_key( $reset_key, $reset_login );
	$reset_key_valid = ! is_wp_error( $reset_user );
}
?>
<div class="dak-portal dak-auth-page">
	<div class="dak-auth-visual" aria-hidden="true">
		<span class="dak-eyebrow"><?php esc_html_e( 'Dr. AK Lohana Clinic', 'doctor-ak-portal' ); ?></span>
		<h2><?php esc_html_e( "We'll get you back in.", 'doctor-ak-portal' ); ?></h2>
		<div class="dak-auth-visual-benefits">
			<div class="dak-auth-visual-benefit">
				<span class="dak-auth-visual-benefit-icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l6.5 2.5v4.3c0 4-2.8 7.2-6.5 8.2-3.7-1-6.5-4.2-6.5-8.2V5z"/><path d="M7.2 10l2 2 3.6-4"/></svg></span>
				<div><strong><?php esc_html_e( 'Secure by design', 'doctor-ak-portal' ); ?></strong><span><?php esc_html_e( 'Reset links are single-use and expire quickly.', 'doctor-ak-portal' ); ?></span></div>
			</div>
			<div class="dak-auth-visual-benefit">
				<span class="dak-auth-visual-benefit-icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6v4l3 2"/></svg></span>
				<div><strong><?php esc_html_e( 'Back to your dashboard fast', 'doctor-ak-portal' ); ?></strong><span><?php esc_html_e( 'Appointments and records are right where you left them.', 'doctor-ak-portal' ); ?></span></div>
			</div>
		</div>
	</div>

	<div class="dak-auth-card">

		<?php if ( $is_reset_mode && $reset_key_valid ) : ?>

			<div class="dak-auth-header">
				<span class="dak-eyebrow"><?php esc_html_e( 'Dr. AK Lohana Clinic & Endoscopy Services', 'doctor-ak-portal' ); ?></span>
				<h1><?php esc_html_e( 'Set a New Password', 'doctor-ak-portal' ); ?></h1>
				<p><?php esc_html_e( 'Choose a new password for your account.', 'doctor-ak-portal' ); ?></p>
			</div>

			<div class="dak-alert dak-alert-success dak-hidden" id="dak-reset-success" role="status"></div>
			<div class="dak-alert dak-alert-error dak-hidden" id="dak-reset-error" role="alert"></div>

			<form id="dak-reset-password-form" novalidate>
				<input type="hidden" name="key" value="<?php echo esc_attr( $reset_key ); ?>">
				<input type="hidden" name="login" value="<?php echo esc_attr( $reset_login ); ?>">

				<div class="dak-field">
					<label for="dak-new-password"><?php esc_html_e( 'New Password', 'doctor-ak-portal' ); ?></label>
					<input type="password" id="dak-new-password" name="password" required autocomplete="new-password">
					<span class="dak-field-error" data-field="password"></span>
				</div>

				<div class="dak-field">
					<label for="dak-confirm-new-password"><?php esc_html_e( 'Confirm New Password', 'doctor-ak-portal' ); ?></label>
					<input type="password" id="dak-confirm-new-password" name="confirm_password" required autocomplete="new-password">
					<span class="dak-field-error" data-field="confirm_password"></span>
				</div>

				<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-reset-submit">
					<span class="dak-button-label"><?php esc_html_e( 'Reset Password', 'doctor-ak-portal' ); ?></span>
				</button>
			</form>

		<?php else : ?>

			<div class="dak-auth-header">
				<span class="dak-eyebrow"><?php esc_html_e( 'Dr. AK Lohana Clinic & Endoscopy Services', 'doctor-ak-portal' ); ?></span>
				<h1><?php esc_html_e( 'Forgot Your Password?', 'doctor-ak-portal' ); ?></h1>
				<p><?php esc_html_e( "Enter your username or email address and we'll send you a link to reset your password.", 'doctor-ak-portal' ); ?></p>
			</div>

			<?php if ( $is_reset_mode && ! $reset_key_valid ) : ?>
				<div class="dak-alert dak-alert-error" role="alert">
					<?php esc_html_e( 'This password reset link is invalid or has expired. Please request a new one below.', 'doctor-ak-portal' ); ?>
				</div>
			<?php endif; ?>

			<div class="dak-alert dak-alert-success dak-hidden" id="dak-forgot-password-success" role="status"></div>
			<div class="dak-alert dak-alert-error dak-hidden" id="dak-forgot-password-error" role="alert"></div>

			<form id="dak-forgot-password-form" novalidate>
				<div class="dak-field">
					<label for="dak-login-or-email"><?php esc_html_e( 'Username or Email', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-login-or-email" name="login_or_email" required>
				</div>

				<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-forgot-password-submit">
					<span class="dak-button-label"><?php esc_html_e( 'Send Reset Link', 'doctor-ak-portal' ); ?></span>
				</button>
			</form>

		<?php endif; ?>

	</div>
</div>
