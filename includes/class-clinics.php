<?php
/**
 * Doctor clinics (physical locations and video-consultation entries) and
 * their per-clinic weekly session schedules.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinics
 *
 * A doctor can have several clinics (physical locations they sit at, plus
 * an optional "video" entry for online consultations), each with its own
 * weekly session schedule: which days it's open and, for each open day, a
 * start/end time and an appointment slot duration in minutes. Replaces the
 * old single `doctor_ak_clinic_location` string, single `doctor_ak_availability`
 * schedule, and `doctor_ak_video_consultation` boolean (see Db_Installer's
 * one-time migration of existing doctors' data into this shape).
 */
class Clinics {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_clinics';

	/**
	 * Physical clinic location type.
	 *
	 * @var string
	 */
	const TYPE_PHYSICAL = 'physical';

	/**
	 * Video-consultation "clinic" type.
	 *
	 * @var string
	 */
	const TYPE_VIDEO = 'video';

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
	 * Days of the week, in display order, keyed by slug. Delegates to
	 * Availability so both classes agree on day slugs/labels/order.
	 *
	 * @return array
	 */
	public static function session_days() {
		return Availability::days();
	}

	/**
	 * Returns a sessions structure with every day disabled.
	 *
	 * @return array
	 */
	public static function empty_sessions() {
		$sessions = array();

		foreach ( self::session_days() as $slug => $label ) {
			$sessions[ $slug ] = array(
				'enabled'               => false,
				'start'                 => '',
				'end'                   => '',
				'slot_duration_minutes' => 0,
			);
		}

		return $sessions;
	}

	/**
	 * Validates and sanitizes the `sessions` portion of a request.
	 *
	 * Expects the shape produced by fields named
	 * `sessions[day][enabled|start|end|slot_duration_minutes]`.
	 *
	 * @param array $posted_sessions Raw `sessions` sub-array from the request.
	 * @return array|\WP_Error Sanitized sessions, or WP_Error on invalid input.
	 */
	public static function sanitize_sessions_from_request( array $posted_sessions ) {
		$sessions = array();

		foreach ( self::session_days() as $slug => $label ) {
			$day = isset( $posted_sessions[ $slug ] ) && is_array( $posted_sessions[ $slug ] )
				? $posted_sessions[ $slug ]
				: array();

			$enabled = ! empty( $day['enabled'] );

			if ( ! $enabled ) {
				$sessions[ $slug ] = array(
					'enabled'               => false,
					'start'                 => '',
					'end'                   => '',
					'slot_duration_minutes' => 0,
				);
				continue;
			}

			$start = isset( $day['start'] ) ? sanitize_text_field( wp_unslash( $day['start'] ) ) : '';
			$end   = isset( $day['end'] ) ? sanitize_text_field( wp_unslash( $day['end'] ) ) : '';

			if ( ! self::is_valid_time( $start ) || ! self::is_valid_time( $end ) ) {
				/* translators: %s: day of the week. */
				return new \WP_Error( 'doctor_ak_invalid_session_time', sprintf( __( 'Please provide a valid start and end time for %s.', 'doctor-ak-portal' ), $label ) );
			}

			if ( $start >= $end ) {
				/* translators: %s: day of the week. */
				return new \WP_Error( 'doctor_ak_invalid_session_range', sprintf( __( 'The end time must be after the start time for %s.', 'doctor-ak-portal' ), $label ) );
			}

			$slot_duration = isset( $day['slot_duration_minutes'] ) ? absint( wp_unslash( $day['slot_duration_minutes'] ) ) : 0;

			if ( $slot_duration < 5 || $slot_duration > 240 ) {
				/* translators: %s: day of the week. */
				return new \WP_Error( 'doctor_ak_invalid_slot_duration', sprintf( __( 'Please provide a slot duration between 5 and 240 minutes for %s.', 'doctor-ak-portal' ), $label ) );
			}

			$sessions[ $slug ] = array(
				'enabled'               => true,
				'start'                 => $start,
				'end'                   => $end,
				'slot_duration_minutes' => $slot_duration,
			);
		}

		return $sessions;
	}

