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

		$fields = array(
			'type'             => $type,
			'name'             => $name,
			'category'         => $category,
			'charge'           => number_format( $charge, 2, '.', '' ),
			'duration_minutes' => $duration_minutes,
			'active'           => $active,
		);

		// Only the admin "Add/Edit Service" modal's form posts these — the
		// doctor-facing Services tab has no Description/Clinics fields at
		// all, so they're genuinely absent from $posted (not just empty) on
		// a doctor's own save. Keying on array_key_exists() rather than
		// defaulting to '' / [] here means update() only overwrites these
		// columns when the submitting form actually has them — otherwise a
		// doctor editing their own service would silently wipe out
		// description/clinics an admin had already set on it.
		if ( array_key_exists( 'description', $posted ) ) {
			$fields['description'] = sanitize_textarea_field( wp_unslash( $posted['description'] ) );
		}

		if ( array_key_exists( 'clinic_location_ids', $posted ) ) {
			$clinic_location_ids = array();

			if ( is_array( $posted['clinic_location_ids'] ) ) {
				foreach ( wp_unslash( $posted['clinic_location_ids'] ) as $clinic_location_id ) {
					$clinic_location_id = absint( $clinic_location_id );

					if ( $clinic_location_id > 0 && Clinic_Locations::find( $clinic_location_id ) ) {
						$clinic_location_ids[] = $clinic_location_id;
					}
				}
			}

			$fields['clinic_location_ids'] = $clinic_location_ids;
		}

		return $fields;
	}

	/**
	 * Creates a new service row.
	 *
	 * @param int   $doctor_id Doctor's user ID.
	 * @param array $fields    Sanitized service fields.
	 * @param int   $image_id  Attachment ID for the public portfolio's image, or 0 for none.
	 * @return int|false New service ID, or false on failure.
	 */
	public static function create( $doctor_id, array $fields, $image_id = 0 ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'doctor_id'           => (int) $doctor_id,
				'type'                => $fields['type'],
				'name'                => $fields['name'],
				'category'            => $fields['category'],
				'charge'              => $fields['charge'],
				'duration_minutes'    => $fields['duration_minutes'],
				'active'              => $fields['active'] ? 1 : 0,
				'description'         => isset( $fields['description'] ) ? $fields['description'] : '',
				'image_id'            => (int) $image_id,
				'clinic_location_ids' => wp_json_encode( isset( $fields['clinic_location_ids'] ) ? $fields['clinic_location_ids'] : array() ),
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return false;
		}

		$service_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a new service is created — Notifications listens here
		 * to email everyone in the directory about it (see
		 * Notifications::notify_new_service()).
		 *
		 * @param int   $service_id Newly created service's ID.
		 * @param int   $doctor_id  Doctor the service belongs to.
		 * @param array $fields     Sanitized service fields (see fields()).
		 */
		do_action( 'doctor_ak_service_created', $service_id, $doctor_id, $fields );

		return $service_id;
	}

	/**
	 * Updates an existing service row.
	 *
	 * @param int      $service_id Service ID.
	 * @param array    $fields     Sanitized service fields. 'description'/'clinic_location_ids' only get written when present (see sanitize_fields_from_request()'s docblock) — a doctor's own save, which never posts either, leaves whatever an admin already set on them untouched.
	 * @param int|null $doctor_id  If given, the update only applies when the service belongs to this doctor; pass null to skip the check (admin context).
	 * @param int|null $image_id   Attachment ID for the public portfolio's image, or null to leave the existing one untouched (a doctor's own save never posts this field either).
	 * @return bool
	 */
	public static function update( $service_id, array $fields, $doctor_id = null, $image_id = null ) {
		global $wpdb;

		$where       = array( 'id' => (int) $service_id );
		$where_types = array( '%d' );

		if ( null !== $doctor_id ) {
			$where['doctor_id'] = (int) $doctor_id;
			$where_types[]      = '%d';
		}

		$data  = array(
			'type'             => $fields['type'],
			'name'             => $fields['name'],
			'category'         => $fields['category'],
			'charge'           => $fields['charge'],
			'duration_minutes' => $fields['duration_minutes'],
			'active'           => $fields['active'] ? 1 : 0,
			'updated_at'       => current_time( 'mysql' ),
		);
		$types = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

		if ( array_key_exists( 'description', $fields ) ) {
			$data['description'] = $fields['description'];
			$types[]              = '%s';
		}

		if ( array_key_exists( 'clinic_location_ids', $fields ) ) {
			$data['clinic_location_ids'] = wp_json_encode( $fields['clinic_location_ids'] );
			$types[]                      = '%s';
		}

		if ( null !== $image_id ) {
			$data['image_id'] = (int) $image_id;
			$types[]           = '%d';
		}

		$updated = $wpdb->update( self::table_name(), $data, $where, $types, $where_types );

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
	 *     @type int $number    Max rows to return. Default 200.
	 *     @type int $doctor_id Only this doctor's services, when > 0. Default 0 (every doctor).
	 * }
	 * @return array List of decoded service rows, each with an added 'doctor' sub-array (id/name/email).
	 */
	public static function all_flat_for_admin( array $args = array() ) {
		global $wpdb;

		$number    = isset( $args['number'] ) ? (int) $args['number'] : 200;
		$doctor_id = isset( $args['doctor_id'] ) ? (int) $args['doctor_id'] : 0;

		$where  = $doctor_id > 0 ? 'WHERE s.doctor_id = %d' : '';
		$params = $doctor_id > 0 ? array( $doctor_id, $number ) : array( $number );

		$sql = $wpdb->prepare(
			"SELECT s.*, u.display_name AS doctor_display_name, u.user_email AS doctor_email
			FROM " . self::table_name() . " s
			INNER JOIN {$wpdb->users} u ON u.ID = s.doctor_id
			$where
			ORDER BY s.id DESC
			LIMIT %d",
			$params
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
	 * Every active service, across every non-deactivated doctor, for the
	 * public [services_directory] grid — this plugin's "service portfolio"
	 * is simply every Services row an admin has enriched with a
	 * description/image/clinics (see Service_Handler's admin save path),
	 * not a separate table.
	 *
	 * @return array List of decoded service rows, alphabetical by name.
	 */
	public static function active_for_public_directory() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.* FROM " . self::table_name() . " s
				INNER JOIN {$wpdb->users} u ON u.ID = s.doctor_id
				LEFT JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = 'doctor_ak_account_disabled'
				WHERE s.type = %s AND s.active = 1 AND ( m.meta_value IS NULL OR m.meta_value != 'yes' )
				ORDER BY s.name ASC",
				self::TYPE_CLINIC
			), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names, not user input; the one variable (TYPE_CLINIC) is bound via %s.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * A single active service for the public [service_profile_view] page —
	 * null if it doesn't exist, isn't active, or its doctor is deactivated
	 * (matches active_for_public_directory()'s visibility rule).
	 *
	 * @param int $service_id Service ID.
	 * @return array|null
	 */
	public static function find_for_public_profile( $service_id ) {
		$service = self::find( $service_id );

		if ( ! $service || ! $service['active'] ) {
			return null;
		}

		$doctor = get_userdata( $service['doctor_id'] );

		if ( ! $doctor || 'yes' === get_user_meta( $doctor->ID, 'doctor_ak_account_disabled', true ) ) {
			return null;
		}

		return $service;
	}

	/**
	 * Decodes a raw DB row into the shape the rest of the codebase works
	 * with: casts IDs/numbers, adds a human-readable category label, and
	 * (for the public service portfolio — see active_for_public_directory())
	 * resolves the image URL, a formatted price, and the associated
	 * Clinic_Locations rows.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		$categories = Specializations::get_all();
		$category   = (string) $row['category'];
		$charge     = (float) $row['charge'];

		$image_id  = isset( $row['image_id'] ) ? (int) $row['image_id'] : 0;
		$image_url = '';

		if ( $image_id > 0 ) {
			$found     = wp_get_attachment_image_url( $image_id, 'large' );
			$image_url = $found ? $found : '';
		}

		$clinic_location_ids = array_map( 'intval', (array) json_decode( isset( $row['clinic_location_ids'] ) ? (string) $row['clinic_location_ids'] : '', true ) );
		$clinic_locations    = array_values( array_filter( array_map( array( __NAMESPACE__ . '\Clinic_Locations', 'find' ), $clinic_location_ids ) ) );

		return array(
			'id'                  => (int) $row['id'],
			'doctor_id'           => (int) $row['doctor_id'],
			'type'                => $row['type'],
			'name'                => $row['name'],
			'category'            => $category,
			'category_label'      => isset( $categories[ $category ] ) ? $categories[ $category ] : '',
			'charge'              => $charge,
			'price_label'         => $charge > 0 ? 'PKR ' . number_format_i18n( $charge ) : __( 'Free', 'doctor-ak-portal' ),
			'duration_minutes'    => (int) $row['duration_minutes'],
			'active'              => ! empty( $row['active'] ),
			'description'         => isset( $row['description'] ) ? (string) $row['description'] : '',
			'image_id'            => $image_id,
			'image_url'           => $image_url,
			'clinic_location_ids' => $clinic_location_ids,
			'clinic_locations'    => $clinic_locations,
		);
	}
}
