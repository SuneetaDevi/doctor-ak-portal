<?php
/**
 * Template: Login form body for the [doctor_login] shortcode.
 *
 * One shared sign-in for patients, doctors, administrators and receptionists
 * — the account's own role decides where it lands (Login_Handler). Renders
 * body content only — the active theme supplies header, navigation and
 * footer around this markup.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $redirect_to Same-site URL to send the visitor to after login instead of their role's dashboard (e.g. back to a booking page they came from), or '' for the usual default — see Shortcodes::render_doctor_login()/Login_Handler::handle_login().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$redirect_to = isset( $redirect_to ) ? $redirect_to : '';
$loader      = new \DoctorAKPortal\Includes\Template_Loader();

$forgot_password_url = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( 'doctor_forgot_password' );
$register_url        = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( 'doctor_register' );

// Signing up from a booking flow should come back to it too.
if ( '' !== $redirect_to && $register_url ) {
	$register_url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $register_url );
}
?>
<div class="dak-portal dak-acct dak-acct--login">
	<div class="dak-acct-shell">
		<?php
		echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
			'auth/partials/account-aside.php',
			array(
				'title' => __( 'Your healthcare appointments, in one place', 'doctor-ak-portal' ),
				'text'  => __( 'Book visits, check what’s coming up and keep your details up to date.', 'doctor-ak-portal' ),
			)
		);
		?>

		<main class="dak-acct-card">
			<div class="dak-acct-main">
			<header class="dak-acct-header">
				<h1><?php esc_html_e( 'Welcome back', 'doctor-ak-portal' ); ?></h1>
				<p><?php esc_html_e( 'Sign in to manage your appointments and account.', 'doctor-ak-portal' ); ?></p>
			</header>

			<div class="dak-alert dak-alert-success dak-hidden" id="dak-login-success" role="status" aria-live="polite"></div>
			<div class="dak-alert dak-alert-error dak-hidden" id="dak-login-error" role="alert" tabindex="-1"></div>

			<form id="dak-login-form" class="dak-acct-form" novalidate>
				<?php if ( '' !== $redirect_to ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
				<?php endif; ?>

				<div class="dak-field">
					<label for="dak-login-username"><?php esc_html_e( 'Username or email', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-login-username" name="login" required aria-required="true" autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="dak-login-username-error">
					<span class="dak-field-error" id="dak-login-username-error" data-field="login"></span>
				</div>

				<div class="dak-field">
					<label for="dak-login-password"><?php esc_html_e( 'Password', 'doctor-ak-portal' ); ?></label>
					<?php
					echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
						'auth/partials/password-input.php',
						array(
							'id'           => 'dak-login-password',
							'name'         => 'password',
							'autocomplete' => 'current-password',
							'describedby'  => 'dak-login-password-error',
						)
					);
					?>
					<span class="dak-field-error" id="dak-login-password-error" data-field="password"></span>
				</div>

				<div class="dak-acct-row">
					<label class="dak-checkbox">
						<input type="checkbox" name="remember" value="1">
						<span><?php esc_html_e( 'Remember me', 'doctor-ak-portal' ); ?></span>
					</label>

					<?php if ( $forgot_password_url ) : ?>
						<a href="<?php echo esc_url( $forgot_password_url ); ?>" class="dak-acct-link"><?php esc_html_e( 'Forgot password?', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
				</div>

				<button type="submit" class="dak-button dak-button-primary dak-button-block dak-acct-submit" id="dak-login-submit" data-loading-label="<?php esc_attr_e( 'Signing in…', 'doctor-ak-portal' ); ?>">
					<span class="dak-button-label"><?php esc_html_e( 'Sign in', 'doctor-ak-portal' ); ?></span>
				</button>
			</form>

			<?php if ( $register_url ) : ?>
				<p class="dak-acct-switch">
					<?php esc_html_e( 'New here?', 'doctor-ak-portal' ); ?>
					<a href="<?php echo esc_url( $register_url ); ?>" class="dak-acct-link"><?php esc_html_e( 'Create an account', 'doctor-ak-portal' ); ?></a>
				</p>
			<?php endif; ?>
			</div>
		</main>
	</div>
	<?php echo $loader->get_template( 'auth/partials/account-footer.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output. ?>
</div>