	/**
	 * Validates and sanitizes a clinic's own fields (not its sessions).
	 *
	 * @param array  $posted Raw request array (e.g. $_POST, already a plain array).
	 * @param string $type   'physical' or 'video'.
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_clinic_fields_from_request( array $posted, $type ) {
		$type = self::TYPE_VIDEO === $type ? self::TYPE_VIDEO : self::TYPE_PHYSICAL;

		$name = isset( $posted['name'] ) ? sanitize_text_field( wp_unslash( $posted['name'] ) ) : '';

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_clinic_name_required', __( 'Please provide a name for this clinic.', 'doctor-ak-portal' ) );
		}

		$address = isset( $posted['address'] ) ? sanitize_text_field( wp_unslash( $posted['address'] ) ) : '';

		if ( self::TYPE_PHYSICAL === $type && '' === $address ) {
			return new \WP_Error( 'doctor_ak_clinic_address_required', __( 'Please provide this clinic\'s address.', 'doctor-ak-portal' ) );
		}

		if ( self::TYPE_VIDEO === $type ) {
			$address = '';
		}

		$phone = isset( $posted['phone'] ) ? sanitize_text_field( wp_unslash( $posted['phone'] ) ) : '';

		if ( '' !== $phone && ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $phone ) ) {
			return new \WP_Error( 'doctor_ak_clinic_phone_invalid', __( 'Please provide a valid phone number.', 'doctor-ak-portal' ) );
		}

		$contact_email = isset( $posted['contact_email'] ) ? sanitize_email( wp_unslash( $posted['contact_email'] ) ) : '';

		if ( '' !== $contact_email && ! is_email( $contact_email ) ) {
			return new \WP_Error( 'doctor_ak_clinic_email_invalid', __( 'Please provide a valid contact email address.', 'doctor-ak-portal' ) );
		}

		return array(
			'type'          => $type,
			'name'          => $name,
			'address'       => $address,
			'phone'         => $phone,
			'contact_email' => $contact_email,
		);
	}

	/**
	 * Creates a new clinic row.
	 *
	 * @param int      $doctor_id Doctor's user ID.
	 * @param array    $fields    Sanitized clinic fields (type/name/address/phone/contact_email).
	 * @param array    $sessions  Sanitized weekly sessions.
	 * @param int|null $actor_id  Unused placeholder kept for signature symmetry with update()/delete(); pass null.
	 * @return int|false New clinic ID, or false on failure.
	 */
	public static function create( $doctor_id, array $fields, array $sessions, $actor_id = null ) {
		global $wpdb;

		unset( $actor_id );

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'     => (int) $doctor_id,
				'type'          => $fields['type'],
				'name'          => $fields['name'],
				'address'       => $fields['address'],
				'phone'         => $fields['phone'],
				'contact_email' => $fields['contact_email'],
				'sessions'      => wp_json_encode( $sessions ),
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates an existing clinic row.
	 *
	 * @param int      $clinic_id Clinic ID.
	 * @param array    $fields    Sanitized clinic fields.
	 * @param array    $sessions  Sanitized weekly sessions.
	 * @param int|null $doctor_id If given, the update only applies when the clinic belongs to this doctor (ownership check); pass null to skip the check (admin context).
	 * @return bool
	 */
	public static function update( $clinic_id, array $fields, array $sessions, $doctor_id = null ) {
		global $wpdb;

		$where       = array( 'id' => (int) $clinic_id );
		$where_types = array( '%d' );

		if ( null !== $doctor_id ) {
			$where['doctor_id'] = (int) $doctor_id;
			$where_types[]      = '%d';
		}

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'type'          => $fields['type'],
				'name'          => $fields['name'],
				'address'       => $fields['address'],
				'phone'         => $fields['phone'],
				'contact_email' => $fields['contact_email'],
				'sessions'      => wp_json_encode( $sessions ),
				'updated_at'    => current_time( 'mysql' ),
			),
			$where,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			$where_types
		);

