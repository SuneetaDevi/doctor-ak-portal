<?php
/**
 * Template: Admin dashboard "Settings" section — clinic branding (phone,
 * logo — used in emails, invoices, and the site footer; name and address
 * are no longer editable here), plus (via $settings_tab_html) the shared
 * Appearance/Notifications card every dashboard's Settings tab renders.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $settings_tab_html Pre-rendered dashboard-settings-tab.php output (Appearance + this admin's own Notification preferences).
 * @var string $clinic_name       Clinic name — read-only here, used only as the logo's initials fallback.
 * @var string $clinic_phone      Clinic contact phone.
 * @var string $clinic_logo_url   Current logo URL, or '' if none set.
 * @var float  $video_fee_percent Video platform/gateway fee — percentage component (0-100).
 * @var float  $video_fee_flat    Video platform/gateway fee — flat PKR amount.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Clinic Settings', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Branding used in emails, invoices and the footer', 'doctor-ak-portal' ); ?></p>
</div>

<section class="dak-dashboard-card" id="dak-clinic-branding-form">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Clinic branding', 'doctor-ak-portal' ); ?></h2>
	</div>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-clinic-branding-error" role="alert"></div>
	<div class="dak-alert dak-alert-success dak-hidden" id="dak-clinic-branding-success" role="status"></div>

	<div class="dak-clinic-branding-logo-row">
		<span class="dak-avatar dak-avatar-lg" id="dak-clinic-branding-logo-preview">
			<?php if ( '' !== $clinic_logo_url ) : ?>
				<img src="<?php echo esc_url( $clinic_logo_url ); ?>" alt="">
			<?php else : ?>
				<?php echo esc_html( '' !== $clinic_name ? mb_strtoupper( mb_substr( $clinic_name, 0, 2 ) ) : 'AK' ); ?>
			<?php endif; ?>
		</span>
		<button type="button" class="dak-button dak-button-secondary" id="dak-clinic-branding-logo-button">
			<span class="dak-nav-icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13V4M6.5 7.5 10 4l3.5 3.5"/><path d="M4 14.5v1a1.5 1.5 0 0 0 1.5 1.5h9a1.5 1.5 0 0 0 1.5-1.5v-1"/></svg></span>
			<?php esc_html_e( 'Upload logo', 'doctor-ak-portal' ); ?>
		</button>
		<input type="file" id="dak-clinic-branding-logo-input" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="dak-hidden">
	</div>

	<div class="dak-field">
		<label for="dak-clinic-branding-phone"><?php esc_html_e( 'Phone', 'doctor-ak-portal' ); ?></label>
		<input type="text" id="dak-clinic-branding-phone" value="<?php echo esc_attr( $clinic_phone ); ?>">
	</div>
</section>

<section class="dak-dashboard-card" id="dak-platform-fee-form">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Video consultation platform fee', 'doctor-ak-portal' ); ?></h2>
	</div>
	<p class="dak-field-hint"><?php esc_html_e( 'Deducted from the gross charge of a video consultation before the doctor/clinic revenue split is applied. Leave at 0 if your payment gateway takes no cut.', 'doctor-ak-portal' ); ?></p>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-platform-fee-error" role="alert"></div>

	<div class="dak-field-row">
		<div class="dak-field">
			<label for="dak-platform-fee-percent"><?php esc_html_e( 'Fee percentage (%)', 'doctor-ak-portal' ); ?></label>
			<input type="number" id="dak-platform-fee-percent" min="0" max="100" step="0.01" value="<?php echo esc_attr( $video_fee_percent ); ?>">
		</div>
		<div class="dak-field">
			<label for="dak-platform-fee-flat"><?php esc_html_e( 'Flat fee (PKR)', 'doctor-ak-portal' ); ?></label>
			<input type="number" id="dak-platform-fee-flat" min="0" step="0.01" value="<?php echo esc_attr( $video_fee_flat ); ?>">
		</div>
	</div>
</section>

<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Appearance and your own notification preferences', 'doctor-ak-portal' ); ?></p>
</div>

<section class="dak-dashboard-card dak-dashboard-profile-form">
	<?php echo $settings_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own dashboard-settings-tab.php template, which escapes its own output — it renders its own "Appearance" and "Notifications" sections (shared with the Doctor/Patient/Receptionist dashboards' Settings tab), with its own "Save preferences" button suppressed here (show_save_button => false) in favor of the single button below. ?>
</section>

<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-settings-error" role="alert"></div>
<div class="dak-alert dak-alert-success dak-hidden" id="dak-admin-settings-success" role="status"></div>

<button type="button" class="dak-button dak-button-primary" id="dak-admin-settings-save">
	<span class="dak-button-label"><?php esc_html_e( 'Save Settings', 'doctor-ak-portal' ); ?></span>
</button>
