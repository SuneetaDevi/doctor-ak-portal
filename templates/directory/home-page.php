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
 * @var string[]   $doctors_html     Pre-rendered directory/home-doctor-card.php output, one per featured doctor.
 * @var string[]   $services_html    Pre-rendered directory/home-service-card.php output, one per featured service.
 * @var array      $specialties      Home_Page::specialties() rows — { slug, label, count, url } — only specializations a registered doctor actually has.
 * @var array      $cities           Home_Page::cities_in_use() rows — { slug, label, count } — only cities a registered doctor actually practises in, for the hero search modal's city quick-picks.
 * @var array      $videos           Home_Videos::get_all() rows — { title, video_url, poster_url } — admin-uploaded videos.
 * @var string   $hero_video_url   Bundled hero tour video URL (assets/videos/thumbnail.mp4), or '' if missing.
 * @var string   $hero_banner_url  Bundled hero banner photo URL (assets/images/doctor-banner.avif), or '' if missing.
 * @var string[] $marketing_videos Bundled marketing reel video URLs (assets/videos/video-1..6.mp4).
 * @var string   $directory_url    URL of the [doctors_directory] page, or '' if not found.
 * @var string   $doctor_register_url URL of the [doctor_register] page, or '' if not found — for the "Join as a Doctor" section.
 * @var string   $services_url     URL of the [services_directory] page, or '' if not found.
 * @var string   $clinics_url      URL of the [clinics_directory] page, or '' if not found.
 * @var string   $clinic_profile_url Base URL of the [clinic_profile_view] page, or '' if not found — each "Visit Us" card links here with `?clinic_id=`.
 * @var array    $stats            { doctors_count, patients_count, appointments_count, max_years_experience, clinics_count }.
 * @var array    $clinic_locations Clinic_Locations::get_all() rows (capped), for the "Visit Us" section.
 * @var string[] $blogs_html       Pre-rendered directory/blog-card.php output for the three newest published posts.
 * @var string   $blogs_url        URL of the [blogs_directory] page, or '' if not found.
 *
 * The hero search modal's live results search across everything at once —
 * doctors, services, specialities, and clinics — via window.dakHomeSearch
 * (wp_localize_script() in Home_Page::render()), filtered client-side (see
 * initHeroSearch() in doctor-ak-home.js):
 *   .doctors     — { name, specialty, avatarUrl, url, citySlugs } — every registered doctor; citySlugs also narrows this list by whichever city is selected in the Location field (the other three aren't location-specific, so aren't filtered by it).
 *   .services    — { name, category, keywords, url } — every active, bookable service.
 *   .specialties — { label, count, url } — every specialization at least one doctor has.
 *   .clinics     — { name, location, keywords, url } — every registered clinic location.
 * `keywords` on services/clinics is admin-only free text (never shown to
 * patients — see Services::sanitize_fields_from_request()/Clinic_Locations
 * ::sanitize_keywords()), matched against in renderResults() but never
 * passed into a result row's display.
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
	'chevron'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 4.5l5.5 5.5-5.5 5.5"/></svg>',
	'chevron_left' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.5 4.5l-5.5 5.5 5.5 5.5"/></svg>',
	'play'     => '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.5 4.3v11.4a1 1 0 0 0 1.53.85l9-5.7a1 1 0 0 0 0-1.7l-9-5.7A1 1 0 0 0 6.5 4.3z"/></svg>',
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'locate'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="2.2"/><path d="M10 2.5v2.3M10 15.2v2.3M17.5 10h-2.3M4.8 10H2.5"/></svg>',
	'phone'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h2.3l1 3.3-1.6 1.4a9 9 0 0 0 4.1 4.1l1.4-1.6 3.3 1v2.3c0 .8-.7 1.4-1.5 1.3C8.7 15 5 11.3 4.2 6c-.1-.8.5-1.5 1.3-1.5z"/></svg>',
	'search'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.8" cy="8.8" r="5.3"/><path d="M17 17l-3.8-3.8"/></svg>',
	'user'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.5 4-5 6.5-5s5.5 1.5 6.5 5"/></svg>',
	'arrow'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 10h12"/><path d="M11 5.5l4.5 4.5-4.5 4.5"/></svg>',
	'pulse'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 16.2S3.8 12.4 3.8 8.1A3.1 3.1 0 0 1 10 6.3a3.1 3.1 0 0 1 6.2 1.8c0 4.3-6.2 8.1-6.2 8.1z"/><path d="M6.5 10h2l1-2 1.5 4 1-2h1.5"/></svg>',
	'check'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>',
);

