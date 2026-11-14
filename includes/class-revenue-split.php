<?php
/**
 * Per-doctor revenue split between the doctor and the clinic/platform —
 * the Revenue Model / Billing module. A salary-based doctor is paid outside
 * the platform, so 100% of what they collect counts as the clinic's own
 * revenue; a commission-based doctor keeps an admin-configured percentage
 * of every charge they generate (OPD or video), with the remainder counted
 * as the clinic's.
 *
 * The split is evaluated against a doctor's CURRENT settings wherever
 * revenue is computed (admin Billing/Dashboard, the doctor's own Earnings
 * tab) rather than snapshotted per appointment at payment time — so
 * changing a doctor's split updates how their past appointments are
 * reported too, instead of only affecting appointments booked afterward.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Revenue_Split
 */
class Revenue_Split {

	const META_PAYMENT_MODEL        = 'doctor_ak_payment_model';
	const META_DOCTOR_SHARE_PERCENT = 'doctor_ak_doctor_share_percent';
	const META_VIDEO_SHARE_PERCENT  = 'doctor_ak_video_share_percent';

	const MODEL_COMMISSION = 'commission';
	const MODEL_SALARY     = 'salary';

	/**
	 * Default doctor share when a commission-based doctor hasn't set one
	 * explicitly yet.
	 *
	 * @var float
	 */
	const DEFAULT_DOCTOR_SHARE_PERCENT = 70.0;

	/**
	 * A doctor's current payment model and split.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array {
	 *     @type string $payment_model          'commission' or 'salary'.
	 *     @type float  $doctor_share_percent   0-100. Always 0 for a salary doctor.
	 *     @type float  $hospital_share_percent 100 - doctor_share_percent.
	 * }
	 */
	public static function get_for_doctor( $doctor_id ) {
		$model = get_user_meta( $doctor_id, self::META_PAYMENT_MODEL, true );
		$model = self::MODEL_SALARY === $model ? self::MODEL_SALARY : self::MODEL_COMMISSION;

		if ( self::MODEL_SALARY === $model ) {
			return array(
				'payment_model'          => $model,
				'doctor_share_percent'   => 0.0,
				'hospital_share_percent' => 100.0,
			);
		}

		$stored  = get_user_meta( $doctor_id, self::META_DOCTOR_SHARE_PERCENT, true );
		$percent = '' === $stored ? self::DEFAULT_DOCTOR_SHARE_PERCENT : (float) $stored;
		$percent = max( 0.0, min( 100.0, $percent ) );

		return array(
			'payment_model'          => $model,
			'doctor_share_percent'   => $percent,
			'hospital_share_percent' => 100.0 - $percent,
		);
	}

	/**
	 * A doctor's default share percentage. Per-clinic overrides are no
	 * longer used — the share is set per service instead (see
	 * service_share_percent() / share_percent_for_appointment()); $clinic_id
	 * is kept only so existing callers keep working.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @param int $clinic_id Unused.
	 * @return float 0-100.
	 */
	public static function effective_doctor_share_percent( $doctor_id, $clinic_id = 0 ) {
		return self::get_for_doctor( $doctor_id )['doctor_share_percent'];
	}

