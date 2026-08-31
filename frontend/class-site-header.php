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
 * header wherever the plugin is installed. The nav menu itself is a
 * registered WordPress menu location so the site owner can edit its links
 * from Appearance -> Menus; a fallback renders sensible defaults out of the
 * box until they do.
 */
class Site_Header {

	/**
	 * Registered nav menu location slug.
	 *
	 * @var string
	 */
	const MENU_LOCATION = 'doctor_ak_portal_header';

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
	 * Registers the header's nav menu location so it appears under
	 * Appearance -> Menus.
	 *
	 * @return void
	 */
	public function register_menu_location() {
		register_nav_menu( self::MENU_LOCATION, __( 'Doctor AK Portal Header', 'doctor-ak-portal' ) );
	}

	/**
	 * Turns "Online Video Consultation" / "Clinic Appointment" links in the
	 * header's nav menu into booking-modal triggers, by matching on their
	 * exact title text. Only relevant to a real menu the site owner assigned
	 * under Appearance -> Menus using plain "#" custom links with these
	 * exact labels — the coded fallback (render_fallback_menu()) no longer
	 * includes a Book Appointment nav item at all (it's the standalone CTA
	 * button instead), so this is a no-op there.
	 *
	 * @param array         $atts  Existing link attributes.
	 * @param \WP_Post      $item  Menu item.
	 * @param \stdClass|null $args wp_nav_menu() args, if available.
	 * @return array
	 */
	public function add_booking_trigger_attributes( $atts, $item, $args = null ) {
		if ( ! isset( $args->theme_location ) || self::MENU_LOCATION !== $args->theme_location ) {
			return $atts;
		}

		$title = trim( $item->title );

		if ( 'Online Video Consultation' === $title ) {
			$atts['data-dak-book-appointment'] = '';
			$atts['data-booking-type']         = 'video';
		} elseif ( 'Clinic Appointment' === $title ) {
			$atts['data-dak-book-appointment'] = '';
			$atts['data-booking-type']         = 'clinic';
		}

		return $atts;
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

		return array(
			'menu_location'   => self::MENU_LOCATION,
			'logo_url'        => self::bundled_logo_url(),
			'phone'           => self::primary_phone(),
			'is_logged_in'    => is_user_logged_in(),
			'user'            => $user,
			'user_avatar_url' => self::user_avatar_url( $user ),
			'dashboard_url' => is_user_logged_in()
				? Page_Finder::url_for_shortcode( self::dashboard_shortcode_for( $user, $is_doctor ) )
				: '',
			'profile_url'   => ( is_user_logged_in() && $profile_allowed ) ? Page_Finder::url_for_shortcode( 'doctor_profile' ) : '',
			'login_url'     => Page_Finder::url_for_shortcode( 'doctor_login' ),
			'logout_url'    => wp_logout_url( home_url( '/' ) ),
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

	/**
	 * Renders a sensible default menu when the site owner hasn't assigned
	 * one to the header's menu location yet (Appearance -> Menus).
	 *
	 * Four links: Services and Doctors go to their directory pages; Videos
	 * and Clinics jump to the matching section of the home page (see the
	 * `id="dak-home-videos"` / `id="dak-home-clinics"` anchors in
	 * templates/directory/home-page.php) since those aren't standalone
	 * pages. "Book Appointment" isn't in this list — it's the standalone
	 * CTA button in the header's auth area, not a nav link.
	 *
	 * @param array $args wp_nav_menu() args (only 'menu_class' is used here).
	 * @return void
	 */
	public static function render_fallback_menu( $args ) {
		$menu_class = isset( $args['menu_class'] ) ? $args['menu_class'] : '';
		$home_url   = Page_Finder::url_for_shortcode( 'dak_home' );
		$home_url   = $home_url ? $home_url : home_url( '/' );

		$links = array(
			__( 'Services', 'doctor-ak-portal' ) => Page_Finder::url_for_shortcode( 'services_directory' ),
			__( 'Doctors', 'doctor-ak-portal' )  => Page_Finder::url_for_shortcode( 'doctors_directory' ),
			__( 'Videos', 'doctor-ak-portal' )   => $home_url . '#dak-home-videos',
			__( 'Clinics', 'doctor-ak-portal' )  => $home_url . '#dak-home-clinics',
		);

		echo '<ul class="' . esc_attr( $menu_class ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		foreach ( $links as $title => $url ) {
			printf(
				'<li class="menu-item"><a href="%1$s">%2$s</a></li>',
				esc_url( $url ),
				esc_html( $title )
			);
		}

		echo '</ul>';
	}
}
