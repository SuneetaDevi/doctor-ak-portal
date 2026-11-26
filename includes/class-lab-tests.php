<?php
/**
 * The catalog of lab tests doctors can suggest and partner labs can price.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lab_Tests
 *
 * One row per test (e.g. "Complete Blood Count (CBC)"), shared by every
 * partner lab — each lab sets its own price for the tests it offers (see
 * Labs::set_prices()). A test is never deleted once it might be on an order
 * (orders snapshot its name anyway); it's switched off instead.
 */
class Lab_Tests {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_lab_tests';

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
	 * Sample types, slug => label.
	 *
	 * @return array
	 */
	public static function sample_types() {
		return array(
			'blood' => __( 'Blood', 'doctor-ak-portal' ),
			'urine' => __( 'Urine', 'doctor-ak-portal' ),
			'stool' => __( 'Stool', 'doctor-ak-portal' ),
			'swab'  => __( 'Swab', 'doctor-ak-portal' ),
			'other' => __( 'Other', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Validates and sanitizes the Add/Edit Test form.
	 *
	 * @param array $posted Raw request array.
	 * @return array|\WP_Error
	 */
	public static function sanitize_from_request( array $posted ) {
		$name = isset( $posted['name'] ) ? sanitize_text_field( wp_unslash( $posted['name'] ) ) : '';

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_lab_test_name_required', __( 'Please enter the test name.', 'doctor-ak-portal' ) );
		}

		$sample_type = isset( $posted['sample_type'] ) ? sanitize_key( wp_unslash( $posted['sample_type'] ) ) : 'blood';

		return array(
			'name'        => mb_substr( $name, 0, 191 ),
			'sample_type' => array_key_exists( $sample_type, self::sample_types() ) ? $sample_type : 'other',
			'preparation' => isset( $posted['preparation'] ) ? mb_substr( sanitize_text_field( wp_unslash( $posted['preparation'] ) ), 0, 255 ) : '',
			'report_time' => isset( $posted['report_time'] ) ? mb_substr( sanitize_text_field( wp_unslash( $posted['report_time'] ) ), 0, 100 ) : '',
			'keywords'    => isset( $posted['keywords'] ) ? mb_substr( sanitize_text_field( wp_unslash( $posted['keywords'] ) ), 0, 255 ) : '',
			'active'      => empty( $posted['active'] ) ? 0 : 1,
		);
	}

	/**
	 * Adds a test.
	 *
	 * @param array $fields Sanitized fields.
	 * @return int|\WP_Error New test ID.
	 */
	public static function create( array $fields ) {
		global $wpdb;

		if ( self::name_taken( $fields['name'] ) ) {
			return new \WP_Error( 'doctor_ak_lab_test_duplicate', __( 'A test with this name already exists.', 'doctor-ak-portal' ) );
		}

		$now = current_time( 'mysql' );

		$wpdb->insert( self::table_name(), array_merge( $fields, array( 'created_at' => $now, 'updated_at' => $now ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a test.
	 *
	 * @param int   $id     Test ID.
	 * @param array $fields Sanitized fields.
	 * @return true|\WP_Error
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;

		if ( self::name_taken( $fields['name'], $id ) ) {
			return new \WP_Error( 'doctor_ak_lab_test_duplicate', __( 'A test with this name already exists.', 'doctor-ak-portal' ) );
		}

		$wpdb->update( self::table_name(), array_merge( $fields, array( 'updated_at' => current_time( 'mysql' ) ) ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return true;
	}

	/**
	 * Whether another test already has this name (case-insensitive).
	 *
	 * @param string $name       Name.
	 * @param int    $exclude_id Test being edited.
	 * @return bool
	 */
	private static function name_taken( $name, $exclude_id = 0 ) {
		global $wpdb;

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT id FROM ' . self::table_name() . ' WHERE LOWER(name) = LOWER(%s) AND id <> %d LIMIT 1', $name, (int) $exclude_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		);
	}

	/**
	 * One test.
	 *
	 * @param int $id Test ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Every test, alphabetical.
	 *
	 * @param bool $active_only Only switched-on tests.
	 * @return array
	 */
	public static function all( $active_only = false ) {
		global $wpdb;

		$where = $active_only ? ' WHERE active = 1' : '';
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . $where . ' ORDER BY name ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	/**
	 * Several tests by ID, keyed by ID.
	 *
	 * @param int[] $ids Test IDs.
	 * @return array
	 */
	public static function find_many( array $ids ) {
		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . ' WHERE id IN (' . implode( ',', $ids ) . ')', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- integer IDs.
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$test               = self::decode( $row );
			$out[ $test['id'] ] = $test;
		}

		return $out;
	}

	/**
	 * Casts a raw row.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	private static function decode( array $row ) {
		$types = self::sample_types();

		return array(
			'id'                => (int) $row['id'],
			'name'              => (string) $row['name'],
			'sample_type'       => (string) $row['sample_type'],
			'sample_type_label' => isset( $types[ $row['sample_type'] ] ) ? $types[ $row['sample_type'] ] : '',
			'preparation'       => (string) $row['preparation'],
			'report_time'       => (string) $row['report_time'],
			'keywords'          => (string) $row['keywords'],
			'active'            => ! empty( $row['active'] ),
		);
	}
}
