<?php
/**
 * Template: Dashboard "Settings" tab/section content, shared by the Admin,
 * Doctor and Patient dashboards. Currently just the light/dark theme
 * toggle — a natural home for future account-level preferences.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-settings-section">
	<h2><?php esc_html_e( 'Appearance', 'doctor-ak-portal' ); ?></h2>
	<div class="dak-settings-row">
		<div class="dak-settings-row-text">
			<strong><?php esc_html_e( 'Dark Mode', 'doctor-ak-portal' ); ?></strong>
			<p><?php esc_html_e( 'Switch the dashboard between light and dark. Your choice is saved to your account.', 'doctor-ak-portal' ); ?></p>
		</div>
		<?php echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( 'dashboard/partials/theme-toggle-button.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own theme-toggle-button.php template, which escapes its own output. ?>
	</div>
</div>
