<?php
/**
 * Template: Site-wide footer, rendered on every front-end page via wp_footer.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $logo_url          Bundled logo URL (assets/images/logo.*), or '' if none was placed there.
 * @var string $brand_domain      Site_Footer::BRAND_DOMAIN — shown in place of the logo when none is uploaded.
 * @var string $brand_tagline     Short tagline shown under the brand — same one used on the home hero.
 * @var string $description       Clinic description paragraph.
 * @var string $phone             Booking phone number.
 * @var string $facebook_url      Facebook page URL, or '' to hide the icon.
 * @var string $twitter_url       X (Twitter) profile URL, or '' to hide the icon.
 * @var string $instagram_url     Instagram profile URL, or '' to hide the icon.
 * @var string $linkedin_url      LinkedIn profile URL, or '' to hide the icon.
 * @var array  $doctors           Site_Footer::doctors_for_footer() rows — { name, url } — a handful of real, active doctors.
 * @var string $directory_url     URL of the [doctors_directory] page, or '' if not found — the "Doctors" column's "All Doctors" link.
 * @var array  $services          Site_Footer::services_for_footer() rows — { name, url } — real, bookable services.
 * @var array  $clinics_by_city   Site_Footer::clinics_by_city_for_footer() rows — { label, url } — one per distinct clinic city.
 * @var array  $policy_links      Site_Footer::policy_links() rows — { label, url } — legal pages found by title, only those that exist.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_footer_social_icons = array(
	'facebook'  => array( $facebook_url, '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M12.5 6.5H11c-.3 0-.5.2-.5.5v1.5H12.5l-.3 2H10.5V17h-2v-6.5H7V8.5h1.5V7c0-1.7 1.3-3 3-3H12.5v2.5z"/></svg>' ),
	'twitter'   => array( $twitter_url, '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 4h1.9l-4.2 4.8L18 16h-3.9l-3-4-3.5 4H5.7l4.5-5.1L4.5 4h4l2.7 3.6L15.5 4z"/></svg>' ),
	'instagram' => array( $instagram_url, '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="14" height="14" rx="4"/><circle cx="10" cy="10" r="3.2"/><circle cx="14" cy="6" r="0.8" fill="currentColor" stroke="none"/></svg>' ),
	'linkedin'  => array( $linkedin_url, '<svg viewBox="0 0 20 20" fill="currentColor"><rect x="3" y="8" width="3" height="9"/><circle cx="4.5" cy="4.5" r="1.6"/><path d="M9 8h3v1.4c.5-.9 1.5-1.6 3-1.6 2.3 0 3 1.4 3 3.7V17h-3v-4.8c0-1.1-.4-1.9-1.4-1.9-1.1 0-1.6.7-1.6 1.9V17H9V8z"/></svg>' ),
);
?>
<footer class="dak-portal dak-site-footer">
	<div class="dak-site-footer-inner">
		<div class="dak-site-footer-col dak-site-footer-brand">
			<a class="dak-site-footer-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_domain ); ?>">
				<?php else : ?>
					<span class="dak-site-footer-logo-text"><?php echo esc_html( $brand_domain ); ?></span>
				<?php endif; ?>
			</a>

			<?php if ( $brand_tagline ) : ?>
				<p class="dak-site-footer-tagline"><?php echo esc_html( $brand_tagline ); ?></p>
			<?php endif; ?>

			<?php if ( $description ) : ?>
				<p class="dak-site-footer-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>

			<div class="dak-site-footer-social">
				<?php foreach ( $dak_footer_social_icons as $network => $data ) : ?>
					<?php list( $url, $icon ) = $data; ?>
					<?php if ( $url ) : ?>
						<a class="dak-site-footer-social-icon" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( ucfirst( $network ) ); ?>">
							<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<?php if ( $phone ) : ?>
				<div class="dak-site-footer-phone">
					<span class="dak-site-footer-phone-icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4.5c0-.6.4-1 1-1h2.2c.5 0 .9.3 1 .8l.7 2.8c.1.4 0 .8-.3 1.1L7.2 9.5c.9 2 2.5 3.6 4.5 4.5l1.3-1.4c.3-.3.7-.4 1.1-.3l2.8.7c.5.1.8.5.8 1v2.2c0 .6-.4 1-1 1h-1C9.4 16.7 3.3 10.6 3.3 3.5v-1"/></svg>
					</span>
					<span>
						<strong><?php esc_html_e( 'For Booking', 'doctor-ak-portal' ); ?></strong><br>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
					</span>
				</div>
			<?php endif; ?>
		</div>

		<div class="dak-site-footer-col">
			<h3><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></h3>
			<ul class="dak-site-footer-menu">
				<?php if ( $directory_url ) : ?>
					<li class="menu-item dak-site-footer-menu-highlight"><a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'All Doctors', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php foreach ( $doctors as $dak_doctor ) : ?>
					<li class="menu-item"><a href="<?php echo esc_url( $dak_doctor['url'] ); ?>"><?php echo esc_html( $dak_doctor['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="dak-site-footer-col">
			<h3><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h3>
			<?php if ( empty( $services ) ) : ?>
				<p class="dak-site-footer-empty"><?php esc_html_e( 'Coming soon.', 'doctor-ak-portal' ); ?></p>
			<?php else : ?>
				<ul class="dak-site-footer-menu">
					<?php foreach ( $services as $dak_service ) : ?>
						<li class="menu-item"><a href="<?php echo esc_url( $dak_service['url'] ); ?>"><?php echo esc_html( $dak_service['name'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="dak-site-footer-col">
			<h3><?php esc_html_e( 'Clinics / Locations', 'doctor-ak-portal' ); ?></h3>
			<?php if ( empty( $clinics_by_city ) ) : ?>
				<p class="dak-site-footer-empty"><?php esc_html_e( 'Coming soon.', 'doctor-ak-portal' ); ?></p>
			<?php else : ?>
				<ul class="dak-site-footer-menu">
					<?php foreach ( $clinics_by_city as $dak_city ) : ?>
						<li class="menu-item"><a href="<?php echo esc_url( $dak_city['url'] ); ?>"><?php echo esc_html( $dak_city['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="dak-site-footer-bottom">
		<?php if ( ! empty( $policy_links ) ) : ?>
			<ul class="dak-site-footer-policy-links">
				<?php foreach ( $policy_links as $dak_policy_link ) : ?>
					<li><a href="<?php echo esc_url( $dak_policy_link['url'] ); ?>"><?php echo esc_html( $dak_policy_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: brand domain, e.g. "drakhlana.com". */
					__( '%s – All Rights Reserved', 'doctor-ak-portal' ),
					$brand_domain
				)
			);
			?>
		</p>
	</div>
</footer>
