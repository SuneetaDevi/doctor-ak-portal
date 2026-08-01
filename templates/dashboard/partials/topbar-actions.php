<?php
/**
 * Template: Topbar action icons (theme toggle + notifications bell), shared
 * by the Admin, Doctor and Patient dashboard topbars.
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
	<?php
	echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own theme-toggle-button.php template, which escapes its own output.
		'dashboard/partials/theme-toggle-button.php',
		array( 'compact' => true )
	);
	?>

	<?php if ( '' !== $notifications_url ) : ?>
		<a class="dak-icon-button dak-topbar-bell" href="<?php echo esc_url( $notifications_url ); ?>" aria-label="<?php esc_attr_e( 'Notifications', 'doctor-ak-portal' ); ?>">
			<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8a5 5 0 0 1 10 0c0 3.2 1 4.3 1.5 5H3.5C4 12.3 5 11.2 5 8z"/><path d="M8.2 15.5a1.8 1.8 0 0 0 3.6 0"/></svg>
			<?php if ( $unread_notifications_count > 0 ) : ?>
				<span class="dak-topbar-bell-badge"><?php echo esc_html( $unread_notifications_count > 9 ? '9+' : $unread_notifications_count ); ?></span>
			<?php endif; ?>
		</a>
	<?php endif; ?>
</div>
