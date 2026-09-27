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
			'doctor-ak-portal-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directory.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-directory.css' )
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
		// Bucketed the same way the site header's Services mega-menu already
		// groups them (Service_Categories order, uncategorized services
		// falling into "Miscellaneous/Other Services" rather than being
		// dropped) — so the filter chips always cover every listed service,
		// even on a site where categories were never assigned.
		$buckets    = Services::grouped_by_category_for_public_directory();
		$categories = array();
		$groups     = array();

		foreach ( $buckets as $bucket ) {
			$categories[ $bucket['slug'] ] = $bucket['label'];

			foreach ( $bucket['services'] as $service ) {
				// Overwrite with the bucket it actually landed in — an
				// uncategorized service's own 'category' field is still ''
				// (grouped_by_category_for_public_directory() only decides
				// *where* to bucket it, it doesn't relabel the row), but the
				// filter chip below is keyed by bucket slug, so the card
				// needs to say which bucket it's in to match.
				$service['category']       = $bucket['slug'];
				$service['category_label'] = $bucket['label'];

				$groups[] = $service;
			}
		}

		// Same wide-row template the home page's own services section uses
		// (directory/home-service-card.php) rather than the old compact
		// portrait card, so this directory page's list matches how services
		// already look on the home page.
		$services_html = array_map(
			function ( $group ) {
				return $this->template_loader->get_template( 'directory/home-service-card.php', $this->card_data( $group ) );
			},
			$groups
		);

		return $this->template_loader->get_template(
			'directory/services-directory.php',
			array(
				'services_html' => $services_html,
				'categories'    => $categories,
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
		$group['profile_url'] = add_query_arg( 'service_id', $group['id'], Page_Finder::url_for_shortcode( 'service_profile_view' ) );

		return $group;
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
