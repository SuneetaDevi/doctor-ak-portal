<?php
/**
 * Pulls the clinic's Google reviews (via the Places API) for display on the
 * Home page, alongside the admin-curated Home_Testimonials.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Google_Reviews
 *
 * Google's Place Details endpoint returns at most 5 reviews — its own
 * "most relevant" picks, not configurable — plus the place's overall rating
 * and total review count. Fetched at most once per CACHE_TTL (a real
 * request costs money and the admin's key may have a tight quota), cached in
 * a transient, and re-fetched lazily the next time get_reviews()/
 * overall_rating() is called after it expires — no cron event needed for
 * something this low-traffic.
 */
class Google_Reviews {

	/**
	 * Option name the Place ID is stored under.
	 *
	 * @var string
	 */
	const OPTION_PLACE_ID = 'doctor_ak_google_place_id';

	/**
	 * Option name the Places API key is stored under.
	 *
	 * @var string
	 */
	const OPTION_API_KEY = 'doctor_ak_google_api_key';

	/**
	 * Transient key the fetched result is cached under.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'doctor_ak_google_reviews_cache';

	/**
	 * How long a fetch is trusted before the next call re-fetches.
	 *
	 * @var int
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * @return string Saved Place ID, or '' if not configured.
	 */
	public static function get_place_id() {
		return (string) get_option( self::OPTION_PLACE_ID, '' );
	}

	/**
	 * @return string Saved Places API key, or '' if not configured.
	 */
	public static function get_api_key() {
		return (string) get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Saves the Place ID + API key and clears any cached fetch so the next
	 * read picks up the new credentials immediately.
	 *
	 * @param string $place_id Google Place ID.
	 * @param string $api_key  Google Places API key.
	 * @return void
	 */
	public static function save_settings( $place_id, $api_key ) {
		update_option( self::OPTION_PLACE_ID, sanitize_text_field( $place_id ) );
		update_option( self::OPTION_API_KEY, sanitize_text_field( $api_key ) );
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Up to 5 reviews, in the shape the Home page's testimonial band expects
	 * (matching Home_Testimonials::get_all()'s rows, plus a 'rating').
	 *
	 * @return array List of { quote, name, attribution, rating }, empty if not configured or the fetch failed.
	 */
	public static function get_reviews() {
		$result = self::cached_result();

		return $result ? $result['reviews'] : array();
	}

	/**
	 * The place's overall Google rating, for a "4.8 on Google (120 reviews)"
	 * style callout.
	 *
	 * @return array{rating: float, total: int} Zeroed out if not configured or the fetch failed.
	 */
	public static function overall_rating() {
		$result = self::cached_result();

		return array(
			'rating' => $result ? $result['rating'] : 0.0,
			'total'  => $result ? $result['total'] : 0,
		);
	}

	/**
	 * Forces an immediate re-fetch, bypassing the cache — used by the admin
	 * Settings page's "Save & Refresh" so a newly-entered key/Place ID gets
	 * validated right away instead of waiting up to a day.
	 *
	 * @return array{success: bool, message: string} Whether the fetch worked, and an error message if not.
	 */
	public static function refresh_now() {
		delete_transient( self::CACHE_KEY );

		$fetched = self::fetch();

		if ( is_wp_error( $fetched ) ) {
			return array(
				'success' => false,
				'message' => $fetched->get_error_message(),
			);
		}

		set_transient( self::CACHE_KEY, $fetched, self::CACHE_TTL );

		return array(
			'success' => true,
			/* translators: %d: number of reviews fetched. */
			'message' => sprintf( _n( 'Connected — %d review fetched.', 'Connected — %d reviews fetched.', count( $fetched['reviews'] ), 'doctor-ak-portal' ), count( $fetched['reviews'] ) ),
		);
	}

	/**
	 * Reads the cached fetch, re-fetching first if it's missing/expired.
	 * A failed re-fetch caches nothing, so the next call tries again rather
	 * than pinning a failure for a whole day.
	 *
	 * @return array{rating: float, total: int, reviews: array}|null Null if not configured or every fetch attempt has failed.
	 */
	private static function cached_result() {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$fetched = self::fetch();

		if ( is_wp_error( $fetched ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $fetched, self::CACHE_TTL );

		return $fetched;
	}

	/**
	 * Calls the Places API's Place Details endpoint.
	 *
	 * @return array{rating: float, total: int, reviews: array}|\WP_Error
	 */
	private static function fetch() {
		$place_id = self::get_place_id();
		$api_key  = self::get_api_key();

		if ( '' === $place_id || '' === $api_key ) {
			return new \WP_Error( 'doctor_ak_google_reviews_not_configured', __( 'Add a Place ID and API key first.', 'doctor-ak-portal' ) );
		}

		$url = add_query_arg(
			array(
				'place_id' => rawurlencode( $place_id ),
				'fields'   => 'rating,user_ratings_total,reviews',
				'key'      => rawurlencode( $api_key ),
			),
			'https://maps.googleapis.com/maps/api/place/details/json'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || 'OK' !== ( $body['status'] ?? '' ) ) {
			$status = is_array( $body ) && isset( $body['status'] ) ? $body['status'] : 'unknown error';

			/* translators: %s: Google API error status, e.g. "REQUEST_DENIED". */
			return new \WP_Error( 'doctor_ak_google_reviews_api_error', sprintf( __( 'Google API error: %s', 'doctor-ak-portal' ), $status ) );
		}

		$result = isset( $body['result'] ) ? $body['result'] : array();

		$reviews = array();

		foreach ( ( $result['reviews'] ?? array() ) as $review ) {
			if ( empty( $review['text'] ) || empty( $review['author_name'] ) ) {
				continue;
			}

			$reviews[] = array(
				'quote'       => sanitize_textarea_field( $review['text'] ),
				'name'        => sanitize_text_field( $review['author_name'] ),
				'attribution' => __( 'Google review', 'doctor-ak-portal' ),
				'rating'      => isset( $review['rating'] ) ? (int) $review['rating'] : 0,
			);
		}

		return array(
			'rating'  => isset( $result['rating'] ) ? (float) $result['rating'] : 0.0,
			'total'   => isset( $result['user_ratings_total'] ) ? (int) $result['user_ratings_total'] : 0,
			'reviews' => array_slice( $reviews, 0, 5 ),
		);
	}
}
