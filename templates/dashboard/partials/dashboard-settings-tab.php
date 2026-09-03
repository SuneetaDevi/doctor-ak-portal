<?php
/**
 * Template: Dashboard "Settings" tab/section content, shared by the Admin,
 * Doctor, Receptionist and Patient dashboards — each account's own
 * notification email preferences (whether *they* personally receive
 * "appointment booked"/"payment received"/"cancelled" emails for
 * appointments involving them — a per-account choice, see
 * Notifications::user_wants(), not a clinic-wide switch). The light/dark
 * toggle lives in the public site header now (see templates/site-header.php)
 * — the dashboards no longer have a separate one.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var bool $notify_booking       Whether the current account receives "appointment booked" emails. Defaults true if unset.
 * @var bool $notify_paid          Whether the current account receives "payment received" emails. Defaults true if unset.
 * @var bool $notify_cancelled     Whether the current account receives "cancellation" emails. Defaults true if unset.
 * @var bool $notify_announcements Whether the current account receives "new blog post/service" announcement emails. Defaults true if unset.
 * @var bool $show_save_button     Whether to render this section's own "Save preferences" button. Defaults true; the admin dashboard's Settings page passes false and saves this alongside Clinic Branding via its own single "Save Settings" button instead (see admin-settings-section.php).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notify_booking       = isset( $notify_booking ) ? $notify_booking : true;
$notify_paid          = isset( $notify_paid ) ? $notify_paid : true;
$notify_cancelled     = isset( $notify_cancelled ) ? $notify_cancelled : true;
$notify_announcements = isset( $notify_announcements ) ? $notify_announcements : true;
$show_save_button     = ! isset( $show_save_button ) || $show_save_button;
?>
<div class="dak-settings-section" id="dak-notification-preferences-form">
	<h2><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?></h2>
	<p class="dak-settings-section-subtitle"><?php esc_html_e( "These control only your own account — no one else's notifications are affected.", 'doctor-ak-portal' ); ?></p>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-notification-preferences-error" role="alert"></div>
	<div class="dak-alert dak-alert-success dak-hidden" id="dak-notification-preferences-success" role="status"></div>

	<div class="dak-settings-row">
		<div class="dak-settings-row-text">
			<strong><?php esc_html_e( 'Appointment booked', 'doctor-ak-portal' ); ?></strong>
			<p><?php esc_html_e( 'Email me when an appointment involving me is booked.', 'doctor-ak-portal' ); ?></p>
		</div>
		<label class="dak-toggle-switch">
			<input type="checkbox" id="dak-notify-booking" <?php checked( $notify_booking ); ?>>
			<span></span>
		</label>
	</div>

	<div class="dak-settings-row">
		<div class="dak-settings-row-text">
			<strong><?php esc_html_e( 'Payment received', 'doctor-ak-portal' ); ?></strong>
			<p><?php esc_html_e( 'Email me when payment is completed for an appointment involving me.', 'doctor-ak-portal' ); ?></p>
		</div>
		<label class="dak-toggle-switch">
			<input type="checkbox" id="dak-notify-paid" <?php checked( $notify_paid ); ?>>
			<span></span>
		</label>
	</div>

	<div class="dak-settings-row">
		<div class="dak-settings-row-text">
			<strong><?php esc_html_e( 'Cancellations', 'doctor-ak-portal' ); ?></strong>
			<p><?php esc_html_e( 'Email me when an appointment involving me is cancelled.', 'doctor-ak-portal' ); ?></p>
		</div>
		<label class="dak-toggle-switch">
			<input type="checkbox" id="dak-notify-cancelled" <?php checked( $notify_cancelled ); ?>>
			<span></span>
		</label>
	</div>

	<div class="dak-settings-row">
		<div class="dak-settings-row-text">
			<strong><?php esc_html_e( 'Announcements', 'doctor-ak-portal' ); ?></strong>
			<p><?php esc_html_e( 'Email me when a new blog post or service is added.', 'doctor-ak-portal' ); ?></p>
		</div>
		<label class="dak-toggle-switch">
			<input type="checkbox" id="dak-notify-announcements" <?php checked( $notify_announcements ); ?>>
			<span></span>
		</label>
	</div>

	<?php if ( $show_save_button ) : ?>
		<button type="button" class="dak-button dak-button-primary" id="dak-notification-preferences-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save preferences', 'doctor-ak-portal' ); ?></span>
		</button>
	<?php endif; ?>
</div>
