<?php
/**
 * Template: Site-wide header, rendered on every front-end page via wp_body_open.
 *
 * Two parts, emitted as siblings so only the second one sticks:
 *  - .dak-site-topbar — a slim strip (phone, email, location, dark-mode
 *    toggle, account) that scrolls away with the page;
 *  - header.dak-site-header — the sticky main bar: logo, nav, Book Now.
 *
 * Doctors / Services / Clinics / Book Now are disclosure buttons (they never
 * navigate) that open one panel each; every panel has its own "View all"
 * link to the matching directory. Behaviour (click/tap, hover intent, one
 * open at a time, Escape/outside click, mobile drawer, in-panel search) lives
 * in assets/js/doctor-ak-site-header.js. Without JavaScript the panels stay
 * closed, and the "View all" destinations are still reachable from the
 * directories' own pages and the footer.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string   $logo_url           Bundled logo URL (assets/images/logo.*), or '' if none was placed there.
 * @var string   $phone              Contact phone number (first clinic location with one on file), or ''.
 * @var string   $location           "City, Country" of the first clinic location with a city on file, or ''.
 * @var string   $email              Contact email (first clinic location with one on file), or ''.
 * @var string   $directory_url      [doctors_directory] page URL, or ''. Reads ?q= (name/specialty search) and ?specialization=.
 * @var string   $services_url       [services_directory] page URL, or ''.
 * @var string   $videos_url         Home page's videos section anchor.
 * @var string   $clinics_url        [clinics_directory] page URL, or ''. Reads ?q= (name/address search) and ?city=.
 * @var string   $blogs_url          [blogs_directory] page URL, or ''.
 * @var string   $booking_url        [book_appointment] page URL, or ''.
 * @var string   $gallery_url        Published "gallery" page URL, or '' (then the item is left out).
 * @var array    $doctor_specialties Home_Page::specialties_in_use() — { slug, label, count, url }.
 * @var array    $doctor_index       Site_Header::doctors_for_menu() — { name, specialty, search, url }.
 * @var array    $service_categories Site_Header::service_categories_for_menu() — { slug, label, services: [{ name, url }] }.
 * @var array    $clinics            Site_Header::clinics_for_menu() — { clinics: [{ name, place, city, url }], cities: [{ slug, label, count }] }.
 * @var array    $section_paths      Nav key => list of page URLs that count as "in" that section (directory + detail page).
 * @var string   $current_path       Site_Header::current_path() — current URL path, for the active-page indicator.
 * @var bool     $is_logged_in       Whether a user is logged in.
 * @var \WP_User $user               Current user (id 0 when logged out).
 * @var string   $user_avatar_url    Logged-in user's profile picture or default avatar.
 * @var string   $dashboard_url      Logged-in user's dashboard URL.
 * @var string   $profile_url        Logged-in user's Edit Profile URL ('' when not allowed).
 * @var string   $login_url          Login page URL (also links to Register from there).
 * @var string   $logout_url         Nonce-protected logout URL.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display_name = $is_logged_in ? ( $user->first_name ? $user->first_name : $user->display_name ) : '';

// Path-only comparison (query strings ignored), so a filtered directory
// (?specialization=…) or any doctor's profile (?doctor_id=…) still counts.
$dak_path_of = function ( $url ) {
	return '' === (string) $url ? null : untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
};

$dak_in_section = function ( $key ) use ( $section_paths, $current_path, $dak_path_of ) {
	if ( '' === $current_path || empty( $section_paths[ $key ] ) ) {
		return false;
	}

	foreach ( $section_paths[ $key ] as $dak_url ) {
		$dak_path = $dak_path_of( $dak_url );

		if ( null !== $dak_path && '' !== $dak_path && $dak_path === $current_path ) {
			return true;
		}
	}

	return false;
};

// A GET form drops its action URL's own query string, so any argument the
// page URL already carries (e.g. ?page_id=12 on plain permalinks) is
// repeated as a hidden field — the search lands on the same page it links to.
$dak_form_args = function ( $url, $skip ) {
	$dak_query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	$dak_args  = array();

	if ( '' !== $dak_query ) {
		parse_str( $dak_query, $dak_args );
	}

	foreach ( $dak_args as $dak_key => $dak_value ) {
		if ( $dak_key !== $skip && is_scalar( $dak_value ) ) {
			echo '<input type="hidden" name="' . esc_attr( $dak_key ) . '" value="' . esc_attr( $dak_value ) . '">';
		}
	}
};

$dak_icon = function ( $paths ) {
	return '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};

$dak_header_icons = array(
	'pin'      => $dak_icon( '<path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/>' ),
	'phone'    => $dak_icon( '<path d="M5 3.5h2.3l1 3.3-1.6 1.4a9 9 0 0 0 4.1 4.1l1.4-1.6 3.3 1v2.3c0 .8-.7 1.4-1.5 1.3C8.7 15 5 11.3 4.2 6c-.1-.8.5-1.5 1.3-1.5z"/>' ),
	'mail'     => $dak_icon( '<rect x="2.5" y="4.5" width="15" height="11" rx="1.5"/><path d="M3 5.5l7 5.5 7-5.5"/>' ),
	'chevron'  => $dak_icon( '<path d="M5.5 7.5l4.5 4.5 4.5-4.5"/>' ),
	'arrow'    => $dak_icon( '<path d="M3.5 10h12"/><path d="M11 5.5l4.5 4.5-4.5 4.5"/>' ),
	'user'     => $dak_icon( '<circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.5 4-5 6.5-5s5.5 1.5 6.5 5"/>' ),
	'search'   => $dak_icon( '<circle cx="8.8" cy="8.8" r="5.3"/><path d="M17 17l-3.8-3.8"/>' ),
	'x'        => $dak_icon( '<path d="M5.5 5.5l9 9M14.5 5.5l-9 9"/>' ),
	'menu'     => $dak_icon( '<path d="M3 5.5h14M3 10h14M3 14.5h14"/>' ),
	'calendar' => $dak_icon( '<rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/>' ),
	'tag'      => $dak_icon( '<path d="M10 2.5l6.5 6.5-7.5 7.5-6.5-6.5V3.5z"/><circle cx="6.5" cy="6.5" r="1.2"/>' ),
	'flask'    => $dak_icon( '<path d="M8 2.5h4M8.4 2.5v4.6L4.3 14a1.6 1.6 0 0 0 1.4 2.5h8.6a1.6 1.6 0 0 0 1.4-2.5l-4.1-6.9V2.5"/><path d="M6.2 12.3h7.6"/>' ),
	'pill'     => $dak_icon( '<rect x="2.8" y="7.2" width="14.4" height="7.6" rx="3.8" transform="rotate(-45 10 10)"/><path d="M8.3 11.7l3.4-3.4"/>' ),
	'scalpel'  => $dak_icon( '<path d="M16.2 3.8L8.5 11.5a2 2 0 0 0-.5.9L7.2 15l2.6-.8a2 2 0 0 0 .9-.5l7.7-7.7a1.4 1.4 0 0 0-2.2-2.2z"/><path d="M3.5 16.5l3-3"/>' ),
	'cross'    => $dak_icon( '<path d="M10 3.5v13M3.5 10h13"/>' ),
	'chat'     => $dak_icon( '<path d="M3 4.7h14v9H8.6L5 16.8v-3.1H3z"/>' ),
	'grid'     => $dak_icon( '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/>' ),
	'steth'    => $dak_icon( '<path d="M5.6 3.4v3.9a3 3 0 0 0 6 0V3.4"/><path d="M4.2 3.4h2.6M10.4 3.4H13"/><path d="M8.6 10.3v1.9a3.6 3.6 0 0 0 7.2 0v-1.4"/><circle cx="15.8" cy="9" r="1.6"/>' ),
	'building' => $dak_icon( '<rect x="4" y="2.5" width="12" height="15" rx="1"/><path d="M8 17.5v-3h4v3"/><path d="M7.5 6h1M11.5 6h1M7.5 9h1M11.5 9h1M7.5 12h1M11.5 12h1"/>' ),
	'sun'      => $dak_icon( '<circle cx="10" cy="10" r="3.5"/><path d="M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/>' ),
	'moon'     => $dak_icon( '<path d="M16.5 12.3A6.8 6.8 0 0 1 7.7 3.5a6.8 6.8 0 1 0 8.8 8.8z"/>' ),
);

// Same body-part glyphs + keyword matching as the home page's specialty tiles.
$dak_header_specialty_icons = array(
	'heart'   => $dak_icon( '<path d="M10 16.2S3.8 12.4 3.8 8.1A3.1 3.1 0 0 1 10 6.3a3.1 3.1 0 0 1 6.2 1.8c0 4.3-6.2 8.1-6.2 8.1z"/>' ),
	'brain'   => $dak_icon( '<path d="M9.2 4.2a2 2 0 0 0-3.4 1.2 2 2 0 0 0-.9 3.3 2 2 0 0 0 1 3.2 2 2 0 0 0 3.3 1.4z"/><path d="M10.8 4.2a2 2 0 0 1 3.4 1.2 2 2 0 0 1 .9 3.3 2 2 0 0 1-1 3.2 2 2 0 0 1-3.3 1.4z"/><path d="M10 4.2v11.6"/>' ),
	'stomach' => $dak_icon( '<path d="M7.5 3.8v3.9c0 2.3 1.5 3.3 3.2 3.6 1.9.3 2.9 1.2 2.9 2.6a2.6 2.6 0 0 1-5.2.2"/><path d="M5.6 3.8h3.8"/>' ),
	'tooth'   => $dak_icon( '<path d="M6.4 3.8c1.1 0 1.4.6 3.6.6s2.5-.6 3.6-.6c.9 0 1.4.9 1.4 2.3 0 1.8-.9 2.7-1.3 4.8-.3 1.7-.5 4.2-1.6 4.2-.9 0-.9-2.1-1.2-3.6-.2-.8-.5-1.2-.9-1.2s-.7.4-.9 1.2c-.3 1.5-.3 3.6-1.2 3.6-1.1 0-1.3-2.5-1.6-4.2C5.9 8.8 5 7.9 5 6.1c0-1.4.5-2.3 1.4-2.3z"/>' ),
	'eye'     => $dak_icon( '<path d="M2.5 10S5.6 5.5 10 5.5 17.5 10 17.5 10 14.4 14.5 10 14.5 2.5 10 2.5 10z"/><circle cx="10" cy="10" r="2.2"/>' ),
	'bone'    => $dak_icon( '<path d="M7 13l6-6"/><circle cx="5.6" cy="14.4" r="2.1"/><circle cx="14.4" cy="5.6" r="2.1"/>' ),
	'lungs'   => $dak_icon( '<path d="M10 3.5v6.2"/><path d="M10 9.7c0-1.2-.9-2-2-2-2 0-3.5 2.6-3.5 5.4 0 2 .6 3.4 1.8 3.4 1.4 0 3.7-1.3 3.7-3.2z"/><path d="M10 9.7c0-1.2.9-2 2-2 2 0 3.5 2.6 3.5 5.4 0 2-.6 3.4-1.8 3.4-1.4 0-3.7-1.3-3.7-3.2z"/>' ),
	'baby'    => $dak_icon( '<circle cx="10" cy="6.8" r="3.4"/><path d="M8.7 6.2h.01M11.3 6.2h.01"/><path d="M4.6 16.8c.8-2.7 2.9-4.3 5.4-4.3s4.6 1.6 5.4 4.3"/>' ),
	'skin'    => $dak_icon( '<path d="M10 3.4s4.4 4.6 4.4 7.3a4.4 4.4 0 0 1-8.8 0C5.6 8 10 3.4 10 3.4z"/><path d="M8.4 11.2h.01M10.6 12.8h.01"/>' ),
	'kidney'  => $dak_icon( '<path d="M8.2 4.2c2.2 0 3.8 2.2 3.8 5.6s-1.6 6-3.8 6-3.4-2-3.4-5.7 1.2-5.9 3.4-5.9z"/><path d="M12 9.8h3.6"/>' ),
);

$dak_header_specialty_icon = function ( $slug ) use ( $dak_header_specialty_icons, $dak_header_icons ) {
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

	foreach ( $matches as $dak_needle => $dak_icon_name ) {
		if ( false !== strpos( $slug, $dak_needle ) ) {
			return $dak_header_specialty_icons[ $dak_icon_name ];
		}
	}

	return $dak_header_icons['steth'];
};

// Service categories are a small fixed set (Service_Categories::get_all()).
$dak_header_category_icons = array(
	'surgeries-and-procedures'     => $dak_header_icons['scalpel'],
	'pharmacy'                     => $dak_header_icons['pill'],
	'labs'                         => $dak_header_icons['flask'],
	'nursing'                      => $dak_header_icons['cross'],
	'second-opinion-services'      => $dak_header_icons['chat'],
	'miscellaneous-other-services' => $dak_header_icons['grid'],
);

$dak_header_category_icon = function ( $slug ) use ( $dak_header_category_icons, $dak_header_icons ) {
	return isset( $dak_header_category_icons[ $slug ] ) ? $dak_header_category_icons[ $slug ] : $dak_header_icons['grid'];
};

// Message for the not-yet-built Lab/Pharmacy booking items (toast, see
// data-dak-coming-soon in doctor-ak-site-header.js).
$dak_header_coming_soon_note = static function ( $feature_label ) use ( $phone ) {
	if ( '' !== $phone ) {
		return sprintf(
			/* translators: 1: feature name, e.g. "Lab Test Booking". 2: clinic phone number. */
			__( '%1$s is launching soon. Call us to book today: %2$s', 'doctor-ak-portal' ),
			$feature_label,
			$phone
		);
	}

	return sprintf(
		/* translators: %s: feature name, e.g. "Lab Test Booking". */
		__( '%s is launching soon — check back soon.', 'doctor-ak-portal' ),
		$feature_label
	);
};

