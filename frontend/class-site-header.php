<?php
/**
 * Site-wide header injected on every front-end page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
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
	 * Renders the header markup. Hooked to wp_body_open so it appears
	 * immediately inside <body>, ahead of whatever the active theme
	 * otherwise renders.
	 *
	 * @return void
	 */
	public function render() {
		if ( is_admin() ) {
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
			'address'            => get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' ),
			'facebook_url'       => get_option( Site_Footer::OPTION_FACEBOOK_URL, '' ),
			'twitter_url'        => get_option( Site_Footer::OPTION_TWITTER_URL, '' ),
			'instagram_url'      => get_option( Site_Footer::OPTION_INSTAGRAM_URL, '' ),
			'linkedin_url'       => get_option( Site_Footer::OPTION_LINKEDIN_URL, '' ),
			'directory_url'      => $directory_url,
			'services_url'       => Page_Finder::url_for_shortcode( 'services_directory' ),
			'videos_url'         => $home_url . '#dak-home-videos',
			'clinics_url'        => $home_url . '#dak-home-clinics',
			'doctor_specialties' => Home_Page::specialties_in_use( $directory_url ),
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
