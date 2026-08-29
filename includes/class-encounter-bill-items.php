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
 *
 * Each row can carry its own discount (0-100%, e.g. a loyalty/staff
 * discount on one particular service added to the bill) — decode_row()
 * applies it, so every consumer that reads a row's 'amount' (this class's
 * own total_for_encounter(), Encounter_Bill_Pdf, Revenue_Ledger) already
 * gets the final, already-discounted figure without needing its own
 * discount-aware logic; the pre-discount price is separately available as
 * 'original_amount' for the add/edit UI to show what was waived.
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
	 * @param int    $encounter_id     Encounter ID.
	 * @param string $description      Line item description.
	 * @param float  $amount           Line item's pre-discount amount.
	 * @param float  $discount_percent Discount to apply to this line, 0-100. Clamped into range.
	 * @return int|false New bill item row ID, or false on failure.
	 */
	public static function add( $encounter_id, $description, $amount, $discount_percent = 0 ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'encounter_id'     => (int) $encounter_id,
				'description'      => $description,
				'amount'           => number_format( (float) $amount, 2, '.', '' ),
				'discount_percent' => number_format( self::clamp_discount( $discount_percent ), 2, '.', '' ),
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Clamps a posted discount percent into the valid 0-100 range.
	 *
	 * @param float $discount_percent Raw value.
	 * @return float
	 */
	private static function clamp_discount( $discount_percent ) {
		return max( 0, min( 100, (float) $discount_percent ) );
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
	 * @return array List of decoded rows, see decode_row().
	 */
	public static function for_encounter( $encounter_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE encounter_id = %d ORDER BY id ASC', (int) $encounter_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Sum of every bill item's final (already-discounted) amount for one
	 * encounter.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return float
	 */
	public static function total_for_encounter( $encounter_id ) {
		global $wpdb;

		return (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount * (1 - discount_percent / 100)), 0) FROM ' . self::table_name() . ' WHERE encounter_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				(int) $encounter_id
			)
		);
	}

	/**
	 * Decodes a raw DB row: casts numbers, and resolves 'amount' to the
	 * final, already-discounted figure (with the original pre-discount
	 * price kept separately as 'original_amount') — see this class's own
	 * docblock for why.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		$original_amount  = (float) $row['amount'];
		$discount_percent = isset( $row['discount_percent'] ) ? (float) $row['discount_percent'] : 0.0;
		$final_amount     = round( $original_amount * ( 1 - $discount_percent / 100 ), 2 );

		return array(
			'id'               => (int) $row['id'],
			'description'      => $row['description'],
			'original_amount'  => $original_amount,
			'discount_percent' => $discount_percent,
			'amount'           => $final_amount,
		);
	}
}
