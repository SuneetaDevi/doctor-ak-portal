<?php
/**
 * Handles the login form.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Authentication;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Login_Handler
 *
 * Backs the [doctor_login] shortcode. Authenticates via Authentication::login()
 * (a thin wrapper around wp_signon()) and, on success, tells the browser
 * which dashboard page to redirect to based on the user's role.
 */
class Login_Handler {

	/**
	 * Nonce action shared by this handler's AJAX endpoint.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_login';

	/**
	 * Authentication service.
	 *
	 * @var Authentication
	 */
	private $authentication;

	/**
	 * Sets up collaborators.
	 *
	 * @param Authentication $authentication Authentication service.
	 */
	public function __construct( Authentication $authentication ) {
		$this->authentication = $authentication;
	}

	/**
	 * Enqueues login assets only on pages containing [doctor_login].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_login_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-password-toggle',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-password-toggle.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-password-toggle.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-login',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-login.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-login.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-login',
			'dakLogin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * AJAX handler: authenticates the submitted credentials.
	 *
	 * @return void
	 */
	public function handle_login() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You are already logged in.', 'doctor-ak-portal' ) ) );
		}

		$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
		$remember = ! empty( $_POST['remember'] );

		if ( '' === $login || '' === $password ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your username/email and password.', 'doctor-ak-portal' ) ) );
		}

		$user = $this->authentication->login( $login, $password, $remember );

		if ( is_wp_error( $user ) ) {
			// Pending/rejected doctor accounts get their real reason — the
			// visitor already proved they own these exact credentials, so
			// there's no privacy benefit to hiding it behind a generic
			// message the way we do for a wrong username/password.
			if ( in_array( $user->get_error_code(), array( 'account_pending', 'account_rejected' ), true ) ) {
				wp_send_json_error( array( 'message' => $user->get_error_message() ) );
			}

			// Deliberately generic: doesn't reveal whether the account exists.
			wp_send_json_error( array( 'message' => __( 'Invalid username/email or password.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Login successful. Redirecting…', 'doctor-ak-portal' ),
				'redirect' => $this->redirect_url_for_user( $user ),
			)
		);
	}

	/**
	 * Determines which dashboard page to send a freshly logged-in user to.
	 *
	 * @param \WP_User $user Authenticated user.
	 * @return string Permalink, or an empty string if no matching page was found.
	 */
	private function redirect_url_for_user( \WP_User $user ) {
		if ( user_can( $user, 'manage_options' ) ) {
			return Page_Finder::url_for_shortcode( 'admin_dashboard' );
		}

		if ( in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			return Page_Finder::url_for_shortcode( 'doctor_dashboard' );
		}

		if ( in_array( Roles::PATIENT_ROLE, (array) $user->roles, true ) ) {
			return Page_Finder::url_for_shortcode( 'patient_dashboard' );
		}

		return '';
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * login shortcode.
	 *
	 * @return bool
	 */
	private function is_login_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, 'doctor_login' );
	}
}
