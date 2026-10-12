<?php
/**
 * Template: Single blog post card — used by the Blog page grid, the home
 * page's latest posts and an article's "Related articles". A fixed 16:10
 * image frame (photos close to that shape fill it; diagrams and other shapes
 * are shown whole on a neutral panel, never cropped or enlarged — see
 * Blogs::image_fit()), a topic + reading-time line, the title (its link
 * covers the whole card), a short excerpt, then author/date and a
 * "Read article" cue.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $id           Blog post ID.
 * @var string $title        Post title.
 * @var string $topic        Optional topic label, or ''.
 * @var int    $read_minutes Estimated reading time in whole minutes.
 * @var string $excerpt      Plain-text excerpt (see Blogs::decode_row()).
 * @var string $image_url    Featured image URL ('large' size), or '' if none uploaded.
 * @var array  $image        Original image: url/width/height/srcset (url '' if none).
 * @var string $author_name  Author's display name.
 * @var string $published_at Published date/time (MySQL format), or null.
 * @var string $view_url     URL of this post's [blog_single] page.
 */

use DoctorAKPortal\Includes\Blogs;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_image = isset( $image ) && is_array( $image ) ? $image : array( 'url' => $image_url, 'width' => 0, 'height' => 0, 'srcset' => '' );
$dak_fit   = Blogs::image_fit( $dak_image, Blogs::CARD_FRAME_RATIO );
$dak_src   = '' !== $image_url ? $image_url : $dak_image['url'];
$dak_date  = $published_at ? date_i18n( get_option( 'date_format' ), strtotime( $published_at ) ) : '';

/* translators: %d: estimated reading time in minutes. */
$dak_read  = sprintf( __( '%d min read', 'doctor-ak-portal' ), (int) $read_minutes );
?>
<article class="dak-blog-card" data-topic="<?php echo esc_attr( '' !== $topic ? sanitize_title( $topic ) : '' ); ?>" data-search="<?php echo esc_attr( mb_strtolower( $title . ' ' . $topic . ' ' . $excerpt ) ); ?>">
	<div class="dak-blog-card-media<?php echo '' !== $dak_fit ? ' is-' . esc_attr( $dak_fit ) : ' is-empty'; ?>">
		<?php if ( '' !== $dak_fit ) : ?>
			<img
				src="<?php echo esc_url( $dak_src ); ?>"
				<?php if ( '' !== $dak_image['srcset'] ) : ?>
					srcset="<?php echo esc_attr( $dak_image['srcset'] ); ?>"
					sizes="(max-width: 640px) calc(100vw - 32px), (max-width: 1024px) 46vw, 380px"
				<?php endif; ?>
				<?php if ( $dak_image['width'] > 0 && $dak_image['height'] > 0 ) : ?>
					width="<?php echo esc_attr( $dak_image['width'] ); ?>"
					height="<?php echo esc_attr( $dak_image['height'] ); ?>"
				<?php endif; ?>
				alt=""
				loading="lazy"
				decoding="async"
			>
		<?php else : ?>
			<span class="dak-blog-card-placeholder" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h7M9 16h5"/></svg>
			</span>
		<?php endif; ?>
	</div>

	<div class="dak-blog-card-body">
		<p class="dak-blog-card-meta">
			<?php if ( '' !== $topic ) : ?>
				<span class="dak-blog-card-topic"><?php echo esc_html( $topic ); ?></span>
				<span class="dak-blog-card-dot" aria-hidden="true">&middot;</span>
			<?php endif; ?>
			<span class="dak-blog-card-read"><?php echo esc_html( $dak_read ); ?></span>
		</p>

		<h3 class="dak-blog-card-title">
			<a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $title ); ?></a>
		</h3>

		<?php if ( '' !== $excerpt ) : ?>
			<p class="dak-blog-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>

		<div class="dak-blog-card-foot">
			<span class="dak-blog-card-byline">
				<?php if ( '' !== (string) $author_name ) : ?>
					<span><?php echo esc_html( $author_name ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $dak_date ) : ?>
					<?php if ( '' !== (string) $author_name ) : ?>
						<span aria-hidden="true">&middot;</span>
					<?php endif; ?>
					<time datetime="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $published_at ) ) ); ?>"><?php echo esc_html( $dak_date ); ?></time>
				<?php endif; ?>
			</span>
			<span class="dak-blog-card-cta" aria-hidden="true">
				<?php esc_html_e( 'Read article', 'doctor-ak-portal' ); ?>
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h12M11 5l5 5-5 5"/></svg>
			</span>
		</div>
	</div>
</article>
