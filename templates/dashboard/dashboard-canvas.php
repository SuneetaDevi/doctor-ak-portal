<?php
/**
 * Bare page template for the Admin/Doctor/Patient dashboards — served via
 * Dashboard_Layout::template_include() instead of the active theme's own
 * page template, so none of the theme's header.php/footer.php markup (site
 * nav, footer links, etc.) wraps the dashboard. Still calls wp_head()/
 * wp_footer()/wp_body_open() so enqueued assets, the admin bar, and other
 * plugins keep working as normal.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'dak-dashboard-canvas' ); ?>>
	<?php wp_body_open(); ?>
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
	<?php wp_footer(); ?>
</body>
</html>
