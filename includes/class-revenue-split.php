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
	 * Splits a charge into the doctor's and the hospital's shares, using
	 * the doctor's current settings (see class docblock).
	 *
	 * @param int   $doctor_id Doctor's user ID.
	 * @param float $charge    Amount actually charged/paid.
	 * @return array { @type float doctor_share, @type float hospital_share } — always sums to $charge.
	 */
	public static function split( $doctor_id, $charge ) {
		$settings = self::get_for_doctor( $doctor_id );
		$charge   = (float) $charge;

		$doctor_share = round( $charge * $settings['doctor_share_percent'] / 100, 2 );

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
