<?php
/**
 * Template: Services directory for the [services_directory] shortcode —
 * search, category filter, a live result count and a responsive card grid
 * with "Show more". Every eligible service is rendered here, so search and
 * category filtering always cover the full list (see
 * doctor-ak-services-directory.js), not only the cards on screen.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $services_html Pre-rendered directory/service-directory-card.php output, alphabetical.
 * @var array    $categories    { slug, label, count } in Service_Categories order, only categories with services.
 * @var int      $total         Number of listed services.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_icon = function ( $paths ) {
	return '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};

$dak_svc_strings = array(
	/* translators: 1: number shown, 2: number matching. */
	'showing'    => __( 'Showing %1$s of %2$s services', 'doctor-ak-portal' ),
	/* translators: %s: number of services. */
	'showingAll' => __( 'Showing all %s services', 'doctor-ak-portal' ),
	'showingOne' => __( 'Showing 1 service', 'doctor-ak-portal' ),
	'none'       => __( 'No services match', 'doctor-ak-portal' ),
	/* translators: 1: category, 2: count sentence. */
	'inCategory' => __( '%1$s — %2$s', 'doctor-ak-portal' ),
	/* translators: %s: how many more will be shown. */
	'more'       => __( 'Show %s more', 'doctor-ak-portal' ),
);
?>
<div class="dak-portal dak-dir dak-dir-services" data-dak-dir-services data-strings="<?php echo esc_attr( wp_json_encode( $dak_svc_strings ) ); ?>">
	<header class="dak-dir-intro">
		<div class="dak-dir-intro-text">
			<h1 class="dak-dir-title"><?php esc_html_e( 'Our services', 'doctor-ak-portal' ); ?></h1>
			<p class="dak-dir-lead"><?php esc_html_e( 'Procedures, tests and care we offer. Open a service to see the doctors who provide it, their clinics and prices.', 'doctor-ak-portal' ); ?></p>
		</div>

		<?php if ( $total > 0 ) : ?>
			<form class="dak-dir-search" role="search" data-dak-dir-search>
				<label class="dak-dir-sr" for="dak-svc-q"><?php esc_html_e( 'Search services', 'doctor-ak-portal' ); ?></label>
				<span class="dak-dir-search-icon"><?php echo $dak_icon( '<circle cx="8.8" cy="8.8" r="5.3"/><path d="M17 17l-3.8-3.8"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<input type="search" id="dak-svc-q" name="q" placeholder="<?php esc_attr_e( 'Search services, e.g. colonoscopy', 'doctor-ak-portal' ); ?>" autocomplete="off">
				<button type="button" class="dak-dir-search-clear" data-dak-dir-clear-search hidden aria-label="<?php esc_attr_e( 'Clear search', 'doctor-ak-portal' ); ?>"><?php echo $dak_icon( '<path d="M5.5 5.5l9 9M14.5 5.5l-9 9"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			</form>
		<?php endif; ?>
	</header>

	<?php if ( $total > 0 ) : ?>
		<?php if ( count( $categories ) > 1 ) : ?>
			<div class="dak-dir-categories" role="group" aria-label="<?php esc_attr_e( 'Service category', 'doctor-ak-portal' ); ?>">
				<button type="button" class="dak-dir-category" data-dak-svc-category="" aria-pressed="true">
					<?php esc_html_e( 'All services', 'doctor-ak-portal' ); ?>
					<span class="dak-dir-option-count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
				</button>
				<?php foreach ( $categories as $dak_category ) : ?>
					<button type="button" class="dak-dir-category" data-dak-svc-category="<?php echo esc_attr( $dak_category['slug'] ); ?>" data-label="<?php echo esc_attr( $dak_category['label'] ); ?>" aria-pressed="false">
						<?php echo esc_html( $dak_category['label'] ); ?>
						<span class="dak-dir-option-count"><?php echo esc_html( number_format_i18n( $dak_category['count'] ) ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="dak-dir-toolbar dak-dir-toolbar-services">
			<p class="dak-dir-count" data-dak-dir-count aria-live="polite" aria-atomic="true">
				<?php echo esc_html( sprintf( $dak_svc_strings['showing'], number_format_i18n( min( 12, $total ) ), number_format_i18n( $total ) ) ); ?>
			</p>
			<button type="button" class="dak-dir-link-button" data-dak-dir-clear-all hidden><?php esc_html_e( 'Clear search and filter', 'doctor-ak-portal' ); ?></button>
		</div>

		<ul class="dak-dir-service-grid" data-dak-dir-list>
			<?php foreach ( $services_html as $dak_card_html ) : ?>
				<?php echo $dak_card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</ul>

		<div class="dak-dir-empty" data-dak-dir-empty hidden>
			<p class="dak-dir-empty-title"><?php esc_html_e( 'No services match', 'doctor-ak-portal' ); ?></p>
			<p class="dak-dir-empty-text"><?php esc_html_e( 'Try a different word, or show every category.', 'doctor-ak-portal' ); ?></p>
			<button type="button" class="dak-dir-btn dak-dir-btn-secondary" data-dak-dir-clear-all><?php esc_html_e( 'Show all services', 'doctor-ak-portal' ); ?></button>
		</div>

		<div class="dak-dir-more" data-dak-dir-more-wrap hidden>
			<button type="button" class="dak-dir-btn dak-dir-btn-secondary" data-dak-dir-more></button>
		</div>
	<?php else : ?>
		<p class="dak-dir-empty-title"><?php esc_html_e( 'No services are listed yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</div>
