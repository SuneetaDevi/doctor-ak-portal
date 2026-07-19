<?php
/**
 * Doctor services — bookable offerings (e.g. "OPD Consultation") each with
 * its own category, charge, and duration.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Services
 *
 * A doctor can offer several services, each tagged 'clinic' (onsite) or
 * 'video' (online), with its own charge and duration. Patients pick one
 * when booking (see Booking_Handler), and its charge becomes the amount
 * charged through Swich for paid video bookings (see Swich_Payment).
 * Storage mirrors Clinics: a plain DB table, no post type.
 */
class Services {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_services';

	const TYPE_CLINIC = 'clinic';
	const TYPE_VIDEO  = 'video';

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
	 * Validates and sanitizes a service's fields from a request.
	 *
	 * @param array $posted Raw request array (e.g. $_POST, already a plain array).
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_fields_from_request( array $posted ) {
		$name = isset( $posted['name'] ) ? sanitize_text_field( wp_unslash( $posted['name'] ) ) : '';

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_service_name_required', __( 'Please provide a name for this service.', 'doctor-ak-portal' ) );
		}

		// Services are onsite (clinic) only — video consultations use a
		// fixed per-doctor price instead (see Video_Pricing).
		$type = self::TYPE_CLINIC;

		$category = isset( $posted['category'] ) ? sanitize_key( wp_unslash( $posted['category'] ) ) : '';

		if ( '' !== $category && ! Specializations::is_valid( $category ) ) {
			return new \WP_Error( 'doctor_ak_service_category_invalid', __( 'Please choose a valid category.', 'doctor-ak-portal' ) );
		}

		$charge = isset( $posted['charge'] ) ? (float) wp_unslash( $posted['charge'] ) : 0;

		if ( $charge < 0 ) {
			return new \WP_Error( 'doctor_ak_service_charge_invalid', __( 'Please provide a valid charge.', 'doctor-ak-portal' ) );
		}

		$duration_minutes = isset( $posted['duration_minutes'] ) ? absint( wp_unslash( $posted['duration_minutes'] ) ) : 0;

		if ( $duration_minutes < 0 || $duration_minutes > 480 ) {
			return new \WP_Error( 'doctor_ak_service_duration_invalid', __( 'Please provide a valid duration in minutes.', 'doctor-ak-portal' ) );
		}

		$active = ! empty( $posted['active'] );

