<?php
/**
 * Email notifications to patients and doctors for appointment events.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

use DoctorAKPortal\Frontend\Site_Footer;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notifications
 *
 * Hooked to the appointment lifecycle actions Appointments already fires
 * (or now fires — see cancel()/mark_paid()) — 'doctor_ak_appointment_created',
 * '_cancelled', '_paid' — plus two WP-Cron events (registered by
 * Activator/cleared by Deactivator, and self-healed on 'init' via
 * ensure_video_link_cron_scheduled() for installs activated before it
 * existed): 'doctor_ak_appointment_reminders' (CRON_HOOK, hourly) for the
 * day-before reminder, and 'doctor_ak_video_link_emails'
 * (VIDEO_LINK_CRON_HOOK, every 5 minutes) for the video-join-link email
 * sent shortly before a paid video appointment starts. Each event type can
 * be turned off from Settings → Email Notifications
 * (Admin\Notification_Settings), which also controls the From name/email
 * used on every email this class sends.
 *
 * Every email is rendered by Email_Template (one shared HTML design plus a
 * plain-text version); this class decides who gets what, and what each
 * recipient needs to see and do. The review-request email (sent later, see
 * Review_Requests) is built here too, so all email wording lives in one
 * place.
 */
class Notifications {

	const OPTION_NOTIFY_BOOKING             = 'doctor_ak_notify_booking';
	const OPTION_NOTIFY_CANCELLED           = 'doctor_ak_notify_cancelled';
	const OPTION_NOTIFY_PAID                = 'doctor_ak_notify_paid';
	const OPTION_NOTIFY_REMINDER            = 'doctor_ak_notify_reminder';
	const OPTION_NOTIFY_VIDEO_LINK          = 'doctor_ak_notify_video_link';
	const OPTION_NOTIFY_DOCTOR_REGISTRATION = 'doctor_ak_notify_doctor_registration';
	const OPTION_NOTIFY_REFUND              = 'doctor_ak_notify_refund';
	const OPTION_NOTIFY_RESCHEDULED         = 'doctor_ak_notify_rescheduled';
	const OPTION_NOTIFY_PATIENT_WELCOME     = 'doctor_ak_notify_patient_welcome';
	const OPTION_FROM_NAME                  = 'doctor_ak_notify_from_name';
	const OPTION_FROM_EMAIL                 = 'doctor_ak_notify_from_email';

	const CRON_HOOK            = 'doctor_ak_appointment_reminders';
	const VIDEO_LINK_CRON_HOOK = 'doctor_ak_video_link_emails';

	/**
	 * Registers a 5-minute WP-Cron interval (hooked to 'cron_schedules') for
	 * VIDEO_LINK_CRON_HOOK — the hourly interval the reminder cron uses is
	 * far too coarse to reliably catch each appointment's short
	 * VIDEO_JOIN_WINDOW_BEFORE_MINUTES window.
	 *
	 * @param array $schedules Existing registered intervals.
	 * @return array
	 */
	public static function add_cron_interval( $schedules ) {
		$schedules['doctor_ak_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 Minutes (Doctor AK Portal)', 'doctor-ak-portal' ),
		);

