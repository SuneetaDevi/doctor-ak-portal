<?php
/**
 * Backs the [videos_gallery] shortcode — the public Videos page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Home_Videos;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Videos_Gallery
 *
 * Every video staff manage from the dashboard's Videos section (the same
 * Home_Videos list whose first few the home page shows), as a gallery grid.
 * The home page's "View all videos" button and the site header's "Videos"
 * link both lead here.
 */
class Videos_Gallery {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'videos_gallery';

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
	 * Registers the shortcode.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render' ) );
	}

	/**
	 * Enqueues assets only on the page containing the shortcode.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) || ! has_shortcode( $post->post_content, self::SHORTCODE_TAG ) ) {
			return;
		}

		wp_enqueue_style( 'doctor-ak-portal-auth', DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css', array(), Assets::version( 'assets/css/doctor-ak-auth.css' ) );
		wp_enqueue_style( 'doctor-ak-portal-directory', DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directory.css', array( 'doctor-ak-portal-auth' ), Assets::version( 'assets/css/doctor-ak-directory.css' ) );
		wp_enqueue_script( 'doctor-ak-portal-videos-gallery', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-videos-gallery.js', array(), Assets::version( 'assets/js/doctor-ak-videos-gallery.js' ), true );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		return $this->template_loader->get_template(
			'directory/videos-gallery.php',
			array(
				'videos' => Home_Videos::get_all(),
			)
		);
	}
}
