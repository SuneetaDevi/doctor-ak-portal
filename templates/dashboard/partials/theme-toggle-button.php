<?php
/**
 * Template: Light/dark theme toggle button, shared by the Admin, Doctor and
 * Patient dashboard sidebars. Its state is driven entirely by the
 * `data-theme` attribute on the ancestor `.dak-dashboard` element (see
 * assets/css/doctor-ak-dashboard.css and assets/js/doctor-ak-theme-toggle.js)
 * — this markup itself takes no variables.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var bool $compact Icon-only styling (topbar) instead of the full labelled
 *                     row (Settings tab). Both variants share the
 *                     `.dak-theme-toggle` class, so JS binds every instance
 *                     on the page regardless of which variant it is.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$compact = ! empty( $compact );
?>
<button type="button" class="dak-theme-toggle<?php echo $compact ? ' dak-icon-button dak-theme-toggle-compact' : ''; ?>" title="<?php esc_attr_e( 'Toggle dark mode', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'doctor-ak-portal' ); ?>">
	<span class="dak-theme-icon dak-theme-icon-sun" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="3.5"/><path d="M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/></svg></span>
	<span class="dak-theme-icon dak-theme-icon-moon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 12.3A6.8 6.8 0 0 1 7.7 3.5a6.8 6.8 0 1 0 8.8 8.8z"/></svg></span>
	<?php if ( ! $compact ) : ?>
		<span class="dak-theme-toggle-label"><?php esc_html_e( 'Dark Mode', 'doctor-ak-portal' ); ?></span>
	<?php endif; ?>
</button>
