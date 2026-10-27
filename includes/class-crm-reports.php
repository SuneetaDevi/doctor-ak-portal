<?php
/**
 * CRM reports: patients, leads, no-shows, revenue per patient, follow-ups,
 * emails and feedback for a chosen period.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Crm_Reports
 *
 * Read-only: every number is worked out from what's already recorded —
 * appointments and encounters (visits, no-shows), the revenue ledger
 * (money, so totals match the Earnings/Billing screens), CRM contacts and
 * tasks, CRM emails, and doctor reviews. Nothing new is stored.
 *
 * The rules match the rest of the CRM (see Crm::refresh()):
 * - a visit is a check-in, or an appointment marked Checked in or
 *   Completed by a doctor (not the hourly auto-complete);
 * - a "possible no-show" is an appointment more than
 *   Appointments::AUTO_COMPLETE_HOURS_AFTER hours past its start, not
 *   cancelled, with no visit — a hint, never a fact;
 * - a new patient's first-ever visit is in the period; a returning
 *   patient visited in the period and had visited before it.
 */
class Crm_Reports {

	/** Longest period one report covers, in days. */
	const MAX_DAYS = 731;

	/**
	 * The whole report.
	 *
	 * @param string $from 'Y-m-d'.
	 * @param string $to   'Y-m-d'.
	 * @return array|\WP_Error
	 */
	public static function build( $from, $to ) {
		$from = (string) $from;
		$to   = (string) $to;

		if ( ! self::is_date( $from ) || ! self::is_date( $to ) || $from > $to ) {
			return new \WP_Error( 'doctor_ak_crm_report_dates', __( 'Choose a start date on or before the end date.', 'doctor-ak-portal' ) );
		}

		if ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS > self::MAX_DAYS ) {
			return new \WP_Error( 'doctor_ak_crm_report_long', __( 'Choose a period of two years or less.', 'doctor-ak-portal' ) );
		}

		$appointments = self::appointments( $from, $to );
		$visits       = self::visits( $from, $to, $appointments );
		$no_shows     = self::no_shows( $appointments );
		$feedback     = self::feedback( $from, $to );

		// One row per doctor: their appointments and their ratings.
		$seen = array();

		foreach ( $no_shows['by_doctor'] as &$row ) {
			$seen[ $row['doctor_id'] ] = true;
			$row['rating']             = isset( $feedback['by_doctor'][ $row['doctor_id'] ] ) ? $feedback['by_doctor'][ $row['doctor_id'] ] : null;
		}
		unset( $row );

		foreach ( $feedback['by_doctor'] as $doctor_id => $rating ) {
			if ( ! isset( $seen[ $doctor_id ] ) ) {
				$no_shows['by_doctor'][] = array(
					'doctor_id'    => $doctor_id,
					'label'        => self::doctor_name( $doctor_id ),
					'total'        => 0,
					'attended'     => 0,
					'no_show'      => 0,
					'cancelled'    => 0,
					'upcoming'     => 0,
					'no_show_rate' => null,
					'rating'       => $rating,
				);
			}
		}

		unset( $feedback['by_doctor'] );