	/**
	 * The doctor's share of one specific service, as set per service on the
	 * Add/Edit Doctor form's Services step (Services' doctor_share_percent
	 * column). A service with no share of its own yet (one created before
	 * per-service shares existed, or from a form that doesn't set it) falls
	 * back to the doctor's default split (see get_for_doctor()).
	 *
	 * A service can also have its own share at specific clinics (Services'
	 * clinic_shares, e.g. OPD 60% at one clinic and 50% at another) — when
	 * $clinic_location_id has one, that wins over the service's own share.
	 *
	 * @param int $doctor_id          Doctor's user ID.
	 * @param int $service_id         Services row ID, or 0 for none.
	 * @param int $clinic_location_id Clinic_Locations ID the service was booked at, or 0 for none.
	 * @return float 0-100.
	 */
	public static function service_share_percent( $doctor_id, $service_id, $clinic_location_id = 0 ) {
		static $cache = array();

		$service_id         = (int) $service_id;
		$clinic_location_id = (int) $clinic_location_id;

		if ( $service_id > 0 ) {
			if ( ! array_key_exists( $service_id, $cache ) ) {
				$service              = Services::find( $service_id );
				$cache[ $service_id ] = $service && (int) $service['doctor_id'] === (int) $doctor_id
					? array(
						'share'         => $service['doctor_share_percent'],
						'clinic_shares' => $service['clinic_shares'],
					)
					: null;
			}

			$entry = $cache[ $service_id ];

			if ( $entry && $clinic_location_id > 0 && isset( $entry['clinic_shares'][ $clinic_location_id ] ) ) {
				return (float) $entry['clinic_shares'][ $clinic_location_id ];
			}

			if ( $entry && null !== $entry['share'] ) {
				return max( 0.0, min( 100.0, (float) $entry['share'] ) );
			}
		}

		return self::get_for_doctor( $doctor_id )['doctor_share_percent'];
	}

	/**
	 * The Clinic_Locations ID behind a doctor's own clinic row (an
	 * appointment's clinic_id), or 0.
	 *
	 * @param int $clinic_id Clinics row ID.
	 * @return int
	 */
	private static function clinic_location_for_clinic( $clinic_id ) {
		static $cache = array();

		$clinic_id = (int) $clinic_id;

		if ( $clinic_id <= 0 ) {
			return 0;
		}

		if ( ! array_key_exists( $clinic_id, $cache ) ) {
			$clinic              = Clinics::find( $clinic_id );
			$cache[ $clinic_id ] = $clinic ? (int) $clinic['clinic_location_id'] : 0;
		}

		return $cache[ $clinic_id ];
	}

	/**
	 * The doctor's share of their video consultations — video isn't a
	 * Services row (its fee lives in Video_Pricing), so its share is kept in
	 * user meta, set from the Services step's Video Consultation row. Falls
	 * back to the doctor's default split while unset.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return float 0-100.
	 */
	public static function video_share_percent( $doctor_id ) {
		$stored = get_user_meta( $doctor_id, self::META_VIDEO_SHARE_PERCENT, true );

		if ( '' === $stored || ! is_numeric( $stored ) ) {
			return self::get_for_doctor( $doctor_id )['doctor_share_percent'];
		}

		return max( 0.0, min( 100.0, (float) $stored ) );
	}

	/**
	 * Saves a doctor's video-consultation share.
	 *
	 * @param int   $doctor_id Doctor's user ID.
	 * @param float $percent   0-100.
	 * @return void
	 */
	public static function save_video_share_percent( $doctor_id, $percent ) {
		update_user_meta( $doctor_id, self::META_VIDEO_SHARE_PERCENT, number_format( max( 0.0, min( 100.0, (float) $percent ) ), 2, '.', '' ) );
	}

	/**
	 * The doctor's share for one appointment: the video share for a video
	 * consultation, otherwise the share of the service that was booked at
	 * the clinic it was booked at.
	 *
	 * @param array $appointment Appointment array with doctor_id, type and service_id.
	 * @return float 0-100.
	 */
	public static function share_percent_for_appointment( array $appointment ) {
		$doctor_id = isset( $appointment['doctor_id'] ) ? (int) $appointment['doctor_id'] : 0;

		if ( isset( $appointment['type'] ) && Appointments::TYPE_VIDEO === $appointment['type'] ) {
			return self::video_share_percent( $doctor_id );
		}

		return self::service_share_percent(
			$doctor_id,
			isset( $appointment['service_id'] ) ? (int) $appointment['service_id'] : 0,
			self::clinic_location_for_clinic( isset( $appointment['clinic_id'] ) ? (int) $appointment['clinic_id'] : 0 )
		);
	}

