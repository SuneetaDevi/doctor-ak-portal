<?php
/**
 * Prescription lines recorded against a clinical encounter.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounter_Prescriptions
 *
 * Repeatable rows scoped to one Encounters row. Each row snapshots the
 * medicine's name at the time it was prescribed (medicine_name), separate
 * from the live Medicines list (medicine_id) — so an edited/deleted list
 * entry never changes what an already-issued prescription shows.
 * medicine_id = 0 means the doctor typed the name directly instead of
 * picking from the list.
 */
class Encounter_Prescriptions {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_encounter_prescriptions';

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
	 * Adds a prescription row to an encounter.
	 *
	 * @param int   $encounter_id Encounter ID.
	 * @param array $fields {
	 *     @type int    $medicine_id   Medicines row ID, or 0 if free-typed.
	 *     @type string $medicine_name Medicine name (snapshot).
	 *     @type string $dosage        E.g. "500mg".
	 *     @type string $frequency     E.g. "1-0-1".
	 *     @type string $duration      E.g. "5 days".
	 *     @type string $instructions  E.g. "after meals".
	 * }
	 * @return int|false New prescription row ID, or false on failure.
	 */
	public static function add( $encounter_id, array $fields ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'encounter_id'  => (int) $encounter_id,
				'medicine_id'   => (int) $fields['medicine_id'],
				'medicine_name' => $fields['medicine_name'],
				'dosage'        => $fields['dosage'],
				'frequency'     => $fields['frequency'],
				'duration'      => $fields['duration'],
				'instructions'  => $fields['instructions'],
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates a prescription row, scoped to the encounter it must belong to.
	 *
	 * @param int   $prescription_id Prescription row ID.
	 * @param int   $encounter_id    Encounter ID it must belong to.
	 * @param array $fields          Same shape as add()'s $fields.
	 * @return bool
	 */
	public static function update( $prescription_id, $encounter_id, array $fields ) {
		global $wpdb;

		return false !== $wpdb->update(
			self::table_name(),
			array(
				'medicine_id'   => (int) $fields['medicine_id'],
				'medicine_name' => $fields['medicine_name'],
				'dosage'        => $fields['dosage'],
				'frequency'     => $fields['frequency'],
				'duration'      => $fields['duration'],
				'instructions'  => $fields['instructions'],
			),
			array(
				'id'           => (int) $prescription_id,
				'encounter_id' => (int) $encounter_id,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * Deletes a prescription row, scoped to the encounter it must belong to.
	 *
	 * @param int $prescription_id Prescription row ID.
	 * @param int $encounter_id    Encounter ID it must belong to.
	 * @return bool
	 */
	public static function delete( $prescription_id, $encounter_id ) {
		global $wpdb;

		return false !== $wpdb->delete(
			self::table_name(),
			array(
				'id'           => (int) $prescription_id,
				'encounter_id' => (int) $encounter_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Every prescription row for one encounter, oldest first.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return array
	 */
	public static function for_encounter( $encounter_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE encounter_id = %d ORDER BY id ASC', (int) $encounter_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'id'            => (int) $row['id'],
					'medicine_id'   => (int) $row['medicine_id'],
					'medicine_name' => $row['medicine_name'],
					'dosage'        => $row['dosage'],
					'frequency'     => $row['frequency'],
					'duration'      => $row['duration'],
					'instructions'  => $row['instructions'],
				);
			},
			$rows
		);
	}
}
