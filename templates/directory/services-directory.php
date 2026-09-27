<?php
/**
 * Template: Services directory list for the [services_directory] shortcode
 * — wide stacked rows, matching how services are shown on the home page
 * (see .dak-home-services-list in doctor-ak-directory.css, redefined there
 * from doctor-ak-home.css since that stylesheet isn't loaded on this page).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $services_html Pre-rendered directory/home-service-card.php output, one per active service.
 * @var string[] $categories    Category slug => label, alphabetical by label — the filter chips (see Services_Directory::render()).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory">
	<div class="dak-directory-header">
		<span class="dak-eyebrow"><?php esc_html_e( 'What We Treat', 'doctor-ak-portal' ); ?></span>
		<h1><?php esc_html_e( 'Our Services', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Browse our services and book an appointment with the doctor and clinic of your choice.', 'doctor-ak-portal' ); ?></p>
	</div>

	<?php if ( ! empty( $services_html ) ) : ?>
		<?php if ( ! empty( $categories ) ) : ?>
			<div class="dak-blog-chips" id="dak-services-directory-chips" role="group" aria-label="<?php esc_attr_e( 'Filter services by category', 'doctor-ak-portal' ); ?>">
				<button type="button" class="dak-blog-chip is-active" data-category-filter="" aria-pressed="true"><?php esc_html_e( 'All services', 'doctor-ak-portal' ); ?></button>
				<?php foreach ( $categories as $dak_category_slug => $dak_category_label ) : ?>
					<button type="button" class="dak-blog-chip" data-category-filter="<?php echo esc_attr( $dak_category_slug ); ?>" aria-pressed="false"><?php echo esc_html( $dak_category_label ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="dak-directory-search dak-services-directory-search">
			<span class="dak-directory-search-icon" aria-hidden="true">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg>
			</span>
			<input type="search" id="dak-services-directory-search-input" placeholder="<?php esc_attr_e( 'Search services…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>">
		</div>
	<?php endif; ?>

	<?php if ( empty( $services_html ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No services are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-home-services-list" id="dak-services-directory-grid">
			<?php foreach ( $services_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>
		<p class="dak-empty-state dak-hidden" id="dak-services-directory-empty"><?php esc_html_e( 'No services match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</div>
