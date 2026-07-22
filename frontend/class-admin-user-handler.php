<?php
/**
 * AJAX handlers backing the [admin_dashboard] Users (Doctors/Patients) table.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Authentication;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Specializations;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_User_Handler
 *
 * Every endpoint here re-checks `manage_options` independently of the
 * dashboard page gate in Admin_Dashboard, since AJAX endpoints are
 * reachable directly regardless of which page rendered the form.
 */
class Admin_User_Handler {

	/**
	 * AJAX handler: creates a new doctor/patient account, or updates an
	 * existing one when `user_id` is present.
	 *
	 * @return void
	 */
	public function handle_save_user() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$errors  = array();

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';

		$specializations = array();
		if ( isset( $_POST['specializations'] ) && is_array( $_POST['specializations'] ) ) {
			foreach ( wp_unslash( $_POST['specializations'] ) as $slug ) {
				$slug = sanitize_key( $slug );

				if ( Specializations::is_valid( $slug ) ) {
					$specializations[] = $slug;
				}
			}
		}

		if ( '' === $first_name ) {
			$errors['first_name'] = __( 'First name is required.', 'doctor-ak-portal' );
		}

		if ( '' === $last_name ) {
			$errors['last_name'] = __( 'Last name is required.', 'doctor-ak-portal' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$errors['email'] = __( 'Please provide a valid email address.', 'doctor-ak-portal' );
		}

		$existing_user = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( $user_id > 0 && ! $existing_user ) {
			wp_send_json_error( array( 'message' => __( 'That account no longer exists.', 'doctor-ak-portal' ) ) );
		}

		// Patients have no specialization field at all — only require/save it
		// for doctors (existing doctor being edited, or a brand-new account
		// whose role was explicitly posted as 'doctor').
		$target_role  = $existing_user
			? ( in_array( Roles::DOCTOR_ROLE, (array) $existing_user->roles, true ) ? Roles::DOCTOR_ROLE : Roles::PATIENT_ROLE )
			: ( isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '' );
		$is_for_doctor = Roles::DOCTOR_ROLE === $target_role;

		if ( $is_for_doctor && empty( $specializations ) ) {
			$errors['specializations'] = __( 'Please select at least one specialization.', 'doctor-ak-portal' );
		}

		if ( $existing_user && strtolower( $email ) !== strtolower( $existing_user->user_email ) && email_exists( $email ) ) {
			$errors['email'] = __( 'An account with that email address already exists.', 'doctor-ak-portal' );
		} elseif ( ! $existing_user && '' !== $email && email_exists( $email ) ) {
			$errors['email'] = __( 'An account with that email address already exists.', 'doctor-ak-portal' );
		}

		if ( ! $existing_user ) {
			$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';

			if ( ! in_array( $role, array( Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE ), true ) ) {
				$errors['role'] = __( 'Please choose whether this account is a Doctor or a Patient.', 'doctor-ak-portal' );
			}

			if ( '' === $password || strlen( $password ) < 8 ) {
				$errors['password'] = __( 'Password must be at least 8 characters long.', 'doctor-ak-portal' );
			}
		} elseif ( '' !== $password && strlen( $password ) < 8 ) {
			$errors['password'] = __( 'Password must be at least 8 characters long.', 'doctor-ak-portal' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		if ( $existing_user ) {
			$update = array(
				'ID'           => $existing_user->ID,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'user_email'   => $email,
			);

			if ( '' !== $password ) {
				$update['user_pass'] = $password;
			}

			$result = wp_update_user( $update );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			$saved_user_id = $existing_user->ID;
		} else {
			$user_login = self::unique_username_from_email( $email );

			$authentication = new Authentication();
			$saved_user_id  = $authentication->register_user(
				array(
					'user_login'   => $user_login,
					'user_email'   => $email,
					'user_pass'    => $password,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'display_name' => trim( $first_name . ' ' . $last_name ),
				),
				$role
			);

			if ( is_wp_error( $saved_user_id ) ) {
				wp_send_json_error( array( 'message' => $saved_user_id->get_error_message() ) );
			}
		}

		update_user_meta( $saved_user_id, 'doctor_ak_specializations', $specializations );

		wp_send_json_success(
			array(
				'message' => $existing_user
					? __( 'Account updated successfully.', 'doctor-ak-portal' )
					: __( 'Account created successfully.', 'doctor-ak-portal' ),
			)
		);
	}

	/**
	 * AJAX handler: permanently deletes a doctor/patient account.
	 *
	 * @return void
	 */
	public function handle_delete_user() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( ! $user || ! array_intersect( array( Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE ), (array) $user->roles ) ) {
			wp_send_json_error( array( 'message' => __( 'That account no longer exists.', 'doctor-ak-portal' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		if ( ! wp_delete_user( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The account could not be deleted.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Account deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: toggles a doctor/patient account between active and
	 * deactivated, blocking or restoring their ability to log in.
	 *
	 * @return void
	 */
	public function handle_toggle_status() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( ! $user || ! array_intersect( array( Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE ), (array) $user->roles ) ) {
			wp_send_json_error( array( 'message' => __( 'That account no longer exists.', 'doctor-ak-portal' ) ) );
		}

		$is_disabled = 'yes' === get_user_meta( $user_id, 'doctor_ak_account_disabled', true );
		update_user_meta( $user_id, 'doctor_ak_account_disabled', $is_disabled ? 'no' : 'yes' );

		wp_send_json_success(
			array(
				'is_disabled' => ! $is_disabled,
				'message'     => $is_disabled
					? __( 'Account reactivated.', 'doctor-ak-portal' )
					: __( 'Account deactivated.', 'doctor-ak-portal' ),
			)
		);
	}

	/**
	 * Derives a unique WordPress username from an email address's local
	 * part, since the admin-add form only collects name/email/password
	 * (no separate username field).
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function unique_username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );

		if ( '' === $base ) {
			$base = 'user';
		}

		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			++$suffix;
			$username = $base . $suffix;
		}

		return $username;
	}
}
