<?php
/**
 * Template: shared layout for the Terms and Conditions, Privacy Policy and
 * Cancellation Policy pages (served by Policy_Pages::template_include()).
 *
 * Replaces the active theme's page template for these three pages only, so
 * the theme's own header/footer no longer render underneath/above this
 * plugin's Site_Header/Site_Footer (hooked to wp_body_open/wp_footer, which
 * still fire here, along with wp_head() — SEO metadata, analytics and other
 * plugins are unaffected).
 *
 * Layout: breadcrumb, compact title + intro (+ verified effective date),
 * links between the three policies, "On this page" (sidebar on wide
 * screens, collapsible list on small ones), the policy text, and links to
 * the related policies.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_policy_slug   = \DoctorAKPortal\Frontend\Policy_Pages::current_slug();
$dak_policy_stored = '';

if ( have_posts() ) {
	the_post();
	$dak_policy_stored = apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
}

$dak_policy = \DoctorAKPortal\Frontend\Policy_Pages::view_data( $dak_policy_slug, $dak_policy_stored );
$dak_policy_has_toc = count( $dak_policy['toc'] ) > 3;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'dak-dashboard-canvas' ); ?>>
	<a class="dak-policy-skip" href="#dak-policy-content"><?php esc_html_e( 'Skip to content', 'doctor-ak-portal' ); ?></a>
	<?php wp_body_open(); ?>

	<main class="dak-policy" id="dak-policy-content" tabindex="-1">
		<div class="dak-policy-wrap">
			<nav class="dak-policy-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'doctor-ak-portal' ); ?>">
				<ol>
					<li><a href="<?php echo esc_url( $dak_policy['home_url'] ); ?>"><?php esc_html_e( 'Home', 'doctor-ak-portal' ); ?></a></li>
					<li aria-current="page"><?php echo esc_html( $dak_policy['policy']['label'] ); ?></li>
				</ol>
			</nav>

			<header class="dak-policy-head">
				<h1><?php echo esc_html( $dak_policy['policy']['label'] ); ?></h1>
				<p class="dak-policy-intro"><?php echo esc_html( $dak_policy['policy']['intro'] ); ?></p>
				<?php if ( ! empty( $dak_policy['meta'] ) ) : ?>
					<dl class="dak-policy-dates">
						<?php foreach ( $dak_policy['meta'] as $dak_policy_meta_label => $dak_policy_meta_value ) : ?>
							<div><dt><?php echo esc_html( $dak_policy_meta_label ); ?></dt><dd><?php echo esc_html( $dak_policy_meta_value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
			</header>

			<nav class="dak-policy-switch" aria-label="<?php esc_attr_e( 'Policies', 'doctor-ak-portal' ); ?>">
				<ul>
					<?php foreach ( $dak_policy['policies'] as $dak_policy_other_slug => $dak_policy_other ) : ?>
						<li>
							<a href="<?php echo esc_url( home_url( '/' . $dak_policy_other_slug . '/' ) ); ?>"<?php echo $dak_policy_other_slug === $dak_policy['slug'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $dak_policy_other['label'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="dak-policy-layout<?php echo $dak_policy_has_toc ? ' has-toc' : ''; ?>">
				<?php if ( $dak_policy_has_toc ) : ?>
					<aside class="dak-policy-toc" aria-labelledby="dak-policy-toc-title">
						<details class="dak-policy-toc-details" id="dak-policy-toc-details" open>
							<summary id="dak-policy-toc-title"><?php esc_html_e( 'On this page', 'doctor-ak-portal' ); ?></summary>
							<ol>
								<?php foreach ( $dak_policy['toc'] as $dak_policy_toc_item ) : ?>
									<li><a href="#<?php echo esc_attr( $dak_policy_toc_item['id'] ); ?>"><?php echo esc_html( $dak_policy_toc_item['label'] ); ?></a></li>
								<?php endforeach; ?>
							</ol>
						</details>
					</aside>
				<?php endif; ?>

				<article class="dak-policy-body">
					<?php echo $dak_policy['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the page's own content (already through the_content filters), or the plugin's reviewed edited copy of it. ?>
				</article>
			</div>

			<section class="dak-policy-related" aria-labelledby="dak-policy-related-title">
				<h2 id="dak-policy-related-title"><?php esc_html_e( 'Related policies', 'doctor-ak-portal' ); ?></h2>
				<ul>
					<?php foreach ( $dak_policy['policies'] as $dak_policy_other_slug => $dak_policy_other ) : ?>
						<?php if ( $dak_policy_other_slug === $dak_policy['slug'] ) { continue; } ?>
						<li>
							<a href="<?php echo esc_url( home_url( '/' . $dak_policy_other_slug . '/' ) ); ?>">
								<strong><?php echo esc_html( $dak_policy_other['label'] ); ?></strong>
								<span><?php echo esc_html( $dak_policy_other['intro'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>
	</main>

	<script>
		( function () {
			// "On this page" starts collapsed on small screens; on wide screens it's a sidebar.
			var d = document.getElementById( 'dak-policy-toc-details' );
			if ( d && window.matchMedia && window.matchMedia( '(max-width: 1023px)' ).matches ) {
				d.removeAttribute( 'open' );
			}

			// Section links land below the sticky site header, whatever its
			// current height (it differs by screen width and admin bar).
			function setOffset() {
				var h = 0;
				document.querySelectorAll( '.dak-site-header, #wpadminbar' ).forEach( function ( el ) {
					var pos = window.getComputedStyle( el ).position;
					if ( 'sticky' === pos || 'fixed' === pos ) {
						h += el.getBoundingClientRect().height;
					}
				} );
				if ( h > 0 ) {
					document.documentElement.style.setProperty( '--pol-offset', Math.ceil( h + 16 ) + 'px' );
					document.body.style.setProperty( '--pol-offset', Math.ceil( h + 16 ) + 'px' );
				}
			}
			setOffset();
			window.addEventListener( 'load', setOffset );
			window.addEventListener( 'resize', setOffset );
		}() );
	</script>
	<?php wp_footer(); ?>
</body>
</html>
