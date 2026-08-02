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
	 * The (up to) three sessions a single day can be split into, in display
	 * order, keyed by slug — lets a doctor take a midday break instead of
	 * being open for one unbroken block. A day counts as "open" if any one
	 * of its periods is enabled; slots are generated independently per
	 * enabled period, so the gap between e.g. a morning and evening period
	 * naturally becomes a break with no bookable slots in it.
	 *
	 * @return array
	 */
	public static function session_periods() {
		return array(
			'morning'   => __( 'Morning', 'doctor-ak-portal' ),
			'afternoon' => __( 'Afternoon', 'doctor-ak-portal' ),
			'evening'   => __( 'Evening', 'doctor-ak-portal' ),
		);
	}

	/**
	 * One disabled period's shape.
	 *
	 * @return array
	 */
	private static function empty_period() {
		return array(
			'enabled'               => false,
			'start'                 => '',
			'end'                   => '',
			'slot_duration_minutes' => 0,
		);
	}

	/**
	 * Returns a sessions structure with every day/period disabled.
	 *
	 * @return array
	 */
	public static function empty_sessions() {
		$sessions = array();

		foreach ( self::session_days() as $day_slug => $day_label ) {
			$periods = array();

			foreach ( self::session_periods() as $period_slug => $period_label ) {
				$periods[ $period_slug ] = self::empty_period();
			}

			$sessions[ $day_slug ] = $periods;
		}

		return $sessions;
	}

	/**
	 * Validates and sanitizes the `sessions` portion of a request.
	 *
	 * Expects the shape produced by fields named
	 * `sessions[day][period][enabled|start|end|slot_duration_minutes]`.
	 *
	 * @param array $posted_sessions Raw `sessions` sub-array from the request.
	 * @return array|\WP_Error Sanitized sessions, or WP_Error on invalid input.
	 */
	public static function sanitize_sessions_from_request( array $posted_sessions ) {
		$sessions = array();

		foreach ( self::session_days() as $day_slug => $day_label ) {
			$posted_day = isset( $posted_sessions[ $day_slug ] ) && is_array( $posted_sessions[ $day_slug ] )
				? $posted_sessions[ $day_slug ]
				: array();

			$periods = array();

			foreach ( self::session_periods() as $period_slug => $period_label ) {
				$period = isset( $posted_day[ $period_slug ] ) && is_array( $posted_day[ $period_slug ] )
					? $posted_day[ $period_slug ]
					: array();

				$enabled = ! empty( $period['enabled'] );

				if ( ! $enabled ) {
					$periods[ $period_slug ] = self::empty_period();
					continue;
				}

				$start = isset( $period['start'] ) ? sanitize_text_field( wp_unslash( $period['start'] ) ) : '';
				$end   = isset( $period['end'] ) ? sanitize_text_field( wp_unslash( $period['end'] ) ) : '';

				/* translators: 1: period name (Morning/Afternoon/Evening), 2: day of the week. */
				$period_day_label = sprintf( __( '%1$s on %2$s', 'doctor-ak-portal' ), $period_label, $day_label );

				if ( ! self::is_valid_time( $start ) || ! self::is_valid_time( $end ) ) {
					/* translators: %s: e.g. "Morning on Monday". */
					return new \WP_Error( 'doctor_ak_invalid_session_time', sprintf( __( 'Please provide a valid start and end time for %s.', 'doctor-ak-portal' ), $period_day_label ) );
				}

				if ( $start >= $end ) {
					/* translators: %s: e.g. "Morning on Monday". */
					return new \WP_Error( 'doctor_ak_invalid_session_range', sprintf( __( 'The end time must be after the start time for %s.', 'doctor-ak-portal' ), $period_day_label ) );
				}

				$slot_duration = isset( $period['slot_duration_minutes'] ) ? absint( wp_unslash( $period['slot_duration_minutes'] ) ) : 0;

				if ( $slot_duration < 5 || $slot_duration > 240 ) {
					/* translators: %s: e.g. "Morning on Monday". */
					return new \WP_Error( 'doctor_ak_invalid_slot_duration', sprintf( __( 'Please provide a slot duration between 5 and 240 minutes for %s.', 'doctor-ak-portal' ), $period_day_label ) );
				}

				$periods[ $period_slug ] = array(
					'enabled'               => true,
					'start'                 => $start,
					'end'                   => $end,
					'slot_duration_minutes' => $slot_duration,
				);
			}

			$sessions[ $day_slug ] = $periods;
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

		$country = isset( $posted['country'] ) ? sanitize_text_field( wp_unslash( $posted['country'] ) ) : '';
		$city    = isset( $posted['city'] ) ? sanitize_text_field( wp_unslash( $posted['city'] ) ) : '';
		$area    = isset( $posted['area'] ) ? sanitize_text_field( wp_unslash( $posted['area'] ) ) : '';

		if ( self::TYPE_PHYSICAL === $type ) {
			if ( '' === $country || ! Locations::is_valid_country( $country ) ) {
				return new \WP_Error( 'doctor_ak_clinic_country_required', __( 'Please select this clinic\'s country.', 'doctor-ak-portal' ) );
			}

			if ( '' === $city || ! Locations::is_valid_city( $country, $city ) ) {
				return new \WP_Error( 'doctor_ak_clinic_city_required', __( 'Please select this clinic\'s city.', 'doctor-ak-portal' ) );
			}

			if ( '' === $area || ! Locations::is_valid_area( $country, $city, $area ) ) {
				return new \WP_Error( 'doctor_ak_clinic_area_required', __( 'Please select this clinic\'s area.', 'doctor-ak-portal' ) );
			}
		}

		if ( self::TYPE_VIDEO === $type ) {
			$address = '';
			$country = '';
			$city    = '';
			$area    = '';
		}

		$phone = isset( $posted['phone'] ) ? sanitize_text_field( wp_unslash( $posted['phone'] ) ) : '';

		if ( '' !== $phone && ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $phone ) ) {
			return new \WP_Error( 'doctor_ak_clinic_phone_invalid', __( 'Please provide a valid phone number.', 'doctor-ak-portal' ) );
		}

		$contact_email = isset( $posted['contact_email'] ) ? sanitize_email( wp_unslash( $posted['contact_email'] ) ) : '';

		if ( '' !== $contact_email && ! is_email( $contact_email ) ) {
			return new \WP_Error( 'doctor_ak_clinic_email_invalid', __( 'Please provide a valid contact email address.', 'doctor-ak-portal' ) );
		}

		// Set by Clinic_Handler::process_save() when this physical clinic was
		// aligned to a Clinic_Locations master record — from either the admin
		// "Doctor Sessions" form or the doctor's own "Clinics" tab (which
		// also already overwrote name/address/country/city/area above with
		// that record's own values) — carried through so the association
		// itself is stored, not just a name/address snapshot.
		$clinic_location_id = isset( $posted['clinic_location_id'] ) ? absint( wp_unslash( $posted['clinic_location_id'] ) ) : 0;

		return array(
			'type'               => $type,
			'name'               => $name,
			'address'            => $address,
			'country'            => $country,
			'city'               => $city,
			'area'               => $area,
			'phone'              => $phone,
			'contact_email'      => $contact_email,
			'clinic_location_id' => $clinic_location_id,
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
				'doctor_id'          => (int) $doctor_id,
				'type'               => $fields['type'],
				'name'               => $fields['name'],
				'address'            => $fields['address'],
				'country'            => $fields['country'],
				'city'               => $fields['city'],
				'area'               => $fields['area'],
				'phone'              => $fields['phone'],
				'contact_email'      => $fields['contact_email'],
				'clinic_location_id' => isset( $fields['clinic_location_id'] ) ? (int) $fields['clinic_location_id'] : 0,
				'sessions'           => wp_json_encode( $sessions ),
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
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
				'type'               => $fields['type'],
				'name'               => $fields['name'],
				'address'            => $fields['address'],
				'country'            => $fields['country'],
				'city'               => $fields['city'],
				'area'               => $fields['area'],
				'phone'              => $fields['phone'],
				'contact_email'      => $fields['contact_email'],
				'clinic_location_id' => isset( $fields['clinic_location_id'] ) ? (int) $fields['clinic_location_id'] : 0,
				'sessions'           => wp_json_encode( $sessions ),
				'updated_at'         => current_time( 'mysql' ),
			),
			$where,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
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
	 * Every clinic for a batch of doctors in one query, grouped by doctor_id
	 * — avoids the N+1 pattern of calling get_for_doctor() once per row when
	 * rendering a table of many doctors at once (see Admin_Dashboard::row_data()).
	 *
	 * @param array $doctor_ids Doctor user IDs.
	 * @return array doctor_id => array of decoded clinic rows (see decode_row()). Doctors with no clinics are simply absent, not an empty array key.
	 */
	public static function get_for_doctors( array $doctor_ids ) {
		global $wpdb;

		$doctor_ids = array_values( array_unique( array_map( 'absint', $doctor_ids ) ) );

		if ( empty( $doctor_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $doctor_ids ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . " WHERE doctor_id IN ({$placeholders}) ORDER BY doctor_id ASC, id ASC", $doctor_ids ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table name/placeholder count, not user input.
			ARRAY_A
		);

		$grouped = array();

		foreach ( $rows as $row ) {
			$grouped[ (int) $row['doctor_id'] ][] = self::decode_row( $row );
		}

		return $grouped;
	}

	/**
	 * A clinic's name, only if it actually belongs to the given doctor —
	 * used to safely resolve a "which clinic was this patient added at"
	 * label without trusting a stored clinic_id blindly (e.g. after the
	 * clinic was later deleted, or reused across doctors).
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @param int $clinic_id Clinic ID.
	 * @return string Empty string if not found or not owned by this doctor.
	 */
	public static function clinic_name_for_doctor( $doctor_id, $clinic_id ) {
		foreach ( self::get_for_doctor( $doctor_id ) as $clinic ) {
			if ( (int) $clinic['id'] === (int) $clinic_id ) {
				return $clinic['name'];
			}
		}

		return '';
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

			$day = isset( $clinic['sessions'][ $weekday ] ) ? $clinic['sessions'][ $weekday ] : array();

			foreach ( self::session_periods() as $period_slug => $period_label ) {
				$period = isset( $day[ $period_slug ] ) ? $day[ $period_slug ] : null;

				if ( ! $period || empty( $period['enabled'] ) ) {
					continue;
				}

				$slot_duration = (int) $period['slot_duration_minutes'];

				if ( $slot_duration <= 0 ) {
					continue;
				}

				$start_minutes = self::time_to_minutes( $period['start'] );
				$end_minutes   = self::time_to_minutes( $period['end'] );

				for ( $minutes = $start_minutes; $minutes + $slot_duration <= $end_minutes; $minutes += $slot_duration ) {
					$slots[] = sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
				}
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
	 * User meta key for the admin-controlled "does this doctor offer video
	 * consultations at all" permission (see Admin_User_Handler::handle_save_user()).
	 * Unset/anything other than '0' is treated as allowed, so existing
	 * doctors who already scheduled a video clinic before this setting
	 * existed keep working exactly as before.
	 *
	 * @var string
	 */
	const VIDEO_CONSULTATION_ALLOWED_META_KEY = 'doctor_ak_video_consultation_allowed';

	/**
	 * Whether a doctor offers video consultations at all — purely the
	 * admin-controlled permission. Booking eligibility no longer additionally
	 * requires the doctor to have already scheduled specific weekly video
	 * session days: an admin turning this on should be enough for patients to
	 * see the Online Video option, even before (or without) the doctor
	 * separately configuring session hours. If no days are configured yet,
	 * the date/time step simply shows its existing "no time slots configured"
	 * empty state rather than hiding the option entirely.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return bool
	 */
	public static function doctor_has_active_video_clinic( $doctor_id ) {
		return '0' !== get_user_meta( $doctor_id, self::VIDEO_CONSULTATION_ALLOWED_META_KEY, true );
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
		$decoded = json_decode( (string) $row['sessions'], true );
		$sessions = self::empty_sessions();

		if ( is_array( $decoded ) ) {
			foreach ( self::session_days() as $day_slug => $day_label ) {
				if ( ! isset( $decoded[ $day_slug ] ) || ! is_array( $decoded[ $day_slug ] ) ) {
					continue;
				}

				$day = $decoded[ $day_slug ];

				if ( array_key_exists( 'enabled', $day ) ) {
					// Pre-existing clinic saved before per-day sessions were
					// split into Morning/Afternoon/Evening periods: a single
					// flat { enabled, start, end, slot_duration_minutes }.
					// Carry it forward as that day's "morning" period so
					// doctors who already had a schedule don't lose it.
					$sessions[ $day_slug ]['morning'] = wp_parse_args( $day, self::empty_period() );
					continue;
				}

				foreach ( self::session_periods() as $period_slug => $period_label ) {
					if ( isset( $day[ $period_slug ] ) && is_array( $day[ $period_slug ] ) ) {
						$sessions[ $day_slug ][ $period_slug ] = wp_parse_args( $day[ $period_slug ], self::empty_period() );
					}
				}
			}
		}

		$enabled_days = array();

		foreach ( self::session_days() as $slug => $label ) {
			foreach ( $sessions[ $slug ] as $period ) {
				if ( ! empty( $period['enabled'] ) ) {
					$enabled_days[ $slug ] = $label;
					break;
				}
			}
		}

		$country = isset( $row['country'] ) ? $row['country'] : '';
		$city    = isset( $row['city'] ) ? $row['city'] : '';
		$area    = isset( $row['area'] ) ? $row['area'] : '';

		return array(
			'id'                 => (int) $row['id'],
			'doctor_id'          => (int) $row['doctor_id'],
			'type'               => $row['type'],
			'name'               => $row['name'],
			'address'            => $row['address'],
			'country'            => $country,
			'country_label'      => '' !== $country ? Locations::country_label( $country ) : '',
			'city'               => $city,
			'city_label'         => '' !== $city ? Locations::city_label( $country, $city ) : '',
			'area'               => $area,
			'area_label'         => '' !== $area ? Locations::area_label( $country, $city, $area ) : '',
			'phone'              => $row['phone'],
			'contact_email'      => $row['contact_email'],
			'clinic_location_id' => isset( $row['clinic_location_id'] ) ? (int) $row['clinic_location_id'] : 0,
			'sessions'           => $sessions,
			'enabled_days'       => $enabled_days,
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
