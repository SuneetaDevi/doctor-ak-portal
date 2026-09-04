<?php
/**
 * Backs the [dak_home] shortcode — the plugin-owned public home page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Google_Reviews;
use DoctorAKPortal\Includes\Home_Testimonials;
use DoctorAKPortal\Includes\Home_Videos;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Services;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Home_Page
 *
 * Composes the marketing home page from data the plugin already tracks —
 * no new queries: doctor cards from Doctors_Directory (same view-model the
 * directory grid and Featured_Doctors slider already use), service cards
 * from Services::grouped_active_for_public_directory() (same as
 * Services_Directory), and headline stats from existing user/appointment
 * counts. Rendered on a dedicated page created by Page_Installer on
 * activation and set as the site's static front page.
 */
class Home_Page {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'dak_home';

	/**
	 * How many doctors/services to feature on the home page.
	 *
	 * @var int
	 */
	const FEATURED_DOCTORS_LIMIT  = 8;
	const FEATURED_SERVICES_LIMIT = 3;
	const FEATURED_CLINICS_LIMIT  = 6;

	/**
	 * Bundled hero preview video shipped with the plugin (assets/videos/) —
	 * distinct from the admin-uploaded Home_Videos list: this one plays
	 * inline in the hero itself, not the "Marketing Videos" grid below.
	 *
	 * @var string
	 */
	const HERO_VIDEO_PATH = 'assets/videos/thumbnail.mp4';

	/**
	 * Bundled hero banner photo (assets/images/) shown behind the hero
	 * headline.
	 *
	 * @var string
	 */
	const HERO_BANNER_IMAGE_PATH = 'assets/images/doctor-banner.avif';

	/**
	 * Illustration for each "Why Choose Us" row, keyed by the icon its trust
	 * point uses in directory/home-page.php so the two stay in step.
	 *
	 * @var string[]
	 */
	const WHY_IMAGE_PATHS = array(
		'shield' => 'assets/images/why-verified-specialists.webp',
		'clock'  => 'assets/images/why-fast-easy-booking.webp',
		'video'  => 'assets/images/why-in-person-or-online.webp',
		'tag'    => 'assets/images/why-transparent-pricing.webp',
	);

