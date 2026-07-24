<?php
/**
 * Backs the [featured_doctors] shortcode.
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
 * Class Featured_Doctors
 *
 * A homepage-friendly doctors slider: the same real doctor cards
 * Doctors_Directory shows in its grid (same data, same card partial), just
 * capped to a handful and laid out as a horizontal scroll-snap carousel
 * with a "View More" link to the full [doctors_directory] page.
 */
class Featured_Doctors {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'featured_doctors';

	/**
	 * How many doctors to show when the shortcode doesn't specify a `limit`
	 * attribute.
	 *
	 * @var int
	 */
	const DEFAULT_LIMIT = 8;

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Doctors directory controller — supplies the same card view-models and
	 * card partial the full directory grid uses.
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
	 * Enqueues assets only on pages containing [featured_doctors].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_featured_doctors_page() ) {
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
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes: `limit` (default self::DEFAULT_LIMIT).
	 *                           WordPress passes '' (not an array) when the
	 *                           shortcode is used with no attributes at all.
	 * @return string
	 */
	public function render( $atts = array() ) {
		$atts  = shortcode_atts( array( 'limit' => self::DEFAULT_LIMIT ), is_array( $atts ) ? $atts : array(), self::SHORTCODE_TAG );
		$limit = max( 0, (int) $atts['limit'] );

		$cards = $this->doctors_directory->doctor_cards_data( $limit );

		$doctors_html = array_map(
			function ( $card ) {
				return $this->template_loader->get_template( 'directory/doctor-card.php', $card );
			},
			$cards
		);

		return $this->template_loader->get_template(
			'directory/featured-doctors.php',
			array(
				'doctors_html'  => $doctors_html,
				'directory_url' => Page_Finder::url_for_shortcode( Doctors_Directory::SHORTCODE_TAG ),
			)
		);
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * featured-doctors shortcode.
	 *
	 * @return bool
	 */
	private function is_featured_doctors_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