		return array(
			'period'       => array(
				'from'    => $from,
				'to'      => $to,
				'buckets' => self::buckets( $from, $to ),
			),
			'patients'     => self::patients( $from, $to, $visits ),
			'leads'        => self::leads( $from, $to ),
			'appointments' => $no_shows,
			'revenue'      => self::revenue( $from, $to ),
			'followups'    => self::followups( $from, $to ),
			'emails'       => self::emails( $from, $to ),
			'feedback'     => $feedback,
			'complete'     => Crm::backfill_done() && Crm::refresh_done(),
		);
	}

	private static function is_date( $value ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) && false !== strtotime( $value );
	}

	/**
	 * Chart buckets: weeks (Monday start) for up to ~3 months, else months,
	 * clipped to the period.
	 *
	 * @return array[] { key, label, from, to }
	 */
	private static function buckets( $from, $to ) {
		$weekly  = ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS <= 92;
		$out     = array();
		$cursor  = strtotime( $from );
		$end     = strtotime( $to );

		while ( $cursor <= $end ) {
			if ( $weekly ) {
				$next = strtotime( '+7 days', strtotime( '-' . ( (int) gmdate( 'N', $cursor ) - 1 ) . ' days', $cursor ) );
				/* translators: %s: date the week starts. */
				$label = sprintf( __( 'Week of %s', 'doctor-ak-portal' ), gmdate( 'j M', $cursor ) );
			} else {
				$next  = strtotime( gmdate( 'Y-m-01', $cursor ) . ' +1 month' );
				$label = gmdate( 'M Y', $cursor );
			}

			$out[]  = array(
				'key'   => gmdate( 'Y-m-d', $cursor ),
				'label' => $label,
				'from'  => gmdate( 'Y-m-d', $cursor ),
				'to'    => gmdate( 'Y-m-d', min( $end, $next - DAY_IN_SECONDS ) ),
			);
			$cursor = $next;
		}

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Lookups
	 * ------------------------------------------------------------------- */

	/**
	 * Appointments dated in the period, with what the reports need.
	 *
	 * @return array[] { id, date, time, status, doctor_id, via }
	 */
	private static function appointments( $from, $to ) {
		global $wpdb;

		$keys = array(
			'appt_date'   => 'doctor_ak_appointment_date',
			'appt_time'   => 'doctor_ak_appointment_time',
			'appt_status' => 'doctor_ak_appointment_status',
			'doctor_id'   => 'doctor_ak_appointment_doctor_id',
			'via'         => Review_Requests::META_COMPLETED_VIA,
		);
		$cols = array();

		foreach ( $keys as $alias => $key ) {
			$cols[] = $wpdb->prepare( "MAX( CASE WHEN m.meta_key = %s THEN m.meta_value END ) AS {$alias}", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed alias.
		}

		$in   = "'" . implode( "','", array_map( 'esc_sql', array_values( $keys ) ) ) . "'";
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT p.ID AS id, ' . implode( ', ', $cols ) . " FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ( {$in} ) WHERE p.post_type = %s AND p.post_status = 'publish' GROUP BY p.ID HAVING appt_date >= %s AND appt_date <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- built from prepared fragments.
				Appointments::POST_TYPE,
				$from,
				$to
			),
			ARRAY_A
		);

		$rows = $rows ? $rows : array();
		$ids  = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$enc  = array_fill_keys( self::chunked_col( 'SELECT appointment_id FROM ' . Encounters::table_name() . ' WHERE appointment_id IN (%s)', $ids ), true );

		return array_map(
			function ( $r ) use ( $enc ) {
				$id = (int) $r['id'];

				return array(
					'id'        => $id,
					'date'      => (string) $r['appt_date'],
					'time'      => (string) $r['appt_time'],
					'status'    => (string) $r['appt_status'],
					'doctor_id' => (int) $r['doctor_id'],
					'attended'  => isset( $enc[ $id ] ) || Appointments::STATUS_CHECKED_IN === $r['appt_status']
						|| ( Appointments::STATUS_COMPLETED === $r['appt_status'] && 'auto' !== (string) $r['via'] ),
				);
			},
			$rows
		);
	}

	/**
	 * Runs "... IN (%s)" over a long ID list in chunks; returns the first column.
	 *
	 * @param string $sql SQL with one "%s" where the ID list goes.
	 * @param int[]  $ids IDs.
	 * @return string[]
	 */
	private static function chunked_col( $sql, array $ids ) {
		global $wpdb;

		$out = array();

		foreach ( array_chunk( array_values( array_unique( array_map( 'intval', $ids ) ) ), 500 ) as $chunk ) {
			$out = array_merge( $out, (array) $wpdb->get_col( sprintf( $sql, implode( ',', $chunk ) ) ) );
		}

		return $out;
	}

	/**
	 * Rows of "... IN (%s)" over a long ID list in chunks.
	 *
	 * @return array[]
	 */
	private static function chunked_rows( $sql, array $ids ) {
		global $wpdb;

		$out = array();

		foreach ( array_chunk( array_values( array_unique( array_map( 'intval', $ids ) ) ), 500 ) as $chunk ) {
			$out = array_merge( $out, (array) $wpdb->get_results( sprintf( $sql, implode( ',', $chunk ) ), ARRAY_A ) );
		}

		return $out;
	}

	/** appointment ID => contact ID. */
	private static function contacts_for_appointments( array $ids ) {
		$map = array();

		foreach ( self::chunked_rows( 'SELECT object_id, contact_id FROM ' . Crm::table( Crm::T_LINKS ) . " WHERE object_type = 'appointment' AND object_id IN (%s)", $ids ) as $r ) {
			$map[ (int) $r['object_id'] ] = (int) $r['contact_id'];
		}

		return $map;
	}

	/** user ID => contact ID. */
	private static function contacts_for_users( array $ids ) {
		$map = array();

		foreach ( self::chunked_rows( 'SELECT id, user_id FROM ' . Crm::table( Crm::T_CONTACTS ) . ' WHERE user_id IN (%s)', $ids ) as $r ) {
			$map[ (int) $r['user_id'] ] = (int) $r['id'];
		}

		return $map;
	}

	/** contact ID => first visit date ('' if none). */
	private static function first_visits( array $ids ) {
		$map = array();

		foreach ( self::chunked_rows( 'SELECT id, first_visit FROM ' . Crm::table( Crm::T_CONTACTS ) . ' WHERE id IN (%s)', $ids ) as $r ) {
			$map[ (int) $r['id'] ] = (string) $r['first_visit'];
		}

		return $map;
	}

	/**
	 * Every visit in the period as [ contact ID, date ] (attended
	 * appointments, plus walk-in check-ins).
	 *
	 * @return array[]
	 */
	private static function visits( $from, $to, array $appointments ) {
		global $wpdb;

		$attended = array_filter(
			$appointments,
			function ( $a ) {
				return $a['attended'];
			}
		);
		$map      = self::contacts_for_appointments( wp_list_pluck( $attended, 'id' ) );
		$out      = array();

		foreach ( $attended as $a ) {
			if ( isset( $map[ $a['id'] ] ) ) {
				$out[] = array( $map[ $a['id'] ], $a['date'] );
			}
		}

		$walk_ins = $wpdb->get_results( $wpdb->prepare( 'SELECT patient_id, checked_in_at FROM ' . Encounters::table_name() . ' WHERE appointment_id = 0 AND patient_id > 0 AND checked_in_at >= %s AND checked_in_at <= %s', $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$walk_ins = $walk_ins ? $walk_ins : array();
		$users    = self::contacts_for_users( wp_list_pluck( $walk_ins, 'patient_id' ) );

		foreach ( $walk_ins as $w ) {
			if ( isset( $users[ (int) $w['patient_id'] ] ) ) {
				$out[] = array( $users[ (int) $w['patient_id'] ], substr( $w['checked_in_at'], 0, 10 ) );
			}
		}

		return $out;
	}

	private static function doctor_name( $id ) {
		static $names = array();

		if ( ! isset( $names[ $id ] ) ) {
			$user          = $id ? get_userdata( $id ) : false;
			$names[ $id ]  = $user ? 'Dr. ' . trim( $user->first_name . ' ' . $user->last_name ) : __( 'No doctor', 'doctor-ak-portal' );
		}

		return $names[ $id ];
	}

	private static function rate( $part, $whole ) {
		return $whole ? round( 100 * $part / $whole, 1 ) : null;
	}

	/* ---------------------------------------------------------------------
	 * Sections
	 * ------------------------------------------------------------------- */

	/**
	 * New vs returning patients, overall and per bucket.
	 */
	private static function patients( $from, $to, array $visits ) {
		$firsts  = self::first_visits( array_map( 'intval', array_column( $visits, 0 ) ) );
		$new     = array();
		$return  = array();
		$buckets = self::buckets( $from, $to );
		$series  = array();

		foreach ( $buckets as $b ) {
			$series[ $b['key'] ] = array(
				'key'       => $b['key'],
				'label'     => $b['label'],
				'new'       => array(),
				'returning' => array(),
			);
		}

		foreach ( $visits as $v ) {
			list( $cid, $day ) = $v;
			$first             = isset( $firsts[ $cid ] ) && '' !== $firsts[ $cid ] ? $firsts[ $cid ] : $day;

			if ( $first >= $from ) {
				$new[ $cid ] = true;
			} else {
				$return[ $cid ] = true;
			}

			foreach ( $buckets as $b ) {
				if ( $day >= $b['from'] && $day <= $b['to'] ) {
					$series[ $b['key'] ][ $first >= $b['from'] ? 'new' : 'returning' ][ $cid ] = true;
					break;
				}
			}
		}

		return array(
			'new'       => count( $new ),
			'returning' => count( array_diff_key( $return, $new ) ),
			'series'    => array_values(
				array_map(
					function ( $s ) {
						$s['returning'] = count( array_diff_key( $s['returning'], $s['new'] ) );
						$s['new']       = count( $s['new'] );
						return $s;
					},
					$series
				)
			),
		);
	}

	/**
	 * People first seen in the period: how far they've got, by source.
	 */
	private static function leads( $from, $to ) {
		global $wpdb;

		$contacts = Crm::table( Crm::T_CONTACTS );
		$links    = Crm::table( Crm::T_LINKS );
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT c.id, c.source, c.stage, c.first_visit, c.lost_reason, EXISTS ( SELECT 1 FROM {$links} l WHERE l.contact_id = c.id AND l.object_type = 'appointment' ) AS has_booking FROM {$contacts} c WHERE c.created_at >= %s AND c.created_at <= %s", $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names.
		$sources  = Crm::sources();
		$by       = array();
		$reasons  = array();
		$total    = array_fill_keys( array( 'added', 'contacted', 'booked', 'visited', 'lost' ), 0 );

		foreach ( $rows ? $rows : array() as $r ) {
			$visited   = '' !== (string) $r['first_visit'];
			$booked    = $visited || (int) $r['has_booking'] || in_array( $r['stage'], array( Crm::STAGE_BOOKED, Crm::STAGE_PATIENT ), true );
			$contacted = $booked || Crm::STAGE_NEW !== $r['stage'];
			$lost      = Crm::STAGE_LOST === $r['stage'];
			$src       = isset( $sources[ $r['source'] ] ) ? $r['source'] : 'other';

			if ( ! isset( $by[ $src ] ) ) {
				$by[ $src ] = array(
					'source'  => $src,
					'label'   => $sources[ $src ],
					'added'   => 0,
					'booked'  => 0,
					'visited' => 0,
					'lost'    => 0,
				);
			}

			++$by[ $src ]['added'];
			++$total['added'];

			foreach ( array( 'booked' => $booked, 'visited' => $visited, 'lost' => $lost ) as $k => $yes ) {
				if ( $yes ) {
					++$by[ $src ][ $k ];
					++$total[ $k ];
				}
			}

			if ( $contacted ) {
				++$total['contacted'];
			}

			if ( $lost ) {
				$reason             = '' !== trim( (string) $r['lost_reason'] ) ? trim( $r['lost_reason'] ) : __( 'No reason given', 'doctor-ak-portal' );
				$reasons[ $reason ] = isset( $reasons[ $reason ] ) ? $reasons[ $reason ] + 1 : 1;
			}
		}

		usort(
			$by,
			function ( $a, $b ) {
				return $b['added'] - $a['added'];
			}
		);
		arsort( $reasons );

		$lost = array();

		foreach ( array_slice( $reasons, 0, 5, true ) as $reason => $n ) {
			$lost[] = array(
				'reason' => (string) $reason,
				'count'  => $n,
			);
		}

		return array_merge(
			$total,
			array(
				'booking_rate' => self::rate( $total['booked'], $total['added'] ),
				'by_source'    => array_map(
					function ( $s ) {
						$s['booking_rate'] = self::rate( $s['booked'], $s['added'] );
						return $s;
					},
					$by
				),
				'lost_reasons' => $lost,
			)
		);
	}

	/**
	 * Appointments in the period: attended, possible no-shows, cancelled — per doctor.
	 */
	private static function no_shows( array $appointments ) {
		$now     = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with stored local date/time.
		$grace   = Appointments::AUTO_COMPLETE_HOURS_AFTER * HOUR_IN_SECONDS;
		$blank   = array(
			'total'     => 0,
			'attended'  => 0,
			'no_show'   => 0,
			'cancelled' => 0,
			'upcoming'  => 0,
		);
		$total   = $blank;
		$doctors = array();

		foreach ( $appointments as $a ) {
			$start = strtotime( $a['date'] . ' ' . ( $a['time'] ? $a['time'] : '00:00' ) );

			if ( Appointments::STATUS_CANCELLED === $a['status'] ) {
				$kind = 'cancelled';
			} elseif ( $a['attended'] ) {
				$kind = 'attended';
			} elseif ( false !== $start && $now > $start + $grace ) {
				$kind = 'no_show';
			} else {
				$kind = 'upcoming';
			}

			if ( ! isset( $doctors[ $a['doctor_id'] ] ) ) {
				$doctors[ $a['doctor_id'] ] = array_merge(
					$blank,
					array(
						'doctor_id' => $a['doctor_id'],
						'label'     => self::doctor_name( $a['doctor_id'] ),
					)
				);
			}

			foreach ( array( &$total, &$doctors[ $a['doctor_id'] ] ) as &$bucket ) {
				++$bucket['total'];
				++$bucket[ $kind ];
			}
			unset( $bucket );
		}

		$with_rate = function ( $row ) {
			$row['no_show_rate'] = self::rate( $row['no_show'], $row['attended'] + $row['no_show'] );
			return $row;
		};

		$doctors = array_map( $with_rate, array_values( $doctors ) );
		usort(
			$doctors,
			function ( $a, $b ) {
				return $b['total'] - $a['total'];
			}
		);

		return array_merge( $with_rate( $total ), array( 'by_doctor' => $doctors ) );
	}

	/**
	 * Money collected in the period (the revenue ledger, as Earnings shows
	 * it), per patient.
	 */
	private static function revenue( $from, $to ) {
		$rows     = Revenue_Ledger::all_flat_for_admin(
			array(
				'date_from' => $from,
				'date_to'   => $to,
				'number'    => 100000,
			)
		);
		$rows     = array_values(
			array_filter(
				$rows,
				function ( $r ) {
					return Revenue_Ledger::TRANSACTION_REFUND !== $r['transaction_type'];
				}
			)
		);
		$appts    = self::contacts_for_appointments( array_filter( wp_list_pluck( $rows, 'appointment_id' ) ) );
		$enc_ids  = array();

		foreach ( $rows as $r ) {
			if ( ! $r['appointment_id'] && preg_match( '/^ENC-(\d+)-/', (string) $r['reference'], $m ) ) {
				$enc_ids[] = (int) $m[1];
			}
		}

		$enc_users = array();

		foreach ( self::chunked_rows( 'SELECT id, patient_id FROM ' . Encounters::table_name() . ' WHERE id IN (%s)', $enc_ids ) as $e ) {
			$enc_users[ (int) $e['id'] ] = (int) $e['patient_id'];
		}

		$users     = self::contacts_for_users( array_values( $enc_users ) );
		$collected = 0.0;
		$unlinked  = 0.0;
		$per       = array();

		foreach ( $rows as $r ) {
			$amount     = (float) $r['gross_amount'];
			$collected += $amount;
			$cid        = 0;

			if ( $r['appointment_id'] ) {
				$cid = isset( $appts[ $r['appointment_id'] ] ) ? $appts[ $r['appointment_id'] ] : 0;
			} elseif ( preg_match( '/^ENC-(\d+)-/', (string) $r['reference'], $m ) && isset( $enc_users[ (int) $m[1] ], $users[ $enc_users[ (int) $m[1] ] ] ) ) {
				$cid = $users[ $enc_users[ (int) $m[1] ] ];
			}

			if ( $cid ) {
				$per[ $cid ] = ( isset( $per[ $cid ] ) ? $per[ $cid ] : 0 ) + $amount;
			} else {
				$unlinked += $amount;
			}
		}

		$firsts   = self::first_visits( array_keys( $per ) );
		$from_new = 0.0;

		foreach ( $per as $cid => $amount ) {
			if ( isset( $firsts[ $cid ] ) && '' !== $firsts[ $cid ] && $firsts[ $cid ] >= $from && $firsts[ $cid ] <= $to ) {
				$from_new += $amount;
			}
		}

		$linked = array_sum( $per );

		return array(
			'collected'   => round( $collected, 2 ),
			'patients'    => count( $per ),
			'per_patient' => $per ? round( $linked / count( $per ), 2 ) : null,
			'from_new'    => round( $from_new, 2 ),
			'unlinked'    => round( $unlinked, 2 ),
		);
	}

	/**
	 * Tasks due in the period and what happened to them, per person.
	 */
	private static function followups( $from, $to ) {
		global $wpdb;

		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT assignee_id, due_date, status, completed_at, origin FROM ' . Crm::table( Crm::T_TASKS ) . ' WHERE due_date >= %s AND due_date <= %s', $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$today = current_time( 'Y-m-d' );
		$staff = Crm::staff();
		$blank = array_fill_keys( array( 'due', 'on_time', 'late', 'not_reached', 'cancelled', 'overdue', 'open' ), 0 );
		$total = $blank;
		$by    = array();

		foreach ( $rows ? $rows : array() as $r ) {
			switch ( $r['status'] ) {
				case Crm::TASK_DONE:
					$kind = substr( (string) $r['completed_at'], 0, 10 ) <= $r['due_date'] ? 'on_time' : 'late';
					break;
				case Crm::TASK_NOT_REACHED:
					$kind = 'not_reached';
					break;
				case Crm::TASK_CANCELLED:
					$kind = 'cancelled';
					break;
				default:
					$kind = $r['due_date'] < $today ? 'overdue' : 'open';
			}

			$who = (int) $r['assignee_id'];

			if ( ! isset( $by[ $who ] ) ) {
				$by[ $who ] = array_merge(
					$blank,
					array(
						'user_id' => $who,
						'label'   => isset( $staff[ $who ] ) ? $staff[ $who ] : __( 'Unassigned', 'doctor-ak-portal' ),
					)
				);
			}

			foreach ( array( &$total, &$by[ $who ] ) as &$bucket ) {
				++$bucket['due'];
				++$bucket[ $kind ];
			}
			unset( $bucket );
		}

		$by = array_values( $by );
		usort(
			$by,
			function ( $a, $b ) {
				return $b['due'] - $a['due'];
			}
		);

		return array_merge(
			$total,
			array(
				'done_rate' => self::rate( $total['on_time'] + $total['late'], $total['due'] ),
				'by_person' => $by,
			)
		);
	}

	/**
	 * CRM emails in the period, and unsubscribes.
	 */
	private static function emails( $from, $to ) {
		global $wpdb;

		$range = array( $from . ' 00:00:00', $to . ' 23:59:59' );
		$out   = array_fill_keys( array( 'sent', 'failed', 'skipped' ), 0 );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM ' . Crm::table( Crm_Email::T_MESSAGES ) . " WHERE kind IN ( 'campaign', 'single' ) AND created_at >= %s AND created_at <= %s GROUP BY status", $range ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		foreach ( $rows ? $rows : array() as $r ) {
			if ( isset( $out[ $r['status'] ] ) ) {
				$out[ $r['status'] ] = (int) $r['n'];
			}
		}

		$out['group_emails'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Crm::table( Crm_Email::T_CAMPAIGNS ) . ' WHERE created_at >= %s AND created_at <= %s', $range ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$out['unsubscribed'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Crm::table( Crm::T_CONTACTS ) . ' WHERE marketing_opt_out >= %s AND marketing_opt_out <= %s', $range ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		return $out;
	}

	/** Local 'Y-m-d H:i:s' → Unix time. */
	private static function to_unix( $local ) {
		return function_exists( 'get_gmt_from_date' ) ? strtotime( get_gmt_from_date( $local ) . ' +0000' ) : strtotime( $local );
	}

	/**
	 * Reviews given (or changed) in the period, the review-request
	 * response rate, and low ratings with their call-back task.
	 */
	private static function feedback( $from, $to ) {
		global $wpdb;

		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Doctor_Reviews::table_name() . ' WHERE updated_at >= %s AND updated_at <= %s ORDER BY updated_at DESC', $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$rows  = $rows ? $rows : array();
		$stars = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
		$sum   = 0;
		$by    = array();
		$low   = array();
		$users = self::contacts_for_users( wp_list_pluck( $rows, 'patient_id' ) );
		$tasks = array();

		foreach ( self::chunked_rows( 'SELECT origin_id, status FROM ' . Crm::table( Crm::T_TASKS ) . " WHERE origin = '" . Crm::TASK_ORIGIN_REVIEW . "' AND origin_id IN (%s) ORDER BY id ASC", wp_list_pluck( $rows, 'id' ) ) as $t ) {
			$tasks[ (int) $t['origin_id'] ] = $t['status']; // Latest wins.
		}

		foreach ( $rows as $r ) {
			$rating = max( 1, min( 5, (int) $r['rating'] ) );
			$doc    = (int) $r['doctor_id'];
			++$stars[ $rating ];
			$sum += $rating;

			if ( ! isset( $by[ $doc ] ) ) {
				$by[ $doc ] = array(
					'doctor_id' => $doc,
					'count'     => 0,
					'sum'       => 0,
				);
			}

			++$by[ $doc ]['count'];
			$by[ $doc ]['sum'] += $rating;

			if ( $rating <= Crm::LOW_RATING ) {
				$cid   = isset( $users[ (int) $r['patient_id'] ] ) ? $users[ (int) $r['patient_id'] ] : 0;
				$c     = $cid ? Crm::find( $cid ) : null;
				$low[] = array(
					'at'        => $r['updated_at'],
					'contactId' => $cid,
					'name'      => $c ? $c['name'] : __( 'Patient', 'doctor-ak-portal' ),
					'doctor'    => self::doctor_name( $doc ),
					'rating'    => $rating,
					'comment'   => (string) $r['comment'],
					'task'      => isset( $tasks[ (int) $r['id'] ] ) ? $tasks[ (int) $r['id'] ] : '',
				);
			}
		}

		$ratings = array();

		foreach ( $by as $d ) {
			$ratings[ $d['doctor_id'] ] = array(
				'count'   => $d['count'],
				'average' => round( $d['sum'] / $d['count'], 1 ),
			);
		}

		// Review-request emails sent in the period, and how many were used.
		$sent = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value + 0 >= %d AND meta_value + 0 <= %d", Review_Requests::META_SENT, self::to_unix( $from . ' 00:00:00' ), self::to_unix( $to . ' 23:59:59' ) ) );
		$used = $sent ? count( self::chunked_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '" . Review_Requests::META_TOKEN_USED . "' AND meta_value <> '' AND post_id IN (%s)", $sent ) ) : 0;

		return array(
			'count'         => count( $rows ),
			'average'       => $rows ? round( $sum / count( $rows ), 1 ) : null,
			'stars'         => $stars,
			'low_threshold' => Crm::LOW_RATING,
			'low'           => $low,
			'by_doctor'     => $ratings,
			'requests_sent' => count( $sent ),
			'requests_used' => $used,
			'response_rate' => self::rate( $used, count( $sent ) ),
		);
	}
}