// Body-part glyphs for the "Consult online" specialty tiles, kept separate
// from the general icon set above since nothing else uses them.
$dak_home_specialty_icons = array(
	'heart'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 16.2S3.8 12.4 3.8 8.1A3.1 3.1 0 0 1 10 6.3a3.1 3.1 0 0 1 6.2 1.8c0 4.3-6.2 8.1-6.2 8.1z"/></svg>',
	'brain'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9.2 4.2a2 2 0 0 0-3.4 1.2 2 2 0 0 0-.9 3.3 2 2 0 0 0 1 3.2 2 2 0 0 0 3.3 1.4z"/><path d="M10.8 4.2a2 2 0 0 1 3.4 1.2 2 2 0 0 1 .9 3.3 2 2 0 0 1-1 3.2 2 2 0 0 1-3.3 1.4z"/><path d="M10 4.2v11.6"/></svg>',
	'stomach'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 3.8v3.9c0 2.3 1.5 3.3 3.2 3.6 1.9.3 2.9 1.2 2.9 2.6a2.6 2.6 0 0 1-5.2.2"/><path d="M5.6 3.8h3.8"/></svg>',
	'tooth'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.4 3.8c1.1 0 1.4.6 3.6.6s2.5-.6 3.6-.6c.9 0 1.4.9 1.4 2.3 0 1.8-.9 2.7-1.3 4.8-.3 1.7-.5 4.2-1.6 4.2-.9 0-.9-2.1-1.2-3.6-.2-.8-.5-1.2-.9-1.2s-.7.4-.9 1.2c-.3 1.5-.3 3.6-1.2 3.6-1.1 0-1.3-2.5-1.6-4.2C5.9 8.8 5 7.9 5 6.1c0-1.4.5-2.3 1.4-2.3z"/></svg>',
	'eye'         => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 10S5.6 5.5 10 5.5 17.5 10 17.5 10 14.4 14.5 10 14.5 2.5 10 2.5 10z"/><circle cx="10" cy="10" r="2.2"/></svg>',
	'bone'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13l6-6"/><circle cx="5.6" cy="14.4" r="2.1"/><circle cx="14.4" cy="5.6" r="2.1"/></svg>',
	'lungs'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3.5v6.2"/><path d="M10 9.7c0-1.2-.9-2-2-2-2 0-3.5 2.6-3.5 5.4 0 2 .6 3.4 1.8 3.4 1.4 0 3.7-1.3 3.7-3.2z"/><path d="M10 9.7c0-1.2.9-2 2-2 2 0 3.5 2.6 3.5 5.4 0 2-.6 3.4-1.8 3.4-1.4 0-3.7-1.3-3.7-3.2z"/></svg>',
	'baby'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="6.8" r="3.4"/><path d="M8.7 6.2h.01M11.3 6.2h.01"/><path d="M4.6 16.8c.8-2.7 2.9-4.3 5.4-4.3s4.6 1.6 5.4 4.3"/></svg>',
	'skin'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3.4s4.4 4.6 4.4 7.3a4.4 4.4 0 0 1-8.8 0C5.6 8 10 3.4 10 3.4z"/><path d="M8.4 11.2h.01M10.6 12.8h.01"/></svg>',
	'kidney'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.2 4.2c2.2 0 3.8 2.2 3.8 5.6s-1.6 6-3.8 6-3.4-2-3.4-5.7 1.2-5.9 3.4-5.9z"/><path d="M12 9.8h3.6"/></svg>',
	'stethoscope' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.6 3.4v3.9a3 3 0 0 0 6 0V3.4"/><path d="M4.2 3.4h2.6M10.4 3.4H13"/><path d="M8.6 10.3v1.9a3.6 3.6 0 0 0 7.2 0v-1.4"/><circle cx="15.8" cy="9" r="1.6"/></svg>',
);

// Picks a glyph from the slug, so every canonical specialization (and any
// custom one) lands on something sensible without a per-label config.
$dak_home_specialty_icon = function ( $slug ) use ( $dak_home_specialty_icons ) {
	$matches = array(
		'cardio'    => 'heart',
		'neuro'     => 'brain',
		'psych'     => 'brain',
		'gastro'    => 'stomach',
		'dent'      => 'tooth',
		'ophthal'   => 'eye',
		'orthop'    => 'bone',
		'rheumat'   => 'bone',
		'pulmon'    => 'lungs',
		'pediatric' => 'baby',
		'obstetric' => 'baby',
		'gyneco'    => 'baby',
		'dermat'    => 'skin',
		'nephro'    => 'kidney',
		'urolog'    => 'kidney',
	);

	foreach ( $matches as $dak_needle => $dak_icon ) {
		if ( false !== strpos( $slug, $dak_needle ) ) {
			return $dak_home_specialty_icons[ $dak_icon ];
		}
	}

	return $dak_home_specialty_icons['stethoscope'];
};

// Homepage quick-access cards, right below the hero — each links straight to
// an existing page/action rather than duplicating it, and only appears when
// that destination actually exists.
$dak_home_quick_links = array();

if ( $directory_url ) {
	$dak_home_quick_links[] = array(
		'icon'   => 'user',
		'title'  => __( 'Find a Doctor', 'doctor-ak-portal' ),
		'text'   => __( 'Browse specialists by condition', 'doctor-ak-portal' ),
		'url'    => $directory_url,
		'color'  => 'success',
		'is_book' => false,
	);
}

$dak_home_quick_links[] = array(
	'icon'    => 'video',
	'title'   => __( 'Video Consult', 'doctor-ak-portal' ),
	'text'    => __( 'Talk to a doctor from anywhere', 'doctor-ak-portal' ),
	'url'     => '',
	'color'   => 'teal',
	'is_book' => true,
);

if ( $services_url ) {
	$dak_home_quick_links[] = array(
		'icon'    => 'tag',
		'title'   => __( 'Our Services', 'doctor-ak-portal' ),
		'text'    => __( 'Fees & procedures, upfront', 'doctor-ak-portal' ),
		'url'     => $services_url,
		'color'   => 'amber',
		'is_book' => false,
	);
}

if ( ! empty( $clinic_locations ) ) {
	$dak_home_quick_links[] = array(
		'icon'    => 'pin',
		'title'   => __( 'Visit a Clinic', 'doctor-ak-portal' ),
		'text'    => sprintf(
			/* translators: %d: number of clinics. */
			_n( '%d location in Karachi', '%d locations in Karachi', $stats['clinics_count'], 'doctor-ak-portal' ),
			$stats['clinics_count']
		),
		'url'     => '#dak-home-clinics',
		'color'   => 'purple',
		'is_book' => false,
	);
}

