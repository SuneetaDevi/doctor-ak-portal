<?php
/**
 * Per-account light/dark dashboard theme preference.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Theme_Preference
 *
 * Stores which theme ('light' or 'dark') a logged-in user last chose for
 * the plugin's dashboards, so it's read back on every visit/device instead
 * of resetting each session.
 */
class Theme_Preference {

	/**
	 * User meta key the preference is stored under.
	 *
	 * @var string
	 */
	const META_KEY = 'doctor_ak_theme';

	/**
	 * Gets a user's current theme preference.
	 *
	 * @param int $user_id User ID.
	 * @return string 'light' or 'dark'.
	 */
	public static function get( $user_id ) {
		return 'dark' === get_user_meta( $user_id, self::META_KEY, true ) ? 'dark' : 'light';
	}

	/**
	 * Flips a user's theme preference and saves it.
	 *
	 * @param int $user_id User ID.
	 * @return string The new preference ('light' or 'dark').
	 */
	public static function toggle( $user_id ) {
		$new_theme = 'dark' === self::get( $user_id ) ? 'light' : 'dark';
		update_user_meta( $user_id, self::META_KEY, $new_theme );

		return $new_theme;
	}
}
