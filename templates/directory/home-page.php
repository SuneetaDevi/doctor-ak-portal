<?php
/**
 * Template: Public home page for the [dak_home] shortcode.
 *
 * The site's header/footer are NOT part of this markup — Site_Header and
 * Site_Footer render globally on `wp_body_open`/`wp_footer` regardless of
 * which page is showing, so this template only owns the content between them.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[]   $doctors_html     Pre-rendered directory/doctor-card.php output, one per featured doctor.
 * @var string[]   $services_html    Pre-rendered directory/service-card.php output, one per featured service.
 * @var array      $videos           Home_Videos::get_all() rows — { title, video_url, poster_url } — admin-uploaded videos.
 * @var array      $testimonials     Home_Testimonials::get_all() rows merged with Google_Reviews::get_reviews() — { quote, name, attribution, rating? } — 'rating' (1-5) is only present on a Google review.
 * @var array      $google_rating    Google_Reviews::overall_rating() — { rating, total } — zeroed out if Google reviews aren't configured.
 * @var string     $hero_video_url   Bundled hero tour video URL (assets/videos/thumbnail.mp4), or '' if missing.
 * @var string[]   $marketing_videos Bundled marketing reel video URLs (assets/videos/video-1..3.mp4).
 * @var string     $directory_url    URL of the [doctors_directory] page, or '' if not found.
 * @var string     $services_url     URL of the [services_directory] page, or '' if not found.
 * @var array      $stats            { doctors_count, patients_count, appointments_count, max_years_experience, clinics_count }.
 * @var array|null $hero_doctor      One row from Doctors_Directory::doctor_cards_data() featured in the hero card, or null if there are no doctors yet.
 * @var array      $clinic_locations Clinic_Locations::get_all() rows (capped), for the "Visit Us" section.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_home_icons = array(
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'shield'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l6.5 2.5v4.3c0 4-2.8 7.2-6.5 8.2-3.7-1-6.5-4.2-6.5-8.2V5z"/><path d="M7.2 10l2 2 3.6-4"/></svg>',
	'clock'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6v4l3 2"/></svg>',
	'video'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M17.5 7.5 12.5 10l5 2.5z"/></svg>',
	'tag'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l6.5 6.5-7.5 7.5-6.5-6.5V3.5z"/><circle cx="6.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>',
	'star'     => '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.7l2.5 5.2 5.7.7-4.2 4 1 5.7-5-2.7-5 2.7 1-5.7-4.2-4 5.7-.7z"/></svg>',
	'chevron'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 4.5l5.5 5.5-5.5 5.5"/></svg>',
	'play'     => '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.5 4.3v11.4a1 1 0 0 0 1.53.85l9-5.7a1 1 0 0 0 0-1.7l-9-5.7A1 1 0 0 0 6.5 4.3z"/></svg>',
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'phone'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h2.3l1 3.3-1.6 1.4a9 9 0 0 0 4.1 4.1l1.4-1.6 3.3 1v2.3c0 .8-.7 1.4-1.5 1.3C8.7 15 5 11.3 4.2 6c-.1-.8.5-1.5 1.3-1.5z"/></svg>',
);

$dak_home_trust_points = array(
	array(
		'icon'  => 'shield',
		'title' => __( 'Verified Specialists', 'doctor-ak-portal' ),
		'text'  => __( 'Every doctor here is a verified specialist in their field.', 'doctor-ak-portal' ),
	),
	array(
		'icon'  => 'clock',
		'title' => __( 'Fast, Easy Booking', 'doctor-ak-portal' ),
		'text'  => __( 'Book a clinic visit or video consultation in under a minute.', 'doctor-ak-portal' ),
	),
	array(
		'icon'  => 'video',
		'title' => __( 'In-Person or Online', 'doctor-ak-portal' ),
		'text'  => __( 'Visit a clinic, or consult with your doctor over video from anywhere.', 'doctor-ak-portal' ),
	),
	array(
		'icon'  => 'tag',
		'title' => __( 'Transparent Pricing', 'doctor-ak-portal' ),
		'text'  => __( 'Every service is quoted in PKR up front, before you book — no surprises.', 'doctor-ak-portal' ),
	),
);

// Sample/example copy shown only until the admin adds their own testimonials
// (Settings -> Home page testimonials) — see the same fallback pattern
// $marketing_videos/$hero_video_url use for the bundled sample clips.
$dak_home_testimonials = ! empty( $testimonials )
	? $testimonials
	: array(
		array(
			'quote'       => __( 'Booking was effortless and the doctor was incredibly thorough. Clear pricing and a genuinely caring team.', 'doctor-ak-portal' ),
			'name'        => __( 'A recent patient', 'doctor-ak-portal' ),
			'attribution' => '',
		),
	);

// Best-effort initials from a display name, for the hero card's avatar
// fallback when no photo is uploaded — a closure (not a named function) so
// re-including this template in the same request never redeclares it.
$dak_home_initials = function ( $name ) {
	$parts    = preg_split( '/\s+/', trim( $name ) );
	$initials = '';

	foreach ( array_slice( $parts, 0, 2 ) as $part ) {
		$initials .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
	}

	return '' !== $initials ? $initials : '?';
};
?>
<div class="dak-portal dak-home">

	<section class="dak-home-hero">
		<div class="dak-home-hero-grid">
			<div class="dak-home-hero-copy">
				<span class="dak-eyebrow"><?php esc_html_e( 'Gastroenterology & Endoscopy · Karachi', 'doctor-ak-portal' ); ?></span>
				<h1>
					<?php esc_html_e( 'Digestive health care that listens', 'doctor-ak-portal' ); ?>
					<em class="dak-home-hero-accent"><?php esc_html_e( 'first.', 'doctor-ak-portal' ); ?></em>
				</h1>
				<p><?php esc_html_e( 'Dr. AK Lohana Clinic brings specialist gastroenterology, hepatology and advanced endoscopy under one roof — with unhurried, plain-spoken consultations.', 'doctor-ak-portal' ); ?></p>

				<div class="dak-home-hero-actions">
					<button type="button" class="dak-button dak-button-primary dak-button-lg" data-dak-book-appointment>
						<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
						<?php echo $dak_home_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>

					<?php if ( $directory_url ) : ?>
						<a class="dak-button dak-button-secondary dak-button-lg" href="<?php echo esc_url( $directory_url ); ?>">
							<?php esc_html_e( 'Meet Our Doctors', 'doctor-ak-portal' ); ?>
						</a>
					<?php endif; ?>
				</div>

			</div>

			<?php if ( $hero_video_url ) : ?>
				<div class="dak-home-hero-visual">
					<video src="<?php echo esc_url( $hero_video_url ); ?>" autoplay muted loop playsinline preload="auto"></video>
					<div class="dak-home-hero-visual-caption">
						<span class="dak-home-hero-visual-play" aria-hidden="true"><?php echo $dak_home_icons['play']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div>
							<strong><?php esc_html_e( 'Take a Video Tour', 'doctor-ak-portal' ); ?></strong>
							<span><?php esc_html_e( 'Inside the clinic', 'doctor-ak-portal' ); ?></span>
						</div>
					</div>
				</div>
			<?php elseif ( $hero_doctor ) : ?>
				<div class="dak-home-hero-card">
					<div class="dak-home-hero-card-doctor">
						<span class="dak-avatar dak-home-hero-card-avatar">
							<?php if ( $hero_doctor['avatar_url'] ) : ?>
								<img src="<?php echo esc_url( $hero_doctor['avatar_url'] ); ?>" alt="">
							<?php else : ?>
								<?php echo esc_html( $dak_home_initials( $hero_doctor['name'] ) ); ?>
							<?php endif; ?>
						</span>
						<div>
							<strong><?php echo esc_html( sprintf( 'Dr. %s', $hero_doctor['name'] ) ); ?></strong>
							<span>
								<?php
								echo esc_html(
									! empty( $hero_doctor['specialization_labels'] )
										? $hero_doctor['specialization_labels'][0]
										: __( 'General Physician', 'doctor-ak-portal' )
								);
								?>
							</span>
						</div>
					</div>

					<div class="dak-home-hero-card-stats">
						<div>
							<strong><?php echo esc_html( number_format_i18n( $stats['max_years_experience'] ) ); ?>+</strong>
							<span><?php esc_html_e( 'Years experience', 'doctor-ak-portal' ); ?></span>
						</div>
						<div>
							<strong><?php echo esc_html( number_format_i18n( $stats['patients_count'] ) ); ?>+</strong>
							<span><?php esc_html_e( 'Patients cared for', 'doctor-ak-portal' ); ?></span>
						</div>
						<div>
							<strong><?php echo esc_html( number_format_i18n( $stats['appointments_count'] ) ); ?>+</strong>
							<span><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></span>
						</div>
					</div>

					<?php if ( $hero_doctor['is_available'] ) : ?>
						<span class="dak-home-hero-card-badge"><span aria-hidden="true">&bull;</span> <?php esc_html_e( 'Available This Week', 'doctor-ak-portal' ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="dak-home-hero-stats-strip">
			<div class="dak-home-hero-stat">
				<strong><?php echo esc_html( number_format_i18n( $stats['max_years_experience'] ) ); ?>+</strong>
				<span><?php esc_html_e( 'Years of Clinical Practice', 'doctor-ak-portal' ); ?></span>
			</div>
			<div class="dak-home-hero-stat">
				<strong><?php echo esc_html( number_format_i18n( $stats['patients_count'] ) ); ?>+</strong>
				<span><?php esc_html_e( 'Patients Cared For', 'doctor-ak-portal' ); ?></span>
			</div>
			<div class="dak-home-hero-stat">
				<strong><?php echo esc_html( number_format_i18n( $stats['appointments_count'] ) ); ?>+</strong>
				<span><?php esc_html_e( 'Appointments Completed', 'doctor-ak-portal' ); ?></span>
			</div>
			<div class="dak-home-hero-stat">
				<strong><?php echo esc_html( number_format_i18n( $stats['clinics_count'] ) ); ?></strong>
				<span><?php esc_html_e( 'Clinics Across Karachi', 'doctor-ak-portal' ); ?></span>
			</div>
		</div>
	</section>

	<section class="dak-home-section dak-home-trust">
		<div class="dak-home-trust-grid">
			<?php foreach ( $dak_home_trust_points as $dak_point ) : ?>
				<div class="dak-home-trust-card">
					<span class="dak-home-trust-icon" aria-hidden="true"><?php echo $dak_home_icons[ $dak_point['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3><?php echo esc_html( $dak_point['title'] ); ?></h3>
					<p><?php echo esc_html( $dak_point['text'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<?php if ( ! empty( $services_html ) ) : ?>
		<section class="dak-home-section dak-home-services">
			<div class="dak-directory-header">
				<span class="dak-eyebrow"><?php esc_html_e( 'What We Treat', 'doctor-ak-portal' ); ?></span>
				<h2><?php esc_html_e( 'Focused Care for Every Part of the Digestive System', 'doctor-ak-portal' ); ?></h2>
			</div>

			<div class="dak-directory-grid">
				<?php foreach ( $services_html as $dak_card_html ) : ?>
					<?php echo $dak_card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
				<?php endforeach; ?>
			</div>

			<?php if ( $services_url ) : ?>
				<div class="dak-home-section-footer">
					<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $services_url ); ?>">
						<?php esc_html_e( 'View All Services', 'doctor-ak-portal' ); ?>
						<?php echo $dak_home_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $doctors_html ) ) : ?>
		<section class="dak-home-section dak-home-doctors">
			<div class="dak-featured-doctors-header">
				<div>
					<span class="dak-eyebrow"><?php esc_html_e( 'Our Specialists', 'doctor-ak-portal' ); ?></span>
					<h2><?php esc_html_e( 'Doctors Patients Come Back To', 'doctor-ak-portal' ); ?></h2>
				</div>

				<?php if ( $directory_url ) : ?>
					<a class="dak-button dak-button-secondary dak-featured-doctors-view-more" href="<?php echo esc_url( $directory_url ); ?>">
						<?php esc_html_e( 'View All Doctors', 'doctor-ak-portal' ); ?>
						<?php echo $dak_home_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				<?php endif; ?>
			</div>

			<div class="dak-home-doctors-grid">
				<?php foreach ( $doctors_html as $dak_card_html ) : ?>
					<?php echo $dak_card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="dak-home-section dak-home-why">
		<div class="dak-directory-header">
			<span class="dak-eyebrow"><?php esc_html_e( 'Why Choose Us', 'doctor-ak-portal' ); ?></span>
			<h2><?php esc_html_e( 'Care Built Around a Clear Diagnosis, Not a Rushed One', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-home-why-grid">
			<?php foreach ( $dak_home_trust_points as $dak_point ) : ?>
				<div class="dak-home-why-item">
					<span class="dak-home-why-icon" aria-hidden="true"><?php echo $dak_home_icons[ $dak_point['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3><?php echo esc_html( $dak_point['title'] ); ?></h3>
					<p><?php echo esc_html( $dak_point['text'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="dak-home-testimonial-band">
		<div class="dak-home-testimonial-band-inner">
			<div class="dak-home-testimonial-band-quotes">
				<div class="dak-home-testimonial-band-eyebrow-row">
					<span class="dak-eyebrow"><?php esc_html_e( 'Patient Stories', 'doctor-ak-portal' ); ?></span>

					<?php if ( $google_rating['total'] > 0 ) : ?>
						<span class="dak-home-google-rating-badge">
							<?php echo $dak_home_icons['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: rating out of 5, e.g. "4.8". 2: number of reviews. */
									__( '%1$s on Google (%2$s reviews)', 'doctor-ak-portal' ),
									number_format_i18n( $google_rating['rating'], 1 ),
									number_format_i18n( $google_rating['total'] )
								)
							);
							?>
						</span>
					<?php endif; ?>
				</div>

				<?php foreach ( $dak_home_testimonials as $dak_testimonial ) : ?>
					<div class="dak-home-testimonial-card">
						<?php if ( ! empty( $dak_testimonial['rating'] ) ) : ?>
							<span class="dak-home-testimonial-stars" aria-hidden="true">
								<?php for ( $dak_star = 0; $dak_star < 5; $dak_star++ ) : ?>
									<span class="<?php echo $dak_star < (int) $dak_testimonial['rating'] ? 'is-filled' : ''; ?>"><?php echo $dak_home_icons['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php endfor; ?>
							</span>
						<?php endif; ?>
						<p>&ldquo;<?php echo esc_html( $dak_testimonial['quote'] ); ?>&rdquo;</p>
						<span class="dak-home-testimonial-band-author">
							<?php
							echo esc_html(
								! empty( $dak_testimonial['attribution'] )
									? sprintf( '— %1$s, %2$s', $dak_testimonial['name'], $dak_testimonial['attribution'] )
									: '— ' . $dak_testimonial['name']
							);
							?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="dak-home-testimonial-band-stats">
				<div>
					<strong><?php echo esc_html( number_format_i18n( $stats['patients_count'] ) ); ?>+</strong>
					<span><?php esc_html_e( 'Patients cared for', 'doctor-ak-portal' ); ?></span>
				</div>
				<div>
					<strong><?php echo esc_html( number_format_i18n( $stats['max_years_experience'] ) ); ?>+</strong>
					<span><?php esc_html_e( 'Years serving Karachi', 'doctor-ak-portal' ); ?></span>
				</div>
				<div>
					<strong><?php echo esc_html( number_format_i18n( $stats['appointments_count'] ) ); ?>+</strong>
					<span><?php esc_html_e( 'Appointments completed', 'doctor-ak-portal' ); ?></span>
				</div>
			</div>
		</div>
	</section>

	<?php if ( ! empty( $clinic_locations ) ) : ?>
		<section class="dak-home-section dak-home-clinics" id="dak-home-clinics">
			<div class="dak-directory-header">
				<span class="dak-eyebrow"><?php esc_html_e( 'Visit Us', 'doctor-ak-portal' ); ?></span>
				<h2><?php echo esc_html( sprintf( /* translators: %d: number of clinics. */ _n( 'Our Clinic Across Karachi', 'Our %d Clinics Across Karachi', $stats['clinics_count'], 'doctor-ak-portal' ), $stats['clinics_count'] ) ); ?></h2>
			</div>

			<div class="dak-home-clinics-grid">
				<?php foreach ( $clinic_locations as $dak_clinic ) : ?>
					<div class="dak-home-clinic-card">
						<strong><?php echo esc_html( $dak_clinic['name'] ); ?></strong>
						<?php if ( '' !== $dak_clinic['address'] || '' !== $dak_clinic['area_label'] || '' !== $dak_clinic['city_label'] ) : ?>
							<span class="dak-home-clinic-meta">
								<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_home_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php echo esc_html( implode( ', ', array_filter( array( $dak_clinic['address'], $dak_clinic['area_label'], $dak_clinic['city_label'] ) ) ) ); ?>
							</span>
						<?php endif; ?>
						<?php if ( '' !== $dak_clinic['phone'] ) : ?>
							<span class="dak-home-clinic-meta">
								<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_home_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php echo esc_html( $dak_clinic['phone'] ); ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $marketing_videos ) || ! empty( $videos ) ) : ?>
		<section class="dak-home-section dak-home-videos" id="dak-home-videos">
			<div class="dak-directory-header">
				<span class="dak-eyebrow"><?php esc_html_e( 'See More', 'doctor-ak-portal' ); ?></span>
				<h2><?php esc_html_e( 'More From Our Clinic', 'doctor-ak-portal' ); ?></h2>
			</div>

			<div class="dak-home-videos-grid">
				<?php foreach ( $marketing_videos as $dak_marketing_video_index => $dak_marketing_video_url ) : ?>
					<button
						type="button"
						class="dak-home-video-card"
						data-dak-home-video
						data-video-url="<?php echo esc_url( $dak_marketing_video_url ); ?>"
						data-video-title="<?php echo esc_attr( sprintf( /* translators: %d: video number. */ __( 'Marketing Video %d', 'doctor-ak-portal' ), $dak_marketing_video_index + 1 ) ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: video number. */ __( 'Play marketing video %d', 'doctor-ak-portal' ), $dak_marketing_video_index + 1 ) ); ?>"
					>
						<video src="<?php echo esc_url( $dak_marketing_video_url ); ?>" autoplay muted loop playsinline preload="auto"></video>
						<span class="dak-home-video-play" aria-hidden="true"><?php echo $dak_home_icons['play']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</button>
				<?php endforeach; ?>

				<?php foreach ( $videos as $dak_video ) : ?>
					<button
						type="button"
						class="dak-home-video-card"
						data-dak-home-video
						data-video-url="<?php echo esc_url( $dak_video['video_url'] ); ?>"
						data-video-title="<?php echo esc_attr( $dak_video['title'] ); ?>"
						aria-label="<?php echo esc_attr( '' !== $dak_video['title'] ? $dak_video['title'] : __( 'Play video', 'doctor-ak-portal' ) ); ?>"
					>
						<video src="<?php echo esc_url( $dak_video['video_url'] ); ?>" autoplay muted loop playsinline preload="auto"></video>
						<span class="dak-home-video-play" aria-hidden="true"><?php echo $dak_home_icons['play']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<?php if ( '' !== $dak_video['title'] ) : ?>
							<span class="dak-home-video-title"><?php echo esc_html( $dak_video['title'] ); ?></span>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>
		</section>

		<div class="dak-home-video-modal" id="dak-home-video-modal" aria-hidden="true">
			<div class="dak-home-video-modal-overlay" id="dak-home-video-modal-overlay"></div>

			<div class="dak-home-video-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-home-video-modal-title">
				<button type="button" class="dak-home-video-modal-close" id="dak-home-video-modal-close" aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
				<h2 id="dak-home-video-modal-title" class="dak-home-video-modal-title dak-hidden"></h2>
				<video id="dak-home-video-modal-player" controls playsinline></video>
			</div>
		</div>
	<?php endif; ?>

	<section class="dak-home-cta">
		<div class="dak-home-cta-inner">
			<div>
				<h2><?php esc_html_e( 'Ready to See a Specialist?', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'Same-day slots are usually available — choose a doctor and book in a minute.', 'doctor-ak-portal' ); ?></p>
			</div>
			<button type="button" class="dak-button dak-button-primary dak-button-lg" data-dak-book-appointment>
				<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
			</button>
		</div>
	</section>

</div>
