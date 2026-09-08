<?php
/**
 * Growing pool of doctor "search keyword" tags — the procedures/conditions
 * a doctor treats, free-typed against their profile so patients can
 * eventually search doctors by them.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Keywords
 *
 * Unlike Specializations (a fixed, hardcoded list), this pool grows on its
 * own: the moment any doctor's profile is saved with a keyword nobody has
 * used before, remember() adds it here, so it shows up as a suggestion the
 * next time anyone (a different doctor, or an admin editing one) types into
 * the same field — see Profile_Handler and Admin_User_Handler, the two
 * places `doctor_ak_keywords` is saved.
 */
class Doctor_Keywords {

	/**
	 * wp_option name the whole known keyword pool is stored under.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'doctor_ak_procedure_keywords';

	/**
	 * Every keyword any doctor has ever used, alphabetically — the
	 * suggestion list a keyword <select multiple> is populated from.
	 *
	 * @return string[]
	 */
	public static function get_all() {
		$keywords = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $keywords ) ) {
			return array();
		}

		$keywords = array_values( array_filter( array_map( 'strval', $keywords ) ) );

		sort( $keywords, SORT_STRING | SORT_FLAG_CASE );

		return $keywords;
	}

	/**
	 * Adds any of the given keywords the pool doesn't already have (matched
	 * case-insensitively, keeping whichever casing was seen first) — safe to
	 * call with a doctor's full current keyword list on every save, since
	 * only genuinely new ones actually change the stored option.
	 *
	 * @param string[] $keywords Freshly saved keywords for one doctor.
	 * @return void
	 */
	public static function remember( array $keywords ) {
		if ( empty( $keywords ) ) {
			return;
		}

		$existing = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$merged  = array();
		$changed = false;

		foreach ( array_merge( $existing, $keywords ) as $keyword ) {
			$keyword = trim( (string) $keyword );

			if ( '' === $keyword ) {
				continue;
			}

			$lower = mb_strtolower( $keyword );

			if ( ! isset( $merged[ $lower ] ) ) {
				$merged[ $lower ] = $keyword;

				if ( ! in_array( $keyword, $existing, true ) ) {
					$changed = true;
				}
			}
		}

		if ( $changed ) {
			update_option( self::OPTION_NAME, array_values( $merged ), false );
		}
	}
}
