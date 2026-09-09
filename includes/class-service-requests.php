<?php
/**
 * "Book without a doctor" requests — a patient's submission for a Services
 * row that has requires_doctor = 0 (a Lab test, a Pharmacy order, etc.).
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Requests
 *
 * Deliberately not an Appointments row: every appointment in this plugin is
 * booked against a specific doctor's calendar (a date/time slot drawn from
 * that doctor's clinic schedule — see Clinics::slot_grid_for_date()), and
 * there's no such thing as a doctor-less schedule to pick a slot from. A
 * service request instead just captures who to contact and about what — the
 * clinic/reception follows up to actually schedule it (see Service_Requests
 * ::STATUS_* for how that follow-up is tracked). See
 * Service_Request_Handler for the AJAX endpoints that create/manage these.
 */
class Service_Requests {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_service_requests';

	const STATUS_PENDING   = 'pending';
	const STATUS_CONTACTED = 'contacted';
	const STATUS_COMPLETED = 'completed';
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
	 * Every status a request can be in, slug => label, in their natural
	 * lifecycle order — for the admin "Service Requests" list's status
	 * dropdown.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_PENDING   => __( 'Pending', 'doctor-ak-portal' ),
			self::STATUS_CONTACTED => __( 'Contacted', 'doctor-ak-portal' ),
			self::STATUS_COMPLETED => __( 'Completed', 'doctor-ak-portal' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Validates and sanitizes a patient's posted request form.
	 *
	 * @param array $posted Raw request array (e.g. $_POST, already a plain array).
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_from_request( array $posted ) {
		$service_id = isset( $posted['service_id'] ) ? absint( $posted['service_id'] ) : 0;
		$service    = $service_id > 0 ? Services::find_for_public_profile( $service_id ) : null;

		if ( ! $service ) {
			return new \WP_Error( 'doctor_ak_service_request_service_invalid', __( 'That service is no longer available.', 'doctor-ak-portal' ) );
		}

		if ( ! empty( $service['requires_doctor'] ) ) {
			return new \WP_Error( 'doctor_ak_service_request_requires_doctor', __( 'This service needs a doctor to be selected — please use the regular booking flow instead.', 'doctor-ak-portal' ) );
		}

		$name = isset( $posted['patient_name'] ) ? sanitize_text_field( wp_unslash( $posted['patient_name'] ) ) : '';

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_service_request_name_required', __( 'Please provide your name.', 'doctor-ak-portal' ) );
		}

		$phone = isset( $posted['patient_phone'] ) ? sanitize_text_field( wp_unslash( $posted['patient_phone'] ) ) : '';

		if ( '' === $phone || ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $phone ) ) {
			return new \WP_Error( 'doctor_ak_service_request_phone_invalid', __( 'Please provide a valid phone number so we can contact you.', 'doctor-ak-portal' ) );
		}

		$email = isset( $posted['patient_email'] ) ? sanitize_email( wp_unslash( $posted['patient_email'] ) ) : '';

		if ( '' !== $email && ! is_email( $email ) ) {
			return new \WP_Error( 'doctor_ak_service_request_email_invalid', __( 'Please provide a valid email address.', 'doctor-ak-portal' ) );
		}

		$notes = isset( $posted['notes'] ) ? sanitize_textarea_field( wp_unslash( $posted['notes'] ) ) : '';

		return array(
			'service_id'    => $service_id,
			'service_name'  => $service['name'],
			'doctor_id'     => $service['doctor_id'],
			'patient_name'  => $name,
			'patient_phone' => $phone,
			'patient_email' => $email,
			'notes'         => $notes,
		);
	}

	/**
	 * Creates a new service request.
	 *
	 * @param array $fields     Sanitized fields, see sanitize_from_request().
	 * @param int   $patient_id Logged-in patient's user ID, or 0 for a guest submission.
	 * @return int|false New row ID, or false on failure.
	 */
	public static function create( array $fields, $patient_id = 0 ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'service_id'    => (int) $fields['service_id'],
				'service_name'  => $fields['service_name'],
				'doctor_id'     => (int) $fields['doctor_id'],
				'patient_id'    => (int) $patient_id,
				'patient_name'  => $fields['patient_name'],
				'patient_phone' => $fields['patient_phone'],
				'patient_email' => $fields['patient_email'],
				'notes'         => $fields['notes'],
				'status'        => self::STATUS_PENDING,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Finds a single request by ID.
	 *
	 * @param int $id Row ID.
	 * @return array|null Decoded row, or null if not found.
	 */
	public static function find( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * Updates a request's status, for the admin "Service Requests" list.
	 *
	 * @param int    $id     Row ID.
	 * @param string $status One of self::statuses()'s keys.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;

		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return false;
		}

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Deletes a request.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		return false !== $wpdb->delete( self::table_name(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Every request, newest first, for the admin "Service Requests" list.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type int $number Max rows to return. Default 200.
	 * }
	 * @return array List of decoded rows.
	 */
	public static function all_flat_for_admin( array $args = array() ) {
		global $wpdb;

		$number = isset( $args['number'] ) ? (int) $args['number'] : 200;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY created_at DESC LIMIT %d', $number ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Number of requests still awaiting a first follow-up, for the admin
	 * sidebar's nav badge (mirrors the "Doctor Requests" pending-count badge).
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE status = %s', self::STATUS_PENDING ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
		);
	}

	/**
	 * Decodes a raw DB row: casts IDs, adds a human-readable status label.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		$statuses = self::statuses();
		$status   = (string) $row['status'];

		return array(
			'id'            => (int) $row['id'],
			'service_id'    => (int) $row['service_id'],
			'service_name'  => $row['service_name'],
			'doctor_id'     => (int) $row['doctor_id'],
			'patient_id'    => (int) $row['patient_id'],
			'patient_name'  => $row['patient_name'],
			'patient_phone' => $row['patient_phone'],
			'patient_email' => $row['patient_email'],
			'notes'         => $row['notes'],
			'status'        => $status,
			'status_label'  => isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status,
			'created_at'    => $row['created_at'],
		);
	}
}
