<?php
/**
 * Backs the [patient_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Template_Loader;
use DoctorAKPortal\Includes\Theme_Preference;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Patient_Dashboard
 *
 * Gates access (must be logged in and hold the Patient role), then
 * assembles real, computed data. Appointments and notifications render
 * through action hooks so a future booking module can populate them.
 */
class Patient_Dashboard {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'patient_dashboard';

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader $template_loader Template loader.
	 */
	public function __construct( Template_Loader $template_loader ) {
		$this->template_loader = $template_loader;
	}

	/**
	 * Enqueues dashboard assets only on pages containing [patient_dashboard].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-dashboard.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-dashboard.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-dashboard.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-dashboard.js' ),
			true
		);
	}

	/**
	 * Reads the current 'tab' query var: 'dashboard' (default) or 'settings'.
	 *
	 * @return string
	 */
	private static function requested_tab() {
		if ( ! isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			return 'dashboard';
		}

		$tab = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		return 'settings' === $tab ? 'settings' : 'dashboard';
	}

	/**
	 * Renders the shortcode: an access-denied state, or the full dashboard.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return $this->template_loader->get_template(
				'dashboard/access-denied.php',
				array(
					'reason'    => 'logged_out',
					'login_url' => Page_Finder::url_for_shortcode( 'doctor_login' ),
				)
			);
		}

		$user = wp_get_current_user();

		if ( ! in_array( Roles::PATIENT_ROLE, (array) $user->roles, true ) ) {
			return $this->template_loader->get_template(
				'dashboard/access-denied.php',
				array(
					'reason'        => 'wrong_role',
					'dashboard_url' => Page_Finder::url_for_shortcode( 'doctor_dashboard' ),
				)
			);
		}

		return $this->template_loader->get_template( 'dashboard/patient-dashboard.php', $this->prepare_data( $user ) );
	}

	/**
	 * Gathers the data the dashboard template needs, computed from real
	 * user meta rather than placeholder values.
	 *
	 * @param \WP_User $user Currently logged-in patient.
	 * @return array
	 */
	private function prepare_data( \WP_User $user ) {
		$phone_number       = get_user_meta( $user->ID, 'doctor_ak_phone_number', true );
		$profile_picture_id = (int) get_user_meta( $user->ID, 'doctor_ak_profile_picture_id', true );

		$completion_checks = array(
			'' !== trim( (string) $user->first_name ),
			'' !== trim( (string) $user->last_name ),
			is_email( $user->user_email ),
			'' !== $phone_number,
			$profile_picture_id > 0,
		);

		$profile_completion = (int) round( ( count( array_filter( $completion_checks ) ) / count( $completion_checks ) ) * 100 );
		$active_tab         = self::requested_tab();
		$dashboard_url      = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );

		return array(
			'user'               => $user,
			'profile_completion' => $profile_completion,
			'phone_number'       => $phone_number,
			'profile_url'        => Page_Finder::url_for_shortcode( 'doctor_profile' ),
			'directory_url'      => Page_Finder::url_for_shortcode( 'doctors_directory' ),
			'logout_url'         => wp_logout_url( Page_Finder::url_for_shortcode( 'doctor_login' ) ),
			'theme'              => Theme_Preference::get( $user->ID ),
			'active_tab'         => $active_tab,
			'dashboard_url'      => $dashboard_url,
			'settings_url'       => $dashboard_url ? add_query_arg( 'tab', 'settings', $dashboard_url ) : '',
			'settings_tab_html'  => 'settings' === $active_tab ? $this->template_loader->get_template( 'dashboard/partials/dashboard-settings-tab.php' ) : '',
		);
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * patient dashboard shortcode.
	 *
	 * @return bool
	 */
	private function is_dashboard_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
