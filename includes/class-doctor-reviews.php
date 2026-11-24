<?php
/**
 * Patient reviews of doctors.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Reviews
 *
 * One review (1-5 stars + optional comment) per patient per doctor. Only a
 * patient with a completed appointment with that doctor may review.
 *
 * Reviews can also come from a doctor's shareable review link (see
 * Review_Links): those have no patient account (patient_id NULL), carry
 * the name the reviewer typed, and wait as 'pending' until an admin
 * publishes them. Only 'published' reviews are shown on the profile or
 * counted in its rating; an admin can hide any review (and show it again).
 */
class Doctor_Reviews {

	const TABLE       = 'dak_doctor_reviews';
	const COMMENT_MAX = 1000;
	const NAME_MAX    = 40;
	const CONTACT_MAX = 100;

	const STATUS_PUBLISHED = 'published';
	const STATUS_PENDING   = 'pending';
	const STATUS_HIDDEN    = 'hidden';

	const SOURCE_PATIENT = 'patient';
	const SOURCE_LINK    = 'link';

	/**
	 * Status slug => admin label.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_PENDING   => __( 'Waiting for approval', 'doctor-ak-portal' ),
			self::STATUS_PUBLISHED => __( 'Published', 'doctor-ak-portal' ),
			self::STATUS_HIDDEN    => __( 'Hidden', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Whether the patient has a completed appointment with the doctor.
	 *
	 * @param int $patient_id Patient user ID.
	 * @param int $doctor_id  Doctor user ID.
	 * @return bool
	 */
	public static function can_review( $patient_id, $doctor_id ) {
		if ( $patient_id < 1 || $doctor_id < 1 || $patient_id === $doctor_id ) {
			return false;
		}

		$found = get_posts(
			array(
				'post_type'      => Appointments::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- tiny lookup.
					'relation' => 'AND',
					array(
						'key'   => 'doctor_ak_appointment_doctor_id',
						'value' => $doctor_id,
					),
					array(
						'key'   => 'doctor_ak_appointment_patient_id',
						'value' => $patient_id,
					),
					array(
						'key'   => 'doctor_ak_appointment_status',
						'value' => Appointments::STATUS_COMPLETED,
					),
				),
			)
		);

