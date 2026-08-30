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
