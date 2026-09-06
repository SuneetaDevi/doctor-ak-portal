<?php
/**
 * Backs the [blog_single] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Blogs;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blog_Single
 *
 * A public, read-only single-post page reached via `?blog_id=` on whichever
 * page contains [blog_single] (found dynamically by Page_Finder, same
 * pattern as Service_Profile_View/Doctor_Profile_View). Only ever shows a
 * Published post — an invalid/draft/missing ID renders the "not found" state
 * instead, never leaking a draft's content publicly.
 */
class Blog_Single {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'blog_single';

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
	 * Enqueues assets only on pages containing [blog_single].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_single_page() ) {
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
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$blog_id = isset( $_GET['blog_id'] ) ? absint( $_GET['blog_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public lookup.
		$blog    = $blog_id > 0 ? Blogs::find_for_public_view( $blog_id ) : null;

		return $this->template_loader->get_template(
			'directory/blog-single-view.php',
			array(
				'blog'          => $blog,
				'directory_url' => Page_Finder::url_for_shortcode( 'blogs_directory' ),
			)
		);
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * single-post shortcode.
	 *
	 * @return bool
	 */
	private function is_single_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
