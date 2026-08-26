<?php
/**
 * Backs the [service_profile_view] shortcode.
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
 * Class Service_Profile_View
 *
 * A public, read-only detail page for one service NAME — reached via
 * `?service_id=` (any one Services row with that name) on whichever page
 * contains [service_profile_view] (found dynamically by Page_Finder, same
 * pattern as Doctor_Profile_View). A service added for several doctors at
 * once (see Service_Handler's bulk-create) is really one portfolio entry
 * with several doctor-owned rows — this page looks up every one of them
 * (Services::active_rows_by_name()) and shows a "Doctors & Pricing"
 * breakdown, each with its own price, clinics, and a "Book with Dr. X"
 * link into the normal booking flow.
 */
class Service_Profile_View {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'service_profile_view';

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
	 * Enqueues assets only on pages containing [service_profile_view].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_profile_view_page() ) {
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
		$service_id = isset( $_GET['service_id'] ) ? absint( $_GET['service_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public lookup.
		$service    = $service_id > 0 ? Services::find_for_public_profile( $service_id ) : null;
		$group      = null;

		if ( $service ) {
			$group = $this->build_group( $service['name'] );
		}

		return $this->template_loader->get_template(
			'directory/service-profile-view.php',
			array(
				'group'         => $group,
				'directory_url' => Page_Finder::url_for_shortcode( 'services_directory' ),
			)
		);
	}

	/**
	 * Gathers every doctor-owned row for a service name into one page's
	 * worth of data: the shared name/description/image (from whichever row
	 * has them), an overall price range, and a "Book with Dr. X" offer per
	 * doctor.
	 *
	 * @param string $name Service name (from an already-verified active row).
	 * @return array
	 */
	private function build_group( $name ) {
		$rows          = Services::active_rows_by_name( $name );
		$doctor_offers = array();
		$description   = '';
		$image_url     = '';
		$prices        = array();

		$base_booking_url = Page_Finder::url_for_shortcode( 'book_appointment' );

		foreach ( $rows as $row ) {
			$doctor = get_userdata( $row['doctor_id'] );

			if ( ! $doctor ) {
				continue;
			}

			if ( '' === $description && '' !== $row['description'] ) {
				$description = $row['description'];
			}

			if ( '' === $image_url && '' !== $row['image_url'] ) {
				$image_url = $row['image_url'];
			}

			$prices[] = $row['effective_price'];

			$doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$doctor_name = '' !== $doctor_name ? $doctor_name : $doctor->display_name;

			$doctor_offers[] = array(
				'doctor_id'          => $doctor->ID,
				'doctor_name'        => $doctor_name,
				'doctor_avatar_url'  => self::doctor_avatar_url( $doctor->ID ),
				'doctor_profile_url' => add_query_arg( 'doctor_id', $doctor->ID, Page_Finder::url_for_shortcode( 'doctor_profile_view' ) ),
				'price_label'        => $row['price_label'],
				'clinic_locations'   => $row['clinic_locations'],
				'booking_url'        => $base_booking_url ? add_query_arg( 'doctor_id', $doctor->ID, $base_booking_url ) : '',
			);
		}

		return array(
			'name'          => $name,
			'description'   => $description,
			'image_url'     => $image_url,
			'price_label'   => Services::price_range_label( $prices ),
			'doctor_offers' => $doctor_offers,
		);
	}

	/**
	 * Resolves a doctor's uploaded profile picture, falling back to a
	 * generic avatar if they haven't uploaded one — same as
	 * Doctors_Directory/Doctor_Profile_View, for the "Provided by" card.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private static function doctor_avatar_url( $doctor_id ) {
		$picture_id = (int) get_user_meta( $doctor_id, 'doctor_ak_profile_picture_id', true );

		if ( $picture_id > 0 ) {
			$url = wp_get_attachment_image_url( $picture_id, 'medium' );

			if ( $url ) {
				return $url;
			}
		}

		return get_avatar_url( $doctor_id, array( 'size' => 200 ) );
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * profile-view shortcode.
	 *
	 * @return bool
	 */
	private function is_profile_view_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
