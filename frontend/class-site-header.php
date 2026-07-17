<?php
/**
 * Site-wide header injected on every front-end page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;
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
	 * exact title text. This works whether the menu is the coded fallback
	 * (render_fallback_menu(), which already carries these attributes
	 * directly) or a real menu the site owner assigned under Appearance ->
	 * Menus using plain "#" custom links with these exact labels.
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

		return array(
			'menu_location'   => self::MENU_LOCATION,
			'logo_url'        => self::bundled_logo_url(),
			'is_logged_in'    => is_user_logged_in(),
			'user'            => $user,
			'user_avatar_url' => self::user_avatar_url( $user ),
			'dashboard_url' => is_user_logged_in()
				? Page_Finder::url_for_shortcode( self::dashboard_shortcode_for( $user, $is_doctor ) )
				: '',
			'profile_url'   => is_user_logged_in() ? Page_Finder::url_for_shortcode( 'doctor_profile' ) : '',
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
	 * Matches the nav requested for this site: Home, Doctors, Intragastric
	 * Balloon (IGB), Services, Blogs, Contact Us, and a "Book Appointment"
	 * item with "Online Video Consultation" / "Clinic Appointment"
	 * sub-items. Since those two booking destinations don't exist yet (no
	 * booking module), they link to "#" until one does.
	 *
	 * @param array $args wp_nav_menu() args (only 'menu_class' is used here).
	 * @return void
	 */
	public static function render_fallback_menu( $args ) {
		$menu_class = isset( $args['menu_class'] ) ? $args['menu_class'] : '';

		$titles = array(
			__( 'Doctors', 'doctor-ak-portal' ),
			__( 'Intragastric Balloon (IGB)', 'doctor-ak-portal' ),
			__( 'Services', 'doctor-ak-portal' ),
			__( 'Blogs', 'doctor-ak-portal' ),
			__( 'Contact Us', 'doctor-ak-portal' ),
		);

		$pages = array();
		foreach ( $titles as $title ) {
			$pages[ $title ] = self::find_page_url( $title );
		}

		echo '<ul class="' . esc_attr( $menu_class ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		printf(
			'<li class="menu-item"><a href="%1$s">%2$s</a></li>',
			esc_url( home_url( '/' ) ),
			esc_html__( 'Home', 'doctor-ak-portal' )
		);

		foreach ( $pages as $title => $url ) {
			printf(
				'<li class="menu-item"><a href="%1$s">%2$s</a></li>',
				esc_url( $url ),
				esc_html( $title )
			);
		}

		printf(
			'<li class="menu-item menu-item-has-children"><a href="#">%1$s</a><ul class="sub-menu"><li class="menu-item"><a href="#" data-dak-book-appointment data-booking-type="video">%2$s</a></li><li class="menu-item"><a href="#" data-dak-book-appointment data-booking-type="clinic">%3$s</a></li></ul></li>',
			esc_html__( 'Book Appointment', 'doctor-ak-portal' ),
			esc_html__( 'Online Video Consultation', 'doctor-ak-portal' ),
			esc_html__( 'Clinic Appointment', 'doctor-ak-portal' )
		);

		echo '</ul>';
	}

	/**
	 * Looks up a published page by its exact title, for the fallback menu's
	 * best-effort links. Returns '#' if no matching page is found — the
	 * site owner should assign a real menu to this location for full
	 * control instead of relying on title matching.
	 *
	 * @param string $title Page title to match.
	 * @return string
	 */
	private static function find_page_url( $title ) {
		$query = new \WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'title'          => $title,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $query->posts ) ) {
			return get_permalink( $query->posts[0] );
		}

		return '#';
	}
}
