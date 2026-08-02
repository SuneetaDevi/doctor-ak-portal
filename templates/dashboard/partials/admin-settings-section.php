<?php
/**
 * Template: Admin dashboard "Settings" section — clinic branding (name,
 * phone, address, logo — used in emails, invoices, and the site footer),
 * plus appearance and notification preferences.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $settings_tab_html Pre-rendered dashboard-settings-tab.php output (the shared "Appearance" theme toggle).
 * @var string $clinic_name       Clinic name (Settings → Footer Settings / this form).
 * @var string $clinic_phone      Clinic contact phone.
 * @var string $clinic_address    Clinic address.
 * @var string $clinic_logo_url   Current logo URL, or '' if none set.
 * @var bool   $notify_booking    Whether "appointment booked" emails are sent.
 * @var bool   $notify_paid       Whether "payment received" emails are sent.
 * @var bool   $notify_cancelled  Whether "cancellation/refund" emails are sent.
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

	<div class="dak-field-row">
		<div class="dak-field">
			<label for="dak-clinic-branding-name"><?php esc_html_e( 'Clinic name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-clinic-branding-name" value="<?php echo esc_attr( $clinic_name ); ?>">
		</div>
		<div class="dak-field">
			<label for="dak-clinic-branding-phone"><?php esc_html_e( 'Phone', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-clinic-branding-phone" value="<?php echo esc_attr( $clinic_phone ); ?>">
		</div>
	</div>

	<div class="dak-field">
		<label for="dak-clinic-branding-address"><?php esc_html_e( 'Address', 'doctor-ak-portal' ); ?></label>
		<textarea id="dak-clinic-branding-address" rows="3"><?php echo esc_textarea( $clinic_address ); ?></textarea>
	</div>

	<button type="button" class="dak-button dak-button-primary" id="dak-clinic-branding-save">
		<span class="dak-button-label"><?php esc_html_e( 'Save branding', 'doctor-ak-portal' ); ?></span>
	</button>
</section>

<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Appearance and notification preferences', 'doctor-ak-portal' ); ?></p>
</div>

<section class="dak-dashboard-card">
	<?php echo $settings_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own dashboard-settings-tab.php template, which escapes its own output — it renders its own "Appearance" heading (shared with the Doctor/Patient dashboards' Settings tab), so this card doesn't add a duplicate one. ?>
</section>

<section class="dak-dashboard-card" id="dak-notification-preferences-form">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?></h2>
	</div>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-notification-preferences-error" role="alert"></div>
	<div class="dak-alert dak-alert-success dak-hidden" id="dak-notification-preferences-success" role="status"></div>

	<div class="dak-settings-row">
		<div class="dak-settings-row-text">
			<strong><?php esc_html_e( 'Appointment booked', 'doctor-ak-portal' ); ?></strong>
			<p><?php esc_html_e( 'Email the clinic when a new appointment is booked.', 'doctor-ak-portal' ); ?></p>
		</div>
		<label class="dak-toggle-switch">
			<input type="checkbox" id="dak-notify-booking" <?php checked( $notify_booking ); ?>>
			<span></span>
		</label>
	</div>

	<div class="dak-settings-row">
		<div class="dak-settings-row-text">
			<strong><?php esc_html_e( 'Payment received', 'doctor-ak-portal' ); ?></strong>
			<p><?php esc_html_e( 'Email the clinic when a patient completes payment.', 'doctor-ak-portal' ); ?></p>
		</div>
		<label class="dak-toggle-switch">
			<input type="checkbox" id="dak-notify-paid" <?php checked( $notify_paid ); ?>>
			<span></span>
		</label>
	</div>

	<div class="dak-settings-row">
		<div class="dak-settings-row-text">
			<strong><?php esc_html_e( 'Cancellations & refunds', 'doctor-ak-portal' ); ?></strong>
			<p><?php esc_html_e( 'Email the clinic about cancellations and refund requests.', 'doctor-ak-portal' ); ?></p>
		</div>
		<label class="dak-toggle-switch">
			<input type="checkbox" id="dak-notify-cancelled" <?php checked( $notify_cancelled ); ?>>
			<span></span>
		</label>
	</div>

	<button type="button" class="dak-button dak-button-primary" id="dak-notification-preferences-save">
		<span class="dak-button-label"><?php esc_html_e( 'Save preferences', 'doctor-ak-portal' ); ?></span>
	</button>
</section>
