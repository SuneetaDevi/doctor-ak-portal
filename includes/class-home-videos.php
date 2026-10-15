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
	 * Upper bound on how many videos can be saved, so the option can't grow
	 * unbounded. The home page shows the first HOME_LIMIT; the Videos page
	 * shows them all.
	 *
	 * @var int
	 */
	const MAX_VIDEOS = 60;

	/**
	 * How many videos the home page's video section shows before "View all".
	 *
	 * @var int
	 */
	const HOME_LIMIT = 8;

	/**
	 * Flag: the clips that used to be hard-coded on the home page have been
	 * copied into the managed list (once), so staff can now edit, reorder or
	 * remove them like any other video.
	 *
	 * @var string
	 */
	const SEEDED_OPTION = 'doctor_ak_home_videos_seeded';

	/**
	 * The clips bundled with the plugin that the home page used to show
	 * unconditionally — now just the starting content of the managed list.
	 *
	 * @var string[]
	 */
	const BUNDLED_VIDEO_PATHS = array(
		'assets/videos/video-1.mp4',
		'assets/videos/video-2.mp4',
		'assets/videos/video-3.mp4',
		'assets/videos/video-4.mp4',
		'assets/videos/video-5.mp4',
		'assets/videos/video-6.mp4',
	);

	/**
	 * Every saved video row, ready for display, in the order staff set.
	 *
	 * @return array List of { title, video_url, poster_url }.
	 */
	public static function get_all() {
		self::maybe_seed();

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
	 * The first few videos, for the home page.
	 *
	 * @return array
	 */
	public static function for_home() {
		return array_slice( self::get_all(), 0, self::HOME_LIMIT );
	}

	/**
	 * One-time: copies the bundled clips (previously hard-coded on the home
	 * page) in front of any videos already saved, so nothing disappears from
	 * the site when the home page switches to showing only the managed list.
	 *
	 * @return void
	 */
	private static function maybe_seed() {
		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}

		$existing = get_option( self::OPTION_NAME, array() );
		$existing = is_array( $existing ) ? $existing : array();
		$seeded   = array();

		foreach ( self::BUNDLED_VIDEO_PATHS as $path ) {
			if ( file_exists( DOCTOR_AK_PORTAL_PATH . $path ) ) {
				$seeded[] = array(
					'title'      => '',
					'video_url'  => DOCTOR_AK_PORTAL_URL . $path,
					'poster_url' => '',
				);
			}
		}

		$urls = wp_list_pluck( $seeded, 'video_url' );

		foreach ( $existing as $row ) {
			if ( ! empty( $row['video_url'] ) && ! in_array( $row['video_url'], $urls, true ) ) {
				$seeded[] = $row;
			}
		}

		update_option( self::OPTION_NAME, array_slice( $seeded, 0, self::MAX_VIDEOS ) );
		update_option( self::SEEDED_OPTION, '1' );
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
