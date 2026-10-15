<?php
/**
 * Template: the public Videos page ([videos_gallery]) — every staff-managed
 * video in a gallery grid. Each tile plays in place with the browser's own
 * controls (doctor-ak-videos-gallery.js just pauses the others when one
 * starts).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $videos Home_Videos::get_all() rows — { title, video_url, poster_url }.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory dak-videos-page">
	<header class="dak-videos-page-head">
		<h1><?php esc_html_e( 'Videos', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Real moments from our clinics, procedures and patient care.', 'doctor-ak-portal' ); ?></p>
	</header>

	<?php if ( empty( $videos ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No videos have been added yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<ul class="dak-videos-grid">
			<?php foreach ( $videos as $dak_index => $dak_video ) : ?>
				<?php
				$dak_title = '' !== $dak_video['title']
					? $dak_video['title']
					/* translators: %d: video number. */
					: sprintf( __( 'Video %d', 'doctor-ak-portal' ), $dak_index + 1 );
				?>
				<li class="dak-videos-card">
					<div class="dak-videos-frame">
						<video
							<?php // No thumbnail: show the frame 1s in (many clips open on black). ?>
							src="<?php echo esc_url( $dak_video['video_url'] . ( '' === $dak_video['poster_url'] ? '#t=1' : '' ) ); ?>"
							controls
							playsinline
							preload="<?php echo '' !== $dak_video['poster_url'] ? 'none' : 'metadata'; ?>"
							<?php if ( '' !== $dak_video['poster_url'] ) : ?>
								poster="<?php echo esc_url( $dak_video['poster_url'] ); ?>"
							<?php endif; ?>
							aria-label="<?php echo esc_attr( $dak_title ); ?>"
							data-dak-gallery-video
						></video>
					</div>
					<?php if ( '' !== $dak_video['title'] ) : ?>
						<p class="dak-videos-title"><?php echo esc_html( $dak_video['title'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
