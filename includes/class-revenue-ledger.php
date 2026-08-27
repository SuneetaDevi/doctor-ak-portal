<?php
/**
 * The doctor+clinic-wise financial ledger — one immutable-ish row per
 * posted transaction (a paid appointment, or a refund reversal of one),
 * snapshotting the gross amount and each side's share at the moment it was
 * posted. See Revenue_Calculator for the actual split math this class
 * persists, and Settlement_Manager for how a batch of ledger rows gets
 * marked settled.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Revenue_Ledger
 */
class Revenue_Ledger {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_revenue_ledger';

	const STATUS_POSTED   = 'posted';
	const STATUS_REVERSED = 'reversed';

	const TRANSACTION_REFUND = 'refund';

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
	 * Posts a ledger entry for a paid appointment — the doctor+clinic-wise
	 * "revenue was recognized" moment (see Appointments::mark_paid(), which
	 * this is hooked to via the `doctor_ak_appointment_paid` action, see
	 * class-plugin.php). Idempotent: does nothing if this appointment
	 * already has a non-reversed entry of the transaction type it would
	 * produce, so calling it more than once (a duplicate webhook, re-running
	 * the one-time backfill migration, etc.) never double-counts revenue.
	 *
	 * @param int  $appointment_id Appointment post ID.
	 * @param bool $is_legacy      True for the one-time historical backfill (Db_Installer::backfill_revenue_ledger()) — flags the row so it's distinguishable from a real-time entry, which snapshots the split actually in effect at payment time rather than today's.
	 * @return int|false New ledger row ID, false if not postable (not paid, appointment missing) or already posted.
	 */
	public static function post_for_appointment( $appointment_id, $is_legacy = false ) {
		$appointment = Appointments::find( $appointment_id );

		if ( empty( $appointment ) || Appointments::PAYMENT_STATUS_PAID !== $appointment['payment_status'] ) {
			return false;
		}

		$breakdown = Revenue_Calculator::calculate_for_appointment( $appointment );

		if ( self::has_posted_entry( $appointment_id, $breakdown['transaction_type'] ) ) {
			return false;
		}

		global $wpdb;

		$now = current_time( 'mysql' );

		$is_video    = Appointments::TYPE_VIDEO === $appointment['type'];
		$description = $is_video
			? __( 'Video Consultation', 'doctor-ak-portal' )
			: ( '' !== $appointment['service_name'] ? $appointment['service_name'] : __( 'Clinic Appointment', 'doctor-ak-portal' ) );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'         => (int) $appointment['doctor_id'],
				'clinic_id'         => (int) $breakdown['clinic_id'],
				'appointment_id'    => (int) $appointment_id,
				'service_id'        => (int) $appointment['service_id'],
				'transaction_type'  => $breakdown['transaction_type'],
				'direction'         => $breakdown['direction'],
				'gross_amount'      => number_format( $breakdown['gross_amount'], 2, '.', '' ),
				'platform_fee'      => number_format( $breakdown['platform_fee'], 2, '.', '' ),
				'share_percent'     => number_format( $breakdown['share_percent'], 2, '.', '' ),
				'doctor_amount'     => number_format( $breakdown['doctor_amount'], 2, '.', '' ),
				'clinic_amount'     => number_format( $breakdown['clinic_amount'], 2, '.', '' ),
				'net_amount'        => number_format( $breakdown['net_amount'], 2, '.', '' ),
				'description'       => $description,
				'reference'         => sprintf( 'APT-%d', $appointment_id ),
				'status'            => self::STATUS_POSTED,
				'is_legacy'         => $is_legacy ? 1 : 0,
				'settlement_id'     => 0,
				'transaction_date'  => '' !== $appointment['date'] ? $appointment['date'] : gmdate( 'Y-m-d', strtotime( $now ) ),
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Posts a ledger entry for the extra charges a doctor added to an
	 * encounter on top of the appointment's own charge (see
	 * Encounter_Bill_Items) — hooked to `doctor_ak_encounter_closed` (see
	 * class-plugin.php) so those on-the-spot charges land in billing the
	 * moment the visit is checked out, same as the appointment's own charge
	 * does via post_for_appointment(). Idempotent per encounter: does
	 * nothing if this encounter already has a posted entry, so it's safe to
	 * call more than once.
	 *
	 * @param int $encounter_id   Encounter ID the extra charges belong to.
	 * @param int $appointment_id Its appointment's post ID (for the revenue split's doctor/clinic/type/payment_mode context).
	 * @return int|false New ledger row ID, false if not postable (nothing to bill, appointment missing, or already posted).
	 */
	public static function post_for_encounter_extra( $encounter_id, $appointment_id ) {
		$extra_amount = Encounter_Bill_Items::total_for_encounter( $encounter_id );

		if ( $extra_amount <= 0 ) {
			return false;
		}

		$appointment = Appointments::find( $appointment_id );

		if ( empty( $appointment ) ) {
			return false;
		}

		$reference = sprintf( 'ENC-%d-EXTRA', $encounter_id );
		$breakdown = Revenue_Calculator::calculate_for_appointment( array_merge( $appointment, array( 'charge' => $extra_amount ) ) );

		global $wpdb;

		// Scoped to this specific transaction_type (clinic/video
		// consultation), not "any posted row with this reference" — a
		// reversal row from resync_encounter_extra() shares the same
		// reference but is posted as TRANSACTION_REFUND, and must not count
		// as "already posted" here or a resync could never repost.
		$already_posted = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE reference = %s AND status = %s AND transaction_type = %s', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				$reference,
				self::STATUS_POSTED,
				$breakdown['transaction_type']
			)
		);

		if ( $already_posted > 0 ) {
			return false;
		}

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'        => (int) $appointment['doctor_id'],
				'clinic_id'        => (int) $breakdown['clinic_id'],
				'appointment_id'   => (int) $appointment_id,
				'service_id'       => (int) $appointment['service_id'],
				'transaction_type' => $breakdown['transaction_type'],
				'direction'        => $breakdown['direction'],
				'gross_amount'     => number_format( $breakdown['gross_amount'], 2, '.', '' ),
				'platform_fee'     => number_format( $breakdown['platform_fee'], 2, '.', '' ),
				'share_percent'    => number_format( $breakdown['share_percent'], 2, '.', '' ),
				'doctor_amount'    => number_format( $breakdown['doctor_amount'], 2, '.', '' ),
				'clinic_amount'    => number_format( $breakdown['clinic_amount'], 2, '.', '' ),
				'net_amount'       => number_format( $breakdown['net_amount'], 2, '.', '' ),
				'description'      => __( 'Additional charges — encounter', 'doctor-ak-portal' ),
				'reference'        => $reference,
				'status'           => self::STATUS_POSTED,
				'is_legacy'        => 0,
				'settlement_id'    => 0,
				'transaction_date' => '' !== $appointment['date'] ? $appointment['date'] : gmdate( 'Y-m-d', strtotime( $now ) ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Re-syncs a CLOSED encounter's "extra charges" ledger entry with its
	 * current Encounter_Bill_Items total — called by Encounter_Handler
	 * whenever a bill item is added/removed on an encounter that's already
	 * closed (an OPEN encounter's bill items are left alone; they get
	 * posted for the first time, once, when it closes — see
	 * post_for_encounter_extra()). Reverses whatever was posted before (if
	 * anything) and posts fresh for the current total, so editing a closed
	 * encounter's bill keeps billing accurate instead of drifting from
	 * what's actually charged.
	 *
	 * @param int $encounter_id   Encounter ID.
	 * @param int $appointment_id Its appointment's post ID.
	 * @return void
	 */
	public static function resync_encounter_extra( $encounter_id, $appointment_id ) {
		self::reverse_encounter_extra( $encounter_id );
		self::post_for_encounter_extra( $encounter_id, $appointment_id );
	}

	/**
	 * Reverses whatever is currently posted for an encounter's extra
	 * charges (see post_for_encounter_extra()) — the original row is marked
	 * `status = reversed` (kept, never deleted) and a new negated row is
	 * posted referencing it, mirroring reverse_for_appointment()'s own
	 * pattern. The reversal row is posted as TRANSACTION_REFUND (not the
	 * original clinic/video type) specifically so post_for_encounter_extra()'s
	 * own idempotency check never mistakes it for an already-posted charge.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return void
	 */
	private static function reverse_encounter_extra( $encounter_id ) {
		global $wpdb;

		$reference = sprintf( 'ENC-%d-EXTRA', $encounter_id );

		$original = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE reference = %s AND status = %s AND transaction_type != %s ORDER BY id DESC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				$reference,
				self::STATUS_POSTED,
				self::TRANSACTION_REFUND
			),
			ARRAY_A
		);

		if ( ! $original ) {
			return;
		}

		$now = current_time( 'mysql' );

		$wpdb->update(
			self::table_name(),
			array(
				'status'     => self::STATUS_REVERSED,
				'updated_at' => $now,
			),
			array( 'id' => (int) $original['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'        => (int) $original['doctor_id'],
				'clinic_id'        => (int) $original['clinic_id'],
				'appointment_id'   => (int) $original['appointment_id'],
				'service_id'       => (int) $original['service_id'],
				'transaction_type' => self::TRANSACTION_REFUND,
				// The reversal always moves the doctor's balance the
				// opposite way the original entry did.
				'direction'        => Revenue_Calculator::DIRECTION_CREDIT === $original['direction'] ? Revenue_Calculator::DIRECTION_DEBIT : Revenue_Calculator::DIRECTION_CREDIT,
				'gross_amount'     => $original['gross_amount'],
				'platform_fee'     => $original['platform_fee'],
				'share_percent'    => $original['share_percent'],
				'doctor_amount'    => $original['doctor_amount'],
				'clinic_amount'    => $original['clinic_amount'],
				'net_amount'       => number_format( 0 - (float) $original['net_amount'], 2, '.', '' ),
				/* translators: %s: original ledger entry's description. */
				'description'      => sprintf( __( 'Reversal — %s', 'doctor-ak-portal' ), $original['description'] ),
				'reference'        => $reference,
				'status'           => self::STATUS_POSTED,
				'is_legacy'        => 0,
				'settlement_id'    => 0,
				'transaction_date' => gmdate( 'Y-m-d', strtotime( $now ) ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Reverses a previously-posted appointment transaction — the refund
	 * path (see Appointments::mark_refund_processed(), hooked via the
	 * existing `doctor_ak_appointment_refund_processed` action). The
	 * original row is marked `status = reversed` (kept, never deleted —
	 * the ledger stays auditable) and a new negated row is posted
	 * referencing it, so the doctor's balance corrects without erasing
	 * history.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return int|false New reversal row ID, false if there was nothing postable to reverse.
	 */
	public static function reverse_for_appointment( $appointment_id ) {
		global $wpdb;

		$original = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE appointment_id = %d AND status = %s ORDER BY id DESC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				(int) $appointment_id,
				self::STATUS_POSTED
			),
			ARRAY_A
		);

		if ( ! $original ) {
			return false;
		}

		$now = current_time( 'mysql' );

		$wpdb->update(
			self::table_name(),
			array(
				'status'     => self::STATUS_REVERSED,
				'updated_at' => $now,
			),
			array( 'id' => (int) $original['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'        => (int) $original['doctor_id'],
				'clinic_id'        => (int) $original['clinic_id'],
				'appointment_id'   => (int) $appointment_id,
				'service_id'       => (int) $original['service_id'],
				'transaction_type' => self::TRANSACTION_REFUND,
				// The reversal always moves the doctor's balance the
				// opposite way the original transaction did.
				'direction'        => Revenue_Calculator::DIRECTION_CREDIT === $original['direction'] ? Revenue_Calculator::DIRECTION_DEBIT : Revenue_Calculator::DIRECTION_CREDIT,
				'gross_amount'     => $original['gross_amount'],
				'platform_fee'     => $original['platform_fee'],
				'share_percent'    => $original['share_percent'],
				'doctor_amount'    => $original['doctor_amount'],
				'clinic_amount'    => $original['clinic_amount'],
				'net_amount'       => number_format( 0 - (float) $original['net_amount'], 2, '.', '' ),
				'description'      => sprintf( /* translators: %s: original transaction description. */ __( 'Refund — %s', 'doctor-ak-portal' ), $original['description'] ),
				'reference'        => sprintf( 'APT-%d', $appointment_id ),
				'status'           => self::STATUS_POSTED,
				'is_legacy'        => 0,
				'settlement_id'    => 0,
				'transaction_date' => gmdate( 'Y-m-d', strtotime( $now ) ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Whether an appointment already has a non-reversed ledger row of a
	 * given transaction type — the idempotency guard post_for_appointment()
	 * relies on.
	 *
	 * @param int    $appointment_id   Appointment post ID.
	 * @param string $transaction_type Transaction type to check for.
	 * @return bool
	 */
	private static function has_posted_entry( $appointment_id, $transaction_type ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE appointment_id = %d AND transaction_type = %s AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				(int) $appointment_id,
				$transaction_type,
				self::STATUS_POSTED
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Every ledger row matching the given filters, newest first.
	 *
	 * @param array $filters {
	 *     Optional.
	 *
	 *     @type int    $doctor_id         Only this doctor's rows, when > 0.
	 *     @type int    $clinic_id         Only this clinic's rows, when > 0. Pass -1 to mean "video only" (clinic_id = 0).
	 *     @type string $transaction_type  Only rows of this type.
	 *     @type int    $service_id        Only this service's rows, when > 0.
	 *     @type string $date_from         'YYYY-MM-DD'.
	 *     @type string $date_to           'YYYY-MM-DD'.
	 *     @type string $settlement_status 'settled' (settlement_id > 0), 'unsettled' (settlement_id = 0), or '' for both.
	 *     @type string $status            'posted'/'reversed', default 'posted' (reversed rows only show via the refund's own row, not double-listed).
	 *     @type int    $number            Max rows. Default 500.
	 * }
	 * @return array List of decoded ledger rows.
	 */
	public static function all_flat_for_admin( array $filters = array() ) {
		global $wpdb;

		$where  = array();
		$params = array();

		if ( ! empty( $filters['doctor_id'] ) ) {
			$where[]  = 'doctor_id = %d';
			$params[] = (int) $filters['doctor_id'];
		}

		if ( isset( $filters['clinic_id'] ) && '' !== $filters['clinic_id'] ) {
			if ( -1 === (int) $filters['clinic_id'] ) {
				$where[] = 'clinic_id = 0';
			} elseif ( (int) $filters['clinic_id'] > 0 ) {
				$where[]  = 'clinic_id = %d';
				$params[] = (int) $filters['clinic_id'];
			}
		}

		if ( ! empty( $filters['transaction_type'] ) ) {
			$where[]  = 'transaction_type = %s';
			$params[] = $filters['transaction_type'];
		}

		if ( ! empty( $filters['service_id'] ) ) {
			$where[]  = 'service_id = %d';
			$params[] = (int) $filters['service_id'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'transaction_date >= %s';
			$params[] = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'transaction_date <= %s';
			$params[] = $filters['date_to'];
		}

		if ( ! empty( $filters['settlement_status'] ) ) {
			$where[] = 'settled' === $filters['settlement_status'] ? 'settlement_id > 0' : 'settlement_id = 0';
		}

		$where[]  = 'status = %s';
		$params[] = ! empty( $filters['status'] ) ? $filters['status'] : self::STATUS_POSTED;

		$number   = ! empty( $filters['number'] ) ? (int) $filters['number'] : 500;
		$params[] = $number;

		$sql = $wpdb->prepare(
			'SELECT * FROM ' . self::table_name() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY transaction_date DESC, id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input; $where built entirely from %d/%s placeholders above.
			$params
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * A summary of ledger totals matching the given filters — gross/fees/
	 * doctor+clinic shares, split by video vs. clinic transaction type, plus
	 * the still-outstanding (unsettled) doctor balance.
	 *
	 * @param array $filters Same shape as all_flat_for_admin(), minus 'number'/'status'.
	 * @return array {
	 *     @type float gross_total
	 *     @type float video_gross
	 *     @type float clinic_gross
	 *     @type float platform_fees
	 *     @type float doctor_earnings
	 *     @type float clinic_earnings
	 *     @type float outstanding_balance Unsettled credits minus unsettled debits — positive = platform owes doctor(s), negative = doctor(s) owe platform.
	 * }
	 */
	public static function summary( array $filters = array() ) {
		$rows = self::all_flat_for_admin( array_merge( $filters, array( 'number' => 100000 ) ) );

		$summary = array(
			'gross_total'         => 0.0,
			'video_gross'         => 0.0,
			'clinic_gross'        => 0.0,
			'platform_fees'       => 0.0,
			'doctor_earnings'     => 0.0,
			'clinic_earnings'     => 0.0,
			'outstanding_balance' => 0.0,
		);

		foreach ( $rows as $row ) {
			if ( Revenue_Calculator::TRANSACTION_VIDEO_CONSULTATION === $row['transaction_type'] ) {
				$summary['video_gross'] += $row['gross_amount'];
			} elseif ( Revenue_Calculator::TRANSACTION_CLINIC_CONSULTATION === $row['transaction_type'] ) {
				$summary['clinic_gross'] += $row['gross_amount'];
			}

			if ( self::TRANSACTION_REFUND !== $row['transaction_type'] ) {
				$summary['gross_total']     += $row['gross_amount'];
				$summary['platform_fees']   += $row['platform_fee'];
				$summary['doctor_earnings'] += $row['doctor_amount'];
				$summary['clinic_earnings'] += $row['clinic_amount'];
			}

			if ( 0 === (int) $row['settlement_id'] ) {
				$summary['outstanding_balance'] += $row['net_amount'];
			}
		}

		foreach ( $summary as $key => $value ) {
			$summary[ $key ] = round( $value, 2 );
		}

		return $summary;
	}

	/**
	 * The still-outstanding (unsettled) video-earnings / clinic-obligations
	 * split for one doctor over an optional period — the exact inputs
	 * Settlement_Manager::create() needs.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $date_from 'YYYY-MM-DD', or '' for no lower bound.
	 * @param string $date_to   'YYYY-MM-DD', or '' for no upper bound.
	 * @return array { @type float video_earnings, @type float clinic_obligations, @type float platform_fees, @type float closing_balance }
	 */
	public static function outstanding_for_doctor( $doctor_id, $date_from = '', $date_to = '' ) {
		$rows = self::all_flat_for_admin(
			array(
				'doctor_id'         => $doctor_id,
				'date_from'         => $date_from,
				'date_to'           => $date_to,
				'settlement_status' => 'unsettled',
				'number'            => 100000,
			)
		);

		$video_earnings     = 0.0;
		$clinic_obligations = 0.0;
		$platform_fees      = 0.0;

		foreach ( $rows as $row ) {
			$platform_fees += $row['platform_fee'];

			if ( Revenue_Calculator::DIRECTION_CREDIT === $row['direction'] ) {
				$video_earnings += $row['net_amount'];
			} else {
				$clinic_obligations += abs( $row['net_amount'] );
			}
		}

		return array(
			'video_earnings'     => round( $video_earnings, 2 ),
			'clinic_obligations' => round( $clinic_obligations, 2 ),
			'platform_fees'      => round( $platform_fees, 2 ),
			'closing_balance'    => round( $video_earnings - $clinic_obligations, 2 ),
		);
	}

	/**
	 * Stamps every given ledger row with a settlement ID, locking them out
	 * of future outstanding-balance calculations — called once by
	 * Settlement_Manager::create() right after computing a settlement's
	 * totals from these same rows.
	 *
	 * @param array $ledger_ids   Ledger row IDs.
	 * @param int   $settlement_id Settlement row ID.
	 * @return void
	 */
	public static function mark_settled( array $ledger_ids, $settlement_id ) {
		global $wpdb;

		$ledger_ids = array_values( array_unique( array_map( 'absint', $ledger_ids ) ) );

		if ( empty( $ledger_ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ledger_ids ), '%d' ) );
		$params       = array_merge( array( (int) $settlement_id, current_time( 'mysql' ) ), $ledger_ids );

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table_name() . " SET settlement_id = %d, updated_at = %s WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table name/placeholder count, not user input.
				$params
			)
		);
	}

	/**
	 * The raw (unsettled-or-not) ledger row IDs for a doctor within a
	 * period — used by Settlement_Manager::create() to know which rows to
	 * stamp via mark_settled() once the settlement itself is inserted.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $date_from 'YYYY-MM-DD'.
	 * @param string $date_to   'YYYY-MM-DD'.
	 * @return array List of ledger row IDs.
	 */
	public static function unsettled_ids_for_doctor( $doctor_id, $date_from, $date_to ) {
		$rows = self::all_flat_for_admin(
			array(
				'doctor_id'         => $doctor_id,
				'date_from'         => $date_from,
				'date_to'           => $date_to,
				'settlement_status' => 'unsettled',
				'number'            => 100000,
			)
		);

		return wp_list_pluck( $rows, 'id' );
	}

	/**
	 * The current outstanding (unsettled) balance for every doctor+clinic
	 * pairing that has activity in the given filters — one row per
	 * (doctor_id, clinic_id), never merging two clinics under the same
	 * doctor. Positive balance = clinic owes doctor, negative = doctor owes
	 * clinic. This is the admin Billing screen's first-view summary.
	 *
	 * @param array $filters { @type string date_from, @type string date_to, @type int doctor_id }
	 * @return array List of { doctor_id, clinic_id, balance, appointment_count, gross_total }.
	 */
	public static function balances_by_doctor_and_clinic( array $filters = array() ) {
		$rows = self::all_flat_for_admin(
			array_merge(
				$filters,
				array(
					'settlement_status' => 'unsettled',
					'number'            => 100000,
				)
			)
		);

		$grouped = array();

		foreach ( $rows as $row ) {
			$key = $row['doctor_id'] . ':' . $row['clinic_id'];

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array(
					'doctor_id'         => $row['doctor_id'],
					'clinic_id'         => $row['clinic_id'],
					'balance'           => 0.0,
					'appointment_count' => 0,
					'gross_total'       => 0.0,
				);
			}

			$grouped[ $key ]['balance']     += $row['net_amount'];
			$grouped[ $key ]['gross_total'] += $row['gross_amount'];

			if ( self::TRANSACTION_REFUND !== $row['transaction_type'] ) {
				++$grouped[ $key ]['appointment_count'];
			}
		}

		return array_values(
			array_map(
				function ( $group ) {
					$group['balance']     = round( $group['balance'], 2 );
					$group['gross_total'] = round( $group['gross_total'], 2 );
					return $group;
				},
				$grouped
			)
		);
	}

	/**
	 * Per-service breakdown (label, quantity, average price, total amount)
	 * for one doctor at one clinic (or their video consultations, when
	 * `$clinic_id` is 0) — the admin Billing screen's "View Details" panel.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param int    $clinic_id Clinic ID, or 0 for video consultations.
	 * @param string $date_from 'YYYY-MM-DD', or '' for no lower bound.
	 * @param string $date_to   'YYYY-MM-DD', or '' for no upper bound.
	 * @return array List of { label, quantity, avg_price, total_amount, doctor_amount, clinic_amount }.
	 */
	public static function service_breakdown_for_doctor_clinic( $doctor_id, $clinic_id, $date_from = '', $date_to = '' ) {
		$rows = self::all_flat_for_admin(
			array(
				'doctor_id' => $doctor_id,
				'clinic_id' => 0 === (int) $clinic_id ? -1 : (int) $clinic_id,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'number'    => 100000,
			)
		);

		$grouped = array();

		foreach ( $rows as $row ) {
			if ( self::TRANSACTION_REFUND === $row['transaction_type'] ) {
				continue;
			}

			$key = $row['description'];

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array(
					'label'         => $row['description'],
					'quantity'      => 0,
					'total_amount'  => 0.0,
					'doctor_amount' => 0.0,
					'clinic_amount' => 0.0,
				);
			}

			++$grouped[ $key ]['quantity'];
			$grouped[ $key ]['total_amount']  += $row['gross_amount'];
			$grouped[ $key ]['doctor_amount'] += $row['doctor_amount'];
			$grouped[ $key ]['clinic_amount'] += $row['clinic_amount'];
		}

		return array_values(
			array_map(
				function ( $group ) {
					return array(
						'label'         => $group['label'],
						'quantity'      => $group['quantity'],
						'avg_price'     => $group['quantity'] > 0 ? round( $group['total_amount'] / $group['quantity'], 2 ) : 0.0,
						'total_amount'  => round( $group['total_amount'], 2 ),
						'doctor_amount' => round( $group['doctor_amount'], 2 ),
						'clinic_amount' => round( $group['clinic_amount'], 2 ),
					);
				},
				$grouped
			)
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
			'id'               => (int) $row['id'],
			'doctor_id'        => (int) $row['doctor_id'],
			'clinic_id'        => (int) $row['clinic_id'],
			'appointment_id'   => (int) $row['appointment_id'],
			'service_id'       => (int) $row['service_id'],
			'transaction_type' => $row['transaction_type'],
			'direction'        => $row['direction'],
			'gross_amount'     => (float) $row['gross_amount'],
			'platform_fee'     => (float) $row['platform_fee'],
			'share_percent'    => (float) $row['share_percent'],
			'doctor_amount'    => (float) $row['doctor_amount'],
			'clinic_amount'    => (float) $row['clinic_amount'],
			'net_amount'       => (float) $row['net_amount'],
			'description'      => $row['description'],
			'reference'        => $row['reference'],
			'status'           => $row['status'],
			'is_legacy'        => ! empty( $row['is_legacy'] ),
			'settlement_id'    => (int) $row['settlement_id'],
			'transaction_date' => $row['transaction_date'],
			'created_at'       => $row['created_at'],
		);
	}
}
