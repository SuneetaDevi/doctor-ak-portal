<?php
/**
 * AJAX handler backing the admin dashboard's "Roles & Permissions" section
 * (the front-end equivalent of wp-admin's Settings → Roles & Permissions
 * page, for admins who manage the site entirely from the [admin_dashboard]
 * shortcode).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Role_Permissions;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role_Permissions_Handler {

	/**
	 * Nonce action shared with the admin dashboard's JS.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_admin_role_permissions';

	/**
	 * AJAX handler: saves the per-role tab visibility checkboxes.
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

		$posted = isset( $_POST['permissions'] ) && is_array( $_POST['permissions'] ) ? wp_unslash( $_POST['permissions'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field in sanitize_from_request().

		update_option( Role_Permissions::OPTION_KEY, Role_Permissions::sanitize_from_request( $posted ) );

		wp_send_json_success( array( 'message' => __( 'Permissions saved.', 'doctor-ak-portal' ) ) );
	}
}