		return false !== $updated;
	}

	/**
	 * Deletes a clinic row.
	 *
	 * @param int      $clinic_id Clinic ID.
	 * @param int|null $doctor_id If given, only deletes when the clinic belongs to this doctor; pass null to skip the check (admin context).
	 * @return bool
	 */
	public static function delete( $clinic_id, $doctor_id = null ) {
		global $wpdb;

		$where       = array( 'id' => (int) $clinic_id );
		$where_types = array( '%d' );

		if ( null !== $doctor_id ) {
			$where['doctor_id'] = (int) $doctor_id;
			$where_types[]      = '%d';
		}

		return false !== $wpdb->delete( self::table_name(), $where, $where_types );
	}

	/**
	 * Finds a single clinic by ID.
	 *
	 * @param int $clinic_id Clinic ID.
	 * @return array|null Decoded clinic row, or null if not found.
	 */
	public static function find( $clinic_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $clinic_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * Gets every clinic belonging to one doctor.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array List of decoded clinic rows, oldest first.
	 */
	public static function get_for_doctor( $doctor_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d ORDER BY id ASC', (int) $doctor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Total number of clinics across every doctor, for the admin dashboard's
	 * "Total Clinics" stat card.
	 *
	 * @return int
	 */
	public static function total_count() {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
	}

	/**
	 * Computes every bookable time slot for a doctor on a given calendar
	 * date, from that weekday's session(s) of the given type — independent
	 * of which slots are already booked (see Appointments::available_slots()
	 * for that). Slot spacing follows each clinic's own configured
	 * slot_duration_minutes for that day; slots from multiple clinics of the
	 * same type on the same day are merged and de-duplicated.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $type      'clinic' or 'video' (booking-form type, not TYPE_PHYSICAL/TYPE_VIDEO).
	 * @param string $date      'YYYY-MM-DD'.
	 * @return array List of 'HH:MM' strings, sorted ascending.
	 */
	public static function slot_grid_for_date( $doctor_id, $type, $date ) {
		return self::slot_grid_from_clinics( self::get_for_doctor( $doctor_id ), $type, $date );
	}

	/**
	 * Same as slot_grid_for_date(), but takes an already-fetched clinics
	 * list instead of querying — for callers computing many dates at once
	 * (e.g. Appointments::month_availability_summary()) who fetch a
	 * doctor's clinics a single time up front instead of once per date.
	 *
	 * @param array  $clinics Decoded clinic rows, see get_for_doctor().
	 * @param string $type    'clinic' or 'video'.
	 * @param string $date    'YYYY-MM-DD'.
	 * @return array List of 'HH:MM' strings, sorted ascending.
	 */
	public static function slot_grid_from_clinics( array $clinics, $type, $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return array();
		}

		$clinic_type = self::TYPE_VIDEO === $type ? self::TYPE_VIDEO : self::TYPE_PHYSICAL;
		$weekday     = strtolower( gmdate( 'l', strtotime( $date ) ) );
		$slots       = array();

		foreach ( $clinics as $clinic ) {
			if ( $clinic['type'] !== $clinic_type ) {
				continue;
			}

			$day = isset( $clinic['sessions'][ $weekday ] ) ? $clinic['sessions'][ $weekday ] : null;

			if ( ! $day || empty( $day['enabled'] ) ) {
				continue;
			}

			$slot_duration = (int) $day['slot_duration_minutes'];

			if ( $slot_duration <= 0 ) {
				continue;
			}

			$start_minutes = self::time_to_minutes( $day['start'] );
			$end_minutes   = self::time_to_minutes( $day['end'] );

			for ( $minutes = $start_minutes; $minutes + $slot_duration <= $end_minutes; $minutes += $slot_duration ) {
				$slots[] = sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
			}
		}

		$slots = array_values( array_unique( $slots ) );
		sort( $slots );

		return $slots;
	}

	/**
	 * Converts an 'HH:MM' time string to minutes since midnight.
	 *
	 * @param string $time 'HH:MM'.
	 * @return int
	 */
	private static function time_to_minutes( $time ) {
		$parts = array_map( 'intval', explode( ':', $time ) );

		return ( isset( $parts[0] ) ? $parts[0] * 60 : 0 ) + ( isset( $parts[1] ) ? $parts[1] : 0 );
	}

	/**
	 * Whether a doctor has at least one active (has an enabled day) video
	 * clinic entry — replaces the old `doctor_ak_video_consultation` boolean.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return bool
	 */
	public static function doctor_has_active_video_clinic( $doctor_id ) {
		foreach ( self::get_for_doctor( $doctor_id ) as $clinic ) {
			if ( self::TYPE_VIDEO === $clinic['type'] && ! empty( $clinic['enabled_days'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets every clinic across every doctor, for the admin "Doctor Sessions"
	 * aggregate table, joined with the doctor's display name/email.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type int $number Max rows to return. Default 200.
	 * }
	 * @return array List of decoded clinic rows, each with an added 'doctor' sub-array (id/name/email).
	 */
	public static function all_flat_for_admin( array $args = array() ) {
		global $wpdb;

		$number = isset( $args['number'] ) ? (int) $args['number'] : 200;

		$sql = $wpdb->prepare(
			"SELECT c.*, u.display_name AS doctor_display_name, u.user_email AS doctor_email
			FROM " . self::table_name() . " c
			INNER JOIN {$wpdb->users} u ON u.ID = c.doctor_id
			ORDER BY c.id DESC
			LIMIT %d",
			$number
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names, not user input.

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return array_map(
			function ( $row ) {
				$clinic = self::decode_row( $row );

				$doctor    = get_userdata( $clinic['doctor_id'] );
				$full_name = $doctor ? trim( $doctor->first_name . ' ' . $doctor->last_name ) : '';

				$clinic['doctor'] = array(
					'id'    => $clinic['doctor_id'],
					'name'  => '' !== $full_name ? $full_name : $row['doctor_display_name'],
					'email' => $row['doctor_email'],
				);

				return $clinic;
			},
			$rows
		);
	}

	/**
	 * Decodes a raw DB row into the shape the rest of the codebase works with:
	 * casts IDs, decodes the sessions JSON, and adds a computed 'enabled_days'
	 * list (day slug => label) for display convenience.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		$sessions = json_decode( (string) $row['sessions'], true );

		if ( ! is_array( $sessions ) ) {
			$sessions = self::empty_sessions();
		} else {
			$sessions = wp_parse_args( $sessions, self::empty_sessions() );
		}

		$enabled_days = array();

		foreach ( self::session_days() as $slug => $label ) {
			if ( ! empty( $sessions[ $slug ]['enabled'] ) ) {
				$enabled_days[ $slug ] = $label;
			}
		}

		return array(
			'id'            => (int) $row['id'],
			'doctor_id'     => (int) $row['doctor_id'],
			'type'          => $row['type'],
			'name'          => $row['name'],
			'address'       => $row['address'],
			'phone'         => $row['phone'],
			'contact_email' => $row['contact_email'],
			'sessions'      => $sessions,
			'enabled_days'  => $enabled_days,
		);
	}

	/**
	 * Validates a time string in 24-hour HH:MM format.
	 *
	 * @param string $time Time string.
	 * @return bool
	 */
	private static function is_valid_time( $time ) {
		return (bool) preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time );
	}
}
