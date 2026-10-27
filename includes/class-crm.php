<?php
/**
 * CRM: one record per person (lead or patient), with stage, owner, tags,
 * follow-up date, notes, tasks and an activity log.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Crm
 *
 * The CRM keeps only its own data (who someone is, where they are in the
 * pipeline, who owns them, notes and tasks). Everything clinical or
 * financial stays where it already lives — appointments, encounters,
 * service requests — and is read from there; a cached summary on each
 * contact (last visit, next appointment, doctors, flags) only exists so the
 * list can be filtered and sorted quickly, and is rebuilt by refresh().
 *
 * People are created automatically from patient accounts, bookings
 * (including guest bookings) and service requests:
 * - a patient account is one contact;
 * - a guest booking or request joins an existing contact only when its
 *   phone or email AND its name match (families often book under one
 *   phone number) — anything less certain is left as a separate contact
 *   and offered to staff as a possible duplicate to merge.
 *
 * Stages move forward on their own (a booking → Booked, a visit →
 * Patient); staff can also set them by hand. Symptom-checker input is never
 * stored anywhere, so it never reaches the CRM. Emails to contacts
 * (templates, group emails, unsubscribing) live in Crm_Email, and the
 * Reports tab's numbers in Crm_Reports.
 *
 * Reviews patients leave (Doctor_Reviews) appear in their history, and a
 * low rating creates a call-back task for the clinic (on_review_saved()).
 */
class Crm {

	const T_CONTACTS = 'dak_crm_contacts';
	const T_LINKS    = 'dak_crm_links';
	const T_NOTES    = 'dak_crm_notes';
	const T_TASKS    = 'dak_crm_tasks';
	const T_ACTIVITY = 'dak_crm_activity';

	const STAGE_NEW       = 'new';
	const STAGE_CONTACTED = 'contacted';
	const STAGE_BOOKED    = 'booked';
	const STAGE_PATIENT   = 'patient';
	const STAGE_LOST      = 'lost';

	const TASK_OPEN        = 'open';
	const TASK_DONE        = 'done';
	const TASK_NOT_REACHED = 'not_reached';
	const TASK_CANCELLED   = 'cancelled';

	const LINK_APPOINTMENT = 'appointment';
	const LINK_REQUEST     = 'service_request';

	/** Option holding the one-time import of existing records' progress. */
	const BACKFILL_OPTION = 'dak_crm_backfill';

	/**
	 * Option holding the one-time re-summary of every contact for Reports
	 * (first visit, first-seen date) on sites imported before they existed.
	 */
	const REFRESH_OPTION = 'dak_crm_refresh_1_2';

	/** Reviews at or below this many stars get a call-back task. */
	const LOW_RATING = 2;

	const TASK_ORIGIN_REVIEW = 'review';

	/** "Inactive" view: patients whose last visit is older than this. */
	const INACTIVE_MONTHS = 6;

	const PER_PAGE = 25;
	const MAX_TAGS = 10;

	/* ---------------------------------------------------------------------
	 * Vocabulary
	 * ------------------------------------------------------------------- */

	/**
	 * @return array slug => label, in pipeline order.
	 */
	public static function stages() {
		return array(
			self::STAGE_NEW       => __( 'New lead', 'doctor-ak-portal' ),
			self::STAGE_CONTACTED => __( 'Contacted', 'doctor-ak-portal' ),
			self::STAGE_BOOKED    => __( 'Booked', 'doctor-ak-portal' ),
			self::STAGE_PATIENT   => __( 'Patient', 'doctor-ak-portal' ),
			self::STAGE_LOST      => __( 'Lost', 'doctor-ak-portal' ),
		);
	}

	/**
	 * @return array slug => label.
	 */
	public static function sources() {
		return array(
			'booking'         => __( 'Online booking', 'doctor-ak-portal' ),
			'account'         => __( 'Patient account', 'doctor-ak-portal' ),
			'service_request' => __( 'Service request', 'doctor-ak-portal' ),
			'walk_in'         => __( 'Walk-in', 'doctor-ak-portal' ),
			'phone'           => __( 'Phone call', 'doctor-ak-portal' ),
			'whatsapp'        => __( 'WhatsApp', 'doctor-ak-portal' ),
			'referral'        => __( 'Referral', 'doctor-ak-portal' ),
			'social'          => __( 'Social media', 'doctor-ak-portal' ),
			'other'           => __( 'Other', 'doctor-ak-portal' ),
		);
	}

	/**
	 * @return array slug => label.
	 */
	public static function task_statuses() {
		return array(
			self::TASK_OPEN        => __( 'Open', 'doctor-ak-portal' ),
			self::TASK_DONE        => __( 'Done', 'doctor-ak-portal' ),
			self::TASK_NOT_REACHED => __( 'Not reached', 'doctor-ak-portal' ),
			self::TASK_CANCELLED   => __( 'Cancelled', 'doctor-ak-portal' ),
		);
	}

	/**
	 * The saved views (tabs above the table).
	 *
	 * @return array slug => label.
	 */
	public static function views() {
		return array(
			'all'             => __( 'All', 'doctor-ak-portal' ),
			'new'             => __( 'New leads', 'doctor-ak-portal' ),
			'followups'       => __( 'Follow-ups due', 'doctor-ak-portal' ),
			'pending_payment' => __( 'Pending payments', 'doctor-ak-portal' ),
			'no_show'         => __( 'Possible no-shows', 'doctor-ak-portal' ),
			'inactive'        => __( 'Inactive patients', 'doctor-ak-portal' ),
		);
	}

