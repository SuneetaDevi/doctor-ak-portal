<?php
/**
 * Resolves the front-end URL of the page containing a given shortcode.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Finder
 *
 * Since pages are created manually by the site owner (per the plugin's
 * design brief) rather than registered by the plugin itself, redirects
 * (post-login, post-reset, "forgot password?" links, etc.) need to locate
 * whichever page currently holds a given shortcode instead of relying on
 * a hardcoded slug.
 */
class Page_Finder {

	/**
	 * Transient key prefix used to cache lookups.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'doctor_ak_page_url_';

	/**
	 * Shortcode tags the plugin knows how to look up. Used to bulk-clear
	 * the cache whenever any page is saved.
	 *
	 * @var array
	 */
	const KNOWN_SHORTCODES = array(
		'doctor_register',
		'doctor_login',
		'doctor_forgot_password',
		'doctor_dashboard',
		'patient_dashboard',
		'doctor_profile',
		'doctors_directory',
		'doctor_profile_view',
		'book_appointment',
		'services_directory',
		'service_profile_view',
	);

	/**
	 * Finds the permalink of the first published page/post containing the
	 * given shortcode tag.
	 *
	 * @param string $shortcode_tag Shortcode tag without brackets, e.g. 'doctor_login'.
	 * @return string Permalink, or an empty string if no matching page exists.
	 */
	public static function url_for_shortcode( $shortcode_tag ) {
		$cache_key = self::CACHE_PREFIX . $shortcode_tag;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%[' . $wpdb->esc_like( $shortcode_tag ) . '%'
			)
		);

		$url = $post_id ? get_permalink( $post_id ) : '';

		set_transient( $cache_key, $url, HOUR_IN_SECONDS );

		return $url;
	}

	/**
	 * Clears cached lookups whenever any post/page is saved, so moving a
	 * shortcode to a different page is picked up without waiting an hour.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		foreach ( self::KNOWN_SHORTCODES as $shortcode_tag ) {
			delete_transient( self::CACHE_PREFIX . $shortcode_tag );
		}
	}
}
