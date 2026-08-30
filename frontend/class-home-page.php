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
use DoctorAKPortal\Includes\Home_Videos;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Services;
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
	const FEATURED_SERVICES_LIMIT = 6;
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
				return $this->template_loader->get_template( 'directory/doctor-card.php', $card );
			},
			$doctor_cards
		);

		$service_groups = array_slice( Services::grouped_active_for_public_directory(), 0, self::FEATURED_SERVICES_LIMIT );

		$services_html = array_map(
			function ( $group ) {
				$group['profile_url'] = add_query_arg( 'service_id', $group['id'], Page_Finder::url_for_shortcode( 'service_profile_view' ) );

				return $this->template_loader->get_template( 'directory/service-card.php', $group );
			},
			$service_groups
		);

		return $this->template_loader->get_template(
			'directory/home-page.php',
			array(
				'doctors_html'     => $doctors_html,
				'services_html'    => $services_html,
				'videos'           => Home_Videos::get_all(),
				'hero_video_url'   => $this->bundled_asset_url( self::HERO_VIDEO_PATH ),
				'marketing_videos' => array_values( array_filter( array_map( array( $this, 'bundled_asset_url' ), self::MARKETING_VIDEO_PATHS ) ) ),
				'directory_url'    => Page_Finder::url_for_shortcode( 'doctors_directory' ),
				'services_url'     => Page_Finder::url_for_shortcode( 'services_directory' ),
				'stats'            => $this->stats( $doctor_cards ),
				'hero_doctor'      => $this->hero_doctor( $doctor_cards ),
				'clinic_locations' => array_slice( Clinic_Locations::get_all(), 0, self::FEATURED_CLINICS_LIMIT ),
			)
		);
	}

	/**
	 * Picks the doctor featured in the hero's floating card — the first
	 * currently-available one (so "Available Today" is actually true), or
	 * just the first doctor if none are marked available right now.
	 *
	 * @param array $doctor_cards Doctors_Directory::doctor_cards_data() rows.
	 * @return array|null Same shape as one row of $doctor_cards, or null if there are no doctors yet.
	 */
	private function hero_doctor( array $doctor_cards ) {
		if ( empty( $doctor_cards ) ) {
			return null;
		}

		foreach ( $doctor_cards as $card ) {
			if ( $card['is_available'] ) {
				return $card;
			}
		}

		return $doctor_cards[0];
	}

	/**
	 * Resolves a bundled plugin asset's public URL, or '' if the file isn't
	 * actually there (a plugin update or a manual removal of the sample
	 * videos shouldn't produce a broken <video> tag).
	 *
	 * @param string $relative_path Path relative to the plugin root, e.g. 'assets/videos/thumbnail.mp4'.
	 * @return string
	 */
	private function bundled_asset_url( $relative_path ) {
		if ( ! file_exists( DOCTOR_AK_PORTAL_PATH . $relative_path ) ) {
			return '';
		}

		return DOCTOR_AK_PORTAL_URL . $relative_path;
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
