<?php
/**
 * Template: Topbar action icons (theme toggle + notifications bell + profile
 * menu), shared by the Admin, Doctor and Patient dashboard topbars.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string    $notifications_url          Same-page URL for the Notifications tab/section. '' if this viewer has none.
 * @var int       $unread_notifications_count Unread notification count, for the bell badge.
 * @var \WP_User  $user                       Currently logged-in user.
 * @var string    $avatar_url                 Uploaded profile picture URL, or '' to fall back to initials.
 * @var string    $profile_url                "Edit Profile" link.
 * @var string    $logout_url                 Nonce-protected logout link.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notifications_url          = isset( $notifications_url ) ? $notifications_url : '';
$unread_notifications_count = isset( $unread_notifications_count ) ? (int) $unread_notifications_count : 0;
$avatar_url                 = isset( $avatar_url ) ? $avatar_url : '';
$profile_url                = isset( $profile_url ) ? $profile_url : '';
$logout_url                 = isset( $logout_url ) ? $logout_url : '';
$dak_display_name           = isset( $user ) && $user instanceof \WP_User ? $user->display_name : '';
$dak_initials                = '' !== $dak_display_name ? mb_strtoupper( mb_substr( $dak_display_name, 0, 1 ) ) : '?';
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

	<?php if ( '' !== $dak_display_name ) : ?>
		<div class="dak-topbar-profile" id="dak-topbar-profile">
			<button type="button" class="dak-topbar-profile-trigger" id="dak-topbar-profile-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="dak-topbar-profile-menu" aria-label="<?php esc_attr_e( 'Account menu', 'doctor-ak-portal' ); ?>">
				<span class="dak-avatar dak-avatar-sm">
					<?php if ( '' !== $avatar_url ) : ?>
						<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
					<?php else : ?>
						<?php echo esc_html( $dak_initials ); ?>
					<?php endif; ?>
				</span>
			</button>

			<div class="dak-topbar-profile-menu" id="dak-topbar-profile-menu" role="menu">
				<div class="dak-topbar-profile-menu-name"><?php echo esc_html( $dak_display_name ); ?></div>
				<?php if ( '' !== $profile_url ) : ?>
					<a class="dak-topbar-profile-menu-item" role="menuitem" href="<?php echo esc_url( $profile_url ); ?>">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>
						<?php esc_html_e( 'Edit Profile', 'doctor-ak-portal' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( '' !== $logout_url ) : ?>
					<a class="dak-topbar-profile-menu-item dak-topbar-profile-menu-item-danger" role="menuitem" href="<?php echo esc_url( $logout_url ); ?>">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 17H4a1.5 1.5 0 0 1-1.5-1.5v-11A1.5 1.5 0 0 1 4 3h3.5"/><path d="M13 14l4-4-4-4"/><path d="M17 10H7.5"/></svg>
						<?php esc_html_e( 'Logout', 'doctor-ak-portal' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
