<?php
/**
 * Problems (diagnoses) recorded against a clinical encounter.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounter_Problems
 *
 * Repeatable rows scoped to one Encounters row.
 */
class Encounter_Problems {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_encounter_problems';

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
	 * Adds a problem row to an encounter.
	 *
	 * @param int    $encounter_id Encounter ID.
	 * @param string $description  Diagnosis/problem text.
	 * @param string $notes        Optional free-text notes.
	 * @return int|false New problem row ID, or false on failure.
	 */
	public static function add( $encounter_id, $description, $notes = '' ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'encounter_id' => (int) $encounter_id,
				'description'  => $description,
				'notes'        => $notes,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Deletes a problem row, scoped to the encounter it must belong to.
	 *
	 * @param int $problem_id   Problem row ID.
	 * @param int $encounter_id Encounter ID it must belong to.
	 * @return bool
	 */
	public static function delete( $problem_id, $encounter_id ) {
		global $wpdb;

		return false !== $wpdb->delete(
			self::table_name(),
			array(
				'id'           => (int) $problem_id,
				'encounter_id' => (int) $encounter_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Every problem row for one encounter, oldest first.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return array List of `array( 'id', 'description', 'notes' )`.
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
					'id'          => (int) $row['id'],
					'description' => $row['description'],
					'notes'       => $row['notes'],
				);
			},
			$rows
		);
	}
}
