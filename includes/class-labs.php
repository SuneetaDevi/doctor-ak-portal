<?php
/**
 * Partner labs and what each charges per test.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Labs
 *
 * A partner lab (e.g. "Chughtai Lab — Clifton") with its own price for each
 * test it offers (dak_lab_test_prices: a missing row means "not offered"),
 * and whether it collects samples at home (with its own fee). Patients
 * compare these quotes when booking the tests a doctor suggested.
 */
class Labs {

	/**
	 * Labs table (without the WordPress prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_labs';

	/**
	 * Per-lab test prices table (without the WordPress prefix).
	 *
	 * @var string
	 */
	const PRICES_TABLE = 'dak_lab_test_prices';

	/**
	 * Labs table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Prices table name.
	 *
	 * @return string
	 */
	public static function prices_table_name() {
		global $wpdb;

		return $wpdb->prefix . self::PRICES_TABLE;
	}

	/**
	 * Validates and sanitizes the Add/Edit Lab form.
	 *
	 * @param array $posted Raw request array.
	 * @return array|\WP_Error
	 */
	public static function sanitize_from_request( array $posted ) {
		$name = isset( $posted['name'] ) ? sanitize_text_field( wp_unslash( $posted['name'] ) ) : '';

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_lab_name_required', __( 'Please enter the lab’s name.', 'doctor-ak-portal' ) );
		}

		$home = ! empty( $posted['home_collection'] );
		$fee  = isset( $posted['home_collection_fee'] ) ? (float) wp_unslash( $posted['home_collection_fee'] ) : 0;

