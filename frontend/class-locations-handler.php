<?php
/**
 * AJAX handler backing the admin dashboard's "Locations" section (the
 * front-end equivalent of wp-admin's Settings → Locations page, for admins
 * who manage the site entirely from the [admin_dashboard] shortcode).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Locations;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Locations_Handler {

	/**
	 * Nonce action shared with the admin dashboard's JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_admin_locations';

	/**
	 * AJAX handler: saves the Country -> City -> Area list.
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

		$names  = isset( $_POST['name'] ) && is_array( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field in sanitize_from_request().
		$cities = isset( $_POST['cities'] ) && is_array( $_POST['cities'] ) ? wp_unslash( $_POST['cities'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field in sanitize_from_request().

		update_option( Locations::OPTION_KEY, Locations::sanitize_from_request( $names, $cities ) );

		wp_send_json_success( array( 'message' => __( 'Locations saved.', 'doctor-ak-portal' ) ) );
	}
}
