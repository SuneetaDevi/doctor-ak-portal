<?php
/**
 * Template: Services directory grid for the [services_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $services_html Pre-rendered directory/service-card.php output, one per active service.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory">
	<div class="dak-directory-header">
		<h1><?php esc_html_e( 'Our Services', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Browse our services and book an appointment with the doctor and clinic of your choice.', 'doctor-ak-portal' ); ?></p>
	</div>

	<?php if ( empty( $services_html ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No services are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-directory-grid" id="dak-services-directory-grid">
			<?php foreach ( $services_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
