<?php
/**
 * Template: Doctors directory grid for the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $doctors_html Pre-rendered directory/doctor-card.php output, one per doctor.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory">
	<div class="dak-directory-header">
		<h1><?php esc_html_e( 'Our Doctors', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Browse our specialists and book a clinic visit or an online video consultation.', 'doctor-ak-portal' ); ?></p>
	</div>

	<?php if ( empty( $doctors_html ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-directory-grid">
			<?php foreach ( $doctors_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
