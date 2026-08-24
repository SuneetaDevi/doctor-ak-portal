<?php
/**
 * Admin-managed master list of physical clinics (Country/City/Area/Name).
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinic_Locations
 *
 * A single source-of-truth clinic list an admin builds once (e.g. "Chughtai
 * Lab — 3 Talwar, Karachi, Pakistan"), independent of any doctor. Doctors are
 * then aligned to one of these from the admin "Doctor Sessions" form instead
 * of each doctor's clinic entry carrying its own freely-typed name/address —
 * see Clinic_Handler::process_save(), which copies a chosen clinic's fields
 * onto the doctor's own `dak_clinics` row (which still owns that doctor's
 * weekly session schedule at the clinic).
 */
class Clinic_Locations {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_clinic_locations';

	/**
	 * User meta key storing a patient's single "home clinic" — the one
	 * Clinic_Locations row they're registered under, chosen by an admin/
	 * doctor when adding them or by the patient themselves via the
	 * post-registration clinic-selection screen. Never set for doctors
	 * (who instead have their own possibly-multiple `dak_clinics` rows) and
	 * never required for/read by the video consultation flow.
	 *
	 * @var string
	 */
	const PATIENT_META_KEY = 'doctor_ak_patient_clinic_location_id';

	/**
	 * User meta key storing a receptionist's assigned Clinic_Locations rows
	 * (an array of IDs — a receptionist can front-desk more than one
	 * clinic, unlike a patient's single home clinic). Set by an admin from
	 * the Add/Edit Receptionist form. An empty/unset value means "not
	 * restricted to any particular clinic" — see
	 * Notification_Center::receptionist_ids_for_clinic_location(), which is
	 * the only thing that currently reads this.
	 *
	 * @var string
	 */
	const RECEPTIONIST_META_KEY = 'doctor_ak_receptionist_clinic_locations';

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
	 * Validates and sanitizes a posted clinic-location form.
	 *
	 * @param array $posted Raw request array (e.g. $_POST, already a plain array).
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_from_request( array $posted ) {
		$name = isset( $posted['name'] ) ? sanitize_text_field( wp_unslash( $posted['name'] ) ) : '';

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_clinic_location_name_required', __( 'Please provide a name for this clinic.', 'doctor-ak-portal' ) );
		}

		$country = isset( $posted['country'] ) ? sanitize_text_field( wp_unslash( $posted['country'] ) ) : '';
		$city    = isset( $posted['city'] ) ? sanitize_text_field( wp_unslash( $posted['city'] ) ) : '';
		$area    = isset( $posted['area'] ) ? sanitize_text_field( wp_unslash( $posted['area'] ) ) : '';

		if ( '' === $country || ! Locations::is_valid_country( $country ) ) {
			return new \WP_Error( 'doctor_ak_clinic_location_country_required', __( 'Please select this clinic\'s country.', 'doctor-ak-portal' ) );
		}

		if ( '' === $city || ! Locations::is_valid_city( $country, $city ) ) {
			return new \WP_Error( 'doctor_ak_clinic_location_city_required', __( 'Please select this clinic\'s city.', 'doctor-ak-portal' ) );
		}

		if ( '' === $area || ! Locations::is_valid_area( $country, $city, $area ) ) {
			return new \WP_Error( 'doctor_ak_clinic_location_area_required', __( 'Please select this clinic\'s area.', 'doctor-ak-portal' ) );
		}

		$address = isset( $posted['address'] ) ? sanitize_text_field( wp_unslash( $posted['address'] ) ) : '';
		$phone   = isset( $posted['phone'] ) ? sanitize_text_field( wp_unslash( $posted['phone'] ) ) : '';

		if ( '' !== $phone && ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $phone ) ) {
			return new \WP_Error( 'doctor_ak_clinic_location_phone_invalid', __( 'Please provide a valid phone number.', 'doctor-ak-portal' ) );
		}

		$contact_email = isset( $posted['contact_email'] ) ? sanitize_email( wp_unslash( $posted['contact_email'] ) ) : '';

		if ( '' !== $contact_email && ! is_email( $contact_email ) ) {
			return new \WP_Error( 'doctor_ak_clinic_location_email_invalid', __( 'Please provide a valid contact email address.', 'doctor-ak-portal' ) );
		}

		return array(
			'name'          => $name,
			'address'       => $address,
			'country'       => $country,
			'city'          => $city,
			'area'          => $area,
			'phone'         => $phone,
			'contact_email' => $contact_email,
		);
	}

	/**
	 * Creates a new clinic-location row.
	 *
	 * @param array $fields Sanitized fields, see sanitize_from_request().
	 * @return int|false New row ID, or false on failure.
	 */
	public static function create( array $fields ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'name'          => $fields['name'],
				'address'       => $fields['address'],
				'country'       => $fields['country'],
				'city'          => $fields['city'],
				'area'          => $fields['area'],
				'phone'         => $fields['phone'],
				'contact_email' => $fields['contact_email'],
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates an existing clinic-location row.
	 *
	 * @param int   $id     Row ID.
	 * @param array $fields Sanitized fields, see sanitize_from_request().
	 * @return bool
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'name'          => $fields['name'],
				'address'       => $fields['address'],
				'country'       => $fields['country'],
				'city'          => $fields['city'],
				'area'          => $fields['area'],
				'phone'         => $fields['phone'],
				'contact_email' => $fields['contact_email'],
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Deletes a clinic-location row. Doctors already aligned to it keep their
	 * own clinic row (name/address/etc. already copied onto it) — only the
	 * shared `clinic_location_id` reference goes stale, same as any other
	 * "deleted the thing a foreign key pointed at" case in this codebase.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		return false !== $wpdb->delete( self::table_name(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Finds a single clinic-location by ID.
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
	 * Every clinic-location, for the admin "Clinic" table and the "Doctor
	 * Sessions" form's Country -> City -> Area -> Clinic picker.
	 *
	 * @return array List of decoded rows, alphabetical by name.
	 */
	public static function get_all() {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . ' ORDER BY name ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Total number of clinic-locations, for the admin dashboard's stat cards.
	 *
	 * @return int
	 */
	public static function total_count() {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
	}

	/**
	 * Decodes a raw DB row: casts the ID, adds human-readable location labels.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		$country = $row['country'];
		$city    = $row['city'];
		$area    = $row['area'];

		return array(
			'id'            => (int) $row['id'],
			'name'          => $row['name'],
			'address'       => $row['address'],
			'country'       => $country,
			'country_label' => '' !== $country ? Locations::country_label( $country ) : '',
			'city'          => $city,
			'city_label'    => '' !== $city ? Locations::city_label( $country, $city ) : '',
			'area'          => $area,
			'area_label'    => '' !== $area ? Locations::area_label( $country, $city, $area ) : '',
			'phone'         => $row['phone'],
			'contact_email' => $row['contact_email'],
		);
	}
}
