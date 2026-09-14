<?php
/**
 * Template: Clinics directory grid for the [clinics_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $clinics_html Pre-rendered directory/clinic-card.php output, one per clinic location.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory">
	<div class="dak-directory-header">
		<span class="dak-eyebrow"><?php esc_html_e( 'Find Us', 'doctor-ak-portal' ); ?></span>
		<h1><?php esc_html_e( 'Our Clinics', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Browse our clinic locations and see which doctors practice at each one.', 'doctor-ak-portal' ); ?></p>
	</div>

	<?php if ( empty( $clinics_html ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No clinics are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-directory-grid" id="dak-clinics-directory-grid">
			<?php foreach ( $clinics_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
