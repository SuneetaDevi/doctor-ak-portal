<?php
/**
 * Clinical encounters — opened when a patient is checked in for an
 * appointment, closed once the visit (and its documentation) is done.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounters
 *
 * One row per check-in. Owns the encounter's own lifecycle (open/closed),
 * separate from the appointment's own status (which mirrors it via
 * Appointments::STATUS_CHECKED_IN/STATUS_COMPLETED — see check_in()/
 * close() below). Problems/Prescriptions/Bill items live in their own
 * tables (Encounter_Problems/Encounter_Prescriptions/Encounter_Bill_Items),
 * each scoped by encounter_id.
 */
class Encounters {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_encounters';

	const STATUS_OPEN   = 'open';
	const STATUS_CLOSED = 'closed';

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
	 * Checks a patient in — opens a new encounter, or returns the already-
	 * open one for this appointment if Check-in is clicked twice (idempotent,
	 * so a double-click/double-submit never errors).
	 *
	 * @param int $appointment_id    Appointment post ID.
	 * @param int $doctor_id         Doctor's user ID.
	 * @param int $patient_id        Patient's user ID (0 for a guest booking).
	 * @param int $clinic_id         Clinic this visit is at, 0 if none/not applicable.
	 * @param int $checked_in_by_id  User ID performing the check-in (doctor, admin, or receptionist).
	 * @return int|\WP_Error New (or existing open) encounter ID, or WP_Error if the appointment isn't checkinable.
	 */
	public static function check_in( $appointment_id, $doctor_id, $patient_id, $clinic_id, $checked_in_by_id ) {
		$existing = self::find_by_appointment( $appointment_id, self::STATUS_OPEN );

		if ( $existing ) {
			return $existing['id'];
		}

		$result = Appointments::check_in( $appointment_id, $clinic_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'appointment_id' => (int) $appointment_id,
				'doctor_id'      => (int) $doctor_id,
				'patient_id'     => (int) $patient_id,
				'clinic_id'      => (int) $clinic_id,
				'status'         => self::STATUS_OPEN,
				'legacy_note'    => (string) get_post_meta( $appointment_id, 'doctor_ak_appointment_encounter_notes', true ),
				'checked_in_at'  => $now,
				'checked_in_by'  => (int) $checked_in_by_id,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'doctor_ak_encounter_not_created', __( 'The encounter could not be created. Please try again.', 'doctor-ak-portal' ) );
		}

		$encounter_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a new encounter is opened via check-in.
		 *
		 * @param int $encounter_id   Newly opened encounter's ID.
		 * @param int $appointment_id Its appointment's post ID.
		 */
		do_action( 'doctor_ak_encounter_checked_in', $encounter_id, $appointment_id );

