<?php
/**
 * Backs the [blogs_directory] shortcode.
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
 * Class Blogs_Directory
 *
 * A public, unauthenticated grid of every Published post, newest first, each
 * linking to its own [blog_single] page — mirrors Services_Directory's own
 * directory/profile-view split.
 */
class Blogs_Directory {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'blogs_directory';

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
	 * Enqueues directory assets only on pages containing [blogs_directory].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_directory_page() ) {
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
			'doctor-ak-portal-blogs-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-blogs-directory.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-blogs-directory.js' ),
			true
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$single_url = Page_Finder::url_for_shortcode( 'blog_single' );
		$blogs      = Blogs::published_for_public_directory();

		$blogs_html = array_map(
			function ( $blog ) use ( $single_url ) {
				$blog['view_url'] = $single_url ? add_query_arg( 'blog_id', $blog['id'], $single_url ) : '';

				return $this->template_loader->get_template( 'directory/blog-card.php', $blog );
			},
			$blogs
		);

		$topics = self::topic_counts( $blogs );

		return $this->template_loader->get_template(
			'directory/blogs-directory.php',
			array(
				'blogs_html'   => $blogs_html,
				'topics'       => $topics,
				'quick_topics' => self::quick_topics( $topics ),
			)
		);
	}

	/**
	 * How many chips sit in the filter row before "All topics".
	 *
	 * @var int
	 */
	const QUICK_TOPIC_COUNT = 6;

	/**
	 * Every topic used by the listed posts, alphabetical, with its post count
	 * — keyed by the same slug the cards carry in data-topic, so labels that
	 * differ only in case ("Stones"/"stones") are one filter.
	 *
	 * @param array $blogs Decoded published posts.
	 * @return array slug => array( 'slug', 'label', 'count' ).
	 */
	private static function topic_counts( array $blogs ) {
		$topics = array();

		foreach ( $blogs as $blog ) {
			$slug = '' !== $blog['topic'] ? sanitize_title( $blog['topic'] ) : '';

			if ( '' === $slug ) {
				continue;
			}

			if ( ! isset( $topics[ $slug ] ) ) {
				$topics[ $slug ] = array(
					'slug'  => $slug,
					'label' => $blog['topic'],
					'count' => 0,
				);
			}

			++$topics[ $slug ]['count'];
		}

		uasort(
			$topics,
			function ( $a, $b ) {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		return $topics;
	}

	/**
	 * The most-used topics (ties alphabetical) for the always-visible chips;
	 * every topic stays reachable from the "All topics" panel.
	 *
	 * @param array $topics See topic_counts().
	 * @return array
	 */
	private static function quick_topics( array $topics ) {
		$sorted = array_values( $topics );

		usort(
			$sorted,
			function ( $a, $b ) {
				return $b['count'] !== $a['count'] ? $b['count'] - $a['count'] : strcasecmp( $a['label'], $b['label'] );
			}
		);

		return array_slice( $sorted, 0, self::QUICK_TOPIC_COUNT );
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * directory shortcode.
	 *
	 * @return bool
	 */
	private function is_directory_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
