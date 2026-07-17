<?php
/**
 * Handles the forgot-password request and reset-password submission.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Forgot_Password_Handler
 *
 * Backs the single [doctor_forgot_password] shortcode, which renders one of
 * two states depending on the URL: the "request a reset link" form, or (when
 * `?key=` and `?login=` are present) the "choose a new password" form. Both
 * states are handled here using only native WordPress primitives —
 * get_password_reset_key(), check_password_reset_key() and wp_set_password()
 * — none of which require wp-login.php to be loaded.
 */
class Forgot_Password_Handler {

	/**
	 * Nonce action shared by both AJAX endpoints in this handler.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_forgot_password';

	/**
	 * Enqueues assets only on pages containing [doctor_forgot_password].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_forgot_password_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-forgot-password',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-forgot-password.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-forgot-password.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-forgot-password',
			'dakForgotPassword',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * AJAX handler: emails a password reset link if the account exists.
	 *
	 * Always returns the same success message regardless of whether an
	 * account was found, to avoid revealing which usernames/emails are
	 * registered.
	 *
	 * @return void
	 */
	public function handle_request_reset() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$login_or_email = isset( $_POST['login_or_email'] ) ? sanitize_text_field( wp_unslash( $_POST['login_or_email'] ) ) : '';

		$generic_message = __( 'If an account exists with that username or email address, a password reset link has been sent.', 'doctor-ak-portal' );

		if ( '' === $login_or_email ) {
			wp_send_json_success( array( 'message' => $generic_message ) );
		}

		$user = is_email( $login_or_email )
			? get_user_by( 'email', $login_or_email )
			: get_user_by( 'login', $login_or_email );

		if ( $user instanceof \WP_User ) {
			$this->send_reset_email( $user );
		}

		wp_send_json_success( array( 'message' => $generic_message ) );
	}

	/**
	 * AJAX handler: validates the reset key and sets the new password.
	 *
	 * @return void
	 */
	public function handle_reset_password() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$login = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';

		$user = check_password_reset_key( $key, $login );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => __( 'This password reset link is invalid or has expired. Please request a new one.', 'doctor-ak-portal' ) ) );
		}

		$password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
		$confirm  = isset( $_POST['confirm_password'] ) ? (string) $_POST['confirm_password'] : '';

		$errors = array();

		if ( strlen( $password ) < 8 ) {
			$errors['password'] = __( 'Password must be at least 8 characters long.', 'doctor-ak-portal' );
		} elseif ( $password !== $confirm ) {
			$errors['confirm_password'] = __( 'Passwords do not match.', 'doctor-ak-portal' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		wp_set_password( $password, $user->ID );
		update_user_meta( $user->ID, 'default_password_nag', false );

		/**
		 * Fires after a user resets their password via the forgot-password flow.
		 *
		 * @param \WP_User $user     The user whose password was reset.
		 * @param string   $password The new plain text password.
		 */
		do_action( 'password_reset', $user, $password );

		wp_password_change_notification( $user );

		wp_send_json_success(
			array(
				'message'  => __( 'Your password has been updated successfully. You can now log in.', 'doctor-ak-portal' ),
				'redirect' => Page_Finder::url_for_shortcode( 'doctor_login' ),
			)
		);
	}

	/**
	 * Generates a reset key and emails the reset link to the user.
	 *
	 * @param \WP_User $user User requesting the reset.
	 * @return void
	 */
	private function send_reset_email( \WP_User $user ) {
		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			return;
		}

		$reset_page_url = Page_Finder::url_for_shortcode( 'doctor_forgot_password' );

		if ( '' === $reset_page_url ) {
			return;
		}

		$reset_url = add_query_arg(
			array(
				'key'   => $key,
				'login' => rawurlencode( $user->user_login ),
			),
			$reset_page_url
		);

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] Password Reset Request', 'doctor-ak-portal' ), $site_name );

		$message  = sprintf(
			/* translators: %s: user's display name. */
			__( 'Hi %s,', 'doctor-ak-portal' ),
			$user->display_name
		) . "\n\n";
		$message .= __( 'We received a request to reset your password. Click the link below to choose a new password:', 'doctor-ak-portal' ) . "\n\n";
		$message .= $reset_url . "\n\n";
		$message .= __( 'If you did not request this, you can safely ignore this email — your password will not be changed.', 'doctor-ak-portal' ) . "\n";

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * forgot-password shortcode.
	 *
	 * @return bool
	 */
	private function is_forgot_password_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, 'doctor_forgot_password' );
	}
}
