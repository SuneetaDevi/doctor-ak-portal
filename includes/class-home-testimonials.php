<?php
/**
 * Stores the admin-managed list of patient testimonials shown on the Home
 * page.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Home_Testimonials
 *
 * A simple option-backed list (same pattern as Home_Videos) — a handful of
 * rows an admin curates by hand, no new table. Each row is plain text: a
 * quote, the patient's name, and an optional short attribution (e.g. a
 * clinic name).
 */
class Home_Testimonials {

	/**
	 * Option name the testimonial list is stored under.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'doctor_ak_home_testimonials';

	/**
	 * Upper bound on how many testimonials can be saved, so the option (and
	 * the home page section) can't grow unbounded.
	 *
	 * @var int
	 */
	const MAX_TESTIMONIALS = 12;

	/**
	 * Every saved testimonial row, ready for display.
	 *
	 * @return array List of { quote, name, attribution }.
	 */
	public static function get_all() {
		$rows = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$decoded = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['quote'] ) || empty( $row['name'] ) ) {
				continue;
			}

			$decoded[] = array(
				'quote'       => (string) $row['quote'],
				'name'        => (string) $row['name'],
				'attribution' => isset( $row['attribution'] ) ? (string) $row['attribution'] : '',
			);
		}

		return $decoded;
	}

	/**
	 * Replaces the saved testimonial list wholesale (the admin editor always
	 * submits its full current row set, same as Home_Videos::save() does).
	 *
	 * @param array $rows Raw rows from the request: [{ quote, name, attribution }, ...].
	 * @return void
	 */
	public static function save( array $rows ) {
		$clean = array();

		foreach ( array_slice( $rows, 0, self::MAX_TESTIMONIALS ) as $row ) {
			$quote = isset( $row['quote'] ) ? sanitize_textarea_field( $row['quote'] ) : '';
			$name  = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';

			if ( '' === $quote || '' === $name ) {
				continue;
			}

			$clean[] = array(
				'quote'       => $quote,
				'name'        => $name,
				'attribution' => isset( $row['attribution'] ) ? sanitize_text_field( $row['attribution'] ) : '',
			);
		}

		update_option( self::OPTION_NAME, $clean );
	}
}
