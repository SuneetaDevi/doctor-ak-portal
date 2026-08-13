<?php
/**
 * In-app notifications (the "Notifications" tab on the doctor, patient, and
 * admin dashboards) — distinct from includes/class-notifications.php, which
 * sends the *email* versions of these same events.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notification_Center
 *
 * Hooked to the exact same appointment-lifecycle actions
 * includes/class-notifications.php already listens to for emails
 * ('doctor_ak_appointment_created', '_cancelled', '_paid', '_completed') —
 * each one writes one row per relevant recipient (the patient, the doctor,
 * and every administrator), so every dashboard's Notifications tab reflects
 * real events, never fabricated ones.
 */
class Notification_Center {

	const TABLE = 'dak_notifications';

	const TYPE_BOOKED             = 'booked';
	const TYPE_CANCELLED          = 'cancelled';
	const TYPE_PAID               = 'paid';
	const TYPE_COMPLETED          = 'completed';
	const TYPE_DOCTOR_REGISTERED  = 'doctor_registered';
	const TYPE_DOCTOR_APPROVED    = 'doctor_approved';
	const TYPE_REFUND_REQUESTED   = 'refund_requested';
	const TYPE_REFUND_PROCESSED   = 'refund_processed';
	const TYPE_RESCHEDULED        = 'rescheduled';
	const TYPE_REMINDER           = 'reminder';

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
	 * Hook callback: a new appointment was created.
	 *
	 * @param int   $appointment_id New appointment's post ID.
	 * @param array $data           Raw data passed to Appointments::create() (unused, kept for hook signature).
	 * @return void
	 */
	public static function notify_created( $appointment_id, $data ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		$has_pending_charge = (float) $appt['charge'] > 0 && ! $appt['is_paid'];

		if ( $appt['patient_id'] > 0 ) {
			self::record(
				$appt['patient_id'],
				self::TYPE_BOOKED,
				$has_pending_charge
					? sprintf(
						/* translators: %s: doctor's display name. */
						__( 'Your appointment with Dr. %s is scheduled — pay from your dashboard to confirm.', 'doctor-ak-portal' ),
						$appt['doctor_name']
					)
					: sprintf(
						/* translators: %s: doctor's display name. */
						__( 'Your appointment with Dr. %s has been booked.', 'doctor-ak-portal' ),
						$appt['doctor_name']
					),
				$appointment_id
			);
		}

		self::record(
			$appt['doctor_id'],
			self::TYPE_BOOKED,
			$has_pending_charge
				? sprintf(
					/* translators: %s: patient's display name. */
					__( 'New appointment with %s — payment pending.', 'doctor-ak-portal' ),
					$appt['patient_name']
				)
				: sprintf(
					/* translators: %s: patient's display name. */
					__( 'New appointment booked with %s.', 'doctor-ak-portal' ),
					$appt['patient_name']
				),
			$appointment_id
		);

		self::notify_admins(
			sprintf(
				/* translators: 1: patient's display name, 2: doctor's display name. */
				__( '%1$s booked an appointment with Dr. %2$s.', 'doctor-ak-portal' ),
				$appt['patient_name'],
				$appt['doctor_name']
			),
			self::TYPE_BOOKED,
			$appointment_id
		);
	}

