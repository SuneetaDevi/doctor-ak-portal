<?php
/**
 * Template: Access-denied body shown by the dashboard shortcodes when the
 * visitor is logged out, or logged in with the wrong role.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $reason        'logged_out', 'wrong_role', or 'not_permitted'.
 * @var string $login_url     Set when $reason is 'logged_out'.
 * @var string $dashboard_url Set when $reason is 'wrong_role' or 'not_permitted'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reason = isset( $reason ) ? $reason : 'logged_out';
?>
<div class="dak-portal dak-dashboard-access-denied">
	<div class="dak-auth-card">
		<?php if ( 'logged_out' === $reason ) : ?>
			<h1><?php esc_html_e( 'Please Log In', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'You need to be logged in to view this page.', 'doctor-ak-portal' ); ?></p>
			<?php if ( ! empty( $login_url ) ) : ?>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log In', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		<?php elseif ( 'not_permitted' === $reason ) : ?>
			<h1><?php esc_html_e( "You Don't Have Access to This Page", 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'An administrator has turned this page off for your account.', 'doctor-ak-portal' ); ?></p>
			<?php if ( ! empty( $dashboard_url ) ) : ?>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Go to Your Dashboard', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		<?php else : ?>
			<h1><?php esc_html_e( "You Don't Have Access to This Page", 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'This dashboard is not available for your account type.', 'doctor-ak-portal' ); ?></p>
			<?php if ( ! empty( $dashboard_url ) ) : ?>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Go to Your Dashboard', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