		return array(
			'name'                => mb_substr( $name, 0, 191 ),
			'address'             => isset( $posted['address'] ) ? mb_substr( sanitize_text_field( wp_unslash( $posted['address'] ) ), 0, 255 ) : '',
			'city'                => isset( $posted['city'] ) ? mb_substr( sanitize_text_field( wp_unslash( $posted['city'] ) ), 0, 100 ) : '',
			'home_collection'     => $home ? 1 : 0,
			'home_collection_fee' => $home ? max( 0, round( $fee, 2 ) ) : 0,
			'active'              => empty( $posted['active'] ) ? 0 : 1,
		);
	}

	/**
	 * Sanitizes the per-test price grid: test_id => price; blank or 0 means
	 * the lab doesn't offer that test.
	 *
	 * @param mixed $posted Raw `prices` array.
	 * @return array test_id => price (> 0 only).
	 */
	public static function sanitize_prices( $posted ) {
		$prices = array();

		foreach ( (array) $posted as $test_id => $price ) {
			$price = is_scalar( $price ) ? trim( (string) wp_unslash( $price ) ) : '';

			if ( '' !== $price && is_numeric( $price ) && (float) $price > 0 && (int) $test_id > 0 ) {
				$prices[ (int) $test_id ] = round( (float) $price, 2 );
			}
		}

		return $prices;
	}

	/**
	 * Adds a lab.
	 *
	 * @param array $fields Sanitized fields.
	 * @return int New lab ID.
	 */
	public static function create( array $fields ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->insert( self::table_name(), array_merge( $fields, array( 'created_at' => $now, 'updated_at' => $now ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a lab.
	 *
	 * @param int   $id     Lab ID.
	 * @param array $fields Sanitized fields.
	 * @return void
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;

		$wpdb->update( self::table_name(), array_merge( $fields, array( 'updated_at' => current_time( 'mysql' ) ) ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Replaces a lab's whole price list.
	 *
	 * @param int   $lab_id Lab ID.
	 * @param array $prices test_id => price, see sanitize_prices().
	 * @return void
	 */
	public static function set_prices( $lab_id, array $prices ) {
		global $wpdb;

		$wpdb->delete( self::prices_table_name(), array( 'lab_id' => (int) $lab_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $prices as $test_id => $price ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::prices_table_name(),
				array(
					'lab_id'  => (int) $lab_id,
					'test_id' => (int) $test_id,
					'price'   => (float) $price,
				)
			);
		}
	}

	/**
	 * One lab's prices, test_id => price.
	 *
	 * @param int $lab_id Lab ID.
	 * @return array
	 */
	public static function prices_for_lab( $lab_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT test_id, price FROM ' . self::prices_table_name() . ' WHERE lab_id = %d', (int) $lab_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['test_id'] ] = (float) $row['price'];
		}

		return $out;
	}

	/**
	 * Every active lab's prices, lab_id => test_id => price — the public
	 * "Book a lab test" page compares labs in the browser from this.
	 *
	 * @return array
	 */
	public static function price_matrix() {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT p.lab_id, p.test_id, p.price FROM ' . self::prices_table_name() . ' p INNER JOIN ' . self::table_name() . ' l ON l.id = p.lab_id WHERE l.active = 1', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['lab_id'] ][ (int) $row['test_id'] ] = (float) $row['price'];
		}

		return $out;
	}

	/**
	 * How many tests each lab offers, lab_id => count.
	 *
	 * @return array
	 */
	public static function offered_counts() {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT lab_id, COUNT(*) AS total FROM ' . self::prices_table_name() . ' GROUP BY lab_id', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['lab_id'] ] = (int) $row['total'];
		}

		return $out;
	}

	/**
	 * One lab.
	 *
	 * @param int $id Lab ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Every lab, alphabetical.
	 *
	 * @param bool $active_only Only switched-on labs.
	 * @return array
	 */
	public static function all( $active_only = false ) {
		global $wpdb;

		$where = $active_only ? ' WHERE active = 1' : '';
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . $where . ' ORDER BY name ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	/**
	 * What each active lab would charge for a set of tests — the patient's
	 * choice when booking. Labs offering every test come first, cheapest
	 * first; labs missing some tests follow (they can't be booked for the
	 * whole set, but the patient sees why).
	 *
	 * @param int[] $test_ids Test IDs.
	 * @return array List of { lab (decoded row), prices: test_id => price, missing: test_id[], covers_all, subtotal }.
	 */
	public static function quotes_for_tests( array $test_ids ) {
		global $wpdb;

		$test_ids = array_values( array_unique( array_filter( array_map( 'intval', $test_ids ) ) ) );
		$labs     = self::all( true );

		if ( empty( $test_ids ) || empty( $labs ) ) {
			return array();
		}

		$rows = $wpdb->get_results( 'SELECT lab_id, test_id, price FROM ' . self::prices_table_name() . ' WHERE test_id IN (' . implode( ',', $test_ids ) . ')', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- integer IDs.
		$map  = array();

		foreach ( (array) $rows as $row ) {
			$map[ (int) $row['lab_id'] ][ (int) $row['test_id'] ] = (float) $row['price'];
		}

		$quotes = array();

		foreach ( $labs as $lab ) {
			$prices = isset( $map[ $lab['id'] ] ) ? $map[ $lab['id'] ] : array();

			if ( empty( $prices ) ) {
				continue; // Offers none of these tests.
			}

			$missing = array_values( array_diff( $test_ids, array_keys( $prices ) ) );

			$quotes[] = array(
				'lab'        => $lab,
				'prices'     => $prices,
				'missing'    => $missing,
				'covers_all' => empty( $missing ),
				'subtotal'   => array_sum( $prices ),
			);
		}

		usort(
			$quotes,
			function ( $a, $b ) {
				if ( $a['covers_all'] !== $b['covers_all'] ) {
					return $a['covers_all'] ? -1 : 1;
				}

				return $a['subtotal'] <=> $b['subtotal'];
			}
		);

		return $quotes;
	}

	/**
	 * Casts a raw row.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	private static function decode( array $row ) {
		return array(
			'id'                  => (int) $row['id'],
			'name'                => (string) $row['name'],
			'address'             => (string) $row['address'],
			'city'                => (string) $row['city'],
			'home_collection'     => ! empty( $row['home_collection'] ),
			'home_collection_fee' => (float) $row['home_collection_fee'],
			'active'              => ! empty( $row['active'] ),
		);
	}
}
