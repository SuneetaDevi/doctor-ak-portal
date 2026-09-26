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

		wp_enqueue_script(
			'doctor-ak-portal-blog-single',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-blog-single.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-blog-single.js' ),
			true
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

		$single_url = Page_Finder::url_for_shortcode( 'blog_single' );
		$share_url  = ( $blog && $single_url ) ? add_query_arg( 'blog_id', $blog['id'], $single_url ) : '';

		return $this->template_loader->get_template(
			'directory/blog-single-view.php',
			array(
				'blog'          => $blog,
				'directory_url' => Page_Finder::url_for_shortcode( 'blogs_directory' ),
				'share_url'     => $share_url,
				'related_html'  => $blog ? $this->related_cards_html( $blog, $single_url ) : array(),
			)
		);
	}

	/**
	 * Up to three other published posts for the "Related articles" row —
	 * same-category posts first, then the newest of the rest — rendered with
	 * the same card the Blog page uses.
	 *
	 * @param array  $blog       The post being viewed.
	 * @param string $single_url URL of the [blog_single] page, or ''.
	 * @return string[]
	 */
	private function related_cards_html( array $blog, $single_url ) {
		$others = array_values(
			array_filter(
				Blogs::published_for_public_directory(),
				function ( $other ) use ( $blog ) {
					return (int) $other['id'] !== (int) $blog['id'];
				}
			)
		);

		usort(
			$others,
			function ( $a, $b ) use ( $blog ) {
				$a_same = '' !== $blog['topic'] && $a['topic'] === $blog['topic'] ? 1 : 0;
				$b_same = '' !== $blog['topic'] && $b['topic'] === $blog['topic'] ? 1 : 0;

				return $b_same - $a_same;
			}
		);

		return array_map(
			function ( $other ) use ( $single_url ) {
				$other['view_url'] = $single_url ? add_query_arg( 'blog_id', $other['id'], $single_url ) : '';

				return $this->template_loader->get_template( 'directory/blog-card.php', $other );
			},
			array_slice( $others, 0, 3 )
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
