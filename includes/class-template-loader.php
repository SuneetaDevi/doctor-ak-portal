<?php
/**
 * Locates and renders plugin templates.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Template_Loader
 *
 * Renders the body-only markup for each shortcode from files in the
 * plugin's templates/ directory. A matching file placed by the active
 * theme in `{theme}/doctor-ak-portal/{template}` takes precedence, so
 * sites can override markup without touching the plugin.
 */
class Template_Loader {

	/**
	 * Renders a template file and returns its output.
	 *
	 * @param string $template_name Relative path within the templates directory, e.g. 'auth/login-form.php'.
	 * @param array  $args          Associative array of variables to expose to the template.
	 * @return string Rendered HTML, or an empty string if the template does not exist yet.
	 */
	public function get_template( $template_name, array $args = array() ) {
		$file = $this->locate_template( $template_name );

		/**
		 * Filters the resolved template file path before it is rendered.
		 *
		 * @param string $file          Resolved absolute file path.
		 * @param string $template_name Requested template name.
		 * @param array  $args          Variables passed to the template.
		 */
		$file = apply_filters( 'doctor_ak_portal_template_path', $file, $template_name, $args );

		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}

		if ( ! empty( $args ) ) {
			extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		ob_start();
		include $file;
		return ob_get_clean();
	}

	/**
	 * Resolves a template name to an absolute file path, preferring a
	 * theme override if one exists.
	 *
	 * @param string $template_name Relative path within the templates directory.
	 * @return string Absolute file path (may not exist).
	 */
	private function locate_template( $template_name ) {
		$theme_override = trailingslashit( get_stylesheet_directory() ) . 'doctor-ak-portal/' . $template_name;

		if ( file_exists( $theme_override ) ) {
			return $theme_override;
		}

		return trailingslashit( DOCTOR_AK_PORTAL_PATH . 'templates' ) . $template_name;
	}
}