// 'title'/'text' feed the compact strip under the video.
$dak_home_trust_points = array(
	array(
		'icon'   => 'shield',
		'title'  => __( 'Verified Specialists', 'doctor-ak-portal' ),
		'accent' => __( 'Specialists', 'doctor-ak-portal' ),
		'text'   => __( 'Every doctor here is a verified specialist in their field.', 'doctor-ak-portal' ),
		'points' => array(
			__( 'Credentials checked before a profile goes live', 'doctor-ak-portal' ),
			__( 'Specialty and years of experience shown on every card', 'doctor-ak-portal' ),
			__( 'Consultant-led care, not a rotating panel', 'doctor-ak-portal' ),
		),
	),
	array(
		'icon'   => 'clock',
		'title'  => __( 'Fast, Easy Booking', 'doctor-ak-portal' ),
		'accent' => __( 'Booking', 'doctor-ak-portal' ),
		'text'   => __( 'Book a clinic visit or video consultation in under a minute.', 'doctor-ak-portal' ),
		'points' => array(
			__( 'Pick a doctor, a slot and confirm in a few taps', 'doctor-ak-portal' ),
			__( 'Live availability — no waiting on a callback', 'doctor-ak-portal' ),
			__( 'Reschedule or cancel from your dashboard', 'doctor-ak-portal' ),
		),
	),
	array(
		'icon'   => 'video',
		'title'  => __( 'In-Person or Online', 'doctor-ak-portal' ),
		'accent' => __( 'Online', 'doctor-ak-portal' ),
		'text'   => __( 'Visit a clinic, or consult with your doctor over video from anywhere.', 'doctor-ak-portal' ),
		'points' => array(
			__( 'Secure video consultations from home', 'doctor-ak-portal' ),
			__( 'Prescriptions and reports saved to your record', 'doctor-ak-portal' ),
			__( 'Switch between clinic and online visits any time', 'doctor-ak-portal' ),
		),
	),
	array(
		'icon'   => 'tag',
		'title'  => __( 'Transparent Pricing', 'doctor-ak-portal' ),
		'accent' => __( 'Pricing', 'doctor-ak-portal' ),
		'text'   => __( 'Every service is quoted in PKR up front, before you book — no surprises.', 'doctor-ak-portal' ),
		'points' => array(
			__( 'Every fee shown in PKR before you confirm', 'doctor-ak-portal' ),
			__( 'No booking fees and no hidden charges', 'doctor-ak-portal' ),
			__( 'Itemised invoice and slip for every visit', 'doctor-ak-portal' ),
		),
	),
);

// The tile row shows the best-represented handful.
$dak_home_specialty_tiles = array_slice( $specialties, 0, 14 );

// Best-effort clinic phone for the "call us" block — the first location that
// has one, since the plugin has no separate global contact-number setting.
$dak_home_booking_phone = '';

foreach ( $clinic_locations as $dak_clinic_row ) {
	if ( '' !== $dak_clinic_row['phone'] ) {
		$dak_home_booking_phone = $dak_clinic_row['phone'];
		break;
	}
}

