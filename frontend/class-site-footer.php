<?php
/**
 * Site-wide footer injected on every front-end page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Site_Footer
 *
 * Mirrors Site_Header: renders on every front-end request (via wp_footer)
 * rather than only on shortcode pages. "Quick Links" and "Our Services" are
 * registered WordPress menu locations the site owner edits from Appearance ->
 * Menus, each with a sensible fallback until one is assigned; everything
 * else (description, phone, social links, clinic info) comes from
 * Footer_Settings (Settings -> Footer Settings), defaulting to this
 * clinic's real, current content rather than a placeholder.
 */
class Site_Footer {

	const MENU_LOCATION_QUICK_LINKS = 'doctor_ak_portal_footer_quick_links';
	const MENU_LOCATION_SERVICES    = 'doctor_ak_portal_footer_services';

	const OPTION_DESCRIPTION     = 'doctor_ak_footer_description';
	const OPTION_PHONE           = 'doctor_ak_footer_phone';
	const OPTION_FACEBOOK_URL    = 'doctor_ak_footer_facebook_url';
	const OPTION_TWITTER_URL     = 'doctor_ak_footer_twitter_url';
	const OPTION_INSTAGRAM_URL   = 'doctor_ak_footer_instagram_url';
	const OPTION_LINKEDIN_URL    = 'doctor_ak_footer_linkedin_url';
	const OPTION_CLINIC_NAME     = 'doctor_ak_footer_clinic_name';
	const OPTION_CLINIC_ADDRESS  = 'doctor_ak_footer_clinic_address';
	const OPTION_CLINIC_PHONE    = 'doctor_ak_footer_clinic_phone';
	const OPTION_COPYRIGHT_NAME  = 'doctor_ak_footer_copyright_name';
	const OPTION_CLINIC_LOGO_URL  = 'doctor_ak_footer_clinic_logo_url';
	const OPTION_CLINIC_LOGO_PATH = 'doctor_ak_footer_clinic_logo_path';

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
	 * Registers the footer's two nav menu locations so they appear under
	 * Appearance -> Menus.
	 *
	 * @return void
	 */
	public function register_menu_locations() {
		register_nav_menu( self::MENU_LOCATION_QUICK_LINKS, __( 'Doctor AK Portal Footer — Quick Links', 'doctor-ak-portal' ) );
		register_nav_menu( self::MENU_LOCATION_SERVICES, __( 'Doctor AK Portal Footer — Our Services', 'doctor-ak-portal' ) );
	}

