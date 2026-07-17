<?php
/**
 * Cache-busting helper for enqueued plugin assets.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * `wp_enqueue_style()`/`wp_enqueue_script()` need a version string to bust
 * browser/CDN caches when a file's contents change. Using the plugin's
 * version constant for every asset means every CSS/JS tweak also requires
 * bumping that constant, or edits silently keep serving a stale cached
 * copy of the file to visitors. Using the file's own modification time
 * instead makes the cache key change automatically whenever the file does.
 */
class Assets {

	/**
	 * Returns a cache-busting version string for a plugin asset.
	 *
	 * @param string $relative_path Path relative to the plugin root, e.g. 'assets/css/doctor-ak-auth.css'.
	 * @return string
	 */
	public static function version( $relative_path ) {
		$full_path = DOCTOR_AK_PORTAL_PATH . ltrim( $relative_path, '/' );

		if ( file_exists( $full_path ) ) {
			return (string) filemtime( $full_path );
		}

		return DOCTOR_AK_PORTAL_VERSION;
	}
}
