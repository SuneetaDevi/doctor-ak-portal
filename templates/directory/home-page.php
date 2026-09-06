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
 * @var array      $specialties      Home_Page::specialties() rows — { slug, label, count, url } — only specializations a registered doctor actually has.
 * @var array      $videos           Home_Videos::get_all() rows — { title, video_url, poster_url } — admin-uploaded videos.
 * @var array      $testimonials     Home_Testimonials::get_all() rows merged with Google_Reviews::get_reviews() — { quote, name, attribution, rating? } — 'rating' (1-5) is only present on a Google review.
 * @var array      $google_rating    Google_Reviews::overall_rating() — { rating, total } — zeroed out if Google reviews aren't configured.
 * @var string   $hero_video_url   Bundled hero tour video URL (assets/videos/thumbnail.mp4), or '' if missing.
 * @var string   $hero_banner_url  Bundled hero banner photo URL (assets/images/doctor-banner.avif), or '' if missing.
 * @var string[] $marketing_videos Bundled marketing reel video URLs (assets/videos/video-1..3.mp4).
 * @var string   $directory_url    URL of the [doctors_directory] page, or '' if not found.
 * @var string   $doctor_register_url URL of the [doctor_register] page, or '' if not found — for the "Join as a Doctor" section.
 * @var string   $services_url     URL of the [services_directory] page, or '' if not found.
 * @var array    $stats            { doctors_count, patients_count, appointments_count, max_years_experience, clinics_count }.
 * @var array    $clinic_locations Clinic_Locations::get_all() rows (capped), for the "Visit Us" section.
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
	'flask'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2.5h4M8.4 2.5v4.6L4.3 14a1.6 1.6 0 0 0 1.4 2.5h8.6a1.6 1.6 0 0 0 1.4-2.5l-4.1-6.9V2.5"/><path d="M6.2 12.3h7.6"/></svg>',
	'pill'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.8" y="7.2" width="14.4" height="7.6" rx="3.8" transform="rotate(-45 10 10)"/><path d="M8.3 11.7l3.4-3.4"/></svg>',
	'star'     => '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.7l2.5 5.2 5.7.7-4.2 4 1 5.7-5-2.7-5 2.7 1-5.7-4.2-4 5.7-.7z"/></svg>',
	'chevron'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 4.5l5.5 5.5-5.5 5.5"/></svg>',
	'chevron_left' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.5 4.5l-5.5 5.5 5.5 5.5"/></svg>',
	'play'     => '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.5 4.3v11.4a1 1 0 0 0 1.53.85l9-5.7a1 1 0 0 0 0-1.7l-9-5.7A1 1 0 0 0 6.5 4.3z"/></svg>',
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'phone'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h2.3l1 3.3-1.6 1.4a9 9 0 0 0 4.1 4.1l1.4-1.6 3.3 1v2.3c0 .8-.7 1.4-1.5 1.3C8.7 15 5 11.3 4.2 6c-.1-.8.5-1.5 1.3-1.5z"/></svg>',
	'search'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.8" cy="8.8" r="5.3"/><path d="M17 17l-3.8-3.8"/></svg>',
	'user'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.5 4-5 6.5-5s5.5 1.5 6.5 5"/></svg>',
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
$dak_home_specialty_tiles = array_slice( $specialties, 0, 6 );

// Best-effort clinic phone for the "call us" block — the first location that
// has one, since the plugin has no separate global contact-number setting.
$dak_home_booking_phone = '';

foreach ( $clinic_locations as $dak_clinic_row ) {
	if ( '' !== $dak_clinic_row['phone'] ) {
		$dak_home_booking_phone = $dak_clinic_row['phone'];
		break;
	}
}

