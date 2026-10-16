<?php
/**
 * Template partial: the quiet brand panel beside the account forms (login,
 * registration, password recovery) — logo, a small illustration and one
 * short message. Hidden on narrow screens so the form comes first (see
 * doctor-ak-account.css).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $title Short panel heading.
 * @var string $text  One supporting sentence.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_logo_url = \DoctorAKPortal\Frontend\Site_Footer::bundled_logo_url();
$dak_site     = (string) wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
?>
<aside class="dak-acct-aside" aria-label="<?php esc_attr_e( 'About your account', 'doctor-ak-portal' ); ?>">
	<div class="dak-acct-aside-inner">
		<div class="dak-acct-brand">
			<?php if ( '' !== $dak_logo_url ) : ?>
				<img src="<?php echo esc_url( $dak_logo_url ); ?>" alt="" class="dak-acct-brand-logo">
			<?php endif; ?>
			<span><?php echo esc_html( '' !== $dak_site ? $dak_site : 'Doctor AK' ); ?></span>
		</div>

		<svg class="dak-acct-illustration" viewBox="0 0 220 150" fill="none" aria-hidden="true" focusable="false">
			<rect x="34" y="22" width="132" height="112" rx="14" style="fill:var(--dak-bg-card,#fff)" stroke="currentColor" stroke-opacity=".28" stroke-width="2"/>
			<path d="M34 50h132" stroke="currentColor" stroke-opacity=".28" stroke-width="2"/>
			<rect x="62" y="12" width="8" height="22" rx="4" fill="currentColor" fill-opacity=".55"/>
			<rect x="130" y="12" width="8" height="22" rx="4" fill="currentColor" fill-opacity=".55"/>
			<g fill="currentColor" fill-opacity=".14">
				<rect x="52" y="64" width="18" height="14" rx="4"/><rect x="78" y="64" width="18" height="14" rx="4"/><rect x="104" y="64" width="18" height="14" rx="4"/><rect x="130" y="64" width="18" height="14" rx="4"/>
				<rect x="52" y="88" width="18" height="14" rx="4"/><rect x="104" y="88" width="18" height="14" rx="4"/><rect x="130" y="88" width="18" height="14" rx="4"/>
				<rect x="52" y="112" width="18" height="10" rx="4"/><rect x="78" y="112" width="18" height="10" rx="4"/>
			</g>
			<rect x="78" y="88" width="18" height="14" rx="4" fill="currentColor" fill-opacity=".85"/>
			<circle cx="168" cy="112" r="26" style="fill:var(--dak-bg-card,#fff)" stroke="currentColor" stroke-opacity=".28" stroke-width="2"/>
			<path d="M168 100v24M156 112h24" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
		</svg>

		<p class="dak-acct-aside-title"><?php echo esc_html( $title ); ?></p>
		<?php if ( ! empty( $text ) ) : ?>
			<p class="dak-acct-aside-text"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>
	</div>
</aside>
