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

		$this->send(
			$appt['patient_email'],
			$subject,
			__( 'Your appointment has been rescheduled', 'doctor-ak-portal' ),
			sprintf(
				/* translators: %s: doctor's display name. */
				__( 'Your appointment with Dr. %s has been rescheduled to a new date/time — see the details below.', 'doctor-ak-portal' ),
				$appt['doctor_name']
			),
			$appt
		);

		$this->send(
			$appt['doctor_email'],
			$subject,
			__( 'Appointment rescheduled', 'doctor-ak-portal' ),
			sprintf(
				/* translators: %s: patient's display name. */
				__( 'Your appointment with %s has been rescheduled to a new date/time — see the details below.', 'doctor-ak-portal' ),
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

		$this->send_invoice( $appt['patient_email'], $appt );

		$this->send(
			$appt['doctor_email'],
			__( 'Payment Received', 'doctor-ak-portal' ),
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

		$login_url = Page_Finder::url_for_shortcode( 'doctor_login' );

		$this->send_simple(
			$doctor->user_email,
			__( 'Your Account Has Been Approved', 'doctor-ak-portal' ),
			__( 'Account Approved', 'doctor-ak-portal' ),
			__( 'Your account has been approved. You can now log in and start managing your clinic and appointments.', 'doctor-ak-portal' ),
			$login_url,
			__( 'Log In to Your Dashboard', 'doctor-ak-portal' )
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

		$this->send_simple(
			$patient->user_email,
			__( 'Welcome', 'doctor-ak-portal' ),
			__( 'Welcome!', 'doctor-ak-portal' ),
			__( "Your account has been created. Once you've set your password, you can log in to book and manage your appointments.", 'doctor-ak-portal' ),
			$login_url,
			__( 'Log In to Your Dashboard', 'doctor-ak-portal' )
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

		$heading = __( 'Refund Requested', 'doctor-ak-portal' );
		$intro   = sprintf(
			/* translators: 1: patient's display name, 2: doctor's display name, 3: refund reason, 4: amount. */
			__( '%1$s requested a refund for their appointment with Dr. %2$s (PKR%4$s). Reason: %3$s. Review and process it from the admin dashboard\'s Appointments section.', 'doctor-ak-portal' ),
			$appt['patient_name'],
			$appt['doctor_name'],
			$appt['refund_reason'],
			number_format( (float) $appt['refund_amount'], 0 )
		);

		foreach ( self::admin_emails() as $admin_email ) {
			$this->send_simple( $admin_email, $heading, $heading, $intro );
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

		$this->send_simple(
			$appt['patient_email'],
			__( 'Refund Processed', 'doctor-ak-portal' ),
			__( 'Refund Processed', 'doctor-ak-portal' ),
			sprintf(
				/* translators: 1: amount, 2: doctor's display name. */
				__( 'We\'ve processed a refund of PKR%1$s for your appointment with Dr. %2$s. It may take a few business days to appear in your account.', 'doctor-ak-portal' ),
				number_format( (float) $appt['refund_amount'], 0 ),
				$appt['doctor_name']
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
	 * Sends one simple, non-appointment email — a heading and one-line
	 * intro, no details table (see render_email() for the appointment-shaped
	 * version used elsewhere in this class).
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject_suffix Appended after "[Clinic Name] " in the subject.
	 * @param string $heading Email body heading.
	 * @param string $intro   One-line message.
	 * @return void
	 */
	private function send_simple( $to, $subject_suffix, $heading, $intro, $cta_url = '', $cta_label = '' ) {
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$cta_html = '';

		if ( '' !== $cta_url && '' !== $cta_label ) {
			$cta_html = '<p style="margin:20px 0 0;">'
				. '<a href="' . esc_url( $cta_url ) . '" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html( $cta_label ) . '</a>'
				. '</p>';
		}

		/* translators: 1: clinic name, 2: email subject. */
		$subject = sprintf( __( '[%1$s] %2$s', 'doctor-ak-portal' ), self::clinic_name(), $subject_suffix );
		$html    = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;">'
			. '<div style="padding:20px;border:1px solid #e3e6ea;border-radius:8px;">'
			. '<h2 style="margin:0 0 12px;font-size:17px;color:#111827;">' . esc_html( $heading ) . '</h2>'
			. '<p style="margin:0;color:#374151;">' . esc_html( $intro ) . '</p>'
			. $cta_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from esc_url()/esc_html()-wrapped pieces.
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
	 * @param string $subject_suffix Appended after "[Clinic Name] " in the subject.
	 * @param string $heading        Email body heading.
	 * @param string $intro          One-line intro sentence.
	 * @param array  $appointment    Row from Appointments::notification_data().
	 * @return void
	 */
	private function send( $to, $subject_suffix, $heading, $intro, array $appointment ) {
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		/* translators: 1: clinic name, 2: email subject. */
		$subject = sprintf( __( '[%1$s] %2$s', 'doctor-ak-portal' ), self::clinic_name(), $subject_suffix );
		$html    = self::render_email( $heading, $intro, $appointment );
		$headers = array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), self::mail_headers() );

		wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Sends the patient's payment invoice — branded with the clinic's logo
	 * and full contact details (unlike the plain notification template
	 * render_email() uses elsewhere, which deliberately omits that banner
	 * since it's redundant there). Fired once, right after mark_paid(), see
	 * notify_paid() above.
	 *
	 * @param string $to          Patient's email.
	 * @param array  $appointment Row from Appointments::notification_data().
	 * @return void
	 */
	private function send_invoice( $to, array $appointment ) {
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$invoice_number = self::invoice_number( $appointment['id'] );

		/* translators: 1: clinic name, 2: invoice number. */
		$subject = sprintf( __( '[%1$s] Payment Invoice #%2$s', 'doctor-ak-portal' ), self::clinic_name(), $invoice_number );
		$html    = self::render_invoice_email( $appointment );
		$headers = array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), self::mail_headers() );

		$pdf_path = self::write_invoice_pdf_tempfile( $appointment, $invoice_number );

		wp_mail( $to, $subject, $html, $headers, $pdf_path ? array( $pdf_path ) : array() );

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
	 * Renders the patient's payment invoice — clinic logo/name/address/phone
	 * header, an invoice number + paid date, a "Bill To" block, the
	 * appointment/service line item, and the amount paid.
	 *
	 * @param array $appointment Row from Appointments::notification_data().
	 * @return string
	 */
	private static function render_invoice_email( array $appointment ) {
		$logo_url       = Site_Footer::bundled_logo_url();
		$clinic_name    = self::clinic_name();
		$clinic_address = get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' );
		$clinic_phone   = get_option( Site_Footer::OPTION_CLINIC_PHONE, '' );
		$paid_date      = date_i18n( get_option( 'date_format' ) );

		$logo_html = '';

		if ( '' !== $logo_url ) {
			$logo_html = sprintf( '<img src="%s" alt="%s" style="max-height:56px;max-width:220px;display:block;margin-bottom:10px;">', esc_url( $logo_url ), esc_attr( $clinic_name ) );
		}

		$service_label    = '' !== $appointment['service_name'] ? $appointment['service_name'] : $appointment['type_label'];
		$charge           = (float) $appointment['charge'];
		$base_charge      = (float) $appointment['base_charge'] + (float) $appointment['surcharge'];
		$discount_percent = (int) $appointment['discount_percent'];
		$has_discount     = $discount_percent > 0 && $base_charge > $charge;

		$amount_cell_html = $has_discount
			? sprintf(
				'<s style="color:#9ca3af;">PKR%1$s</s><br><span style="color:#111827;">PKR%2$s</span><br><span style="color:#16a34a;font-size:11px;">%3$s%% ' . esc_html__( 'off', 'doctor-ak-portal' ) . '</span>',
				esc_html( number_format( $base_charge, 0 ) ),
				esc_html( number_format( $charge, 0 ) ),
				esc_html( $discount_percent )
			)
			: 'PKR' . esc_html( number_format( $charge, 0 ) );

		$line_items_html = sprintf(
			'<tr>
				<td style="padding:10px 12px;border-bottom:1px solid #e3e6ea;">%1$s<br><span style="color:#6b7280;font-size:12px;">%2$s %3$s %4$s with Dr. %5$s</span></td>
				<td style="padding:10px 12px;border-bottom:1px solid #e3e6ea;text-align:right;white-space:nowrap;">%6$s</td>
			</tr>',
			esc_html( $service_label ),
			esc_html( $appointment['date'] ),
			'&middot;',
			esc_html( $appointment['time'] ),
			esc_html( $appointment['doctor_name'] ),
			$amount_cell_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from esc_html()-wrapped pieces.
		);

		return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#111827;">'
			. '<div style="padding:24px;border:1px solid #e3e6ea;border-radius:8px;">'
			. '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">'
			. '<tr>'
			. '<td style="vertical-align:top;">'
			. $logo_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from esc_url()/esc_attr()-wrapped pieces.
			. '<strong style="font-size:16px;">' . esc_html( $clinic_name ) . '</strong><br>'
			. ( '' !== $clinic_address ? '<span style="color:#6b7280;font-size:13px;">' . esc_html( $clinic_address ) . '</span><br>' : '' )
			. ( '' !== $clinic_phone ? '<span style="color:#6b7280;font-size:13px;">' . esc_html( $clinic_phone ) . '</span>' : '' )
			. '</td>'
			. '<td style="vertical-align:top;text-align:right;">'
			. '<strong style="font-size:16px;">' . esc_html__( 'INVOICE', 'doctor-ak-portal' ) . '</strong><br>'
			. '<span style="color:#6b7280;font-size:13px;">' . esc_html( self::invoice_number( $appointment['id'] ) ) . '</span><br>'
			. '<span style="color:#6b7280;font-size:13px;">' . esc_html( $paid_date ) . '</span>'
			. '</td>'
			. '</tr>'
			. '</table>'
			. '<p style="margin:0 0 4px;color:#6b7280;font-size:12px;text-transform:uppercase;letter-spacing:.03em;">' . esc_html__( 'Billed To', 'doctor-ak-portal' ) . '</p>'
			. '<p style="margin:0 0 20px;">' . esc_html( $appointment['patient_name'] ) . '</p>'
			. '<table style="width:100%;border-collapse:collapse;">'
			. '<tr><th style="text-align:left;padding:0 12px 8px;border-bottom:2px solid #111827;font-size:12px;color:#6b7280;text-transform:uppercase;">' . esc_html__( 'Description', 'doctor-ak-portal' ) . '</th><th style="text-align:right;padding:0 12px 8px;border-bottom:2px solid #111827;font-size:12px;color:#6b7280;text-transform:uppercase;">' . esc_html__( 'Amount', 'doctor-ak-portal' ) . '</th></tr>'
			. $line_items_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from esc_html()-wrapped pieces.
			. ( $has_discount ? '<tr><td style="padding:0 12px;text-align:right;color:#16a34a;font-size:12px;">' . esc_html__( 'You Saved', 'doctor-ak-portal' ) . '</td><td style="padding:0 12px;text-align:right;color:#16a34a;font-size:12px;">PKR' . esc_html( number_format( $base_charge - $charge, 0 ) ) . '</td></tr>' : '' )
			. '<tr><td style="padding:12px;text-align:right;font-weight:700;">' . esc_html__( 'Total Paid', 'doctor-ak-portal' ) . '</td><td style="padding:12px;text-align:right;font-weight:700;">PKR' . esc_html( number_format( $charge, 0 ) ) . '</td></tr>'
			. '</table>'
			. '<p style="margin:24px 0 0;color:#6b7280;font-size:13px;">' . esc_html__( 'Thank you for choosing us — we look forward to seeing you.', 'doctor-ak-portal' ) . '</p>'
			. '</div>'
			. '</div>';
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
	 * Builds the From header from the configured (or default) name/email.
	 *
	 * @return array
	 */
	private static function mail_headers() {
		$name  = get_option( self::OPTION_FROM_NAME, '' );
		$email = get_option( self::OPTION_FROM_EMAIL, '' );

		if ( '' === $name ) {
			$name = self::clinic_name();
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$email = get_option( 'admin_email' );
		}

		return array( sprintf( 'From: %s <%s>', $name, $email ) );
	}

	/**
	 * Renders the shared HTML template every notification email uses: a
	 * heading, a one-line intro, and a details table built from whichever of
	 * Doctor/Patient/Type/Date/Time/Service/Charge apply. No site/clinic
	 * name banner — the clinic name is already the email's visible "From"
	 * sender, so repeating it in the body would be redundant.
	 *
	 * @param string $heading     Body heading.
	 * @param string $intro       One-line intro sentence.
	 * @param array  $appointment Row from Appointments::notification_data().
	 * @return string
	 */
	private static function render_email( $heading, $intro, array $appointment ) {
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
			. '<div style="padding:20px;border:1px solid #e3e6ea;border-radius:8px;">'
			. '<h2 style="margin:0 0 12px;font-size:17px;color:#111827;">' . esc_html( $heading ) . '</h2>'
			. '<p style="margin:0 0 16px;color:#374151;">' . esc_html( $intro ) . '</p>'
			. '<table style="width:100%;border-collapse:collapse;">' . $rows_html . '</table>'
			. '</div>'
			. '</div>';
	}
}
