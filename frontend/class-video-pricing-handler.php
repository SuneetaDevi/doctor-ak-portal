<?php
/**
 * AJAX handlers backing a doctor's "Video Consultation" tab (dashboard) and
 * the admin dashboard's "Video Consultation" section.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Video_Pricing;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Video_Pricing_Handler
 *
 * Doctors manage only their own video price (ownership is implicit — there's
 * one row per doctor, keyed by user ID); administrators can set/override any
 * doctor's video price from the "Video Consultation" admin section, reusing
 * the exact same save logic instead of duplicating it, gated by a separate
 * nonce + `manage_options`. Mirrors Service_Handler's dual doctor/admin
 * endpoint pattern.
 */
class Video_Pricing_Handler {

	/**
	 * Nonce action for the doctor-facing Video Consultation tab.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_video_pricing';

	/**
	 * Enqueues Video Consultation tab assets only when that tab is active.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_video_pricing_tab_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-registration',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-registration.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-clinics',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-clinics.css',
			array( 'doctor-ak-portal-registration' ),
			Assets::version( 'assets/css/doctor-ak-clinics.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-video-pricing-tab',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-video-pricing-tab.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-video-pricing-tab.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-video-pricing-tab',
			'dakVideoPricingTab',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * AJAX handler: saves the logged-in doctor's own video pricing.
	 *
	 * @return void
	 */
	public function handle_save_price() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'video-consultation' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Video Consultation page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$this->process_save( get_current_user_id() );
	}

	/**
	 * AJAX handler: admin sets/overrides any doctor's video pricing.
	 *
	 * @return void
	 */
	public function handle_admin_save_price() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( wp_unslash( $_POST['doctor_id'] ) ) : 0;
		$doctor    = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid doctor.', 'doctor-ak-portal' ) ) );
		}

		$this->process_save( $doctor_id );
	}

	/**
	 * Shared save logic for both the doctor-facing and admin-facing endpoints.
	 *
	 * @param int $doctor_id Doctor whose video pricing is being saved.
	 * @return void
	 */
	private function process_save( $doctor_id ) {
		$fields = Video_Pricing::sanitize_fields_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Video_Pricing::sanitize_fields_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			wp_send_json_error( array( 'errors' => array( 'price' => $fields->get_error_message() ) ) );
		}

		Video_Pricing::save_for_doctor( $doctor_id, $fields );

		wp_send_json_success( array( 'message' => __( 'Video consultation pricing saved.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Checks whether the current request is for the doctor dashboard's
	 * Video Consultation tab.
	 *
	 * @return bool
	 */
	private function is_video_pricing_tab_page() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) || ! has_shortcode( $post->post_content, Doctor_Dashboard::SHORTCODE_TAG ) ) {
			return false;
		}

		return isset( $_GET['tab'] ) && 'video-consultation' === $_GET['tab']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
	}
}
