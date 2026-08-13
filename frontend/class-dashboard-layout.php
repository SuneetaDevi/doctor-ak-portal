<?php
/**
 * Serves the three dashboards (Admin/Receptionist, Doctor, Patient) inside a
 * bare page template — no theme header.php/footer.php — so they read as a
 * standalone app rather than a page embedded in the public site.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard_Layout
 */
class Dashboard_Layout {

	/**
	 * Swaps in a header/footer-free template for any page containing the
	 * admin, doctor, or patient dashboard shortcode. wp_head()/wp_footer()
	 * still fire (see templates/dashboard/dashboard-canvas.php), so enqueued
	 * assets, the admin bar, and other plugins keep working — only the
	 * active theme's own header.php/footer.php markup is skipped.
	 *
	 * @param string $template Template path WordPress was about to load.
	 * @return string
	 */
	public function template_include( $template ) {
		if ( ! $this->is_dashboard_page() ) {
			return $template;
		}

		return DOCTOR_AK_PORTAL_PATH . 'templates/dashboard/dashboard-canvas.php';
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