$dak_service_categories = array_values(
	array_filter(
		(array) $service_categories,
		function ( $category ) {
			return ! empty( $category['services'] );
		}
	)
);
$dak_service_total      = array_sum(
	array_map(
		function ( $category ) {
			return count( $category['services'] );
		},
		$dak_service_categories
	)
);
$dak_clinic_list        = isset( $clinics['clinics'] ) ? $clinics['clinics'] : array();
$dak_clinic_cities      = isset( $clinics['cities'] ) ? $clinics['cities'] : array();
$dak_home_url           = home_url( '/' );
$dak_is_home            = '' === $current_path;
$dak_phone_href         = '' !== $phone ? 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) : '';

$dak_nav_trigger = function ( $key, $label, $is_current ) use ( $dak_header_icons ) {
	?>
	<button type="button" class="dak-nav-trigger<?php echo $is_current ? ' is-current' : ''; ?>" id="dak-nav-trigger-<?php echo esc_attr( $key ); ?>" aria-expanded="false" aria-controls="dak-nav-panel-<?php echo esc_attr( $key ); ?>"<?php echo $is_current ? ' aria-current="true"' : ''; ?>>
		<span class="dak-nav-label"><?php echo esc_html( $label ); ?></span>
		<span class="dak-nav-chevron"><?php echo $dak_header_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
	</button>
	<?php
};