	/**
	 * Admin/receptionist-triggered: sends the patient a one-off reminder
	 * about an upcoming appointment (and its pending payment, if any) — used
	 * by the Appointments list's bulk "Send reminder" action, not tied to a
	 * lifecycle hook like the notify_*() methods above.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return bool True if a reminder was recorded, false if there's no patient to notify.
	 */
	public static function notify_reminder( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) || empty( $appt['patient_id'] ) ) {
			return false;
		}

		$has_pending_charge = (float) $appt['charge'] > 0 && ! $appt['is_paid'];

		self::record(
			$appt['patient_id'],
			self::TYPE_REMINDER,
			$has_pending_charge
				? sprintf(
					/* translators: 1: doctor's display name, 2: appointment date/time. */
					__( 'Reminder: your appointment with Dr. %1$s is on %2$s — payment is still pending.', 'doctor-ak-portal' ),
					$appt['doctor_name'],
					$appt['datetime_label']
				)
				: sprintf(
					/* translators: 1: doctor's display name, 2: appointment date/time. */
					__( 'Reminder: your appointment with Dr. %1$s is on %2$s.', 'doctor-ak-portal' ),
					$appt['doctor_name'],
					$appt['datetime_label']
				),
			$appointment_id
		);

		return true;
	}

	/**
	 * Hook callback: an appointment was cancelled.
	 *
	 * @param int $appointment_id Cancelled appointment's post ID.
	 * @return void
	 */
	public static function notify_cancelled( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		if ( $appt['patient_id'] > 0 ) {
			self::record(
				$appt['patient_id'],
				self::TYPE_CANCELLED,
				sprintf(
					/* translators: %s: doctor's display name. */
					__( 'Your appointment with Dr. %s has been cancelled.', 'doctor-ak-portal' ),
					$appt['doctor_name']
				),
				$appointment_id
			);
		}

		self::record(
			$appt['doctor_id'],
			self::TYPE_CANCELLED,
			sprintf(
				/* translators: %s: patient's display name. */
				__( 'Your appointment with %s has been cancelled.', 'doctor-ak-portal' ),
				$appt['patient_name']
			),
			$appointment_id
		);

		self::notify_admins(
			sprintf(
				/* translators: 1: patient's display name, 2: doctor's display name. */
				__( '%1$s cancelled their appointment with Dr. %2$s.', 'doctor-ak-portal' ),
				$appt['patient_name'],
				$appt['doctor_name']
			),
			self::TYPE_CANCELLED,
			$appointment_id
		);
	}

	/**
	 * Hook callback: an appointment was rescheduled to a new date/time.
	 *
	 * @param int $appointment_id Rescheduled appointment's post ID.
	 * @return void
	 */
	public static function notify_rescheduled( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		if ( $appt['patient_id'] > 0 ) {
			self::record(
				$appt['patient_id'],
				self::TYPE_RESCHEDULED,
				sprintf(
					/* translators: 1: doctor's display name, 2: date, 3: time. */
					__( 'Your appointment with Dr. %1$s has been rescheduled to %2$s at %3$s.', 'doctor-ak-portal' ),
					$appt['doctor_name'],
					$appt['date'],
					$appt['time']
				),
				$appointment_id
			);
		}

		self::record(
			$appt['doctor_id'],
			self::TYPE_RESCHEDULED,
			sprintf(
				/* translators: 1: patient's display name, 2: date, 3: time. */
				__( 'Your appointment with %1$s has been rescheduled to %2$s at %3$s.', 'doctor-ak-portal' ),
				$appt['patient_name'],
				$appt['date'],
				$appt['time']
			),
			$appointment_id
		);

		self::notify_admins(
			sprintf(
				/* translators: 1: patient's display name, 2: doctor's display name. */
				__( '%1$s\'s appointment with Dr. %2$s was rescheduled.', 'doctor-ak-portal' ),
				$appt['patient_name'],
				$appt['doctor_name']
			),
			self::TYPE_RESCHEDULED,
			$appointment_id
		);
	}

	/**
	 * Hook callback: an appointment's online payment succeeded.
	 *
	 * @param int $appointment_id Paid appointment's post ID.
	 * @return void
	 */
	public static function notify_paid( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		if ( $appt['patient_id'] > 0 ) {
			self::record(
				$appt['patient_id'],
				self::TYPE_PAID,
				sprintf(
					/* translators: %s: doctor's display name. */
					__( 'Payment received for your appointment with Dr. %s.', 'doctor-ak-portal' ),
					$appt['doctor_name']
				),
				$appointment_id
			);
		}

		self::record(
			$appt['doctor_id'],
			self::TYPE_PAID,
			sprintf(
				/* translators: %s: patient's display name. */
				__( '%s has paid for their appointment.', 'doctor-ak-portal' ),
				$appt['patient_name']
			),
			$appointment_id
		);

		self::notify_admins(
			sprintf(
				/* translators: 1: patient's display name, 2: doctor's display name. */
				__( '%1$s paid for their appointment with Dr. %2$s.', 'doctor-ak-portal' ),
				$appt['patient_name'],
				$appt['doctor_name']
			),
			self::TYPE_PAID,
			$appointment_id
		);
	}

	/**
	 * Hook callback: an appointment was marked completed (manually by the
	 * doctor, or automatically — see Appointments::mark_completed()/
	 * auto_complete_past_appointments()).
	 *
	 * @param int $appointment_id Completed appointment's post ID.
	 * @return void
	 */
	public static function notify_completed( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		if ( $appt['patient_id'] > 0 ) {
			self::record(
				$appt['patient_id'],
				self::TYPE_COMPLETED,
				sprintf(
					/* translators: %s: doctor's display name. */
					__( 'Your appointment with Dr. %s is complete.', 'doctor-ak-portal' ),
					$appt['doctor_name']
				),
				$appointment_id
			);
		}

		self::notify_admins(
			sprintf(
				/* translators: 1: patient's display name, 2: doctor's display name. */
				__( '%1$s\'s appointment with Dr. %2$s is complete.', 'doctor-ak-portal' ),
				$appt['patient_name'],
				$appt['doctor_name']
			),
			self::TYPE_COMPLETED,
			$appointment_id
		);
	}

	/**
	 * Hook callback: a new doctor account was created and is awaiting admin
	 * approval (see Registration_Handler::handle_register()).
	 *
	 * @param int $doctor_id New doctor's user ID.
	 * @return void
	 */
	public static function notify_doctor_registered( $doctor_id ) {
		$doctor = get_userdata( $doctor_id );

		if ( ! $doctor ) {
			return;
		}

		self::notify_admins(
			sprintf(
				/* translators: %s: doctor's display name. */
				__( 'Dr. %s has registered and is awaiting your approval.', 'doctor-ak-portal' ),
				self::doctor_display_name( $doctor )
			),
			self::TYPE_DOCTOR_REGISTERED,
			0
		);
	}

	/**
	 * Hook callback: a pending doctor account was approved by an admin (see
	 * Doctor_Requests_Handler::handle_approve()).
	 *
	 * @param int $doctor_id Approved doctor's user ID.
	 * @return void
	 */
	public static function notify_doctor_approved( $doctor_id ) {
		self::record(
			$doctor_id,
			self::TYPE_DOCTOR_APPROVED,
			__( 'Your account has been approved. You can now log in.', 'doctor-ak-portal' ),
			0
		);
	}

	/**
	 * Hook callback: a patient requested a refund — notifies every admin.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function notify_refund_requested( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		self::notify_admins(
			sprintf(
				/* translators: 1: patient's display name, 2: doctor's display name, 3: amount. */
				__( '%1$s requested a refund of PKR%3$s for their appointment with Dr. %2$s.', 'doctor-ak-portal' ),
				$appt['patient_name'],
				$appt['doctor_name'],
				number_format( (float) $appt['refund_amount'], 0 )
			),
			self::TYPE_REFUND_REQUESTED,
			$appointment_id
		);
	}

	/**
	 * Hook callback: an admin processed a refund — notifies the patient.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function notify_refund_processed( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		$patient_id = (int) get_post_meta( $appointment_id, 'doctor_ak_appointment_patient_id', true );

		if ( $patient_id > 0 ) {
			self::record(
				$patient_id,
				self::TYPE_REFUND_PROCESSED,
				sprintf(
					/* translators: 1: amount, 2: doctor's display name. */
					__( 'Your refund of PKR%1$s for your appointment with Dr. %2$s has been processed.', 'doctor-ak-portal' ),
					number_format( (float) $appt['refund_amount'], 0 ),
					$appt['doctor_name']
				),
				$appointment_id
			);
		}
	}

	/**
	 * A doctor user's display name, falling back to their WP display name.
	 *
	 * @param \WP_User $doctor Doctor user.
	 * @return string
	 */
	private static function doctor_display_name( \WP_User $doctor ) {
		$name = trim( $doctor->first_name . ' ' . $doctor->last_name );

		return '' !== $name ? $name : $doctor->display_name;
	}

	/**
	 * Records one notification for every administrator account.
	 *
	 * @param string $message        Notification text.
	 * @param string $type           One of the TYPE_* constants.
	 * @param int    $appointment_id Related appointment's post ID.
	 * @return void
	 */
	private static function notify_admins( $message, $type, $appointment_id ) {
		foreach ( self::admin_user_ids() as $admin_id ) {
			self::record( $admin_id, $type, $message, $appointment_id );
		}
	}

	/**
	 * Every administrator's user ID.
	 *
	 * @return array
	 */
	private static function admin_user_ids() {
		static $ids = null;

		if ( null === $ids ) {
			$ids = get_users(
				array(
					'role'   => 'administrator',
					'fields' => 'ID',
				)
			);
		}

		return $ids;
	}

	/**
	 * Inserts one notification row.
	 *
	 * @param int    $recipient_id   User who should see this.
	 * @param string $type           One of the TYPE_* constants.
	 * @param string $message        Notification text.
	 * @param int    $appointment_id Related appointment's post ID.
	 * @return void
	 */
	private static function record( $recipient_id, $type, $message, $appointment_id ) {
		global $wpdb;

		$wpdb->insert(
			self::table_name(),
			array(
				'recipient_id'   => (int) $recipient_id,
				'type'           => $type,
				'message'        => $message,
				'appointment_id' => (int) $appointment_id,
				'is_read'        => 0,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * A user's notifications, most recent first.
	 *
	 * @param int    $user_id     Recipient's user ID.
	 * @param int    $limit       Max rows to return. Default 100.
	 * @param string $date_filter Optional 'YYYY-MM-DD' — only notifications from that day.
	 * @return array List of `array( 'id', 'type', 'message', 'is_read', 'date', 'date_raw', 'appointment_id' )`.
	 */
	public static function for_user( $user_id, $limit = 100, $date_filter = '' ) {
		global $wpdb;

		$table  = self::table_name();
		$where  = 'recipient_id = %d';
		$params = array( $user_id );

		if ( '' !== $date_filter ) {
			$where   .= ' AND DATE(created_at) = %s';
			$params[] = $date_filter;
		}

		$params[] = $limit;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, type, message, appointment_id, is_read, created_at FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is our own table_name(), $where is built from our own hardcoded fragments above, not user input.
				$params
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			function ( $row ) {
				$timestamp = strtotime( $row['created_at'] );

				return array(
					'id'             => (int) $row['id'],
					'type'           => $row['type'],
					'message'        => $row['message'],
					'appointment_id' => (int) $row['appointment_id'],
					'is_read'        => '1' === $row['is_read'],
					'date'           => $timestamp ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '',
					'date_raw'       => $timestamp ? date_i18n( 'Y-m-d', $timestamp ) : '',
				);
			},
			$rows
		);
	}

	/**
	 * Groups a for_user() result into "Today" / "Yesterday" / "Earlier" —
	 * the Facebook/Instagram-style buckets the Notifications tab (shared by
	 * all three dashboards) displays as separate sections.
	 *
	 * @param array $notifications Rows from for_user().
	 * @return array `array( 'today' => [...], 'yesterday' => [...], 'earlier' => [...] )`, each in the same order they were passed in.
	 */
	public static function group_by_recency( array $notifications ) {
		$today     = current_time( 'Y-m-d' );
		$yesterday = gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) );

		$groups = array(
			'today'     => array(),
			'yesterday' => array(),
			'earlier'   => array(),
		);

		foreach ( $notifications as $notification ) {
			if ( $notification['date_raw'] === $today ) {
				$groups['today'][] = $notification;
			} elseif ( $notification['date_raw'] === $yesterday ) {
				$groups['yesterday'][] = $notification;
			} else {
				$groups['earlier'][] = $notification;
			}
		}

		return $groups;
	}

	/**
	 * A user's unread notification count, for the sidebar badge.
	 *
	 * @param int $user_id Recipient's user ID.
	 * @return int
	 */
	public static function unread_count( $user_id ) {
		global $wpdb;

		$table = self::table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE recipient_id = %d AND is_read = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is our own table_name(), not user input.
				$user_id
			)
		);
	}

	/**
	 * Marks a single notification read — ownership-checked (only the
	 * recipient it belongs to can mark it read).
	 *
	 * @param int $notification_id Notification row ID.
	 * @param int $user_id         Must match the notification's recipient.
	 * @return bool
	 */
	public static function mark_read( $notification_id, $user_id ) {
		global $wpdb;

		$updated = $wpdb->update(
			self::table_name(),
			array( 'is_read' => 1 ),
			array(
				'id'           => (int) $notification_id,
				'recipient_id' => (int) $user_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Marks every one of a user's notifications read.
	 *
	 * @param int $user_id Recipient's user ID.
	 * @return void
	 */
	public static function mark_all_read( $user_id ) {
		global $wpdb;

		$wpdb->update(
			self::table_name(),
			array( 'is_read' => 1 ),
			array( 'recipient_id' => (int) $user_id ),
			array( '%d' ),
			array( '%d' )
		);
	}
}
