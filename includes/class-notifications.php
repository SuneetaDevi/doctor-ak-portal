<?php
/**
 * Email notifications to patients and doctors for appointment events.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

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
 */
class Notifications {

	const OPTION_NOTIFY_BOOKING             = 'doctor_ak_notify_booking';
	const OPTION_NOTIFY_CANCELLED           = 'doctor_ak_notify_cancelled';
	const OPTION_NOTIFY_PAID                = 'doctor_ak_notify_paid';
	const OPTION_NOTIFY_REMINDER            = 'doctor_ak_notify_reminder';
	const OPTION_NOTIFY_VIDEO_LINK          = 'doctor_ak_notify_video_link';
	const OPTION_NOTIFY_DOCTOR_REGISTRATION = 'doctor_ak_notify_doctor_registration';
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
		);

		if ( ! isset( $option_map[ $type ] ) ) {
			return false;
		}

		return '1' === get_option( $option_map[ $type ], '1' );
	}

	/**
	 * AJAX/hook callback: a new appointment was created.
	 *
	 * @param int   $appointment_id New appointment's post ID.
	 * @param array $data           Raw data passed to Appointments::create() (unused, kept for hook signature).
	 * @return void
	 */
	public function notify_created( $appointment_id, $data ) {
		if ( ! self::is_enabled( 'booking' ) ) {
			return;
		}

		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		// A booking with a charge that hasn't been paid yet (the patient
		// chose "Pay Later", or is a guest awaiting manual payment) isn't
		// really "confirmed" — say so plainly instead of overstating it.
		$has_pending_charge = (float) $appt['charge'] > 0 && ! $appt['is_paid'];

		$subject = $has_pending_charge
			? __( 'Appointment Scheduled — Payment Pending', 'doctor-ak-portal' )
			: __( 'Appointment Confirmed', 'doctor-ak-portal' );

		$patient_intro = $has_pending_charge
			? sprintf(
				/* translators: %s: doctor's display name. */
				(int) $appt['patient_id'] > 0
					? __( 'Your appointment with Dr. %s is scheduled, but payment is still pending. Please pay from your dashboard to confirm it.', 'doctor-ak-portal' )
					: __( 'Your appointment with Dr. %s is scheduled, but payment is still pending. Please contact us to arrange payment and confirm it.', 'doctor-ak-portal' ),
				$appt['doctor_name']
			)
			: sprintf(
				/* translators: %s: doctor's display name. */
				__( 'Your appointment with Dr. %s has been booked.', 'doctor-ak-portal' ),
				$appt['doctor_name']
			);

		$this->send(
			$appt['patient_email'],
			$subject,
			$has_pending_charge ? __( 'Your appointment is scheduled — payment pending', 'doctor-ak-portal' ) : __( 'Your appointment is confirmed', 'doctor-ak-portal' ),
			$patient_intro,
			$appt
		);

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

		$this->send(
			$appt['doctor_email'],
			$subject,
			__( 'New appointment booked', 'doctor-ak-portal' ),
			$doctor_intro,
			$appt
		);
	}

	/**
	 * Hook callback: an appointment was cancelled.
	 *
	 * @param int $appointment_id Cancelled appointment's post ID.
	 * @return void
	 */
	public function notify_cancelled( $appointment_id ) {
		if ( ! self::is_enabled( 'cancelled' ) ) {
			return;
		}

		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		$subject = __( 'Appointment Cancelled', 'doctor-ak-portal' );

		$this->send(
			$appt['patient_email'],
			$subject,
			__( 'Your appointment was cancelled', 'doctor-ak-portal' ),
			sprintf(
				/* translators: %s: doctor's display name. */
				__( 'Your appointment with Dr. %s has been cancelled.', 'doctor-ak-portal' ),
				$appt['doctor_name']
			),
			$appt
		);

		$this->send(
			$appt['doctor_email'],
			$subject,
			__( 'Appointment cancelled', 'doctor-ak-portal' ),
			sprintf(
				/* translators: %s: patient's display name. */
				__( 'Your appointment with %s has been cancelled.', 'doctor-ak-portal' ),
				$appt['patient_name']
			),
			$appt
		);
	}

	/**
	 * Hook callback: an appointment's online payment succeeded.
	 *
	 * @param int $appointment_id Paid appointment's post ID.
	 * @return void
	 */
	public function notify_paid( $appointment_id ) {
		if ( ! self::is_enabled( 'paid' ) ) {
			return;
		}

		$appt = Appointments::notification_data( $appointment_id );

		if ( empty( $appt ) ) {
			return;
		}

		$subject = __( 'Payment Received', 'doctor-ak-portal' );

		$this->send(
			$appt['patient_email'],
			$subject,
			__( 'Payment received — you\'re all set', 'doctor-ak-portal' ),
			sprintf(
				/* translators: %s: doctor's display name. */
				__( 'We\'ve received your payment for your appointment with Dr. %s.', 'doctor-ak-portal' ),
				$appt['doctor_name']
			),
			$appt
		);

		$this->send(
			$appt['doctor_email'],
			$subject,
			__( 'Appointment paid', 'doctor-ak-portal' ),
			sprintf(
				/* translators: %s: patient's display name. */
				__( '%s has paid for their appointment.', 'doctor-ak-portal' ),
				$appt['patient_name']
			),
			$appt
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

			$this->send(
				$appt['patient_email'],
				$subject,
				__( 'Your appointment is tomorrow', 'doctor-ak-portal' ),
				sprintf(
					/* translators: %s: doctor's display name. */
					__( 'This is a reminder that your appointment with Dr. %s is tomorrow.', 'doctor-ak-portal' ),
					$appt['doctor_name']
				),
				$appt
			);

			$this->send(
				$appt['doctor_email'],
				$subject,
				__( 'Appointment tomorrow', 'doctor-ak-portal' ),
				sprintf(
					/* translators: %s: patient's display name. */
					__( 'This is a reminder that you have an appointment with %s tomorrow.', 'doctor-ak-portal' ),
					$appt['patient_name']
				),
				$appt
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

			$appt['video_room_url'] = $video['room_url'];

			$subject = __( 'Your Video Consultation Link Is Ready', 'doctor-ak-portal' );

			$this->send(
				$appt['patient_email'],
				$subject,
				__( 'Your video consultation link is ready', 'doctor-ak-portal' ),
				sprintf(
					/* translators: 1: doctor's display name, 2: minutes before the appointment. */
					__( 'Your video consultation with Dr. %1$s starts in about %2$d minutes. Use the link below to join.', 'doctor-ak-portal' ),
					$appt['doctor_name'],
					Appointments::VIDEO_JOIN_WINDOW_BEFORE_MINUTES
				),
				$appt
			);

			$this->send(
				$appt['doctor_email'],
				$subject,
				__( 'Your video consultation link is ready', 'doctor-ak-portal' ),
				sprintf(
					/* translators: 1: patient's display name, 2: minutes before the appointment. */
					__( 'Your video consultation with %1$s starts in about %2$d minutes. Use the link below to join.', 'doctor-ak-portal' ),
					$appt['patient_name'],
					Appointments::VIDEO_JOIN_WINDOW_BEFORE_MINUTES
				),
				$appt
			);

			Appointments::mark_video_link_sent( $appointment['id'] );
		}
	}

	/**
	 * Hook callback: a new doctor account was created and is awaiting admin
	 * approval — emails every administrator.
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

		$name = trim( $doctor->first_name . ' ' . $doctor->last_name );
		$name = '' !== $name ? $name : $doctor->display_name;

		$heading = __( 'New Doctor Registration', 'doctor-ak-portal' );
		$intro   = sprintf(
			/* translators: 1: doctor's display name, 2: doctor's email address. */
			__( 'Dr. %1$s (%2$s) has registered and is awaiting your approval. Review the request from the "Doctor Requests" tab on the admin dashboard.', 'doctor-ak-portal' ),
			$name,
			$doctor->user_email
		);

		foreach ( self::admin_emails() as $admin_email ) {
			$this->send_simple( $admin_email, __( 'New Doctor Registration', 'doctor-ak-portal' ), $heading, $intro );
		}
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

		$this->send_simple(
			$doctor->user_email,
			__( 'Your Account Has Been Approved', 'doctor-ak-portal' ),
			__( 'Account Approved', 'doctor-ak-portal' ),
			__( 'Your account has been approved. You can now log in and start managing your clinic and appointments.', 'doctor-ak-portal' )
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
	 * Sends one simple, non-appointment email — a heading and one-line
	 * intro, no details table (see render_email() for the appointment-shaped
	 * version used elsewhere in this class).
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject_suffix Appended after "[Site Name] " in the subject.
	 * @param string $heading Email body heading.
	 * @param string $intro   One-line message.
	 * @return void
	 */
	private function send_simple( $to, $subject_suffix, $heading, $intro ) {
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		/* translators: 1: site name, 2: email subject. */
		$subject = sprintf( __( '[%1$s] %2$s', 'doctor-ak-portal' ), $site_name, $subject_suffix );
		$html    = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;">'
			. '<div style="background:#4c8a3b;color:#ffffff;padding:16px 20px;font-size:18px;font-weight:700;">' . esc_html( $site_name ) . '</div>'
			. '<div style="padding:20px;border:1px solid #e3e6ea;border-top:none;">'
			. '<h2 style="margin:0 0 12px;font-size:17px;color:#111827;">' . esc_html( $heading ) . '</h2>'
			. '<p style="margin:0;color:#374151;">' . esc_html( $intro ) . '</p>'
			. '</div>'
			. '</div>';
		$headers = array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), self::mail_headers() );

		wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Sends one notification email, silently skipping invalid/empty
	 * addresses (e.g. a deleted patient account with no guest email).
	 *
	 * @param string $to             Recipient email.
	 * @param string $subject_suffix Appended after "[Site Name] " in the subject.
	 * @param string $heading        Email body heading.
	 * @param string $intro          One-line intro sentence.
	 * @param array  $appointment    Row from Appointments::notification_data().
	 * @return void
	 */
	private function send( $to, $subject_suffix, $heading, $intro, array $appointment ) {
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		/* translators: 1: site name, 2: email subject. */
		$subject = sprintf( __( '[%1$s] %2$s', 'doctor-ak-portal' ), $site_name, $subject_suffix );
		$html    = self::render_email( $site_name, $heading, $intro, $appointment );
		$headers = array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), self::mail_headers() );

		wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Builds the From header from the configured (or default) name/email.
	 *
	 * @return array
	 */
	private static function mail_headers() {
		$name  = get_option( self::OPTION_FROM_NAME, '' );
		$email = get_option( self::OPTION_FROM_EMAIL, '' );

		if ( '' === $name ) {
			$name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$email = get_option( 'admin_email' );
		}

		return array( sprintf( 'From: %s <%s>', $name, $email ) );
	}

	/**
	 * Renders the shared HTML template every notification email uses: a
	 * site-name header bar, a one-line intro, and a details table built
	 * from whichever of Doctor/Patient/Type/Date/Time/Service/Charge apply.
	 *
	 * @param string $site_name   Site name for the header bar.
	 * @param string $heading     Body heading.
	 * @param string $intro       One-line intro sentence.
	 * @param array  $appointment Row from Appointments::notification_data().
	 * @return string
	 */
	private static function render_email( $site_name, $heading, $intro, array $appointment ) {
		$rows = array(
			__( 'Doctor', 'doctor-ak-portal' )  => sprintf( 'Dr. %s', $appointment['doctor_name'] ),
			__( 'Patient', 'doctor-ak-portal' ) => $appointment['patient_name'],
			__( 'Type', 'doctor-ak-portal' )    => $appointment['type_label'],
			__( 'Date', 'doctor-ak-portal' )    => $appointment['date'],
			__( 'Time', 'doctor-ak-portal' )    => $appointment['time'],
		);

		if ( '' !== $appointment['service_name'] ) {
			$rows[ __( 'Service', 'doctor-ak-portal' ) ] = $appointment['service_name'];
		}

		if ( $appointment['charge'] > 0 ) {
			$rows[ __( 'Charge', 'doctor-ak-portal' ) ] = 'PKR' . number_format( (float) $appointment['charge'], 0 );
		}

		$rows_html = '';

		foreach ( $rows as $label => $value ) {
			$rows_html .= sprintf(
				'<tr><td style="padding:8px 12px;color:#6b7280;border-bottom:1px solid #e3e6ea;">%s</td><td style="padding:8px 12px;font-weight:600;border-bottom:1px solid #e3e6ea;">%s</td></tr>',
				esc_html( $label ),
				esc_html( $value )
			);
		}

		if ( ! empty( $appointment['video_room_url'] ) ) {
			$rows_html .= sprintf(
				'<tr><td style="padding:8px 12px;color:#6b7280;border-bottom:1px solid #e3e6ea;">%s</td><td style="padding:8px 12px;font-weight:600;border-bottom:1px solid #e3e6ea;"><a href="%s" style="color:#2563eb;">%s</a></td></tr>',
				esc_html__( 'Join Link', 'doctor-ak-portal' ),
				esc_url( $appointment['video_room_url'] ),
				esc_html__( 'Click to Join', 'doctor-ak-portal' )
			);
		}

		return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;">'
			. '<div style="background:#4c8a3b;color:#ffffff;padding:16px 20px;font-size:18px;font-weight:700;">' . esc_html( $site_name ) . '</div>'
			. '<div style="padding:20px;border:1px solid #e3e6ea;border-top:none;">'
			. '<h2 style="margin:0 0 12px;font-size:17px;color:#111827;">' . esc_html( $heading ) . '</h2>'
			. '<p style="margin:0 0 16px;color:#374151;">' . esc_html( $intro ) . '</p>'
			. '<table style="width:100%;border-collapse:collapse;">' . $rows_html . '</table>'
			. '</div>'
			. '</div>';
	}
}
