<?php
/**
 * Template: Forgot-password body for the [doctor_forgot_password] shortcode.
 *
 * Renders one of three states depending on the URL:
 * - Default: a form to request a password reset link.
 * - `?key=...&login=...` present and valid: a form to choose a new password.
 * - `?key=...&login=...` present but invalid/expired: an explanation plus
 *   the request form again.
 *
 * Token validation and expiry are WordPress's own (check_password_reset_key());
 * Forgot_Password_Handler re-checks the key before changing anything.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reset_key     = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the reset key is itself the credential, verified below.
$reset_login   = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
$is_reset_mode = ( '' !== $reset_key && '' !== $reset_login );

$reset_key_valid = false;

if ( $is_reset_mode ) {
	$reset_user      = check_password_reset_key( $reset_key, $reset_login );
	$reset_key_valid = ! is_wp_error( $reset_user );
}

$loader    = new \DoctorAKPortal\Includes\Template_Loader();
$login_url = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( 'doctor_login' );
?>
<div class="dak-portal dak-acct dak-acct--recovery">
	<div class="dak-acct-shell">
		<?php
		echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
			'auth/partials/account-aside.php',
			array(
				'title' => __( 'Get back into your account', 'doctor-ak-portal' ),
				'text'  => __( 'We’ll email you a secure link to choose a new password.', 'doctor-ak-portal' ),
			)
		);
		?>
		<main class="dak-acct-card">
			<div class="dak-acct-main">

			<?php if ( $is_reset_mode && $reset_key_valid ) : ?>

				<header class="dak-acct-header">
					<h1><?php esc_html_e( 'Choose a new password', 'doctor-ak-portal' ); ?></h1>
					<p><?php esc_html_e( 'Enter a new password for your account.', 'doctor-ak-portal' ); ?></p>
				</header>

				<div class="dak-alert dak-alert-success dak-hidden" id="dak-reset-success" role="status" aria-live="polite"></div>
				<div class="dak-alert dak-alert-error dak-hidden" id="dak-reset-error" role="alert" tabindex="-1"></div>

				<form id="dak-reset-password-form" class="dak-acct-form" novalidate>
					<input type="hidden" name="key" value="<?php echo esc_attr( $reset_key ); ?>">
					<input type="hidden" name="login" value="<?php echo esc_attr( $reset_login ); ?>">
					<?php // Lets password managers pair the new password with the right account. ?>
					<input type="text" name="dak_username_hint" value="<?php echo esc_attr( $reset_login ); ?>" autocomplete="username" class="dak-visually-hidden" tabindex="-1" aria-hidden="true" readonly>

					<div class="dak-field">
						<label for="dak-new-password"><?php esc_html_e( 'New password', 'doctor-ak-portal' ); ?></label>
						<?php
						echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
							'auth/partials/password-input.php',
							array(
								'id'           => 'dak-new-password',
								'name'         => 'password',
								'autocomplete' => 'new-password',
								'describedby'  => 'dak-new-password-hint dak-new-password-error',
							)
						);
						?>
						<p class="dak-acct-hint" id="dak-new-password-hint"><?php esc_html_e( 'At least 8 characters.', 'doctor-ak-portal' ); ?></p>
						<span class="dak-field-error" id="dak-new-password-error" data-field="password"></span>
					</div>

					<div class="dak-field">
						<label for="dak-confirm-new-password"><?php esc_html_e( 'Confirm new password', 'doctor-ak-portal' ); ?></label>
						<?php
						echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
							'auth/partials/password-input.php',
							array(
								'id'           => 'dak-confirm-new-password',
								'name'         => 'confirm_password',
								'autocomplete' => 'new-password',
								'describedby'  => 'dak-confirm-new-password-error',
							)
						);
						?>
						<span class="dak-field-error" id="dak-confirm-new-password-error" data-field="confirm_password"></span>
					</div>

					<button type="submit" class="dak-button dak-button-primary dak-button-block dak-acct-submit" id="dak-reset-submit" data-loading-label="<?php esc_attr_e( 'Saving…', 'doctor-ak-portal' ); ?>">
						<span class="dak-button-label"><?php esc_html_e( 'Save new password', 'doctor-ak-portal' ); ?></span>
					</button>
				</form>

			<?php else : ?>

				<header class="dak-acct-header">
					<h1><?php esc_html_e( 'Reset your password', 'doctor-ak-portal' ); ?></h1>
					<p><?php esc_html_e( 'Enter your username or email to request a reset link.', 'doctor-ak-portal' ); ?></p>
				</header>

				<?php if ( $is_reset_mode && ! $reset_key_valid ) : ?>
					<div class="dak-alert dak-alert-warning" role="alert">
						<strong><?php esc_html_e( 'This reset link is invalid or has expired.', 'doctor-ak-portal' ); ?></strong>
						<?php esc_html_e( 'Reset links can only be used once and stop working after a while. Request a new one below.', 'doctor-ak-portal' ); ?>
					</div>
				<?php endif; ?>

				<div class="dak-alert dak-alert-success dak-hidden" id="dak-forgot-password-success" role="status" aria-live="polite" tabindex="-1"></div>
				<div class="dak-alert dak-alert-error dak-hidden" id="dak-forgot-password-error" role="alert" tabindex="-1"></div>

				<form id="dak-forgot-password-form" class="dak-acct-form" novalidate>
					<div class="dak-field">
						<label for="dak-login-or-email"><?php esc_html_e( 'Username or email', 'doctor-ak-portal' ); ?></label>
						<input type="text" id="dak-login-or-email" name="login_or_email" required aria-required="true" autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="dak-login-or-email-error">
						<span class="dak-field-error" id="dak-login-or-email-error" data-field="login_or_email"></span>
					</div>

					<button type="submit" class="dak-button dak-button-primary dak-button-block dak-acct-submit" id="dak-forgot-password-submit" data-loading-label="<?php esc_attr_e( 'Sending…', 'doctor-ak-portal' ); ?>">
						<span class="dak-button-label"><?php esc_html_e( 'Send reset link', 'doctor-ak-portal' ); ?></span>
					</button>
				</form>

			<?php endif; ?>

			<?php if ( $login_url ) : ?>
				<p class="dak-acct-switch">
					<a href="<?php echo esc_url( $login_url ); ?>" class="dak-acct-link dak-acct-back">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15l-5-5 5-5"/></svg>
						<?php esc_html_e( 'Back to sign in', 'doctor-ak-portal' ); ?>
					</a>
				</p>
			<?php endif; ?>
			</div>
		</main>
	</div>
	<?php echo $loader->get_template( 'auth/partials/account-footer.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output. ?>
</div>
