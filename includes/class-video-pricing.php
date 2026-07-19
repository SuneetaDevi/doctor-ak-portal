<?php
/**
 * Per-doctor fixed video-consultation price, with an optional time-limited
 * percentage discount.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Video_Pricing
 *
 * Unlike Services (a per-doctor list stored in its own table), a doctor has
 * exactly one video-consultation price, so it's stored as plain user meta —
 * the same pattern already used for other single-value doctor settings
 * (e.g. doctor_ak_years_experience). Settable by the doctor themself
 * (Video_Pricing_Handler::handle_save_price()) or overridden per doctor by
 * an administrator (handle_admin_save_price()). Read by the booking page to
 * show a fixed price instead of a service list for video appointments, and
 * by Appointments::create()/update() to charge the (possibly discounted)
 * price.
 */
class Video_Pricing {

	const META_PRICE             = 'doctor_ak_video_price';
	const META_DISCOUNT_PERCENT  = 'doctor_ak_video_discount_percent';
	const META_DISCOUNT_ENDS_AT  = 'doctor_ak_video_discount_ends_at';

	/**
	 * Gets a doctor's raw price settings.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array {
	 *     @type float  $price             Base price.
	 *     @type int    $discount_percent  0-100.
	 *     @type string $discount_ends_at  'Y-m-d H:i', or '' if none set.
	 * }
	 */
	public static function get_for_doctor( $doctor_id ) {
		return array(
			'price'             => (float) get_user_meta( $doctor_id, self::META_PRICE, true ),
			'discount_percent'  => (int) get_user_meta( $doctor_id, self::META_DISCOUNT_PERCENT, true ),
			'discount_ends_at'  => (string) get_user_meta( $doctor_id, self::META_DISCOUNT_ENDS_AT, true ),
		);
	}

	/**
	 * Validates and sanitizes video pricing fields from a request.
	 *
	 * @param array $posted Raw request array (e.g. $_POST, already a plain array).
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_fields_from_request( array $posted ) {
		$price = isset( $posted['price'] ) ? (float) wp_unslash( $posted['price'] ) : 0;

		if ( $price < 0 ) {
			return new \WP_Error( 'doctor_ak_video_price_invalid', __( 'Please provide a valid price.', 'doctor-ak-portal' ) );
		}

		$discount_percent = isset( $posted['discount_percent'] ) ? absint( wp_unslash( $posted['discount_percent'] ) ) : 0;

		if ( $discount_percent > 100 ) {
			return new \WP_Error( 'doctor_ak_video_discount_invalid', __( 'Discount must be between 0 and 100%.', 'doctor-ak-portal' ) );
		}

		$discount_ends_at = isset( $posted['discount_ends_at'] ) ? sanitize_text_field( wp_unslash( $posted['discount_ends_at'] ) ) : '';

		if ( '' !== $discount_ends_at ) {
			$discount_ends_at = str_replace( 'T', ' ', $discount_ends_at );
			$timestamp        = strtotime( $discount_ends_at );

			if ( false === $timestamp ) {
				return new \WP_Error( 'doctor_ak_video_discount_ends_at_invalid', __( 'Please provide a valid discount end date/time.', 'doctor-ak-portal' ) );
			}

			$discount_ends_at = date( 'Y-m-d H:i', $timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- storing the doctor's entered local time as-is, not converting timezones.
		}

		return array(
			'price'             => number_format( $price, 2, '.', '' ),
			'discount_percent'  => $discount_percent,
			'discount_ends_at'  => $discount_ends_at,
		);
	}

	/**
	 * Saves a doctor's video pricing settings.
	 *
	 * @param int   $doctor_id Doctor's user ID.
	 * @param array $fields    Sanitized fields from sanitize_fields_from_request().
	 * @return void
	 */
	public static function save_for_doctor( $doctor_id, array $fields ) {
		update_user_meta( $doctor_id, self::META_PRICE, $fields['price'] );
		update_user_meta( $doctor_id, self::META_DISCOUNT_PERCENT, $fields['discount_percent'] );
		update_user_meta( $doctor_id, self::META_DISCOUNT_ENDS_AT, $fields['discount_ends_at'] );
	}

	/**
	 * Computes a doctor's current effective video-consultation price,
	 * applying the discount only while it's still within its time window.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array {
	 *     @type float  $base_price       Undiscounted price.
	 *     @type int    $discount_percent 0-100.
	 *     @type bool   $discount_active  Whether the discount currently applies.
	 *     @type string $discount_ends_at 'Y-m-d H:i', or '' if none set.
	 *     @type float  $final_price      The price to actually charge.
	 * }
	 */
	public static function effective_price_for_doctor( $doctor_id ) {
		$settings = self::get_for_doctor( $doctor_id );

		$discount_active = $settings['discount_percent'] > 0
			&& '' !== $settings['discount_ends_at']
			&& strtotime( $settings['discount_ends_at'] ) > current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against a stored local-time string, not doing math that needs a real Unix timestamp.

		$final_price = $discount_active
			? round( $settings['price'] * ( 100 - $settings['discount_percent'] ) / 100, 2 )
			: $settings['price'];

		return array(
			'base_price'        => $settings['price'],
			'discount_percent'  => $settings['discount_percent'],
			'discount_active'   => $discount_active,
			'discount_ends_at'  => $settings['discount_ends_at'],
			'final_price'       => $final_price,
		);
	}

	/**
	 * Every doctor's video pricing, for the admin "Video Consultation" table.
	 *
	 * @return array List of { doctor: {id, name, email}, ...effective_price_for_doctor() }.
	 */
	public static function all_flat_for_admin() {
		$rows = array();

		foreach ( Appointments::doctor_options() as $doctor_id => $display_name ) {
			$doctor    = get_userdata( $doctor_id );
			$full_name = $doctor ? trim( $doctor->first_name . ' ' . $doctor->last_name ) : '';

			$row = self::effective_price_for_doctor( $doctor_id );

			$row['doctor'] = array(
				'id'    => $doctor_id,
				'name'  => '' !== $full_name ? $full_name : $display_name,
				'email' => $doctor ? $doctor->user_email : '',
			);

			$rows[] = $row;
		}

		return $rows;
	}
}
