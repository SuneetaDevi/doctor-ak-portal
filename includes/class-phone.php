<?php
/**
 * Country dial code list plus phone number formatting/parsing helpers.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Phone
 *
 * Every patient phone field (registration, profile, admin/doctor add-patient)
 * is a dial-code `<select>` next to a plain number `<input>` — this class
 * owns the dial code list and the glue between that pair and the single
 * `+<dial code><national number>` string stored in `doctor_ak_phone_number`
 * user meta, so the split/format logic lives in one place instead of being
 * copied into every handler.
 */
class Phone {

	/**
	 * Dial code used when a submitted one isn't recognized, and the assumed
	 * country for legacy numbers stored before this feature existed (plain
	 * "03xxxxxxxxx" values with no dial code prefix at all).
	 *
	 * @var string
	 */
	const DEFAULT_DIAL_CODE = '92';

	/**
	 * Dial code => label, ordered with Pakistan first since that's this
	 * clinic's home market, then roughly by how often an expat/international
	 * patient here would need the rest.
	 *
	 * @return array
	 */
	public static function dial_codes() {
		return array(
			'92'  => __( 'Pakistan (+92)', 'doctor-ak-portal' ),
			'971' => __( 'UAE (+971)', 'doctor-ak-portal' ),
			'966' => __( 'Saudi Arabia (+966)', 'doctor-ak-portal' ),
			'974' => __( 'Qatar (+974)', 'doctor-ak-portal' ),
			'965' => __( 'Kuwait (+965)', 'doctor-ak-portal' ),
			'973' => __( 'Bahrain (+973)', 'doctor-ak-portal' ),
			'968' => __( 'Oman (+968)', 'doctor-ak-portal' ),
			'44'  => __( 'United Kingdom (+44)', 'doctor-ak-portal' ),
			'1'   => __( 'US / Canada (+1)', 'doctor-ak-portal' ),
			'91'  => __( 'India (+91)', 'doctor-ak-portal' ),
			'880' => __( 'Bangladesh (+880)', 'doctor-ak-portal' ),
			'94'  => __( 'Sri Lanka (+94)', 'doctor-ak-portal' ),
			'61'  => __( 'Australia (+61)', 'doctor-ak-portal' ),
			'49'  => __( 'Germany (+49)', 'doctor-ak-portal' ),
			'33'  => __( 'France (+33)', 'doctor-ak-portal' ),
			'86'  => __( 'China (+86)', 'doctor-ak-portal' ),
			'90'  => __( 'Turkey (+90)', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Validates and combines a posted dial code + national number into the
	 * stored `+<dial code><number>` string.
	 *
	 * @param string $dial_code Raw posted dial code (digits, may include a leading '+').
	 * @param string $number    Raw posted national number.
	 * @return string|\WP_Error The combined string (e.g. '+923001234567'), or a WP_Error if the number is missing/invalid.
	 */
	public static function sanitize_from_request( $dial_code, $number ) {
		$dial_code = preg_replace( '/\D/', '', (string) $dial_code );
		$number    = preg_replace( '/\D/', '', (string) $number );

		if ( '' === $dial_code || ! isset( self::dial_codes()[ $dial_code ] ) ) {
			$dial_code = self::DEFAULT_DIAL_CODE;
		}

		if ( strlen( $number ) < 6 || strlen( $number ) > 14 ) {
			return new \WP_Error( 'doctor_ak_phone_invalid', __( 'Please provide a valid phone number.', 'doctor-ak-portal' ) );
		}

		return '+' . $dial_code . $number;
	}

	/**
	 * Splits a stored phone value back into its dial code + national number,
	 * for pre-filling an edit form's two fields. Handles legacy values saved
	 * before this feature existed (a plain local number with no dial code,
	 * e.g. "03001234567") by assuming DEFAULT_DIAL_CODE and stripping the
	 * leading trunk zero.
	 *
	 * @param string $stored Stored `doctor_ak_phone_number` meta value.
	 * @return array { @type string dial_code, @type string number }
	 */
	public static function split( $stored ) {
		$digits = preg_replace( '/\D/', '', (string) $stored );

		if ( '' === $digits ) {
			return array(
				'dial_code' => self::DEFAULT_DIAL_CODE,
				'number'    => '',
			);
		}

		// Longest dial code first, so e.g. '966' isn't mistaken for '96'.
		$codes = array_keys( self::dial_codes() );
		usort(
			$codes,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		if ( '+' === substr( trim( (string) $stored ), 0, 1 ) ) {
			foreach ( $codes as $code ) {
				if ( 0 === strpos( $digits, $code ) ) {
					return array(
						'dial_code' => $code,
						'number'    => substr( $digits, strlen( $code ) ),
					);
				}
			}
		}

		return array(
			'dial_code' => self::DEFAULT_DIAL_CODE,
			'number'    => ltrim( $digits, '0' ),
		);
	}

	/**
	 * A stored phone value formatted for display, e.g. '+92 3001234567'.
	 *
	 * @param string $stored Stored `doctor_ak_phone_number` meta value.
	 * @return string Empty string if there's nothing stored.
	 */
	public static function format_display( $stored ) {
		if ( '' === trim( (string) $stored ) ) {
			return '';
		}

		$parts = self::split( $stored );

		return '' !== $parts['number'] ? '+' . $parts['dial_code'] . ' ' . $parts['number'] : '';
	}
}
