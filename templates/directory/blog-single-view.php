<?php
/**
 * Template: Public single blog post page for the [blog_single] shortcode —
 * breadcrumb, category, title and summary, a wide photo, the author line, then
 * a two-column layout: a sticky sidebar (share buttons + table of contents,
 * the latter built by doctor-ak-blog-single.js from the post's own headings)
 * beside the article, and a "Related articles" row underneath.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array|null $blog {
 *     Null if no valid/published blog_id was given.
 *
 *     @type int    $id           Blog post ID.
 *     @type string $title        Post title.
 *     @type string $topic        Category label, or ''.
 *     @type int    $read_minutes Estimated reading time in minutes.
 *     @type string $excerpt      Plain-text summary.
 *     @type string $content      Full body — rich-text HTML (bold/italic/lists/links) from the admin's formatting toolbar.
 *     @type string $image_url    Featured image URL, or '' if none uploaded.
 *     @type string $author_name  Author's display name.
 *     @type string $published_at Published date/time (MySQL format).
 * }
 * @var string   $directory_url "All Posts" breadcrumb link.
 * @var string   $share_url     Absolute URL of this post, for the share buttons — '' if the single-post page couldn't be found.
 * @var string[] $related_html  Pre-rendered directory/blog-card.php output for up to three other posts.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_share_title = $blog ? $blog['title'] : '';
$dak_share_enc   = rawurlencode( $share_url );
$dak_share_text  = rawurlencode( $dak_share_title );

$dak_share_links = array(
	'whatsapp' => array(
		'label' => __( 'WhatsApp', 'doctor-ak-portal' ),
		'url'   => 'https://wa.me/?text=' . rawurlencode( $dak_share_title . ' ' . $share_url ),
		'icon'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5a7.5 7.5 0 0 0-6.4 11.4L2.5 17.5l3.7-1A7.5 7.5 0 1 0 10 2.5z"/><path d="M7.6 7.2c0 2.6 2.6 5.2 5.2 5.2l.9-1-1.6-.9-.7.6a3.6 3.6 0 0 1-1.7-1.7l.6-.7-.9-1.6z"/></svg>',
	),
	'facebook' => array(
		'label' => __( 'Facebook', 'doctor-ak-portal' ),
		'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $dak_share_enc,
		'icon'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M12.5 6.5H11c-.3 0-.5.2-.5.5v1.5H12.5l-.3 2H10.5V17h-2v-6.5H7V8.5h1.5V7c0-1.7 1.3-3 3-3H12.5v2.5z"/></svg>',
	),
	'x'        => array(
		'label' => __( 'X (Twitter)', 'doctor-ak-portal' ),
		'url'   => 'https://twitter.com/intent/tweet?url=' . $dak_share_enc . '&text=' . $dak_share_text,
		'icon'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 4h1.9l-4.2 4.8L18 16h-3.9l-3-4-3.5 4H5.7l4.5-5.1L4.5 4h4l2.7 3.6L15.5 4z"/></svg>',
	),
	'linkedin' => array(
		'label' => __( 'LinkedIn', 'doctor-ak-portal' ),
		'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $dak_share_enc,
		'icon'  => '<svg viewBox="0 0 20 20" fill="currentColor"><rect x="3" y="8" width="3" height="9"/><circle cx="4.5" cy="4.5" r="1.6"/><path d="M9 8h3v1.4c.5-.9 1.5-1.6 3-1.6 2.3 0 3 1.4 3 3.7V17h-3v-4.8c0-1.1-.4-1.9-1.4-1.9-1.1 0-1.6.7-1.6 1.9V17H9V8z"/></svg>',
	),
	'telegram' => array(
		'label' => __( 'Telegram', 'doctor-ak-portal' ),
		'url'   => 'https://t.me/share/url?url=' . $dak_share_enc . '&text=' . $dak_share_text,
		'icon'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 3L2.5 9l4.5 1.7L8.5 16l2.4-2.7 3.7 2.7L17.5 3z"/><path d="M7 10.7l7-4.7"/></svg>',
	),
	'email'    => array(
		'label' => __( 'Email', 'doctor-ak-portal' ),
		'url'   => 'mailto:?subject=' . $dak_share_text . '&body=' . $dak_share_enc,
		'icon'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="15" height="11" rx="1.5"/><path d="M3 5.5l7 5.5 7-5.5"/></svg>',
	),
);

$dak_copy_icon = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="7" width="9.5" height="9.5" rx="1.5"/><path d="M13 7V4.5A1.5 1.5 0 0 0 11.5 3h-7A1.5 1.5 0 0 0 3 4.5v7A1.5 1.5 0 0 0 4.5 13H7"/></svg>';
?>
<div class="dak-portal dak-directory dak-blog-single">
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
		<header class="dak-blog-single-head">
			<?php if ( '' !== $blog['topic'] ) : ?>
				<span class="dak-blog-single-category"><?php echo esc_html( $blog['topic'] ); ?></span>
			<?php endif; ?>
			<h1><?php echo esc_html( $blog['title'] ); ?></h1>
			<?php if ( '' !== $blog['excerpt'] ) : ?>
				<p class="dak-blog-single-summary"><?php echo esc_html( $blog['excerpt'] ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $blog['image_url'] ) : ?>
			<img class="dak-blog-single-hero" src="<?php echo esc_url( $blog['image_url'] ); ?>" alt="">
		<?php endif; ?>

		<div class="dak-blog-single-byline">
			<?php if ( $blog['author_name'] ) : ?>
				<span class="dak-blog-single-avatar" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $blog['author_name'], 0, 1 ) ) ); ?></span>
				<span class="dak-blog-single-byline-text">
					<span><?php esc_html_e( 'Published by', 'doctor-ak-portal' ); ?></span>
					<strong><?php echo esc_html( $blog['author_name'] ); ?></strong>
				</span>
			<?php endif; ?>
			<?php if ( $blog['published_at'] ) : ?>
				<span class="dak-blog-single-byline-text">
					<span><?php esc_html_e( 'Published on', 'doctor-ak-portal' ); ?></span>
					<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $blog['published_at'] ) ) ); ?></strong>
				</span>
			<?php endif; ?>
			<span class="dak-blog-single-byline-text">
				<span><?php esc_html_e( 'Reading time', 'doctor-ak-portal' ); ?></span>
				<strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: estimated reading time in minutes. */
							__( '%d min read', 'doctor-ak-portal' ),
							(int) $blog['read_minutes']
						)
					);
					?>
				</strong>
			</span>
		</div>

		<div class="dak-blog-single-layout">
			<aside class="dak-blog-single-sidebar">
				<?php if ( '' !== $share_url ) : ?>
					<section class="dak-blog-side-card">
						<h2><?php esc_html_e( 'Share this article', 'doctor-ak-portal' ); ?></h2>
						<div class="dak-blog-share">
							<?php foreach ( $dak_share_links as $dak_network => $dak_share ) : ?>
								<a class="dak-blog-share-btn dak-blog-share-<?php echo esc_attr( $dak_network ); ?>" href="<?php echo esc_url( $dak_share['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: social network name. */ __( 'Share on %s', 'doctor-ak-portal' ), $dak_share['label'] ) ); ?>" title="<?php echo esc_attr( $dak_share['label'] ); ?>">
									<?php echo $dak_share['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</a>
							<?php endforeach; ?>
						</div>
						<button type="button" class="dak-blog-copy" data-copy-link="<?php echo esc_attr( $share_url ); ?>" data-copied="<?php esc_attr_e( 'Link copied', 'doctor-ak-portal' ); ?>">
							<?php echo $dak_copy_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span><?php esc_html_e( 'Copy link', 'doctor-ak-portal' ); ?></span>
						</button>
					</section>
				<?php endif; ?>

				<section class="dak-blog-side-card dak-blog-toc dak-hidden" id="dak-blog-toc">
					<h2><?php esc_html_e( 'Table of contents', 'doctor-ak-portal' ); ?></h2>
					<ul id="dak-blog-toc-list"></ul>
				</section>
			</aside>

			<article class="dak-blog-single-body dak-rich-text-content" id="dak-blog-article"><?php echo wp_kses_post( $blog['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></article>
		</div>

		<?php if ( ! empty( $related_html ) ) : ?>
			<section class="dak-blog-related">
				<div class="dak-blog-related-head">
					<h2><?php esc_html_e( 'Related Articles', 'doctor-ak-portal' ); ?></h2>
					<?php if ( $directory_url ) : ?>
						<a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'View all posts', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="dak-blog-grid">
					<?php foreach ( $related_html as $dak_related_card ) : ?>
						<?php echo $dak_related_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