	/**
	 * Enqueues the footer's assets on every front-end page.
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
			'doctor-ak-portal-site-footer',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-site-footer.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-site-footer.css' )
		);
	}

	/**
	 * Renders the footer markup. Hooked to wp_footer.
	 *
	 * @return void
	 */
	public function render() {
		if ( is_admin() ) {
			return;
		}

		echo $this->template_loader->get_template( 'site-footer.php', $this->prepare_data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
	}

	/**
	 * Gathers the data templates/site-footer.php needs.
	 *
	 * @return array
	 */
	private function prepare_data() {
		$copyright_name = get_option( self::OPTION_COPYRIGHT_NAME, '' );

		return array(
			'quick_links_menu_location' => self::MENU_LOCATION_QUICK_LINKS,
			'services_menu_location'    => self::MENU_LOCATION_SERVICES,
			'logo_url'                  => self::bundled_logo_url(),
			'description'               => get_option( self::OPTION_DESCRIPTION, 'At Dr. A.K. Lohana Clinics And Endoscopy Services, We Provide Comprehensive Care For Digestive, Liver, And Gastrointestinal Conditions.' ),
			'phone'                     => get_option( self::OPTION_PHONE, '0303-3638304' ),
			'facebook_url'              => get_option( self::OPTION_FACEBOOK_URL, '' ),
			'twitter_url'               => get_option( self::OPTION_TWITTER_URL, '' ),
			'instagram_url'             => get_option( self::OPTION_INSTAGRAM_URL, '' ),
			'linkedin_url'              => get_option( self::OPTION_LINKEDIN_URL, '' ),
			'clinic_name'               => get_option( self::OPTION_CLINIC_NAME, 'Main Clinic' ),
			'clinic_address'            => get_option( self::OPTION_CLINIC_ADDRESS, 'Chugtai Lab, Khaliq Zaman Road, Adjacent To Bacha Party, Block 8 Clifton, Karachi.' ),
			'clinic_phone'              => get_option( self::OPTION_CLINIC_PHONE, '0303-3638304' ),
			'copyright_name'            => '' !== $copyright_name ? $copyright_name : get_bloginfo( 'name' ),
		);
	}

	/**
	 * Resolves the bundled logo's URL (same file the header uses) — public
	 * so email templates (Notifications::render_invoice_email()) can embed
	 * it too, since an email needs a real absolute image URL, not markup.
	 *
	 * @return string Logo URL, or '' if no bundled logo file exists.
	 */
	public static function bundled_logo_url() {
		$uploaded_url = get_option( self::OPTION_CLINIC_LOGO_URL, '' );

		if ( '' !== $uploaded_url ) {
			return $uploaded_url;
		}

		$relative_path = self::bundled_logo_relative_path();

		return '' !== $relative_path ? DOCTOR_AK_PORTAL_URL . $relative_path : '';
	}

	/**
	 * Resolves the bundled logo's absolute filesystem path — used by
	 * Invoice_Pdf, which needs to read the actual image bytes (GD can't
	 * fetch a URL reliably from inside an email-sending request).
	 *
	 * @return string Absolute path, or '' if no bundled logo file exists.
	 */
	public static function bundled_logo_path() {
		$uploaded_path = get_option( self::OPTION_CLINIC_LOGO_PATH, '' );

		if ( '' !== $uploaded_path && file_exists( $uploaded_path ) ) {
			return $uploaded_path;
		}

		$relative_path = self::bundled_logo_relative_path();

		return '' !== $relative_path ? DOCTOR_AK_PORTAL_PATH . $relative_path : '';
	}

	/**
	 * @return string e.g. 'assets/images/logo.png', or '' if none exists.
	 */
	private static function bundled_logo_relative_path() {
		foreach ( array( 'png', 'svg', 'jpg', 'jpeg', 'webp' ) as $extension ) {
			$relative_path = 'assets/images/logo.' . $extension;

			if ( file_exists( DOCTOR_AK_PORTAL_PATH . $relative_path ) ) {
				return $relative_path;
			}
		}

		return '';
	}

	/**
	 * Renders a sensible default "Quick Links" menu when the site owner
	 * hasn't assigned one to that location yet (Appearance -> Menus).
	 *
	 * @param array $args wp_nav_menu() args (only 'menu_class' is used here).
	 * @return void
	 */
	public static function render_fallback_quick_links_menu( $args ) {
		$menu_class = isset( $args['menu_class'] ) ? $args['menu_class'] : '';

		$items = array(
			__( 'Book Online Consultation', 'doctor-ak-portal' ) => Page_Finder::url_for_shortcode( 'doctors_directory' ),
			__( 'Refund & Return Policy', 'doctor-ak-portal' )   => self::find_page_url( __( 'Refund & Return Policy', 'doctor-ak-portal' ) ),
			__( 'Cancellation Policy', 'doctor-ak-portal' )      => self::find_page_url( __( 'Cancellation Policy', 'doctor-ak-portal' ) ),
			__( 'Privacy Policy', 'doctor-ak-portal' )           => self::find_page_url( __( 'Privacy Policy', 'doctor-ak-portal' ) ),
			__( 'Terms and Conditions', 'doctor-ak-portal' )     => self::find_page_url( __( 'Terms and Conditions', 'doctor-ak-portal' ) ),
		);

		self::render_fallback_list( $menu_class, $items );
	}

	/**
	 * Renders a sensible default "Our Services" menu when the site owner
	 * hasn't assigned one to that location yet (Appearance -> Menus). These
	 * are marketing/procedure names, not the plugin's bookable per-doctor
	 * Services (which have their own charges/duration) — purely informational
	 * links the site owner should point at real service-detail pages.
	 *
	 * @param array $args wp_nav_menu() args (only 'menu_class' is used here).
	 * @return void
	 */
	public static function render_fallback_services_menu( $args ) {
		$menu_class = isset( $args['menu_class'] ) ? $args['menu_class'] : '';

		$titles = array(
			__( 'Hemorrhoidal Band Ligation', 'doctor-ak-portal' ),
			__( 'Endoscopic Submucosal Dissection', 'doctor-ak-portal' ),
			__( 'Endoscopic Mucosal Resection', 'doctor-ak-portal' ),
			__( 'Endoscopic Ultrasound', 'doctor-ak-portal' ),
			__( 'Endoscopic Retrograde Cholangiopancreatography', 'doctor-ak-portal' ),
			__( 'Pediatric Colonoscopy', 'doctor-ak-portal' ),
			__( 'Pediatric Gastroscopy', 'doctor-ak-portal' ),
			__( 'GI Bleeding Management', 'doctor-ak-portal' ),
		);

		$items = array();
		foreach ( $titles as $title ) {
			$items[ $title ] = self::find_page_url( $title );
		}

		self::render_fallback_list( $menu_class, $items );
	}

	/**
	 * @param string $menu_class CSS class for the <ul>.
	 * @param array  $items      Label => URL.
	 * @return void
	 */
	private static function render_fallback_list( $menu_class, array $items ) {
		echo '<ul class="' . esc_attr( $menu_class ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		foreach ( $items as $label => $url ) {
			printf(
				'<li class="menu-item"><a href="%1$s">%2$s</a></li>',
				esc_url( $url ? $url : '#' ),
				esc_html( $label )
			);
		}

		echo '</ul>';
	}

	/**
	 * Looks up a published page by its exact title, for the fallback menus'
	 * best-effort links. Returns '' if no matching page is found — the site
	 * owner should assign a real menu to these locations for full control
	 * instead of relying on title matching.
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

		return '';
	}
}