// Message shown when tapping the not-yet-built Lab/Pharmacy booking buttons —
// a simple stopgap (see data-dak-coming-soon handling in
// doctor-ak-site-header.js) until those modules are actually built.
$dak_home_coming_soon_note = static function ( $feature_label ) use ( $dak_home_booking_phone ) {
	if ( '' !== $dak_home_booking_phone ) {
		return sprintf(
			/* translators: 1: feature name, e.g. "Lab Test Booking". 2: clinic phone number. */
			__( '%1$s is launching soon. Call us to book today: %2$s', 'doctor-ak-portal' ),
			$feature_label,
			$dak_home_booking_phone
		);
	}

	return sprintf(
		/* translators: %s: feature name, e.g. "Lab Test Booking". */
		__( '%s is launching soon — check back soon.', 'doctor-ak-portal' ),
		$feature_label
	);
};

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
			<span class="dak-eyebrow"><?php esc_html_e( 'Gastroenterology & Endoscopy · Karachi', 'doctor-ak-portal' ); ?></span>
			<h1>
				<?php esc_html_e( 'Digestive health care that listens', 'doctor-ak-portal' ); ?>
				<em class="dak-home-hero-accent"><?php esc_html_e( 'first.', 'doctor-ak-portal' ); ?></em>
			</h1>
			<p><?php esc_html_e( 'Dr. AK Lohana Clinic brings specialist gastroenterology, hepatology and advanced endoscopy under one roof — with unhurried, plain-spoken consultations.', 'doctor-ak-portal' ); ?></p>

			<div class="dak-home-hero-actions">
			<div class="dak-home-hero-book-group">
				<button type="button" class="dak-home-hero-book-btn" data-dak-book-appointment>
					<span class="dak-home-hero-book-icon" aria-hidden="true"><?php echo $dak_home_icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php esc_html_e( 'Book Doctor', 'doctor-ak-portal' ); ?>
				</button>
				<?php if ( $services_url ) : ?>
					<a class="dak-home-hero-book-btn" href="<?php echo esc_url( $services_url ); ?>">
						<span class="dak-home-hero-book-icon" aria-hidden="true"><?php echo $dak_home_icons['tag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<?php esc_html_e( 'Book Service', 'doctor-ak-portal' ); ?>
					</a>
				<?php endif; ?>
				<button type="button" class="dak-home-hero-book-btn" data-dak-coming-soon="<?php echo esc_attr( $dak_home_coming_soon_note( __( 'Lab Test Booking', 'doctor-ak-portal' ) ) ); ?>">
					<span class="dak-home-hero-book-icon" aria-hidden="true"><?php echo $dak_home_icons['flask']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php esc_html_e( 'Book Lab', 'doctor-ak-portal' ); ?>
				</button>
				<button type="button" class="dak-home-hero-book-btn" data-dak-coming-soon="<?php echo esc_attr( $dak_home_coming_soon_note( __( 'Pharmacy Ordering', 'doctor-ak-portal' ) ) ); ?>">
					<span class="dak-home-hero-book-icon" aria-hidden="true"><?php echo $dak_home_icons['pill']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php esc_html_e( 'Book Pharmacy', 'doctor-ak-portal' ); ?>
				</button>
			</div>

			<?php if ( $directory_url ) : ?>
				<a class="dak-button dak-button-secondary dak-button-lg dak-home-hero-banner-outline" href="<?php echo esc_url( $directory_url ); ?>">
					<?php esc_html_e( 'Meet Our Doctors', 'doctor-ak-portal' ); ?>
				</a>
			<?php endif; ?>
			</div>
		</div>

		<div class="dak-home-hero-banner-stats dak-home-hero-banner-stats-2">
			<div>
				<strong><?php echo esc_html( number_format_i18n( 100000 ) ); ?>+</strong>
				<span><?php esc_html_e( 'Patients Cared For', 'doctor-ak-portal' ); ?></span>
			</div>
			<div>
				<strong><?php echo esc_html( number_format_i18n( 100 ) ); ?>+</strong>
				<span><?php esc_html_e( 'Clinics Across Pakistan', 'doctor-ak-portal' ); ?></span>
			</div>
		</div>
	</section>

	<?php if ( $directory_url ) : ?>
		<a class="dak-home-hero-search" href="<?php echo esc_url( $directory_url ); ?>">
			<span class="dak-home-hero-search-field">
				<span class="dak-home-hero-search-icon" aria-hidden="true"><?php echo $dak_home_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span>
					<strong><?php esc_html_e( 'Treatment or Specialist', 'doctor-ak-portal' ); ?></strong>
					<em><?php esc_html_e( 'Search by condition or doctor', 'doctor-ak-portal' ); ?></em>
				</span>
			</span>
			<span class="dak-home-hero-search-field">
				<span class="dak-home-hero-search-icon" aria-hidden="true"><?php echo $dak_home_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span>
					<strong><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></strong>
					<em><?php esc_html_e( 'Any clinic in Karachi', 'doctor-ak-portal' ); ?></em>
				</span>
			</span>
			<span class="dak-home-hero-search-field">
				<span class="dak-home-hero-search-icon" aria-hidden="true"><?php echo $dak_home_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span>
					<strong><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></strong>
					<em><?php esc_html_e( 'Choose a day that works', 'doctor-ak-portal' ); ?></em>
				</span>
			</span>
			<span class="dak-button dak-button-primary dak-home-hero-search-submit">
				<?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?>
			</span>
		</a>
	<?php endif; ?>

	<?php if ( ! empty( $dak_home_quick_links ) ) : ?>
		<section class="dak-home-section dak-home-quick-links">
			<div class="dak-home-quick-links-grid">
				<?php foreach ( $dak_home_quick_links as $dak_link ) : ?>
					<?php if ( $dak_link['is_book'] ) : ?>
						<button type="button" class="dak-home-quick-link-card" data-dak-book-appointment data-booking-type="video">
							<span class="dak-home-quick-link-icon dak-home-quick-link-icon-<?php echo esc_attr( $dak_link['color'] ); ?>" aria-hidden="true"><?php echo $dak_home_icons[ $dak_link['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="dak-home-quick-link-title"><?php echo esc_html( $dak_link['title'] ); ?></span>
							<span class="dak-home-quick-link-text"><?php echo esc_html( $dak_link['text'] ); ?></span>
						</button>
					<?php else : ?>
						<a class="dak-home-quick-link-card" href="<?php echo esc_url( $dak_link['url'] ); ?>">
							<span class="dak-home-quick-link-icon dak-home-quick-link-icon-<?php echo esc_attr( $dak_link['color'] ); ?>" aria-hidden="true"><?php echo $dak_home_icons[ $dak_link['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="dak-home-quick-link-title"><?php echo esc_html( $dak_link['title'] ); ?></span>
							<span class="dak-home-quick-link-text"><?php echo esc_html( $dak_link['text'] ); ?></span>
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
			<div class="dak-directory-header">
				<span class="dak-eyebrow"><?php esc_html_e( 'What We Treat', 'doctor-ak-portal' ); ?></span>
				<h2><?php esc_html_e( 'Focused Care for Every Part of the Digestive System', 'doctor-ak-portal' ); ?></h2>
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

			<div class="dak-featured-doctors-slider">
				<button type="button" class="dak-featured-doctors-nav dak-featured-doctors-prev" id="dak-featured-doctors-prev" aria-label="<?php esc_attr_e( 'Previous doctors', 'doctor-ak-portal' ); ?>">
					<?php echo $dak_home_icons['chevron_left']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>

				<div class="dak-featured-doctors-track" id="dak-featured-doctors-track">
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

			<?php if ( $directory_url ) : ?>
				<a class="dak-button dak-button-primary dak-featured-doctors-view-more-mobile" href="<?php echo esc_url( $directory_url ); ?>">
					<?php esc_html_e( 'View All Doctors', 'doctor-ak-portal' ); ?>
				</a>
			<?php endif; ?>
		</section>
	<?php endif; ?>

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
						<video src="<?php echo esc_url( $dak_marketing_video_url ); ?>" muted playsinline preload="metadata"></video>
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
						<video src="<?php echo esc_url( $dak_video['video_url'] ); ?>" muted playsinline preload="metadata"></video>
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
