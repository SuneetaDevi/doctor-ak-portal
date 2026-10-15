<?php
/**
 * Template: Admin/Receptionist dashboard "Videos" section — the videos shown
 * on the public site: the first few in the home page's video section, all of
 * them on the Videos page. Rows are built and saved by
 * doctor-ak-admin-home-videos.js (each upload, title change, reorder or
 * removal saves straight away).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $videos_page_url Public Videos page URL, or ''.
 * @var int    $home_limit      How many videos the home page shows.
 * @var int    $max_videos      Most videos that can be added.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Videos', 'doctor-ak-portal' ); ?></h1>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of videos shown on the home page. */
					__( 'Videos shown on the website. The first %d appear on the home page; all of them appear on the Videos page. Changes save automatically.', 'doctor-ak-portal' ),
					(int) $home_limit
				)
			);
			?>
		</p>
	</div>
	<?php if ( '' !== $videos_page_url ) : ?>
		<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $videos_page_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Videos page', 'doctor-ak-portal' ); ?></a>
	<?php endif; ?>
</div>

<section class="dak-results dak-admin-videos" data-home-videos-editor data-home-limit="<?php echo esc_attr( (int) $home_limit ); ?>" data-max-videos="<?php echo esc_attr( (int) $max_videos ); ?>">
	<div class="dak-admin-videos-toolbar">
		<p class="dak-admin-videos-count" data-home-videos-count aria-live="polite"></p>
		<button type="button" class="dak-button dak-button-primary" data-home-videos-add-row><?php esc_html_e( '+ Add video', 'doctor-ak-portal' ); ?></button>
	</div>

	<p class="dak-field-hint"><?php esc_html_e( 'MP4 works everywhere (WebM, MOV and OGG are accepted too). A thumbnail is optional — without one the video’s first frame is shown. Use the arrows to change the order.', 'doctor-ak-portal' ); ?></p>

	<div class="dak-admin-videos-rows" data-home-videos-rows></div>

	<p class="dak-admin-videos-empty" data-home-videos-empty hidden><?php esc_html_e( 'No videos yet. Add one to show it on the home page and the Videos page.', 'doctor-ak-portal' ); ?></p>
</section>
</div>
