<?php
/**
 * Template: the bottom of every dashboard sidebar — one quiet "Help &
 * support" entry and "Sign out". The Help entry replaces the floating
 * WhatsApp/chatbot launchers on dashboard pages (hidden there by
 * doctor-ak-dashboard-ui.css so they can't cover navigation, save buttons
 * or row actions); it points at the same Contact page the public site
 * uses, where those support widgets are still available.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $logout_url Sign-out URL.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_support_url = \DoctorAKPortal\Frontend\Dashboard_Layout::support_url();
?>
<div class="dak-sidebar-footer">
	<a class="dak-sidebar-footer-link" href="<?php echo esc_url( $dak_support_url ); ?>">
		<span class="dak-nav-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M7.9 7.7a2.2 2.2 0 0 1 4.2.9c0 1.5-2.1 1.9-2.1 3.1"/><path d="M10 14.3h.01"/></svg></span>
		<span><?php esc_html_e( 'Help & support', 'doctor-ak-portal' ); ?></span>
	</a>
	<a class="dak-sidebar-footer-link dak-sidebar-logout" href="<?php echo esc_url( $logout_url ); ?>">
		<span class="dak-nav-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4H5a1.5 1.5 0 0 0-1.5 1.5v9A1.5 1.5 0 0 0 5 16h3"/><path d="M13 13.5 16.5 10 13 6.5"/><path d="M16.5 10H8"/></svg></span>
		<span><?php esc_html_e( 'Sign out', 'doctor-ak-portal' ); ?></span>
	</a>
</div>
