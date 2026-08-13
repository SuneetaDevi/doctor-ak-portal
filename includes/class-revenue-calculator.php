<?php
/**
 * Pure revenue-split calculation for a single appointment — no database
 * writes, no side effects. Revenue_Ledger calls this to decide what to
 * persist; anything that wants a live preview of a split (without posting
 * anything) can call it directly too.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Revenue_Calculator
 *
 * Two accounting streams, deliberately calculated differently (see the
 * plugin's revenue-ledger design notes):
 *
 * Video consultation (`Appointments::TYPE_VIDEO`, always `clinic_id = 0`):
 *   platform_fee  = gross * fee_percent/100 + fee_flat
 *   net_after_fee = gross - platform_fee
 *   doctor_amount = net_after_fee * doctor_share_percent/100
 *   clinic_amount = net_after_fee - doctor_amount
 *
 * Physical clinic visit (`Appointments::TYPE_CLINIC`):
 *   platform_fee  = 0 (a clinic visit is typically cash-collected in person;
 *                   see PLATFORM_FEE_* options' docblock if that assumption
 *                   changes)
 *   doctor_amount = gross * doctor_share_percent/100
 *   clinic_amount = gross - doctor_amount
 *
 * Either way, `doctor_amount + clinic_amount + platform_fee = gross`, and
 * the doctor's balance impact (`net_amount`/`direction`) mirrors the
 * existing Appointments::net_dues_for_doctor() rule: the party that
 * actually collected the payment (per `payment_mode`) owes the other side
 * its share.
 */
class Revenue_Calculator {

	/**
	 * Option name for the video platform/gateway fee's percentage component.
	 *
	 * @var string
	 */
	const OPTION_FEE_PERCENT = 'dak_video_platform_fee_percent';

	/**
	 * Option name for the video platform/gateway fee's flat-amount component.
	 *
	 * @var string
	 */
	const OPTION_FEE_FLAT = 'dak_video_platform_fee_flat';

	/**
	 * Direction of a ledger entry relative to the doctor's own balance.
	 *
	 * @var string
	 */
	const DIRECTION_CREDIT = 'credit';
	const DIRECTION_DEBIT  = 'debit';

	const TRANSACTION_VIDEO_CONSULTATION  = 'video_consultation';
	const TRANSACTION_CLINIC_CONSULTATION = 'clinic_consultation';

	/**
	 * The video platform/gateway fee, as configured by an admin — both
	 * components default to 0 (no fee deducted) until explicitly set, per
	 * the project's "never silently invent a financial figure" rule.
	 *
	 * @return array { @type float percent, @type float flat }
	 */
	public static function video_platform_fee_settings() {
		return array(
			'percent' => (float) get_option( self::OPTION_FEE_PERCENT, 0 ),
			'flat'    => (float) get_option( self::OPTION_FEE_FLAT, 0 ),
		);
	}

	/**
	 * Saves the video platform/gateway fee settings.
	 *
	 * @param float $percent Percentage of the gross charge, 0-100.
	 * @param float $flat    Flat PKR amount per transaction, >= 0.
	 * @return void
	 */
	public static function save_video_platform_fee_settings( $percent, $flat ) {
		update_option( self::OPTION_FEE_PERCENT, number_format( max( 0.0, min( 100.0, (float) $percent ) ), 2, '.', '' ) );
		update_option( self::OPTION_FEE_FLAT, number_format( max( 0.0, (float) $flat ), 2, '.', '' ) );
	}

	/**
	 * Calculates the full revenue breakdown for a single appointment,
	 * without persisting anything.
	 *
	 * @param array $appointment Decoded appointment array (see Appointments::get()/find()) — must include id, doctor_id, clinic_id, service_id, type, charge, payment_mode, date.
	 * @return array {
	 *     @type string transaction_type  'video_consultation' or 'clinic_consultation'.
	 *     @type string direction        'credit' or 'debit'.
	 *     @type float  gross_amount
	 *     @type float  platform_fee
	 *     @type float  share_percent
	 *     @type float  doctor_amount
	 *     @type float  clinic_amount
	 *     @type float  net_amount      The signed amount actually posted to the doctor's balance.
	 *     @type int    clinic_id       Always 0 for video.
	 * }
	 */
	public static function calculate_for_appointment( array $appointment ) {
		$doctor_id = (int) $appointment['doctor_id'];
		$is_video  = Appointments::TYPE_VIDEO === $appointment['type'];
		$clinic_id = $is_video ? 0 : (int) $appointment['clinic_id'];
		$gross     = round( (float) $appointment['charge'], 2 );

		$platform_fee = 0.0;

		if ( $is_video ) {
			$fee_settings = self::video_platform_fee_settings();
			$platform_fee = round( ( $gross * $fee_settings['percent'] / 100 ) + $fee_settings['flat'], 2 );
			$platform_fee = min( $platform_fee, $gross ); // Never let a fee exceed the charge itself.
		}

		$net_after_fee = round( $gross - $platform_fee, 2 );
		$share_percent = Revenue_Split::effective_doctor_share_percent( $doctor_id, $clinic_id );
		$doctor_amount = round( $net_after_fee * $share_percent / 100, 2 );
		$clinic_amount = round( $net_after_fee - $doctor_amount, 2 );

		$collected_online = Appointments::PAYMENT_MODE_ONLINE === $appointment['payment_mode'];

		return array(
			'transaction_type' => $is_video ? self::TRANSACTION_VIDEO_CONSULTATION : self::TRANSACTION_CLINIC_CONSULTATION,
			'direction'        => $collected_online ? self::DIRECTION_CREDIT : self::DIRECTION_DEBIT,
			'gross_amount'     => $gross,
			'platform_fee'     => $platform_fee,
			'share_percent'    => $share_percent,
			'doctor_amount'    => $doctor_amount,
			'clinic_amount'    => $clinic_amount,
			// Platform collected the payment (online) => it owes the doctor their share (credit).
			// Doctor/clinic collected it in person (manual/cash) => the doctor owes the clinic its share (debit).
			'net_amount'       => $collected_online ? $doctor_amount : ( 0 - $clinic_amount ),
			'clinic_id'        => $clinic_id,
		);
	}
}
