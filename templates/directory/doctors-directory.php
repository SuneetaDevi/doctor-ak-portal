<?php
/**
 * Template: Doctors directory grid for the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $doctors_html    Pre-rendered directory/doctor-card.php output, one per doctor.
 * @var string   $hero_banner_url Bundled hero banner photo URL (Doctors_Directory::HERO_BANNER_IMAGE_PATH), or '' if missing.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_directory_icons = array(
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'video'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M17.5 7.5 12.5 10l5 2.5z"/></svg>',
	'clock'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6v4l3 2"/></svg>',
	'chevron'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8l4 4 4-4"/></svg>',
	'arrow_l'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 4.5l-5.5 5.5 5.5 5.5"/></svg>',
	'arrow_r'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 4.5l5.5 5.5-5.5 5.5"/></svg>',
);
?>
<div class="dak-portal dak-directory">
	<section class="dak-directory-hero">
		<?php if ( $hero_banner_url ) : ?>
			<div class="dak-directory-hero-media">
				<img src="<?php echo esc_url( $hero_banner_url ); ?>" alt="">
				<span class="dak-directory-hero-overlay" aria-hidden="true"></span>
			</div>
		<?php endif; ?>

		<div class="dak-directory-hero-content">
			<span class="dak-eyebrow"><?php esc_html_e( 'Our Specialists', 'doctor-ak-portal' ); ?></span>
			<h1>
				<?php esc_html_e( 'Our', 'doctor-ak-portal' ); ?>
				<span class="dak-directory-hero-accent"><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></span>
			</h1>
			<p><?php esc_html_e( 'Browse our specialists and book a clinic visit or an online video consultation.', 'doctor-ak-portal' ); ?></p>
		</div>
	</section>

	<?php if ( ! empty( $doctors_html ) ) : ?>
		<div class="dak-directory-filters">
			<div class="dak-directory-search">
				<span class="dak-directory-search-icon" aria-hidden="true">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg>
				</span>
				<input type="search" id="dak-directory-search-input" placeholder="<?php esc_attr_e( 'Search by doctor name, specialty…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search by doctor name or specialty', 'doctor-ak-portal' ); ?>">
			</div>
			<select id="dak-directory-sort" aria-label="<?php esc_attr_e( 'Sort doctors', 'doctor-ak-portal' ); ?>">
				<option value="experience-desc"><?php esc_html_e( 'Sort by: Most Experienced', 'doctor-ak-portal' ); ?></option>
				<option value="name-asc"><?php esc_html_e( 'Sort by: Name (A-Z)', 'doctor-ak-portal' ); ?></option>
				<option value="name-desc"><?php esc_html_e( 'Sort by: Name (Z-A)', 'doctor-ak-portal' ); ?></option>
			</select>
		</div>

		<div class="dak-directory-quick-filters">
			<button
				type="button"
				class="dak-directory-pill"
				id="dak-directory-nearme-toggle"
				aria-pressed="false"
				data-msg-denied="<?php esc_attr_e( 'We could not get your location. Allow location access in your browser to use Near me.', 'doctor-ak-portal' ); ?>"
				data-msg-unsupported="<?php esc_attr_e( 'Your browser does not support location detection.', 'doctor-ak-portal' ); ?>"
				data-msg-none="<?php esc_attr_e( 'No doctors are listed in a city we can match to your location yet.', 'doctor-ak-portal' ); ?>"
				data-label-near="<?php esc_attr_e( 'Near me:', 'doctor-ak-portal' ); ?>"
			>
				<?php echo $dak_directory_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span data-nearme-label><?php esc_html_e( 'Near me', 'doctor-ak-portal' ); ?></span>
			</button>

			<button type="button" class="dak-directory-pill" id="dak-directory-video-toggle" aria-pressed="false">
				<?php echo $dak_directory_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?>
			</button>

			<button type="button" class="dak-directory-pill" id="dak-directory-availability-toggle" aria-pressed="false">
				<?php echo $dak_directory_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Availability', 'doctor-ak-portal' ); ?>
			</button>

			<p class="dak-directory-nearme-status dak-hidden" id="dak-directory-nearme-status" role="status"></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $doctors_html ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-directory-grid dak-directory-grid-list" id="dak-directory-grid">
			<?php foreach ( $doctors_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>
		<p class="dak-empty-state dak-hidden" id="dak-directory-no-results"><?php esc_html_e( 'No doctors match your search.', 'doctor-ak-portal' ); ?></p>

		<nav class="dak-directory-pagination" id="dak-directory-pagination" aria-label="<?php esc_attr_e( 'Doctors list pages', 'doctor-ak-portal' ); ?>">
			<button type="button" class="dak-directory-page-nav" id="dak-directory-page-prev" aria-label="<?php esc_attr_e( 'Previous page', 'doctor-ak-portal' ); ?>">
				<?php echo $dak_directory_icons['arrow_l']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
			<div class="dak-directory-page-numbers" id="dak-directory-page-numbers"></div>
			<button type="button" class="dak-directory-page-nav" id="dak-directory-page-next" aria-label="<?php esc_attr_e( 'Next page', 'doctor-ak-portal' ); ?>">
				<?php echo $dak_directory_icons['arrow_r']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		</nav>
	<?php endif; ?>
</div>
