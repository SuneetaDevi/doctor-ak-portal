<?php
/**
 * Handles the global booking modal's AJAX submission.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Swich_Payment;
use DoctorAKPortal\Includes\Video_Pricing;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Booking_Handler
 *
 * Registered for both wp_ajax_doctor_ak_book_appointment and
 * wp_ajax_nopriv_doctor_ak_book_appointment, since guest bookings must work
 * while logged out. When the visitor is logged in as a patient, their
 * identity is always taken from the session — client-submitted name/email
 * for a logged-in patient is never trusted.
 */
class Booking_Handler {

	/**
	 * Nonce action shared with Booking_Page.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_booking';

	/**
	 * AJAX handler: returns every configured time slot for a doctor/type/
	 * date, each tagged 'available', 'booked', or 'past', so the booking
	 * page's slot-card calendar can show the whole day color-coded rather
	 * than just a list of openings. Public (guests book too).
	 *
	 * @return void
	 */
	public function handle_get_available_slots() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		$type      = ( isset( $_POST['type'] ) && 'video' === $_POST['type'] ) ? 'video' : 'clinic';
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( $doctor_id <= 0 || ! self::is_valid_date( $date ) || ! Appointments::is_active_doctor( $doctor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a doctor and date.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'slots' => Appointments::slot_statuses_for_date( $doctor_id, $type, $date ) ) );
	}

	/**
	 * AJAX handler: a doctor's current cancellation-refund window (and other
	 * booking-rule settings), fetched fresh whenever a doctor is selected on
	 * the booking page — rather than relying only on the copy baked into the
	 * page's initial `wp_localize_script` output, which a full-page cache
	 * (common on a public, logged-out-accessible page like this one) can
	 * easily serve stale for a while after an admin/doctor changes it.
	 * Public (guests book too).
	 *
	 * @return void
	 */
	public function handle_get_booking_rules() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;

		if ( ! Appointments::is_active_doctor( $doctor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a doctor.', 'doctor-ak-portal' ) ) );
		}

		$settings = Video_Pricing::get_for_doctor( $doctor_id );

		wp_send_json_success(
			array(
				'instant_lead_hours'  => $settings['instant_lead_hours'],
				'instant_surcharge'   => $settings['instant_surcharge'],
				'cancel_refund_hours' => $settings['cancel_refund_hours'],
			)
		);
	}

	/**
	 * AJAX handler: returns per-day slot counts for a whole month, so the
	 * booking page's calendar can show a "many slots"/"few left"/"full or
	 * past" dot under each date. Public (guests book too).
	 *
	 * @return void
	 */
	public function handle_get_month_availability() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		$type      = ( isset( $_POST['type'] ) && 'video' === $_POST['type'] ) ? 'video' : 'clinic';
		$year      = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0;
		$month     = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : 0;

		if ( $year < 2000 || $month < 1 || $month > 12 || ! Appointments::is_active_doctor( $doctor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a doctor.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'days' => Appointments::month_availability_summary( $doctor_id, $type, $year, $month ) ) );
	}

	/**
	 * Validates a date string in Y-m-d format.
	 *
	 * @param string $date Date string.
	 * @return bool
	 */
	private static function is_valid_date( $date ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
			return false;
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] );
	}

	/**
	 * AJAX handler: validates and saves a new appointment request.
	 *
	 * @return void
	 */
	public function handle_book_appointment() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id      = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		$type           = ( isset( $_POST['type'] ) && 'video' === $_POST['type'] ) ? 'video' : 'clinic';
		$date           = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time           = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$notes          = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$service_id     = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$clinic_id      = isset( $_POST['clinic_id'] ) ? absint( $_POST['clinic_id'] ) : 0;
		$payment_choice = ( isset( $_POST['payment_choice'] ) && 'now' === $_POST['payment_choice'] ) ? 'now' : 'later';

		$errors = array();

		if ( ! Appointments::is_active_doctor( $doctor_id ) ) {
			$errors['doctor_id'] = __( 'Please choose a doctor.', 'doctor-ak-portal' );
		}

		if ( '' === $date ) {
			$errors['date'] = __( 'Please choose an appointment date.', 'doctor-ak-portal' );
		}

		if ( '' === $time ) {
			$errors['time'] = __( 'Please choose an appointment time.', 'doctor-ak-portal' );
		}

		$current_user         = wp_get_current_user();
		$is_logged_in_patient = is_user_logged_in() && in_array( Roles::PATIENT_ROLE, (array) $current_user->roles, true );
		$is_staff              = current_user_can( 'manage_options' ) || current_user_can( 'doctor_ak_manage_appointments' );

		$patient_id       = 0;
		$guest_name       = '';
		$guest_email      = '';
		$guest_phone      = '';
		$staff_new_patient = false;

		if ( $is_logged_in_patient ) {
			$patient_id = $current_user->ID;
		} elseif ( $is_staff ) {
			$posted_patient_id = isset( $_POST['patient_id'] ) ? absint( wp_unslash( $_POST['patient_id'] ) ) : 0;

			if ( $posted_patient_id > 0 ) {
				$existing_patient = get_userdata( $posted_patient_id );

				if ( ! $existing_patient || ! in_array( Roles::PATIENT_ROLE, (array) $existing_patient->roles, true ) ) {
					$errors['patient_id'] = __( 'Please choose a valid patient.', 'doctor-ak-portal' );
				} else {
					$patient_id = $posted_patient_id;
				}
			} else {
				// "+ Add new patient" — same name/email/phone fields as a
				// guest booking, but this creates a real patient account
				// (see self::create_patient_account()) so this admin/
				// receptionist can find and book them again later.
				$staff_new_patient = true;
				$guest_name        = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '';
				$guest_email       = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';
				$guest_phone       = isset( $_POST['guest_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_phone'] ) ) : '';

				if ( '' === $guest_name ) {
					$errors['guest_name'] = __( "Please enter the patient's name.", 'doctor-ak-portal' );
				}

				if ( '' === $guest_email || ! is_email( $guest_email ) ) {
					$errors['guest_email'] = __( 'Please enter a valid email address.', 'doctor-ak-portal' );
				} elseif ( email_exists( $guest_email ) ) {
					$errors['guest_email'] = __( 'A patient with that email address already exists — search for them above instead.', 'doctor-ak-portal' );
				}
			}
		} else {
			$guest_name  = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '';
			$guest_email = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';
			$guest_phone = isset( $_POST['guest_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_phone'] ) ) : '';

			if ( '' === $guest_name ) {
				$errors['guest_name'] = __( 'Please enter your name.', 'doctor-ak-portal' );
			}

			if ( '' === $guest_email || ! is_email( $guest_email ) ) {
				$errors['guest_email'] = __( 'Please enter a valid email address.', 'doctor-ak-portal' );
			}
		}

		if ( $staff_new_patient && empty( $errors ) ) {
			$new_patient_id = self::create_patient_account( $guest_name, $guest_email, $guest_phone );

			if ( is_wp_error( $new_patient_id ) ) {
				wp_send_json_error( array( 'message' => $new_patient_id->get_error_message() ) );
			}

			$patient_id  = $new_patient_id;
			$guest_name  = '';
			$guest_email = '';
		}

		// A phone number is always required for video consultations (so the
		// clinic can reach the patient), regardless of whether they're paying
		// now or later; for clinic (onsite) visits it's only needed when
		// actually paying online right away.
		$requires_phone = 'video' === $type || 'now' === $payment_choice;

		if ( $requires_phone && empty( $errors ) ) {
			$phone_for_payment = $patient_id > 0 ? get_user_meta( $patient_id, 'doctor_ak_phone_number', true ) : $guest_phone;

			if ( '' === Swich_Payment::normalize_msisdn( $phone_for_payment ) ) {
				if ( $is_logged_in_patient ) {
					$message = 'video' === $type
						? __( 'Please add a valid mobile number (e.g. 03xxxxxxxxx) to your profile before booking a video consultation.', 'doctor-ak-portal' )
						: __( 'Please add a valid mobile number (e.g. 03xxxxxxxxx) to your profile before paying online.', 'doctor-ak-portal' );

					wp_send_json_error( array( 'message' => $message ) );
				}

				if ( $patient_id > 0 ) {
					// A staff member picked (or just created) a patient
					// who has no valid mobile number on file — there's no
					// guest_phone field visible in that flow, so this has
					// to be a top-level message rather than a field error.
					$message = 'video' === $type
						? __( "This patient doesn't have a valid mobile number on file. Please add one to their profile before booking a video consultation.", 'doctor-ak-portal' )
						: __( "This patient doesn't have a valid mobile number on file. Please add one to their profile before paying online.", 'doctor-ak-portal' );

					wp_send_json_error( array( 'message' => $message ) );
				}

				$errors['guest_phone'] = 'video' === $type
					? __( 'A valid mobile number (e.g. 03xxxxxxxxx) is required for a video consultation.', 'doctor-ak-portal' )
					: __( 'A valid mobile number (e.g. 03xxxxxxxxx) is required to pay online.', 'doctor-ak-portal' );
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		$appointment_id = Appointments::create(
			array(
				'doctor_id'   => $doctor_id,
				'patient_id'  => $patient_id,
				'guest_name'  => $guest_name,
				'guest_email' => $guest_email,
				'guest_phone' => $guest_phone,
				'type'        => $type,
				'date'        => $date,
				'time'        => $time,
				'notes'       => $notes,
				'service_id'  => $service_id,
				'clinic_id'   => $clinic_id,
				'payment_choice' => $payment_choice,
			)
		);

		if ( is_wp_error( $appointment_id ) ) {
			wp_send_json_error( array( 'message' => $appointment_id->get_error_message() ) );
		}

		$appointment = Appointments::find( $appointment_id );
		$payment_url = '';

		if ( Appointments::STATUS_PENDING_PAYMENT === $appointment['status'] ) {
			$payment_url = Swich_Payment::build_payment_url( $appointment_id );

			if ( is_wp_error( $payment_url ) ) {
				wp_send_json_error( array( 'message' => $payment_url->get_error_message() ) );
			}
		}

		// "Pay Now" already redirects to Swich via $payment_url above. For
		// "Pay Later" (no payment_url but there's still a charge to settle),
		// a logged-in patient is sent straight to their own dashboard, where
		// the appointment shows up with a working Pay Now button — same
		// place Notifications/Notification_Center point them via email and
		// the in-app Notifications tab.
		$has_pending_charge = '' === $payment_url && (float) $appointment['charge'] > 0 && Appointments::PAYMENT_STATUS_PAID !== $appointment['payment_status'];
		$redirect_url       = ( $is_logged_in_patient && '' === $payment_url )
			? Page_Finder::url_for_shortcode( 'patient_dashboard' )
			: '';

		if ( $is_logged_in_patient ) {
			$message = $has_pending_charge
				? __( 'Your appointment is scheduled. Payment is still pending — pay any time from your dashboard.', 'doctor-ak-portal' )
				: __( 'Your appointment request has been received. You can track it from your dashboard.', 'doctor-ak-portal' );
		} elseif ( $is_staff ) {
			$message = $has_pending_charge
				? __( 'Appointment booked for the patient — payment is still pending.', 'doctor-ak-portal' )
				: __( 'Appointment booked for the patient.', 'doctor-ak-portal' );
		} else {
			$message = $has_pending_charge
				? __( "Your appointment request has been received. It has a pending payment — we'll contact you shortly to arrange it.", 'doctor-ak-portal' )
				: __( "Your appointment request has been received. We'll contact you shortly to confirm.", 'doctor-ak-portal' );
		}

		wp_send_json_success(
			array(
				'message'        => $message,
				'appointment_id' => $appointment_id,
				'payment_url'    => $payment_url,
				'redirect_url'   => $redirect_url,
			)
		);
	}

	/**
	 * Creates a new patient account for a staff member (Admin/Receptionist)
	 * booking on behalf of someone not already in the system — mirrors
	 * Doctor_Patient_Handler::handle_add_patient()'s core account-creation
	 * logic (same Authentication::register_user() primitive, same
	 * "welcome" email), minus the doctor-specific home-clinic tagging that
	 * feature has, since this booking flow has no clinic-location field.
	 *
	 * @param string $full_name New patient's full name.
	 * @param string $email     New patient's email address (caller must have already validated it's a real, not-already-registered address).
	 * @param string $phone     New patient's phone number, or '' if none given.
	 * @return int|\WP_Error New patient user ID, or WP_Error on failure.
	 */
	private static function create_patient_account( $full_name, $email, $phone ) {
		$name_parts = preg_split( '/\s+/', trim( $full_name ), 2 );
		$first_name = $name_parts[0];
		$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

		$authentication = new \DoctorAKPortal\Includes\Authentication();
		$patient_id      = $authentication->register_user(
			array(
				'user_login'   => self::unique_username_from_email( $email ),
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 20, true, true ),
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $full_name,
			),
			Roles::PATIENT_ROLE
		);

		if ( is_wp_error( $patient_id ) ) {
			return $patient_id;
		}

		if ( '' !== $phone ) {
			update_user_meta( $patient_id, 'doctor_ak_phone_number', $phone );
		}

		wp_new_user_notification( $patient_id, null, 'user' );

		return $patient_id;
	}

	/**
	 * Derives a unique WordPress username from an email address's local
	 * part, matching Admin_User_Handler/Doctor_Patient_Handler's own copies
	 * of this same logic.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function unique_username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );

		if ( '' === $base ) {
			$base = 'patient';
		}

		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			++$suffix;
			$username = $base . $suffix;
		}

		return $username;
	}
}
