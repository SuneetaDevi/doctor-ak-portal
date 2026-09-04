<?php
/**
 * Template: Site-wide header, rendered on every front-end page via wp_body_open.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string   $logo_url        Bundled logo URL (assets/images/logo.*), or '' if none was placed there.
 * @var string   $phone           Contact phone number (first clinic location with one on file), or '' if none.
 * @var string   $email           Contact email (first clinic location with one on file), or '' if none.
 * @var string   $address         Clinic address (Settings -> Footer), or '' if not set.
 * @var string   $facebook_url    Facebook page URL, or '' to hide the icon.
 * @var string   $twitter_url     X (Twitter) profile URL, or '' to hide the icon.
 * @var string   $instagram_url   Instagram profile URL, or '' to hide the icon.
 * @var string   $linkedin_url    LinkedIn profile URL, or '' to hide the icon.
 * @var string   $directory_url   URL of the [doctors_directory] page, or '' if not found.
 * @var string   $services_url    URL of the [services_directory] page, or '' if not found.
 * @var string   $videos_url      Home page's "More From Our Clinic" section anchor.
 * @var string   $clinics_url     Home page's "Visit Us" section anchor.
 * @var array    $doctor_specialties All rows from Home_Page::specialties_in_use() — { slug, label, count, url } — real specialties at least one doctor has.
 * @var string   $current_path    Site_Header::current_path() — current request's URL path, for the active-page nav underline.
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

// Matches on path only (ignores query string), so e.g. the doctors
// directory filtered via a mega-menu link (?specialization=...) still
// underlines "Doctors" as the active nav item.
$dak_is_current_page = function ( $url ) use ( $current_path ) {
	if ( '' === $url || '' === $current_path ) {
		return false;
	}

	return untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) === $current_path;
};

$dak_header_icons = array(
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'phone'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h2.3l1 3.3-1.6 1.4a9 9 0 0 0 4.1 4.1l1.4-1.6 3.3 1v2.3c0 .8-.7 1.4-1.5 1.3C8.7 15 5 11.3 4.2 6c-.1-.8.5-1.5 1.3-1.5z"/></svg>',
	'mail'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="15" height="11" rx="1.5"/><path d="M3 5.5l7 5.5 7-5.5"/></svg>',
	'chevron'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.5 7.5l4.5 4.5 4.5-4.5"/></svg>',
	'arrow'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 10h12"/><path d="M11 5.5l4.5 4.5-4.5 4.5"/></svg>',
	'user'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.5 4-5 6.5-5s5.5 1.5 6.5 5"/></svg>',
	'search'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.8" cy="8.8" r="5.3"/><path d="M17 17l-3.8-3.8"/></svg>',
	'home'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 10 3.5l7 6"/><path d="M4.8 8v8.5h10.4V8"/></svg>',
);

// Same body-part glyphs + keyword matching as the home page's "Consult Top
// Doctors Online" tiles (templates/directory/home-page.php) — duplicated
// rather than shared since it's a small, self-contained lookup and the two
// templates otherwise have nothing in common to justify a shared include.
$dak_header_specialty_icons = array(
	'heart'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 16.2S3.8 12.4 3.8 8.1A3.1 3.1 0 0 1 10 6.3a3.1 3.1 0 0 1 6.2 1.8c0 4.3-6.2 8.1-6.2 8.1z"/></svg>',
	'brain'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9.2 4.2a2 2 0 0 0-3.4 1.2 2 2 0 0 0-.9 3.3 2 2 0 0 0 1 3.2 2 2 0 0 0 3.3 1.4z"/><path d="M10.8 4.2a2 2 0 0 1 3.4 1.2 2 2 0 0 1 .9 3.3 2 2 0 0 1-1 3.2 2 2 0 0 1-3.3 1.4z"/><path d="M10 4.2v11.6"/></svg>',
	'stomach'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 3.8v3.9c0 2.3 1.5 3.3 3.2 3.6 1.9.3 2.9 1.2 2.9 2.6a2.6 2.6 0 0 1-5.2.2"/><path d="M5.6 3.8h3.8"/></svg>',
	'tooth'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.4 3.8c1.1 0 1.4.6 3.6.6s2.5-.6 3.6-.6c.9 0 1.4.9 1.4 2.3 0 1.8-.9 2.7-1.3 4.8-.3 1.7-.5 4.2-1.6 4.2-.9 0-.9-2.1-1.2-3.6-.2-.8-.5-1.2-.9-1.2s-.7.4-.9 1.2c-.3 1.5-.3 3.6-1.2 3.6-1.1 0-1.3-2.5-1.6-4.2C5.9 8.8 5 7.9 5 6.1c0-1.4.5-2.3 1.4-2.3z"/></svg>',
	'eye'         => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 10S5.6 5.5 10 5.5 17.5 10 17.5 10 14.4 14.5 10 14.5 2.5 10 2.5 10z"/><circle cx="10" cy="10" r="2.2"/></svg>',
	'bone'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13l6-6"/><circle cx="5.6" cy="14.4" r="2.1"/><circle cx="14.4" cy="5.6" r="2.1"/></svg>',
	'lungs'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3.5v6.2"/><path d="M10 9.7c0-1.2-.9-2-2-2-2 0-3.5 2.6-3.5 5.4 0 2 .6 3.4 1.8 3.4 1.4 0 3.7-1.3 3.7-3.2z"/><path d="M10 9.7c0-1.2.9-2 2-2 2 0 3.5 2.6 3.5 5.4 0 2-.6 3.4-1.8 3.4-1.4 0-3.7-1.3-3.7-3.2z"/></svg>',
	'baby'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="6.8" r="3.4"/><path d="M8.7 6.2h.01M11.3 6.2h.01"/><path d="M4.6 16.8c.8-2.7 2.9-4.3 5.4-4.3s4.6 1.6 5.4 4.3"/></svg>',
	'skin'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3.4s4.4 4.6 4.4 7.3a4.4 4.4 0 0 1-8.8 0C5.6 8 10 3.4 10 3.4z"/><path d="M8.4 11.2h.01M10.6 12.8h.01"/></svg>',
	'kidney'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.2 4.2c2.2 0 3.8 2.2 3.8 5.6s-1.6 6-3.8 6-3.4-2-3.4-5.7 1.2-5.9 3.4-5.9z"/><path d="M12 9.8h3.6"/></svg>',
	'stethoscope' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.6 3.4v3.9a3 3 0 0 0 6 0V3.4"/><path d="M4.2 3.4h2.6M10.4 3.4H13"/><path d="M8.6 10.3v1.9a3.6 3.6 0 0 0 7.2 0v-1.4"/><circle cx="15.8" cy="9" r="1.6"/></svg>',
);

$dak_header_specialty_icon = function ( $slug ) use ( $dak_header_specialty_icons ) {
	$matches = array(
		'cardio'    => 'heart',
		'neuro'     => 'brain',
		'psych'     => 'brain',
		'gastro'    => 'stomach',
		'dent'      => 'tooth',
		'ophthal'   => 'eye',
		'orthop'    => 'bone',
		'rheumat'   => 'bone',
		'pulmon'    => 'lungs',
		'pediatric' => 'baby',
		'obstetric' => 'baby',
		'gyneco'    => 'baby',
		'dermat'    => 'skin',
		'nephro'    => 'kidney',
		'urolog'    => 'kidney',
	);

	foreach ( $matches as $dak_needle => $dak_icon ) {
		if ( false !== strpos( $slug, $dak_needle ) ) {
			return $dak_header_specialty_icons[ $dak_icon ];
		}
	}

	return $dak_header_specialty_icons['stethoscope'];
};

$dak_header_social_icons = array(
	'facebook'  => array( $facebook_url, '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M12.5 6.5H11c-.3 0-.5.2-.5.5v1.5H12.5l-.3 2H10.5V17h-2v-6.5H7V8.5h1.5V7c0-1.7 1.3-3 3-3H12.5v2.5z"/></svg>' ),
	'twitter'   => array( $twitter_url, '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 4h1.9l-4.2 4.8L18 16h-3.9l-3-4-3.5 4H5.7l4.5-5.1L4.5 4h4l2.7 3.6L15.5 4z"/></svg>' ),
	'instagram' => array( $instagram_url, '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="14" height="14" rx="4"/><circle cx="10" cy="10" r="3.2"/><circle cx="14" cy="6" r="0.8" fill="currentColor" stroke="none"/></svg>' ),
	'linkedin'  => array( $linkedin_url, '<svg viewBox="0 0 20 20" fill="currentColor"><rect x="3" y="8" width="3" height="9"/><circle cx="4.5" cy="4.5" r="1.6"/><path d="M9 8h3v1.4c.5-.9 1.5-1.6 3-1.6 2.3 0 3 1.4 3 3.7V17h-3v-4.8c0-1.1-.4-1.9-1.4-1.9-1.1 0-1.6.7-1.6 1.9V17H9V8z"/></svg>' ),
);

$dak_header_has_utility_bar = $address || $phone || $email || $facebook_url || $twitter_url || $instagram_url || $linkedin_url;
?>
<header class="dak-portal dak-site-header">
	<?php if ( $dak_header_has_utility_bar ) : ?>
		<div class="dak-site-header-utility">
			<div class="dak-site-header-utility-inner">
				<div class="dak-site-header-utility-contact">
					<?php if ( $address ) : ?>
						<span class="dak-site-header-utility-item">
							<span aria-hidden="true"><?php echo $dak_header_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php echo esc_html( $address ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $phone ) : ?>
						<a class="dak-site-header-utility-item" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">
							<span aria-hidden="true"><?php echo $dak_header_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php echo esc_html( $phone ); ?>
						</a>
					<?php endif; ?>

					<?php if ( $email ) : ?>
						<a class="dak-site-header-utility-item" href="mailto:<?php echo esc_attr( $email ); ?>">
							<span aria-hidden="true"><?php echo $dak_header_icons['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php echo esc_html( $email ); ?>
						</a>
					<?php endif; ?>
				</div>

				<?php if ( $facebook_url || $twitter_url || $instagram_url || $linkedin_url ) : ?>
					<div class="dak-site-header-utility-social">
						<?php foreach ( $dak_header_social_icons as $dak_network => $dak_social ) : ?>
							<?php list( $dak_social_url, $dak_social_icon ) = $dak_social; ?>
							<?php if ( $dak_social_url ) : ?>
								<a href="<?php echo esc_url( $dak_social_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( ucfirst( $dak_network ) ); ?>">
									<?php echo $dak_social_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

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
			<ul class="dak-site-header-menu">
				<?php if ( $directory_url ) : ?>
					<li class="menu-item menu-item-has-children dak-site-header-doctors-item<?php echo $dak_is_current_page( $directory_url ) ? ' dak-site-header-menu-current' : ''; ?>">
						<a href="<?php echo esc_url( $directory_url ); ?>">
							<?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?>
							<span class="dak-site-header-menu-caret" aria-hidden="true"><?php echo $dak_header_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</a>

						<?php if ( ! empty( $doctor_specialties ) ) : ?>
							<div class="sub-menu dak-site-header-mega">
								<div class="dak-site-header-mega-panel">
									<form class="dak-site-header-mega-search" method="get" action="<?php echo esc_url( $directory_url ); ?>">
										<span aria-hidden="true"><?php echo $dak_header_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<input type="text" name="s" id="dak-site-header-mega-search-input" placeholder="<?php esc_attr_e( 'Search doctors, specialties, symptoms…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search doctors, specialties, symptoms', 'doctor-ak-portal' ); ?>" autocomplete="off">
									</form>
									<p class="dak-site-header-mega-no-results dak-hidden"><?php esc_html_e( 'No matches — press Enter to search the full doctors directory instead.', 'doctor-ak-portal' ); ?></p>

									<div class="dak-site-header-mega-body">
										<span class="dak-site-header-mega-heading"><?php esc_html_e( 'By Speciality', 'doctor-ak-portal' ); ?></span>
										<div class="dak-site-header-mega-cards">
											<?php foreach ( $doctor_specialties as $dak_specialty ) : ?>
												<a class="dak-site-header-mega-card" href="<?php echo esc_url( $dak_specialty['url'] ); ?>">
													<span class="dak-site-header-mega-card-icon" aria-hidden="true"><?php echo $dak_header_specialty_icon( $dak_specialty['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
													<span class="dak-site-header-mega-card-body">
														<strong><?php echo esc_html( $dak_specialty['label'] ); ?></strong>
														<span>
															<?php
															echo esc_html(
																sprintf(
																	/* translators: %d: number of doctors with this specialty. */
																	_n( '%d specialist', '%d specialists', $dak_specialty['count'], 'doctor-ak-portal' ),
																	$dak_specialty['count']
																)
															);
															?>
														</span>
													</span>
												</a>
											<?php endforeach; ?>
										</div>
									</div>

									<div class="dak-site-header-mega-footer">
										<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $directory_url ); ?>">
											<?php esc_html_e( 'View All Doctors', 'doctor-ak-portal' ); ?>
											<span aria-hidden="true"><?php echo $dak_header_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</a>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</li>
				<?php endif; ?>

				<?php if ( $services_url ) : ?>
					<li class="menu-item<?php echo $dak_is_current_page( $services_url ) ? ' dak-site-header-menu-current' : ''; ?>"><a href="<?php echo esc_url( $services_url ); ?>"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>

				<li class="menu-item"><a href="<?php echo esc_url( $clinics_url ); ?>"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></a></li>
				<li class="menu-item"><a href="<?php echo esc_url( $videos_url ); ?>"><?php esc_html_e( 'Videos', 'doctor-ak-portal' ); ?></a></li>

				<!--
					Gallery/Blogs: nav items only, by request — neither has a
					real page or content behind it yet (no photo-gallery
					feature exists in the plugin, and no WordPress "posts
					page" is configured), so these intentionally go nowhere
					(href="#") rather than link to a fabricated destination.
					Point them at a real URL once there's something to show.
				-->
				<li class="menu-item"><a href="#"><?php esc_html_e( 'Gallery', 'doctor-ak-portal' ); ?></a></li>
				<li class="menu-item"><a href="#"><?php esc_html_e( 'Blogs', 'doctor-ak-portal' ); ?></a></li>
			</ul>
		</nav>

		<div class="dak-site-header-auth">
			<button type="button" class="dak-public-theme-toggle" data-dak-public-theme-toggle aria-pressed="false" title="<?php esc_attr_e( 'Toggle dark mode', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'doctor-ak-portal' ); ?>">
				<span class="dak-theme-icon dak-theme-icon-sun" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="3.5"/><path d="M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/></svg></span>
				<span class="dak-theme-icon dak-theme-icon-moon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 12.3A6.8 6.8 0 0 1 7.7 3.5a6.8 6.8 0 1 0 8.8 8.8z"/></svg></span>
			</button>

			<button type="button" class="dak-button dak-button-primary dak-site-header-cta" data-dak-book-appointment>
				<?php esc_html_e( 'Schedule Appointment', 'doctor-ak-portal' ); ?>
			</button>

			<?php if ( $is_logged_in ) : ?>
				<button type="button" class="dak-site-header-account" id="dak-site-header-account" aria-haspopup="true" aria-expanded="false" aria-label="<?php echo esc_attr( $display_name ); ?>">
					<img src="<?php echo esc_url( $user_avatar_url ); ?>" alt="" class="dak-site-header-avatar">
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
					<span class="dak-site-header-account-icon" aria-hidden="true"><?php echo $dak_header_icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php esc_html_e( 'Login / Register', 'doctor-ak-portal' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</header>
