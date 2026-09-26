<?php
/**
 * Template: Topbar action icons (notifications bell), shared by the Admin,
 * Doctor and Patient dashboard topbars. The light/dark toggle is the public
 * site header's own (see templates/site-header.php) — the dashboards no
 * longer have a separate one.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $notifications_url          Same-page URL for the Notifications tab/section. '' if this viewer has none.
 * @var int    $unread_notifications_count Unread notification count, for the bell badge.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notifications_url          = isset( $notifications_url ) ? $notifications_url : '';
$unread_notifications_count = isset( $unread_notifications_count ) ? (int) $unread_notifications_count : 0;
?>
<div class="dak-dashboard-topbar-actions">
	<button type="button" class="dak-public-theme-toggle" data-dak-public-theme-toggle aria-pressed="false" title="<?php esc_attr_e( 'Toggle dark mode', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'doctor-ak-portal' ); ?>">
		<span class="dak-theme-icon dak-theme-icon-sun" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="3.5"/><path d="M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/></svg></span>
		<span class="dak-theme-icon dak-theme-icon-moon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 12.3A6.8 6.8 0 0 1 7.7 3.5a6.8 6.8 0 1 0 8.8 8.8z"/></svg></span>
	</button>
	<?php if ( '' !== $notifications_url ) : ?>
		<a class="dak-icon-button dak-topbar-bell" href="<?php echo esc_url( $notifications_url ); ?>" aria-label="<?php esc_attr_e( 'Notifications', 'doctor-ak-portal' ); ?>">
			<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8a5 5 0 0 1 10 0c0 3.2 1 4.3 1.5 5H3.5C4 12.3 5 11.2 5 8z"/><path d="M8.2 15.5a1.8 1.8 0 0 0 3.6 0"/></svg>
			<?php if ( $unread_notifications_count > 0 ) : ?>
				<span class="dak-topbar-bell-badge"><?php echo esc_html( $unread_notifications_count > 9 ? '9+' : $unread_notifications_count ); ?></span>
			<?php endif; ?>
		</a>
	<?php endif; ?>

</div>