		return ! empty( $found );
	}

	/**
	 * The patient's existing review of the doctor, or null.
	 *
	 * @param int $patient_id Patient user ID.
	 * @param int $doctor_id  Doctor user ID.
	 * @return array|null
	 */
	public static function find_for_patient( $patient_id, $doctor_id ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d AND patient_id = %d', $doctor_id, $patient_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Creates or updates the patient's review.
	 *
	 * @param int    $patient_id Patient user ID.
	 * @param int    $doctor_id  Doctor user ID.
	 * @param int    $rating     1-5.
	 * @param string $comment    Optional text.
	 * @return bool
	 */
	public static function save( $patient_id, $doctor_id, $rating, $comment ) {
		global $wpdb;

		$rating  = max( 1, min( 5, (int) $rating ) );
		$comment = mb_substr( trim( $comment ), 0, self::COMMENT_MAX );
		$now     = current_time( 'mysql' );
		$exists  = self::find_for_patient( $patient_id, $doctor_id );

		if ( $exists ) {
			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table_name(),
				array(
					'rating'     => $rating,
					'comment'    => $comment,
					'updated_at' => $now,
				),
				array( 'id' => (int) $exists['id'] ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table_name(),
				array(
					'doctor_id'  => $doctor_id,
					'patient_id' => $patient_id,
					'rating'     => $rating,
					'comment'    => $comment,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s' )
			);
		}

		if ( false !== $result ) {
			$review_id = $exists ? (int) $exists['id'] : (int) $wpdb->insert_id;

			/**
			 * A review was saved (new or changed) — the CRM shows it in the
			 * patient's history and follows up a low rating (Crm::on_review_saved()).
			 *
			 * @param int    $review_id  Review row ID.
			 * @param int    $patient_id Patient user ID.
			 * @param int    $doctor_id  Doctor user ID.
			 * @param int    $rating     1-5.
			 * @param string $comment    Comment.
			 */
			do_action( 'doctor_ak_review_saved', $review_id, (int) $patient_id, (int) $doctor_id, $rating, $comment );
		}

		return false !== $result;
	}

	/**
	 * Newest-first reviews for a doctor, with the reviewer's first name.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @param int $limit     Max rows.
	 * @return array
	 */
	public static function get_for_doctor( $doctor_id, $limit = 20 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d AND status = %s ORDER BY created_at DESC, id DESC LIMIT %d', $doctor_id, self::STATUS_PUBLISHED, $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'       => (int) $row['id'],
				'rating'   => (int) $row['rating'],
				'comment'  => $row['comment'],
				'name'     => self::public_name( $row ),
				'date'     => mysql2date( get_option( 'date_format' ), $row['created_at'] ),
				// From a patient account with a completed visit (not a shared link).
				'verified' => self::SOURCE_LINK !== $row['source'],
			);
		}

		return $out;
	}

	/**
	 * The name a review is shown under on the profile: the patient's first
	 * name, or the name typed on a review-link form.
	 *
	 * @param array $row Table row.
	 * @return string
	 */
	public static function public_name( array $row ) {
		if ( self::SOURCE_LINK === $row['source'] ) {
			$name = trim( (string) $row['reviewer_name'] );

			return '' !== $name ? $name : __( 'Patient', 'doctor-ak-portal' );
		}

		$user  = get_userdata( (int) $row['patient_id'] );
		$first = $user ? trim( $user->first_name ) : '';

		return '' !== $first ? $first : ( $user ? $user->display_name : __( 'Patient', 'doctor-ak-portal' ) );
	}

	/**
	 * Saves a review sent from a doctor's review link. It is held as
	 * 'pending' until an admin publishes it.
	 *
	 * @param int    $doctor_id Doctor user ID.
	 * @param string $name      Reviewer's name as typed.
	 * @param string $contact   Optional phone/email, seen only by admins.
	 * @param int    $rating    1-5.
	 * @param string $comment   Optional text.
	 * @return int New review ID, or 0.
	 */
	public static function add_from_link( $doctor_id, $name, $contact, $rating, $comment ) {
		global $wpdb;

		$now    = current_time( 'mysql' );
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table_name(),
			array(
				'doctor_id'        => (int) $doctor_id,
				'rating'           => max( 1, min( 5, (int) $rating ) ),
				'comment'          => mb_substr( trim( $comment ), 0, self::COMMENT_MAX ),
				'status'           => self::STATUS_PENDING,
				'source'           => self::SOURCE_LINK,
				'reviewer_name'    => mb_substr( trim( $name ), 0, self::NAME_MAX ),
				'reviewer_contact' => mb_substr( trim( $contact ), 0, self::CONTACT_MAX ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * One review row by ID.
	 *
	 * @param int $review_id Review ID.
	 * @return array|null
	 */
	public static function find( $review_id ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', $review_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Publishes, hides or (re)shows a review. The text and rating are never
	 * changed here.
	 *
	 * @param int    $review_id Review ID.
	 * @param string $status    One of the STATUS_* constants.
	 * @return bool
	 */
	public static function set_status( $review_id, $status ) {
		global $wpdb;

		if ( ! array_key_exists( $status, self::statuses() ) || ! self::find( $review_id ) ) {
			return false;
		}

		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table_name(),
			array( 'status' => $status ),
			array( 'id' => (int) $review_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * How many reviews are in each status.
	 *
	 * @return array status => count (every status present).
	 */
	public static function status_counts() {
		global $wpdb;

		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );
		$rows   = $wpdb->get_results( 'SELECT status, COUNT(*) AS c FROM ' . self::table_name() . ' GROUP BY status', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['c'];
			}
		}

		return $counts;
	}

	/**
	 * Reviews for the admin list, newest first.
	 *
	 * @param array $args {
	 *     @type int    $doctor_id 0 for all.
	 *     @type int    $rating    1-5, or 0 for all.
	 *     @type string $status    A STATUS_* constant, or '' for all.
	 *     @type int    $page      1-based page.
	 *     @type int    $per_page  Rows per page.
	 * }
	 * @return array { rows: array, total: int }
	 */
	public static function admin_query( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'doctor_id' => 0,
				'rating'    => 0,
				'status'    => '',
				'page'      => 1,
				'per_page'  => 20,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( (int) $args['doctor_id'] > 0 ) {
			$where[]  = 'doctor_id = %d';
			$params[] = (int) $args['doctor_id'];
		}

		if ( (int) $args['rating'] >= 1 && (int) $args['rating'] <= 5 ) {
			$where[]  = 'rating = %d';
			$params[] = (int) $args['rating'];
		}

		if ( array_key_exists( $args['status'], self::statuses() ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) $args['per_page'] );
		$offset    = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$count_sql = 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE ' . $where_sql;
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE ' . $where_sql . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', array_merge( $params, array( $per_page, $offset ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
			ARRAY_A
		);

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Average, count and per-star breakdown.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return array
	 */
	public static function summary( $doctor_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT rating, COUNT(*) AS c FROM ' . self::table_name() . ' WHERE doctor_id = %d AND status = %s GROUP BY rating', $doctor_id, self::STATUS_PUBLISHED ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$stars = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
		$sum   = 0;
		$count = 0;

		foreach ( (array) $rows as $row ) {
			$r           = (int) $row['rating'];
			$c           = (int) $row['c'];
			$stars[ $r ] = $c;
			$sum        += $r * $c;
			$count      += $c;
		}

		return array(
			'average' => $count ? round( $sum / $count, 1 ) : 0.0,
			'count'   => $count,
			'stars'   => $stars,
		);
	}
}
