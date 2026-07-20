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
 *
 * Also carries a doctor's two booking-rule settings (surfaced in the same
 * "Video Consultation" tab/modal since there's no other per-doctor settings
 * home yet, but they apply to every appointment type, not just video):
 * an "instant booking" lead-time window that adds a surcharge when a
 * patient books inside it, and a cancellation refund window.
 */
class Video_Pricing {

	const META_PRICE                = 'doctor_ak_video_price';
	const META_DISCOUNT_PERCENT     = 'doctor_ak_video_discount_percent';
	const META_DISCOUNT_ENDS_AT     = 'doctor_ak_video_discount_ends_at';
	const META_INSTANT_LEAD_HOURS   = 'doctor_ak_instant_lead_hours';
	const META_INSTANT_SURCHARGE    = 'doctor_ak_instant_surcharge';
	const META_CANCEL_REFUND_HOURS  = 'doctor_ak_cancel_refund_hours';

	/**
	 * Gets a doctor's raw price + booking-rule settings.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array {
	 *     @type float  $price               Base price.
	 *     @type int    $discount_percent    0-100.
	 *     @type string $discount_ends_at    'Y-m-d H:i', or '' if none set.
	 *     @type float  $instant_lead_hours  Hours of notice required before a booking stops
	 *                                       being "instant"; 0 disables the surcharge entirely.
	 *     @type float  $instant_surcharge   Flat PKR amount added to instant bookings.
	 *     @type float  $cancel_refund_hours Hours-before-appointment cutoff for a refund-eligible
	 *                                       cancellation; 0 means always eligible (until it starts).
	 * }
	 */
	public static function get_for_doctor( $doctor_id ) {
		return array(
			'price'                => (float) get_user_meta( $doctor_id, self::META_PRICE, true ),
			'discount_percent'     => (int) get_user_meta( $doctor_id, self::META_DISCOUNT_PERCENT, true ),
			'discount_ends_at'     => (string) get_user_meta( $doctor_id, self::META_DISCOUNT_ENDS_AT, true ),
			'instant_lead_hours'   => (float) get_user_meta( $doctor_id, self::META_INSTANT_LEAD_HOURS, true ),
			'instant_surcharge'    => (float) get_user_meta( $doctor_id, self::META_INSTANT_SURCHARGE, true ),
			'cancel_refund_hours'  => (float) get_user_meta( $doctor_id, self::META_CANCEL_REFUND_HOURS, true ),
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

		$instant_lead_hours = isset( $posted['instant_lead_hours'] ) ? (float) wp_unslash( $posted['instant_lead_hours'] ) : 0;

		if ( $instant_lead_hours < 0 || $instant_lead_hours > 72 ) {
			return new \WP_Error( 'doctor_ak_instant_lead_hours_invalid', __( 'Instant booking window must be between 0 and 72 hours.', 'doctor-ak-portal' ) );
		}

		$instant_surcharge = isset( $posted['instant_surcharge'] ) ? (float) wp_unslash( $posted['instant_surcharge'] ) : 0;

		if ( $instant_surcharge < 0 ) {
			return new \WP_Error( 'doctor_ak_instant_surcharge_invalid', __( 'Please provide a valid instant booking surcharge.', 'doctor-ak-portal' ) );
		}

		$cancel_refund_hours = isset( $posted['cancel_refund_hours'] ) ? (float) wp_unslash( $posted['cancel_refund_hours'] ) : 0;

		if ( $cancel_refund_hours < 0 || $cancel_refund_hours > 720 ) {
			return new \WP_Error( 'doctor_ak_cancel_refund_hours_invalid', __( 'Cancellation refund window must be between 0 and 720 hours.', 'doctor-ak-portal' ) );
		}

		return array(
			'price'                => number_format( $price, 2, '.', '' ),
			'discount_percent'     => $discount_percent,
			'discount_ends_at'     => $discount_ends_at,
			'instant_lead_hours'   => number_format( $instant_lead_hours, 2, '.', '' ),
			'instant_surcharge'    => number_format( $instant_surcharge, 2, '.', '' ),
			'cancel_refund_hours'  => number_format( $cancel_refund_hours, 2, '.', '' ),
		);
	}

	/**
	 * Saves a doctor's video pricing + booking-rule settings.
	 *
	 * @param int   $doctor_id Doctor's user ID.
	 * @param array $fields    Sanitized fields from sanitize_fields_from_request().
	 * @return void
	 */
	public static function save_for_doctor( $doctor_id, array $fields ) {
		update_user_meta( $doctor_id, self::META_PRICE, $fields['price'] );
		update_user_meta( $doctor_id, self::META_DISCOUNT_PERCENT, $fields['discount_percent'] );
		update_user_meta( $doctor_id, self::META_DISCOUNT_ENDS_AT, $fields['discount_ends_at'] );
		update_user_meta( $doctor_id, self::META_INSTANT_LEAD_HOURS, $fields['instant_lead_hours'] );
		update_user_meta( $doctor_id, self::META_INSTANT_SURCHARGE, $fields['instant_surcharge'] );
		update_user_meta( $doctor_id, self::META_CANCEL_REFUND_HOURS, $fields['cancel_refund_hours'] );
	}

	/**
	 * Whether booking a given doctor at the given date/time, right now,
	 * counts as an "instant" booking — inside the doctor's configured
	 * lead-time window, so the surcharge applies. Always false while the
	 * doctor hasn't configured a window (instant_lead_hours is 0).
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $date      'YYYY-MM-DD'.
	 * @param string $time      'HH:MM'.
	 * @return bool
	 */
	public static function is_instant_booking( $doctor_id, $date, $time ) {
		$lead_hours = (float) get_user_meta( $doctor_id, self::META_INSTANT_LEAD_HOURS, true );

		if ( $lead_hours <= 0 ) {
			return false;
		}

		$appointment_timestamp = strtotime( $date . ' ' . $time );

		if ( false === $appointment_timestamp ) {
			return false;
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against a strtotime() of stored local date/time strings, not doing math that needs UTC.

		return $appointment_timestamp > $now && ( $appointment_timestamp - $now ) < $lead_hours * HOUR_IN_SECONDS;
	}

	/**
	 * A doctor's configured flat instant-booking surcharge (0 if unset).
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return float
	 */
	public static function instant_surcharge_for_doctor( $doctor_id ) {
		return (float) get_user_meta( $doctor_id, self::META_INSTANT_SURCHARGE, true );
	}

	/**
	 * Whether cancelling a given doctor's appointment right now, at the
	 * given date/time, is still within the doctor's refund window. True
	 * whenever the doctor hasn't configured a window (cancel_refund_hours
	 * is 0) — i.e. refund-eligible any time before the appointment starts.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $date      'YYYY-MM-DD'.
	 * @param string $time      'HH:MM'.
	 * @return bool
	 */
	public static function is_cancellation_refund_eligible( $doctor_id, $date, $time ) {
		$refund_hours = (float) get_user_meta( $doctor_id, self::META_CANCEL_REFUND_HOURS, true );

		$appointment_timestamp = strtotime( $date . ' ' . $time );

		if ( false === $appointment_timestamp ) {
			return false;
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against a strtotime() of stored local date/time strings, not doing math that needs UTC.

		return $now <= ( $appointment_timestamp - $refund_hours * HOUR_IN_SECONDS );
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

			$row              = self::effective_price_for_doctor( $doctor_id );
			$row['booking_rules'] = self::get_for_doctor( $doctor_id );

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