	/**
	 * Bundled marketing reel shown in its own section, separate from the
	 * admin-uploaded Home_Videos list.
	 *
	 * @var string[]
	 */
	const MARKETING_VIDEO_PATHS = array(
		'assets/videos/video-1.mp4',
		'assets/videos/video-2.mp4',
		'assets/videos/video-3.mp4',
	);

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Doctors directory controller — supplies the same doctor card
	 * view-models the full directory and featured-doctors slider use.
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
	 * Enqueues assets only on the Home page itself. Pulls in the same
	 * directory/featured-doctors stylesheets those sections' markup already
	 * depends on (their own controllers only enqueue when THEIR shortcode is
	 * on the page, which isn't the case here), plus the home page's own
	 * layout stylesheet for the hero/trust-strip/testimonials.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_home_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directory.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-directory.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-featured-doctors',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-featured-doctors.css',
			array( 'doctor-ak-portal-directory' ),
			Assets::version( 'assets/css/doctor-ak-featured-doctors.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-featured-doctors',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-featured-doctors.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-featured-doctors.js' ),
			true
		);

		wp_enqueue_style(
			'doctor-ak-portal-home',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-home.css',
			array( 'doctor-ak-portal-directory', 'doctor-ak-portal-featured-doctors' ),
			Assets::version( 'assets/css/doctor-ak-home.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-home',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-home.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-home.js' ),
			true
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$doctor_cards = $this->doctors_directory->doctor_cards_data( self::FEATURED_DOCTORS_LIMIT );

		$doctors_html = array_map(
			function ( $card ) {
				return $this->template_loader->get_template( 'directory/home-doctor-card.php', $card );
			},
			$doctor_cards
		);

		$service_groups = array_slice( Services::grouped_active_for_public_directory(), 0, self::FEATURED_SERVICES_LIMIT );

		$services_html = array_map(
			function ( $group ) {
				$group['profile_url'] = add_query_arg( 'service_id', $group['id'], Page_Finder::url_for_shortcode( 'service_profile_view' ) );

				return $this->template_loader->get_template( 'directory/home-service-card.php', $group );
			},
			$service_groups
		);

		$directory_url = Page_Finder::url_for_shortcode( 'doctors_directory' );

		return $this->template_loader->get_template(
			'directory/home-page.php',
			array(
				'doctors_html'     => $doctors_html,
				'services_html'    => $services_html,
				'specialties'      => self::specialties_in_use( $directory_url ),
				'videos'           => Home_Videos::get_all(),
				'testimonials'     => array_merge( Home_Testimonials::get_all(), Google_Reviews::get_reviews() ),
				'google_rating'    => Google_Reviews::overall_rating(),
				'hero_video_url'   => $this->bundled_asset_url( self::HERO_VIDEO_PATH ),
				'hero_banner_url'  => $this->bundled_asset_url( self::HERO_BANNER_IMAGE_PATH ),
				'why_images'       => array_map( array( $this, 'bundled_asset_url' ), self::WHY_IMAGE_PATHS ),
				'marketing_videos' => array_values( array_filter( array_map( array( $this, 'bundled_asset_url' ), self::MARKETING_VIDEO_PATHS ) ) ),
				'directory_url'    => $directory_url,
				'booking_url'      => Page_Finder::url_for_shortcode( Booking_Page::SHORTCODE_TAG ),
				'services_url'     => Page_Finder::url_for_shortcode( 'services_directory' ),
				'stats'            => $this->stats( $doctor_cards ),
				'clinic_locations' => array_slice( Clinic_Locations::get_all(), 0, self::FEATURED_CLINICS_LIMIT ),
			)
		);
	}

	/**
	 * The specializations at least one registered doctor actually has, most
	 * represented first — never the full canonical Specializations::get_all()
	 * list, so a tile can't send a visitor to an empty filtered directory and
	 * the booking form's Department picker can't offer one nobody covers.
	 *
	 * Each row's URL preselects that specialty in the directory's filter (see
	 * assets/js/doctor-ak-directory.js), so the value has to be the lowercased
	 * label the filter's <option>s carry, not the slug.
	 *
	 * Public/static so the site header's Doctors mega-menu (see
	 * Site_Header::prepare_data()) can list the same real specialties without
	 * duplicating this query.
	 *
	 * @param string $directory_url URL of the [doctors_directory] page, or '' if not found.
	 * @return array List of { slug, label, count, url }.
	 */
	public static function specialties_in_use( $directory_url ) {
		$counts = array();

		foreach ( get_users( array( 'role' => Roles::DOCTOR_ROLE, 'fields' => 'ID' ) ) as $doctor_id ) {
			foreach ( (array) get_user_meta( $doctor_id, 'doctor_ak_specializations', true ) as $slug ) {
				if ( '' === $slug ) {
					continue;
				}

				$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
			}
		}

		arsort( $counts );

		$all         = Specializations::get_all();
		$specialties = array();

		foreach ( $counts as $slug => $count ) {
			$label = isset( $all[ $slug ] ) ? $all[ $slug ] : $slug;

			$specialties[] = array(
				'slug'  => $slug,
				'label' => $label,
				'count' => $count,
				'url'   => '' === $directory_url ? '' : add_query_arg( 'specialization', mb_strtolower( $label ), $directory_url ),
			);
		}

		return $specialties;
	}

	/**
	 * Resolves a bundled plugin asset's public URL, or '' if the file isn't
	 * actually there (a plugin update or a manual removal of the sample
	 * videos shouldn't produce a broken <video> tag).
	 *
	 * Public/static — PHP resolves `array( $this, 'bundled_asset_url' )`
	 * (used below via array_map()) as a valid callable for a static method
	 * too, so this didn't need to change at its call sites. Made public so
	 * the site header's Doctors mega-menu backdrop (see
	 * Site_Header::prepare_data()) can reuse the same hero photo without a
	 * second file_exists()/cache-busting implementation.
	 *
	 * @param string $relative_path Path relative to the plugin root, e.g. 'assets/videos/thumbnail.mp4'.
	 * @return string
	 */
	public static function bundled_asset_url( $relative_path ) {
		if ( ! file_exists( DOCTOR_AK_PORTAL_PATH . $relative_path ) ) {
			return '';
		}

		// Versioned on the file's own modification time, exactly like the
		// enqueued CSS/JS. These URLs are otherwise identical from one release
		// to the next, so replacing a bundled clip or photo in assets/ would
		// leave browsers and CDNs serving the previously cached copy.
		return add_query_arg(
			'ver',
			Assets::version( $relative_path ),
			DOCTOR_AK_PORTAL_URL . $relative_path
		);
	}

	/**
	 * Headline trust-strip numbers, computed from data the plugin already
	 * tracks (no new tables).
	 *
	 * @param array $doctor_cards Doctors_Directory::doctor_cards_data() rows, for years_experience.
	 * @return array { doctors_count, patients_count, appointments_count, max_years_experience, clinics_count }
	 */
	private function stats( array $doctor_cards ) {
		$years = array_filter(
			array_map(
				function ( $card ) {
					return '' !== $card['years_experience'] ? (int) $card['years_experience'] : 0;
				},
				$doctor_cards
			)
		);

		return array(
			'doctors_count'        => count( get_users( array( 'role' => Roles::DOCTOR_ROLE, 'fields' => 'ID' ) ) ),
			'patients_count'       => count( get_users( array( 'role' => Roles::PATIENT_ROLE, 'fields' => 'ID' ) ) ),
			'appointments_count'   => Appointments::total_count(),
			'max_years_experience' => ! empty( $years ) ? max( $years ) : 0,
			'clinics_count'        => Clinic_Locations::total_count(),
		);
	}

	/**
	 * Checks whether the current request is for the page containing
	 * [dak_home].
	 *
	 * @return bool
	 */
	private function is_home_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
