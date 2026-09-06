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
use DoctorAKPortal\Includes\Services;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Site_Footer
 *
 * Mirrors Site_Header: renders on every front-end request (via wp_footer)
 * rather than only on shortcode pages. Its link columns (Doctors, Services,
 * Clinics) are generated straight from the same real data the rest of the
 * public site already uses (Doctors_Directory, Services, Clinic_Locations) — not
 * site-owner-edited WordPress menus, since a stale hand-built menu could
 * easily point at a doctor/specialty/clinic that no longer exists. The
 * legal/policy links (Privacy Policy, Terms, etc.) that used to live in the
 * old "Quick Links" menu are still real WordPress pages, found by title —
 * see policy_links(). Everything else (description, phone, social links,
 * clinic branding used elsewhere in the plugin) comes from Footer_Settings
 * (Settings -> Footer Settings), defaulting to this clinic's real, current
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
	 * Max doctors listed in the footer's "Doctors" column before pointing
	 * the rest at the full directory — keeps the column tidy regardless of
	 * how many doctors are registered.
	 *
	 * @var int
	 */
	const FOOTER_DOCTORS_LIMIT = 8;

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Doctors directory controller — supplies the footer's "Doctors" column
	 * (same card data the directory grid/featured-doctors slider use).
	 *
	 * @var Doctors_Directory
	 */
	private $doctors_directory;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader   $template_loader   Template loader.
	 * @param Doctors_Directory $doctors_directory Doctors directory controller.
	 */
	public function __construct( Template_Loader $template_loader, Doctors_Directory $doctors_directory ) {
		$this->template_loader   = $template_loader;
		$this->doctors_directory = $doctors_directory;
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
			'logo_url'          => self::bundled_logo_url(),
			'brand_domain'      => self::BRAND_DOMAIN,
			'brand_tagline'     => __( 'Gastroenterology & Endoscopy · Karachi', 'doctor-ak-portal' ),
			'description'       => get_option( self::OPTION_DESCRIPTION, 'At Dr. A.K. Lohana Clinics And Endoscopy Services, We Provide Comprehensive Care For Digestive, Liver, And Gastrointestinal Conditions.' ),
			'phone'             => get_option( self::OPTION_PHONE, '0303-3638304' ),
			'facebook_url'      => get_option( self::OPTION_FACEBOOK_URL, '' ),
			'twitter_url'       => get_option( self::OPTION_TWITTER_URL, '' ),
			'instagram_url'     => get_option( self::OPTION_INSTAGRAM_URL, '' ),
			'linkedin_url'      => get_option( self::OPTION_LINKEDIN_URL, '' ),
			'doctors'           => self::doctors_for_footer( $directory_url ),
			'directory_url'     => $directory_url,
			'services'          => self::services_for_footer(),
			'clinics_by_city'   => self::clinics_by_city_for_footer( $directory_url ),
			'policy_links'      => self::policy_links(),
		);
	}

	/**
	 * Real, active doctors for the footer's "Doctors" column — same card
	 * data the directory grid/featured-doctors slider use, capped at
	 * FOOTER_DOCTORS_LIMIT with the full directory linked separately for
	 * "see all".
	 *
	 * @param string $directory_url URL of the [doctors_directory] page, or '' if not found.
	 * @return array [{name, url}, ...]
	 */
	private function doctors_for_footer( $directory_url ) {
		return array_map(
			function ( $card ) {
				return array(
					'name' => sprintf( 'Dr. %s', $card['name'] ),
					'url'  => $card['profile_url'],
				);
			},
			$this->doctors_directory->doctor_cards_data( self::FOOTER_DOCTORS_LIMIT )
		);
	}

	/**
	 * Real, bookable services for the footer's "Services" column — the same
	 * grouped-by-name rows the [services_directory] grid uses, each linking
	 * to that service's own detail page.
	 *
	 * @return array [{name, url}, ...]
	 */
	private static function services_for_footer() {
		$service_profile_url = Page_Finder::url_for_shortcode( 'service_profile_view' );

		return array_map(
			function ( $group ) use ( $service_profile_url ) {
				return array(
					'name' => $group['name'],
					'url'  => $service_profile_url ? add_query_arg( 'service_id', $group['id'], $service_profile_url ) : '',
				);
			},
			Services::grouped_active_for_public_directory()
		);
	}

	/**
	 * Physical clinic locations grouped by city for the footer's "Clinics"
	 * column — one row per distinct city, each linking to the doctors
	 * directory pre-filtered to it (?city=<slug>, the same deep link
	 * assets/js/doctor-ak-directory.js's presetCity handling already reads).
	 *
	 * @param string $directory_url URL of the [doctors_directory] page, or '' if not found.
	 * @return array [{label, url}, ...], alphabetical by city.
	 */
	private static function clinics_by_city_for_footer( $directory_url ) {
		$cities = array();

		foreach ( Clinic_Locations::get_all() as $clinic_location ) {
			if ( '' === $clinic_location['city'] || isset( $cities[ $clinic_location['city'] ] ) ) {
				continue;
			}

			$cities[ $clinic_location['city'] ] = array(
				'label' => $clinic_location['city_label'],
				'url'   => $directory_url ? add_query_arg( 'city', $clinic_location['city'], $directory_url ) : '',
			);
		}

		uasort(
			$cities,
			function ( $a, $b ) {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		return array_values( $cities );
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
