<?php
/**
 * Template: Single blog post card within the blogs directory grid — a flat
 * card: rounded photo, a small topic + reading-time line, the title, a short
 * excerpt, then author and date under a hairline. The whole card is the link.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $id           Blog post ID.
 * @var string $title        Post title.
 * @var string $topic        Optional topic label, or ''.
 * @var int    $read_minutes Estimated reading time in whole minutes.
 * @var string $excerpt      Plain-text excerpt (see Blogs::decode_row()), already trimmed to a word count.
 * @var string $image_url    Featured image URL, or '' if none uploaded.
 * @var string $author_name  Author's display name.
 * @var string $published_at Published date/time (MySQL format), or null.
 * @var string $view_url     URL of this post's [blog_single] page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="dak-blog-card" data-topic="<?php echo esc_attr( '' !== $topic ? sanitize_title( $topic ) : '' ); ?>">
	<span class="dak-blog-card-image">
		<?php if ( $image_url ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy">
		<?php else : ?>
			<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><circle cx="7" cy="8" r="1.5"/><path d="M17.5 13.5l-4-4-3 3-2.5-2.5-5 5"/></svg>
		<?php endif; ?>
	</span>

	<div class="dak-blog-card-meta">
		<span class="dak-blog-card-topic"><?php echo esc_html( $topic ); ?></span>
		<span class="dak-blog-card-read">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: estimated reading time in minutes. */
					__( '%d min read', 'doctor-ak-portal' ),
					(int) $read_minutes
				)
			);
			?>
		</span>
	</div>

	<h3 class="dak-blog-card-title">
		<a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $title ); ?></a>
	</h3>

	<?php if ( '' !== $excerpt ) : ?>
		<p class="dak-blog-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
	<?php endif; ?>

	<div class="dak-blog-card-foot">
		<span><?php echo esc_html( $author_name ); ?></span>
		<?php if ( $published_at ) : ?>
			<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $published_at ) ) ); ?></span>
		<?php endif; ?>
	</div>
</article>
