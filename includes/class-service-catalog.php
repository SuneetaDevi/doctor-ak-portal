<?php
/**
 * The public "Service Portfolio" — marketing-facing service listings (e.g.
 * "Intragastric Balloon"), each shown on the public services directory and
 * its own detail page.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Catalog
 *
 * Distinct from Services (the per-doctor bookable line-items used at
 * checkout/billing time, unaffected by this class): a catalog entry here is
 * a single admin-authored portfolio item — name, description, image, one
 * price — associated with any number of Clinic_Locations rows ("offered
 * at") and any number of doctors ("provided by"), stored as JSON columns
 * rather than join tables (mirrors how Clinics stores its `sessions`
 * structure). Storage mirrors Clinic_Locations: a plain DB table, no post
 * type.
 */
class Service_Catalog {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_service_catalog';

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
	 * Validates and sanitizes a posted service-catalog form (excluding the
	 * image, which the handler resolves separately — see
	 * Service_Catalog_Handler::handle_save()).
	 *
	 * @param array $posted Raw request array (e.g. $_POST, already a plain array).
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_fields_from_request( array $posted ) {
		$name = isset( $posted['name'] ) ? sanitize_text_field( wp_unslash( $posted['name'] ) ) : '';

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_service_catalog_name_required', __( 'Please provide a name for this service.', 'doctor-ak-portal' ) );
		}

		$description = isset( $posted['description'] ) ? sanitize_textarea_field( wp_unslash( $posted['description'] ) ) : '';

		$price = isset( $posted['price'] ) ? (float) wp_unslash( $posted['price'] ) : 0;

		if ( $price < 0 ) {
			return new \WP_Error( 'doctor_ak_service_catalog_price_invalid', __( 'Please provide a valid price.', 'doctor-ak-portal' ) );
		}

		$clinic_location_ids = array();

		if ( isset( $posted['clinic_location_ids'] ) && is_array( $posted['clinic_location_ids'] ) ) {
			foreach ( wp_unslash( $posted['clinic_location_ids'] ) as $clinic_location_id ) {
				$clinic_location_id = absint( $clinic_location_id );

				if ( $clinic_location_id > 0 && Clinic_Locations::find( $clinic_location_id ) ) {
					$clinic_location_ids[] = $clinic_location_id;
				}
			}
		}

		$doctor_ids = array();

		if ( isset( $posted['doctor_ids'] ) && is_array( $posted['doctor_ids'] ) ) {
			foreach ( wp_unslash( $posted['doctor_ids'] ) as $doctor_id ) {
				$doctor_id = absint( $doctor_id );
				$doctor    = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

				if ( $doctor && in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
					$doctor_ids[] = $doctor_id;
				}
			}
		}

		$active = ! empty( $posted['active'] );

		return array(
			'name'                => $name,
			'description'         => $description,
			'price'               => number_format( $price, 2, '.', '' ),
			'clinic_location_ids' => $clinic_location_ids,
			'doctor_ids'          => $doctor_ids,
			'active'              => $active,
		);
	}

	/**
	 * Creates a new service-catalog row.
	 *
	 * @param array $fields   Sanitized fields, see sanitize_fields_from_request().
	 * @param int   $image_id Attachment ID, or 0 for none.
	 * @return int|false New row ID, or false on failure.
	 */
	public static function create( array $fields, $image_id = 0 ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'name'                => $fields['name'],
				'description'         => $fields['description'],
				'price'               => $fields['price'],
				'image_id'            => (int) $image_id,
				'clinic_location_ids' => wp_json_encode( $fields['clinic_location_ids'] ),
				'doctor_ids'          => wp_json_encode( $fields['doctor_ids'] ),
				'active'              => $fields['active'] ? 1 : 0,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates an existing service-catalog row.
	 *
	 * @param int   $id       Row ID.
	 * @param array $fields   Sanitized fields, see sanitize_fields_from_request().
	 * @param int   $image_id Attachment ID to store, or 0 to clear it.
	 * @return bool
	 */
	public static function update( $id, array $fields, $image_id = 0 ) {
		global $wpdb;

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'name'                => $fields['name'],
				'description'         => $fields['description'],
				'price'               => $fields['price'],
				'image_id'            => (int) $image_id,
				'clinic_location_ids' => wp_json_encode( $fields['clinic_location_ids'] ),
				'doctor_ids'          => wp_json_encode( $fields['doctor_ids'] ),
				'active'              => $fields['active'] ? 1 : 0,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Deletes a service-catalog row. Its image attachment (if any) is left
	 * in the Media Library untouched, same as every other attachment
	 * reference in this codebase (e.g. a doctor's profile picture on
	 * account deletion).
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		return false !== $wpdb->delete( self::table_name(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Finds a single service-catalog row by ID.
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
	 * Every service-catalog row, for the admin "Service Portfolio" table.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type int $number Max rows to return. Default 200.
	 * }
	 * @return array List of decoded rows, newest first.
	 */
	public static function all_flat_for_admin( array $args = array() ) {
		global $wpdb;

		$number = isset( $args['number'] ) ? (int) $args['number'] : 200;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d', $number ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Every active service-catalog row, for the public [services_directory]
	 * grid.
	 *
	 * @return array List of decoded rows, alphabetical by name.
	 */
	public static function active_for_directory() {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . ' WHERE active = 1 ORDER BY name ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Total number of service-catalog rows, for the admin dashboard's stat
	 * cards.
	 *
	 * @return int
	 */
	public static function total_count() {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
	}

	/**
	 * Decodes a raw DB row into the shape the rest of the codebase works
	 * with: casts IDs/numbers, expands the JSON clinic/doctor ID lists into
	 * their full resolved records (skipping any that no longer exist —
	 * e.g. a since-deleted clinic location or doctor), and resolves the
	 * image URL and a formatted price label.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		$clinic_location_ids = array_map( 'intval', (array) json_decode( (string) $row['clinic_location_ids'], true ) );
		$doctor_ids           = array_map( 'intval', (array) json_decode( (string) $row['doctor_ids'], true ) );

		$clinic_locations = array_values(
			array_filter(
				array_map( array( 'DoctorAKPortal\Includes\Clinic_Locations', 'find' ), $clinic_location_ids )
			)
		);

		$doctors = array();

		foreach ( $doctor_ids as $doctor_id ) {
			$doctor = get_userdata( $doctor_id );

			if ( ! $doctor ) {
				continue;
			}

			$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );

			$doctors[] = array(
				'id'          => $doctor->ID,
				'name'        => '' !== $display_name ? $display_name : $doctor->display_name,
				'is_disabled' => 'yes' === get_user_meta( $doctor->ID, 'doctor_ak_account_disabled', true ),
			);
		}

		$image_id  = (int) $row['image_id'];
		$image_url = '';

		if ( $image_id > 0 ) {
			$found = wp_get_attachment_image_url( $image_id, 'large' );
			$image_url = $found ? $found : '';
		}

		$price = (float) $row['price'];

		return array(
			'id'                  => (int) $row['id'],
			'name'                => $row['name'],
			'description'         => $row['description'],
			'price'               => $price,
			'price_label'         => $price > 0 ? 'PKR ' . number_format_i18n( $price ) : __( 'Free', 'doctor-ak-portal' ),
			'image_id'            => $image_id,
			'image_url'           => $image_url,
			'clinic_location_ids' => $clinic_location_ids,
			'clinic_locations'    => $clinic_locations,
			'doctor_ids'          => $doctor_ids,
			'doctors'             => $doctors,
			'active'              => ! empty( $row['active'] ),
		);
	}
}
