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
 */
class Doctor_Reviews {

	const TABLE       = 'dak_doctor_reviews';
	const COMMENT_MAX = 1000;

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
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE doctor_id = %d ORDER BY created_at DESC, id DESC LIMIT %d', $doctor_id, $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$user  = get_userdata( (int) $row['patient_id'] );
			$first = $user ? trim( $user->first_name ) : '';
			$name  = '' !== $first ? $first : ( $user ? $user->display_name : __( 'Patient', 'doctor-ak-portal' ) );

			$out[] = array(
				'id'      => (int) $row['id'],
				'rating'  => (int) $row['rating'],
				'comment' => $row['comment'],
				'name'    => $name,
				'date'    => mysql2date( get_option( 'date_format' ), $row['created_at'] ),
			);
		}

		return $out;
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
			$wpdb->prepare( 'SELECT rating, COUNT(*) AS c FROM ' . self::table_name() . ' WHERE doctor_id = %d GROUP BY rating', $doctor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
