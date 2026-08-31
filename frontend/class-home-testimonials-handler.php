<?php
/**
 * AJAX handler backing the admin dashboard's "Settings" section — the
 * "Home page testimonials" card (patient quotes shown on the [dak_home]
 * page).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Home_Testimonials;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Home_Testimonials_Handler
 */
class Home_Testimonials_Handler {

	/**
	 * Nonce action shared with the admin dashboard's JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_admin_home_testimonials';

	/**
	 * AJAX handler: replaces the saved testimonial row set with whatever the
	 * editor currently shows.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'doctor-ak-portal' ) ), 403 );
		}

		$rows = array();

		if ( isset( $_POST['rows'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['rows'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash() applied, decoded value is sanitized field-by-field in Home_Testimonials::save().

			if ( is_array( $decoded ) ) {
				$rows = $decoded;
			}
		}

		Home_Testimonials::save( $rows );

		wp_send_json_success( array( 'message' => __( 'Home page testimonials saved.', 'doctor-ak-portal' ) ) );
	}
}
