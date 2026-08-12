<?php
/**
 * Extra bill line items added during a clinical encounter.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounter_Bill_Items
 *
 * Repeatable rows scoped to one Encounters row — on-the-spot charges (e.g.
 * a procedure done during the visit) added on top of the appointment's own
 * original charge. The encounter's total bill = appointment charge + sum of
 * these rows (see Encounter_Bill_Pdf, which reads the appointment charge
 * directly rather than duplicating it into a row here).
 */
class Encounter_Bill_Items {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_encounter_bill_items';

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
	 * Adds a bill line item to an encounter.
	 *
	 * @param int    $encounter_id Encounter ID.
	 * @param string $description  Line item description.
	 * @param float  $amount       Line item amount.
	 * @return int|false New bill item row ID, or false on failure.
	 */
	public static function add( $encounter_id, $description, $amount ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'encounter_id' => (int) $encounter_id,
				'description'  => $description,
				'amount'       => number_format( (float) $amount, 2, '.', '' ),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Deletes a bill item row, scoped to the encounter it must belong to.
	 *
	 * @param int $item_id      Bill item row ID.
	 * @param int $encounter_id Encounter ID it must belong to.
	 * @return bool
	 */
	public static function delete( $item_id, $encounter_id ) {
		global $wpdb;

		return false !== $wpdb->delete(
			self::table_name(),
			array(
				'id'           => (int) $item_id,
				'encounter_id' => (int) $encounter_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Every bill item row for one encounter, oldest first.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return array List of `array( 'id', 'description', 'amount' )`.
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
					'amount'      => (float) $row['amount'],
				);
			},
			$rows
		);
	}

	/**
	 * Sum of every bill item's amount for one encounter.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return float
	 */
	public static function total_for_encounter( $encounter_id ) {
		global $wpdb;

		return (float) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(amount), 0) FROM ' . self::table_name() . ' WHERE encounter_id = %d', (int) $encounter_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
		);
	}
}