		return $schedules;
	}

	/**
	 * Schedules VIDEO_LINK_CRON_HOOK if it isn't already — a no-op on every
	 * request once it's scheduled. Activator::activate() also schedules it,
	 * but that only runs on a fresh plugin activation, not a code update on
	 * an install that was activated before this cron event existed.
	 *
	 * @return void
	 */
	public static function ensure_video_link_cron_scheduled() {
		if ( ! wp_next_scheduled( self::VIDEO_LINK_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'doctor_ak_five_minutes', self::VIDEO_LINK_CRON_HOOK );
		}
	}

	/**
	 * Whether a given notification type is enabled (default: all enabled).
	 *
	 * @param string $type 'booking', 'cancelled', 'paid', or 'reminder'.
	 * @return bool
	 */
	public static function is_enabled( $type ) {
		$option_map = array(
			'booking'             => self::OPTION_NOTIFY_BOOKING,
			'cancelled'           => self::OPTION_NOTIFY_CANCELLED,
			'paid'                => self::OPTION_NOTIFY_PAID,
			'reminder'            => self::OPTION_NOTIFY_REMINDER,
			'video_link'          => self::OPTION_NOTIFY_VIDEO_LINK,
			'doctor_registration' => self::OPTION_NOTIFY_DOCTOR_REGISTRATION,
			'refund'              => self::OPTION_NOTIFY_REFUND,
			'rescheduled'         => self::OPTION_NOTIFY_RESCHEDULED,
			'patient_welcome'     => self::OPTION_NOTIFY_PATIENT_WELCOME,
			'review_request'      => Review_Requests::OPTION_ENABLED,
		);

		if ( ! isset( $option_map[ $type ] ) ) {
			return false;
		}

		return '1' === get_option( $option_map[ $type ], '1' );
	}

	/**
	 * Whether a specific account wants to receive a given notification
	 * type's emails — a personal, per-account preference (Settings →
	 * Notifications on the Admin/Doctor/Receptionist/Patient dashboards),
	 * default on until that account explicitly turns it off. Covers
	 * 'booking'/'paid'/'cancelled' (sent to a specific patient/doctor
	 * involved in an appointment), 'announcements' (sent to every
	 * account in the directory when a new blog post is published — see
	 * notify_new_blog_post(); a new service only emails administrators, see
	 * notify_new_service()) and 'review_request' (a patient's "How was your
	 * appointment?" email, see Review_Requests). Guests (no account, no
	 * `user_id`) always receive — there's nowhere to store their preference.
	 *
	 * @param int    $user_id WP user ID, or 0 for a guest.
	 * @param string $type    'booking', 'paid', 'cancelled', 'announcements', or 'review_request'.
	 * @return bool
	 */
	public static function user_wants( $user_id, $type ) {
		if ( $user_id <= 0 ) {
			return true;
		}

		$value = get_user_meta( $user_id, 'doctor_ak_notify_' . $type, true );

		return '' === $value || '1' === $value;
	}

	/**
	 * Saves an account's own notification preferences.
	 *
	 * @param int       $user_id        WP user ID.
	 * @param bool      $booking        Receive "appointment booked" emails.
	 * @param bool      $paid           Receive "payment received" emails.
	 * @param bool      $cancelled      Receive "cancellation" emails.
	 * @param bool      $announcements  Receive "new blog post/service" announcement emails.
	 * @param bool|null $review_request Receive "How was your appointment?" emails; null leaves it unchanged (only patients see this toggle).
	 * @return void
	 */
	public static function save_user_preferences( $user_id, $booking, $paid, $cancelled, $announcements = true, $review_request = null ) {
		update_user_meta( $user_id, 'doctor_ak_notify_booking', $booking ? '1' : '0' );
		update_user_meta( $user_id, 'doctor_ak_notify_paid', $paid ? '1' : '0' );
		update_user_meta( $user_id, 'doctor_ak_notify_cancelled', $cancelled ? '1' : '0' );
		update_user_meta( $user_id, 'doctor_ak_notify_announcements', $announcements ? '1' : '0' );

		if ( null !== $review_request ) {
			update_user_meta( $user_id, 'doctor_ak_notify_review_request', $review_request ? '1' : '0' );
		}
	}

	/**
	 * An account's own notification preferences, for pre-filling the
	 * Settings → Notifications toggles.
	 *
	 * @param int $user_id WP user ID.
	 * @return array { booking: bool, paid: bool, cancelled: bool, announcements: bool, review_request: bool }
	 */
	public static function user_preferences( $user_id ) {
		return array(
			'booking'        => self::user_wants( $user_id, 'booking' ),
			'paid'           => self::user_wants( $user_id, 'paid' ),
			'cancelled'      => self::user_wants( $user_id, 'cancelled' ),
			'announcements'  => self::user_wants( $user_id, 'announcements' ),
			'review_request' => self::user_wants( $user_id, 'review_request' ),
		);
	}

	/**
	 * Every account in the directory — Doctor, Patient, Receptionist, and
	 * Administrator — for the "new blog post/service" announcement emails.
	 * This is deliberately broader than admin_emails() (administrators
	 * only, used for operational alerts like a refund request): the whole
	 * point of an announcement is that everyone using the portal sees it.
	 *
	 * @return \WP_User[]
	 */
	private static function directory_users() {
		return get_users(
			array(
				'role__in' => array( Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE, Roles::RECEPTIONIST_ROLE, 'administrator' ),
			)
		);
	}

	/**
	 * Emails every administrator that a doctor added a new service — an
	 * operational notice for the clinic's own staff, not a patient-facing
	 * announcement. Deliberately administrator-only (see admin_emails()):
	 * a service is only relevant to that one doctor's own patients, and
	 * broadcasting it to every patient/doctor/receptionist in the whole
	 * directory (as this used to, via directory_users()) meant patients
	 * with no relationship to that doctor were getting emailed about it.
	 * Hooked to 'doctor_ak_service_created' (see Services::create()).
	 *
	 * @param int   $service_id Newly created service's ID.
	 * @param int   $doctor_id  Doctor the service belongs to.
	 * @param array $fields     Sanitized service fields, see Services::fields().
	 * @return void
	 */
	public function notify_new_service( $service_id, $doctor_id, array $fields ) {
		$doctor_name = self::user_display_name( $doctor_id );
		$charge      = isset( $fields['charge'] ) ? (float) $fields['charge'] : 0.0;

		// The button opens the service's own public page — no sign-in needed.
		// An inactive service isn't shown there, so that one links to the
		// dashboard's services list instead.
		$detail_page = Page_Finder::url_for_shortcode( 'service_profile_view' );
		$is_public   = ! empty( $fields['active'] ) && '' !== $detail_page;
		$button_url  = $is_public ? add_query_arg( 'service_id', (int) $service_id, $detail_page ) : self::admin_url_for( 'services' );

		foreach ( self::admin_emails() as $admin_email ) {
			self::deliver(
				$admin_email,
				__( 'New Service Added', 'doctor-ak-portal' ),
				array(
					'preheader'   => sprintf( /* translators: %s: service name. */ __( '%s was added to the services list.', 'doctor-ak-portal' ), $fields['name'] ),
					'eyebrow'     => __( 'Services', 'doctor-ak-portal' ),
					'heading'     => __( 'New service added', 'doctor-ak-portal' ),
					'paragraphs'  => array( ! empty( $fields['active'] ) ? __( 'A new service has been added and is now listed on the website.', 'doctor-ak-portal' ) : __( 'A new service has been added. It is inactive, so it isn’t shown on the website yet.', 'doctor-ak-portal' ) ),
					'details'     => array(
						array( __( 'Service', 'doctor-ak-portal' ), $fields['name'] ),
						array( __( 'Doctor', 'doctor-ak-portal' ), '' !== $doctor_name ? sprintf( 'Dr. %s', $doctor_name ) : '' ),
						array( __( 'Price', 'doctor-ak-portal' ), self::money( $charge ) ),
					),
					'button'      => $button_url ? array( 'label' => $is_public ? __( 'View service', 'doctor-ak-portal' ) : __( 'View services', 'doctor-ak-portal' ), 'url' => $button_url ) : array(),
					'footer_note' => self::footer_note( 'admin' ),
				)
			);
		}
	}

	/**
	 * Emails every opted-in account in the directory about a newly
	 * published blog post. Hooked to 'transition_post_status' rather than
	 * 'publish_post' so it fires exactly once — on the transition INTO
	 * publish — instead of re-firing on every subsequent edit of an
	 * already-published post.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       The post transitioning status.
	 * @return void
	 */
	public function notify_new_blog_post( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
			return;
		}

		$intro = '' !== $post->post_excerpt
			? wp_strip_all_tags( $post->post_excerpt )
			: __( "We've just published a new blog post — take a look.", 'doctor-ak-portal' );

		$permalink = get_permalink( $post );

		foreach ( self::directory_users() as $user ) {
			if ( ! is_email( $user->user_email ) || ! self::user_wants( $user->ID, 'announcements' ) ) {
				continue;
			}

			self::deliver(
				$user->user_email,
				__( 'New Blog Post', 'doctor-ak-portal' ),
				array(
					'preheader'   => $intro,
					'eyebrow'     => __( 'New article', 'doctor-ak-portal' ),
					'heading'     => $post->post_title,
					'greeting'    => self::greeting( $user->first_name ),
					'paragraphs'  => array( $intro ),
					'button'      => $permalink ? array( 'label' => __( 'Read the article', 'doctor-ak-portal' ), 'url' => $permalink ) : array(),
					'footer_note' => __( 'You’re receiving this because announcement emails are turned on for your account. You can turn them off in your dashboard’s Settings.', 'doctor-ak-portal' ),
				)
			);
		}
	}

	/**
	 * AJAX/hook callback: a new appointment was created.
	 *
	 * @param int   $appointment_id New appointment's post ID.
	 * @param array $data           Raw data passed to Appointments::create() (unused, kept for hook signature).
	 * @return void
	 */
	public function notify_created( $appointment_id, $data ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		// A booking with a charge that hasn't been paid yet (the patient
		// chose "Pay Later", or is a guest awaiting manual payment) isn't
		// really "confirmed" — say so plainly instead of overstating it.
		$has_pending_charge = (float) $appt['charge'] > 0 && ! $appt['is_paid'];
		$is_registered      = (int) $appt['patient_id'] > 0;

		$subject = $has_pending_charge
			? __( 'Appointment Scheduled — Payment Pending', 'doctor-ak-portal' )
			: __( 'Appointment Confirmed', 'doctor-ak-portal' );

		$patient_intro = $has_pending_charge
			? sprintf(
				/* translators: %s: doctor's display name. */
				$is_registered
					? __( 'Your appointment with Dr. %s is scheduled, but payment is still pending. Please pay from your dashboard to confirm it.', 'doctor-ak-portal' )
					: __( 'Your appointment with Dr. %s is scheduled, but payment is still pending. Please contact us to arrange payment and confirm it.', 'doctor-ak-portal' ),
				$appt['doctor_name']
			)
			: sprintf(
				/* translators: %s: doctor's display name. */
				__( 'Your appointment with Dr. %s has been booked.', 'doctor-ak-portal' ),
				$appt['doctor_name']
			);

		if ( self::user_wants( $appt['patient_id'], 'booking' ) ) {
			$paragraphs = array( $patient_intro );

			if ( 'video' === $appt['type'] ) {
				$paragraphs[] = __( 'This is an online video consultation. We’ll email you the link to join shortly before it starts.', 'doctor-ak-portal' );
			}

			self::deliver(
				$appt['patient_email'],
				$subject,
				array(
					'preheader'   => sprintf( /* translators: 1: doctor name, 2: date and time. */ __( 'Dr. %1$s · %2$s', 'doctor-ak-portal' ), $appt['doctor_name'], self::date_label( $appt ) ),
					'eyebrow'     => $has_pending_charge ? __( 'Payment pending', 'doctor-ak-portal' ) : __( 'Appointment confirmed', 'doctor-ak-portal' ),
					'heading'     => $has_pending_charge ? __( 'Your appointment is scheduled', 'doctor-ak-portal' ) : __( 'Your appointment is confirmed', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( self::patient_first_name( $appt ) ),
					'paragraphs'  => $paragraphs,
					'details'     => self::appointment_details( $appt, 'patient' ),
					'button'      => self::patient_button( $appt, $has_pending_charge ),
					'footer_note' => self::footer_note( 'patient' ),
				)
			);
		}

		$doctor_intro = $has_pending_charge
			? sprintf(
				/* translators: %s: patient's display name. */
				__( 'You have a new appointment with %s. Payment is still pending on their end.', 'doctor-ak-portal' ),
				$appt['patient_name']
			)
			: sprintf(
				/* translators: %s: patient's display name. */
				__( 'You have a new appointment with %s.', 'doctor-ak-portal' ),
				$appt['patient_name']
			);

		if ( self::user_wants( $appt['doctor_id'], 'booking' ) ) {
			self::deliver(
				$appt['doctor_email'],
				$subject,
				array(
					'preheader'   => sprintf( /* translators: 1: patient name, 2: date and time. */ __( '%1$s · %2$s', 'doctor-ak-portal' ), $appt['patient_name'], self::date_label( $appt ) ),
					'eyebrow'     => __( 'New booking', 'doctor-ak-portal' ),
					'heading'     => __( 'New appointment booked', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( 'Dr. ' . $appt['doctor_name'] ),
					'paragraphs'  => array( $doctor_intro ),
					'details'     => self::appointment_details( $appt, 'doctor' ),
					'button'      => self::doctor_button( $appt ),
					'footer_note' => self::footer_note( 'doctor' ),
				)
			);
		}
	}

	/**
	 * Hook callback: an appointment was cancelled.
	 *
	 * @param int $appointment_id Cancelled appointment's post ID.
	 * @return void
	 */
	public function notify_cancelled( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		$subject = __( 'Appointment Cancelled', 'doctor-ak-portal' );

		if ( self::user_wants( $appt['patient_id'], 'cancelled' ) ) {
			$book_url = Page_Finder::url_for_shortcode( 'book_appointment' );

			self::deliver(
				$appt['patient_email'],
				$subject,
				array(
					'preheader'   => sprintf( /* translators: 1: doctor name, 2: date and time. */ __( 'Dr. %1$s · %2$s', 'doctor-ak-portal' ), $appt['doctor_name'], self::date_label( $appt ) ),
					'eyebrow'     => __( 'Appointment cancelled', 'doctor-ak-portal' ),
					'heading'     => __( 'Your appointment was cancelled', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( self::patient_first_name( $appt ) ),
					'paragraphs'  => array(
						sprintf(
							/* translators: %s: doctor's display name. */
							__( 'Your appointment with Dr. %s has been cancelled.', 'doctor-ak-portal' ),
							$appt['doctor_name']
						),
						$appt['is_paid'] && 'online' === $appt['payment_mode'] && '' === (string) $appt['refund_status'] && (int) $appt['patient_id'] > 0
							? __( 'As you paid online, you can request a refund from your dashboard.', 'doctor-ak-portal' )
							: '',
					),
					'details'     => self::appointment_details( $appt, 'patient' ),
					'button'      => $book_url ? array( 'label' => __( 'Book another appointment', 'doctor-ak-portal' ), 'url' => $book_url ) : array(),
					'footer_note' => self::footer_note( 'patient' ),
				)
			);
		}

		if ( self::user_wants( $appt['doctor_id'], 'cancelled' ) ) {
			self::deliver(
				$appt['doctor_email'],
				$subject,
				array(
					'preheader'   => sprintf( /* translators: 1: patient name, 2: date and time. */ __( '%1$s · %2$s', 'doctor-ak-portal' ), $appt['patient_name'], self::date_label( $appt ) ),
					'eyebrow'     => __( 'Appointment cancelled', 'doctor-ak-portal' ),
					'heading'     => __( 'Appointment cancelled', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( 'Dr. ' . $appt['doctor_name'] ),
					'paragraphs'  => array(
						sprintf(
							/* translators: %s: patient's display name. */
							__( 'Your appointment with %s has been cancelled.', 'doctor-ak-portal' ),
							$appt['patient_name']
						),
					),
					'details'     => self::appointment_details( $appt, 'doctor' ),
					'button'      => self::doctor_button( $appt ),
					'footer_note' => self::footer_note( 'doctor' ),
				)
			);
		}
	}

	/**
	 * Hook callback: an appointment was rescheduled to a new date/time.
	 *
	 * @param int $appointment_id Rescheduled appointment's post ID.
	 * @return void
	 */
	public function notify_rescheduled( $appointment_id ) {
		if ( ! self::is_enabled( 'rescheduled' ) ) {
			return;
		}

		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		$subject = __( 'Appointment Rescheduled', 'doctor-ak-portal' );

		self::deliver(
			$appt['patient_email'],
			$subject,
			array(
				'preheader'   => sprintf( /* translators: %s: new date and time. */ __( 'New time: %s', 'doctor-ak-portal' ), self::date_label( $appt ) ),
				'eyebrow'     => __( 'Appointment rescheduled', 'doctor-ak-portal' ),
				'heading'     => __( 'Your appointment has a new time', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( self::patient_first_name( $appt ) ),
				'paragraphs'  => array(
					sprintf(
						/* translators: %s: doctor's display name. */
						__( 'Your appointment with Dr. %s has been rescheduled to a new date/time — see the details below.', 'doctor-ak-portal' ),
						$appt['doctor_name']
					),
				),
				'details'     => self::appointment_details( $appt, 'patient' ),
				'button'      => self::patient_button( $appt, false ),
				'footer_note' => self::footer_note( 'patient' ),
			)
		);

		self::deliver(
			$appt['doctor_email'],
			$subject,
			array(
				'preheader'   => sprintf( /* translators: 1: patient name, 2: new date and time. */ __( '%1$s · new time %2$s', 'doctor-ak-portal' ), $appt['patient_name'], self::date_label( $appt ) ),
				'eyebrow'     => __( 'Appointment rescheduled', 'doctor-ak-portal' ),
				'heading'     => __( 'Appointment rescheduled', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( 'Dr. ' . $appt['doctor_name'] ),
				'paragraphs'  => array(
					sprintf(
						/* translators: %s: patient's display name. */
						__( 'Your appointment with %s has been rescheduled to a new date/time — see the details below.', 'doctor-ak-portal' ),
						$appt['patient_name']
					),
				),
				'details'     => self::appointment_details( $appt, 'doctor' ),
				'button'      => self::doctor_button( $appt ),
				'footer_note' => self::footer_note( 'doctor' ),
			)
		);
	}

	/**
	 * Hook callback: an appointment's online payment succeeded.
	 *
	 * @param int $appointment_id Paid appointment's post ID.
	 * @return void
	 */
	public function notify_paid( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		if ( self::user_wants( $appt['patient_id'], 'paid' ) ) {
			$this->send_invoice( $appt['patient_email'], $appt );
		}

		if ( self::user_wants( $appt['doctor_id'], 'paid' ) ) {
			self::deliver(
				$appt['doctor_email'],
				__( 'Payment Received', 'doctor-ak-portal' ),
				array(
					'preheader'   => sprintf( /* translators: %s: patient name. */ __( '%s has paid for their appointment.', 'doctor-ak-portal' ), $appt['patient_name'] ),
					'eyebrow'     => __( 'Payment received', 'doctor-ak-portal' ),
					'heading'     => __( 'Appointment paid', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( 'Dr. ' . $appt['doctor_name'] ),
					'paragraphs'  => array(
						sprintf(
							/* translators: %s: patient's display name. */
							__( '%s has paid for their appointment.', 'doctor-ak-portal' ),
							$appt['patient_name']
						),
					),
					'details'     => self::appointment_details( $appt, 'doctor' ),
					'button'      => self::doctor_button( $appt ),
					'footer_note' => self::footer_note( 'doctor' ),
				)
			);
		}
	}

	/**
	 * Hook callback: an admin/receptionist clicked "Send Reminder" on one
	 * appointment in the Appointments list (see
	 * Appointment_Handler::handle_send_reminder(), which fires
	 * 'doctor_ak_appointment_reminder_sent' after recording the in-app
	 * notification via Notification_Center::notify_reminder()) — emails
	 * just the patient, a manual one-off version of send_reminders()'s
	 * automated day-before cron email. Deliberately ignores is_enabled()'s
	 * site-wide "reminder emails" toggle (an admin explicitly asking to
	 * send this one right now should still work even if the automated
	 * cron reminder is turned off) but still honors the patient's own
	 * per-account preference.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public function notify_manual_reminder( $appointment_id ) {
		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) || empty( $appt['patient_id'] ) || ! self::user_wants( $appt['patient_id'], 'reminder' ) ) {
			return;
		}

		$has_pending_charge = (float) $appt['charge'] > 0 && ! $appt['is_paid'];

		self::deliver(
			$appt['patient_email'],
			__( 'Appointment Reminder', 'doctor-ak-portal' ),
			array(
				'preheader'   => sprintf( /* translators: 1: doctor name, 2: date and time. */ __( 'Dr. %1$s · %2$s', 'doctor-ak-portal' ), $appt['doctor_name'], self::date_label( $appt ) ),
				'eyebrow'     => __( 'Reminder', 'doctor-ak-portal' ),
				'heading'     => __( 'Your appointment reminder', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( self::patient_first_name( $appt ) ),
				'paragraphs'  => array(
					$has_pending_charge
						? sprintf(
							/* translators: 1: doctor's display name, 2: appointment date/time. */
							__( 'Reminder: your appointment with Dr. %1$s is on %2$s — payment is still pending.', 'doctor-ak-portal' ),
							$appt['doctor_name'],
							self::date_label( $appt )
						)
						: sprintf(
							/* translators: 1: doctor's display name, 2: appointment date/time. */
							__( 'Reminder: your appointment with Dr. %1$s is on %2$s.', 'doctor-ak-portal' ),
							$appt['doctor_name'],
							self::date_label( $appt )
						),
				),
				'details'     => self::appointment_details( $appt, 'patient' ),
				'button'      => self::patient_button( $appt, $has_pending_charge ),
				'footer_note' => self::footer_note( 'patient' ),
			)
		);
	}

	/**
	 * Cron callback: emails a reminder for every appointment scheduled
	 * tomorrow that hasn't already had one sent.
	 *
	 * @return void
	 */
	public function send_reminders() {
		if ( ! self::is_enabled( 'reminder' ) ) {
			return;
		}

		$tomorrow = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 day' ) );

		foreach ( Appointments::due_for_reminder( $tomorrow ) as $appointment ) {
			$appt = Appointments::notification_data( $appointment['id'] );

			if ( empty( $appt ) ) {
				continue;
			}

			$subject = __( 'Appointment Reminder', 'doctor-ak-portal' );

			self::deliver(
				$appt['patient_email'],
				$subject,
				array(
					'preheader'   => sprintf( /* translators: 1: doctor name, 2: date and time. */ __( 'Tomorrow with Dr. %1$s · %2$s', 'doctor-ak-portal' ), $appt['doctor_name'], self::date_label( $appt ) ),
					'eyebrow'     => __( 'Reminder', 'doctor-ak-portal' ),
					'heading'     => __( 'Your appointment is tomorrow', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( self::patient_first_name( $appt ) ),
					'paragraphs'  => array(
						sprintf(
							/* translators: %s: doctor's display name. */
							__( 'This is a reminder that your appointment with Dr. %s is tomorrow.', 'doctor-ak-portal' ),
							$appt['doctor_name']
						),
					),
					'details'     => self::appointment_details( $appt, 'patient' ),
					'button'      => self::patient_button( $appt, (float) $appt['charge'] > 0 && ! $appt['is_paid'] ),
					'footer_note' => self::footer_note( 'patient' ),
				)
			);

			self::deliver(
				$appt['doctor_email'],
				$subject,
				array(
					'preheader'   => sprintf( /* translators: 1: patient name, 2: date and time. */ __( 'Tomorrow with %1$s · %2$s', 'doctor-ak-portal' ), $appt['patient_name'], self::date_label( $appt ) ),
					'eyebrow'     => __( 'Reminder', 'doctor-ak-portal' ),
					'heading'     => __( 'Appointment tomorrow', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( 'Dr. ' . $appt['doctor_name'] ),
					'paragraphs'  => array(
						sprintf(
							/* translators: %s: patient's display name. */
							__( 'This is a reminder that you have an appointment with %s tomorrow.', 'doctor-ak-portal' ),
							$appt['patient_name']
						),
					),
					'details'     => self::appointment_details( $appt, 'doctor' ),
					'button'      => self::doctor_button( $appt ),
					'footer_note' => self::footer_note( 'doctor' ),
				)
			);

			Appointments::mark_reminder_sent( $appointment['id'] );
		}
	}

	/**
	 * Cron callback: emails the video-consultation join link for every paid
	 * video appointment whose join window (Appointments::due_for_video_link_email())
	 * has just opened — i.e. roughly VIDEO_JOIN_WINDOW_BEFORE_MINUTES before
	 * it starts — that hasn't already had one sent.
	 *
	 * @return void
	 */
	public function send_video_link_emails() {
		if ( ! self::is_enabled( 'video_link' ) ) {
			return;
		}

		foreach ( Appointments::due_for_video_link_email() as $appointment ) {
			$appt = Appointments::notification_data( $appointment['id'] );

			if ( empty( $appt ) ) {
				continue;
			}

			$video = Appointments::video_call_info( $appointment );

			if ( ! $video['applicable'] || '' === $video['room_url'] ) {
				continue;
			}

			$subject = __( 'Your Video Consultation Link Is Ready', 'doctor-ak-portal' );
			$button  = array( 'label' => __( 'Join video consultation', 'doctor-ak-portal' ), 'url' => $video['room_url'] );

			self::deliver(
				$appt['patient_email'],
				$subject,
				array(
					'preheader'   => sprintf( /* translators: %d: minutes. */ __( 'Starts in about %d minutes.', 'doctor-ak-portal' ), Appointments::VIDEO_JOIN_WINDOW_BEFORE_MINUTES ),
					'eyebrow'     => __( 'Video consultation', 'doctor-ak-portal' ),
					'heading'     => __( 'Your video consultation link is ready', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( self::patient_first_name( $appt ) ),
					'paragraphs'  => array(
						sprintf(
							/* translators: 1: doctor's display name, 2: minutes before the appointment. */
							__( 'Your video consultation with Dr. %1$s starts in about %2$d minutes. Use the link below to join.', 'doctor-ak-portal' ),
							$appt['doctor_name'],
							Appointments::VIDEO_JOIN_WINDOW_BEFORE_MINUTES
						),
					),
					'details'     => self::appointment_details( $appt, 'patient' ),
					'button'      => $button,
					'notes'       => array( __( 'Join from a quiet place with a stable internet connection, and allow camera and microphone access when your browser asks.', 'doctor-ak-portal' ) ),
					'footer_note' => self::footer_note( 'patient' ),
				)
			);

			self::deliver(
				$appt['doctor_email'],
				$subject,
				array(
					'preheader'   => sprintf( /* translators: 1: patient name, 2: minutes. */ __( '%1$s · starts in about %2$d minutes.', 'doctor-ak-portal' ), $appt['patient_name'], Appointments::VIDEO_JOIN_WINDOW_BEFORE_MINUTES ),
					'eyebrow'     => __( 'Video consultation', 'doctor-ak-portal' ),
					'heading'     => __( 'Your video consultation link is ready', 'doctor-ak-portal' ),
					'greeting'    => self::greeting( 'Dr. ' . $appt['doctor_name'] ),
					'paragraphs'  => array(
						sprintf(
							/* translators: 1: patient's display name, 2: minutes before the appointment. */
							__( 'Your video consultation with %1$s starts in about %2$d minutes. Use the link below to join.', 'doctor-ak-portal' ),
							$appt['patient_name'],
							Appointments::VIDEO_JOIN_WINDOW_BEFORE_MINUTES
						),
					),
					'details'     => self::appointment_details( $appt, 'doctor' ),
					'button'      => $button,
					'footer_note' => self::footer_note( 'doctor' ),
				)
			);

			Appointments::mark_video_link_sent( $appointment['id'] );
		}
	}

	/**
	 * Hook callback: a new doctor account was created and is awaiting admin
	 * approval — emails every administrator. The button only opens the
	 * Doctor Requests page (sign-in and admin checks apply as usual);
	 * approving or rejecting still happens there.
	 *
	 * @param int $doctor_id New doctor's user ID.
	 * @return void
	 */
	public function notify_doctor_registered( $doctor_id ) {
		if ( ! self::is_enabled( 'doctor_registration' ) ) {
			return;
		}

		$doctor = get_userdata( $doctor_id );

		if ( ! $doctor ) {
			return;
		}

		$name        = self::user_display_name( $doctor_id );
		$review_url  = self::admin_url_for( 'doctor-requests' );
		$review_url  = $review_url ? $review_url . '#dak-doctor-request-' . (int) $doctor_id : '';
		$specialties = array();
		$all         = Specializations::get_all();

		foreach ( (array) get_user_meta( $doctor_id, 'doctor_ak_specializations', true ) as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$specialties[] = $all[ $slug ];
			}
		}

		$years    = (string) get_user_meta( $doctor_id, 'doctor_ak_years_experience', true );
		$country  = (string) get_user_meta( $doctor_id, 'doctor_ak_country', true );
		$city     = (string) get_user_meta( $doctor_id, 'doctor_ak_city', true );
		$area     = (string) get_user_meta( $doctor_id, 'doctor_ak_area', true );
		$location = array_filter(
			array(
				'' !== $area ? Locations::area_label( $country, $city, $area ) : '',
				'' !== $city ? Locations::city_label( $country, $city ) : '',
				'' !== $country ? Locations::country_label( $country ) : '',
			)
		);

		$registered = strtotime( $doctor->user_registered . ' UTC' );

		foreach ( self::admin_emails() as $admin_email ) {
			self::deliver(
				$admin_email,
				__( 'New Doctor Registration', 'doctor-ak-portal' ),
				array(
					'preheader'   => sprintf( /* translators: %s: doctor name. */ __( 'Dr. %s is awaiting your review.', 'doctor-ak-portal' ), $name ),
					'eyebrow'     => __( 'Doctor requests', 'doctor-ak-portal' ),
					'heading'     => __( 'New doctor registration', 'doctor-ak-portal' ),
					'paragraphs'  => array( __( 'A doctor has registered and is awaiting your review.', 'doctor-ak-portal' ) ),
					'details'     => array(
						array( __( 'Name', 'doctor-ak-portal' ), sprintf( 'Dr. %s', $name ) ),
						array( __( 'Email', 'doctor-ak-portal' ), $doctor->user_email ),
						array( __( 'Phone', 'doctor-ak-portal' ), (string) get_user_meta( $doctor_id, 'doctor_ak_phone_number', true ) ),
						array( __( 'Specialities', 'doctor-ak-portal' ), implode( ', ', $specialties ) ),
						array( __( 'Qualification', 'doctor-ak-portal' ), (string) get_user_meta( $doctor_id, 'doctor_ak_qualification', true ) ),
						array(
							__( 'Experience', 'doctor-ak-portal' ),
							'' !== $years
								/* translators: %s: number of years. */
								? sprintf( _n( '%s year', '%s years', (int) $years, 'doctor-ak-portal' ), $years )
								: '',
						),
						array( __( 'Location', 'doctor-ak-portal' ), implode( ', ', array_filter( $location ) ) ),
						array( __( 'Registered', 'doctor-ak-portal' ), false !== $registered ? wp_date( 'j F Y, h:i A T', $registered ) : '' ),
					),
					'button'      => $review_url ? array( 'label' => __( 'Review doctor request', 'doctor-ak-portal' ), 'url' => $review_url ) : array(),
					'notes'       => array( __( 'You’ll be asked to sign in. Opening this link doesn’t approve or reject the request — you decide on the Doctor Requests page.', 'doctor-ak-portal' ) ),
					'footer_note' => self::footer_note( 'admin' ),
				)
			);
		}
	}

	/**
	 * Hook callback: a doctor just self-registered and is awaiting admin
	 * approval — confirms receipt directly to the doctor (separate from
	 * notify_doctor_registered() above, which alerts admins instead; this
	 * one is a transactional confirmation to the registrant themselves, so
	 * it isn't gated by the admin's "New Doctor Registration" notification
	 * preference).
	 *
	 * @param int $doctor_id Newly registered doctor's user ID.
	 * @return void
	 */
	public function notify_doctor_registration_received( $doctor_id ) {
		$doctor = get_userdata( $doctor_id );

		if ( ! $doctor ) {
			return;
		}

		self::deliver(
			$doctor->user_email,
			__( 'Your Account Is Pending Approval', 'doctor-ak-portal' ),
			array(
				'preheader'   => __( 'We’ve received your registration and will email you once it’s approved.', 'doctor-ak-portal' ),
				'eyebrow'     => __( 'Registration received', 'doctor-ak-portal' ),
				'heading'     => __( 'Thanks for registering', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( 'Dr. ' . self::user_display_name( $doctor_id ) ),
				'paragraphs'  => array(
					__( 'Your account has been created and is now awaiting admin approval.', 'doctor-ak-portal' ),
					__( 'We will email you as soon as it has been approved. You don’t need to do anything else for now.', 'doctor-ak-portal' ),
				),
				'footer_note' => __( 'You’re receiving this because you registered as a doctor.', 'doctor-ak-portal' ),
			)
		);
	}

	/**
	 * Hook callback: a pending doctor account was approved by an admin —
	 * emails the doctor.
	 *
	 * @param int $doctor_id Approved doctor's user ID.
	 * @return void
	 */
	public function notify_doctor_approved( $doctor_id ) {
		if ( ! self::is_enabled( 'doctor_registration' ) ) {
			return;
		}

		$doctor = get_userdata( $doctor_id );

		if ( ! $doctor ) {
			return;
		}

		$login_url = Page_Finder::url_for_shortcode( 'doctor_login' );

		self::deliver(
			$doctor->user_email,
			__( 'Your Account Has Been Approved', 'doctor-ak-portal' ),
			array(
				'preheader'   => __( 'You can now sign in and set up your clinics and appointments.', 'doctor-ak-portal' ),
				'eyebrow'     => __( 'Account approved', 'doctor-ak-portal' ),
				'heading'     => __( 'Your account is approved', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( 'Dr. ' . self::user_display_name( $doctor_id ) ),
				'paragraphs'  => array( __( 'Your account has been approved. You can now log in and start managing your clinic and appointments.', 'doctor-ak-portal' ) ),
				'button'      => $login_url ? array( 'label' => __( 'Log in to your dashboard', 'doctor-ak-portal' ), 'url' => $login_url ) : array(),
				'footer_note' => self::footer_note( 'doctor' ),
			)
		);
	}

	/**
	 * Hook callback: a new patient account was created — whether by
	 * self-registration, an admin (Users → Patients → Add), or a doctor
	 * adding a walk-in patient from their own dashboard. Doesn't handle
	 * password setup itself (wp_new_user_notification(), called by each of
	 * those three creation points, already emails a secure link for that) —
	 * this is just a friendly welcome with a direct link to log in once
	 * they've set one.
	 *
	 * @param int $patient_id New patient's user ID.
	 * @return void
	 */
	public function notify_patient_added( $patient_id ) {
		if ( ! self::is_enabled( 'patient_welcome' ) ) {
			return;
		}

		$patient = get_userdata( $patient_id );

		if ( ! $patient ) {
			return;
		}

		$login_url = Page_Finder::url_for_shortcode( 'doctor_login' );

		self::deliver(
			$patient->user_email,
			__( 'Welcome', 'doctor-ak-portal' ),
			array(
				'preheader'   => __( 'Your account is ready — book and manage your appointments online.', 'doctor-ak-portal' ),
				'eyebrow'     => __( 'Welcome', 'doctor-ak-portal' ),
				'heading'     => __( 'Your account has been created', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( $patient->first_name ),
				'paragraphs'  => array( __( "Your account has been created. Once you've set your password, you can log in to book and manage your appointments.", 'doctor-ak-portal' ) ),
				'button'      => $login_url ? array( 'label' => __( 'Log in to your dashboard', 'doctor-ak-portal' ), 'url' => $login_url ) : array(),
				'notes'       => array( __( 'Look out for a separate email with the link to set your password.', 'doctor-ak-portal' ) ),
				'footer_note' => self::footer_note( 'patient' ),
			)
		);
	}

	/**
	 * Hook callback: a patient requested a refund on a cancelled, paid
	 * appointment — emails every administrator to review and process it from
	 * the admin Appointments section.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public function notify_refund_requested( $appointment_id ) {
		if ( ! self::is_enabled( 'refund' ) ) {
			return;
		}

		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		$url = self::admin_url_for( 'appointments' );

		foreach ( self::admin_emails() as $admin_email ) {
			self::deliver(
				$admin_email,
				__( 'Refund Requested', 'doctor-ak-portal' ),
				array(
					'preheader'   => sprintf( /* translators: 1: patient name, 2: amount. */ __( '%1$s requested a refund of %2$s.', 'doctor-ak-portal' ), $appt['patient_name'], self::money( $appt['refund_amount'] ) ),
					'eyebrow'     => __( 'Refunds', 'doctor-ak-portal' ),
					'heading'     => __( 'Refund requested', 'doctor-ak-portal' ),
					'paragraphs'  => array(
						sprintf(
							/* translators: 1: patient's display name, 2: doctor's display name. */
							__( '%1$s requested a refund for their appointment with Dr. %2$s. Review and process it from the admin dashboard’s Appointments section.', 'doctor-ak-portal' ),
							$appt['patient_name'],
							$appt['doctor_name']
						),
					),
					'details'     => array(
						array( __( 'Reference', 'doctor-ak-portal' ), self::reference( $appt['id'] ) ),
						array( __( 'Patient', 'doctor-ak-portal' ), $appt['patient_name'] ),
						array( __( 'Doctor', 'doctor-ak-portal' ), sprintf( 'Dr. %s', $appt['doctor_name'] ) ),
						array( __( 'Amount', 'doctor-ak-portal' ), self::money( $appt['refund_amount'] ) ),
						array( __( 'Reason', 'doctor-ak-portal' ), (string) $appt['refund_reason'] ),
					),
					'button'      => $url ? array( 'label' => __( 'Review refund request', 'doctor-ak-portal' ), 'url' => $url . '#dak-appointment-' . (int) $appt['id'] ) : array(),
					'footer_note' => self::footer_note( 'admin' ),
				)
			);
		}
	}

	/**
	 * Hook callback: an admin successfully processed a refund through
	 * Swich — emails the patient.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public function notify_refund_processed( $appointment_id ) {
		if ( ! self::is_enabled( 'refund' ) ) {
			return;
		}

		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) || '' === $appt['patient_email'] ) {
			return;
		}

		self::deliver(
			$appt['patient_email'],
			__( 'Refund Processed', 'doctor-ak-portal' ),
			array(
				'preheader'   => sprintf( /* translators: %s: amount. */ __( 'We’ve refunded %s.', 'doctor-ak-portal' ), self::money( $appt['refund_amount'] ) ),
				'eyebrow'     => __( 'Refund processed', 'doctor-ak-portal' ),
				'heading'     => __( 'Your refund has been processed', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( self::patient_first_name( $appt ) ),
				'paragraphs'  => array(
					sprintf(
						/* translators: 1: amount, 2: doctor's display name. */
						__( 'We’ve processed a refund of %1$s for your appointment with Dr. %2$s. It may take a few business days to appear in your account.', 'doctor-ak-portal' ),
						self::money( $appt['refund_amount'] ),
						$appt['doctor_name']
					),
				),
				'details'     => array(
					array( __( 'Reference', 'doctor-ak-portal' ), self::reference( $appt['id'] ) ),
					array( __( 'Doctor', 'doctor-ak-portal' ), sprintf( 'Dr. %s', $appt['doctor_name'] ) ),
					array( __( 'Appointment', 'doctor-ak-portal' ), self::date_label( $appt ) ),
					array( __( 'Amount refunded', 'doctor-ak-portal' ), self::money( $appt['refund_amount'] ), true ),
				),
				'footer_note' => self::footer_note( 'patient' ),
			)
		);
	}

	/**
	 * Sends the patient's "How was your appointment?" email — called by
	 * Review_Requests::maybe_send() once the appointment is eligible. Each
	 * star links to the review form on the doctor's profile with that
	 * rating preselected; every rating leads to the same form.
	 *
	 * @param array  $appt  Row from Appointments::notification_data().
	 * @param string $token Raw review token for the links.
	 * @return bool Whether the email was handed to the mailer.
	 */
	public static function send_review_request( array $appt, $token ) {
		if ( empty( $appt ) || '' === (string) $token ) {
			return false;
		}

		$write_url = Review_Requests::review_url( $appt['doctor_id'], $token );

		if ( '' === $write_url ) {
			return false;
		}

		$font      = Email_Template::FONT_STACK;
		$cells     = '';
		$fallbacks = array();
		$text      = array( __( 'Choose a rating to continue to your review:', 'doctor-ak-portal' ) );

		for ( $rating = 1; $rating <= 5; $rating++ ) {
			$url = Review_Requests::review_url( $appt['doctor_id'], $token, $rating );
			/* translators: %d: number of stars. */
			$label = sprintf( _n( '%d star', '%d stars', $rating, 'doctor-ak-portal' ), $rating );
			/* translators: %d: number of stars. */
			$title = sprintf( _n( 'Rate %d star out of 5', 'Rate %d stars out of 5', $rating, 'doctor-ak-portal' ), $rating );

			$cells .= '<td align="center" style="padding:0 4px;">'
				. '<a href="' . esc_url( $url ) . '" title="' . esc_attr( $title ) . '" aria-label="' . esc_attr( $title ) . '" target="_blank" class="dak-panel" style="display:block;width:52px;padding:8px 0 6px;border:1px solid ' . Email_Template::COLOR_BORDER . ';border-radius:8px;background-color:#ffffff;text-decoration:none;text-align:center;">'
				. '<span style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:30px;line-height:32px;color:#c8940e;">&#9733;</span>'
				. '<span class="dak-muted" style="display:block;font-family:' . $font . ';font-size:13px;line-height:18px;font-weight:600;color:' . Email_Template::COLOR_MUTED . ';">' . esc_html( (string) $rating ) . '</span>'
				. '</a>'
				. '</td>';

			$fallbacks[] = array( $label, $url );
			$text[]      = $label . ': ' . $url;
		}

		$stars_html = '<p class="dak-text" style="margin:8px 0 12px;font-family:' . $font . ';font-size:16px;line-height:24px;font-weight:600;color:' . Email_Template::COLOR_TEXT . ';">' . esc_html__( 'Choose a rating to continue to your review.', 'doctor-ak-portal' ) . '</p>'
			. '<table role="presentation" class="dak-stars" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px -4px;"><tr>' . $cells . '</tr></table>'
			. '<p class="dak-muted" style="margin:0 0 24px;font-family:' . $font . ';font-size:13px;line-height:20px;color:' . Email_Template::COLOR_MUTED . ';">' . esc_html__( '1 = poor, 5 = excellent', 'doctor-ak-portal' ) . '</p>';

		$settings_url = self::patient_dashboard_url( 'settings' );
		$first_name   = self::patient_first_name( $appt );

		return Email_Template::send(
			$appt['patient_email'],
			__( 'How was your appointment?', 'doctor-ak-portal' ),
			array(
				'preheader'   => sprintf( /* translators: %s: doctor name. */ __( 'Share your feedback about your appointment with Dr. %s.', 'doctor-ak-portal' ), $appt['doctor_name'] ),
				'heading'     => __( 'How was your experience?', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( $first_name ),
				'paragraphs'  => array(
					sprintf(
						/* translators: %s: doctor's display name. */
						__( 'Thank you for attending your appointment with Dr. %s. We’d appreciate your feedback about your experience.', 'doctor-ak-portal' ),
						$appt['doctor_name']
					),
				),
				'details'     => array(
					array( __( 'Doctor', 'doctor-ak-portal' ), sprintf( 'Dr. %s', $appt['doctor_name'] ) ),
					array( __( 'Date', 'doctor-ak-portal' ), self::date_label( $appt, false ) ),
					array( __( 'Visit', 'doctor-ak-portal' ), self::visit_label( $appt, false ) ),
				),
				'body_html'   => $stars_html,
				'body_text'   => implode( "\n", $text ),
				'button'      => array( 'label' => __( 'Write a review', 'doctor-ak-portal' ), 'url' => $write_url ),
				'links'       => $fallbacks,
				'notes'       => array_filter( array(
					__( 'Your rating is selected on the next page. You can change it before submitting.', 'doctor-ak-portal' ),
					sprintf(
						/* translators: 1: number of days, 2: doctor name. */
						__( 'This link is personal to you and works for %1$d days. Reviews appear on Dr. %2$s’s profile with your first name only.', 'doctor-ak-portal' ),
						Review_Requests::TOKEN_TTL_DAYS,
						$appt['doctor_name']
					),
					$settings_url
						? array(
							'text'       => __( 'Prefer not to get review requests?', 'doctor-ak-portal' ),
							'link_label' => __( 'Turn them off in your settings', 'doctor-ak-portal' ),
							'url'        => $settings_url,
						)
						: '',
				) ),
				'footer_note' => sprintf(
					/* translators: %s: doctor name. */
					__( 'You’re receiving this one-time email because you had a completed appointment with Dr. %s.', 'doctor-ak-portal' ),
					$appt['doctor_name']
				),
			)
		);
	}

	/**
	 * Every administrator's email address.
	 *
	 * @return string[]
	 */
	private static function admin_emails() {
		return array_map(
			function ( $admin ) {
				return $admin->user_email;
			},
			get_users( array( 'role' => 'administrator' ) )
		);
	}

	/**
	 * Sends one email in the shared design, with the usual "[Clinic Name] "
	 * subject prefix. Invalid/empty addresses (e.g. a deleted patient
	 * account with no guest email) are skipped silently.
	 *
	 * @param string   $to             Recipient email.
	 * @param string   $subject_suffix Appended after "[Clinic Name] " in the subject.
	 * @param array    $email          Email description, see Email_Template.
	 * @param string[] $attachments    File paths to attach.
	 * @return bool
	 */
	private static function deliver( $to, $subject_suffix, array $email, array $attachments = array() ) {
		/* translators: 1: clinic name, 2: email subject. */
		$subject = sprintf( __( '[%1$s] %2$s', 'doctor-ak-portal' ), self::clinic_name(), $subject_suffix );

		$email['notes'] = array_values( array_filter( isset( $email['notes'] ) ? (array) $email['notes'] : array() ) );

		return Email_Template::send( $to, $subject, $email, $attachments );
	}

	/**
	 * Sends the patient's payment invoice, with the PDF invoice attached.
	 * Fired once, right after mark_paid(), see notify_paid() above.
	 *
	 * @param string $to          Patient's email.
	 * @param array  $appointment Row from Appointments::notification_data().
	 * @return void
	 */
	private function send_invoice( $to, array $appointment ) {
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$invoice_number   = self::invoice_number( $appointment['id'] );
		$service_label    = '' !== $appointment['service_name'] ? $appointment['service_name'] : $appointment['type_label'];
		$charge           = (float) $appointment['charge'];
		$base_charge      = (float) $appointment['base_charge'] + (float) $appointment['surcharge'];
		$discount_percent = (int) $appointment['discount_percent'];
		$has_discount     = $discount_percent > 0 && $base_charge > $charge;

		$details = array(
			array( __( 'Invoice', 'doctor-ak-portal' ), $invoice_number ),
			array( __( 'Payment date', 'doctor-ak-portal' ), date_i18n( get_option( 'date_format' ) ) ),
			array( __( 'Billed to', 'doctor-ak-portal' ), $appointment['patient_name'] ),
			array( __( 'Service', 'doctor-ak-portal' ), $service_label ),
			array( __( 'Doctor', 'doctor-ak-portal' ), sprintf( 'Dr. %s', $appointment['doctor_name'] ) ),
			array( __( 'Appointment', 'doctor-ak-portal' ), self::date_label( $appointment ) ),
		);

		if ( $has_discount ) {
			$details[] = array( __( 'Price', 'doctor-ak-portal' ), self::money( $base_charge ) );
			/* translators: 1: discount percent, 2: amount saved. */
			$details[] = array( __( 'Discount', 'doctor-ak-portal' ), sprintf( __( '%1$d%% off (you saved %2$s)', 'doctor-ak-portal' ), $discount_percent, self::money( $base_charge - $charge ) ) );
		}

		$details[] = array( __( 'Total paid', 'doctor-ak-portal' ), self::money( $charge ), true );

		$pdf_path = self::write_invoice_pdf_tempfile( $appointment, $invoice_number );

		/* translators: 1: clinic name, 2: invoice number. */
		$subject = sprintf( __( '[%1$s] Payment Invoice #%2$s', 'doctor-ak-portal' ), self::clinic_name(), $invoice_number );

		Email_Template::send(
			$to,
			$subject,
			array(
				'preheader'   => sprintf( /* translators: 1: amount, 2: invoice number. */ __( 'We’ve received %1$s · Invoice %2$s', 'doctor-ak-portal' ), self::money( $charge ), $invoice_number ),
				'eyebrow'     => __( 'Payment received', 'doctor-ak-portal' ),
				'heading'     => __( 'Thank you — your payment is confirmed', 'doctor-ak-portal' ),
				'greeting'    => self::greeting( self::patient_first_name( $appointment ) ),
				'paragraphs'  => array(
					sprintf(
						/* translators: %s: doctor name. */
						__( 'We’ve received your payment for your appointment with Dr. %s.', 'doctor-ak-portal' ),
						$appointment['doctor_name']
					),
					$pdf_path ? __( 'Your invoice is attached to this email as a PDF.', 'doctor-ak-portal' ) : '',
				),
				'details'     => $details,
				'details_title' => __( 'Invoice', 'doctor-ak-portal' ),
				'button'      => self::patient_button( $appointment, false ),
				'footer_note' => self::footer_note( 'patient' ),
			),
			$pdf_path ? array( $pdf_path ) : array()
		);

		if ( $pdf_path ) {
			wp_delete_file( $pdf_path );
		}
	}

	/**
	 * Renders Invoice_Pdf::build() to a temp file for wp_mail()'s
	 * attachments param, which takes file paths, not raw bytes. The caller
	 * deletes the file right after wp_mail() sends (synchronous, so it's
	 * no longer needed by then).
	 *
	 * @param array  $appointment    Row from Appointments::notification_data().
	 * @param string $invoice_number e.g. 'INV-00123', used for the file name.
	 * @return string|false Temp file path, or false if it couldn't be written.
	 */
	private static function write_invoice_pdf_tempfile( array $appointment, $invoice_number ) {
		$pdf_bytes = Invoice_Pdf::build( $appointment );

		// Named directly (not via wp_tempnam()'s randomized name) so the
		// attachment the patient receives is cleanly "INV-00123.pdf" rather
		// than a scrambled temp filename — safe since the invoice number is
		// already unique per appointment.
		$tmp_path = trailingslashit( get_temp_dir() ) . sanitize_file_name( $invoice_number ) . '.pdf';

		$written = file_put_contents( $tmp_path, $pdf_bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing our own generated bytes to a WP temp path, not user-supplied data.

		return false !== $written ? $tmp_path : false;
	}

	/**
	 * A stable, human-friendly invoice number derived from the appointment's
	 * post ID — no separate invoice-numbering sequence exists (or needs to),
	 * since every paid appointment produces exactly one invoice.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return string
	 */
	private static function invoice_number( $appointment_id ) {
		return sprintf( 'INV-%05d', (int) $appointment_id );
	}

	/**
	 * The clinic's configured name (Settings → Footer Settings), falling
	 * back to 'Main Clinic' if unset — used as every notification email's
	 * From name and subject-line prefix instead of the WordPress site title.
	 *
	 * @return string
	 */
	private static function clinic_name() {
		return get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' );
	}

	/**
	 * The appointment's reference, as shown across the dashboards and PDFs.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return string
	 */
	public static function reference( $appointment_id ) {
		return sprintf( 'APT-%04d', (int) $appointment_id );
	}

	/**
	 * "Monday, 13 October 2026, 03:30 PM PKT" — the appointment's local
	 * date and time with the site's time zone spelled out.
	 *
	 * @param array $appt      Row from Appointments::notification_data().
	 * @param bool  $with_time Include the time and zone.
	 * @return string
	 */
	public static function date_label( array $appt, $with_time = true ) {
		$timezone = wp_timezone();
		$moment   = date_create( $appt['date'] . ' ' . $appt['time'], $timezone );

		if ( ! $moment ) {
			return trim( $appt['date'] . ' ' . $appt['time'] );
		}

		$date = wp_date( 'l, j F Y', $moment->getTimestamp(), $timezone );

		if ( ! $with_time ) {
			return $date;
		}

		$zone = $moment->format( 'T' );

		// Zones without an abbreviation come back as "+05".
		if ( preg_match( '/^[+-]/', $zone ) ) {
			$zone = 'UTC' . $zone;
		}

		return $date . ', ' . wp_date( 'h:i A', $moment->getTimestamp(), $timezone ) . ' ' . $zone;
	}

	/**
	 * Where the visit happens: the clinic (name and address) or the
	 * consultation type.
	 *
	 * @param array $appt         Row from Appointments::notification_data().
	 * @param bool  $with_address Include the clinic's address on a second line.
	 * @return string
	 */
	public static function visit_label( array $appt, $with_address = true ) {
		if ( 'video' === $appt['type'] ) {
			return __( 'Online video consultation', 'doctor-ak-portal' );
		}

		if ( '' !== (string) $appt['clinic_name'] ) {
			return $with_address && '' !== (string) $appt['clinic_address']
				? $appt['clinic_name'] . "\n" . $appt['clinic_address']
				: $appt['clinic_name'];
		}

		return $appt['type_label'];
	}

	/**
	 * The appointment's payment state in words.
	 *
	 * @param array $appt Row from Appointments::notification_data().
	 * @return string
	 */
	private static function payment_label( array $appt ) {
		$charge = (float) $appt['charge'];

		if ( 'processed' === $appt['refund_status'] ) {
			return __( 'Refunded', 'doctor-ak-portal' );
		}

		if ( $charge <= 0 ) {
			return __( 'No charge', 'doctor-ak-portal' );
		}

		return $appt['is_paid']
			/* translators: %s: amount. */
			? sprintf( __( 'Paid · %s', 'doctor-ak-portal' ), self::money( $charge ) )
			/* translators: %s: amount. */
			: sprintf( __( 'Payment pending · %s', 'doctor-ak-portal' ), self::money( $charge ) );
	}

	/**
	 * The compact, labelled appointment summary — tailored to who reads it
	 * (a patient sees the doctor; a doctor sees the patient). No notes or
	 * clinical details.
	 *
	 * @param array  $appt     Row from Appointments::notification_data().
	 * @param string $audience 'patient', 'doctor' or 'admin'.
	 * @return array
	 */
	private static function appointment_details( array $appt, $audience ) {
		$rows = array(
			array( __( 'Reference', 'doctor-ak-portal' ), self::reference( $appt['id'] ) ),
		);

		if ( 'doctor' !== $audience ) {
			$rows[] = array( __( 'Doctor', 'doctor-ak-portal' ), sprintf( 'Dr. %s', $appt['doctor_name'] ) );
		}

		if ( 'patient' !== $audience ) {
			$rows[] = array( __( 'Patient', 'doctor-ak-portal' ), $appt['patient_name'] );
		}

		$rows[] = array( __( 'Service', 'doctor-ak-portal' ), (string) $appt['service_name'] );
		$rows[] = array( 'video' === $appt['type'] ? __( 'Consultation', 'doctor-ak-portal' ) : __( 'Clinic', 'doctor-ak-portal' ), self::visit_label( $appt ) );
		$rows[] = array( __( 'Date & time', 'doctor-ak-portal' ), self::date_label( $appt ) );
		$rows[] = array( __( 'Payment', 'doctor-ak-portal' ), self::payment_label( $appt ) );

		return $rows;
	}

	/**
	 * The patient's primary action: pay (when a charge is pending) or view
	 * the appointment — for registered patients only (a guest has no
	 * dashboard to go to).
	 *
	 * @param array $appt        Row from Appointments::notification_data().
	 * @param bool  $pay_pending Whether payment is still due.
	 * @return array
	 */
	private static function patient_button( array $appt, $pay_pending ) {
		if ( (int) $appt['patient_id'] < 1 ) {
			return array();
		}

		$url = self::patient_dashboard_url( $pay_pending ? 'payments' : 'appointments' );

		if ( '' === $url ) {
			return array();
		}

		return $pay_pending
			? array( 'label' => __( 'Pay now', 'doctor-ak-portal' ), 'url' => $url )
			: array( 'label' => __( 'View appointment', 'doctor-ak-portal' ), 'url' => $url . '#dak-appointment-' . (int) $appt['id'] );
	}

	/**
	 * The doctor's primary action: the appointment in their dashboard.
	 *
	 * @param array $appt Row from Appointments::notification_data().
	 * @return array
	 */
	private static function doctor_button( array $appt ) {
		$dashboard = Page_Finder::url_for_shortcode( 'doctor_dashboard' );

		if ( '' === $dashboard ) {
			return array();
		}

		return array(
			'label' => __( 'View in your dashboard', 'doctor-ak-portal' ),
			'url'   => add_query_arg( 'tab', 'appointments', $dashboard ) . '#dak-appointment-' . (int) $appt['id'],
		);
	}

	/**
	 * A patient-dashboard tab URL, or ''.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	private static function patient_dashboard_url( $tab ) {
		$dashboard = Page_Finder::url_for_shortcode( 'patient_dashboard' );

		return '' !== $dashboard ? add_query_arg( 'tab', $tab, $dashboard ) : '';
	}

	/**
	 * An admin-dashboard section URL, or ''.
	 *
	 * @param string $section Section key.
	 * @return string
	 */
	private static function admin_url_for( $section ) {
		$dashboard = Page_Finder::url_for_shortcode( 'admin_dashboard' );

		return '' !== $dashboard ? add_query_arg( 'section', $section, $dashboard ) : '';
	}

	/**
	 * The patient's first name — account first name, else the first word
	 * of the guest name — or '' (the greeting then falls back to "Hello,").
	 *
	 * @param array $appt Row from Appointments::notification_data().
	 * @return string
	 */
	private static function patient_first_name( array $appt ) {
		if ( (int) $appt['patient_id'] > 0 ) {
			$user = get_userdata( (int) $appt['patient_id'] );

			if ( $user && '' !== trim( (string) $user->first_name ) ) {
				return trim( (string) $user->first_name );
			}
		}

		$name  = trim( '' !== (string) $appt['guest_name'] ? $appt['guest_name'] : (string) $appt['patient_name'] );
		$parts = preg_split( '/\s+/u', $name );

		return $parts && '' !== $parts[0] ? $parts[0] : '';
	}

	/**
	 * "Hi Ayesha," / "Hello,".
	 *
	 * @param string $name Name to greet, or ''.
	 * @return string
	 */
	private static function greeting( $name ) {
		$name = trim( (string) $name );

		/* translators: %s: person's name. */
		return '' !== $name && 'Dr.' !== $name ? sprintf( __( 'Hi %s,', 'doctor-ak-portal' ), $name ) : __( 'Hello,', 'doctor-ak-portal' );
	}

	/**
	 * A user's "First Last", falling back to the display name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function user_display_name( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return '';
		}

		$name = trim( $user->first_name . ' ' . $user->last_name );

		return '' !== $name ? $name : $user->display_name;
	}

	/**
	 * "PKR 3,500".
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function money( $amount ) {
		return 'PKR ' . number_format_i18n( (float) $amount, 0 );
	}

	/**
	 * Why the recipient received the email.
	 *
	 * @param string $audience 'patient', 'doctor' or 'admin'.
	 * @return string
	 */
	private static function footer_note( $audience ) {
		switch ( $audience ) {
			case 'doctor':
				return __( 'You’re receiving this because you’re a doctor on our platform. You can choose which emails you get in your dashboard’s Settings.', 'doctor-ak-portal' );
			case 'admin':
				return __( 'Sent to the site’s administrators.', 'doctor-ak-portal' );
			default:
				return __( 'You’re receiving this because you have an appointment booked through our website.', 'doctor-ak-portal' );
		}
	}
}
