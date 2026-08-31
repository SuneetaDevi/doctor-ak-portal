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
 * @var string $google_place_id  Saved Google Place ID, or '' if not configured.
 * @var string $google_api_key   Saved Google Places API key, or '' if not configured.
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

<section class="dak-dashboard-card" id="dak-home-videos-form" data-home-videos-editor>
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Home page videos', 'doctor-ak-portal' ); ?></h2>
	</div>
	<p class="dak-field-hint"><?php esc_html_e( 'Shown in a video section on your public Home page. Upload short clinic tour or patient story videos (MP4 recommended) with an optional title and thumbnail.', 'doctor-ak-portal' ); ?></p>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-home-videos-error" role="alert"></div>

	<div class="dak-home-videos-rows" data-home-videos-rows></div>

	<button type="button" class="dak-button dak-button-secondary" data-home-videos-add-row>
		<span class="dak-nav-icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4.5v11M4.5 10h11"/></svg></span>
		<?php esc_html_e( 'Add Video', 'doctor-ak-portal' ); ?>
	</button>
</section>

<section class="dak-dashboard-card" id="dak-home-testimonials-form" data-home-testimonials-editor>
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Home page testimonials', 'doctor-ak-portal' ); ?></h2>
	</div>
	<p class="dak-field-hint"><?php esc_html_e( 'Shown in the "Patient Stories" section on your public Home page. Add a short quote, the patient\'s name, and an optional attribution (e.g. a clinic name).', 'doctor-ak-portal' ); ?></p>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-home-testimonials-error" role="alert"></div>

	<div class="dak-home-testimonials-rows" data-home-testimonials-rows></div>

	<button type="button" class="dak-button dak-button-secondary" data-home-testimonials-add-row>
		<span class="dak-nav-icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4.5v11M4.5 10h11"/></svg></span>
		<?php esc_html_e( 'Add Testimonial', 'doctor-ak-portal' ); ?>
	</button>
</section>

<section class="dak-dashboard-card" id="dak-google-reviews-form">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Google reviews', 'doctor-ak-portal' ); ?></h2>
	</div>
	<p class="dak-field-hint">
		<?php
		esc_html_e(
			'Automatically pulls your clinic\'s Google rating and up to 5 reviews (Google\'s own "most relevant" picks — not selectable) into the Home page\'s Patient Stories section, refreshed roughly once a day. Requires a Places API key from Google Cloud Console (enable the "Places API" and create an API key under Credentials) and your clinic\'s Place ID (search your business name at the "Place ID Finder" tool in Google\'s Places API documentation).',
			'doctor-ak-portal'
		);
		?>
	</p>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-google-reviews-error" role="alert"></div>
	<div class="dak-alert dak-alert-success dak-hidden" id="dak-google-reviews-success" role="status"></div>

	<div class="dak-field-row">
		<div class="dak-field">
			<label for="dak-google-reviews-place-id"><?php esc_html_e( 'Place ID', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-google-reviews-place-id" value="<?php echo esc_attr( $google_place_id ); ?>" placeholder="ChIJ...">
		</div>
		<div class="dak-field">
			<label for="dak-google-reviews-api-key"><?php esc_html_e( 'Places API key', 'doctor-ak-portal' ); ?></label>
			<input type="password" id="dak-google-reviews-api-key" value="<?php echo esc_attr( $google_api_key ); ?>" autocomplete="off">
		</div>
	</div>

	<button type="button" class="dak-button dak-button-secondary" id="dak-google-reviews-save">
		<?php esc_html_e( 'Save & Refresh', 'doctor-ak-portal' ); ?>
	</button>
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
