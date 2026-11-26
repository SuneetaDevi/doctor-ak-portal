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
 *
 * Also carries an admin-only `keywords` field (see sanitize_keywords()) —
 * free text an admin types when adding/editing a clinic, never rendered
 * anywhere public. It only exists so the hero search popup's "Clinics"
 * results (see Home_Page::render()/doctor-ak-home.js) can match a visitor's
 * search against things they'd actually type that the clinic's own
 * name/address don't cover, without adding visible clutter to the listing.
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
	 * The website's own contact phone and email, used for every clinic —
	 * clinics don't have their own. The phone is the footer's "Booking Phone
	 * Number" and the email its "Contact Email" (WP Admin → Settings →
	 * Footer Settings). Until that email is filled in, the first email ever
	 * saved on a clinic is used, so nothing changes for visitors meanwhile.
	 *
	 * @return array { phone: string, email: string } — either may be ''.
	 */
	public static function website_contact() {
		$email = sanitize_email( (string) get_option( \DoctorAKPortal\Frontend\Site_Footer::OPTION_EMAIL, '' ) );

		return array(
			'phone' => trim( (string) get_option( \DoctorAKPortal\Frontend\Site_Footer::OPTION_PHONE, '0303-3638304' ) ),
			'email' => '' !== $email ? $email : self::first_saved_email(),
		);
	}

	/**
	 * The first contact email saved on any clinic (read straight from the
	 * table — decode_row() itself reports the website email).
	 *
	 * @return string
	 */
	private static function first_saved_email() {
		static $email = null;

		if ( null === $email ) {
			global $wpdb;

			$email = (string) $wpdb->get_var( 'SELECT contact_email FROM ' . self::table_name() . " WHERE contact_email <> '' ORDER BY id ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name, not user input.
		}

		return $email;
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

		if ( ! Locations::is_valid_area_choice( $country, $city, $area ) ) {
			return new \WP_Error( 'doctor_ak_clinic_location_area_required', __( 'Please select this clinic\'s area.', 'doctor-ak-portal' ) );
		}

		$address = isset( $posted['address'] ) ? sanitize_text_field( wp_unslash( $posted['address'] ) ) : '';

		// Phone and email aren't per clinic: every clinic uses the website's
		// own (see website_contact()), whatever the form sent.
		$website       = self::website_contact();
		$phone         = $website['phone'];
		$contact_email = $website['email'];

		$coordinates = self::parse_coordinates( isset( $posted['coordinates'] ) ? wp_unslash( $posted['coordinates'] ) : '' );

		if ( is_wp_error( $coordinates ) ) {
			return $coordinates;
		}

		return array(
			'name'          => $name,
			'address'       => $address,
			'country'       => $country,
			'city'          => $city,
			'area'          => $area,
			'phone'         => $phone,
			'contact_email' => $contact_email,
			'keywords'      => self::sanitize_keywords( isset( $posted['keywords'] ) ? wp_unslash( $posted['keywords'] ) : '' ),
			'latitude'      => $coordinates ? $coordinates[0] : null,
			'longitude'     => $coordinates ? $coordinates[1] : null,
		);
	}

	/**
	 * Parses the optional "Map coordinates" field — "24.8138, 67.0299", the
	 * format Google Maps copies when you right-click a spot. Used only to
	 * work out approximate straight-line distances (e.g. the Symptom
	 * Checker's "nearby doctors"); a clinic without them is simply listed
	 * without a distance.
	 *
	 * @param string $raw Raw input.
	 * @return array|null|\WP_Error [ lat, lng ], null when left empty, or an error.
	 */
	public static function parse_coordinates( $raw ) {
		$raw = trim( (string) $raw );

		if ( '' === $raw ) {
			return null;
		}

		if ( ! preg_match( '/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,\s]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $raw, $m ) ) {
			return new \WP_Error( 'doctor_ak_clinic_location_coordinates_invalid', __( 'Please enter map coordinates as "latitude, longitude", e.g. 24.8138, 67.0299 — or leave the field empty.', 'doctor-ak-portal' ) );
		}

		$lat = (float) $m[1];
		$lng = (float) $m[2];

		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ( 0.0 === $lat && 0.0 === $lng ) ) {
			return new \WP_Error( 'doctor_ak_clinic_location_coordinates_range', __( 'Those map coordinates are out of range. Latitude goes from -90 to 90 and longitude from -180 to 180.', 'doctor-ak-portal' ) );
		}

		return array( round( $lat, 7 ), round( $lng, 7 ) );
	}

	/**
	 * Sanitizes the admin-only "Search Keywords" field into a clean,
	 * comma-separated string — never shown to patients (see the class
	 * docblock's keywords note and templates/modal/admin-clinic-location-
	 * modal.php), only matched against in the public hero search popup's
	 * "Clinics" results (see Home_Page::render()/doctor-ak-home.js) so an
	 * admin can make a clinic findable by things visitors would actually
	 * type ("parking", "wheelchair accessible", a nearby landmark, an old
	 * clinic name) without cluttering the clinic's own public listing.
	 *
	 * @param string $raw Raw comma-separated input.
	 * @return string
	 */
	private static function sanitize_keywords( $raw ) {
		$pieces = array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) );

		return implode( ', ', array_map( 'sanitize_text_field', $pieces ) );
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
				'keywords'      => isset( $fields['keywords'] ) ? $fields['keywords'] : '',
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			self::save_coordinates( (int) $wpdb->insert_id, $fields );
		}

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
				'keywords'      => isset( $fields['keywords'] ) ? $fields['keywords'] : '',
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			self::save_coordinates( (int) $id, $fields );
		}

		return false !== $updated;
	}

	/**
	 * Writes (or clears) a row's coordinates. A separate query so NULL is
	 * stored for "not set" rather than 0,0.
	 *
	 * @param int   $id     Row ID.
	 * @param array $fields Sanitized fields (latitude/longitude may be absent or null).
	 * @return void
	 */
	private static function save_coordinates( $id, array $fields ) {
		global $wpdb;

		if ( ! array_key_exists( 'latitude', $fields ) ) {
			return;
		}

		if ( null === $fields['latitude'] || null === $fields['longitude'] ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table_name() . ' SET latitude = NULL, longitude = NULL WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			return;
		}

		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table_name() . ' SET latitude = %f, longitude = %f WHERE id = %d', $fields['latitude'], $fields['longitude'], $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
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

		$country_label = '' !== $country ? Locations::country_label( $country ) : '';
		$city_label    = '' !== $city ? Locations::city_label( $country, $city ) : '';
		$area_label    = '' !== $area ? Locations::area_label( $country, $city, $area ) : '';

		// Every clinic shows the website's phone and email, including ones
		// saved before that rule (their old values stay in the table).
		$website = self::website_contact();

		return array(
			'id'            => (int) $row['id'],
			'name'          => $row['name'],
			'address'       => $row['address'],
			'country'       => $country,
			'country_label' => $country_label,
			'city'          => $city,
			'city_label'    => $city_label,
			'area'          => $area,
			'area_label'    => $area_label,
			'phone'         => $website['phone'],
			'contact_email' => $website['email'],
			'keywords'      => isset( $row['keywords'] ) ? $row['keywords'] : '',
			'map_url'       => self::map_url( $row['name'], $row['address'], $area_label, $city_label, $country_label ),
			// Optional — null when the admin hasn't entered map coordinates.
			'latitude'      => isset( $row['latitude'] ) && null !== $row['latitude'] && '' !== $row['latitude'] ? (float) $row['latitude'] : null,
			'longitude'     => isset( $row['longitude'] ) && null !== $row['longitude'] && '' !== $row['longitude'] ? (float) $row['longitude'] : null,
		);
	}

	/**
	 * A Google Maps search link for a clinic — no stored coordinates, just
	 * everything we know about where it is, so a visitor can tap through to
	 * see it on a map/get directions. Google's search endpoint geocodes the
	 * free-text query itself; no API key needed.
	 *
	 * @param string $name          Clinic name.
	 * @param string $address       Street address, or ''.
	 * @param string $area_label    Area label, or ''.
	 * @param string $city_label    City label, or ''.
	 * @param string $country_label Country label, or ''.
	 * @return string Empty when there's nothing usable to search for (bare name only, no location at all).
	 */
	public static function map_url( $name, $address, $area_label, $city_label, $country_label ) {
		$location_parts = array_filter( array( $address, $area_label, $city_label, $country_label ) );

		if ( empty( $location_parts ) ) {
			return '';
		}

		$query = trim( $name . ' ' . implode( ', ', $location_parts ) );

		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query );
	}

	/* ---------------------------------------------------------------------
	 * Duplicates
	 * ------------------------------------------------------------------- */

	/**
	 * A clinic name reduced to what identifies it: lowercase letters and
	 * digits only, so "Darul Sehat Hospital", "Darul sehat  Hospital" and
	 * "Darul-Sehat Hospital." all compare equal.
	 *
	 * @param string $name Clinic name.
	 * @return string
	 */
	public static function normalize_name( $name ) {
		return preg_replace( '/[^a-z0-9]+/', '', strtolower( remove_accents( (string) $name ) ) );
	}

	/**
	 * An existing clinic that the given fields would duplicate — same
	 * normalized name in the same country, city and area — or null. Used to
	 * stop the same clinic being added (or edited into) a second time.
	 *
	 * @param array $fields     Sanitized fields (name, country, city, area).
	 * @param int   $exclude_id The clinic being edited, so it doesn't match itself.
	 * @return array|null Decoded row of the existing clinic.
	 */
	public static function find_duplicate( array $fields, $exclude_id = 0 ) {
		$key = self::normalize_name( $fields['name'] );

		if ( '' === $key ) {
			return null;
		}

		foreach ( self::get_all() as $row ) {
			if ( (int) $row['id'] === (int) $exclude_id ) {
				continue;
			}

			if ( $key === self::normalize_name( $row['name'] )
				&& (string) $row['country'] === (string) $fields['country']
				&& (string) $row['city'] === (string) $fields['city']
				&& (string) $row['area'] === (string) $fields['area']
			) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Clinics that look like the same place entered more than once: same
	 * normalized name in the same country and city (area is ignored here,
	 * since a wrong area is a common way the same clinic got re-entered).
	 * Each row gets a 'doctors_count' so the admin can see which copy is
	 * actually in use before choosing the one to keep.
	 *
	 * @return array[] List of groups, each a list of decoded rows (2+), most-used first.
	 */
	public static function duplicate_groups() {
		global $wpdb;

		$groups = array();

		foreach ( self::get_all() as $row ) {
			$key = self::normalize_name( $row['name'] );

			if ( '' === $key ) {
				continue;
			}

			$groups[ $key . '|' . $row['country'] . '|' . $row['city'] ][] = $row;
		}

		$groups = array_values(
			array_filter(
				$groups,
				function ( $rows ) {
					return count( $rows ) > 1;
				}
			)
		);

		if ( empty( $groups ) ) {
			return array();
		}

		$counts = array();
		$rows   = $wpdb->get_results( 'SELECT clinic_location_id, COUNT(DISTINCT doctor_id) AS n FROM ' . Clinics::table_name() . ' WHERE clinic_location_id > 0 GROUP BY clinic_location_id', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.

		foreach ( (array) $rows as $count_row ) {
			$counts[ (int) $count_row['clinic_location_id'] ] = (int) $count_row['n'];
		}

		foreach ( $groups as &$group ) {
			foreach ( $group as &$row ) {
				$row['doctors_count'] = isset( $counts[ $row['id'] ] ) ? $counts[ $row['id'] ] : 0;
			}
			unset( $row );

			usort(
				$group,
				function ( $a, $b ) {
					return $b['doctors_count'] <=> $a['doctors_count'] ?: $a['id'] <=> $b['id'];
				}
			);
		}
		unset( $group );

		return $groups;
	}

	/**
	 * Merges duplicate clinics into one: everything that pointed at a
	 * duplicate is moved to the kept clinic, then the duplicates are deleted.
	 *
	 * Moved: doctors' clinic schedules (Clinics rows — re-pointed and given
	 * the kept clinic's name/address), per-clinic service prices and
	 * doctor-share overrides (Services clinic_charges / clinic_shares, the
	 * kept clinic's own value winning where both have one), patients'
	 * assigned clinic and receptionists' assigned clinics (user meta).
	 * Blank contact details / coordinates on the kept clinic are filled from
	 * a duplicate, and the duplicates' spellings are added to its search
	 * keywords so they still find it.
	 *
	 * @param int   $keep_id   Clinic to keep.
	 * @param int[] $merge_ids Duplicates to fold into it.
	 * @return array|\WP_Error Summary { doctor_schedules, services, patients, receptionists, merged }, or WP_Error.
	 */
	public static function merge( $keep_id, array $merge_ids ) {
		global $wpdb;

		$keep_id   = (int) $keep_id;
		$merge_ids = array_values( array_diff( array_unique( array_filter( array_map( 'intval', $merge_ids ) ) ), array( $keep_id ) ) );
		$keep      = self::find( $keep_id );

		if ( ! $keep || empty( $merge_ids ) ) {
			return new \WP_Error( 'doctor_ak_clinic_merge_invalid', __( 'Choose the clinic to keep and at least one duplicate.', 'doctor-ak-portal' ) );
		}

		$duplicates = array();

		foreach ( $merge_ids as $merge_id ) {
			$duplicate = self::find( $merge_id );

			if ( ! $duplicate ) {
				return new \WP_Error( 'doctor_ak_clinic_merge_missing', __( 'One of those clinics no longer exists. Please refresh the page.', 'doctor-ak-portal' ) );
			}

			$duplicates[ $merge_id ] = $duplicate;
		}

		$id_list = implode( ',', $merge_ids ); // All intval'd above.
		$summary = array(
			'doctor_schedules' => 0,
			'services'         => 0,
			'patients'         => 0,
			'receptionists'    => 0,
			'merged'           => count( $merge_ids ),
		);

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// 1. Doctors' schedules at the duplicates now belong to the kept clinic.
		$moved = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Clinics::table_name() . " SET clinic_location_id = %d, name = %s, address = %s, country = %s, city = %s, area = %s, updated_at = %s WHERE clinic_location_id IN ({$id_list})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and integer list.
				$keep_id,
				$keep['name'],
				$keep['address'],
				$keep['country'],
				$keep['city'],
				$keep['area'],
				current_time( 'mysql' )
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $moved ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new \WP_Error( 'doctor_ak_clinic_merge_failed', __( 'The clinics could not be merged. Nothing was changed.', 'doctor-ak-portal' ) );
		}

		$summary['doctor_schedules'] = (int) $moved;

		// 2. Per-clinic prices and share overrides on services (JSON keyed by clinic ID).
		$services = $wpdb->get_results( 'SELECT id, clinic_charges, clinic_shares FROM ' . Services::table_name() . " WHERE ( clinic_charges IS NOT NULL AND clinic_charges NOT IN ( '', '[]', '{}' ) ) OR ( clinic_shares IS NOT NULL AND clinic_shares NOT IN ( '', '[]', '{}' ) )", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.

		foreach ( (array) $services as $service ) {
			$data = array();

			foreach ( array( 'clinic_charges', 'clinic_shares' ) as $column ) {
				$map = json_decode( (string) $service[ $column ], true );

				if ( ! is_array( $map ) ) {
					continue;
				}

				$changed = false;

				foreach ( $merge_ids as $merge_id ) {
					if ( ! array_key_exists( $merge_id, $map ) && ! array_key_exists( (string) $merge_id, $map ) ) {
						continue;
					}

					$value = $map[ $merge_id ];
					unset( $map[ $merge_id ] );

					if ( ! array_key_exists( $keep_id, $map ) ) {
						$map[ $keep_id ] = $value;
					}

					$changed = true;
				}

				if ( $changed ) {
					$data[ $column ] = wp_json_encode( $map );
				}
			}

			if ( $data ) {
				$wpdb->update( Services::table_name(), $data, array( 'id' => (int) $service['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				++$summary['services'];
			}
		}

		// 3. Patients' assigned clinic (a single ID). update_user_meta() rather
		// than one UPDATE, so a persistent object cache sees the change too.
		$patient_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value IN ('" . implode( "','", $merge_ids ) . "')", self::PATIENT_META_KEY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer list.

		foreach ( (array) $patient_ids as $user_id ) {
			update_user_meta( (int) $user_id, self::PATIENT_META_KEY, $keep_id );
			++$summary['patients'];
		}

		// 4. Receptionists' assigned clinics (a list of IDs).
		$receptionist_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", self::RECEPTIONIST_META_KEY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( (array) $receptionist_ids as $user_id ) {
			$assigned = array_map( 'intval', (array) get_user_meta( (int) $user_id, self::RECEPTIONIST_META_KEY, true ) );

			if ( ! array_intersect( $assigned, $merge_ids ) ) {
				continue;
			}

			$updated = array();

			foreach ( $assigned as $location_id ) {
				$updated[] = in_array( $location_id, $merge_ids, true ) ? $keep_id : $location_id;
			}

			update_user_meta( (int) $user_id, self::RECEPTIONIST_META_KEY, array_values( array_unique( $updated ) ) );
			++$summary['receptionists'];
		}

		// 5. Fill blanks on the kept clinic, keep the old spellings searchable.
		$fill     = array();
		$keywords = array_filter( array_map( 'trim', explode( ',', (string) $keep['keywords'] ) ) );

		foreach ( $duplicates as $duplicate ) {
			foreach ( array( 'address', 'phone', 'contact_email' ) as $field ) {
				if ( '' === (string) $keep[ $field ] && ! isset( $fill[ $field ] ) && '' !== (string) $duplicate[ $field ] ) {
					$fill[ $field ] = $duplicate[ $field ];
				}
			}

			if ( null === $keep['latitude'] && ! isset( $fill['latitude'] ) && null !== $duplicate['latitude'] ) {
				$fill['latitude']  = $duplicate['latitude'];
				$fill['longitude'] = $duplicate['longitude'];
			}

			if ( $duplicate['name'] !== $keep['name'] ) {
				$keywords[] = $duplicate['name'];
			}

			$keywords = array_merge( $keywords, array_filter( array_map( 'trim', explode( ',', (string) $duplicate['keywords'] ) ) ) );
		}

		$fill['keywords']   = implode( ', ', array_unique( $keywords ) );
		$fill['updated_at'] = current_time( 'mysql' );

		$wpdb->update( self::table_name(), $fill, array( 'id' => $keep_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// 6. The duplicates themselves.
		$deleted = $wpdb->query( 'DELETE FROM ' . self::table_name() . " WHERE id IN ({$id_list})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and integer list.

		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new \WP_Error( 'doctor_ak_clinic_merge_failed', __( 'The clinics could not be merged. Nothing was changed.', 'doctor-ak-portal' ) );
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $summary;
	}
}