	/**
	 * split() for one appointment, using its own service's/video share.
	 *
	 * @param array $appointment Appointment array with doctor_id, type, service_id and charge.
	 * @return array { @type float doctor_share, @type float hospital_share }
	 */
	public static function split_for_appointment( array $appointment ) {
		return self::split( (int) $appointment['doctor_id'], $appointment['charge'], self::share_percent_for_appointment( $appointment ) );
	}

	/**
	 * Validates an optional share percentage typed into a form: '' means
	 * "not set" (null), anything else must be a number from 0 to 100.
	 *
	 * @param mixed $raw Raw posted value.
	 * @return string|null|\WP_Error Formatted 'NN.NN', null when blank, or WP_Error.
	 */
	public static function sanitize_share_percent( $raw ) {
		$raw = trim( (string) wp_unslash( $raw ) );

		if ( '' === $raw ) {
			return null;
		}

		if ( ! is_numeric( $raw ) || (float) $raw < 0 || (float) $raw > 100 ) {
			return new \WP_Error( 'doctor_ak_doctor_share_percent_invalid', __( "Doctor's share must be between 0 and 100%.", 'doctor-ak-portal' ) );
		}

		return number_format( (float) $raw, 2, '.', '' );
	}

	/**
	 * Splits a charge into the doctor's and the hospital's shares, using
	 * the doctor's current settings (see class docblock).
	 *
	 * @param int        $doctor_id Doctor's user ID.
	 * @param float      $charge    Amount actually charged/paid.
	 * @param float|null $percent   Doctor's share to use (e.g. a service's own, see service_share_percent()); null for the doctor's default.
	 * @return array { @type float doctor_share, @type float hospital_share } — always sums to $charge.
	 */
	public static function split( $doctor_id, $charge, $percent = null ) {
		$percent = null === $percent ? self::get_for_doctor( $doctor_id )['doctor_share_percent'] : (float) $percent;
		$charge  = (float) $charge;

		$doctor_share = round( $charge * $percent / 100, 2 );

		return array(
			'doctor_share'   => $doctor_share,
			'hospital_share' => round( $charge - $doctor_share, 2 ),
		);
	}

	/**
	 * Validates and sanitizes revenue-split fields from a request.
	 *
	 * @param array $posted Raw request array (e.g. $_POST, already a plain array).
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_fields_from_request( array $posted ) {
		$model = isset( $posted['payment_model'] ) && self::MODEL_SALARY === $posted['payment_model']
			? self::MODEL_SALARY
			: self::MODEL_COMMISSION;

		$percent = isset( $posted['doctor_share_percent'] )
			? (float) wp_unslash( $posted['doctor_share_percent'] )
			: self::DEFAULT_DOCTOR_SHARE_PERCENT;

		if ( self::MODEL_COMMISSION === $model && ( $percent < 0 || $percent > 100 ) ) {
			return new \WP_Error( 'doctor_ak_doctor_share_percent_invalid', __( "Doctor's share must be between 0 and 100%.", 'doctor-ak-portal' ) );
		}

		return array(
			'payment_model'        => $model,
			'doctor_share_percent' => self::MODEL_SALARY === $model ? '0.00' : number_format( $percent, 2, '.', '' ),
		);
	}

	/**
	 * Saves a doctor's payment model and split.
	 *
	 * @param int   $doctor_id Doctor's user ID.
	 * @param array $fields    Sanitized fields from sanitize_fields_from_request().
	 * @return void
	 */
	public static function save_for_doctor( $doctor_id, array $fields ) {
		update_user_meta( $doctor_id, self::META_PAYMENT_MODEL, $fields['payment_model'] );
		update_user_meta( $doctor_id, self::META_DOCTOR_SHARE_PERCENT, $fields['doctor_share_percent'] );
	}
}
