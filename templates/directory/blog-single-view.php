<?php
/**
 * Template: Public single blog post page for the [blog_single] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array|null $blog {
 *     Null if no valid/published blog_id was given.
 *
 *     @type int    $id           Blog post ID.
 *     @type string $title        Post title.
 *     @type string $content      Full body — rich-text HTML (bold/italic/lists/links) from the admin's formatting toolbar.
 *     @type string $image_url    Featured image URL, or '' if none uploaded.
 *     @type string $author_name  Author's display name.
 *     @type string $published_at Published date/time (MySQL format).
 * }
 * @var string $directory_url "All Posts" breadcrumb link.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory">
	<div class="dak-profile-breadcrumb">
		<?php if ( $directory_url ) : ?>
			<a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Blog', 'doctor-ak-portal' ); ?></a>
			<span>&rsaquo;</span>
		<?php endif; ?>
		<span><?php echo esc_html( $blog ? $blog['title'] : __( 'Post not found', 'doctor-ak-portal' ) ); ?></span>
	</div>

	<?php if ( ! $blog ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'This post is no longer available.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<article class="dak-profile-header-card dak-blog-single-card">
			<h1><?php echo esc_html( $blog['title'] ); ?></h1>

			<?php if ( $blog['author_name'] || $blog['published_at'] ) : ?>
				<p class="dak-profile-qualification">
					<?php if ( $blog['author_name'] ) : ?>
						<?php echo esc_html( $blog['author_name'] ); ?>
					<?php endif; ?>
					<?php if ( $blog['author_name'] && $blog['published_at'] ) : ?>
						&middot;
					<?php endif; ?>
					<?php if ( $blog['published_at'] ) : ?>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $blog['published_at'] ) ) ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $blog['image_url'] ) : ?>
				<img class="dak-blog-single-image" src="<?php echo esc_url( $blog['image_url'] ); ?>" alt="">
			<?php endif; ?>

			<div class="dak-rich-text-content"><?php echo wp_kses_post( $blog['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</article>
	<?php endif; ?>
</div>
