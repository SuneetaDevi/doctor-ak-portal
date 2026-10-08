<?php
/**
 * Display-only formatting shared by the dashboard templates, so every list
 * prints money, dates, durations, missing values and e-mail addresses the
 * same way the approved Appointments page does. Never changes stored values
 * or timezone handling — each helper only formats what it's given.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard_Format
 */
class Dashboard_Format {

	/**
	 * "PKR 2,500" — the Appointments list's currency format.
	 *
	 * @param float|int|string $amount     Amount.
	 * @param string|null      $zero_label Text for a zero amount (e.g. "Free"); null prints "PKR 0".
	 * @return string Plain text (escape on output).
	 */
	public static function money( $amount, $zero_label = null ) {
		$amount = (float) $amount;

		if ( 0.0 === $amount && null !== $zero_label ) {
			return $zero_label;
		}

		$decimals = ( floor( $amount ) !== $amount ) ? 2 : 0;

		return 'PKR ' . number_format_i18n( $amount, $decimals );
	}

	/**
	 * "06 Oct 2026" from a stored 'YYYY-MM-DD' (or any strtotime() input,
	 * e.g. a MySQL datetime). Same date_i18n() call the rest of the plugin
	 * uses, so the scheduling timezone is unchanged.
	 *
	 * @param string $value Stored date / datetime.
	 * @param string $empty Text when there's no usable date (blank means '—').
	 * @return string
	 */
	public static function date( $value, $empty = '—' ) {
		$value = trim( (string) $value );
		$empty = '' === trim( (string) $empty ) ? '—' : $empty; // A blank fallback (an unset stored value passed through) still reads as missing.
		$ts    = '' !== $value ? strtotime( $value ) : false;

		return false !== $ts ? date_i18n( 'd M Y', $ts ) : $empty;
	}

	/**
	 * "06 Oct 2026, 03:30 PM" from a stored datetime.
	 *
	 * @param string $value Stored datetime.
	 * @param string $empty Text when there's no usable date (blank means '—').
	 * @return string
	 */
	public static function datetime( $value, $empty = '—' ) {
		$value = trim( (string) $value );
		$empty = '' === trim( (string) $empty ) ? '—' : $empty; // A blank fallback (an unset stored value passed through) still reads as missing.
		$ts    = '' !== $value ? strtotime( $value ) : false;

		return false !== $ts ? date_i18n( 'd M Y, h:i A', $ts ) : $empty;
	}

	/**
	 * "03:30 PM" from a stored 'HH:MM'.
	 *
	 * @param string $value Stored time.
	 * @return string
	 */
	public static function time( $value ) {
		$ts = '' !== (string) $value ? strtotime( '2000-01-01 ' . $value ) : false;

		return false !== $ts ? date_i18n( 'h:i A', $ts ) : (string) $value;
	}

	/**
	 * "30 min" / "1 h 30 min".
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function duration( $minutes ) {
		$minutes = (int) $minutes;

		if ( $minutes <= 0 ) {
			return '—';
		}

		if ( $minutes < 60 ) {
			/* translators: %d: minutes. */
			return sprintf( __( '%d min', 'doctor-ak-portal' ), $minutes );
		}

		$hours = intdiv( $minutes, 60 );
		$rest  = $minutes % 60;

		return $rest
			/* translators: 1: hours, 2: minutes. */
			? sprintf( __( '%1$d h %2$d min', 'doctor-ak-portal' ), $hours, $rest )
			/* translators: %d: hours. */
			: sprintf( __( '%d h', 'doctor-ak-portal' ), $hours );
	}

	/**
	 * Status-pill class for an appointment's stored booking status — the
	 * Appointments list's colours: completed/cancelled are neutral (red stays
	 * for destructive actions and errors). Unknown statuses are neutral.
	 *
	 * @param string $status Stored appointment status.
	 * @return string
	 */
	public static function status_class( $status ) {
		$classes = array(
			'confirmed'       => 'dak-status-pill-is-confirmed',
			'pending_payment' => 'dak-status-pill-is-pending',
			'paid'            => 'dak-status-pill-is-active',
			'checked_in'      => 'dak-status-pill-is-checked-in',
			'completed'       => 'dak-status-pill-is-neutral',
			'cancelled'       => 'dak-status-pill-is-neutral',
			'rescheduled'     => 'dak-status-pill-is-rescheduled',
		);

		return isset( $classes[ $status ] ) ? $classes[ $status ] : 'dak-status-pill-is-neutral';
	}

	/**
	 * An e-mail address as escaped HTML with line-break opportunities only
	 * after "@" and ".", so a long address wraps at a sensible point instead
	 * of mid-word.
	 *
	 * @param string $email Address.
	 * @return string Escaped HTML.
	 */
	public static function email_html( $email ) {
		return str_replace( array( '@', '.' ), array( '@<wbr>', '.<wbr>' ), esc_html( (string) $email ) );
	}

	/**
	 * "Get directions" link to a clinic on Google Maps — the same link the
	 * public clinic pages use (Clinic_Locations::map_url()). Opens in a new tab.
	 *
	 * @param string $url   Map URL; '' renders nothing.
	 * @param string $place Clinic name, for the screen-reader label.
	 * @return string Escaped HTML.
	 */
	public static function map_link_html( $url, $place = '' ) {
		if ( '' === (string) $url ) {
			return '';
		}

		$label = '' !== (string) $place
			/* translators: %s: clinic name. */
			? sprintf( __( 'Get directions to %s (opens Google Maps in a new tab)', 'doctor-ak-portal' ), $place )
			: __( 'Get directions (opens Google Maps in a new tab)', 'doctor-ak-portal' );

		return '<a class="dak-map-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $label ) . '">'
			. '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>'
			. esc_html__( 'Get directions', 'doctor-ak-portal' )
			. '</a>';
	}
}
