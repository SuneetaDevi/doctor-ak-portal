<?php
/**
 * Return navigation for the Encounter detail screen.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounter_Return
 *
 * The Encounter screen is reached from several lists (Encounters,
 * Appointments, the dashboard overview) on both the admin and doctor
 * dashboards, but its back link used to always say "Back to Appointments"
 * and drop every filter. Each link into an encounter now carries
 * `from=<list>` plus that list's own filter values; the encounter screen
 * turns them back into a link to the same list, same filters, scrolled to
 * the same row. `from` is always checked against an allowlist, and only the
 * listed parameters are carried — nothing else from the URL is echoed back.
 */
class Encounter_Return {

	/**
	 * Allowed `from` values => the filter parameters that list uses.
	 * Admin and doctor dashboards share these parameter names.
	 *
	 * @var array
	 */
	const LISTS = array(
		'encounters'   => array( 'date_from', 'date_to', 'doctor_id', 'patient_id', 'status' ),
		'appointments' => array( 'range', 'date_from', 'date_to', 'doctor_id', 'payment_status', 'search', 'sort', 'patient_id' ),
		'dashboard'    => array(),
	);

	/**
	 * Query args to add to a link into an encounter from a given list.
	 *
	 * @param string $from  One of the LISTS keys.
	 * @param array  $state That list's current filter values (key => value).
	 * @return array Empty for an unknown $from.
	 */
	public static function link_args( $from, array $state = array() ) {
		if ( ! isset( self::LISTS[ $from ] ) ) {
			return array();
		}

		$args = array( 'from' => $from );

		foreach ( self::LISTS[ $from ] as $key ) {
			if ( ! array_key_exists( $key, $state ) || null === $state[ $key ] ) {
				continue;
			}

			$value = (string) $state[ $key ];

			// An empty `range` is a real choice ("All"), unlike the other
			// filters, where empty/0 just means "not filtered".
			if ( 'range' !== $key && ( '' === $value || '0' === $value ) ) {
				continue;
			}

			$args[ $key ] = $value;
		}

		return $args;
	}

	/**
	 * The back link for the current encounter request.
	 *
	 * @param callable $list_url    Maps a list slug ('encounters', 'appointments', 'dashboard') to that list's base URL, or '' when the viewer can't reach it.
	 * @param array    $encounter   The encounter being viewed (Encounters::find()), or an empty array.
	 * @return array { @type string url, @type string label } — url is '' when no list can be linked.
	 */
	public static function back_link( $list_url, array $encounter = array() ) {
		$from = isset( $_GET['from'] ) ? sanitize_key( wp_unslash( $_GET['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		// Unknown or missing → the previous fixed behaviour (Appointments).
		if ( ! isset( self::LISTS[ $from ] ) ) {
			$from = 'appointments';
		}

		$labels = array(
			'encounters'   => __( 'Back to Encounters', 'doctor-ak-portal' ),
			'appointments' => __( 'Back to Appointments', 'doctor-ak-portal' ),
			'dashboard'    => __( 'Back to Dashboard', 'doctor-ak-portal' ),
		);

		$base = (string) call_user_func( $list_url, $from );

		if ( '' === $base ) {
			return array(
				'url'   => '',
				'label' => $labels[ $from ],
			);
		}

		$args = array();

		foreach ( self::LISTS[ $from ] as $key ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			}
		}

		$url = empty( $args ) ? $base : add_query_arg( $args, $base );

		// Land on the row the visitor came from (rows carry these ids, and
		// :target highlights them).
		if ( 'encounters' === $from && ! empty( $encounter['id'] ) ) {
			$url .= '#dak-encounter-' . (int) $encounter['id'];
		} elseif ( 'appointments' === $from && ! empty( $encounter['appointment_id'] ) && isset( $_GET['from'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
			$url .= '#dak-appointment-' . (int) $encounter['appointment_id'];
		}

		return array(
			'url'   => $url,
			'label' => $labels[ $from ],
		);
	}
}