$dak_search_box = function ( $args ) use ( $dak_header_icons ) {
	?>
	<div class="dak-nav-search-field">
		<span class="dak-nav-search-icon"><?php echo $dak_header_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<label class="dak-sr-only" for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
		<input type="search" id="<?php echo esc_attr( $args['id'] ); ?>" class="dak-nav-search-input"<?php echo isset( $args['name'] ) ? ' name="' . esc_attr( $args['name'] ) . '"' : ''; ?> placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" autocomplete="off" spellcheck="false" data-dak-nav-search aria-describedby="<?php echo esc_attr( $args['id'] ); ?>-status">
		<button type="button" class="dak-nav-search-clear" data-dak-nav-search-clear hidden aria-label="<?php esc_attr_e( 'Clear search', 'doctor-ak-portal' ); ?>"><?php echo $dak_header_icons['x']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
	</div>
	<?php
};

$dak_view_all = function ( $url, $label, $extra_attr = '' ) use ( $dak_header_icons ) {
	?>
	<a class="dak-nav-view-all" href="<?php echo esc_url( $url ); ?>"<?php echo $extra_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from literals below. ?>>
		<span data-dak-nav-view-all-label><?php echo esc_html( $label ); ?></span>
		<?php echo $dak_header_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
	</a>
	<?php
};
?>
<div class="dak-portal dak-site-topbar">
	<div class="dak-site-topbar-inner">
		<div class="dak-site-topbar-contact">
			<?php if ( $phone ) : ?>
				<a class="dak-site-topbar-item" href="<?php echo esc_attr( $dak_phone_href ); ?>">
					<?php echo $dak_header_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php echo esc_html( $phone ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<a class="dak-site-topbar-item dak-site-topbar-email" href="mailto:<?php echo esc_attr( $email ); ?>">
					<?php echo $dak_header_icons['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php echo esc_html( $email ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $location ) : ?>
				<span class="dak-site-topbar-item dak-site-topbar-location">
					<?php echo $dak_header_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php echo esc_html( $location ); ?></span>
				</span>
			<?php endif; ?>
		</div>

		<div class="dak-site-topbar-actions">
			<button type="button" class="dak-public-theme-toggle" data-dak-public-theme-toggle aria-pressed="false" title="<?php esc_attr_e( 'Dark mode', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Dark mode', 'doctor-ak-portal' ); ?>">
				<span class="dak-theme-icon dak-theme-icon-sun"><?php echo $dak_header_icons['sun']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="dak-theme-icon dak-theme-icon-moon"><?php echo $dak_header_icons['moon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			</button>

			<?php if ( $is_logged_in ) : ?>
				<div class="dak-site-topbar-account" data-dak-nav-item="account">
					<button type="button" class="dak-site-topbar-account-trigger" id="dak-nav-trigger-account" aria-expanded="false" aria-controls="dak-nav-panel-account">
						<img src="<?php echo esc_url( $user_avatar_url ); ?>" alt="" class="dak-site-topbar-avatar" width="28" height="28">
						<span class="dak-site-topbar-account-name"><?php echo esc_html( $display_name ); ?></span>
						<span class="dak-sr-only"><?php esc_html_e( 'Account menu', 'doctor-ak-portal' ); ?></span>
						<span class="dak-nav-chevron"><?php echo $dak_header_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					</button>
					<div class="dak-nav-dropdown dak-site-topbar-account-menu" id="dak-nav-panel-account" hidden>
						<?php if ( $dashboard_url ) : ?>
							<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Dashboard', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
						<?php if ( $profile_url ) : ?>
							<a href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Edit Profile', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
						<a href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Logout', 'doctor-ak-portal' ); ?></a>
					</div>
				</div>
			<?php elseif ( $login_url ) : ?>
				<a class="dak-site-topbar-login" href="<?php echo esc_url( $login_url ); ?>">
					<?php echo $dak_header_icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php esc_html_e( 'Login / Register', 'doctor-ak-portal' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php
// Text the header script writes into the panels (search results / empty states).
$dak_nav_strings = array(
	/* translators: %s: what the visitor searched for. */
	'noResults'   => __( 'No results for “%s”', 'doctor-ak-portal' ),
	/* translators: 1: search text, 2: city name. */
	'inCity'      => __( '%1$s in %2$s', 'doctor-ak-portal' ),
	/* translators: %d: number of matching doctors. */
	'seeAll'      => __( 'See all %d matching doctors', 'doctor-ak-portal' ),
	'noMatches'   => __( 'No matches.', 'doctor-ak-portal' ),
	/* translators: %d: number of matches. */
	'matchOne'    => __( '%d match.', 'doctor-ak-portal' ),
	/* translators: %d: number of matches. */
	'matchMany'   => __( '%d matches.', 'doctor-ak-portal' ),
	/* translators: %d: number of services found. */
	'serviceOne'  => __( '%d service found.', 'doctor-ak-portal' ),
	/* translators: %d: number of services found. */
	'serviceMany' => __( '%d services found.', 'doctor-ak-portal' ),
	'noClinics'   => __( 'No clinics found.', 'doctor-ak-portal' ),
	/* translators: %d: number of clinics found. */
	'clinicOne'   => __( '%d clinic.', 'doctor-ak-portal' ),
	/* translators: %d: number of clinics found. */
	'clinicMany'  => __( '%d clinics.', 'doctor-ak-portal' ),
);
?>
<header class="dak-portal dak-site-header" id="dak-site-header" data-dak-nav-strings="<?php echo esc_attr( wp_json_encode( $dak_nav_strings ) ); ?>">
	<div class="dak-site-header-inner">
		<a class="dak-site-header-logo" href="<?php echo esc_url( $dak_home_url ); ?>">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="291" height="301">
			<?php elseif ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="dak-site-header-logo-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<?php endif; ?>
		</a>

		<div class="dak-site-header-drawer" id="dak-site-header-drawer" data-dak-drawer-label="<?php esc_attr_e( 'Site menu', 'doctor-ak-portal' ); ?>">
			<div class="dak-site-header-drawer-head">
				<span class="dak-site-header-drawer-title"><?php esc_html_e( 'Menu', 'doctor-ak-portal' ); ?></span>
				<button type="button" class="dak-site-header-drawer-close" data-dak-drawer-close aria-label="<?php esc_attr_e( 'Close menu', 'doctor-ak-portal' ); ?>"><?php echo $dak_header_icons['x']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			</div>

			<nav class="dak-site-header-nav" aria-label="<?php esc_attr_e( 'Main', 'doctor-ak-portal' ); ?>">
				<ul class="dak-site-header-menu">
					<li class="dak-nav-item">
						<a class="dak-nav-link<?php echo $dak_is_home ? ' is-current' : ''; ?>" href="<?php echo esc_url( $dak_home_url ); ?>"<?php echo $dak_is_home ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Home', 'doctor-ak-portal' ); ?></a>
					</li>

					<?php if ( $directory_url ) : ?>
						<li class="dak-nav-item has-panel" data-dak-nav-item="doctors">
							<?php $dak_nav_trigger( 'doctors', __( 'Doctors', 'doctor-ak-portal' ), $dak_in_section( 'doctors' ) ); ?>
							<div class="dak-nav-panel" id="dak-nav-panel-doctors" hidden>
								<div class="dak-nav-panel-card">
									<form class="dak-nav-panel-search" method="get" action="<?php echo esc_url( strtok( $directory_url, '?' ) ); ?>" role="search" data-dak-search-kind="doctors">
										<?php $dak_form_args( $directory_url, 'q' ); ?>
										<?php
										$dak_search_box(
											array(
												'id'          => 'dak-nav-search-doctors',
												'name'        => 'q',
												'label'       => __( 'Search doctors by name or specialty', 'doctor-ak-portal' ),
												'placeholder' => __( 'Search by doctor name or specialty', 'doctor-ak-portal' ),
											)
										);
										?>
										<button type="submit" class="dak-nav-search-submit"><?php esc_html_e( 'Search all doctors', 'doctor-ak-portal' ); ?></button>
									</form>
									<p class="dak-sr-only" id="dak-nav-search-doctors-status" aria-live="polite" data-dak-nav-search-status></p>

									<div class="dak-nav-panel-body">
										<div class="dak-nav-browse" data-dak-nav-browse>
											<?php if ( ! empty( $doctor_specialties ) ) : ?>
												<p class="dak-nav-heading"><?php esc_html_e( 'Browse by specialty', 'doctor-ak-portal' ); ?></p>
												<ul class="dak-nav-tiles">
													<?php foreach ( $doctor_specialties as $dak_specialty ) : ?>
														<li>
															<a class="dak-nav-tile" href="<?php echo esc_url( $dak_specialty['url'] ); ?>">
																<span class="dak-nav-tile-icon"><?php echo $dak_header_specialty_icon( $dak_specialty['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
																<span class="dak-nav-tile-text">
																	<span class="dak-nav-tile-title"><?php echo esc_html( $dak_specialty['label'] ); ?></span>
																	<span class="dak-nav-tile-meta">
																		<?php
																		echo esc_html(
																			sprintf(
																				/* translators: %d: number of doctors with this specialty. */
																				_n( '%d doctor', '%d doctors', $dak_specialty['count'], 'doctor-ak-portal' ),
																				$dak_specialty['count']
																			)
																		);
																		?>
																	</span>
																</span>
															</a>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<p class="dak-nav-note"><?php esc_html_e( 'Browse every doctor in the directory.', 'doctor-ak-portal' ); ?></p>
											<?php endif; ?>
										</div>

										<div class="dak-nav-results" data-dak-nav-results hidden>
											<?php if ( ! empty( $doctor_specialties ) ) : ?>
												<div class="dak-nav-result-group" data-dak-nav-group>
													<p class="dak-nav-heading"><?php esc_html_e( 'Specialties', 'doctor-ak-portal' ); ?></p>
													<ul class="dak-nav-result-list">
														<?php foreach ( $doctor_specialties as $dak_specialty ) : ?>
															<li data-dak-nav-match="<?php echo esc_attr( mb_strtolower( $dak_specialty['label'] ) ); ?>" hidden>
																<a class="dak-nav-result" href="<?php echo esc_url( $dak_specialty['url'] ); ?>">
																	<span class="dak-nav-result-icon"><?php echo $dak_header_specialty_icon( $dak_specialty['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
																	<span class="dak-nav-result-text">
																		<span class="dak-nav-result-title"><?php echo esc_html( $dak_specialty['label'] ); ?></span>
																		<span class="dak-nav-result-meta">
																			<?php
																			echo esc_html(
																				sprintf(
																					/* translators: %d: number of doctors with this specialty. */
																					_n( '%d doctor', '%d doctors', $dak_specialty['count'], 'doctor-ak-portal' ),
																					$dak_specialty['count']
																				)
																			);
																			?>
																		</span>
																	</span>
																</a>
															</li>
														<?php endforeach; ?>
													</ul>
												</div>
											<?php endif; ?>

											<?php if ( ! empty( $doctor_index ) ) : ?>
												<div class="dak-nav-result-group" data-dak-nav-group data-dak-nav-limit="8">
													<p class="dak-nav-heading"><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></p>
													<ul class="dak-nav-result-list">
														<?php foreach ( $doctor_index as $dak_doctor ) : ?>
															<li data-dak-nav-match="<?php echo esc_attr( $dak_doctor['search'] ); ?>" hidden>
																<a class="dak-nav-result" href="<?php echo esc_url( $dak_doctor['url'] ); ?>">
																	<span class="dak-nav-result-icon"><?php echo $dak_header_icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
																	<span class="dak-nav-result-text">
																		<span class="dak-nav-result-title">
																			<?php
																			/* translators: %s: doctor's name. */
																			echo esc_html( sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $dak_doctor['name'] ) );
																			?>
																		</span>
																		<?php if ( '' !== $dak_doctor['specialty'] ) : ?>
																			<span class="dak-nav-result-meta"><?php echo esc_html( $dak_doctor['specialty'] ); ?></span>
																		<?php endif; ?>
																	</span>
																</a>
															</li>
														<?php endforeach; ?>
													</ul>
													<p class="dak-nav-more" data-dak-nav-more hidden></p>
												</div>
											<?php endif; ?>
										</div>

										<div class="dak-nav-empty" data-dak-nav-empty hidden>
											<p class="dak-nav-empty-title" data-dak-nav-empty-title></p>
											<p class="dak-nav-empty-text"><?php esc_html_e( 'Check the spelling, or try a specialty such as “cardiologist”.', 'doctor-ak-portal' ); ?></p>
										</div>
									</div>

									<div class="dak-nav-panel-foot">
										<?php $dak_view_all( $directory_url, __( 'View all doctors', 'doctor-ak-portal' ) ); ?>
									</div>
								</div>
							</div>
						</li>
					<?php endif; ?>

					<?php if ( $services_url ) : ?>
						<li class="dak-nav-item has-panel" data-dak-nav-item="services">
							<?php $dak_nav_trigger( 'services', __( 'Services', 'doctor-ak-portal' ), $dak_in_section( 'services' ) ); ?>
							<div class="dak-nav-panel" id="dak-nav-panel-services" hidden>
								<div class="dak-nav-panel-card">
									<?php if ( ! empty( $dak_service_categories ) ) : ?>
										<div class="dak-nav-panel-search" role="search" data-dak-search-kind="services">
											<?php
											$dak_search_box(
												array(
													'id'          => 'dak-nav-search-services',
													'label'       => __( 'Search all services', 'doctor-ak-portal' ),
													'placeholder' => sprintf(
														/* translators: %d: number of services. */
														_n( 'Search %d service', 'Search all %d services', $dak_service_total, 'doctor-ak-portal' ),
														$dak_service_total
													),
												)
											);
											?>
										</div>
										<p class="dak-sr-only" id="dak-nav-search-services-status" aria-live="polite" data-dak-nav-search-status></p>

										<div class="dak-nav-panel-body">
											<div class="dak-nav-categories" data-dak-nav-categories style="--dak-nav-category-rows: <?php echo (int) count( $dak_service_categories ); ?>;">
												<?php foreach ( $dak_service_categories as $dak_index => $dak_category ) : ?>
													<?php $dak_cat_id = 'dak-nav-category-' . sanitize_html_class( $dak_category['slug'] ? $dak_category['slug'] : (string) $dak_index ); ?>
													<button type="button" class="dak-nav-category" data-dak-nav-category aria-expanded="<?php echo 0 === $dak_index ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $dak_cat_id ); ?>">
														<span class="dak-nav-category-icon"><?php echo $dak_header_category_icon( $dak_category['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
														<span class="dak-nav-category-label"><?php echo esc_html( $dak_category['label'] ); ?></span>
														<span class="dak-nav-category-count"><?php echo esc_html( number_format_i18n( count( $dak_category['services'] ) ) ); ?></span>
														<span class="dak-nav-chevron"><?php echo $dak_header_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
													</button>
													<div class="dak-nav-category-pane" id="<?php echo esc_attr( $dak_cat_id ); ?>" data-dak-nav-group<?php echo 0 === $dak_index ? '' : ' hidden'; ?>>
														<p class="dak-nav-heading"><?php echo esc_html( $dak_category['label'] ); ?></p>
														<ul class="dak-nav-link-list">
															<?php foreach ( $dak_category['services'] as $dak_service ) : ?>
																<li data-dak-nav-match="<?php echo esc_attr( mb_strtolower( $dak_service['name'] ) ); ?>">
																	<a class="dak-nav-plain-link" href="<?php echo esc_url( $dak_service['url'] ); ?>"><?php echo esc_html( $dak_service['name'] ); ?></a>
																</li>
															<?php endforeach; ?>
														</ul>
													</div>
												<?php endforeach; ?>
											</div>

											<div class="dak-nav-empty" data-dak-nav-empty hidden>
												<p class="dak-nav-empty-title" data-dak-nav-empty-title></p>
												<p class="dak-nav-empty-text"><?php esc_html_e( 'Try a shorter word, or browse the categories.', 'doctor-ak-portal' ); ?></p>
											</div>
										</div>
									<?php else : ?>
										<div class="dak-nav-panel-body">
											<p class="dak-nav-note"><?php esc_html_e( 'See every service we offer, with prices and the doctors who provide them.', 'doctor-ak-portal' ); ?></p>
										</div>
									<?php endif; ?>

									<div class="dak-nav-panel-foot">
										<?php $dak_view_all( $services_url, __( 'View all services', 'doctor-ak-portal' ) ); ?>
									</div>
								</div>
							</div>
						</li>
					<?php endif; ?>

					<?php if ( $clinics_url ) : ?>
						<li class="dak-nav-item has-panel" data-dak-nav-item="clinics">
							<?php $dak_nav_trigger( 'clinics', __( 'Clinics', 'doctor-ak-portal' ), $dak_in_section( 'clinics' ) ); ?>
							<div class="dak-nav-panel" id="dak-nav-panel-clinics" hidden>
								<div class="dak-nav-panel-card">
									<?php if ( ! empty( $dak_clinic_list ) ) : ?>
										<form class="dak-nav-panel-search" method="get" action="<?php echo esc_url( strtok( $clinics_url, '?' ) ); ?>" role="search" data-dak-search-kind="clinics">
											<?php $dak_form_args( $clinics_url, 'q' ); ?>
											<input type="hidden" name="city" value="" data-dak-nav-city-field disabled>
											<?php
											$dak_search_box(
												array(
													'id'          => 'dak-nav-search-clinics',
													'name'        => 'q',
													'label'       => __( 'Search clinics by name or area', 'doctor-ak-portal' ),
													'placeholder' => __( 'Search by clinic name, area or city', 'doctor-ak-portal' ),
												)
											);
											?>
											<button type="submit" class="dak-nav-search-submit"><?php esc_html_e( 'Search all clinics', 'doctor-ak-portal' ); ?></button>
										</form>
										<p class="dak-sr-only" id="dak-nav-search-clinics-status" aria-live="polite" data-dak-nav-search-status></p>

										<?php if ( count( $dak_clinic_cities ) > 1 ) : ?>
											<div class="dak-nav-chips" role="group" aria-label="<?php esc_attr_e( 'Filter clinics by city', 'doctor-ak-portal' ); ?>">
												<button type="button" class="dak-nav-chip" data-dak-nav-city="" aria-pressed="true">
													<?php esc_html_e( 'All cities', 'doctor-ak-portal' ); ?>
													<span class="dak-nav-chip-count"><?php echo esc_html( number_format_i18n( count( $dak_clinic_list ) ) ); ?></span>
												</button>
												<?php foreach ( $dak_clinic_cities as $dak_city ) : ?>
													<button type="button" class="dak-nav-chip" data-dak-nav-city="<?php echo esc_attr( $dak_city['slug'] ); ?>" data-dak-nav-city-label="<?php echo esc_attr( $dak_city['label'] ); ?>" aria-pressed="false">
														<?php echo esc_html( $dak_city['label'] ); ?>
														<span class="dak-nav-chip-count"><?php echo esc_html( number_format_i18n( $dak_city['count'] ) ); ?></span>
													</button>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>

										<div class="dak-nav-panel-body">
											<ul class="dak-nav-clinics" data-dak-nav-group>
												<?php foreach ( $dak_clinic_list as $dak_clinic ) : ?>
													<li data-dak-nav-match="<?php echo esc_attr( mb_strtolower( $dak_clinic['name'] . ',' . $dak_clinic['place'] ) ); ?>" data-dak-nav-city="<?php echo esc_attr( $dak_clinic['city'] ); ?>">
														<a class="dak-nav-result" href="<?php echo esc_url( $dak_clinic['url'] ? $dak_clinic['url'] : $clinics_url ); ?>">
															<span class="dak-nav-result-icon"><?php echo $dak_header_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
															<span class="dak-nav-result-text">
																<span class="dak-nav-result-title"><?php echo esc_html( $dak_clinic['name'] ); ?></span>
																<?php if ( '' !== $dak_clinic['place'] ) : ?>
																	<span class="dak-nav-result-meta"><?php echo esc_html( $dak_clinic['place'] ); ?></span>
																<?php endif; ?>
															</span>
														</a>
													</li>
												<?php endforeach; ?>
											</ul>

											<div class="dak-nav-empty" data-dak-nav-empty hidden>
												<p class="dak-nav-empty-title" data-dak-nav-empty-title></p>
												<p class="dak-nav-empty-text"><?php esc_html_e( 'Try another name or area, or choose “All cities”.', 'doctor-ak-portal' ); ?></p>
											</div>
										</div>
									<?php else : ?>
										<div class="dak-nav-panel-body">
											<p class="dak-nav-note"><?php esc_html_e( 'Find our clinic locations and the doctors who practise at each one.', 'doctor-ak-portal' ); ?></p>
										</div>
									<?php endif; ?>

									<div class="dak-nav-panel-foot">
										<?php
										$dak_view_all(
											$clinics_url,
											__( 'View all clinics', 'doctor-ak-portal' ),
											' data-dak-nav-clinics-all data-dak-nav-base-url="' . esc_url( $clinics_url ) . '" data-dak-nav-all-label="' . esc_attr__( 'View all clinics', 'doctor-ak-portal' ) . '" data-dak-nav-city-template="' . esc_attr(
												/* translators: %s: city name. */
												__( 'View all clinics in %s', 'doctor-ak-portal' )
											) . '"'
										);
										?>
									</div>
								</div>
							</div>
						</li>
					<?php endif; ?>

					<li class="dak-nav-item">
						<a class="dak-nav-link" href="<?php echo esc_url( $videos_url ); ?>"><?php esc_html_e( 'Videos', 'doctor-ak-portal' ); ?></a>
					</li>

					<?php if ( $gallery_url ) : ?>
						<?php $dak_gallery_current = $dak_path_of( $gallery_url ) === $current_path; ?>
						<li class="dak-nav-item">
							<a class="dak-nav-link<?php echo $dak_gallery_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( $gallery_url ); ?>"<?php echo $dak_gallery_current ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Gallery', 'doctor-ak-portal' ); ?></a>
						</li>
					<?php endif; ?>

					<?php if ( $blogs_url ) : ?>
						<?php
						$dak_blogs_current = $dak_in_section( 'blogs' );
						// The list page itself is "page"; a single post is inside the section.
						$dak_blogs_aria = $dak_path_of( $blogs_url ) === $current_path ? 'page' : 'true';
						?>
						<li class="dak-nav-item">
							<a class="dak-nav-link<?php echo $dak_blogs_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( $blogs_url ); ?>"<?php echo $dak_blogs_current ? ' aria-current="' . esc_attr( $dak_blogs_aria ) . '"' : ''; ?>><?php esc_html_e( 'Blogs', 'doctor-ak-portal' ); ?></a>
						</li>
					<?php endif; ?>
				</ul>
			</nav>

			<?php if ( $phone ) : ?>
				<div class="dak-site-header-drawer-foot">
					<a class="dak-site-header-drawer-call" href="<?php echo esc_attr( $dak_phone_href ); ?>">
						<?php echo $dak_header_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<span>
							<?php
							/* translators: %s: phone number. */
							echo esc_html( sprintf( __( 'Call %s', 'doctor-ak-portal' ), $phone ) );
							?>
						</span>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<div class="dak-site-header-actions">
			<div class="dak-site-header-book" data-dak-nav-item="book">
				<button type="button" class="dak-site-header-cta" id="dak-nav-trigger-book" aria-expanded="false" aria-controls="dak-nav-panel-book">
					<span class="dak-site-header-cta-label"><?php esc_html_e( 'Book Now', 'doctor-ak-portal' ); ?></span>
					<span class="dak-nav-chevron"><?php echo $dak_header_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				</button>
				<div class="dak-nav-dropdown dak-site-header-book-menu" id="dak-nav-panel-book" hidden>
					<ul class="dak-book-options">
						<?php if ( $booking_url ) : ?>
							<li>
								<a class="dak-book-option" href="<?php echo esc_url( $booking_url ); ?>" data-dak-book-appointment>
									<span class="dak-book-option-icon"><?php echo $dak_header_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									<span class="dak-book-option-text">
										<span class="dak-book-option-title"><?php esc_html_e( 'Book a doctor', 'doctor-ak-portal' ); ?></span>
										<span class="dak-book-option-meta"><?php esc_html_e( 'Clinic visit or video consultation', 'doctor-ak-portal' ); ?></span>
									</span>
								</a>
							</li>
						<?php endif; ?>
						<?php if ( $services_url ) : ?>
							<li>
								<a class="dak-book-option" href="<?php echo esc_url( $services_url ); ?>">
									<span class="dak-book-option-icon"><?php echo $dak_header_icons['tag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									<span class="dak-book-option-text">
										<span class="dak-book-option-title"><?php esc_html_e( 'Book a service', 'doctor-ak-portal' ); ?></span>
										<span class="dak-book-option-meta"><?php esc_html_e( 'Choose a service, then a doctor', 'doctor-ak-portal' ); ?></span>
									</span>
								</a>
							</li>
						<?php endif; ?>
						<li>
							<button type="button" class="dak-book-option is-unavailable" data-dak-coming-soon="<?php echo esc_attr( $dak_header_coming_soon_note( __( 'Lab Test Booking', 'doctor-ak-portal' ) ) ); ?>">
								<span class="dak-book-option-icon"><?php echo $dak_header_icons['flask']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
								<span class="dak-book-option-text">
									<span class="dak-book-option-title">
										<?php esc_html_e( 'Lab tests', 'doctor-ak-portal' ); ?>
										<span class="dak-book-option-badge"><?php esc_html_e( 'Coming soon', 'doctor-ak-portal' ); ?></span>
									</span>
									<span class="dak-book-option-meta"><?php echo '' !== $phone ? esc_html__( 'Online booking not available yet — call to book', 'doctor-ak-portal' ) : esc_html__( 'Online booking not available yet', 'doctor-ak-portal' ); ?></span>
								</span>
							</button>
						</li>
						<li>
							<button type="button" class="dak-book-option is-unavailable" data-dak-coming-soon="<?php echo esc_attr( $dak_header_coming_soon_note( __( 'Pharmacy Ordering', 'doctor-ak-portal' ) ) ); ?>">
								<span class="dak-book-option-icon"><?php echo $dak_header_icons['pill']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
								<span class="dak-book-option-text">
									<span class="dak-book-option-title">
										<?php esc_html_e( 'Pharmacy', 'doctor-ak-portal' ); ?>
										<span class="dak-book-option-badge"><?php esc_html_e( 'Coming soon', 'doctor-ak-portal' ); ?></span>
									</span>
									<span class="dak-book-option-meta"><?php echo '' !== $phone ? esc_html__( 'Online ordering not available yet — call to order', 'doctor-ak-portal' ) : esc_html__( 'Online ordering not available yet', 'doctor-ak-portal' ); ?></span>
								</span>
							</button>
						</li>
					</ul>
				</div>
			</div>

			<button type="button" class="dak-site-header-toggle" id="dak-site-header-toggle" aria-expanded="false" aria-controls="dak-site-header-drawer">
				<?php echo $dak_header_icons['menu']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<span class="dak-sr-only"><?php esc_html_e( 'Menu', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	</div>
	<div class="dak-site-header-scrim" data-dak-drawer-close hidden></div>
</header>
