<?php
/**
 * Authentication service built entirely on native WordPress user functions.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Authentication
 *
 * Thin, reusable wrapper around wp_signon(), wp_logout() and
 * wp_insert_user(). Holds no knowledge of forms or templates so it can be
 * called from any shortcode handler added in later phases.
 */
class Authentication {

	/**
	 * Logs a user in using either their username or email address.
	 *
	 * @param string $login    Username or email address.
	 * @param string $password Plain text password.
	 * @param bool   $remember Whether to set a long-lived auth cookie.
	 * @return \WP_User|\WP_Error Signed-in user on success, WP_Error on failure.
	 */
	public function login( $login, $password, $remember = false ) {
		$login = sanitize_text_field( wp_unslash( $login ) );

		if ( is_email( $login ) ) {
			$user_by_email = get_user_by( 'email', $login );

			if ( $user_by_email instanceof \WP_User ) {
				$login = $user_by_email->user_login;
			}
		}

		$credentials = array(
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => (bool) $remember,
		);

		$user = wp_signon( $credentials, is_ssl() );

		if ( $user instanceof \WP_User && 'yes' === get_user_meta( $user->ID, 'doctor_ak_account_disabled', true ) ) {
			wp_logout();
			return new \WP_Error( 'account_disabled', __( 'This account has been deactivated. Please contact the site administrator.', 'doctor-ak-portal' ) );
		}

		if ( $user instanceof \WP_User && in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			$registration_status = get_user_meta( $user->ID, 'doctor_ak_registration_status', true );

			if ( 'pending' === $registration_status ) {
				wp_logout();
				return new \WP_Error( 'account_pending', __( 'Your account is awaiting admin approval. We will email you once it has been approved.', 'doctor-ak-portal' ) );
			}

			if ( 'rejected' === $registration_status ) {
				wp_logout();
				return new \WP_Error( 'account_rejected', __( 'Your registration was not approved. Please contact the site administrator for more information.', 'doctor-ak-portal' ) );
			}
		}

		return $user;
	}

	/**
	 * Logs the current user out and destroys their session.
	 *
	 * @return void
	 */
	public function logout() {
		wp_logout();
	}

	/**
	 * Creates a new WordPress user and assigns them the given role.
	 *
	 * Expects `$userdata` to already contain sanitized values for at least
	 * `user_login`, `user_email` and `user_pass`. Field-specific validation
	 * (required fields, password confirmation, specialization, etc.) is the
	 * responsibility of the calling shortcode handler.
	 *
	 * @param array  $userdata Data compatible with wp_insert_user().
	 * @param string $role     Role slug to assign (e.g. Roles::DOCTOR_ROLE).
	 * @return int|\WP_Error New user ID on success, WP_Error on failure.
	 */
	public function register_user( array $userdata, $role ) {
		$userdata['user_login'] = sanitize_user( $userdata['user_login'] ?? '', true );
		$userdata['user_email'] = sanitize_email( $userdata['user_email'] ?? '' );
		$userdata['role']       = $role;

		if ( username_exists( $userdata['user_login'] ) ) {
			return new \WP_Error( 'username_exists', __( 'That username is already taken.', 'doctor-ak-portal' ) );
		}

		if ( email_exists( $userdata['user_email'] ) ) {
			return new \WP_Error( 'email_exists', __( 'An account with that email address already exists.', 'doctor-ak-portal' ) );
		}

		$user_id = wp_insert_user( $userdata );

		if ( ! is_wp_error( $user_id ) && Roles::PATIENT_ROLE === $role ) {
			/**
			 * Fires after a new patient account is created — via
			 * self-registration, an admin, or a doctor adding a walk-in
			 * patient (every path creates the account through this one
			 * method), so this is the single place to hook a "welcome, here's
			 * how to log in" email rather than duplicating that call at each
			 * of those three separate call sites.
			 *
			 * @param int $user_id New patient's user ID.
			 */
			do_action( 'doctor_ak_patient_added', $user_id );
		}

		return $user_id;
	}
}
