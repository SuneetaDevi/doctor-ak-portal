<?php
/**
 * Template: Site-wide footer, rendered on every front-end page via wp_footer.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $logo_url      Bundled logo URL (assets/images/logo.*), or '' if none was placed there.
 * @var string $brand_domain  Site_Footer::BRAND_DOMAIN — shown in place of the logo when none is uploaded.
 * @var string $description   Clinic description paragraph.
 * @var string $phone         Booking phone number.
 * @var string $email         Contact email, see Site_Footer::primary_email() — or '' if none on file.
 * @var string $address       Postal address, see Site_Footer::primary_address() — or '' if none on file.
 * @var string $facebook_url  Facebook page URL, or '' to hide the icon.
 * @var string $twitter_url   X (Twitter) profile URL, or '' to hide the icon.
 * @var string $instagram_url Instagram profile URL, or '' to hide the icon.
 * @var string $linkedin_url  LinkedIn profile URL, or '' to hide the icon.
 * @var array  $quick_links   Site_Footer::quick_links() rows — { label, url } — one per major site section, numbered in the template.
 * @var array  $policy_links  Site_Footer::policy_links() rows — { label, url } — legal pages found by title, only those that exist.
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

$dak_numbered_quick_links = array();
foreach ( $quick_links as $dak_link_index => $dak_link ) {
	$dak_link['number']         = $dak_link_index + 1;
	$dak_numbered_quick_links[] = $dak_link;
}
$dak_footer_link_columns = array_chunk( $dak_numbered_quick_links, (int) ceil( count( $dak_numbered_quick_links ) / 2 ) );
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

			<?php if ( $description ) : ?>
				<p class="dak-site-footer-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>

			<form class="dak-site-footer-subscribe" data-dak-coming-soon="<?php esc_attr_e( 'Email updates are launching soon.', 'doctor-ak-portal' ); ?>">
				<input type="email" placeholder="<?php esc_attr_e( 'Email Address', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Email Address', 'doctor-ak-portal' ); ?>">
				<button type="submit"><?php esc_html_e( 'Subscribe', 'doctor-ak-portal' ); ?></button>
			</form>

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
		</div>

		<?php foreach ( $dak_footer_link_columns as $dak_column_links ) : ?>
			<div class="dak-site-footer-col dak-site-footer-links-col">
				<ul class="dak-site-footer-menu">
					<?php foreach ( $dak_column_links as $dak_link ) : ?>
						<li class="menu-item">
							<span class="dak-site-footer-menu-index"><?php echo esc_html( sprintf( '%02d', $dak_link['number'] ) ); ?></span>
							<a href="<?php echo esc_url( $dak_link['url'] ); ?>"><?php echo esc_html( $dak_link['label'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>

		<div class="dak-site-footer-col dak-site-footer-contact-col">
			<?php if ( $address ) : ?>
				<div class="dak-site-footer-contact-group">
					<h3><?php esc_html_e( 'Address', 'doctor-ak-portal' ); ?></h3>
					<p><?php echo esc_html( $address ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $phone || $email ) : ?>
				<div class="dak-site-footer-contact-group">
					<h3><?php esc_html_e( 'Contact', 'doctor-ak-portal' ); ?></h3>
					<?php if ( $phone ) : ?>
						<a class="dak-site-footer-contact-line" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">
							<span aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4.5c0-.6.4-1 1-1h2.2c.5 0 .9.3 1 .8l.7 2.8c.1.4 0 .8-.3 1.1L7.2 9.5c.9 2 2.5 3.6 4.5 4.5l1.3-1.4c.3-.3.7-.4 1.1-.3l2.8.7c.5.1.8.5.8 1v2.2c0 .6-.4 1-1 1h-1C9.4 16.7 3.3 10.6 3.3 3.5v-1"/></svg></span>
							<?php echo esc_html( $phone ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<a class="dak-site-footer-contact-line" href="mailto:<?php echo esc_attr( $email ); ?>">
							<span aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="15" height="11" rx="1.5"/><path d="M3 5.5l7 5.5 7-5.5"/></svg></span>
							<?php echo esc_html( $email ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="dak-site-footer-bottom">
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
		<?php if ( ! empty( $policy_links ) ) : ?>
			<ul class="dak-site-footer-policy-links">
				<?php foreach ( $policy_links as $dak_policy_index => $dak_policy_link ) : ?>
					<?php if ( $dak_policy_index > 0 ) : ?>
						<li class="dak-site-footer-policy-sep" aria-hidden="true">|</li>
					<?php endif; ?>
					<li><a href="<?php echo esc_url( $dak_policy_link['url'] ); ?>"><?php echo esc_html( $dak_policy_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</footer>
