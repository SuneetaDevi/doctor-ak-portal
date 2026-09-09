<?php
/**
 * Fixed list of Service categories — a deliberately small, hardcoded set
 * (unlike Specializations, this one isn't expected to grow), used to
 * classify Services rows for the public site's Services mega-menu.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Categories
 *
 * Mirrors Specializations' own shape (get_all()/is_valid()) so
 * Services::sanitize_fields_from_request()/decode_row() can validate/label
 * a service's category the same way they already validate/label a doctor's
 * specialization — just against this fixed 6-entry list instead. The last
 * entry ("Miscellaneous/Other Services") is also this list's catch-all
 * fallback bucket for the header mega-menu (see
 * Services::grouped_by_category_for_public_directory()) — keep it last if
 * the list is ever reordered.
 */
class Service_Categories {

	/**
	 * Returns the full category list as slug => label.
	 *
	 * @return array
	 */
	public static function get_all() {
		$labels = array(
			__( 'Surgeries and Procedures', 'doctor-ak-portal' ),
			__( 'Pharmacy', 'doctor-ak-portal' ),
			__( 'Labs', 'doctor-ak-portal' ),
			__( 'Nursing', 'doctor-ak-portal' ),
			__( 'Second Opinion Services', 'doctor-ak-portal' ),
			__( 'Miscellaneous/Other Services', 'doctor-ak-portal' ),
		);

		$categories = array();

		foreach ( $labels as $label ) {
			$categories[ sanitize_title( $label ) ] = $label;
		}

		return $categories;
	}

	/**
	 * Checks whether the given slug is a recognised category.
	 *
	 * @param string $slug Category slug.
	 * @return bool
	 */
	public static function is_valid( $slug ) {
		return array_key_exists( $slug, self::get_all() );
	}

	/**
	 * The catch-all category slug a service with no (or an unrecognised —
	 * e.g. left over from before this fixed list existed) category falls
	 * into for grouping purposes. Always the last entry in get_all().
	 *
	 * @return string
	 */
	public static function fallback_slug() {
		$slugs = array_keys( self::get_all() );

		return end( $slugs );
	}
}
