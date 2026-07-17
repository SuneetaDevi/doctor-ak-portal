<?php
/**
 * AJAX handler backing the dashboards' light/dark theme toggle.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Theme_Preference;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Theme_Handler
 *
 * Enqueues the toggle's script on the three dashboards (Admin/Doctor/Patient)
 * and persists a logged-in user's choice via Theme_Preference, so it's
 * remembered next time they log in on any device.
 */
class Theme_Handler {

	/**
	 * Nonce action for the toggle's AJAX call.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_theme';

	/**
	 * Enqueues the toggle script on any of the three dashboard pages.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		wp_enqueue_script(
			'doctor-ak-portal-theme-toggle',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-theme-toggle.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-theme-toggle.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-theme-toggle',
			'dakTheme',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * AJAX handler: flips and saves the logged-in user's theme preference.
	 *
	 * @return void
	 */
	public function handle_toggle_theme() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'doctor-ak-portal' ) ), 401 );
		}

		$theme = Theme_Preference::toggle( get_current_user_id() );

		wp_send_json_success( array( 'theme' => $theme ) );
	}

	/**
	 * Checks whether the current request is for a page containing any of
	 * the three dashboard shortcodes.
	 *
	 * @return bool
	 */
	private function is_dashboard_page() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) ) {
			return false;
		}

		foreach ( array( 'admin_dashboard', 'doctor_dashboard', 'patient_dashboard' ) as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}
}
