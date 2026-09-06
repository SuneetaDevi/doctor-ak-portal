<?php
/**
 * Template: Single blog post card within the blogs directory grid.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $id           Blog post ID.
 * @var string $title        Post title.
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

$dak_blog_card_icons = array(
	'image'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><circle cx="7" cy="8" r="1.5"/><path d="M17.5 13.5l-4-4-3 3-2.5-2.5-5 5"/></svg>',
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
);
?>
<div class="dak-service-card">
	<span class="dak-service-card-image">
		<?php if ( $image_url ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" alt="">
		<?php else : ?>
			<?php echo $dak_blog_card_icons['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</span>

	<div class="dak-service-card-body">
		<h3 class="dak-service-card-name"><?php echo esc_html( $title ); ?></h3>

		<?php if ( '' !== $excerpt ) : ?>
			<p class="dak-service-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>

		<?php if ( $author_name || $published_at ) : ?>
			<div class="dak-service-card-price">
				<span><?php echo esc_html( $author_name ); ?></span>
				<?php if ( $published_at ) : ?>
					<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $published_at ) ) ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="dak-service-card-actions">
			<a class="dak-button dak-button-primary dak-button-block" href="<?php echo esc_url( $view_url ); ?>">
				<?php echo $dak_blog_card_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Read More', 'doctor-ak-portal' ); ?>
			</a>
		</div>
	</div>
</div>
