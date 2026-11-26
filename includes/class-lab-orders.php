<?php
/**
 * Lab tests a doctor suggested at a visit, and the patient's booking of them.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lab_Orders
 *
 * One order per encounter, holding the tests the doctor suggested
 * (dak_lab_order_items — each snapshots the test's name, and its price once
 * booked). The lifecycle:
 *
 *   suggested → booked → collected → ready
 *                  ↘ (patient cancels: back to suggested)
 *   any open status → cancelled (staff)
 *
 * The doctor can change the tests only while the order is "suggested". The
 * patient books it from their portal (a partner lab, at the lab or home
 * collection, a day and a time window) and pays at collection; staff mark
 * the sample collected and upload the results, which also land in the
 * encounter's Reports.
 */
class Lab_Orders {

	const TABLE       = 'dak_lab_orders';
	const ITEMS_TABLE = 'dak_lab_order_items';

	const STATUS_SUGGESTED = 'suggested';
	const STATUS_BOOKED    = 'booked';
	const STATUS_COLLECTED = 'collected';
	const STATUS_READY     = 'ready';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * How many days ahead a patient may book.
	 *
	 * @var int
	 */
	const BOOK_AHEAD_DAYS = 14;

	/**
	 * User meta remembering the patient's last home-collection address.
	 *
	 * @var string
	 */
	const ADDRESS_META_KEY = 'doctor_ak_lab_collection_address';

	/**
	 * Orders table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Items table name.
	 *
	 * @return string
	 */
	public static function items_table_name() {
		global $wpdb;

		return $wpdb->prefix . self::ITEMS_TABLE;
	}

	/**
	 * Statuses, slug => label.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_SUGGESTED => __( 'To book', 'doctor-ak-portal' ),
			self::STATUS_BOOKED    => __( 'Booked', 'doctor-ak-portal' ),
			self::STATUS_COLLECTED => __( 'Sample collected', 'doctor-ak-portal' ),
			self::STATUS_READY     => __( 'Results ready', 'doctor-ak-portal' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Collection time windows, slug => label.
	 *
	 * @return array
	 */
	public static function time_windows() {
		return array(
			'morning'   => __( 'Morning (8 am – 12 pm)', 'doctor-ak-portal' ),
			'afternoon' => __( 'Afternoon (12 – 4 pm)', 'doctor-ak-portal' ),
			'evening'   => __( 'Evening (4 – 8 pm)', 'doctor-ak-portal' ),
		);
	}

	/**
	 * The encounter's order (not cancelled), or null.
	 *
	 * @param int $encounter_id Encounter ID.
	 * @return array|null Decoded order with items.
	 */
	public static function for_encounter( $encounter_id ) {
		global $wpdb;

		$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT id FROM ' . self::table_name() . ' WHERE encounter_id = %d AND status <> %s ORDER BY id DESC LIMIT 1', (int) $encounter_id, self::STATUS_CANCELLED ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		);

		return $id > 0 ? self::find( $id ) : null;
	}

	/**
	 * Adds a suggested test to an encounter's order, creating the order on
	 * the first one. A test already on the order isn't added twice.
	 *
	 * @param array  $encounter Decoded encounter row.
	 * @param array  $test      Decoded Lab_Tests row.
	 * @param string $note      Doctor's note for the patient (optional).
	 * @return array|\WP_Error { order_id, created (bool: the order is new) }.
	 */
	public static function suggest_test( array $encounter, array $test, $note = '' ) {
		global $wpdb;

		$order   = self::for_encounter( $encounter['id'] );
		$created = false;

		if ( $order && self::STATUS_SUGGESTED !== $order['status'] ) {
			return new \WP_Error( 'doctor_ak_lab_order_locked', __( 'The patient has already booked these tests, so they can’t be changed here.', 'doctor-ak-portal' ) );
		}

		if ( ! $order ) {
			$now = current_time( 'mysql' );

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table_name(),
				array(
					'patient_id'     => (int) $encounter['patient_id'],
					'doctor_id'      => (int) $encounter['doctor_id'],
					'encounter_id'   => (int) $encounter['id'],
					'appointment_id' => (int) $encounter['appointment_id'],
					'status'         => self::STATUS_SUGGESTED,
					'created_at'     => $now,
					'updated_at'     => $now,
				)
			);

			$order_id = (int) $wpdb->insert_id;
			$created  = true;
		} else {
			$order_id = $order['id'];

			foreach ( $order['items'] as $item ) {
				if ( $item['test_id'] === (int) $test['id'] ) {
					return array( 'order_id' => $order_id, 'created' => false );
				}
			}
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::items_table_name(),
			array(
				'order_id'  => $order_id,
				'test_id'   => (int) $test['id'],
				'test_name' => $test['name'],
				'price'     => 0,
				'note'      => mb_substr( (string) $note, 0, 255 ),
			)
		);