	private static function rank( $stage ) {
		$ranks = array(
			self::STAGE_NEW       => 0,
			self::STAGE_CONTACTED => 1,
			self::STAGE_BOOKED    => 2,
			self::STAGE_PATIENT   => 3,
		);

		return isset( $ranks[ $stage ] ) ? $ranks[ $stage ] : -1;
	}

	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . $name;
	}

	/* ---------------------------------------------------------------------
	 * Identity
	 * ------------------------------------------------------------------- */

	/**
	 * Comparable phone: digits only, without the international prefix or
	 * the leading trunk zero ("+92 300-1234567", "03001234567" and
	 * "923001234567" all become "3001234567").
	 *
	 * @param string $phone Phone as entered.
	 * @return string '' when too short to be a phone number.
	 */
	public static function norm_phone( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );

		if ( 0 === strpos( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		if ( 0 === strpos( $digits, Phone::DEFAULT_DIAL_CODE ) && strlen( $digits ) >= 11 ) {
			$digits = substr( $digits, strlen( Phone::DEFAULT_DIAL_CODE ) );
		}

		$digits = ltrim( $digits, '0' );

		return strlen( $digits ) >= 6 ? $digits : '';
	}

	public static function norm_email( $email ) {
		$email = strtolower( trim( (string) $email ) );

		return is_email( $email ) ? $email : '';
	}

	public static function norm_name( $name ) {
		$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $name ) : strtolower( (string) $name );
		$name = preg_replace( '/^(mr|mrs|ms|miss|dr)\.?\s+/u', '', trim( $name ) );

		return trim( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $name ) );
	}

	/* ---------------------------------------------------------------------
	 * Contacts
	 * ------------------------------------------------------------------- */

	/**
	 * @param int $id Contact ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( self::T_CONTACTS ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		return $row ? self::decode( $row ) : null;
	}

	public static function find_by_user( $user_id ) {
		global $wpdb;

		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( self::T_CONTACTS ) . ' WHERE user_id = %d LIMIT 1', (int) $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		return $id ? self::find( $id ) : null;
	}

	/**
	 * Casts a raw row and unpacks the pipe lists and summary.
	 */
	private static function decode( array $row ) {
		foreach ( array( 'id', 'user_id', 'owner_id', 'created_by' ) as $int ) {
			$row[ $int ] = (int) $row[ $int ];
		}

		$row['tags']       = self::unpipe( $row['tags'] );
		$row['doctor_ids'] = array_map( 'intval', self::unpipe( $row['doctor_ids'] ) );
		$row['flags']      = self::unpipe( $row['flags'] );
		$summary           = json_decode( (string) $row['summary'], true );
		$row['summary']    = is_array( $summary ) ? $summary : array();

		return $row;
	}

	private static function pipe( array $list ) {
		$list = array_values( array_filter( array_map( 'strval', $list ), 'strlen' ) );

		return $list ? '|' . implode( '|', $list ) . '|' : '';
	}

	private static function unpipe( $value ) {
		return array_values( array_filter( explode( '|', (string) $value ), 'strlen' ) );
	}

	/**
	 * Inserts a contact.
	 *
	 * @param array $fields name, phone, email, user_id, source, stage, owner_id, created_by.
	 * @return int Contact ID (0 on failure).
	 */
	private static function insert( array $fields ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$data = array(
			'user_id'             => isset( $fields['user_id'] ) ? (int) $fields['user_id'] : 0,
			'name'                => self::cut( isset( $fields['name'] ) ? $fields['name'] : '', 191 ),
			'name_norm'           => self::cut( self::norm_name( isset( $fields['name'] ) ? $fields['name'] : '' ), 191 ),
			'phone'               => self::cut( isset( $fields['phone'] ) ? $fields['phone'] : '', 40 ),
			'phone_norm'          => self::norm_phone( isset( $fields['phone'] ) ? $fields['phone'] : '' ),
			'email'               => self::cut( isset( $fields['email'] ) ? $fields['email'] : '', 191 ),
			'email_norm'          => self::norm_email( isset( $fields['email'] ) ? $fields['email'] : '' ),
			'source'              => isset( $fields['source'] ) && isset( self::sources()[ $fields['source'] ] ) ? $fields['source'] : 'other',
			'stage'               => isset( $fields['stage'] ) && isset( self::stages()[ $fields['stage'] ] ) ? $fields['stage'] : self::STAGE_NEW,
			'stage_changed_at'    => $now,
			'lost_reason'         => '',
			'owner_id'            => isset( $fields['owner_id'] ) ? (int) $fields['owner_id'] : 0,
			'tags'                => '',
			'follow_up_date'      => null,
			'doctor_ids'          => '',
			'flags'               => '',
			'last_visit'          => null,
			'next_appointment'    => null,
			'summary'             => '',
			'last_activity_at'    => $now,
			'last_activity_label' => __( 'Added', 'doctor-ak-portal' ),
			'created_by'          => isset( $fields['created_by'] ) ? (int) $fields['created_by'] : 0,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$ok = $wpdb->insert( self::table( self::T_CONTACTS ), $data );

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function update_row( $id, array $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update( self::table( self::T_CONTACTS ), $data, array( 'id' => (int) $id ) );
	}

	private static function cut( $text, $max ) {
		$text = trim( (string) $text );

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	/**
	 * The contact for a patient account, creating it if needed. A guest
	 * contact that is clearly the same person (same name and same phone or
	 * email, no account yet) becomes this account's contact.
	 *
	 * @param int    $user_id Patient's user ID.
	 * @param string $source  Source for a new contact.
	 * @return int Contact ID (0 if the user doesn't exist).
	 */
	public static function ensure_for_user( $user_id, $source = 'account' ) {
		$existing = self::find_by_user( $user_id );

		if ( $existing ) {
			return $existing['id'];
		}

		$user = get_userdata( (int) $user_id );

		if ( ! $user ) {
			return 0;
		}

		$identity = self::user_identity( $user );
		$guest    = self::match_guest( $identity['name'], $identity['phone'], $identity['email'] );

		if ( $guest ) {
			self::update_row(
				$guest,
				array(
					'user_id'    => (int) $user_id,
					'name'       => self::cut( $identity['name'], 191 ),
					'name_norm'  => self::cut( self::norm_name( $identity['name'] ), 191 ),
					'phone'      => self::cut( $identity['phone'], 40 ),
					'phone_norm' => self::norm_phone( $identity['phone'] ),
					'email'      => self::cut( $identity['email'], 191 ),
					'email_norm' => self::norm_email( $identity['email'] ),
				)
			);
			self::log( $guest, 0, 'account', __( 'Linked to their patient account.', 'doctor-ak-portal' ) );

			return $guest;
		}

		return self::insert(
			array(
				'user_id' => (int) $user_id,
				'name'    => $identity['name'],
				'phone'   => $identity['phone'],
				'email'   => $identity['email'],
				'source'  => $source,
			)
		);
	}

	/**
	 * @param \WP_User $user User.
	 * @return array { name, phone, email }
	 */
	private static function user_identity( $user ) {
		$name = trim( $user->first_name . ' ' . $user->last_name );

		return array(
			'name'  => '' !== $name ? $name : $user->display_name,
			'phone' => (string) get_user_meta( $user->ID, 'doctor_ak_phone_number', true ),
			'email' => (string) $user->user_email,
		);
	}

	/**
	 * A contact without an account that has the same name and the same
	 * phone or email.
	 *
	 * @return int Contact ID or 0.
	 */
	private static function match_guest( $name, $phone, $email ) {
		global $wpdb;

		$name_norm  = self::norm_name( $name );
		$phone_norm = self::norm_phone( $phone );
		$email_norm = self::norm_email( $email );

		if ( '' === $name_norm || ( '' === $phone_norm && '' === $email_norm ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table( self::T_CONTACTS ) . ' WHERE user_id = 0 AND name_norm = %s AND ( ( phone_norm <> %s AND phone_norm = %s ) OR ( email_norm <> %s AND email_norm = %s ) ) ORDER BY id ASC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
				$name_norm,
				'',
				$phone_norm,
				'',
				$email_norm
			)
		);
	}

	/**
	 * The contact for a person without an account (guest booking, service
	 * request): an existing contact when name and phone/email match —
	 * including a patient account's contact — otherwise a new one.
	 *
	 * @return int Contact ID.
	 */
	public static function ensure_for_guest( $name, $phone, $email, $source ) {
		global $wpdb;

		$name_norm  = self::norm_name( $name );
		$phone_norm = self::norm_phone( $phone );
		$email_norm = self::norm_email( $email );

		if ( '' !== $name_norm && ( '' !== $phone_norm || '' !== $email_norm ) ) {
			$id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . self::table( self::T_CONTACTS ) . ' WHERE name_norm = %s AND ( ( phone_norm <> %s AND phone_norm = %s ) OR ( email_norm <> %s AND email_norm = %s ) ) ORDER BY user_id DESC, id ASC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
					$name_norm,
					'',
					$phone_norm,
					'',
					$email_norm
				)
			);

			if ( $id ) {
				return $id;
			}
		}

		return self::insert(
			array(
				'name'   => '' !== trim( (string) $name ) ? $name : __( 'Unknown', 'doctor-ak-portal' ),
				'phone'  => $phone,
				'email'  => $email,
				'source' => $source,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Links to appointments and service requests
	 * ------------------------------------------------------------------- */

	private static function link( $contact_id, $type, $object_id ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table( self::T_LINKS ) . ' ( contact_id, object_type, object_id, created_at ) VALUES ( %d, %s, %d, %s )', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
				(int) $contact_id,
				$type,
				(int) $object_id,
				current_time( 'mysql' )
			)
		);
	}

	public static function contact_for( $type, $object_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT contact_id FROM ' . self::table( self::T_LINKS ) . ' WHERE object_type = %s AND object_id = %d', $type, (int) $object_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
	}

	private static function linked_ids( $contact_id, $type ) {
		global $wpdb;

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT object_id FROM ' . self::table( self::T_LINKS ) . ' WHERE contact_id = %d AND object_type = %s', (int) $contact_id, $type ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
	}

	/* ---------------------------------------------------------------------
	 * Sync from the rest of the portal (hooked in class-plugin.php)
	 * ------------------------------------------------------------------- */

	/**
	 * A booking was created or changed (paid, cancelled, rescheduled,
	 * checked in, completed, refunded).
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return int Contact ID, or 0.
	 */
	public static function sync_appointment( $appointment_id ) {
		$appointment_id = (int) $appointment_id;

		if ( ! self::tables_ready() ) {
			return 0;
		}

		$contact_id = self::contact_for( self::LINK_APPOINTMENT, $appointment_id );

		if ( ! $contact_id ) {
			$appointment = Appointments::find( $appointment_id );

			if ( empty( $appointment ) ) {
				return 0;
			}

			$contact_id = $appointment['patient_id'] > 0
				? self::ensure_for_user( $appointment['patient_id'], 'booking' )
				: self::ensure_for_guest( $appointment['guest_name'], $appointment['guest_phone'], $appointment['guest_email'], 'booking' );

			if ( ! $contact_id ) {
				return 0;
			}

			self::link( $contact_id, self::LINK_APPOINTMENT, $appointment_id );
		}

		self::refresh( $contact_id );

		return $contact_id;
	}

	/** Hook signature helper for 'doctor_ak_appointment_created' ( $post_id, $data ). */
	public static function on_appointment( $appointment_id ) {
		self::sync_appointment( $appointment_id );
	}

	/**
	 * @param int $user_id New patient account.
	 */
	public static function on_patient_added( $user_id ) {
		$user = get_userdata( (int) $user_id );

		if ( self::tables_ready() && $user && in_array( Roles::PATIENT_ROLE, (array) $user->roles, true ) ) {
			$id = self::ensure_for_user( $user_id, 'account' );

			if ( $id ) {
				self::refresh( $id );
			}
		}
	}

	/**
	 * @param int $request_id New service request.
	 * @return int Contact ID, or 0.
	 */
	public static function on_service_request( $request_id ) {
		if ( ! self::tables_ready() ) {
			return 0;
		}

		$contact_id = self::contact_for( self::LINK_REQUEST, $request_id );

		if ( ! $contact_id ) {
			$request = Service_Requests::find( $request_id );

			if ( ! $request ) {
				return 0;
			}

			$contact_id = (int) $request['patient_id'] > 0
				? self::ensure_for_user( (int) $request['patient_id'], 'service_request' )
				: self::ensure_for_guest( $request['patient_name'], $request['patient_phone'], $request['patient_email'], 'service_request' );

			if ( ! $contact_id ) {
				return 0;
			}

			self::link( $contact_id, self::LINK_REQUEST, $request_id );
		}

		self::refresh( $contact_id );

		return $contact_id;
	}

	/**
	 * A check-in opened an encounter (walk-ins have no appointment).
	 *
	 * @param int $encounter_id Encounter ID.
	 */
	public static function on_encounter( $encounter_id ) {
		if ( ! self::tables_ready() ) {
			return;
		}

		$encounter = Encounters::find( $encounter_id );

		if ( ! $encounter ) {
			return;
		}

		if ( ! empty( $encounter['appointment_id'] ) ) {
			self::sync_appointment( (int) $encounter['appointment_id'] );
		} elseif ( ! empty( $encounter['patient_id'] ) ) {
			$id = self::ensure_for_user( (int) $encounter['patient_id'], 'walk_in' );

			if ( $id ) {
				self::refresh( $id );
			}
		}
	}

	/**
	 * 'doctor_ak_review_saved': a patient rated a doctor. The review shows in
	 * their history (read from Doctor_Reviews by timeline()); a rating of
	 * LOW_RATING or less also gets a call-back task, unless one for this
	 * review is still open.
	 *
	 * @param int    $review_id  Review ID.
	 * @param int    $patient_id Patient user ID.
	 * @param int    $doctor_id  Doctor user ID.
	 * @param int    $rating     1-5.
	 * @param string $comment    Comment.
	 * @return int Task ID created, or 0.
	 */
	public static function on_review_saved( $review_id, $patient_id, $doctor_id, $rating, $comment = '' ) {
		global $wpdb;

		if ( ! self::tables_ready() ) {
			return 0;
		}

		$contact_id = self::ensure_for_user( (int) $patient_id, 'account' );

		if ( ! $contact_id ) {
			return 0;
		}

		self::touch( $contact_id, __( 'Left a review', 'doctor-ak-portal' ) );

		if ( (int) $rating > self::LOW_RATING ) {
			return 0;
		}

		$open = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( self::T_TASKS ) . " WHERE origin = %s AND origin_id = %d AND status = 'open'", self::TASK_ORIGIN_REVIEW, (int) $review_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		if ( $open ) {
			return 0;
		}

		$contact = self::find( $contact_id );
		$doctor  = get_userdata( (int) $doctor_id );
		$owner   = $contact && array_key_exists( $contact['owner_id'], self::staff() ) ? $contact['owner_id'] : 0;
		$task    = self::add_task(
			$contact_id,
			array(
				/* translators: 1: doctor name, 2: stars. */
				'title'       => sprintf( __( 'Call back: rated %1$s %2$d of 5', 'doctor-ak-portal' ), $doctor ? 'Dr. ' . trim( $doctor->first_name . ' ' . $doctor->last_name ) : __( 'their doctor', 'doctor-ak-portal' ), (int) $rating ),
				'due_date'    => current_time( 'Y-m-d' ),
				'assignee_id' => $owner,
				'origin'      => self::TASK_ORIGIN_REVIEW,
				'origin_id'   => (int) $review_id,
			),
			0
		);

		return is_wp_error( $task ) ? 0 : (int) $task;
	}

	/**
	 * Whether the CRM tables exist yet (hooks can fire before the first
	 * upgrade on a site that just received the files).
	 */
	public static function tables_ready() {
		return Db_Installer::CRM_DB_VERSION === get_option( Db_Installer::CRM_DB_VERSION_OPTION );
	}

	/* ---------------------------------------------------------------------
	 * Summary (cached for the list)
	 * ------------------------------------------------------------------- */

	/**
	 * Appointment IDs of a contact: linked ones, plus any of their
	 * account's appointments not linked yet (which get linked now).
	 */
	private static function appointment_ids( array $contact ) {
		$ids = self::linked_ids( $contact['id'], self::LINK_APPOINTMENT );

		if ( $contact['user_id'] > 0 ) {
			$query = new \WP_Query(
				array(
					'post_type'      => Appointments::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => 'doctor_ak_appointment_patient_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- non-public post type.
					'meta_value'     => (int) $contact['user_id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- non-public post type.
				)
			);

			foreach ( $query->posts as $id ) {
				if ( ! in_array( (int) $id, $ids, true ) && ! self::contact_for( self::LINK_APPOINTMENT, $id ) ) {
					self::link( $contact['id'], self::LINK_APPOINTMENT, $id );
					$ids[] = (int) $id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/** Walk-in encounters (no appointment) of the contact's account. */
	private static function walk_in_encounters( array $contact ) {
		global $wpdb;

		if ( $contact['user_id'] <= 0 ) {
			return array();
		}

		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Encounters::table_name() . ' WHERE appointment_id = 0 AND patient_id = %d ORDER BY checked_in_at DESC', $contact['user_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
	}

	/**
	 * Rebuilds a contact's cached summary from their appointments,
	 * encounters and requests, and moves the stage forward when the data
	 * shows they booked or visited.
	 *
	 * @param int $contact_id Contact ID.
	 * @return array|null Updated contact.
	 */
	public static function refresh( $contact_id ) {
		$contact = self::find( $contact_id );

		if ( ! $contact ) {
			return null;
		}

		$now         = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with stored local date/time strings.
		$update      = array();
		$doctors     = array();
		$services    = array();
		$visits      = 0;
		$first_visit = '';
		$last_visit  = '';
		$next        = '';
		$paid        = 0.0;
		$unpaid      = 0.0;
		$no_shows    = 0;
		$pending_pay = 0;
		$booked      = false;
		$last_booked = '';
		$events      = array( array( $contact['created_at'], __( 'Added', 'doctor-ak-portal' ) ) );

		// When the clinic first heard of them: the import gives old records
		// today's date, so the earliest booking/request/registration wins.
		$first_seen = $contact['created_at'];
		$seen       = function ( $when ) use ( &$first_seen ) {
			if ( '' !== (string) $when && (string) $when < $first_seen ) {
				$first_seen = (string) $when;
			}
		};

		if ( $contact['user_id'] > 0 ) {
			$user = get_userdata( $contact['user_id'] );

			if ( $user && ! empty( $user->user_registered ) ) {
				$seen( function_exists( 'get_date_from_gmt' ) ? get_date_from_gmt( $user->user_registered ) : $user->user_registered );
			}

			if ( $user ) {
				$identity = self::user_identity( $user );
				$update   = array(
					'name'       => self::cut( $identity['name'], 191 ),
					'name_norm'  => self::cut( self::norm_name( $identity['name'] ), 191 ),
					'phone'      => self::cut( $identity['phone'], 40 ),
					'phone_norm' => self::norm_phone( $identity['phone'] ),
					'email'      => self::cut( $identity['email'], 191 ),
					'email_norm' => self::norm_email( $identity['email'] ),
				);
			}
		}

		foreach ( self::appointment_ids( $contact ) as $id ) {
			$a = Appointments::find( $id );

			if ( empty( $a ) ) {
				continue;
			}

			$post      = get_post( $id );
			$created   = $post ? $post->post_date : '';
			$start     = strtotime( $a['date'] . ' ' . $a['time'] );
			$cancelled = Appointments::STATUS_CANCELLED === $a['status'];
			$encounter = Encounters::find_by_appointment( $id );
			$attended  = $encounter || Appointments::STATUS_CHECKED_IN === $a['status']
				|| ( Appointments::STATUS_COMPLETED === $a['status'] && 'auto' !== get_post_meta( $id, Review_Requests::META_COMPLETED_VIA, true ) );

			if ( $a['doctor_id'] ) {
				$doctors[] = (int) $a['doctor_id'];
			}

			if ( '' !== (string) $a['service_name'] ) {
				$services[ (string) $a['service_name'] ] = $created;
			}

			if ( '' !== $created ) {
				$seen( $created );
				$events[] = array( $created, $cancelled ? __( 'Booking cancelled', 'doctor-ak-portal' ) : __( 'Booked', 'doctor-ak-portal' ) );

				if ( ! $cancelled ) {
					$booked      = true;
					$last_booked = max( $last_booked, $created );
				}
			}

			if ( $attended ) {
				++$visits;
				$last_visit  = max( $last_visit, (string) $a['date'] );
				$first_visit = '' === $first_visit ? (string) $a['date'] : min( $first_visit, (string) $a['date'] );
				$events[]   = array( $a['date'] . ' ' . $a['time'], __( 'Visited', 'doctor-ak-portal' ) );
			} elseif ( ! $cancelled && false !== $start && $now > $start + Appointments::AUTO_COMPLETE_HOURS_AFTER * HOUR_IN_SECONDS ) {
				// Past, not cancelled, no check-in or doctor completion: possibly
				// didn't come. Never stated as fact.
				++$no_shows;
			}

			if ( ! $cancelled && false !== $start && $start > $now && ( '' === $next || $a['date'] . ' ' . $a['time'] < $next ) ) {
				$next = $a['date'] . ' ' . $a['time'];
			}

			if ( Appointments::PAYMENT_STATUS_PAID === $a['payment_status'] ) {
				$paid += (float) $a['charge'] - ( Appointments::REFUND_STATUS_PROCESSED === $a['refund_status'] ? (float) $a['refund_amount'] : 0 );
			} elseif ( ! $cancelled && (float) $a['charge'] > 0 ) {
				$unpaid += (float) $a['charge'];

				if ( Appointments::STATUS_PENDING_PAYMENT === $a['status'] ) {
					++$pending_pay;
				}
			}
		}

		foreach ( self::walk_in_encounters( $contact ) as $e ) {
			++$visits;
			$day         = substr( (string) $e['checked_in_at'], 0, 10 );
			$last_visit  = max( $last_visit, $day );
			$first_visit = '' === $first_visit ? $day : min( $first_visit, $day );
			$seen( (string) $e['checked_in_at'] );
			$doctors[]  = (int) $e['doctor_id'];
			$events[]   = array( $e['checked_in_at'], __( 'Visited (walk-in)', 'doctor-ak-portal' ) );
		}

		foreach ( self::linked_ids( $contact['id'], self::LINK_REQUEST ) as $rid ) {
			$r = Service_Requests::find( $rid );

			if ( $r ) {
				if ( ! empty( $r['doctor_id'] ) ) {
					$doctors[] = (int) $r['doctor_id'];
				}

				$services[ (string) $r['service_name'] ] = (string) $r['created_at'];
				$seen( (string) $r['created_at'] );
				$events[]                                 = array( (string) $r['created_at'], __( 'Requested a service', 'doctor-ak-portal' ) );
			}
		}

		$latest = self::latest_crm_event( $contact['id'] );

		if ( $latest ) {
			$events[] = $latest;
		}

		usort(
			$events,
			function ( $a, $b ) {
				return strcmp( $b[0], $a[0] );
			}
		);

		// Most recent service first.
		arsort( $services );

		$flags = array();

		if ( $pending_pay ) {
			$flags[] = 'pending_payment';
		}

		if ( $no_shows ) {
			$flags[] = 'no_show';
		}

		$stage = $contact['stage'];
		$auto  = $visits ? self::STAGE_PATIENT : ( $booked ? self::STAGE_BOOKED : '' );

		if ( '' !== $auto ) {
			if ( self::STAGE_LOST === $stage ) {
				// A lost lead who books again after being marked lost comes back.
				if ( $last_booked > $contact['stage_changed_at'] ) {
					$stage = $auto;
				}
			} elseif ( self::rank( $auto ) > self::rank( $stage ) ) {
				$stage = $auto;
			}
		}

		$update = array_merge(
			$update,
			array(
				'doctor_ids'          => self::pipe( array_values( array_unique( array_filter( $doctors ) ) ) ),
				'flags'               => self::pipe( $flags ),
				'first_visit'         => '' !== $first_visit ? $first_visit : null,
				'last_visit'          => '' !== $last_visit ? $last_visit : null,
				'next_appointment'    => '' !== $next ? $next : null,
				'summary'             => wp_json_encode(
					array(
						'visits'            => $visits,
						'last_visit'        => $last_visit,
						'next_appointment'  => $next,
						'total_paid'        => round( $paid, 2 ),
						'unpaid'            => round( $unpaid, 2 ),
						'possible_no_shows' => $no_shows,
						'pending_payments'  => $pending_pay,
						'services'          => array_slice( array_keys( $services ), 0, 3 ),
					)
				),
				'last_activity_at'    => $events[0][0],
				'last_activity_label' => $events[0][1],
			)
		);

		if ( $first_seen < $contact['created_at'] ) {
			$update['created_at'] = $first_seen;
		}

		if ( $stage !== $contact['stage'] ) {
			$update['stage']            = $stage;
			$update['stage_changed_at'] = current_time( 'mysql' );
			self::log( $contact['id'], 0, 'stage', sprintf( /* translators: %s: stage. */ __( 'Stage moved to %s (from their bookings and visits).', 'doctor-ak-portal' ), self::stages()[ $stage ] ) );
		}

		self::update_row( $contact['id'], $update );

		return self::find( $contact['id'] );
	}

	/** Most recent note / task / staff action, as [ datetime, label ]. */
	private static function latest_crm_event( $contact_id ) {
		global $wpdb;

		$note = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(created_at) FROM ' . self::table( self::T_NOTES ) . ' WHERE contact_id = %d', $contact_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$task = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(GREATEST(created_at, COALESCE(completed_at, created_at))) FROM ' . self::table( self::T_TASKS ) . ' WHERE contact_id = %d', $contact_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$best = null;

		if ( $note ) {
			$best = array( $note, __( 'Note added', 'doctor-ak-portal' ) );
		}

		if ( $task && ( ! $best || $task > $best[0] ) ) {
			$best = array( $task, __( 'Task updated', 'doctor-ak-portal' ) );
		}

		return $best;
	}

	/* ---------------------------------------------------------------------
	 * The list
	 * ------------------------------------------------------------------- */

	/**
	 * Contacts for the table.
	 *
	 * @param array $args {
	 *     view, q, stage, source, doctor, owner, tag, date_from, date_to (created),
	 *     last_visit_from, last_visit_to, scope_doctor (forced doctor scope), page,
	 *     per_page (default PER_PAGE, at most 500), sort ('activity'|'name'|'follow_up'|'created').
	 * }
	 * @return array { rows, total, page, pages, counts: view => n }
	 */
	public static function query( array $args ) {
		global $wpdb;

		$table = self::table( self::T_CONTACTS );
		$base  = array( '1=1' );
		$vals  = array();

		$scope = isset( $args['scope_doctor'] ) ? (int) $args['scope_doctor'] : 0;

		if ( $scope ) {
			$base[] = 'doctor_ids LIKE %s';
			$vals[] = '%|' . $scope . '|%';
		}

		$where = $base;
		$wvals = $vals;

		if ( ! empty( $args['q'] ) ) {
			$like    = '%' . $wpdb->esc_like( trim( (string) $args['q'] ) ) . '%';
			$digits  = self::norm_phone( $args['q'] );
			$where[] = '( name LIKE %s OR email LIKE %s' . ( '' !== $digits ? ' OR phone_norm LIKE %s' : '' ) . ' )';
			$wvals[] = $like;
			$wvals[] = $like;

			if ( '' !== $digits ) {
				$wvals[] = '%' . $wpdb->esc_like( $digits ) . '%';
			}
		}

		foreach ( array( 'stage', 'source' ) as $col ) {
			if ( ! empty( $args[ $col ] ) ) {
				$where[] = "$col = %s";
				$wvals[] = (string) $args[ $col ];
			}
		}

		if ( ! empty( $args['doctor'] ) ) {
			$where[] = 'doctor_ids LIKE %s';
			$wvals[] = '%|' . (int) $args['doctor'] . '|%';
		}

		if ( isset( $args['owner'] ) && '' !== (string) $args['owner'] ) {
			$where[] = 'owner_id = %d';
			$wvals[] = (int) $args['owner'];
		}

		if ( ! empty( $args['tag'] ) ) {
			$where[] = 'tags LIKE %s';
			$wvals[] = '%|' . $wpdb->esc_like( (string) $args['tag'] ) . '|%';
		}

		if ( ! empty( $args['date_from'] ) && self::is_date( $args['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$wvals[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) && self::is_date( $args['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$wvals[] = $args['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $args['last_visit_from'] ) && self::is_date( $args['last_visit_from'] ) ) {
			$where[] = 'last_visit >= %s';
			$wvals[] = $args['last_visit_from'];
		}

		if ( ! empty( $args['last_visit_to'] ) && self::is_date( $args['last_visit_to'] ) ) {
			$where[] = 'last_visit <= %s';
			$wvals[] = $args['last_visit_to'];
		}

		$view   = isset( $args['view'] ) && isset( self::views()[ $args['view'] ] ) ? $args['view'] : 'all';
		$counts = array();

		foreach ( array_keys( self::views() ) as $v ) {
			$sql        = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode( ' AND ', array_merge( $where, self::view_sql( $v ) ) );
			$counts[ $v ] = (int) $wpdb->get_var( $wvals ? $wpdb->prepare( $sql, $wvals ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from fixed fragments with placeholders.
		}

		$where = array_merge( $where, self::view_sql( $view ) );
		$sorts = array(
			'activity'  => 'last_activity_at DESC, id DESC',
			'name'      => 'name ASC, id ASC',
			'follow_up' => 'follow_up_date IS NULL, follow_up_date ASC, id DESC',
			'created'   => 'created_at DESC, id DESC',
		);
		$sort  = isset( $args['sort'] ) && isset( $sorts[ $args['sort'] ] ) ? $args['sort'] : ( 'followups' === $view ? 'follow_up' : 'activity' );
		$page  = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$per   = ! empty( $args['per_page'] ) ? min( 500, max( 1, (int) $args['per_page'] ) ) : self::PER_PAGE;
		$total = $counts[ $view ];
		$sql   = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY ' . $sorts[ $sort ] . ' LIMIT %d OFFSET %d';
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $wvals, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from fixed fragments with placeholders.

		return array(
			'rows'   => array_map( array( __CLASS__, 'decode' ), $rows ? $rows : array() ),
			'total'  => $total,
			'page'   => $page,
			'pages'  => max( 1, (int) ceil( $total / $per ) ),
			'counts' => $counts,
		);
	}

	/**
	 * SQL conditions (no placeholders) for a view.
	 */
	private static function view_sql( $view ) {
		$today    = esc_sql( current_time( 'Y-m-d' ) );
		$cutoff   = esc_sql( gmdate( 'Y-m-d', strtotime( '-' . self::INACTIVE_MONTHS . ' months', current_time( 'timestamp' ) ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local date maths.
		$tasks    = self::table( self::T_TASKS );
		$contacts = self::table( self::T_CONTACTS );

		switch ( $view ) {
			case 'new':
				return array( "stage = 'new'" );
			case 'followups':
				return array( "stage <> 'lost' AND ( ( follow_up_date IS NOT NULL AND follow_up_date <= '{$today}' ) OR EXISTS ( SELECT 1 FROM {$tasks} t WHERE t.contact_id = {$contacts}.id AND t.status = 'open' AND t.due_date <= '{$today}' ) )" );
			case 'pending_payment':
				return array( "flags LIKE '%|pending_payment|%'" );
			case 'no_show':
				return array( "flags LIKE '%|no_show|%'" );
			case 'inactive':
				return array( "stage = 'patient' AND last_visit IS NOT NULL AND last_visit < '{$cutoff}' AND next_appointment IS NULL" );
		}

		return array();
	}

	private static function is_date( $value ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value );
	}

	/**
	 * Every tag in use (for the filter and suggestions).
	 *
	 * @return string[]
	 */
	public static function all_tags() {
		global $wpdb;

		$tags = array();

		foreach ( $wpdb->get_col( 'SELECT tags FROM ' . self::table( self::T_CONTACTS ) . " WHERE tags <> ''" ) as $value ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
			foreach ( self::unpipe( $value ) as $tag ) {
				$tags[ strtolower( $tag ) ] = $tag;
			}
		}

		natcasesort( $tags );

		return array_values( $tags );
	}

	/* ---------------------------------------------------------------------
	 * Changes (permissions are checked by Crm_Handler)
	 * ------------------------------------------------------------------- */

	/**
	 * Adds a contact by hand (a phone enquiry, a walk-in lead…).
	 *
	 * @return int|\WP_Error Contact ID.
	 */
	public static function create_manual( array $input, $user_id ) {
		$fields = self::clean_identity( $input );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$id = self::insert(
			array_merge(
				$fields,
				array(
					'source'     => isset( $input['source'] ) ? (string) $input['source'] : 'other',
					'owner_id'   => isset( $input['owner_id'] ) ? (int) $input['owner_id'] : 0,
					'created_by' => (int) $user_id,
				)
			)
		);

		if ( ! $id ) {
			return new \WP_Error( 'doctor_ak_crm_save', __( 'The contact could not be saved. Please try again.', 'doctor-ak-portal' ) );
		}

		self::log( $id, $user_id, 'create', __( 'Added by hand.', 'doctor-ak-portal' ) );

		return $id;
	}

	/**
	 * Validates name / phone / email.
	 *
	 * @return array|\WP_Error { name, phone, email }
	 */
	private static function clean_identity( array $input ) {
		$name  = sanitize_text_field( isset( $input['name'] ) ? (string) $input['name'] : '' );
		$phone = sanitize_text_field( isset( $input['phone'] ) ? (string) $input['phone'] : '' );
		$email = sanitize_email( isset( $input['email'] ) ? (string) $input['email'] : '' );

		if ( '' === trim( $name ) ) {
			return new \WP_Error( 'doctor_ak_crm_name', __( 'Enter a name.', 'doctor-ak-portal' ), array( 'field' => 'name' ) );
		}

		if ( '' === self::norm_phone( $phone ) && '' === self::norm_email( $email ) ) {
			return new \WP_Error( 'doctor_ak_crm_contact', __( 'Enter a phone number or an email address.', 'doctor-ak-portal' ), array( 'field' => 'phone' ) );
		}

		if ( '' !== trim( (string) ( isset( $input['email'] ) ? $input['email'] : '' ) ) && '' === self::norm_email( $email ) ) {
			return new \WP_Error( 'doctor_ak_crm_email', __( 'Enter a valid email address.', 'doctor-ak-portal' ), array( 'field' => 'email' ) );
		}

		return array(
			'name'  => $name,
			'phone' => $phone,
			'email' => $email,
		);
	}

	/**
	 * Updates CRM fields of a contact. Only the given keys change.
	 *
	 * @param int   $id      Contact ID.
	 * @param array $input   name, phone, email (contacts without an account only),
	 *                       source, stage, lost_reason, owner_id, tags, follow_up_date.
	 * @param int   $user_id Acting user.
	 * @return array|\WP_Error Updated contact.
	 */
	public static function update_fields( $id, array $input, $user_id ) {
		$contact = self::find( $id );

		if ( ! $contact ) {
			return new \WP_Error( 'doctor_ak_crm_missing', __( 'That contact no longer exists.', 'doctor-ak-portal' ) );
		}

		$data = array();
		$logs = array();

		if ( array_intersect_key( $input, array_flip( array( 'name', 'phone', 'email' ) ) ) ) {
			if ( $contact['user_id'] > 0 ) {
				return new \WP_Error( 'doctor_ak_crm_account', __( 'This person has a patient account: change their name, phone or email on their account.', 'doctor-ak-portal' ) );
			}

			$identity = self::clean_identity(
				array_merge(
					array(
						'name'  => $contact['name'],
						'phone' => $contact['phone'],
						'email' => $contact['email'],
					),
					$input
				)
			);

			if ( is_wp_error( $identity ) ) {
				return $identity;
			}

			$data = array_merge(
				$data,
				array(
					'name'       => self::cut( $identity['name'], 191 ),
					'name_norm'  => self::cut( self::norm_name( $identity['name'] ), 191 ),
					'phone'      => self::cut( $identity['phone'], 40 ),
					'phone_norm' => self::norm_phone( $identity['phone'] ),
					'email'      => self::cut( $identity['email'], 191 ),
					'email_norm' => self::norm_email( $identity['email'] ),
				)
			);
			$logs[] = array( 'edit', __( 'Contact details edited.', 'doctor-ak-portal' ) );
		}

		if ( isset( $input['source'] ) && $input['source'] !== $contact['source'] ) {
			if ( ! isset( self::sources()[ $input['source'] ] ) ) {
				return new \WP_Error( 'doctor_ak_crm_source', __( 'Choose a source from the list.', 'doctor-ak-portal' ) );
			}

			$data['source'] = $input['source'];
			$logs[]         = array( 'source', sprintf( /* translators: %s: source. */ __( 'Source set to %s.', 'doctor-ak-portal' ), self::sources()[ $input['source'] ] ) );
		}

		if ( isset( $input['stage'] ) && $input['stage'] !== $contact['stage'] ) {
			if ( ! isset( self::stages()[ $input['stage'] ] ) ) {
				return new \WP_Error( 'doctor_ak_crm_stage', __( 'Choose a stage from the list.', 'doctor-ak-portal' ) );
			}

			$reason                   = self::STAGE_LOST === $input['stage'] ? self::cut( sanitize_text_field( isset( $input['lost_reason'] ) ? (string) $input['lost_reason'] : '' ), 191 ) : '';
			$data['stage']            = $input['stage'];
			$data['stage_changed_at'] = current_time( 'mysql' );
			$data['lost_reason']      = $reason;
			$logs[]                   = array( 'stage', sprintf( /* translators: %s: stage. */ __( 'Stage set to %s.', 'doctor-ak-portal' ), self::stages()[ $input['stage'] ] ) . ( '' !== $reason ? ' ' . sprintf( /* translators: %s: reason. */ __( 'Reason: %s', 'doctor-ak-portal' ), $reason ) : '' ) );
		}

		if ( isset( $input['owner_id'] ) && (int) $input['owner_id'] !== $contact['owner_id'] ) {
			$owner = (int) $input['owner_id'];

			if ( $owner && ! array_key_exists( $owner, self::staff() ) ) {
				return new \WP_Error( 'doctor_ak_crm_owner', __( 'Choose an owner from the clinic staff.', 'doctor-ak-portal' ) );
			}

			$data['owner_id'] = $owner;
			$logs[]           = array( 'owner', $owner ? sprintf( /* translators: %s: name. */ __( 'Owner set to %s.', 'doctor-ak-portal' ), self::staff()[ $owner ] ) : __( 'Owner removed.', 'doctor-ak-portal' ) );
		}

		if ( isset( $input['tags'] ) ) {
			$tags = self::clean_tags( $input['tags'] );

			if ( $tags !== $contact['tags'] ) {
				$data['tags'] = self::pipe( $tags );
				$logs[]       = array( 'tags', $tags ? sprintf( /* translators: %s: tags. */ __( 'Tags: %s.', 'doctor-ak-portal' ), implode( ', ', $tags ) ) : __( 'Tags removed.', 'doctor-ak-portal' ) );
			}
		}

		if ( array_key_exists( 'follow_up_date', $input ) ) {
			$date = (string) $input['follow_up_date'];

			if ( '' !== $date && ! self::is_date( $date ) ) {
				return new \WP_Error( 'doctor_ak_crm_date', __( 'Choose a valid follow-up date.', 'doctor-ak-portal' ) );
			}

			if ( ( '' === $date ? null : $date ) !== $contact['follow_up_date'] ) {
				$data['follow_up_date'] = '' === $date ? null : $date;
				$logs[]                 = array( 'follow_up', '' === $date ? __( 'Follow-up date cleared.', 'doctor-ak-portal' ) : sprintf( /* translators: %s: date. */ __( 'Follow-up set for %s.', 'doctor-ak-portal' ), date_i18n( get_option( 'date_format' ), strtotime( $date ) ) ) );
			}
		}

		if ( $data ) {
			$data['last_activity_at']    = current_time( 'mysql' );
			$data['last_activity_label'] = __( 'Updated', 'doctor-ak-portal' );
			self::update_row( $id, $data );

			foreach ( $logs as $log ) {
				self::log( $id, $user_id, $log[0], $log[1] );
			}
		}

		return self::find( $id );
	}

	/**
	 * @param mixed $tags Array or comma-separated text.
	 * @return string[]
	 */
	public static function clean_tags( $tags ) {
		if ( is_string( $tags ) ) {
			$tags = explode( ',', $tags );
		}

		$out = array();

		foreach ( (array) $tags as $tag ) {
			$tag = self::cut( str_replace( '|', '', sanitize_text_field( (string) $tag ) ), 30 );

			if ( '' !== $tag && ! isset( $out[ strtolower( $tag ) ] ) ) {
				$out[ strtolower( $tag ) ] = $tag;
			}
		}

		return array_slice( array_values( $out ), 0, self::MAX_TAGS );
	}

	/**
	 * Clinic staff who can own contacts and be assigned tasks.
	 *
	 * @return array user ID => name.
	 */
	public static function staff() {
		static $staff = null;

		if ( null === $staff ) {
			$staff = array();

			foreach ( get_users( array( 'role__in' => array( 'administrator', Roles::RECEPTIONIST_ROLE ), 'orderby' => 'display_name' ) ) as $user ) {
				$staff[ (int) $user->ID ] = $user->display_name;
			}
		}

		return $staff;
	}

	/* ---------------------------------------------------------------------
	 * Notes, tasks, activity
	 * ------------------------------------------------------------------- */

	/**
	 * @return int|\WP_Error Note ID.
	 */
	public static function add_note( $contact_id, $body, $user_id ) {
		global $wpdb;

		$body = trim( sanitize_textarea_field( (string) $body ) );

		if ( '' === $body ) {
			return new \WP_Error( 'doctor_ak_crm_note', __( 'Write the note first.', 'doctor-ak-portal' ) );
		}

		$wpdb->insert(
			self::table( self::T_NOTES ),
			array(
				'contact_id' => (int) $contact_id,
				'author_id'  => (int) $user_id,
				'body'       => self::cut( $body, 4000 ),
				'created_at' => current_time( 'mysql' ),
			)
		);

		self::touch( $contact_id, __( 'Note added', 'doctor-ak-portal' ) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array $input title, due_date, assignee_id (and, for tasks the
	 *                     system creates, origin + origin_id).
	 * @return int|\WP_Error Task ID.
	 */
	public static function add_task( $contact_id, array $input, $user_id ) {
		global $wpdb;

		$title    = self::cut( sanitize_text_field( isset( $input['title'] ) ? (string) $input['title'] : '' ), 191 );
		$due      = isset( $input['due_date'] ) ? (string) $input['due_date'] : '';
		$assignee = isset( $input['assignee_id'] ) ? (int) $input['assignee_id'] : 0;

		if ( '' === $title ) {
			return new \WP_Error( 'doctor_ak_crm_task', __( 'Describe the task, e.g. “Call to book a review visit”.', 'doctor-ak-portal' ), array( 'field' => 'title' ) );
		}

		if ( ! self::is_date( $due ) ) {
			return new \WP_Error( 'doctor_ak_crm_due', __( 'Choose a due date.', 'doctor-ak-portal' ), array( 'field' => 'due_date' ) );
		}

		if ( $assignee && ! array_key_exists( $assignee, self::staff() ) ) {
			return new \WP_Error( 'doctor_ak_crm_assignee', __( 'Assign the task to a member of the clinic staff.', 'doctor-ak-portal' ), array( 'field' => 'assignee_id' ) );
		}

		$wpdb->insert(
			self::table( self::T_TASKS ),
			array(
				'contact_id'   => (int) $contact_id,
				'title'        => $title,
				'due_date'     => $due,
				'assignee_id'  => $assignee,
				'status'       => self::TASK_OPEN,
				'outcome'      => '',
				'created_by'   => (int) $user_id,
				'created_at'   => current_time( 'mysql' ),
				'completed_by' => 0,
				'completed_at' => null,
				'origin'       => isset( $input['origin'] ) ? substr( sanitize_key( $input['origin'] ), 0, 20 ) : '',
				'origin_id'    => isset( $input['origin_id'] ) ? (int) $input['origin_id'] : 0,
			)
		);

		$task_id = (int) $wpdb->insert_id;
		self::log( $contact_id, $user_id, 'task', sprintf( /* translators: 1: task, 2: date. */ __( 'Task added: %1$s (due %2$s).', 'doctor-ak-portal' ), $title, date_i18n( get_option( 'date_format' ), strtotime( $due ) ) ) );
		self::touch( $contact_id, __( 'Task added', 'doctor-ak-portal' ) );

		return $task_id;
	}

	public static function find_task( $task_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( self::T_TASKS ) . ' WHERE id = %d', (int) $task_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
	}

	/**
	 * Marks a task done / not reached / cancelled / open again.
	 *
	 * @return array|\WP_Error Task row.
	 */
	public static function update_task( $task_id, $status, $outcome, $user_id ) {
		global $wpdb;

		$task = self::find_task( $task_id );

		if ( ! $task ) {
			return new \WP_Error( 'doctor_ak_crm_task_missing', __( 'That task no longer exists.', 'doctor-ak-portal' ) );
		}

		if ( ! isset( self::task_statuses()[ $status ] ) ) {
			return new \WP_Error( 'doctor_ak_crm_task_status', __( 'Unknown task status.', 'doctor-ak-portal' ) );
		}

		$open    = self::TASK_OPEN === $status;
		$outcome = self::cut( sanitize_textarea_field( (string) $outcome ), 1000 );

		$wpdb->update(
			self::table( self::T_TASKS ),
			array(
				'status'       => $status,
				'outcome'      => $outcome,
				'completed_by' => $open ? 0 : (int) $user_id,
				'completed_at' => $open ? null : current_time( 'mysql' ),
			),
			array( 'id' => (int) $task_id )
		);

		self::log( (int) $task['contact_id'], $user_id, 'task', sprintf( /* translators: 1: task, 2: status. */ __( 'Task “%1$s”: %2$s.', 'doctor-ak-portal' ), $task['title'], self::task_statuses()[ $status ] ) . ( '' !== $outcome ? ' ' . $outcome : '' ) );
		self::touch( (int) $task['contact_id'], __( 'Task updated', 'doctor-ak-portal' ) );

		// Reaching someone counts as contacting them.
		$contact = self::find( (int) $task['contact_id'] );

		if ( $contact && self::TASK_DONE === $status && self::STAGE_NEW === $contact['stage'] ) {
			self::update_fields( $contact['id'], array( 'stage' => self::STAGE_CONTACTED ), $user_id );
		}

		return self::find_task( $task_id );
	}

	/**
	 * Tasks of one contact, open first.
	 */
	public static function tasks_for( $contact_id ) {
		global $wpdb;

		return self::shape_tasks( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( self::T_TASKS ) . " WHERE contact_id = %d ORDER BY status = 'open' DESC, due_date ASC, id DESC", (int) $contact_id ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
	}

	/**
	 * Open tasks due by a date (default today), for "My tasks".
	 *
	 * @param int  $assignee_id Only this person's tasks (0 = everyone's).
	 * @param int  $scope_doctor Only contacts of this doctor (0 = all).
	 * @param bool $upcoming    Include tasks due later too.
	 * @return array[]
	 */
	public static function open_tasks( $assignee_id, $scope_doctor = 0, $upcoming = false ) {
		global $wpdb;

		$tasks    = self::table( self::T_TASKS );
		$contacts = self::table( self::T_CONTACTS );
		$where    = array( "t.status = 'open'" );
		$vals     = array();

		if ( ! $upcoming ) {
			$where[] = 't.due_date <= %s';
			$vals[]  = current_time( 'Y-m-d' );
		}

		if ( $assignee_id ) {
			$where[] = 't.assignee_id = %d';
			$vals[]  = (int) $assignee_id;
		}

		if ( $scope_doctor ) {
			$where[] = 'c.doctor_ids LIKE %s';
			$vals[]  = '%|' . (int) $scope_doctor . '|%';
		}

		$sql  = "SELECT t.*, c.name AS contact_name, c.phone AS contact_phone FROM {$tasks} t JOIN {$contacts} c ON c.id = t.contact_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY t.due_date ASC, t.id ASC LIMIT 200';
		$rows = $wpdb->get_results( $vals ? $wpdb->prepare( $sql, $vals ) : $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed fragments with placeholders.

		return self::shape_tasks( $rows );
	}

	private static function shape_tasks( $rows ) {
		$staff = self::staff();
		$today = current_time( 'Y-m-d' );

		return array_map(
			function ( $t ) use ( $staff, $today ) {
				$t['id']            = (int) $t['id'];
				$t['contact_id']    = (int) $t['contact_id'];
				$t['assignee_id']   = (int) $t['assignee_id'];
				$t['assignee_name'] = isset( $staff[ $t['assignee_id'] ] ) ? $staff[ $t['assignee_id'] ] : '';
				$t['overdue']       = self::TASK_OPEN === $t['status'] && $t['due_date'] < $today;
				$t['created_by_name'] = self::user_name( (int) $t['created_by'] );
				return $t;
			},
			$rows ? $rows : array()
		);
	}

	/**
	 * Records a staff action (and automatic stage moves) on a contact.
	 */
	public static function log( $contact_id, $user_id, $action, $summary ) {
		global $wpdb;

		$wpdb->insert(
			self::table( self::T_ACTIVITY ),
			array(
				'contact_id' => (int) $contact_id,
				'user_id'    => (int) $user_id,
				'action'     => substr( (string) $action, 0, 30 ),
				'summary'    => (string) $summary,
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	public static function touch( $contact_id, $label ) {
		self::update_row(
			$contact_id,
			array(
				'last_activity_at'    => current_time( 'mysql' ),
				'last_activity_label' => $label,
			)
		);
	}

	private static function user_name( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;

		return $user ? $user->display_name : '';
	}

	/* ---------------------------------------------------------------------
	 * Duplicates and merging (always confirmed by staff)
	 * ------------------------------------------------------------------- */

	/**
	 * Other contacts with the same phone or email.
	 *
	 * @return array[] Contacts.
	 */
	public static function duplicates( $contact_id ) {
		global $wpdb;

		$contact = self::find( $contact_id );

		if ( ! $contact || ( '' === $contact['phone_norm'] && '' === $contact['email_norm'] ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table( self::T_CONTACTS ) . ' WHERE id <> %d AND ( ( phone_norm <> %s AND phone_norm = %s ) OR ( email_norm <> %s AND email_norm = %s ) ) ORDER BY id ASC LIMIT 10', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
				$contact['id'],
				'',
				$contact['phone_norm'],
				'',
				$contact['email_norm']
			),
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'decode' ), $rows ? $rows : array() );
	}

	/**
	 * Merges $source_id into $target_id: bookings, requests, notes, tasks
	 * and history move across, tags combine, the furthest stage and the
	 * earliest follow-up date are kept; the source is removed.
	 *
	 * @return array|\WP_Error Merged contact.
	 */
	public static function merge( $target_id, $source_id, $user_id ) {
		global $wpdb;

		$target = self::find( $target_id );
		$source = self::find( $source_id );

		if ( ! $target || ! $source || $target['id'] === $source['id'] ) {
			return new \WP_Error( 'doctor_ak_crm_merge', __( 'Choose two different contacts to merge.', 'doctor-ak-portal' ) );
		}

		if ( $target['user_id'] > 0 && $source['user_id'] > 0 ) {
			return new \WP_Error( 'doctor_ak_crm_merge_accounts', __( 'Both have their own patient account, so they are kept separate.', 'doctor-ak-portal' ) );
		}

		foreach ( array( self::T_LINKS, self::T_NOTES, self::T_TASKS, self::T_ACTIVITY, Crm_Email::T_MESSAGES ) as $t ) {
			$wpdb->update( self::table( $t ), array( 'contact_id' => $target['id'] ), array( 'contact_id' => $source['id'] ) );
		}

		$stage = self::rank( $source['stage'] ) > self::rank( $target['stage'] ) ? $source['stage'] : $target['stage'];
		$dates = array_filter( array( $target['follow_up_date'], $source['follow_up_date'] ) );
		$data  = array(
			'tags'           => self::pipe( self::clean_tags( array_merge( $target['tags'], $source['tags'] ) ) ),
			'stage'          => $stage,
			'owner_id'       => $target['owner_id'] ? $target['owner_id'] : $source['owner_id'],
			'follow_up_date' => $dates ? min( $dates ) : null,
		);

		// If either record unsubscribed from offers and news, the merged one has.
		if ( empty( $target['marketing_opt_out'] ) && ! empty( $source['marketing_opt_out'] ) ) {
			$data['marketing_opt_out'] = $source['marketing_opt_out'];
			$data['opt_out_via']       = $source['opt_out_via'];
		}

		if ( $source['user_id'] > 0 ) {
			$data['user_id'] = $source['user_id'];
		}

		if ( '' === $target['phone_norm'] && '' !== $source['phone_norm'] ) {
			$data['phone']      = $source['phone'];
			$data['phone_norm'] = $source['phone_norm'];
		}

		if ( '' === $target['email_norm'] && '' !== $source['email_norm'] ) {
			$data['email']      = $source['email'];
			$data['email_norm'] = $source['email_norm'];
		}

		if ( strtotime( $source['created_at'] ) < strtotime( $target['created_at'] ) ) {
			$data['source']     = $source['source'];
			$data['created_at'] = $source['created_at'];
		}

		self::update_row( $target['id'], $data );
		$wpdb->delete( self::table( self::T_CONTACTS ), array( 'id' => $source['id'] ) );
		self::log( $target['id'], $user_id, 'merge', sprintf( /* translators: 1: name, 2: phone/email. */ __( 'Merged with “%1$s” (%2$s).', 'doctor-ak-portal' ), $source['name'], trim( $source['phone'] . ' ' . $source['email'] ) ) );

		return self::refresh( $target['id'] );
	}

	/* ---------------------------------------------------------------------
	 * Deleting
	 * ------------------------------------------------------------------- */

	/**
	 * Why a contact can't be deleted, or '' if it can. Someone with a
	 * patient account or a booking would only come back on the next sync
	 * (and their bookings and visits stay where they are anyway), so only
	 * leads without either — added by hand, an enquiry, a test entry — can
	 * be removed. The others can be marked Lost or merged instead.
	 *
	 * @param array $contact Contact.
	 * @return string
	 */
	public static function delete_block( array $contact ) {
		if ( $contact['user_id'] > 0 ) {
			return __( 'This person has a patient account, so they stay in the CRM. Mark them as Lost if you no longer want to follow them up.', 'doctor-ak-portal' );
		}

		if ( self::linked_ids( $contact['id'], self::LINK_APPOINTMENT ) ) {
			return __( 'This person has bookings, so they stay in the CRM. Mark them as Lost, or merge them if they’re a duplicate.', 'doctor-ak-portal' );
		}

		return '';
	}

	/**
	 * Removes a contact and their CRM notes, tasks and history. Service
	 * requests they made stay in Service Requests; emails already sent stay
	 * in their group email's results.
	 *
	 * @param int $id      Contact ID.
	 * @param int $user_id Acting user.
	 * @return true|\WP_Error
	 */
	public static function delete_contact( $id, $user_id ) {
		global $wpdb;

		$contact = self::find( $id );

		if ( ! $contact ) {
			return new \WP_Error( 'doctor_ak_crm_missing', __( 'That contact no longer exists.', 'doctor-ak-portal' ) );
		}

		$block = self::delete_block( $contact );

		if ( '' !== $block ) {
			return new \WP_Error( 'doctor_ak_crm_delete_blocked', $block );
		}

		foreach ( array( self::T_LINKS, self::T_NOTES, self::T_TASKS, self::T_ACTIVITY ) as $t ) {
			$wpdb->delete( self::table( $t ), array( 'contact_id' => $contact['id'] ) );
		}

		$wpdb->delete( self::table( self::T_CONTACTS ), array( 'id' => $contact['id'] ) );

		/**
		 * A contact was deleted from the CRM.
		 *
		 * @param array $contact The deleted contact.
		 * @param int   $user_id Who deleted it.
		 */
		do_action( 'doctor_ak_crm_contact_deleted', $contact, (int) $user_id );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Timeline
	 * ------------------------------------------------------------------- */

	/**
	 * Everything about a contact, newest first.
	 *
	 * @param int $contact_id   Contact ID.
	 * @param int $scope_doctor Only this doctor's bookings/visits/requests (0 = all).
	 * @return array[] { at, type, title, detail, meta }
	 */
	public static function timeline( $contact_id, $scope_doctor = 0 ) {
		global $wpdb;

		$contact = self::find( $contact_id );

		if ( ! $contact ) {
			return array();
		}

		$items   = array();
		$hidden  = 0;
		$doctors = array();
		$name    = function ( $id ) use ( &$doctors ) {
			if ( ! isset( $doctors[ $id ] ) ) {
				$u              = $id ? get_userdata( $id ) : false;
				$doctors[ $id ] = $u ? 'Dr. ' . trim( $u->first_name . ' ' . $u->last_name ) : '';
			}
			return $doctors[ $id ];
		};
		$statuses = Appointments::status_options();

		foreach ( self::appointment_ids( $contact ) as $id ) {
			$a = Appointments::find( $id );

			if ( empty( $a ) ) {
				continue;
			}

			if ( $scope_doctor && (int) $a['doctor_id'] !== $scope_doctor ) {
				++$hidden;
				continue;
			}

			$post   = get_post( $id );
			$when   = trim( $a['date'] . ' ' . $a['time'] );
			$detail = array_filter(
				array(
					$name( (int) $a['doctor_id'] ),
					(string) $a['service_name'],
					Appointments::TYPE_VIDEO === $a['type'] ? __( 'Video', 'doctor-ak-portal' ) : __( 'Clinic', 'doctor-ak-portal' ),
					(float) $a['charge'] > 0 ? 'PKR ' . number_format_i18n( (float) $a['charge'] ) . ' · ' . ( Appointments::PAYMENT_STATUS_PAID === $a['payment_status'] ? __( 'paid', 'doctor-ak-portal' ) : __( 'unpaid', 'doctor-ak-portal' ) ) : '',
					Appointments::REFUND_STATUS_PROCESSED === $a['refund_status'] ? sprintf( /* translators: %s: amount. */ __( 'refunded PKR %s', 'doctor-ak-portal' ), number_format_i18n( (float) $a['refund_amount'] ) ) : ( Appointments::REFUND_STATUS_REQUESTED === $a['refund_status'] ? __( 'refund requested', 'doctor-ak-portal' ) : '' ),
				)
			);

			$items[] = array(
				'at'     => $post ? $post->post_date : $when,
				'type'   => 'appointment',
				'title'  => sprintf( /* translators: 1: date/time, 2: status. */ __( 'Appointment for %1$s — %2$s', 'doctor-ak-portal' ), date_i18n( 'j M Y, g:i a', strtotime( $when ) ), isset( $statuses[ $a['status'] ] ) ? $statuses[ $a['status'] ] : $a['status'] ),
				'detail' => implode( ' · ', $detail ),
				'meta'   => array( 'appointment_id' => (int) $id ),
			);

			$encounter = Encounters::find_by_appointment( $id );

			if ( $encounter ) {
				$items[] = array(
					'at'     => $encounter['checked_in_at'],
					'type'   => 'visit',
					'title'  => __( 'Checked in', 'doctor-ak-portal' ),
					'detail' => $name( (int) $encounter['doctor_id'] ),
					'meta'   => array( 'encounter_id' => (int) $encounter['id'] ),
				);
			}
		}

		foreach ( self::walk_in_encounters( $contact ) as $e ) {
			if ( $scope_doctor && (int) $e['doctor_id'] !== $scope_doctor ) {
				++$hidden;
				continue;
			}

			$items[] = array(
				'at'     => $e['checked_in_at'],
				'type'   => 'visit',
				'title'  => __( 'Walk-in visit', 'doctor-ak-portal' ),
				'detail' => $name( (int) $e['doctor_id'] ),
				'meta'   => array( 'encounter_id' => (int) $e['id'] ),
			);
		}

		foreach ( self::linked_ids( $contact['id'], self::LINK_REQUEST ) as $rid ) {
			$r = Service_Requests::find( $rid );

			if ( ! $r ) {
				continue;
			}

			if ( $scope_doctor && (int) $r['doctor_id'] !== $scope_doctor ) {
				++$hidden;
				continue;
			}

			$items[] = array(
				'at'     => $r['created_at'],
				'type'   => 'request',
				'title'  => sprintf( /* translators: %s: service. */ __( 'Requested “%s”', 'doctor-ak-portal' ), $r['service_name'] ),
				'detail' => trim( (string) $r['notes'] ),
				'meta'   => array(),
			);
		}

		if ( $contact['user_id'] > 0 ) {
			$reviews = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Doctor_Reviews::table_name() . ' WHERE patient_id = %d', $contact['user_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

			foreach ( $reviews ? $reviews : array() as $rv ) {
				if ( $scope_doctor && (int) $rv['doctor_id'] !== $scope_doctor ) {
					++$hidden;
					continue;
				}

				$stars   = (int) $rv['rating'];
				$items[] = array(
					'at'     => $rv['updated_at'],
					'type'   => 'review',
					/* translators: 1: doctor name, 2: stars. */
					'title'  => sprintf( __( 'Rated %1$s %2$d of 5', 'doctor-ak-portal' ), $name( (int) $rv['doctor_id'] ), $stars ),
					'detail' => trim( (string) $rv['comment'] ),
					'meta'   => array( 'rating' => $stars ),
				);
			}
		}

		$notes = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( self::T_NOTES ) . ' WHERE contact_id = %d', $contact['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		foreach ( $notes ? $notes : array() as $n ) {
			$items[] = array(
				'at'     => $n['created_at'],
				'type'   => 'note',
				'title'  => sprintf( /* translators: %s: author. */ __( 'Note by %s', 'doctor-ak-portal' ), self::user_name( (int) $n['author_id'] ) ),
				'detail' => $n['body'],
				'meta'   => array(),
			);
		}

		$log = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( self::T_ACTIVITY ) . ' WHERE contact_id = %d', $contact['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		foreach ( $log ? $log : array() as $l ) {
			$by      = self::user_name( (int) $l['user_id'] );
			$items[] = array(
				'at'     => $l['created_at'],
				'type'   => 'activity',
				'title'  => $l['summary'],
				'detail' => '' !== $by ? sprintf( /* translators: %s: name. */ __( 'by %s', 'doctor-ak-portal' ), $by ) : __( 'automatic', 'doctor-ak-portal' ),
				'meta'   => array(),
			);
		}

		// Emails (subjects only for automatic ones) — clinic staff only.
		if ( ! $scope_doctor ) {
			$items = array_merge( $items, Crm_Email::timeline_items( $contact['id'] ) );
		}

		usort(
			$items,
			function ( $a, $b ) {
				return strcmp( (string) $b['at'], (string) $a['at'] );
			}
		);

		if ( $hidden ) {
			$items[] = array(
				'at'     => '',
				'type'   => 'hidden',
				'title'  => sprintf( /* translators: %d: count. */ _n( '%d item with another doctor isn’t shown.', '%d items with other doctors aren’t shown.', $hidden, 'doctor-ak-portal' ), $hidden ),
				'detail' => '',
				'meta'   => array(),
			);
		}

		return $items;
	}

	/* ---------------------------------------------------------------------
	 * Export
	 * ------------------------------------------------------------------- */

	/**
	 * CSV of contacts (cells starting like a formula are prefixed with ').
	 *
	 * @param array[] $rows Contacts.
	 * @return string
	 */
	public static function to_csv( array $rows ) {
		$stages  = self::stages();
		$sources = self::sources();
		$staff   = self::staff();
		$cell    = function ( $v ) {
			$v = (string) $v;

			if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) {
				$v = "'" . $v;
			}

			return '"' . str_replace( '"', '""', $v ) . '"';
		};
		$lines   = array( implode( ',', array( 'Name', 'Phone', 'Email', 'Source', 'Stage', 'Owner', 'Tags', 'Next follow-up', 'Last visit', 'Next appointment', 'Visits', 'Last activity', 'Added' ) ) );

		foreach ( $rows as $r ) {
			$lines[] = implode(
				',',
				array_map(
					$cell,
					array(
						$r['name'],
						$r['phone'],
						$r['email'],
						isset( $sources[ $r['source'] ] ) ? $sources[ $r['source'] ] : $r['source'],
						isset( $stages[ $r['stage'] ] ) ? $stages[ $r['stage'] ] : $r['stage'],
						isset( $staff[ $r['owner_id'] ] ) ? $staff[ $r['owner_id'] ] : '',
						implode( '; ', $r['tags'] ),
						$r['follow_up_date'],
						$r['last_visit'],
						$r['next_appointment'],
						isset( $r['summary']['visits'] ) ? $r['summary']['visits'] : 0,
						$r['last_activity_at'],
						$r['created_at'],
					)
				)
			);
		}

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/* ---------------------------------------------------------------------
	 * One-time import of existing records
	 * ------------------------------------------------------------------- */

	/**
	 * Imports existing patients, bookings and service requests in small
	 * batches (called repeatedly from the CRM screen until done).
	 *
	 * @param int $limit Records per call.
	 * @return array { done: bool, imported: int, phase: string }
	 */
	public static function backfill_batch( $limit = 100 ) {
		global $wpdb;

		$state = wp_parse_args(
			(array) get_option( self::BACKFILL_OPTION, array() ),
			array(
				'phase'    => 'patients',
				'after'    => 0,
				'imported' => 0,
				'done'     => false,
			)
		);

		if ( $state['done'] ) {
			return $state;
		}

		$ids = array();

		if ( 'patients' === $state['phase'] ) {
			// Patient accounts after the last one done (get_users() can't page by ID).
			$ids = array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT u.ID FROM {$wpdb->users} u JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = %s WHERE m.meta_value LIKE %s AND u.ID > %d ORDER BY u.ID ASC LIMIT %d",
						$wpdb->get_blog_prefix() . 'capabilities',
						'%"' . $wpdb->esc_like( Roles::PATIENT_ROLE ) . '"%',
						(int) $state['after'],
						$limit
					)
				)
			);

			foreach ( $ids as $id ) {
				$cid = self::ensure_for_user( $id, 'account' );

				if ( $cid ) {
					self::refresh( $cid );
				}
			}
		} elseif ( 'appointments' === $state['phase'] ) {
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT %d", Appointments::POST_TYPE, (int) $state['after'], $limit ) ) );

			foreach ( $ids as $id ) {
				self::sync_appointment( $id );
			}
		} else {
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Service_Requests::table_name() . ' WHERE id > %d ORDER BY id ASC LIMIT %d', (int) $state['after'], $limit ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

			foreach ( $ids as $id ) {
				self::on_service_request( $id );
			}
		}

		$state['imported'] += count( $ids );

		if ( count( $ids ) < $limit ) {
			$next           = array( 'patients' => 'appointments', 'appointments' => 'requests', 'requests' => '' );
			$state['phase'] = $next[ $state['phase'] ];
			$state['after'] = 0;
			$state['done']  = '' === $state['phase'];

			// Everything was just summarised with today's rules.
			if ( $state['done'] ) {
				update_option( self::REFRESH_OPTION, array( 'after' => 0, 'done' => true ), false );
			}
		} else {
			$state['after'] = max( $ids );
		}

		update_option( self::BACKFILL_OPTION, $state, false );

		return $state;
	}

	public static function backfill_done() {
		$state = get_option( self::BACKFILL_OPTION, array() );

		return ! empty( $state['done'] );
	}

	/**
	 * Re-summarises existing contacts in batches (first visit, first-seen
	 * date) — once, on sites imported before Reports existed.
	 *
	 * @param int $limit Contacts per call.
	 * @return array { done: bool, after: int, total: int }
	 */
	public static function refresh_batch( $limit = 100 ) {
		global $wpdb;

		$state = wp_parse_args( (array) get_option( self::REFRESH_OPTION, array() ), array( 'after' => 0, 'done' => false ) );

		if ( $state['done'] ) {
			return $state;
		}

		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table( self::T_CONTACTS ) . ' WHERE id > %d ORDER BY id ASC LIMIT %d', (int) $state['after'], (int) $limit ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		foreach ( $ids as $id ) {
			self::refresh( $id );
		}

		$state['after'] = $ids ? max( $ids ) : $state['after'];
		$state['done']  = count( $ids ) < $limit;
		$state['total'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( self::T_CONTACTS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		update_option( self::REFRESH_OPTION, $state, false );

		return $state;
	}

	public static function refresh_done() {
		$state = get_option( self::REFRESH_OPTION, array() );

		return ! empty( $state['done'] );
	}
}
