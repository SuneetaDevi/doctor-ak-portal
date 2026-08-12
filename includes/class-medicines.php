<?php
/**
 * Doctor-managed medicines master list, used when writing a prescription.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Medicines
 *
 * A doctor can maintain a list of medicines they commonly prescribe (name +
 * default dosage), picked from when adding a prescription row during an
 * encounter — dosage/frequency/duration/instructions are still editable per
 * prescription line. Storage mirrors Services: a plain DB table, no post
 * type. A prescription row that doesn't match any list entry (an off-list/
 * urgent medicine, typed in directly) stores medicine_id = 0 instead.
 */
class Medicines {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_medicines';

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
	 * Validates and sanitizes a medicine's fields from a request.
	 *
	 * @param array $posted Raw request array (e.g. $_POST, already a plain array).
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_fields_from_request( array $posted ) {
		$name = isset( $posted['name'] ) ? sanitize_text_field( wp_unslash( $posted['name'] ) ) : '';

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_medicine_name_required', __( 'Please provide a name for this medicine.', 'doctor-ak-portal' ) );
		}

		$default_dosage = isset( $posted['default_dosage'] ) ? sanitize_text_field( wp_unslash( $posted['default_dosage'] ) ) : '';
		$active         = ! empty( $posted['active'] );

		return array(
			'name'           => $name,
			'default_dosage' => $default_dosage,
			'active'         => $active,
		);
	}

	/**
	 * Creates a new medicine row.
	 *
	 * @param int   $doctor_id Doctor's user ID.
	 * @param array $fields    Sanitized medicine fields.
	 * @return int|false New medicine ID, or false on failure.
	 */
	public static function create( $doctor_id, array $fields ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'      => (int) $doctor_id,
				'name'           => $fields['name'],
				'default_dosage' => $fields['default_dosage'],
				'active'         => $fields['active'] ? 1 : 0,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates an existing medicine row.
	 *
	 * @param int      $medicine_id Medicine ID.
	 * @param array    $fields      Sanitized medicine fields.
	 * @param int|null $doctor_id   If given, the update only applies when the medicine belongs to this doctor; pass null to skip the check (admin context).
	 * @return bool
	 */
	public static function update( $medicine_id, array $fields, $doctor_id = null ) {
		global $wpdb;

		$where       = array( 'id' => (int) $medicine_id );
		$where_types = array( '%d' );

		if ( null !== $doctor_id ) {
			$where['doctor_id'] = (int) $doctor_id;
			$where_types[]      = '%d';
		}

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'name'           => $fields['name'],
				'default_dosage' => $fields['default_dosage'],
				'active'         => $fields['active'] ? 1 : 0,
				'updated_at'     => current_time( 'mysql' ),
			),
			$where,
			array( '%s', '%s', '%d', '%s' ),
			$where_types
		);

		return false !== $updated;
	}

	/**
	 * Deletes a medicine row.
	 *
	 * @param int      $medicine_id Medicine ID.
	 * @param int|null $doctor_id   If given, only deletes when the medicine belongs to this doctor; pass null to skip the check (admin context).
	 * @return bool
	 */
	public static function delete( $medicine_id, $doctor_id = null ) {
		global $wpdb;

		$where       = array( 'id' => (int) $medicine_id );
		$where_types = array( '%d' );

		if ( null !== $doctor_id ) {
			$where['doctor_id'] = (int) $doctor_id;
			$where_types[]      = '%d';
		}

		return false !== $wpdb->delete( self::table_name(), $where, $where_types );
	}

	/**
	 * Finds a single medicine by ID.
	 *
	 * @param int $medicine_id Medicine ID.
	 * @return array|null Decoded medicine row, or null if not found.
	 */
	public static function find( $medicine_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $medicine_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * Gets every medicine belonging to one doctor.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array List of decoded medicine rows.
	 */
	public static function get_for_doctor( $doctor_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d ORDER BY name ASC', (int) $doctor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Gets a doctor's active medicines, for the prescription row's medicine
	 * picker.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array
	 */
	public static function active_for_doctor( $doctor_id ) {
		return array_values(
			array_filter(
				self::get_for_doctor( $doctor_id ),
				function ( $medicine ) {
					return ! empty( $medicine['active'] );
				}
			)
		);
	}

	/**
	 * Gets every medicine across every doctor, for the admin "Medicines"
	 * table, joined with the doctor's display name/email.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type int $number    Max rows to return. Default 200.
	 *     @type int $doctor_id Only this doctor's medicines, when > 0. Default 0 (every doctor).
	 * }
	 * @return array List of decoded medicine rows, each with an added 'doctor' sub-array (id/name/email).
	 */
	public static function all_flat_for_admin( array $args = array() ) {
		global $wpdb;

		$number    = isset( $args['number'] ) ? (int) $args['number'] : 200;
		$doctor_id = isset( $args['doctor_id'] ) ? (int) $args['doctor_id'] : 0;

		$where  = $doctor_id > 0 ? 'WHERE m.doctor_id = %d' : '';
		$params = $doctor_id > 0 ? array( $doctor_id, $number ) : array( $number );

		$sql = $wpdb->prepare(
			"SELECT m.*, u.display_name AS doctor_display_name, u.user_email AS doctor_email
			FROM " . self::table_name() . " m
			INNER JOIN {$wpdb->users} u ON u.ID = m.doctor_id
			$where
			ORDER BY m.id DESC
			LIMIT %d",
			$params
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names, not user input.

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return array_map(
			function ( $row ) {
				$medicine = self::decode_row( $row );

				$doctor    = get_userdata( $medicine['doctor_id'] );
				$full_name = $doctor ? trim( $doctor->first_name . ' ' . $doctor->last_name ) : '';

				$medicine['doctor'] = array(
					'id'    => $medicine['doctor_id'],
					'name'  => '' !== $full_name ? $full_name : $row['doctor_display_name'],
					'email' => $row['doctor_email'],
				);

				return $medicine;
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
			'id'             => (int) $row['id'],
			'doctor_id'      => (int) $row['doctor_id'],
			'name'           => $row['name'],
			'default_dosage' => $row['default_dosage'],
			'active'         => ! empty( $row['active'] ),
		);
	}
}