		return $encounter_id;
	}

	/**
	 * Closes an encounter and checks the patient out.
	 *
	 * @param int $encounter_id   Encounter ID.
	 * @param int $closed_by_id   User ID closing the encounter.
	 * @return true|\WP_Error
	 */
	public static function close( $encounter_id, $closed_by_id ) {
		$encounter = self::find( $encounter_id );

		if ( empty( $encounter ) || self::STATUS_OPEN !== $encounter['status'] ) {
			return new \WP_Error( 'doctor_ak_encounter_not_open', __( 'That encounter is not open.', 'doctor-ak-portal' ) );
		}

		global $wpdb;

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'status'     => self::STATUS_CLOSED,
				'closed_at'  => current_time( 'mysql' ),
				'closed_by'  => (int) $closed_by_id,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $encounter_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'doctor_ak_encounter_not_closed', __( 'The encounter could not be closed. Please try again.', 'doctor-ak-portal' ) );
		}

		Appointments::checkout( $encounter['appointment_id'] );

		/**
		 * Fires after an encounter is closed (patient checked out).
		 *
		 * @param int $encounter_id   Closed encounter's ID.
		 * @param int $appointment_id Its appointment's post ID.
		 */
		do_action( 'doctor_ak_encounter_closed', $encounter_id, $encounter['appointment_id'] );

		return true;
	}

	/**
	 * Permanently deletes an encounter and all of its child rows (problems,
	 * prescriptions, bill items, reports — including their uploaded
	 * attachments). If the encounter was still open, also reverts its
	 * appointment's status back to what it was before check-in
	 * (STATUS_PAID if payment had already settled, STATUS_CONFIRMED
	 * otherwise) — check_in() only knows how to move an appointment
	 * forward, never back, so this is the one place that undoes it, for
	 * the "this check-in was a mistake" admin cleanup case. A CLOSED
	 * encounter's appointment (already STATUS_COMPLETED) is left
	 * untouched — the visit did happen.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return bool
	 */
	public static function delete( $encounter_id ) {
		$encounter = self::find( $encounter_id );

		if ( ! $encounter ) {
			return false;
		}

		global $wpdb;

		$wpdb->delete( Encounter_Problems::table_name(), array( 'encounter_id' => $encounter_id ), array( '%d' ) );
		$wpdb->delete( Encounter_Prescriptions::table_name(), array( 'encounter_id' => $encounter_id ), array( '%d' ) );
		$wpdb->delete( Encounter_Bill_Items::table_name(), array( 'encounter_id' => $encounter_id ), array( '%d' ) );

		foreach ( Encounter_Reports::for_encounter( $encounter_id ) as $report ) {
			wp_delete_attachment( $report['attachment_id'], true );
		}

		$wpdb->delete( Encounter_Reports::table_name(), array( 'encounter_id' => $encounter_id ), array( '%d' ) );

		$deleted = false !== $wpdb->delete( self::table_name(), array( 'id' => $encounter_id ), array( '%d' ) );

		if ( $deleted && self::STATUS_OPEN === $encounter['status'] ) {
			$appointment = Appointments::get( $encounter['appointment_id'] );

			if ( $appointment ) {
				$status = Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'] ? Appointments::STATUS_PAID : Appointments::STATUS_CONFIRMED;
				update_post_meta( $encounter['appointment_id'], 'doctor_ak_appointment_status', $status );
			}
		}

		return $deleted;
	}

	/**
	 * Finds a single encounter by ID.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return array|null Decoded encounter row, or null if not found.
	 */
	public static function find( $encounter_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $encounter_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * Finds the (most recent) encounter for a given appointment.
	 *
	 * @param int         $appointment_id Appointment post ID.
	 * @param string|null $status         Optional, 'open' or 'closed' to filter; null for either.
	 * @return array|null Decoded encounter row, or null if none exists.
	 */
	public static function find_by_appointment( $appointment_id, $status = null ) {
		global $wpdb;

		if ( null !== $status ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE appointment_id = %d AND status = %s ORDER BY id DESC LIMIT 1', (int) $appointment_id, $status ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE appointment_id = %d ORDER BY id DESC LIMIT 1', (int) $appointment_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				ARRAY_A
			);
		}

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * Gets every encounter belonging to one doctor, most recent first.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array List of decoded encounter rows.
	 */
	public static function for_doctor( $doctor_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d ORDER BY id DESC', (int) $doctor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Every encounter across every doctor, most recent first — for the admin
	 * "Encounters" list (replaces the old completed-appointments visit log).
	 * Each row is enriched with an 'appointment' sub-array (patient/doctor/
	 * clinic/date summary — see Appointments::notification_data()) so the
	 * list template doesn't need a separate lookup per row.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type int    $doctor_id  Only this doctor's encounters, when > 0. Default 0 (every doctor).
	 *     @type int    $patient_id Only this patient's encounters, when > 0. Default 0 (every patient).
	 *     @type string $status     STATUS_OPEN or STATUS_CLOSED, or '' for both. Default ''.
	 *     @type string $date_from  'YYYY-MM-DD', only encounters checked in on/after this date. Default ''.
	 *     @type string $date_to    'YYYY-MM-DD', only encounters checked in on/before this date. Default ''.
	 *     @type int    $number     Max rows to return. Default 200.
	 * }
	 * @return array List of decoded encounter rows, each with an added 'appointment' sub-array.
	 */
	public static function all_flat_for_admin( array $args = array() ) {
		global $wpdb;

		$doctor_id  = isset( $args['doctor_id'] ) ? (int) $args['doctor_id'] : 0;
		$patient_id = isset( $args['patient_id'] ) ? (int) $args['patient_id'] : 0;
		$status     = isset( $args['status'] ) ? (string) $args['status'] : '';
		$date_from  = isset( $args['date_from'] ) ? (string) $args['date_from'] : '';
		$date_to    = isset( $args['date_to'] ) ? (string) $args['date_to'] : '';
		$number     = isset( $args['number'] ) ? (int) $args['number'] : 200;

		$where  = array();
		$params = array();

		if ( $doctor_id > 0 ) {
			$where[]  = 'doctor_id = %d';
			$params[] = $doctor_id;
		}

		if ( $patient_id > 0 ) {
			$where[]  = 'patient_id = %d';
			$params[] = $patient_id;
		}

		if ( in_array( $status, array( self::STATUS_OPEN, self::STATUS_CLOSED ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		if ( '' !== $date_from ) {
			$where[]  = 'checked_in_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		if ( '' !== $date_to ) {
			$where[]  = 'checked_in_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$params[]  = $number;

		$sql = $wpdb->prepare(
			'SELECT * FROM ' . self::table_name() . " $where_sql ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input; $where_sql built entirely from %d/%s placeholders above.
			$params
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return array_map(
			function ( $row ) {
				$encounter                = self::decode_row( $row );
				$encounter['appointment'] = Appointments::notification_data( $encounter['appointment_id'] );

				$problems                     = Encounter_Problems::for_encounter( $encounter['id'] );
				$encounter['problem_summary'] = ! empty( $problems ) ? implode( ', ', wp_list_pluck( $problems, 'description' ) ) : '';

				return $encounter;
			},
			$rows
		);
	}

	/**
	 * Whether an encounter is currently open.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return bool
	 */
	public static function is_open( $encounter_id ) {
		$encounter = self::find( $encounter_id );

		return null !== $encounter && self::STATUS_OPEN === $encounter['status'];
	}

	/**
	 * Decodes a raw DB row into the shape the rest of the codebase works with.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		return array(
			'id'             => (int) $row['id'],
			'appointment_id' => (int) $row['appointment_id'],
			'doctor_id'      => (int) $row['doctor_id'],
			'patient_id'     => (int) $row['patient_id'],
			'clinic_id'      => (int) $row['clinic_id'],
			'status'         => $row['status'],
			'legacy_note'    => $row['legacy_note'],
			'checked_in_at'  => $row['checked_in_at'],
			'checked_in_by'  => (int) $row['checked_in_by'],
			'closed_at'      => $row['closed_at'],
			'closed_by'      => null !== $row['closed_by'] ? (int) $row['closed_by'] : null,
		);
	}
}
