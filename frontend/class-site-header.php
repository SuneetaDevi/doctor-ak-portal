<?php
/**
 * Site-wide header injected on every front-end page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Services;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Site_Header
 *
 * Unlike the shortcode handlers, this renders on every front-end request
 * (via wp_body_open) rather than only on pages containing a specific
 * shortcode, since the header is meant to replace the active theme's own
 * header wherever the plugin is installed.
 *
 * The nav itself (Doctors/Services/Clinics/Videos, the Doctors mega-menu's
 * specialty/clinic lists) is fully plugin-rendered from real data — it no
 * longer reads a wp_nav_menu() location under Appearance -> Menus. That
 * gave up site-owner-editable links in exchange for the mega-menu actually
 * being possible to build safely; the old fallback link set (all this ever
 * rendered in practice) is now simply the only nav.
 */
class Site_Header {

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader $template_loader Template loader.
	 */
	public function __construct( Template_Loader $template_loader ) {
		$this->template_loader = $template_loader;
	}

	/**
	 * Enqueues the header's assets on every front-end page.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-site-header',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-site-header.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-site-header.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-site-header',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-site-header.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-site-header.js' ),
			true
		);

		// Loaded in the <head> (in_footer = false), not the footer like
		// everything else here — it needs to apply a returning visitor's
		// saved theme before the page paints, or dark-mode visitors would
		// see a flash of the light theme on every page load.
		wp_enqueue_script(
			'doctor-ak-portal-public-theme-toggle',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-public-theme-toggle.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-public-theme-toggle.js' ),
			false
		);
	}

	/**
	 * Outputs a `<meta name="viewport">` tag, hooked to `wp_head` at
	 * priority 1 (ahead of the active theme's own `wp_head()` output).
	 *
	 * This plugin's whole layout — the two-row site header, the mega-menus,
	 * every responsive breakpoint in doctor-ak-*.css — assumes a real mobile
	 * viewport width is being reported. Whether the active theme's own
	 * header.php already outputs one varies (most modern themes do; a bare/
	 * blank "canvas" theme installed just to host this plugin's own
	 * fully-custom front end might not) — since a missing one makes a phone
	 * browser render the page at desktop width and scale it down (nav never
	 * collapses to the hamburger, the hero's absolutely-positioned stat
	 * cards/search panel overlap the copy — exactly what a "squished
	 * desktop site" looks like on a phone), this plugin ensures one is
	 * always present rather than depending on the theme for it. Two
	 * viewport tags on a page is harmless (browsers use whichever comes
	 * first), so this doesn't check whether the theme already added one.
	 *
	 * @return void
	 */
	public function render_viewport_meta() {
		if ( is_admin() ) {
			return;
		}

		echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
	}

