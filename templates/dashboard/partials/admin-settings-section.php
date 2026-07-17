<?php
/**
 * Template: Admin dashboard "Settings" section chrome (greeting + card),
 * wrapping the shared dashboard-settings-tab.php content — the Doctor/Patient
 * dashboards supply this same chrome from their own parent templates, but
 * the admin dashboard's non-Users sections render their own per-section
 * chrome, so this small wrapper exists to match.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $settings_tab_html Pre-rendered dashboard-settings-tab.php output.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></h1>
</div>

<section class="dak-dashboard-card dak-admin-users-card">
	<?php echo $settings_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own dashboard-settings-tab.php template, which escapes its own output. ?>
</section>