?>
<div class="dak-portal dak-home">

	<section class="dak-home-hero-banner">
		<div class="dak-home-hero-banner-media" aria-hidden="true">
			<?php if ( $hero_banner_url ) : ?>
				<img class="dak-home-hero-banner-img" src="<?php echo esc_url( $hero_banner_url ); ?>" alt="">
			<?php endif; ?>
			<div class="dak-home-hero-banner-overlay"></div>
		</div>

		<div class="dak-home-hero-banner-content">
			<span class="dak-home-hero-eyebrow">
				<?php echo $dak_home_specialty_icons['stomach']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Gastroenterology & Endoscopy · Karachi', 'doctor-ak-portal' ); ?>
			</span>
			<h1>
				<?php esc_html_e( 'Book your doctor,', 'doctor-ak-portal' ); ?>
				<em class="dak-home-hero-accent"><?php esc_html_e( 'in minutes.', 'doctor-ak-portal' ); ?></em>
			</h1>
			<p><?php esc_html_e( 'Find specialist gastroenterology, hepatology and advanced endoscopy care under one roof, and book your appointment online in a few clicks — no calls, no waiting.', 'doctor-ak-portal' ); ?></p>

			<div class="dak-home-hero-actions">
				<?php if ( $directory_url ) : ?>
					<a class="dak-button dak-button-primary dak-button-lg" href="<?php echo esc_url( $directory_url ); ?>">
						<?php esc_html_e( 'Meet Our Doctors', 'doctor-ak-portal' ); ?>
						<?php echo $dak_home_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				<?php endif; ?>
				<?php if ( $hero_video_url ) : ?>
					<button type="button" class="dak-button dak-button-lg dak-home-hero-watch" data-dak-home-video data-video-url="<?php echo esc_url( $hero_video_url ); ?>" data-video-title="<?php esc_attr_e( 'How to Book an Appointment', 'doctor-ak-portal' ); ?>">
						<svg viewBox="0 0 20 20" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.5"/><path d="M8.3 7l4.2 3-4.2 3z"/></svg>
						<?php esc_html_e( 'Watch Video', 'doctor-ak-portal' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<div class="dak-home-hero-stats">
			<div class="dak-home-hero-stat">
				<span class="dak-home-hero-stat-icon" aria-hidden="true"><?php echo $dak_home_icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="dak-home-hero-stat-text">
					<strong><?php echo esc_html( number_format_i18n( 100000 ) ); ?>+</strong>
					<span><?php esc_html_e( 'Patients Cared For', 'doctor-ak-portal' ); ?></span>
				</span>
			</div>
			<?php if ( $stats['doctors_count'] > 0 ) : ?>
				<div class="dak-home-hero-stat">
					<span class="dak-home-hero-stat-icon" aria-hidden="true"><?php echo $dak_home_specialty_icons['stethoscope']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-home-hero-stat-text">
						<strong><?php echo esc_html( number_format_i18n( $stats['doctors_count'] ) ); ?></strong>
						<span><?php esc_html_e( 'Verified Doctors', 'doctor-ak-portal' ); ?></span>
					</span>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $specialties ) ) : ?>
				<div class="dak-home-hero-stat">
					<span class="dak-home-hero-stat-icon" aria-hidden="true"><?php echo $dak_home_icons['pulse']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-home-hero-stat-text">
						<strong><?php echo esc_html( number_format_i18n( count( $specialties ) ) ); ?></strong>
						<span><?php esc_html_e( 'Specialities Covered', 'doctor-ak-portal' ); ?></span>
					</span>
				</div>
			<?php endif; ?>
			<div class="dak-home-hero-stat">
				<span class="dak-home-hero-stat-icon" aria-hidden="true"><?php echo $dak_home_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="dak-home-hero-stat-text">
					<strong><?php echo esc_html( number_format_i18n( 100 ) ); ?>+</strong>
					<span><?php esc_html_e( 'Clinics Across Pakistan', 'doctor-ak-portal' ); ?></span>
				</span>
			</div>
		</div>
	</section>

	<?php if ( $directory_url ) : ?>
		<div class="dak-home-hero-searchbar">
			<button type="button" class="dak-home-hero-search" id="dak-home-hero-search-trigger" aria-haspopup="dialog" aria-controls="dak-home-search-modal">
				<span class="dak-home-hero-search-field">
					<span class="dak-home-hero-search-icon" aria-hidden="true"><?php echo $dak_home_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-home-hero-search-control">
						<strong><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></strong>
						<em id="dak-home-hero-search-trigger-location"><?php esc_html_e( 'Any city', 'doctor-ak-portal' ); ?></em>
					</span>
				</span>
				<span class="dak-home-hero-search-field">
					<span class="dak-home-hero-search-icon" aria-hidden="true"><?php echo $dak_home_specialty_icons['stethoscope']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-home-hero-search-control">
						<strong><?php esc_html_e( 'Specialty / Doctor', 'doctor-ak-portal' ); ?></strong>
						<em><?php esc_html_e( 'e.g. Gastroenterology, doctor name…', 'doctor-ak-portal' ); ?></em>
					</span>
				</span>
				<span class="dak-button dak-button-primary dak-home-hero-search-submit">
					<?php echo $dak_home_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?>
				</span>
			</button>
		</div>
	<?php endif; ?>

	<?php if ( $directory_url ) : ?>
		<div class="dak-home-search-modal" id="dak-home-search-modal" aria-hidden="true">
			<div class="dak-home-search-modal-overlay" id="dak-home-search-modal-overlay"></div>

			<div class="dak-home-search-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-home-search-modal-title">
				<div class="dak-home-search-modal-header">
					<h2 id="dak-home-search-modal-title"><?php esc_html_e( 'Search doctors, services & more', 'doctor-ak-portal' ); ?></h2>
					<button type="button" class="dak-home-search-modal-close" id="dak-home-search-modal-close" aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
				</div>

				<form class="dak-home-search-modal-form" method="get" action="<?php echo esc_url( $directory_url ); ?>">
					<div class="dak-home-search-modal-row">
						<div class="dak-home-search-modal-location">
							<span class="dak-home-hero-search-icon" aria-hidden="true"><?php echo $dak_home_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<input
								type="text"
								id="dak-home-search-modal-location-input"
								placeholder="<?php esc_attr_e( 'Search or select a city', 'doctor-ak-portal' ); ?>"
								data-detecting-placeholder="<?php esc_attr_e( 'Detecting your city…', 'doctor-ak-portal' ); ?>"
								autocomplete="off"
							>
							<input type="hidden" name="city" id="dak-home-search-modal-city">
							<button
								type="button"
								class="dak-home-hero-search-detect"
								id="dak-home-search-modal-detect"
								aria-label="<?php esc_attr_e( 'Detect my location', 'doctor-ak-portal' ); ?>"
								title="<?php esc_attr_e( 'Detect my location', 'doctor-ak-portal' ); ?>"
							>
								<?php echo $dak_home_icons['locate']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php esc_html_e( 'Detect', 'doctor-ak-portal' ); ?>
							</button>
						</div>

						<div class="dak-home-search-modal-query">
							<span class="dak-home-hero-search-icon" aria-hidden="true"><?php echo $dak_home_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<input
								type="text"
								name="s"
								id="dak-home-search-modal-query-input"
								placeholder="<?php esc_attr_e( 'Search doctors, services, specialities, clinics…', 'doctor-ak-portal' ); ?>"
								autocomplete="off"
							>
							<button
								type="button"
								class="dak-home-search-modal-query-clear dak-hidden"
								id="dak-home-search-modal-query-clear"
								aria-label="<?php esc_attr_e( 'Clear search', 'doctor-ak-portal' ); ?>"
							>&times;</button>
						</div>
					</div>

					<?php if ( ! empty( $cities ) ) : ?>
						<div class="dak-home-search-modal-cities" id="dak-home-search-modal-cities">
							<?php foreach ( $cities as $dak_city ) : ?>
								<button
									type="button"
									class="dak-home-search-modal-city"
									data-city-slug="<?php echo esc_attr( $dak_city['slug'] ); ?>"
									data-city-label="<?php echo esc_attr( $dak_city['label'] ); ?>"
								>
									<span aria-hidden="true"><?php echo $dak_home_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php echo esc_html( $dak_city['label'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<div class="dak-home-search-modal-results dak-hidden" id="dak-home-search-modal-results">
						<div id="dak-home-search-modal-results-groups"></div>
						<p class="dak-home-search-modal-no-results dak-hidden" id="dak-home-search-modal-no-results">
							<?php esc_html_e( 'No matches — try a different name, specialty, service, or clinic.', 'doctor-ak-portal' ); ?>
						</p>
					</div>

					<button type="submit" class="dak-button dak-button-primary dak-home-search-modal-submit">
						<?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?>
					</button>
				</form>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $dak_home_quick_links ) ) : ?>
		<section class="dak-home-section dak-home-quick-links">
			<h2 class="dak-home-quick-links-title"><?php esc_html_e( 'What Would You Like To Do?', 'doctor-ak-portal' ); ?></h2>
			<div class="dak-home-quick-links-grid">
				<?php foreach ( $dak_home_quick_links as $dak_link ) : ?>
					<?php if ( $dak_link['is_book'] ) : ?>
						<button type="button" class="dak-home-quick-link-card" data-dak-book-appointment data-booking-type="video">
							<span class="dak-home-quick-link-icon dak-home-quick-link-icon-<?php echo esc_attr( $dak_link['color'] ); ?>" aria-hidden="true"><?php echo $dak_home_icons[ $dak_link['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="dak-home-quick-link-title"><?php echo esc_html( $dak_link['title'] ); ?></span>
							<span class="dak-home-quick-link-text"><?php echo esc_html( $dak_link['text'] ); ?></span>
							<span class="dak-home-quick-link-arrow" aria-hidden="true"><?php echo $dak_home_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</button>
					<?php else : ?>
						<a class="dak-home-quick-link-card" href="<?php echo esc_url( $dak_link['url'] ); ?>">
							<span class="dak-home-quick-link-icon dak-home-quick-link-icon-<?php echo esc_attr( $dak_link['color'] ); ?>" aria-hidden="true"><?php echo $dak_home_icons[ $dak_link['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="dak-home-quick-link-title"><?php echo esc_html( $dak_link['title'] ); ?></span>
							<span class="dak-home-quick-link-text"><?php echo esc_html( $dak_link['text'] ); ?></span>
							<span class="dak-home-quick-link-arrow" aria-hidden="true"><?php echo $dak_home_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $hero_video_url ) : ?>
		<section class="dak-home-section dak-home-video-tour">
			<div class="dak-home-video-tour-heading">
				<div>
					<span class="dak-eyebrow"><?php esc_html_e( 'How It Works', 'doctor-ak-portal' ); ?></span>
					<h2 class="dak-home-serif"><?php esc_html_e( 'Booking an appointment, start to finish.', 'doctor-ak-portal' ); ?></h2>
				</div>
				<p><?php esc_html_e( 'A short walkthrough of the whole process — choosing your doctor, picking a time that suits you, and confirming your appointment in a couple of minutes.', 'doctor-ak-portal' ); ?></p>
			</div>

			<div class="dak-home-video-tour-panel">
				<button
					type="button"
					class="dak-home-hero-visual"
					data-dak-home-video
					data-video-url="<?php echo esc_url( $hero_video_url ); ?>"
					data-video-title="<?php esc_attr_e( 'How to Book an Appointment', 'doctor-ak-portal' ); ?>"
					aria-label="<?php esc_attr_e( 'Play the booking walkthrough video', 'doctor-ak-portal' ); ?>"
				>
					<video
						class="dak-home-hero-visual-video"
						src="<?php echo esc_url( $hero_video_url ); ?>"
						autoplay
						muted
						loop
						playsinline
						preload="auto"
					></video>
				</button>

				<div class="dak-home-video-tour-features">
					<?php foreach ( $dak_home_trust_points as $dak_point ) : ?>
						<div class="dak-home-video-tour-feature">
							<span class="dak-home-video-tour-feature-icon" aria-hidden="true"><?php echo $dak_home_icons[ $dak_point['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<div>
								<strong><?php echo esc_html( $dak_point['title'] ); ?></strong>
								<span><?php echo esc_html( $dak_point['text'] ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $dak_home_specialty_tiles ) ) : ?>
		<section class="dak-home-section dak-home-specialties">
			<div class="dak-directory-header dak-home-specialties-header">
				<span class="dak-eyebrow"><?php esc_html_e( 'Online Consultation', 'doctor-ak-portal' ); ?></span>
				<h2><?php esc_html_e( 'Consult Top Doctors Online For Any Health Concern', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'Private video consultations with verified specialists — pick a specialty to see who is available.', 'doctor-ak-portal' ); ?></p>
			</div>

			<div class="dak-home-specialties-grid">
				<?php foreach ( $dak_home_specialty_tiles as $dak_specialty ) : ?>
					<a class="dak-home-specialty-card" href="<?php echo esc_url( $dak_specialty['url'] ); ?>">
						<span class="dak-home-specialty-icon" aria-hidden="true"><?php echo $dak_home_specialty_icon( $dak_specialty['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="dak-home-specialty-label"><?php echo esc_html( $dak_specialty['label'] ); ?></span>
						<span class="dak-home-specialty-action"><?php esc_html_e( 'Consult Now', 'doctor-ak-portal' ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( $directory_url ) : ?>
				<div class="dak-home-section-footer">
					<a class="dak-button dak-button-primary" href="<?php echo esc_url( $directory_url ); ?>">
						<?php esc_html_e( 'See All Specialities', 'doctor-ak-portal' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $services_html ) ) : ?>
		<section class="dak-home-section dak-home-services">
			<div class="dak-directory-header dak-home-services-header">
				<h2><?php esc_html_e( 'Our', 'doctor-ak-portal' ); ?> <span class="dak-home-services-accent"><?php esc_html_e( 'Healthcare', 'doctor-ak-portal' ); ?></span> <?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'Comprehensive healthcare solutions for you and your family.', 'doctor-ak-portal' ); ?></p>
			</div>

			<div class="dak-home-services-list">
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
					<span class="dak-eyebrow">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.6 3.4v3.9a3 3 0 0 0 6 0V3.4"/><path d="M4.2 3.4h2.6M10.4 3.4H13"/><path d="M8.6 10.3v1.9a3.6 3.6 0 0 0 7.2 0v-1.4"/><circle cx="15.8" cy="9" r="1.6"/></svg>
						<?php esc_html_e( 'Our Specialists', 'doctor-ak-portal' ); ?>
					</span>
					<h2><?php esc_html_e( 'Doctors Available Now', 'doctor-ak-portal' ); ?></h2>
					<p><?php esc_html_e( 'Our experienced and compassionate doctors are here to provide you with high-quality care and personalized treatment.', 'doctor-ak-portal' ); ?></p>
				</div>

				<?php if ( $directory_url ) : ?>
					<a class="dak-button dak-button-secondary dak-featured-doctors-view-more" href="<?php echo esc_url( $directory_url ); ?>">
						<?php esc_html_e( 'View All Doctors', 'doctor-ak-portal' ); ?>
						<?php echo $dak_home_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				<?php endif; ?>
			</div>

			<div class="dak-featured-doctors-slider">
				<button type="button" class="dak-featured-doctors-nav dak-featured-doctors-prev" id="dak-featured-doctors-prev" aria-label="<?php esc_attr_e( 'Previous doctors', 'doctor-ak-portal' ); ?>">
					<?php echo $dak_home_icons['chevron_left']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>

				<div class="dak-featured-doctors-track" id="dak-featured-doctors-track" data-loop>
					<?php foreach ( $doctors_html as $dak_card_html ) : ?>
						<div class="dak-featured-doctors-slide">
							<?php echo $dak_card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
						</div>
					<?php endforeach; ?>
				</div>

				<button type="button" class="dak-featured-doctors-nav dak-featured-doctors-next" id="dak-featured-doctors-next" aria-label="<?php esc_attr_e( 'Next doctors', 'doctor-ak-portal' ); ?>">
					<?php echo $dak_home_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
			</div>

			<div class="dak-home-doctors-dots" id="dak-featured-doctors-dots" aria-hidden="true"></div>

			<?php if ( $directory_url ) : ?>
				<a class="dak-button dak-button-primary dak-featured-doctors-view-more-mobile" href="<?php echo esc_url( $directory_url ); ?>">
					<?php esc_html_e( 'View All Doctors', 'doctor-ak-portal' ); ?>
				</a>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $clinic_locations ) ) : ?>
		<section class="dak-home-section dak-home-clinics" id="dak-home-clinics">
			<div class="dak-directory-header dak-home-clinics-header">
				<span class="dak-eyebrow">
					<?php echo $dak_home_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Visit Us', 'doctor-ak-portal' ); ?>
				</span>
				<h2><?php echo esc_html( sprintf( /* translators: %d: number of clinics. */ _n( 'Our Clinic Across Karachi', 'Our %d Clinics Across Karachi', $stats['clinics_count'], 'doctor-ak-portal' ), $stats['clinics_count'] ) ); ?></h2>
				<p><?php esc_html_e( 'Trusted locations near you', 'doctor-ak-portal' ); ?></p>
			</div>

			<?php
			// A continuously scrolling ticker: one cycle of cards (repeated until it
			// is comfortably wider than the screen) rendered twice back to back, so
			// the CSS animation can slide exactly half the track and loop seamlessly.
			$dak_clinic_cycle = array();
			while ( count( $dak_clinic_cycle ) < 8 ) {
				$dak_clinic_cycle = array_merge( $dak_clinic_cycle, $clinic_locations );
			}
			?>
			<div class="dak-home-clinics-marquee">
				<div class="dak-home-clinics-track" style="--dak-marquee-duration: <?php echo esc_attr( count( $dak_clinic_cycle ) * 4 ); ?>s;">
					<?php foreach ( array( false, true ) as $dak_is_duplicate ) : ?>
						<?php foreach ( $dak_clinic_cycle as $dak_clinic ) : ?>
							<?php
							$dak_clinic_card_url = $clinic_profile_url ? add_query_arg( 'clinic_id', $dak_clinic['id'], $clinic_profile_url ) : '';
							$dak_clinic_subtitle = implode( ', ', array_filter( array( $dak_clinic['area_label'], $dak_clinic['city_label'] ) ) );
							if ( '' === $dak_clinic_subtitle ) {
								$dak_clinic_subtitle = $dak_clinic['address'];
							}
							$dak_clinic_card_attrs = $dak_is_duplicate ? ' aria-hidden="true" tabindex="-1"' : '';
							?>
							<?php if ( $dak_clinic_card_url ) : ?>
								<a class="dak-home-clinic-card" href="<?php echo esc_url( $dak_clinic_card_url ); ?>"<?php echo $dak_clinic_card_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php else : ?>
								<div class="dak-home-clinic-card"<?php echo $dak_is_duplicate ? ' aria-hidden="true"' : ''; ?>>
							<?php endif; ?>
								<span class="dak-home-clinic-avatar" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $dak_clinic['name'], 0, 1 ) ) ); ?></span>
								<strong><?php echo esc_html( $dak_clinic['name'] ); ?></strong>
								<?php if ( '' !== $dak_clinic_subtitle ) : ?>
									<span class="dak-home-clinic-sub"><?php echo esc_html( $dak_clinic_subtitle ); ?></span>
								<?php endif; ?>
							<?php echo $dak_clinic_card_url ? '</a>' : '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( $clinics_url ) : ?>
				<div class="dak-home-section-footer">
					<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $clinics_url ); ?>">
						<?php esc_html_e( 'View All Clinics', 'doctor-ak-portal' ); ?>
						<?php echo $dak_home_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $marketing_videos ) || ! empty( $videos ) ) : ?>
		<section class="dak-home-section dak-home-videos" id="dak-home-videos">
			<div class="dak-home-videos-inner">
			<div class="dak-home-videos-header">
				<span class="dak-home-videos-live"><?php esc_html_e( 'Inside our clinics', 'doctor-ak-portal' ); ?></span>
				<h2><?php esc_html_e( 'More From', 'doctor-ak-portal' ); ?> <em><?php esc_html_e( 'Our Clinic', 'doctor-ak-portal' ); ?></em></h2>
				<p><?php esc_html_e( 'Real moments from our clinics. Tap any clip to watch it full screen, with sound.', 'doctor-ak-portal' ); ?></p>
			</div>

			<div class="dak-home-videos-slider">
				<button type="button" class="dak-home-videos-nav dak-home-videos-prev" id="dak-home-videos-prev" aria-label="<?php esc_attr_e( 'Previous videos', 'doctor-ak-portal' ); ?>">
					<?php echo $dak_home_icons['chevron_left']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>

				<div class="dak-home-videos-grid" id="dak-home-videos-track" data-loop>
					<?php foreach ( $marketing_videos as $dak_marketing_video_index => $dak_marketing_video_url ) : ?>
						<button
							type="button"
							class="dak-home-video-card"
							data-dak-home-video
							data-video-url="<?php echo esc_url( $dak_marketing_video_url ); ?>"
							data-video-title="<?php echo esc_attr( sprintf( /* translators: %d: video number. */ __( 'Marketing Video %d', 'doctor-ak-portal' ), $dak_marketing_video_index + 1 ) ); ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: video number. */ __( 'Play marketing video %d', 'doctor-ak-portal' ), $dak_marketing_video_index + 1 ) ); ?>"
						>
							<video src="<?php echo esc_url( $dak_marketing_video_url ); ?>" muted loop playsinline preload="metadata" data-gallery-video></video>
							<span class="dak-home-video-play" data-label="<?php esc_attr_e( 'Watch with sound', 'doctor-ak-portal' ); ?>" aria-hidden="true"><?php echo $dak_home_icons['play']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
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
							<video src="<?php echo esc_url( $dak_video['video_url'] ); ?>" muted loop playsinline preload="metadata" data-gallery-video></video>
							<span class="dak-home-video-play" data-label="<?php esc_attr_e( 'Watch with sound', 'doctor-ak-portal' ); ?>" aria-hidden="true"><?php echo $dak_home_icons['play']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php if ( '' !== $dak_video['title'] ) : ?>
								<span class="dak-home-video-title"><?php echo esc_html( $dak_video['title'] ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>

				<button type="button" class="dak-home-videos-nav dak-home-videos-next" id="dak-home-videos-next" aria-label="<?php esc_attr_e( 'Next videos', 'doctor-ak-portal' ); ?>">
					<?php echo $dak_home_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
			</div>

			<div class="dak-home-videos-dots" id="dak-home-videos-dots" aria-hidden="true"></div>
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

	<?php if ( ! empty( $blogs_html ) ) : ?>
		<section class="dak-home-section dak-home-blogs">
			<div class="dak-home-blogs-header">
				<div>
					<h2><?php esc_html_e( 'From Our Blog', 'doctor-ak-portal' ); ?></h2>
					<p><?php esc_html_e( 'Health tips, clinic news and articles from our doctors.', 'doctor-ak-portal' ); ?></p>
				</div>
				<?php if ( $blogs_url ) : ?>
					<a class="dak-home-blogs-viewall" href="<?php echo esc_url( $blogs_url ); ?>"><?php esc_html_e( 'View All', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</div>

			<div class="dak-blog-grid">
				<?php foreach ( $blogs_html as $dak_blog_card_html ) : ?>
					<?php echo $dak_blog_card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	// Frequently asked questions — plain <details> accordion (no script), and the
	// same list is echoed as FAQPage structured data so search engines can show
	// them. Edit the wording here; each entry is [ question, answer ].
	$dak_home_faqs = array(
		array(
			__( 'How do I book an appointment?', 'doctor-ak-portal' ),
			__( 'Pick a doctor (or a service), choose a clinic visit or a video consultation, then select a date and time that suits you. It takes under a minute, and you can do it from your phone.', 'doctor-ak-portal' ),
		),
		array(
			__( 'Can I consult a doctor online?', 'doctor-ak-portal' ),
			__( 'Yes. Doctors who offer video consultations are marked on their profile. Book a slot, then join a secure video call from your dashboard at your appointment time.', 'doctor-ak-portal' ),
		),
		array(
			__( 'How much will a consultation cost?', 'doctor-ak-portal' ),
			__( 'Every fee is shown in PKR before you confirm, so there are no surprises. You also get an itemised invoice for each visit.', 'doctor-ak-portal' ),
		),
		array(
			__( 'Can I reschedule or cancel my appointment?', 'doctor-ak-portal' ),
			__( 'Yes. Log in to your dashboard, open your appointments and change or cancel a booking from there.', 'doctor-ak-portal' ),
		),
		array(
			__( 'Where are your clinics?', 'doctor-ak-portal' ),
			__( 'We have clinics across Karachi and other cities. Open the Clinics page to see every location, its address and phone number, and the doctors available there.', 'doctor-ak-portal' ),
		),
		array(
			__( 'Where can I find my prescriptions and reports?', 'doctor-ak-portal' ),
			__( 'Prescriptions and reports from your visits are saved to your patient record, so you can open them any time from your dashboard.', 'doctor-ak-portal' ),
		),
		array(
			__( 'Is my personal and medical information safe?', 'doctor-ak-portal' ),
			__( 'Your details are only visible to you and the clinic team who care for you, and you sign in with your own account to see them.', 'doctor-ak-portal' ),
		),
	);
	?>
	<section class="dak-home-section dak-home-faq">
		<div class="dak-home-faq-intro">
			<span class="dak-eyebrow"><?php esc_html_e( 'FAQ', 'doctor-ak-portal' ); ?></span>
			<h2><?php esc_html_e( 'Frequently Asked Questions', 'doctor-ak-portal' ); ?></h2>
			<p><?php esc_html_e( 'Quick answers about booking, video consultations and your records.', 'doctor-ak-portal' ); ?></p>

			<?php if ( '' !== $dak_home_booking_phone ) : ?>
				<a class="dak-home-faq-call" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $dak_home_booking_phone ) ); ?>">
					<span class="dak-home-faq-call-icon" aria-hidden="true"><?php echo $dak_home_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span>
						<?php esc_html_e( 'Still have questions?', 'doctor-ak-portal' ); ?>
						<strong><?php echo esc_html( $dak_home_booking_phone ); ?></strong>
					</span>
				</a>
			<?php endif; ?>
		</div>

		<div class="dak-home-faq-list">
			<?php foreach ( $dak_home_faqs as $dak_faq_index => $dak_faq ) : ?>
				<details class="dak-home-faq-item"<?php echo 0 === $dak_faq_index ? ' open' : ''; ?>>
					<summary>
						<span><?php echo esc_html( $dak_faq[0] ); ?></span>
						<span class="dak-home-faq-toggle" aria-hidden="true"></span>
					</summary>
					<p><?php echo esc_html( $dak_faq[1] ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>

		<script type="application/ld+json">
			<?php
			echo wp_json_encode(
				array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => array_map(
						function ( $faq ) {
							return array(
								'@type'          => 'Question',
								'name'           => $faq[0],
								'acceptedAnswer' => array(
									'@type' => 'Answer',
									'text'  => $faq[1],
								),
							);
						},
						$dak_home_faqs
					),
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in a script block.
			?>
		</script>
	</section>

	<?php if ( $doctor_register_url ) : ?>
		<section class="dak-home-join-doctors">
			<div class="dak-home-join-doctors-inner">
				<div class="dak-home-join-doctors-copy">
					<span class="dak-eyebrow"><?php esc_html_e( 'For Doctors', 'doctor-ak-portal' ); ?></span>
					<h2 class="dak-home-serif"><?php esc_html_e( 'Grow your practice with us.', 'doctor-ak-portal' ); ?></h2>
					<p><?php esc_html_e( 'Join our growing network of specialists — set your own weekly hours, consult patients online or in-clinic, and track your earnings from your own dashboard.', 'doctor-ak-portal' ); ?></p>

					<a class="dak-button dak-button-primary dak-button-lg" href="<?php echo esc_url( $doctor_register_url ); ?>">
						<?php esc_html_e( 'Join as a Doctor', 'doctor-ak-portal' ); ?>
						<?php echo $dak_home_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>

					<?php if ( '' !== $dak_home_booking_phone ) : ?>
						<a class="dak-home-booking-contact" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $dak_home_booking_phone ) ); ?>">
							<span class="dak-home-booking-contact-icon" aria-hidden="true"><?php echo $dak_home_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span>
								<em><?php esc_html_e( 'Have questions? Call us', 'doctor-ak-portal' ); ?></em>
								<strong><?php echo esc_html( $dak_home_booking_phone ); ?></strong>
							</span>
						</a>
					<?php endif; ?>
				</div>

				<div class="dak-home-join-doctors-card">
					<h3><?php esc_html_e( 'Why doctors choose us', 'doctor-ak-portal' ); ?></h3>

					<ul class="dak-home-join-doctors-list">
						<li>
							<span class="dak-home-join-doctors-check" aria-hidden="true"><?php echo $dak_home_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Flexible weekly scheduling — you set your own hours', 'doctor-ak-portal' ); ?>
						</li>
						<li>
							<span class="dak-home-join-doctors-check" aria-hidden="true"><?php echo $dak_home_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Video and in-clinic consultations, your choice', 'doctor-ak-portal' ); ?>
						</li>
						<li>
							<span class="dak-home-join-doctors-check" aria-hidden="true"><?php echo $dak_home_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Transparent revenue share, tracked in your own dashboard', 'doctor-ak-portal' ); ?>
						</li>
						<li>
							<span class="dak-home-join-doctors-check" aria-hidden="true"><?php echo $dak_home_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Dedicated admin support for booking and billing', 'doctor-ak-portal' ); ?>
						</li>
					</ul>

					<a class="dak-button dak-button-secondary dak-button-block" href="<?php echo esc_url( $doctor_register_url ); ?>">
						<?php esc_html_e( 'Get Started', 'doctor-ak-portal' ); ?>
					</a>
				</div>
			</div>
		</section>
	<?php endif; ?>

</div>
