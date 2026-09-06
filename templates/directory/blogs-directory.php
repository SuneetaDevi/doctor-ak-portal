<?php
/**
 * Template: Blog listing grid for the [blogs_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $blogs_html Pre-rendered directory/blog-card.php output, one per published post.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory">
	<div class="dak-directory-header">
		<span class="dak-eyebrow"><?php esc_html_e( 'From the Clinic', 'doctor-ak-portal' ); ?></span>
		<h1><?php esc_html_e( 'Blog', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Health tips, clinic news, and articles from our doctors.', 'doctor-ak-portal' ); ?></p>
	</div>

	<?php if ( empty( $blogs_html ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No posts are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-directory-grid" id="dak-blogs-directory-grid">
			<?php foreach ( $blogs_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