		return array(
			'type'              => $type,
			'name'              => $name,
			'category'          => $category,
			'charge'            => number_format( $charge, 2, '.', '' ),
			'duration_minutes'  => $duration_minutes,
			'active'            => $active,
		);
	}

	/**
	 * Creates a new service row.
	 *
	 * @param int   $doctor_id Doctor's user ID.
	 * @param array $fields    Sanitized service fields.
	 * @return int|false New service ID, or false on failure.
	 */
	public static function create( $doctor_id, array $fields ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'         => (int) $doctor_id,
				'type'              => $fields['type'],
				'name'              => $fields['name'],
				'category'          => $fields['category'],
				'charge'            => $fields['charge'],
				'duration_minutes'  => $fields['duration_minutes'],
				'active'            => $fields['active'] ? 1 : 0,
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates an existing service row.
	 *
	 * @param int      $service_id Service ID.
	 * @param array    $fields     Sanitized service fields.
	 * @param int|null $doctor_id  If given, the update only applies when the service belongs to this doctor; pass null to skip the check (admin context).
	 * @return bool
	 */
	public static function update( $service_id, array $fields, $doctor_id = null ) {
		global $wpdb;

		$where       = array( 'id' => (int) $service_id );
		$where_types = array( '%d' );

		if ( null !== $doctor_id ) {
			$where['doctor_id'] = (int) $doctor_id;
			$where_types[]      = '%d';
		}

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'type'              => $fields['type'],
				'name'              => $fields['name'],
				'category'          => $fields['category'],
				'charge'            => $fields['charge'],
				'duration_minutes'  => $fields['duration_minutes'],
				'active'            => $fields['active'] ? 1 : 0,
				'updated_at'        => current_time( 'mysql' ),
			),
			$where,
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
			$where_types
		);

		return false !== $updated;
	}

	/**
	 * Deletes a service row.
	 *
	 * @param int      $service_id Service ID.
	 * @param int|null $doctor_id  If given, only deletes when the service belongs to this doctor; pass null to skip the check (admin context).
	 * @return bool
	 */
	public static function delete( $service_id, $doctor_id = null ) {
		global $wpdb;

		$where       = array( 'id' => (int) $service_id );
		$where_types = array( '%d' );

		if ( null !== $doctor_id ) {
			$where['doctor_id'] = (int) $doctor_id;
			$where_types[]      = '%d';
		}

		return false !== $wpdb->delete( self::table_name(), $where, $where_types );
	}

	/**
	 * Finds a single service by ID.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null Decoded service row, or null if not found.
	 */
	public static function find( $service_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $service_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * Gets every service belonging to one doctor, optionally filtered by type.
	 *
	 * @param int         $doctor_id Doctor's user ID.
	 * @param string|null $type      'clinic', 'video', or null for both.
	 * @return array List of decoded service rows.
	 */
	public static function get_for_doctor( $doctor_id, $type = null ) {
		global $wpdb;

		if ( null !== $type ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d AND type = %s ORDER BY id ASC', (int) $doctor_id, $type ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d ORDER BY id ASC', (int) $doctor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
				ARRAY_A
			);
		}

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Gets a doctor's active services for a given type, for the booking
	 * form's service picker.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $type      'clinic' or 'video'.
	 * @return array
	 */
	public static function active_for_doctor( $doctor_id, $type ) {
		return array_values(
			array_filter(
				self::get_for_doctor( $doctor_id, $type ),
				function ( $service ) {
					return ! empty( $service['active'] );
				}
			)
		);
	}

	/**
	 * Total number of services across every doctor.
	 *
	 * @return int
	 */
	public static function total_count() {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
	}

	/**
	 * Gets every service across every doctor, for the admin "Services"
	 * table, joined with the doctor's display name/email.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type int $number Max rows to return. Default 200.
	 * }
	 * @return array List of decoded service rows, each with an added 'doctor' sub-array (id/name/email).
	 */
	public static function all_flat_for_admin( array $args = array() ) {
		global $wpdb;

		$number = isset( $args['number'] ) ? (int) $args['number'] : 200;

		$sql = $wpdb->prepare(
			"SELECT s.*, u.display_name AS doctor_display_name, u.user_email AS doctor_email
			FROM " . self::table_name() . " s
			INNER JOIN {$wpdb->users} u ON u.ID = s.doctor_id
			ORDER BY s.id DESC
			LIMIT %d",
			$number
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names, not user input.

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return array_map(
			function ( $row ) {
				$service = self::decode_row( $row );

				$doctor    = get_userdata( $service['doctor_id'] );
				$full_name = $doctor ? trim( $doctor->first_name . ' ' . $doctor->last_name ) : '';

				$service['doctor'] = array(
					'id'    => $service['doctor_id'],
					'name'  => '' !== $full_name ? $full_name : $row['doctor_display_name'],
					'email' => $row['doctor_email'],
				);

				return $service;
			},
			$rows
		);
	}

	/**
	 * Decodes a raw DB row into the shape the rest of the codebase works
	 * with: casts IDs/numbers, adds a human-readable category label.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		$categories = Specializations::get_all();
		$category   = (string) $row['category'];

		return array(
			'id'                => (int) $row['id'],
			'doctor_id'         => (int) $row['doctor_id'],
			'type'              => $row['type'],
			'name'              => $row['name'],
			'category'          => $category,
			'category_label'    => isset( $categories[ $category ] ) ? $categories[ $category ] : '',
			'charge'            => (float) $row['charge'],
			'duration_minutes'  => (int) $row['duration_minutes'],
			'active'            => ! empty( $row['active'] ),
		);
	}
}
