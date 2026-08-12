<?php
/**
 * Uploaded report files (lab results, scans, etc.) attached to a clinical
 * encounter.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encounter_Reports
 *
 * Repeatable rows scoped to one Encounters row — each just points at a real
 * Media Library attachment (uploaded via Encounter_Report_Uploader), so the
 * file itself is stored/served the same way any other WordPress upload is;
 * this table only tracks which attachments belong to which encounter.
 */
class Encounter_Reports {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_encounter_reports';

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
	 * Attaches an already-uploaded Media Library file to an encounter.
	 *
	 * @param int $encounter_id  Encounter ID.
	 * @param int $attachment_id Media Library attachment ID.
	 * @param int $uploaded_by   User ID who uploaded it.
	 * @return int|false New report row ID, or false on failure.
	 */
	public static function add( $encounter_id, $attachment_id, $uploaded_by ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'encounter_id'  => (int) $encounter_id,
				'attachment_id' => (int) $attachment_id,
				'uploaded_by'   => (int) $uploaded_by,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Deletes a report row (and its underlying attachment) — scoped to the
	 * encounter it must belong to.
	 *
	 * @param int $report_id    Report row ID.
	 * @param int $encounter_id Encounter ID it must belong to.
	 * @return bool
	 */
	public static function delete( $report_id, $encounter_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE id = %d AND encounter_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				(int) $report_id,
				(int) $encounter_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return false;
		}

		wp_delete_attachment( (int) $row['attachment_id'], true );

		return false !== $wpdb->delete(
			self::table_name(),
			array(
				'id'           => (int) $report_id,
				'encounter_id' => (int) $encounter_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Every report row for one encounter, oldest first.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return array List of `array( 'id', 'attachment_id', 'file_name', 'url', 'uploaded_at' )`.
	 */
	public static function for_encounter( $encounter_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE encounter_id = %d ORDER BY id ASC', (int) $encounter_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_values(
			array_filter(
				array_map(
					function ( $row ) {
						$attachment_id = (int) $row['attachment_id'];
						$url           = wp_get_attachment_url( $attachment_id );

						if ( ! $url ) {
							return null;
						}

						return array(
							'id'            => (int) $row['id'],
							'attachment_id' => $attachment_id,
							'file_name'     => basename( get_attached_file( $attachment_id ) ),
							'url'           => $url,
							'uploaded_at'   => $row['created_at'],
						);
					},
					$rows
				)
			)
		);
	}
}
