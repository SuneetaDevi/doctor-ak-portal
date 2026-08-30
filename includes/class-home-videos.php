<?php
/**
 * Stores the admin-managed list of videos shown in the Home page's video
 * section.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Home_Videos
 *
 * A simple option-backed list (no new table — this is a handful of rows an
 * admin curates by hand, the same scale as Clinic Branding's single logo).
 * Each row is an uploaded video file (via wp_handle_upload(), same pattern
 * as Clinic_Branding_Handler's logo upload) plus an optional poster image
 * and title.
 */
class Home_Videos {

	/**
	 * Option name the video list is stored under.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'doctor_ak_home_videos';

	/**
	 * Upper bound on how many videos can be saved, so the option (and the
	 * home page section) can't grow unbounded.
	 *
	 * @var int
	 */
	const MAX_VIDEOS = 12;

	/**
	 * Every saved video row, ready for display.
	 *
	 * @return array List of { title, video_url, poster_url }.
	 */
	public static function get_all() {
		$rows = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$decoded = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['video_url'] ) ) {
				continue;
			}

			$decoded[] = array(
				'title'      => isset( $row['title'] ) ? (string) $row['title'] : '',
				'video_url'  => (string) $row['video_url'],
				'poster_url' => isset( $row['poster_url'] ) ? (string) $row['poster_url'] : '',
			);
		}

		return $decoded;
	}

	/**
	 * Replaces the saved video list wholesale (the admin editor always
	 * submits its full current row set, same as Locations_Handler does for
	 * the countries list).
	 *
	 * @param array $rows Raw rows from the request: [{ title, video_url, poster_url }, ...].
	 * @return void
	 */
	public static function save( array $rows ) {
		$clean = array();

		foreach ( array_slice( $rows, 0, self::MAX_VIDEOS ) as $row ) {
			$video_url = isset( $row['video_url'] ) ? esc_url_raw( $row['video_url'] ) : '';

			if ( '' === $video_url ) {
				continue;
			}

			$clean[] = array(
				'title'      => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
				'video_url'  => $video_url,
				'poster_url' => isset( $row['poster_url'] ) ? esc_url_raw( $row['poster_url'] ) : '',
			);
		}

		update_option( self::OPTION_NAME, $clean );
	}
}
