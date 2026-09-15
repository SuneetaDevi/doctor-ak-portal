<?php
/**
 * Site-wide footer injected on every front-end page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
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
 * rather than only on shortcode pages. Its "Quick Links" nav points at each
 * major site section's real page (Doctors, Services, Clinics/Locations,
 * Book Appointment, Blogs, About Us), resolved via Page_Finder rather than a
 * site-owner-edited WordPress menu — see quick_links(). Legal/policy links
 * (Privacy Policy, Terms, etc.) are real WordPress pages found by title —
 * see policy_links(). Everything else (description, phone, address, social
 * links) comes from Footer_Settings (Settings -> Footer Settings) or the
 * first clinic location on file, defaulting to this clinic's real, current
 * content rather than a placeholder.
 */
class Site_Footer {

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
	 * The site's brand domain — shown in the footer in place of the logo
	 * when no bundled logo file exists, and in the copyright line, per the
	 * site owner's explicit request to lead with the domain rather than any
	 * one person's name.
	 *
	 * @var string
	 */
	const BRAND_DOMAIN = 'drakhlana.com';

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
		$directory_url = Page_Finder::url_for_shortcode( 'doctors_directory' );

		return array(
			'logo_url'       => self::bundled_logo_url(),
			'brand_domain'   => self::BRAND_DOMAIN,
			'description'    => get_option( self::OPTION_DESCRIPTION, 'Your trusted platform to find doctors, book appointments, and manage your healthcare — all in one place.' ),
			'phone'          => get_option( self::OPTION_PHONE, '0303-3638304' ),
			'email'          => self::primary_email(),
			'address'        => self::primary_address(),
			'facebook_url'   => get_option( self::OPTION_FACEBOOK_URL, '' ),
			'twitter_url'    => get_option( self::OPTION_TWITTER_URL, '' ),
			'instagram_url'  => get_option( self::OPTION_INSTAGRAM_URL, '' ),
			'linkedin_url'   => get_option( self::OPTION_LINKEDIN_URL, '' ),
			'quick_links'    => self::quick_links( $directory_url ),
			'policy_links'   => self::policy_links(),
		);
	}

	/**
	 * The footer's compact "Quick Links" nav — one link per major site
	 * section (not an exhaustive per-doctor/per-service/per-city listing,
	 * see the numbered 2-column layout in templates/site-footer.php), each
	 * dropped silently when its page isn't found rather than shown as a
	 * dead link.
	 *
	 * @param string $directory_url URL of the [doctors_directory] page, or '' if not found.
	 * @return array [{label, url}, ...]
	 */
	private static function quick_links( $directory_url ) {
		$candidates = array(
			array( __( 'About Us', 'doctor-ak-portal' ), home_url( '/' ) ),
			array( __( 'Doctors', 'doctor-ak-portal' ), $directory_url ),
			array( __( 'Services', 'doctor-ak-portal' ), Page_Finder::url_for_shortcode( 'services_directory' ) ),
			array( __( 'Clinics / Locations', 'doctor-ak-portal' ), Page_Finder::url_for_shortcode( 'clinics_directory' ) ),
			array( __( 'Book Appointment', 'doctor-ak-portal' ), Page_Finder::url_for_shortcode( 'book_appointment' ) ),
			array( __( 'Blogs', 'doctor-ak-portal' ), Page_Finder::url_for_shortcode( 'blogs_directory' ) ),
		);

		$links = array();

		foreach ( $candidates as $candidate ) {
			list( $label, $url ) = $candidate;

			if ( $url ) {
				$links[] = array(
					'label' => $label,
					'url'   => $url,
				);
			}
		}

		return $links;
	}

	/**
	 * Resolves a contact email for the footer — the first clinic location
	 * with one on file, mirroring Site_Header::primary_email() (the plugin
	 * has no separate site-wide "contact email" setting).
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
	 * Resolves a postal address for the footer — the first clinic location
	 * with one on file, mirroring Site_Header::primary_phone()/primary_email().
	 *
	 * @return string Address, or '' if no clinic has one set.
	 */
	private static function primary_address() {
		foreach ( Clinic_Locations::get_all() as $clinic_location ) {
			if ( '' !== $clinic_location['address'] ) {
				return $clinic_location['address'];
			}
		}

		return '';
	}

	/**
	 * Legal/policy pages for the footer's bottom bar, found by title — the
	 * same best-effort lookup the old "Quick Links" menu's fallback used
	 * (see find_page_url()), now the only thing that column was for once
	 * Doctors/Specialities/Services/Clinics became their own real columns.
	 *
	 * @return array [{label, url}, ...] — an entry is dropped entirely (not
	 *               shown as a dead link) when no matching page is published.
	 */
	private static function policy_links() {
		$titles = array(
			__( 'Privacy Policy', 'doctor-ak-portal' ),
			__( 'Terms and Conditions', 'doctor-ak-portal' ),
			__( 'Cancellation Policy', 'doctor-ak-portal' ),
			__( 'Refund & Return Policy', 'doctor-ak-portal' ),
		);

		$links = array();

		foreach ( $titles as $title ) {
			$url = self::find_page_url( $title );

			if ( '' !== $url ) {
				$links[] = array(
					'label' => $title,
					'url'   => $url,
				);
			}
		}

		return $links;
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
	 * Looks up a published page by its exact title, for policy_links()'s
	 * best-effort links. Returns '' if no matching page is found.
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
