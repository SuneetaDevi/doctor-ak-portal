<?php
/**
 * AJAX handler backing the admin dashboard's "Settings" section — the
 * "Google reviews" card (Place ID + Places API key).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Google_Reviews;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Google_Reviews_Handler
 */
class Google_Reviews_Handler {

	/**
	 * Nonce action shared with the admin dashboard's JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_admin_google_reviews';

	/**
	 * AJAX handler: saves the Place ID + API key, then immediately tries a
	 * live fetch so the admin finds out right away if either is wrong,
	 * rather than silently failing the next time the Home page loads.
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

		$place_id = isset( $_POST['place_id'] ) ? sanitize_text_field( wp_unslash( $_POST['place_id'] ) ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		Google_Reviews::save_settings( $place_id, $api_key );

		if ( '' === $place_id || '' === $api_key ) {
			wp_send_json_success( array( 'message' => __( 'Saved. Add both a Place ID and an API key to start pulling reviews.', 'doctor-ak-portal' ) ) );
		}

		$result = Google_Reviews::refresh_now();

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success( array( 'message' => $result['message'] ) );
	}
}
