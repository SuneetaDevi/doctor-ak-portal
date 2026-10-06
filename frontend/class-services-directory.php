<?php
/**
 * Backs the [services_directory] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Services;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Services_Directory
 *
 * A public, unauthenticated list — one row per unique service name, each
 * linking to that service's own [service_profile_view] detail page (which
 * lists every doctor/clinic it's actually offered through, see
 * Services::grouped_active_for_public_directory()) — the same directory/
 * profile-view split already used for Doctors (see Doctors_Directory/
 * Doctor_Profile_View). Backed by the same Services rows the admin/doctor
 * "Services" section already manages, not a separate table.
 */
class Services_Directory {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'services_directory';

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
	 * Enqueues directory assets only on pages containing [services_directory].
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
			'doctor-ak-portal-directories',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directories.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-directories.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-services-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-services-directory.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-services-directory.js' ),
			true
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		// Bucketed the same way the site header's Services menu groups them
		// (Service_Categories order, uncategorized services falling into
		// "Miscellaneous/Other Services" rather than being dropped) — so the
		// category filter always covers every listed service.
		$buckets    = Services::grouped_by_category_for_public_directory();
		$categories = array();
		$groups     = array();

		foreach ( $buckets as $bucket ) {
			$categories[] = array(
				'slug'  => $bucket['slug'],
				'label' => $bucket['label'],
				'count' => count( $bucket['services'] ),
			);

			foreach ( $bucket['services'] as $service ) {
				// The card's category is the bucket it landed in — an
				// uncategorized service's own 'category' is still '', but
				// the filter is keyed by bucket slug.
				$service['category']       = $bucket['slug'];
				$service['category_label'] = $bucket['label'];

				$groups[] = $service;
			}
		}

		// Alphabetical across categories, so "All services" reads as one list.
		usort(
			$groups,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		$services_html = array_map(
			function ( $group ) {
				return $this->template_loader->get_template( 'directory/service-directory-card.php', $this->card_data( $group ) );
			},
			$groups
		);

		return $this->template_loader->get_template(
			'directory/services-directory.php',
			array(
				'services_html' => $services_html,
				'categories'    => $categories,
				'total'         => count( $groups ),
			)
		);
	}

	/**
	 * Builds a single service card's view-model.
	 *
	 * @param array $group One entry from Services::grouped_active_for_public_directory().
	 * @return array
	 */
	private function card_data( array $group ) {
		$profile_url = Page_Finder::url_for_shortcode( 'service_profile_view' );

		return array(
			'id'              => $group['id'],
			'name'            => $group['name'],
			'excerpt'         => Services::plain_excerpt( $group['description'], $group['name'] ),
			'image_url'       => $group['image_url'],
			'category'        => $group['category'],
			'category_label'  => $group['category_label'],
			'keywords'        => $group['keywords'],
			'requires_doctor' => ! empty( $group['requires_doctor'] ),
			'provider_count'  => (int) $group['provider_count'],
			'price'           => $group['public_price'],
			'profile_url'     => $profile_url ? add_query_arg( 'service_id', $group['id'], $profile_url ) : '',
		);
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
