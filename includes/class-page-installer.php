<?php
/**
 * Auto-creates the plugin's front-end pages on activation.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Installer
 *
 * Historically this plugin required the site owner to hand-build a WordPress
 * page for each shortcode (see Page_Finder's docblock). This class replaces
 * that manual step: on activation, it creates a published page for any
 * shortcode that doesn't already have one — using Page_Finder::url_for_shortcode()
 * (the plugin's existing "does a page with this shortcode exist" check) so an
 * existing, hand-built page is always left alone and never duplicated.
 */
class Page_Installer {

	/**
	 * Option flag marking that the Home page's one-time "set as static front
	 * page" step has already run, so a later re-activation (or an admin who
	 * deliberately points Settings > Reading elsewhere afterwards) is never
	 * silently overridden again.
	 *
	 * @var string
	 */
	const HOME_FRONT_PAGE_OPTION = 'doctor_ak_home_front_page_set';

	/**
	 * Option tracking which version of the PAGES list has already been
	 * installed. Bump this whenever a page is added to PAGES so upgrades of
	 * an already-active install (where the activation hook never re-fires)
	 * still pick up the new page — see maybe_install().
	 *
	 * @var string
	 */
	const INSTALLED_VERSION_OPTION = 'doctor_ak_pages_installer_version';

	/**
	 * Current version of the PAGES list.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Self-healing install, safe to run on every request: does nothing once
	 * install() has already run for the current VERSION, so plugin.php can
	 * hook this to 'init' unconditionally (mirrors the same self-healing
	 * pattern already used for Notifications::ensure_video_link_cron_scheduled)
	 * rather than requiring the site owner to deactivate/reactivate the
	 * plugin every time a page is added.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( get_option( self::INSTALLED_VERSION_OPTION ) === self::VERSION ) {
			return;
		}

		self::install();

		update_option( self::INSTALLED_VERSION_OPTION, self::VERSION );
	}

	/**
	 * Shortcode tag => published page title, for every page this plugin owns.
	 * `dak_home` is intentionally first so it's created (and, the first time
	 * only, set as the site's static front page) before the rest.
	 *
	 * @var array
	 */
	const PAGES = array(
		'dak_home'                => 'Home',
		'doctors_directory'       => 'Our Doctors',
		'doctor_profile_view'     => 'Doctor Profile',
		'services_directory'      => 'Our Services',
		'service_profile_view'    => 'Service Details',
		'book_appointment'        => 'Book Appointment',
		'doctor_register'         => 'Register',
		'doctor_login'            => 'Login',
		'doctor_forgot_password'  => 'Forgot Password',
		'doctor_profile'          => 'My Profile',
		'doctor_dashboard'        => 'Doctor Dashboard',
		'patient_dashboard'       => 'Patient Dashboard',
		'admin_dashboard'         => 'Admin Dashboard',
	);

	/**
	 * Creates every missing plugin-owned page. Safe to call on every
	 * activation/reactivation — pages that already exist (whether created by
	 * this method before, or hand-built by the site owner) are left as-is.
	 *
	 * @return void
	 */
	public static function install() {
		foreach ( self::PAGES as $shortcode_tag => $title ) {
			self::ensure_page_exists( $shortcode_tag, $title );
		}
	}

	/**
	 * Creates the page for one shortcode if none currently hosts it.
	 *
	 * @param string $shortcode_tag Shortcode tag without brackets.
	 * @param string $title         Title to give the page if it's created.
	 * @return void
	 */
	private static function ensure_page_exists( $shortcode_tag, $title ) {
		if ( '' !== Page_Finder::url_for_shortcode( $shortcode_tag ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => '[' . $shortcode_tag . ']',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return;
		}

		delete_transient( Page_Finder::CACHE_PREFIX . $shortcode_tag );

		if ( 'dak_home' === $shortcode_tag ) {
			self::maybe_set_as_front_page( $page_id );
		}
	}

	/**
	 * The very first time the Home page is created, points Settings >
	 * Reading's static front page at it. Guarded by a one-time option so a
	 * site owner who later changes that setting themselves never gets
	 * overridden by a subsequent plugin reactivation.
	 *
	 * @param int $page_id Newly created Home page ID.
	 * @return void
	 */
	private static function maybe_set_as_front_page( $page_id ) {
		if ( get_option( self::HOME_FRONT_PAGE_OPTION ) ) {
			return;
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
		update_option( self::HOME_FRONT_PAGE_OPTION, 1 );
	}
}
