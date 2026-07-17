<?php
/**
 * Template: Login form body for the [doctor_login] shortcode.
 *
 * Renders body content only — the active theme supplies header, navigation
 * and footer around this markup.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forgot_password_url = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( 'doctor_forgot_password' );
$register_url         = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( 'doctor_register' );
?>
<div class="dak-portal dak-auth-page">
	<div class="dak-auth-card">
		<div class="dak-auth-header">
			<h1><?php esc_html_e( 'Welcome Back', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Log in to access your dashboard.', 'doctor-ak-portal' ); ?></p>
		</div>

		<div class="dak-alert dak-alert-success dak-hidden" id="dak-login-success" role="status"></div>
		<div class="dak-alert dak-alert-error dak-hidden" id="dak-login-error" role="alert"></div>

		<form id="dak-login-form" novalidate>
			<div class="dak-field">
				<label for="dak-login-username"><?php esc_html_e( 'Username or Email', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-login-username" name="login" required autocomplete="username">
			</div>

			<div class="dak-field">
				<label for="dak-login-password"><?php esc_html_e( 'Password', 'doctor-ak-portal' ); ?></label>
				<div class="dak-password-field">
					<input type="password" id="dak-login-password" name="password" required autocomplete="current-password">
					<button type="button" class="dak-password-toggle" aria-label="<?php esc_attr_e( 'Show password', 'doctor-ak-portal' ); ?>">
						<svg class="dak-icon-eye" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6-8.5-6-8.5-6z"/><circle cx="10" cy="10" r="2.5"/></svg>
						<svg class="dak-icon-eye-off" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 2.5l15 15"/><path d="M8.3 4.3C8.85 4.1 9.42 4 10 4c5.5 0 8.5 6 8.5 6a14.9 14.9 0 0 1-3 3.9M5.5 5.6C3 7.2 1.5 10 1.5 10s3 6 8.5 6c1.1 0 2.1-.24 3-.63"/><path d="M7.8 7.8a2.5 2.5 0 0 0 3.4 3.5"/></svg>
					</button>
				</div>
			</div>

			<div class="dak-field dak-field-inline">
				<label class="dak-checkbox">
					<input type="checkbox" name="remember" value="1">
					<span><?php esc_html_e( 'Remember Me', 'doctor-ak-portal' ); ?></span>
				</label>

				<?php if ( $forgot_password_url ) : ?>
					<a href="<?php echo esc_url( $forgot_password_url ); ?>" class="dak-link"><?php esc_html_e( 'Forgot Password?', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</div>

			<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-login-submit">
				<span class="dak-button-label"><?php esc_html_e( 'Log In', 'doctor-ak-portal' ); ?></span>
			</button>

			<?php if ( $register_url ) : ?>
				<p class="dak-form-footer-link">
					<?php esc_html_e( 'New user?', 'doctor-ak-portal' ); ?>
					<a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register', 'doctor-ak-portal' ); ?></a>
				</p>
			<?php endif; ?>
		</form>
	</div>
</div>
