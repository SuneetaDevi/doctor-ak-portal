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

		// Only the admin "Add/Edit Service" modal's form posts a
		// 'has_portfolio_fields' marker (a hidden input, always '1') — the
		// doctor-facing Services tab has no Description/Clinics fields at
		// all, so it's absent there. Gating on this single marker (rather
		// than each field's own presence) means update() only overwrites
		// these columns when the submitting form is the richer admin one —
		// otherwise a doctor editing their own service would silently wipe
		// out description/clinic pricing an admin had already set on it,
		// and an admin clearing every clinic checkbox (a legitimate "no
		// clinics" edit, which posts no clinic_charges[] entries at all)
		// wouldn't otherwise be distinguishable from "field not submitted".
		if ( ! empty( $posted['has_portfolio_fields'] ) ) {
			$fields['description'] = isset( $posted['description'] ) ? sanitize_textarea_field( wp_unslash( $posted['description'] ) ) : '';

			$clinic_charges = array();

			if ( isset( $posted['clinic_charges'] ) && is_array( $posted['clinic_charges'] ) ) {
				foreach ( wp_unslash( $posted['clinic_charges'] ) as $clinic_location_id => $price ) {
					$clinic_location_id = absint( $clinic_location_id );
					$price               = (float) $price;

					if ( $clinic_location_id > 0 && $price >= 0 && Clinic_Locations::find( $clinic_location_id ) ) {
						$clinic_charges[ $clinic_location_id ] = number_format( $price, 2, '.', '' );
					}
				}
			}

			$fields['clinic_charges'] = $clinic_charges;
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
				'doctor_id'        => (int) $doctor_id,
				'type'             => $fields['type'],
				'name'             => $fields['name'],
				'category'         => $fields['category'],
				'charge'           => $fields['charge'],
				'duration_minutes' => $fields['duration_minutes'],
				'active'           => $fields['active'] ? 1 : 0,
				'description'      => isset( $fields['description'] ) ? $fields['description'] : '',
				'image_id'         => (int) $image_id,
				'clinic_charges'   => wp_json_encode( isset( $fields['clinic_charges'] ) ? $fields['clinic_charges'] : array() ),
				'created_at'       => $now,
				'updated_at'       => $now,
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
	 * @param array    $fields     Sanitized service fields. 'description'/'clinic_charges' only get written when present (see sanitize_fields_from_request()'s docblock) — a doctor's own save, which never posts either, leaves whatever an admin already set on them untouched.
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

		if ( array_key_exists( 'clinic_charges', $fields ) ) {
			$data['clinic_charges'] = wp_json_encode( $fields['clinic_charges'] );
			$types[]                = '%s';
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
	 * Every active Services row sharing the given name (case-insensitive,
	 * trimmed), across every non-deactivated doctor. A service added for
	 * several doctors at once (see Service_Handler::handle_admin_save_service()'s
	 * bulk-create) is really ONE portfolio entry with several doctor-owned
	 * rows, not several unrelated services — this is how the public
	 * [service_profile_view] page finds all of them to show a "price per
	 * doctor" breakdown with a Book-with-this-doctor link each.
	 *
	 * @param string $name Service name to match.
	 * @return array List of decoded rows, cheapest first.
	 */
	public static function active_rows_by_name( $name ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.* FROM " . self::table_name() . " s
				INNER JOIN {$wpdb->users} u ON u.ID = s.doctor_id
				LEFT JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = 'doctor_ak_account_disabled'
				WHERE s.type = %s AND s.active = 1 AND LOWER(TRIM(s.name)) = LOWER(TRIM(%s)) AND ( m.meta_value IS NULL OR m.meta_value != 'yes' )
				ORDER BY s.charge ASC",
				self::TYPE_CLINIC,
				$name
			), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names, not user input; both variables are bound via %s.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * active_for_public_directory()'s rows, grouped by name (case-
	 * insensitive, trimmed) — one card per unique service name for the
	 * public [services_directory] grid, since a service added for several
	 * doctors (see active_rows_by_name()'s docblock) is one portfolio entry,
	 * not one per doctor. Each group's description/image come from whichever
	 * row has them (bulk-create copies both onto every doctor's row
	 * identically, so this only matters if they've since diverged); its
	 * price is the cheapest doctor's, "From "-prefixed when they vary.
	 *
	 * @return array List of { id (a representative row's, for the detail-page link), name, description, image_url, price_label }, alphabetical by name.
	 */
	public static function grouped_active_for_public_directory() {
		$groups = array();

		foreach ( self::active_for_public_directory() as $row ) {
			$key = mb_strtolower( trim( $row['name'] ) );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'id'          => $row['id'],
					'name'        => $row['name'],
					'description' => $row['description'],
					'image_url'   => $row['image_url'],
					'prices'      => array(),
				);
			}

			if ( '' === $groups[ $key ]['description'] && '' !== $row['description'] ) {
				$groups[ $key ]['description'] = $row['description'];
			}

			if ( '' === $groups[ $key ]['image_url'] && '' !== $row['image_url'] ) {
				$groups[ $key ]['image_url'] = $row['image_url'];
			}

			$groups[ $key ]['prices'][] = $row['effective_price'];
		}

		return array_values(
			array_map(
				function ( $group ) {
					$prices = $group['prices'];
					unset( $group['prices'] );

					$group['price_label'] = self::price_range_label( $prices );

					// One price was collected per doctor offering this service,
					// so the count of them is the number of doctors it can be
					// booked with.
					$group['doctor_count'] = count( $prices );

					return $group;
				},
				$groups
			)
		);
	}

	/**
	 * Formats a list of prices as a single label — the lowest one, "From "-
	 * prefixed when they vary. Shared by grouped_active_for_public_directory()
	 * and Service_Profile_View for the "price across doctors" headline.
	 *
	 * @param float[] $prices Prices to summarize (may be empty).
	 * @return string
	 */
	public static function price_range_label( array $prices ) {
		if ( empty( $prices ) ) {
			return __( 'Free', 'doctor-ak-portal' );
		}

		$min   = min( $prices );
		$label = $min > 0 ? 'PKR ' . number_format_i18n( $min ) : __( 'Free', 'doctor-ak-portal' );

		if ( count( array_unique( $prices ) ) > 1 ) {
			$label = sprintf( /* translators: %s: lowest price. */ __( 'From %s', 'doctor-ak-portal' ), $label );
		}

		return $label;
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

		// clinic_charges maps a Clinic_Locations ID to this doctor's own
		// price at that clinic (see sanitize_fields_from_request()) — the
		// public portfolio pages show that instead of the flat $charge
		// whenever it's set, since the same service can cost differently
		// at different clinics for this doctor.
		$clinic_charges_raw = json_decode( isset( $row['clinic_charges'] ) ? (string) $row['clinic_charges'] : '', true );
		$clinic_charges     = array();

		foreach ( (array) $clinic_charges_raw as $clinic_location_id => $price ) {
			$clinic_charges[ (int) $clinic_location_id ] = (float) $price;
		}

		$clinic_locations = array();

		foreach ( $clinic_charges as $clinic_location_id => $price ) {
			$clinic_location = Clinic_Locations::find( $clinic_location_id );

			if ( ! $clinic_location ) {
				continue;
			}

			$clinic_location['price']       = $price;
			$clinic_location['price_label'] = $price > 0 ? 'PKR ' . number_format_i18n( $price ) : __( 'Free', 'doctor-ak-portal' );
			$clinic_locations[]             = $clinic_location;
		}

		// The headline price: the flat $charge, unless per-clinic prices
		// are set, in which case it's the cheapest of those (with "From "
		// prefixed if they vary) — same "From X" convention
		// Doctor_Profile_View::clinic_fee_label() already uses.
		// effective_price is the same figure as a plain number (no "From "/
		// currency text), for callers that group several doctors' rows of
		// the same service name and need to compute their own min/"From"
		// across doctors (see grouped_active_for_public_directory() and
		// active_rows_by_name()'s callers).
		$effective_price = $charge;
		$price_label      = $charge > 0 ? 'PKR ' . number_format_i18n( $charge ) : __( 'Free', 'doctor-ak-portal' );

		if ( ! empty( $clinic_charges ) ) {
			$effective_price = min( $clinic_charges );

			$price_label = $effective_price > 0 ? 'PKR ' . number_format_i18n( $effective_price ) : __( 'Free', 'doctor-ak-portal' );

			if ( count( array_unique( $clinic_charges ) ) > 1 ) {
				$price_label = sprintf( /* translators: %s: lowest clinic price. */ __( 'From %s', 'doctor-ak-portal' ), $price_label );
			}
		}

		return array(
			'id'               => (int) $row['id'],
			'doctor_id'        => (int) $row['doctor_id'],
			'type'             => $row['type'],
			'name'             => $row['name'],
			'category'         => $category,
			'category_label'   => isset( $categories[ $category ] ) ? $categories[ $category ] : '',
			'charge'           => $charge,
			'effective_price'  => $effective_price,
			'price_label'      => $price_label,
			'duration_minutes' => (int) $row['duration_minutes'],
			'active'           => ! empty( $row['active'] ),
			'description'      => isset( $row['description'] ) ? (string) $row['description'] : '',
			'image_id'         => $image_id,
			'image_url'        => $image_url,
			'clinic_charges'   => $clinic_charges,
			'clinic_locations' => $clinic_locations,
		);
	}
}
