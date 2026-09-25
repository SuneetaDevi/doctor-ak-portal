<?php
/**
 * Template: Blog listing grid for the [blogs_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $blogs_html Pre-rendered directory/blog-card.php output, one per published post.
 * @var string[] $topics     Distinct topics in use on published posts, alphabetical — the filter chips (none shown when empty).
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
		<?php if ( ! empty( $topics ) ) : ?>
			<div class="dak-blog-chips" id="dak-blog-chips" role="group" aria-label="<?php esc_attr_e( 'Filter posts by topic', 'doctor-ak-portal' ); ?>">
				<button type="button" class="dak-blog-chip is-active" data-topic-filter="" aria-pressed="true"><?php esc_html_e( 'All topics', 'doctor-ak-portal' ); ?></button>
				<?php foreach ( $topics as $dak_topic ) : ?>
					<button type="button" class="dak-blog-chip" data-topic-filter="<?php echo esc_attr( sanitize_title( $dak_topic ) ); ?>" aria-pressed="false"><?php echo esc_html( $dak_topic ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="dak-blog-grid" id="dak-blogs-directory-grid">
			<?php foreach ( $blogs_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
