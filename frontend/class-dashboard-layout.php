<?php
/**
 * Serves the three dashboards (Admin/Receptionist, Doctor, Patient) inside a
 * bare page template — no theme header.php/footer.php — so they read as a
 * standalone app rather than a page embedded in the public site.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard_Layout
 */
class Dashboard_Layout {

	/**
	 * The three dashboard shortcode tags, in the order dashboard_shortcode_on_page()
	 * checks them.
	 *
	 * @var array
	 */
	const DASHBOARD_SHORTCODES = array( 'admin_dashboard', 'doctor_dashboard', 'patient_dashboard' );

	/**
	 * Swaps in a header/footer-free template for any page containing the
	 * admin, doctor, or patient dashboard shortcode. wp_head()/wp_footer()
	 * still fire (see templates/dashboard/dashboard-canvas.php), so enqueued
	 * assets, the admin bar, and other plugins keep working — only the
	 * active theme's own header.php/footer.php markup is skipped.
	 *
	 * Also where a logged-in user who lands on the *wrong* dashboard page
	 * (e.g. a Receptionist opening the Patient dashboard URL directly, or
	 * a bookmarked/typed link) gets sent straight to their own dashboard —
	 * this filter runs early enough (before any theme output) that a real
	 * redirect is still possible, unlike each dashboard controller's own
	 * render(), which by the time it runs is just generating shortcode
	 * content mid-page and can only show an in-page "access denied" state.
	 *
	 * @param string $template Template path WordPress was about to load.
	 * @return string
	 */
	public function template_include( $template ) {
		$shortcode = $this->dashboard_shortcode_on_page();

		if ( '' === $shortcode ) {
			return $template;
		}

		$this->maybe_redirect_to_own_dashboard( $shortcode );

		return DOCTOR_AK_PORTAL_PATH . 'templates/dashboard/dashboard-canvas.php';
	}

	/**
	 * If a logged-in user with a recognized role (Administrator/
	 * Receptionist/Doctor/Patient) is on a dashboard page that isn't
	 * theirs, redirects them to the one that is. Left alone (falls through
	 * to that dashboard's own "access denied" state) when the visitor isn't
	 * logged in at all, or holds none of those roles — there's no "their
	 * own dashboard" to send them to in either case.
	 *
	 * @param string $current_shortcode Shortcode tag on the current page, see dashboard_shortcode_on_page().
	 * @return void
	 */
	private function maybe_redirect_to_own_dashboard( $current_shortcode ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$own_shortcode = self::dashboard_shortcode_for_user( wp_get_current_user() );

		if ( '' === $own_shortcode || $own_shortcode === $current_shortcode ) {
			return;
		}

		$redirect_url = Page_Finder::url_for_shortcode( $own_shortcode );

		if ( '' === $redirect_url ) {
			return;
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Resolves which dashboard shortcode a given user actually belongs on,
	 * by role — Administrators and Receptionists share the admin
	 * dashboard (see Admin_Dashboard::RECEPTIONIST_ALLOWED_SECTIONS),
	 * Doctors and Patients each have their own. Empty string if the user
	 * holds none of the four recognized roles.
	 *
	 * @param \WP_User $user
	 * @return string
	 */
	public static function dashboard_shortcode_for_user( \WP_User $user ) {
		if ( user_can( $user, 'manage_options' ) || in_array( Roles::RECEPTIONIST_ROLE, (array) $user->roles, true ) ) {
			return 'admin_dashboard';
		}

		if ( in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			return 'doctor_dashboard';
		}

		if ( in_array( Roles::PATIENT_ROLE, (array) $user->roles, true ) ) {
			return 'patient_dashboard';
		}

		return '';
	}

	/**
	 * Checks whether the current request is for a page containing any of
	 * the three dashboard shortcodes, returning whichever one it found.
	 *
	 * @return string Shortcode tag, or '' if the page has none of them.
	 */
	private function dashboard_shortcode_on_page() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) ) {
			return '';
		}

		foreach ( self::DASHBOARD_SHORTCODES as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return $shortcode;
			}
		}

		return '';
	}
}
