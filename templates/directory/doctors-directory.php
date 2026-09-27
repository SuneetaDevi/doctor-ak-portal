<?php
/**
 * Template: Doctors directory grid for the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array    $specialities    Specialities at least one listed doctor has — list of { slug, label, count }, alphabetical by label — the sidebar filter list (see Doctors_Directory::render()).
 * @var int      $doctors_count   Total number of listed doctors — the initial "Showing X of Y" count before any filter narrows it.
 * @var string[] $doctors_html    Pre-rendered directory/doctor-card.php output, one per doctor.
 * @var string   $hero_banner_url Bundled hero banner photo URL (Doctors_Directory::HERO_BANNER_IMAGE_PATH), or '' if missing.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_directory_icons = array(
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'user'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.5 4-5 6.5-5s5.5 1.5 6.5 5"/></svg>',
	'video'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M17.5 7.5 12.5 10l5 2.5z"/></svg>',
	'clock'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6v4l3 2"/></svg>',
	'chevron'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8l4 4 4-4"/></svg>',
	'arrow_l'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 4.5l-5.5 5.5 5.5 5.5"/></svg>',
	'arrow_r'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 4.5l5.5 5.5-5.5 5.5"/></svg>',
	'sliders'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h9M15 6h2M3 14h5M11 14h6"/><circle cx="12.5" cy="6" r="1.7"/><circle cx="8" cy="14" r="1.7"/></svg>',
);

// Sidebar shows the first few specialities and tucks the rest behind a
// "+N more" toggle (see the dak-directory-specialties-toggle wiring in
// doctor-ak-directory.js) — plain UI collapse, no effect on filtering itself.
$dak_visible_specialities     = 6;
$dak_extra_specialities_count = max( 0, count( $specialities ) - $dak_visible_specialities );
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
		<div class="dak-directory-layout">
			<aside class="dak-directory-sidebar">
				<?php if ( ! empty( $specialities ) ) : ?>
					<div class="dak-directory-sidebar-section">
						<span class="dak-directory-sidebar-heading"><?php esc_html_e( 'Specialties', 'doctor-ak-portal' ); ?></span>

						<ul class="dak-directory-specialty-list" id="dak-directory-spec-chips" role="group" aria-label="<?php esc_attr_e( 'Filter doctors by speciality', 'doctor-ak-portal' ); ?>">
							<li>
								<button type="button" class="dak-directory-specialty-item is-active" data-spec-filter="" aria-pressed="true">
									<span><?php esc_html_e( 'All Specialties', 'doctor-ak-portal' ); ?></span>
								</button>
							</li>
							<?php foreach ( $specialities as $dak_i => $dak_speciality ) : ?>
								<li<?php echo $dak_i >= $dak_visible_specialities ? ' class="dak-directory-specialty-extra dak-hidden"' : ''; ?>>
									<button type="button" class="dak-directory-specialty-item" data-spec-filter="<?php echo esc_attr( $dak_speciality['slug'] ); ?>" aria-pressed="false">
										<span><?php echo esc_html( $dak_speciality['label'] ); ?></span>
										<span class="dak-directory-specialty-count"><?php echo esc_html( $dak_speciality['count'] ); ?></span>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>

						<?php if ( $dak_extra_specialities_count > 0 ) : ?>
							<button type="button" class="dak-directory-specialties-toggle" id="dak-directory-specialties-toggle" data-label-more="<?php echo esc_attr( sprintf( /* translators: %d: number of additional specialities. */ __( '+%d more', 'doctor-ak-portal' ), $dak_extra_specialities_count ) ); ?>" data-label-less="<?php esc_attr_e( 'Show less', 'doctor-ak-portal' ); ?>">
								<?php echo esc_html( sprintf( /* translators: %d: number of additional specialities. */ __( '+%d more', 'doctor-ak-portal' ), $dak_extra_specialities_count ) ); ?>
								<?php echo $dak_directory_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="dak-directory-sidebar-section">
					<span class="dak-directory-sidebar-heading"><?php esc_html_e( 'Availability', 'doctor-ak-portal' ); ?></span>

					<label class="dak-directory-filter-checkbox">
						<button type="button" class="dak-directory-checkbox-box" id="dak-directory-availability-toggle" aria-pressed="false">
							<?php echo $dak_directory_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- reused as the checkmark glyph via CSS rotation/clip, matching the box's on/off state. ?>
						</button>
						<span><?php esc_html_e( 'Available today', 'doctor-ak-portal' ); ?></span>
					</label>

					<label class="dak-directory-filter-checkbox">
						<button type="button" class="dak-directory-checkbox-box" id="dak-directory-video-toggle" aria-pressed="false">
							<?php echo $dak_directory_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
						<span><?php esc_html_e( 'Video consultation', 'doctor-ak-portal' ); ?></span>
					</label>

					<label class="dak-directory-filter-checkbox">
						<button type="button" class="dak-directory-checkbox-box" id="dak-directory-male-toggle" aria-pressed="false">
							<?php echo $dak_directory_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
						<span><?php esc_html_e( 'Male doctor', 'doctor-ak-portal' ); ?></span>
					</label>

					<label class="dak-directory-filter-checkbox">
						<button type="button" class="dak-directory-checkbox-box" id="dak-directory-female-toggle" aria-pressed="false">
							<?php echo $dak_directory_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
						<span><?php esc_html_e( 'Female doctor', 'doctor-ak-portal' ); ?></span>
					</label>
				</div>
			</aside>

			<div class="dak-directory-main">
				<div class="dak-directory-toolbar">
					<div class="dak-directory-search">
						<span class="dak-directory-search-icon" aria-hidden="true">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg>
						</span>
						<input type="search" id="dak-directory-search-input" placeholder="<?php esc_attr_e( 'Search by doctor name, specialty…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search by doctor name or specialty', 'doctor-ak-portal' ); ?>">
					</div>

					<button
						type="button"
						class="dak-directory-pill dak-directory-toolbar-nearme"
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

					<div class="dak-directory-sort-field">
						<span class="dak-directory-sort-label"><?php esc_html_e( 'Sort by:', 'doctor-ak-portal' ); ?></span>
						<select id="dak-directory-sort" aria-label="<?php esc_attr_e( 'Sort doctors', 'doctor-ak-portal' ); ?>">
							<option value="experience-desc"><?php esc_html_e( 'Most Experienced', 'doctor-ak-portal' ); ?></option>
							<option value="name-asc"><?php esc_html_e( 'Name (A-Z)', 'doctor-ak-portal' ); ?></option>
							<option value="name-desc"><?php esc_html_e( 'Name (Z-A)', 'doctor-ak-portal' ); ?></option>
						</select>
					</div>
				</div>

				<p class="dak-directory-nearme-status dak-hidden" id="dak-directory-nearme-status" role="status"></p>

				<?php
				/* translators: 1: number of doctors currently shown, 2: total number of doctors. */
				$dak_results_count_template = __( 'Showing %1$d of %2$d specialists', 'doctor-ak-portal' );
				?>
				<p class="dak-directory-results-count" id="dak-directory-results-count" data-template="<?php echo esc_attr( $dak_results_count_template ); ?>">
					<?php echo esc_html( sprintf( $dak_results_count_template, $doctors_count, $doctors_count ) ); ?>
				</p>

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
			</div>
		</div>
	<?php else : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</div>