		self::touch( $order_id );

		return array( 'order_id' => $order_id, 'created' => $created );
	}

	/**
	 * A patient's own order — tests they picked themselves on the website's
	 * "Book a lab test" page, with no doctor or visit behind it. Created as
	 * "to book"; Lab_Tests_Page books it straight away.
	 *
	 * @param int   $patient_id Patient user ID.
	 * @param int[] $test_ids   Chosen tests.
	 * @return int|\WP_Error New order ID.
	 */
	public static function create_for_patient( $patient_id, array $test_ids ) {
		global $wpdb;

		$tests = array_filter(
			Lab_Tests::find_many( $test_ids ),
			function ( $test ) {
				return $test['active'];
			}
		);

		if ( (int) $patient_id <= 0 || empty( $tests ) ) {
			return new \WP_Error( 'doctor_ak_lab_no_tests', __( 'Please choose at least one test.', 'doctor-ak-portal' ) );
		}

		$now = current_time( 'mysql' );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table_name(),
			array(
				'patient_id' => (int) $patient_id,
				'status'     => self::STATUS_SUGGESTED,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		$order_id = (int) $wpdb->insert_id;

		foreach ( $tests as $test ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::items_table_name(),
				array(
					'order_id'  => $order_id,
					'test_id'   => $test['id'],
					'test_name' => $test['name'],
					'price'     => 0,
					'note'      => '',
				)
			);
		}

		return $order_id;
	}

	/**
	 * Deletes an order and its items (a self-booking that couldn't be booked).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function delete( $order_id ) {
		global $wpdb;

		$wpdb->delete( self::items_table_name(), array( 'order_id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::table_name(), array( 'id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Removes a suggested test; an order left with none is deleted.
	 *
	 * @param int $item_id      Item ID.
	 * @param int $encounter_id The encounter it must belong to.
	 * @return true|\WP_Error
	 */
	public static function remove_test( $item_id, $encounter_id ) {
		global $wpdb;

		$order = self::for_encounter( $encounter_id );

		if ( ! $order ) {
			return true;
		}

		if ( self::STATUS_SUGGESTED !== $order['status'] ) {
			return new \WP_Error( 'doctor_ak_lab_order_locked', __( 'The patient has already booked these tests, so they can’t be changed here.', 'doctor-ak-portal' ) );
		}

		$wpdb->delete( self::items_table_name(), array( 'id' => (int) $item_id, 'order_id' => $order['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( empty( self::items( $order['id'] ) ) ) {
			$wpdb->delete( self::table_name(), array( 'id' => $order['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			self::touch( $order['id'] );
		}

		return true;
	}

	/**
	 * The patient books a suggested order.
	 *
	 * @param int   $order_id   Order ID.
	 * @param int   $patient_id Must own the order.
	 * @param array $args { lab_id, collection 'lab'|'home', date 'Y-m-d', time_window, address, notes }.
	 * @return true|\WP_Error
	 */
	public static function book( $order_id, $patient_id, array $args ) {
		global $wpdb;

		$order = self::find( $order_id );

		if ( ! $order || (int) $patient_id <= 0 || $order['patient_id'] !== (int) $patient_id ) {
			return new \WP_Error( 'doctor_ak_lab_order_not_found', __( 'These lab tests could not be found.', 'doctor-ak-portal' ) );
		}

		if ( self::STATUS_SUGGESTED !== $order['status'] ) {
			return new \WP_Error( 'doctor_ak_lab_order_booked', __( 'These lab tests are already booked.', 'doctor-ak-portal' ) );
		}

		$lab_id = isset( $args['lab_id'] ) ? (int) $args['lab_id'] : 0;
		$quote  = null;

		foreach ( Labs::quotes_for_tests( wp_list_pluck( $order['items'], 'test_id' ) ) as $candidate ) {
			if ( $candidate['lab']['id'] === $lab_id ) {
				$quote = $candidate;
				break;
			}
		}

		if ( ! $quote || ! $quote['covers_all'] ) {
			return new \WP_Error( 'doctor_ak_lab_unavailable', __( 'Please choose a lab that offers all of these tests.', 'doctor-ak-portal' ) );
		}

		$collection = isset( $args['collection'] ) && 'home' === $args['collection'] ? 'home' : 'lab';
		$address    = isset( $args['address'] ) ? mb_substr( trim( (string) $args['address'] ), 0, 255 ) : '';

		if ( 'home' === $collection ) {
			if ( ! $quote['lab']['home_collection'] ) {
				return new \WP_Error( 'doctor_ak_lab_no_home', __( 'This lab doesn’t collect samples at home. Please choose “Visit the lab”.', 'doctor-ak-portal' ) );
			}

			if ( '' === $address ) {
				return new \WP_Error( 'doctor_ak_lab_address_required', __( 'Please enter the address for home sample collection.', 'doctor-ak-portal' ) );
			}
		}

		$date  = isset( $args['date'] ) ? (string) $args['date'] : '';
		$today = current_time( 'Y-m-d' );
		$last  = gmdate( 'Y-m-d', strtotime( $today . ' +' . self::BOOK_AHEAD_DAYS . ' days' ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $date < $today || $date > $last ) {
			return new \WP_Error( 'doctor_ak_lab_date_invalid', __( 'Please choose a day within the next two weeks.', 'doctor-ak-portal' ) );
		}

		$window = isset( $args['time_window'] ) ? (string) $args['time_window'] : '';

		if ( ! array_key_exists( $window, self::time_windows() ) ) {
			return new \WP_Error( 'doctor_ak_lab_time_invalid', __( 'Please choose a time.', 'doctor-ak-portal' ) );
		}

		// Today's earlier windows are gone.
		if ( $date === $today ) {
			$hour = (int) current_time( 'G' );
			$ends = array( 'morning' => 12, 'afternoon' => 16, 'evening' => 20 );

			if ( $hour >= $ends[ $window ] ) {
				return new \WP_Error( 'doctor_ak_lab_time_past', __( 'That time has already passed today. Please choose a later time or another day.', 'doctor-ak-portal' ) );
			}
		}

		// Snapshot each test's price at this lab.
		foreach ( $order['items'] as $item ) {
			$wpdb->update( self::items_table_name(), array( 'price' => $quote['prices'][ $item['test_id'] ] ), array( 'id' => $item['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		$home_fee = 'home' === $collection ? (float) $quote['lab']['home_collection_fee'] : 0.0;
		$now      = current_time( 'mysql' );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table_name(),
			array(
				'lab_id'         => $lab_id,
				'status'         => self::STATUS_BOOKED,
				'collection'     => $collection,
				'preferred_date' => $date,
				'time_window'    => $window,
				'address'        => 'home' === $collection ? $address : '',
				'patient_notes'  => isset( $args['notes'] ) ? mb_substr( trim( (string) $args['notes'] ), 0, 500 ) : '',
				'home_fee'       => $home_fee,
				'total'          => $quote['subtotal'] + $home_fee,
				'booked_at'      => $now,
				'updated_at'     => $now,
			),
			array( 'id' => $order['id'] )
		);

		if ( 'home' === $collection ) {
			update_user_meta( (int) $patient_id, self::ADDRESS_META_KEY, $address );
		}

		return true;
	}

	/**
	 * The patient cancels a booking that hasn't been collected yet — the
	 * tests go back to "to book", so they can choose again.
	 *
	 * @param int $order_id   Order ID.
	 * @param int $patient_id Must own the order.
	 * @return true|\WP_Error
	 */
	public static function cancel_booking( $order_id, $patient_id ) {
		global $wpdb;

		$order = self::find( $order_id );

		if ( ! $order || (int) $patient_id <= 0 || $order['patient_id'] !== (int) $patient_id ) {
			return new \WP_Error( 'doctor_ak_lab_order_not_found', __( 'These lab tests could not be found.', 'doctor-ak-portal' ) );
		}

		if ( self::STATUS_BOOKED !== $order['status'] ) {
			return new \WP_Error( 'doctor_ak_lab_order_not_cancellable', __( 'The sample has already been collected, so this booking can’t be cancelled here. Please call us.', 'doctor-ak-portal' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table_name(),
			array(
				'lab_id'         => 0,
				'status'         => self::STATUS_SUGGESTED,
				'collection'     => '',
				'preferred_date' => null,
				'time_window'    => '',
				'address'        => '',
				'home_fee'       => 0,
				'total'          => 0,
				'booked_at'      => null,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $order['id'] )
		);

		$wpdb->update( self::items_table_name(), array( 'price' => 0 ), array( 'order_id' => $order['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return true;
	}

	/**
	 * Staff move an order along: "collected" (from booked) or "cancelled"
	 * (from any status but ready).
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   self::STATUS_COLLECTED or self::STATUS_CANCELLED.
	 * @return true|\WP_Error
	 */
	public static function set_status( $order_id, $status ) {
		global $wpdb;

		$order = self::find( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'doctor_ak_lab_order_not_found', __( 'That lab order could not be found.', 'doctor-ak-portal' ) );
		}

		$now    = current_time( 'mysql' );
		$fields = array( 'updated_at' => $now );

		if ( self::STATUS_COLLECTED === $status && self::STATUS_BOOKED === $order['status'] ) {
			$fields['status']       = self::STATUS_COLLECTED;
			$fields['collected_at'] = $now;
		} elseif ( self::STATUS_CANCELLED === $status && self::STATUS_READY !== $order['status'] && self::STATUS_CANCELLED !== $order['status'] ) {
			$fields['status'] = self::STATUS_CANCELLED;
		} else {
			return new \WP_Error( 'doctor_ak_lab_status_invalid', __( 'That change isn’t possible for this order.', 'doctor-ak-portal' ) );
		}

		$wpdb->update( self::table_name(), $fields, array( 'id' => $order['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return true;
	}

	/**
	 * Records payment taken at collection.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function mark_paid( $order_id ) {
		global $wpdb;

		$wpdb->update( self::table_name(), array( 'is_paid' => 1, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Staff attach the results file: the order becomes "ready", and the file
	 * is also added to the encounter's Reports so the doctor sees it there.
	 *
	 * @param int $order_id      Order ID.
	 * @param int $attachment_id Uploaded file's attachment ID.
	 * @param int $uploaded_by   Staff user ID.
	 * @return true|\WP_Error
	 */
	public static function attach_results( $order_id, $attachment_id, $uploaded_by ) {
		global $wpdb;

		$order = self::find( $order_id );

		if ( ! $order || ! in_array( $order['status'], array( self::STATUS_BOOKED, self::STATUS_COLLECTED, self::STATUS_READY ), true ) ) {
			return new \WP_Error( 'doctor_ak_lab_status_invalid', __( 'Results can only be added once the tests are booked.', 'doctor-ak-portal' ) );
		}

		$now = current_time( 'mysql' );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table_name(),
			array(
				'status'               => self::STATUS_READY,
				'report_attachment_id' => (int) $attachment_id,
				'collected_at'         => $order['collected_at'] ? $order['collected_at'] : $now,
				'ready_at'             => $now,
				'updated_at'           => $now,
			),
			array( 'id' => $order['id'] )
		);

		if ( $order['encounter_id'] > 0 ) {
			Encounter_Reports::add( $order['encounter_id'], (int) $attachment_id, (int) $uploaded_by );
		}

		return true;
	}

	/**
	 * One order with its items.
	 *
	 * @param int $id Order ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return $row ? self::decode( $row ) : null;
	}

	/**
	 * An order's items.
	 *
	 * @param int $order_id Order ID.
	 * @return array List of { id, test_id, test_name, price, note }.
	 */
	public static function items( $order_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::items_table_name() . ' WHERE order_id = %d ORDER BY id ASC', (int) $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_map(
			function ( $row ) {
				return array(
					'id'        => (int) $row['id'],
					'test_id'   => (int) $row['test_id'],
					'test_name' => (string) $row['test_name'],
					'price'     => (float) $row['price'],
					'note'      => (string) $row['note'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * A patient's orders, newest first (cancelled ones last).
	 *
	 * @param int $patient_id Patient user ID.
	 * @return array
	 */
	public static function for_patient( $patient_id ) {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT id FROM ' . self::table_name() . ' WHERE patient_id = %d ORDER BY (status = %s) ASC, id DESC', (int) $patient_id, self::STATUS_CANCELLED ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		);

		return array_values( array_filter( array_map( array( __CLASS__, 'find' ), (array) $ids ) ) );
	}

	/**
	 * How many of a patient's orders still need booking (for the menu badge).
	 *
	 * @param int $patient_id Patient user ID.
	 * @return int
	 */
	public static function to_book_count( $patient_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE patient_id = %d AND status = %s', (int) $patient_id, self::STATUS_SUGGESTED ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		);
	}

	/**
	 * The tests doctors suggest most, for one-tap chips in the encounter.
	 *
	 * @param int $limit How many.
	 * @return int[] Test IDs, most suggested first.
	 */
	public static function popular_test_ids( $limit = 8 ) {
		global $wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( 'SELECT test_id FROM ' . self::items_table_name() . ' WHERE test_id > 0 GROUP BY test_id ORDER BY COUNT(*) DESC, test_id ASC LIMIT %d', (int) $limit ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
			)
		);
	}

	/**
	 * Order counts by status, slug => count (every status present).
	 *
	 * @return array
	 */
	public static function status_counts() {
		global $wpdb;

		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );
		$rows   = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . self::table_name() . ' GROUP BY status', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['total'];
			}
		}

		return $counts;
	}

	/**
	 * Orders for the staff list.
	 *
	 * @param array $args { status ('' = all), search (patient name/phone), page, per_page }.
	 * @return array { rows, total }.
	 */
	public static function admin_query( array $args ) {
		global $wpdb;

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$where    = array( '1=1' );
		$params   = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'o.status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '( u.display_name LIKE %s OR EXISTS ( SELECT 1 FROM ' . $wpdb->usermeta . " m WHERE m.user_id = o.patient_id AND m.meta_key IN ( 'first_name', 'last_name', 'doctor_ak_phone_number' ) AND m.meta_value LIKE %s ) )";
			$params[] = $like;
			$params[] = $like;
		}

		$from = ' FROM ' . self::table_name() . ' o LEFT JOIN ' . $wpdb->users . ' u ON u.ID = o.patient_id WHERE ' . implode( ' AND ', $where );

		// Soonest collection first for live work; newest first otherwise.
		$order_by = ' ORDER BY ( o.preferred_date IS NULL ) ASC, o.preferred_date ASC, o.id DESC';

		if ( empty( $args['status'] ) || in_array( $args['status'], array( self::STATUS_READY, self::STATUS_CANCELLED, self::STATUS_SUGGESTED ), true ) ) {
			$order_by = ' ORDER BY o.id DESC';
		}

		$count_sql = 'SELECT COUNT(*)' . $from;
		$rows_sql  = 'SELECT o.id' . $from . $order_by . ' LIMIT %d OFFSET %d';

		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$ids   = $wpdb->get_col( $wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		return array(
			'rows'  => array_values( array_filter( array_map( array( __CLASS__, 'find' ), (array) $ids ) ) ),
			'total' => $total,
		);
	}

	/**
	 * Bumps updated_at.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private static function touch( $order_id ) {
		global $wpdb;

		$wpdb->update( self::table_name(), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Casts a raw row and attaches its items.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	private static function decode( array $row ) {
		$statuses = self::statuses();
		$windows  = self::time_windows();
		$report   = (int) $row['report_attachment_id'];

		return array(
			'id'                => (int) $row['id'],
			'patient_id'        => (int) $row['patient_id'],
			'doctor_id'         => (int) $row['doctor_id'],
			'encounter_id'      => (int) $row['encounter_id'],
			'appointment_id'    => (int) $row['appointment_id'],
			'lab_id'            => (int) $row['lab_id'],
			'status'            => (string) $row['status'],
			'status_label'      => isset( $statuses[ $row['status'] ] ) ? $statuses[ $row['status'] ] : (string) $row['status'],
			'collection'        => (string) $row['collection'],
			'preferred_date'    => $row['preferred_date'] ? (string) $row['preferred_date'] : '',
			'time_window'       => (string) $row['time_window'],
			'time_window_label' => isset( $windows[ $row['time_window'] ] ) ? $windows[ $row['time_window'] ] : '',
			'address'           => (string) $row['address'],
			'patient_notes'     => (string) $row['patient_notes'],
			'home_fee'          => (float) $row['home_fee'],
			'total'             => (float) $row['total'],
			'is_paid'           => ! empty( $row['is_paid'] ),
			'report_attachment_id' => $report,
			'report_url'        => $report > 0 ? (string) wp_get_attachment_url( $report ) : '',
			'booked_at'         => $row['booked_at'] ? (string) $row['booked_at'] : '',
			'collected_at'      => $row['collected_at'] ? (string) $row['collected_at'] : '',
			'ready_at'          => $row['ready_at'] ? (string) $row['ready_at'] : '',
			'created_at'        => (string) $row['created_at'],
			'items'             => self::items( (int) $row['id'] ),
		);
	}
}
