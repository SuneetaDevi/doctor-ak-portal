<?php
/**
 * Doctor settlement records — one row per doctor per reviewed period,
 * capturing the Revenue_Ledger totals at review time and letting an admin
 * record that a payout/collection actually happened.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settlement_Manager
 *
 * A settlement is a record-keeping action only — there's no payment-gateway
 * payout integration here. An admin pays/collects from the doctor by
 * whatever real-world method the clinic already uses, then marks the
 * settlement Paid (platform paid the doctor) or Received (platform
 * collected from the doctor), which is what actually locks the covered
 * Revenue_Ledger rows out of future outstanding-balance calculations (see
 * create(), which stamps them via Revenue_Ledger::mark_settled()
 * immediately — not only once "paid"/"received" is clicked — so a pending
 * settlement can't be double-created for the same period).
 */
class Settlement_Manager {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_revenue_settlements';

	const DIRECTION_PAYABLE_TO_DOCTOR     = 'payable_to_doctor';
	const DIRECTION_RECEIVABLE_FROM_DOCTOR = 'receivable_from_doctor';
	const DIRECTION_SETTLED                = 'settled';

	const STATUS_PENDING   = 'pending';
	const STATUS_PAID      = 'paid';
	const STATUS_RECEIVED  = 'received';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Returns the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Creates a settlement for a doctor's currently-outstanding (unsettled)
	 * ledger rows within a period, and immediately locks those rows to it
	 * (see Revenue_Ledger::mark_settled()) so they stop counting toward
	 * "outstanding" the moment the settlement exists, before an admin has
	 * even marked it Paid/Received.
	 *
	 * @param int    $doctor_id    Doctor's user ID.
	 * @param string $period_start 'YYYY-MM-DD'.
	 * @param string $period_end   'YYYY-MM-DD'.
	 * @param string $notes        Optional admin note.
	 * @return int|\WP_Error New settlement ID, or WP_Error if there's nothing to settle.
	 */
	public static function create( $doctor_id, $period_start, $period_end, $notes = '' ) {
		$ledger_ids = Revenue_Ledger::unsettled_ids_for_doctor( $doctor_id, $period_start, $period_end );

		if ( empty( $ledger_ids ) ) {
			return new \WP_Error( 'doctor_ak_settlement_nothing_to_settle', __( 'This doctor has no outstanding, unsettled transactions in that period.', 'doctor-ak-portal' ) );
		}

		$outstanding     = Revenue_Ledger::outstanding_for_doctor( $doctor_id, $period_start, $period_end );
		$closing_balance = $outstanding['closing_balance'];

		if ( $closing_balance > 0.0 ) {
			$direction = self::DIRECTION_PAYABLE_TO_DOCTOR;
		} elseif ( $closing_balance < 0.0 ) {
			$direction = self::DIRECTION_RECEIVABLE_FROM_DOCTOR;
		} else {
			$direction = self::DIRECTION_SETTLED;
		}

		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'            => (int) $doctor_id,
				'period_start'         => $period_start,
				'period_end'           => $period_end,
				'video_earnings'       => number_format( $outstanding['video_earnings'], 2, '.', '' ),
				'clinic_obligations'   => number_format( $outstanding['clinic_obligations'], 2, '.', '' ),
				'platform_fees'        => number_format( $outstanding['platform_fees'], 2, '.', '' ),
				'adjustments'          => '0.00',
				'opening_balance'      => '0.00',
				'closing_balance'      => number_format( $closing_balance, 2, '.', '' ),
				'settlement_direction' => $direction,
				'settlement_status'    => self::STATUS_PENDING,
				'settled_amount'       => '0.00',
				'notes'                => sanitize_textarea_field( $notes ),
				'created_at'           => $now,
				'updated_at'           => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'doctor_ak_settlement_not_created', __( 'The settlement could not be created. Please try again.', 'doctor-ak-portal' ) );
		}

		$settlement_id = (int) $wpdb->insert_id;

		Revenue_Ledger::mark_settled( $ledger_ids, $settlement_id );

		return $settlement_id;
	}

	/**
	 * Marks a settlement Paid (the platform paid the doctor their payable
	 * balance) — only valid for a pending settlement whose direction is
	 * payable_to_doctor or settled (zero-balance periods can still be
	 * marked "paid" as a no-op close-out).
	 *
	 * @param int $settlement_id Settlement ID.
	 * @param int $admin_id      User ID marking it.
	 * @return true|\WP_Error
	 */
	public static function mark_paid( $settlement_id, $admin_id ) {
		return self::mark_resolved( $settlement_id, $admin_id, self::STATUS_PAID, array( self::DIRECTION_PAYABLE_TO_DOCTOR, self::DIRECTION_SETTLED ) );
	}

	/**
	 * Marks a settlement Received (the platform collected the doctor's
	 * receivable balance) — only valid for a pending settlement whose
	 * direction is receivable_from_doctor or settled.
	 *
	 * @param int $settlement_id Settlement ID.
	 * @param int $admin_id      User ID marking it.
	 * @return true|\WP_Error
	 */
	public static function mark_received( $settlement_id, $admin_id ) {
		return self::mark_resolved( $settlement_id, $admin_id, self::STATUS_RECEIVED, array( self::DIRECTION_RECEIVABLE_FROM_DOCTOR, self::DIRECTION_SETTLED ) );
	}

	/**
	 * Shared implementation for mark_paid()/mark_received().
	 *
	 * @param int    $settlement_id      Settlement ID.
	 * @param int    $admin_id           User ID marking it.
	 * @param string $status             STATUS_PAID or STATUS_RECEIVED.
	 * @param array  $allowed_directions Directions this status is valid for.
	 * @return true|\WP_Error
	 */
	private static function mark_resolved( $settlement_id, $admin_id, $status, array $allowed_directions ) {
		$settlement = self::find( $settlement_id );

		if ( ! $settlement ) {
			return new \WP_Error( 'doctor_ak_settlement_not_found', __( 'That settlement could not be found.', 'doctor-ak-portal' ) );
		}

		if ( self::STATUS_PENDING !== $settlement['settlement_status'] ) {
			return new \WP_Error( 'doctor_ak_settlement_not_pending', __( 'That settlement has already been resolved.', 'doctor-ak-portal' ) );
		}

		if ( ! in_array( $settlement['settlement_direction'], $allowed_directions, true ) ) {
			return new \WP_Error( 'doctor_ak_settlement_wrong_direction', __( 'This settlement direction does not match that action.', 'doctor-ak-portal' ) );
		}

		global $wpdb;

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'settlement_status' => $status,
				'settled_amount'    => number_format( abs( $settlement['closing_balance'] ), 2, '.', '' ),
				'settled_at'        => current_time( 'mysql' ),
				'settled_by'        => (int) $admin_id,
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => (int) $settlement_id ),
			array( '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'doctor_ak_settlement_not_updated', __( 'The settlement could not be updated. Please try again.', 'doctor-ak-portal' ) );
		}

		return true;
	}

	/**
	 * Finds a single settlement by ID.
	 *
	 * @param int $settlement_id Settlement ID.
	 * @return array|null Decoded settlement row, or null if not found.
	 */
	public static function find( $settlement_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $settlement_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * Every settlement for one doctor, newest first.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array List of decoded settlement rows.
	 */
	public static function for_doctor( $doctor_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d ORDER BY period_end DESC, id DESC', (int) $doctor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Every settlement across every doctor, for the admin billing screen's
	 * settlement panel.
	 *
	 * @param array $filters { @type int doctor_id, @type string status }
	 * @return array List of decoded settlement rows, each with an added 'doctor_name'.
	 */
	public static function all_flat_for_admin( array $filters = array() ) {
		global $wpdb;

		$where  = array();
		$params = array();

		if ( ! empty( $filters['doctor_id'] ) ) {
			$where[]  = 'doctor_id = %d';
			$params[] = (int) $filters['doctor_id'];
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'settlement_status = %s';
			$params[] = $filters['status'];
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$sql = ! empty( $params )
			? $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . " $where_sql ORDER BY period_end DESC, id DESC", $params ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input; $where_sql built entirely from %d/%s placeholders above.
			: 'SELECT * FROM ' . self::table_name() . ' ORDER BY period_end DESC, id DESC'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is either the result of $wpdb->prepare() above, or a literal string with no user input.

		return array_map(
			function ( $row ) {
				$settlement = self::decode_row( $row );
				$doctor     = get_userdata( $settlement['doctor_id'] );

				$settlement['doctor_name'] = $doctor ? trim( $doctor->first_name . ' ' . $doctor->last_name ) : '';
				$settlement['doctor_name'] = '' !== $settlement['doctor_name'] ? $settlement['doctor_name'] : ( $doctor ? $doctor->display_name : __( 'Unknown doctor', 'doctor-ak-portal' ) );

				return $settlement;
			},
			$rows
		);
	}

	/**
	 * Decodes a raw DB row into the shape the rest of the codebase works with.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		return array(
			'id'                    => (int) $row['id'],
			'doctor_id'             => (int) $row['doctor_id'],
			'period_start'          => $row['period_start'],
			'period_end'            => $row['period_end'],
			'video_earnings'        => (float) $row['video_earnings'],
			'clinic_obligations'    => (float) $row['clinic_obligations'],
			'platform_fees'         => (float) $row['platform_fees'],
			'adjustments'           => (float) $row['adjustments'],
			'opening_balance'       => (float) $row['opening_balance'],
			'closing_balance'       => (float) $row['closing_balance'],
			'settlement_direction'  => $row['settlement_direction'],
			'settlement_status'     => $row['settlement_status'],
			'settled_amount'        => (float) $row['settled_amount'],
			'settled_at'            => $row['settled_at'],
			'settled_by'            => (int) $row['settled_by'],
			'notes'                 => $row['notes'],
			'created_at'            => $row['created_at'],
		);
	}
}
