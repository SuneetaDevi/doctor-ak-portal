<?php
/**
 * Template: Site-wide header, rendered on every front-end page via wp_body_open.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string   $menu_location Registered nav menu location slug.
 * @var string   $logo_url        Bundled logo URL (assets/images/logo.*), or '' if none was placed there.
 * @var bool     $is_logged_in    Whether a user is currently logged in.
 * @var \WP_User $user            Current user (id 0 when logged out).
 * @var string   $user_avatar_url Logged-in user's uploaded profile picture, or a default avatar if none set.
 * @var string   $dashboard_url Logged-in user's dashboard URL.
 * @var string   $profile_url   Logged-in user's Edit Profile URL.
 * @var string   $login_url     Login page URL (also links to Register from there).
 * @var string   $logout_url    Nonce-protected logout URL.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display_name = $is_logged_in ? ( $user->first_name ? $user->first_name : $user->display_name ) : '';
?>
<header class="dak-portal dak-site-header">
	<div class="dak-site-header-inner">
		<a class="dak-site-header-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php elseif ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="dak-site-header-logo-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<?php endif; ?>
		</a>

		<button type="button" class="dak-site-header-toggle" id="dak-site-header-toggle" aria-label="<?php esc_attr_e( 'Toggle menu', 'doctor-ak-portal' ); ?>" aria-expanded="false" aria-controls="dak-site-header-nav">
			<span></span><span></span><span></span>
		</button>

		<nav class="dak-site-header-nav" id="dak-site-header-nav">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => $menu_location,
					'container'      => false,
					'menu_class'     => 'dak-site-header-menu',
					'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
					'fallback_cb'    => array( '\\DoctorAKPortal\\Frontend\\Site_Header', 'render_fallback_menu' ),
				)
			);
			?>
		</nav>

		<div class="dak-site-header-auth">
			<button type="button" class="dak-public-theme-toggle" data-dak-public-theme-toggle aria-pressed="false" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'doctor-ak-portal' ); ?>">
				<span class="dak-public-theme-toggle-thumb" aria-hidden="true"></span>
			</button>

			<button type="button" class="dak-button dak-button-primary dak-site-header-cta" data-dak-book-appointment>
				<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
			</button>

			<?php if ( $is_logged_in ) : ?>
				<button type="button" class="dak-site-header-account" id="dak-site-header-account" aria-haspopup="true" aria-expanded="false">
					<img src="<?php echo esc_url( $user_avatar_url ); ?>" alt="" class="dak-site-header-avatar">
					<span class="dak-site-header-account-name"><?php echo esc_html( $display_name ); ?></span>
				</button>
				<div class="dak-site-header-account-menu" id="dak-site-header-account-menu">
					<?php if ( $dashboard_url ) : ?>
						<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Dashboard', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
					<?php if ( $profile_url ) : ?>
						<a href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Edit Profile', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Logout', 'doctor-ak-portal' ); ?></a>
				</div>
			<?php elseif ( $login_url ) : ?>
				<a class="dak-site-header-account-link" href="<?php echo esc_url( $login_url ); ?>">
					<span class="dak-site-header-account-icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>
					</span>
					<?php esc_html_e( 'Login / Register', 'doctor-ak-portal' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</header>