	/**
	 * Whether the current page holds one of the three dashboards. Those are
	 * full-screen app shells with their own sidebar and top bar, so the public
	 * site header and footer are left out.
	 *
	 * @return bool
	 */
	public static function is_dashboard_app_page() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) ) {
			return false;
		}

		foreach ( array( 'admin_dashboard', 'doctor_dashboard', 'patient_dashboard' ) as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Adds `dak-app-page` to <body> on dashboard pages so CSS can hide any
	 * theme header/footer around them.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function add_body_class( $classes ) {
		if ( self::is_dashboard_app_page() ) {
			$classes[] = 'dak-app-page';
		}

		return $classes;
	}

	/**
	 * Renders the header markup. Hooked to wp_body_open.
	 *
	 * @return void
	 */
	public function render() {
		if ( is_admin() || self::is_dashboard_app_page() ) {
			return;
		}

		echo $this->template_loader->get_template( 'site-header.php', $this->prepare_data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
	}

	/**
	 * Gathers the data templates/site-header.php needs.
	 *
	 * @return array
	 */
	private function prepare_data() {
		$user      = wp_get_current_user();
		$is_doctor = in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true );
		$is_patient = in_array( Roles::PATIENT_ROLE, (array) $user->roles, true );

		// Profile editing can be turned off per role (Settings -> Roles &
		// Permissions); admins (and any account with neither role) aren't
		// gated by that setting, so their link always shows.
		$profile_allowed = ! $is_doctor && ! $is_patient
			? true
			: Role_Permissions::is_tab_allowed( $is_doctor ? Roles::DOCTOR_ROLE : Roles::PATIENT_ROLE, 'profile' );

		$directory_url = Page_Finder::url_for_shortcode( 'doctors_directory' );
		$home_url      = Page_Finder::url_for_shortcode( 'dak_home' );
		$home_url      = $home_url ? $home_url : home_url( '/' );

		return array(
			'logo_url'           => self::bundled_logo_url(),
			'phone'              => self::primary_phone(),
			'email'              => self::primary_email(),
			'location'           => self::primary_location(),
			'directory_url'      => $directory_url,
			'services_url'       => Page_Finder::url_for_shortcode( 'services_directory' ),
			'videos_url'         => $home_url . '#dak-home-videos',
			'clinics_url'        => Page_Finder::url_for_shortcode( 'clinics_directory' ),
			'blogs_url'          => Page_Finder::url_for_shortcode( 'blogs_directory' ),
			'doctor_specialties' => Home_Page::specialties_in_use( $directory_url ),
			'doctor_index'       => self::doctors_for_menu(),
			'service_categories' => self::service_categories_for_menu(),
			'clinics'            => self::clinics_for_menu(),
			'booking_url'        => Page_Finder::url_for_shortcode( 'book_appointment' ),
			'gallery_url'        => self::gallery_url(),
			// Detail pages that belong under a nav item, for the active-page
			// indicator (a doctor profile highlights "Doctors", etc.).
			'section_paths'      => array(
				'doctors'  => array( $directory_url, Page_Finder::url_for_shortcode( 'doctor_profile_view' ) ),
				'services' => array( Page_Finder::url_for_shortcode( 'services_directory' ), Page_Finder::url_for_shortcode( 'service_profile_view' ) ),
				'clinics'  => array( Page_Finder::url_for_shortcode( 'clinics_directory' ), Page_Finder::url_for_shortcode( 'clinic_profile_view' ) ),
				'blogs'    => array( Page_Finder::url_for_shortcode( 'blogs_directory' ), Page_Finder::url_for_shortcode( 'blog_single' ) ),
			),
			'current_path'       => self::current_path(),
			'is_logged_in'       => is_user_logged_in(),
			'user'               => $user,
			'user_avatar_url'    => self::user_avatar_url( $user ),
			'dashboard_url'      => is_user_logged_in()
				? Page_Finder::url_for_shortcode( self::dashboard_shortcode_for( $user, $is_doctor ) )
				: '',
			'profile_url'        => ( is_user_logged_in() && $profile_allowed ) ? Page_Finder::url_for_shortcode( 'doctor_profile' ) : '',
			'login_url'          => Page_Finder::url_for_shortcode( 'doctor_login' ),
			'logout_url'         => wp_logout_url( home_url( '/' ) ),
		);
	}

	/**
	 * The Services mega-menu's columns — one per Service_Categories entry
	 * that actually has an active service in it, each with its own list of
	 * { name, url } links straight into [service_profile_view]. Mirrors
	 * doctor_specialties' role above, just grouped/shaped for a
	 * multi-column layout instead of a flat card grid (see
	 * Services::grouped_by_category_for_public_directory()).
	 *
	 * @return array List of { slug, label, services: [{ name, url }] }, sorted by service count descending (the category with the most services first) so the busiest category always lands in the first column.
	 */
	private static function service_categories_for_menu() {
		$profile_url = Page_Finder::url_for_shortcode( 'service_profile_view' );

		$columns = array_map(
			function ( $bucket ) use ( $profile_url ) {
				return array(
					'slug'     => $bucket['slug'],
					'label'    => $bucket['label'],
					'services' => array_map(
						function ( $service ) use ( $profile_url ) {
							return array(
								'name' => $service['name'],
								'url'  => $profile_url ? add_query_arg( 'service_id', $service['id'], $profile_url ) : '',
							);
						},
						$bucket['services']
					),
				);
			},
			Services::grouped_by_category_for_public_directory()
		);

		usort(
			$columns,
			function ( $a, $b ) {
				return count( $b['services'] ) - count( $a['services'] );
			}
		);

		return $columns;
	}

	/**
	 * The Clinics menu's data, from the same source as the clinics
	 * directory (Clinic_Public_Data): every clinic as name, "Area, City"
	 * subtitle and detail link (never the full street address), plus the
	 * cities that have clinics, most first, for the city filter — so the
	 * menu and the directory always agree.
	 *
	 * @return array { clinics: [{ name, place, city, url }], cities: [{ slug, label, count }] }
	 */
	private static function clinics_for_menu() {
		$clinics = array();

		foreach ( Clinic_Public_Data::locations() as $location ) {
			$clinics[] = array(
				'name'  => $location['name'],
				'place' => $location['place'],
				'city'  => $location['city'],
				'url'   => $location['profile_url'],
			);
		}

		$cities = array_map(
			function ( $city ) {
				return array(
					'slug'  => $city['slug'],
					'label' => $city['label'],
					'count' => $city['count'],
				);
			},
			Clinic_Public_Data::cities()
		);

		return array(
			'clinics' => $clinics,
			'cities'  => $cities,
		);
	}

	/**
	 * Every doctor the public directory lists (same rule as
	 * Doctors_Directory::doctor_cards_data(): the Doctor role, not
	 * deactivated), alphabetical, so the Doctors menu's search can show
	 * matching doctors inline. `search` holds exactly what the directory's
	 * own search box matches (the name and every specialty, lowercase), so
	 * the menu never promises a match the directory then can't find.
	 *
	 * @return array List of { name, specialty, search, url }.
	 */
	private static function doctors_for_menu() {
		$profile_url = Page_Finder::url_for_shortcode( 'doctor_profile_view' );
		$labels      = Specializations::get_all();
		$doctors     = array();

		foreach ( Appointments::active_doctor_ids() as $doctor_id ) {
			$doctor = get_userdata( $doctor_id );

			if ( ! $doctor ) {
				continue;
			}

			$name        = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$name        = '' !== $name ? $name : $doctor->display_name;
			$specialties = array();

			foreach ( (array) get_user_meta( $doctor_id, 'doctor_ak_specializations', true ) as $slug ) {
				if ( isset( $labels[ $slug ] ) ) {
					$specialties[] = $labels[ $slug ];
				}
			}

			$doctors[] = array(
				'name'      => $name,
				'specialty' => $specialties ? $specialties[0] : '',
				'search'    => mb_strtolower( $name . ',' . implode( ',', $specialties ) ),
				'url'       => $profile_url ? add_query_arg( 'doctor_id', $doctor_id, $profile_url ) : '',
			);
		}

		usort(
			$doctors,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $doctors;
	}

	/**
	 * The Gallery page's URL — a published page with the `gallery` slug — or
	 * '' when there isn't one (then the nav item isn't shown, rather than a
	 * link that goes nowhere).
	 *
	 * @return string
	 */
	private static function gallery_url() {
		$page = get_page_by_path( 'gallery' );

		return ( $page instanceof \WP_Post && 'publish' === $page->post_status ) ? (string) get_permalink( $page ) : '';
	}

	/**
	 * Resolves which dashboard shortcode a user's "Dashboard" link should
	 * point at: administrators first (an admin may also hold the doctor or
	 * patient role), then doctor, then patient.
	 *
	 * @param \WP_User $user      Current user.
	 * @param bool     $is_doctor Whether the user holds the Doctor role.
	 * @return string
	 */
	private static function dashboard_shortcode_for( \WP_User $user, $is_doctor ) {
		if ( user_can( $user, 'manage_options' ) ) {
			return 'admin_dashboard';
		}

		return $is_doctor ? 'doctor_dashboard' : 'patient_dashboard';
	}

	/**
	 * Resolves the user's uploaded profile picture (the same one shown on
	 * their dashboard), falling back to Gravatar/default avatar when they
	 * haven't uploaded one.
	 *
	 * @param \WP_User $user Current user (id 0 when logged out).
	 * @return string Avatar image URL.
	 */
	private static function user_avatar_url( \WP_User $user ) {
		$picture_id = $user->ID ? (int) get_user_meta( $user->ID, 'doctor_ak_profile_picture_id', true ) : 0;

		if ( $picture_id > 0 ) {
			$url = wp_get_attachment_image_url( $picture_id, 'thumbnail' );

			if ( $url ) {
				return $url;
			}
		}

		return get_avatar_url( $user->ID, array( 'size' => 64 ) );
	}

	/**
	 * Resolves a contact number for the header — the first clinic location
	 * with a phone number on file, since the plugin has no separate
	 * site-wide "contact phone" setting.
	 *
	 * @return string Phone number, or '' if no clinic has one set.
	 */
	private static function primary_phone() {
		foreach ( Clinic_Locations::get_all() as $clinic_location ) {
			if ( '' !== $clinic_location['phone'] ) {
				return $clinic_location['phone'];
			}
		}

		return '';
	}

	/**
	 * Resolves a contact email for the header — the first clinic location
	 * with one on file, mirroring primary_phone() (the plugin has no
	 * separate site-wide "contact email" setting either).
	 *
	 * @return string Email address, or '' if no clinic has one set.
	 */
	private static function primary_email() {
		foreach ( Clinic_Locations::get_all() as $clinic_location ) {
			if ( '' !== $clinic_location['contact_email'] ) {
				return $clinic_location['contact_email'];
			}
		}

		return '';
	}

	/**
	 * "City, Country" of the first clinic location that has a city on file, for
	 * the utility strip's location line — '' when none does.
	 *
	 * @return string
	 */
	private static function primary_location() {
		foreach ( Clinic_Locations::get_all() as $clinic_location ) {
			if ( '' !== $clinic_location['city_label'] ) {
				return implode( ', ', array_filter( array( $clinic_location['city_label'], $clinic_location['country_label'] ) ) );
			}
		}

		return '';
	}

	/**
	 * The current request's URL path (no query string, no trailing slash),
	 * for the nav's "which page is this" underline — see the
	 * `dak-site-header-menu-current` class applied in templates/site-header.php.
	 * Read-only string comparison only, never output, so this doesn't need
	 * sanitizing the way echoing $_SERVER['REQUEST_URI'] would.
	 *
	 * @return string
	 */
	private static function current_path() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared as a plain string below, never output.
			return '';
		}

		return untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared as a plain string below, never output.
	}

	/**
	 * Resolves the bundled logo's URL, checking a few common extensions
	 * under assets/images/logo.* so the site owner can just drop a file in
	 * without editing code.
	 *
	 * @return string Logo URL, or '' if no bundled logo file exists.
	 */
	private static function bundled_logo_url() {
		foreach ( array( 'png', 'svg', 'jpg', 'jpeg', 'webp' ) as $extension ) {
			$relative_path = 'assets/images/logo.' . $extension;

			if ( file_exists( DOCTOR_AK_PORTAL_PATH . $relative_path ) ) {
				return DOCTOR_AK_PORTAL_URL . $relative_path;
			}
		}

		return '';
	}
}
