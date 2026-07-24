<?php
/**
 * Template: Homepage doctors slider for the [featured_doctors] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $doctors_html  Pre-rendered directory/doctor-card.php output, one per doctor.
 * @var string   $directory_url "View More" link target ([doctors_directory] page), or '' if not found.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory dak-featured-doctors">
	<div class="dak-featured-doctors-header">
		<div>
			<span class="dak-eyebrow"><?php esc_html_e( 'Our Specialists', 'doctor-ak-portal' ); ?></span>
			<h2><?php esc_html_e( 'Meet Our Doctors', 'doctor-ak-portal' ); ?></h2>
			<p><?php esc_html_e( 'Browse our specialists and book a clinic visit or an online video consultation.', 'doctor-ak-portal' ); ?></p>
		</div>

		<?php if ( $directory_url ) : ?>
			<a class="dak-button dak-button-secondary dak-featured-doctors-view-more" href="<?php echo esc_url( $directory_url ); ?>">
				<?php esc_html_e( 'View More', 'doctor-ak-portal' ); ?>
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 4.5l5.5 5.5-5.5 5.5"/></svg>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( empty( $doctors_html ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-featured-doctors-slider">
			<button type="button" class="dak-featured-doctors-nav dak-featured-doctors-prev" id="dak-featured-doctors-prev" aria-label="<?php esc_attr_e( 'Previous doctors', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.5 5l-5 5 5 5"/></svg>
			</button>

			<div class="dak-featured-doctors-track" id="dak-featured-doctors-track">
				<?php foreach ( $doctors_html as $card_html ) : ?>
					<div class="dak-featured-doctors-slide">
						<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
					</div>
				<?php endforeach; ?>
			</div>

			<button type="button" class="dak-featured-doctors-nav dak-featured-doctors-next" id="dak-featured-doctors-next" aria-label="<?php esc_attr_e( 'Next doctors', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 5l5 5-5 5"/></svg>
			</button>
		</div>

		<?php if ( $directory_url ) : ?>
			<a class="dak-button dak-button-primary dak-featured-doctors-view-more-mobile" href="<?php echo esc_url( $directory_url ); ?>">
				<?php esc_html_e( 'View More Doctors', 'doctor-ak-portal' ); ?>
			</a>
		<?php endif; ?>
	<?php endif; ?>
</div>
