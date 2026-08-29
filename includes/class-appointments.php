<?php
/**
 * Appointment booking storage, queries, and dashboard rendering.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Appointments
 *
 * Stores bookings as a non-public custom post type (mirroring
 * Specialization_Request's storage approach), keyed to a doctor and either a
 * registered patient or a guest's manually-entered details. Shared by
 * Booking_Handler (creates appointments) and both dashboards (list/count
 * them) so appointment data access lives in one place.
 */
class Appointments {

	/**
	 * Custom post type slug appointments are stored as.
	 *
	 * @var string
	 */
	const POST_TYPE = 'dak_appointment';

	const STATUS_PENDING_PAYMENT = 'pending_payment';
	const STATUS_CONFIRMED       = 'confirmed';
	const STATUS_PAID            = 'paid';
	const STATUS_CANCELLED       = 'cancelled';
	const STATUS_COMPLETED       = 'completed';
	const STATUS_RESCHEDULED     = 'rescheduled';
	const STATUS_CHECKED_IN      = 'checked_in';

	const TYPE_CLINIC = 'clinic';
	const TYPE_VIDEO  = 'video';

	const PAYMENT_STATUS_PENDING = 'pending';
	const PAYMENT_STATUS_PAID    = 'paid';

	const REFUND_STATUS_REQUESTED = 'requested';
	const REFUND_STATUS_PROCESSED = 'processed';

	const REFUND_REASON_MAX_LENGTH = 500;

	const PAYMENT_MODE_MANUAL = 'manual';
	const PAYMENT_MODE_ONLINE = 'online';

	/**
	 * How early, and for how long after the scheduled start, a video
	 * appointment's "Join Call" link is active. There's no explicit call
	 * duration stored anywhere, so the "after" window is a reasonable
	 * assumed consultation length rather than a real end time.
	 */
	const VIDEO_JOIN_WINDOW_BEFORE_MINUTES = 15;
	const VIDEO_JOIN_WINDOW_AFTER_MINUTES  = 90;

	/**
	 * How close to an appointment's current scheduled start it can still be
	 * rescheduled — see reschedule().
	 */
	const RESCHEDULE_CUTOFF_MINUTES_BEFORE = 30;

	/**
	 * Hours after a booked (confirmed) appointment's scheduled start before
	 * it's assumed over and auto-completed if the doctor hasn't manually
	 * marked it — see auto_complete_past_appointments().
	 */
	const AUTO_COMPLETE_HOURS_AFTER = 3;

	/**
	 * Registers the (intentionally non-public) post type used for storage.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Appointments', 'doctor-ak-portal' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'capability_type' => 'post',
				'supports'        => array( 'title' ),
			)
		);
	}

	/**
	 * Resolves the service(s) picked for a clinic appointment into its
	 * charge/name fields — shared by create()/update() so both accept
	 * either a single `service_id` (the patient-facing booking flow, one
	 * service per visit) or several via `service_ids[]` (the admin Add/Edit
	 * Appointment modal's multi-select, letting an admin bundle more than
	 * one service — e.g. "OPD Consultation" + "Dressing" — into one visit).
	 * Every picked service's charge is summed and its name joined into one
	 * comma-separated string, so nothing downstream (invoices, PDFs, the
	 * revenue ledger) needs to know it's more than one service — they only
	 * ever see the combined charge/name, the same shape a single service
	 * already produced. Never applies to a video consultation, which always
	 * uses the doctor's own fixed price instead of a picked service.
	 *
	 * @param array  $data      Raw data passed to create()/update() — reads 'service_id' and/or 'service_ids'.
	 * @param int    $doctor_id Doctor's user ID every picked service must belong to.
	 * @param string $type      'clinic' or 'video'.
	 * @return array|\WP_Error {
	 *     @type int    $service_id   First/primary picked service's ID, 0 if none.
	 *     @type array  $service_ids  Every picked service's ID, for storage (empty array if none).
	 *     @type string $service_name Joined name(s), '' if none.
	 *     @type float  $charge       Summed charge.
	 *     @type float  $base_charge  Same as $charge (kept as its own field to mirror the video-consultation branch's base/final price split).
	 * }
	 */
	private static function resolve_services( array $data, $doctor_id, $type ) {
		$empty = array(
			'service_id'   => 0,
			'service_ids'  => array(),
			'service_name' => '',
			'charge'       => 0.0,
			'base_charge'  => 0.0,
		);

		if ( self::TYPE_VIDEO === $type ) {
			return $empty;
		}

		$posted_ids = array();

		if ( isset( $data['service_ids'] ) && is_array( $data['service_ids'] ) ) {
			$posted_ids = array_values( array_unique( array_filter( array_map( 'absint', $data['service_ids'] ) ) ) );
		} elseif ( isset( $data['service_id'] ) && (int) $data['service_id'] > 0 ) {
			$posted_ids = array( (int) $data['service_id'] );
		}

		if ( empty( $posted_ids ) ) {
			return $empty;
		}

		$names  = array();
		$charge = 0.0;

		foreach ( $posted_ids as $service_id ) {
			$service = Services::find( $service_id );

			if ( ! $service || (int) $service['doctor_id'] !== (int) $doctor_id || self::TYPE_CLINIC !== $service['type'] || empty( $service['active'] ) ) {
				return new \WP_Error( 'doctor_ak_invalid_service', __( 'Please choose valid, active services.', 'doctor-ak-portal' ) );
			}

			$names[] = $service['name'];
			$charge += (float) $service['charge'];
		}

		return array(
			'service_id'   => $posted_ids[0],
			'service_ids'  => $posted_ids,
			'service_name' => implode( ', ', $names ),
			'charge'       => $charge,
			'base_charge'  => $charge,
		);
	}

	/**
	 * Validates and saves a new appointment.
	 *
	 * @param array $data {
	 *     @type int    $doctor_id   Doctor's user ID.
	 *     @type int    $patient_id  Logged-in patient's user ID, or 0 for a guest booking.
	 *     @type string $guest_name  Guest's name (ignored when patient_id is set).
	 *     @type string $guest_email Guest's email (ignored when patient_id is set).
	 *     @type string $guest_phone Guest's phone (ignored when patient_id is set).
	 *     @type string $type        'clinic' or 'video'.
	 *     @type int    $clinic_id   For a clinic visit, which of the doctor's physical Clinics rows the patient is visiting (0 if the doctor has none configured, or for a video appointment).
	 *     @type string $date        'YYYY-MM-DD'.
	 *     @type string $time        'HH:MM'.
	 *     @type string $notes       Optional free text.
	 * }
	 * @return int|\WP_Error New appointment post ID, or WP_Error on invalid input.
	 */
	public static function create( array $data ) {
		$doctor_id = isset( $data['doctor_id'] ) ? (int) $data['doctor_id'] : 0;
		$doctor    = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
			return new \WP_Error( 'doctor_ak_invalid_doctor', __( 'Please choose a valid doctor.', 'doctor-ak-portal' ) );
		}

		$type = isset( $data['type'] ) && self::TYPE_VIDEO === $data['type'] ? self::TYPE_VIDEO : self::TYPE_CLINIC;

		if ( self::TYPE_VIDEO === $type && ! Clinics::doctor_has_active_video_clinic( $doctor_id ) ) {
			return new \WP_Error( 'doctor_ak_video_not_offered', __( 'This doctor does not offer online video consultations.', 'doctor-ak-portal' ) );
		}

		// A clinic visit needs to know WHICH of the doctor's physical clinic
		// locations the patient is going to when the doctor practices at more
		// than one — see Booking_Page::clinics_by_doctor(). Doctors with no
		// physical clinic configured yet (legacy/incomplete profiles) are
		// exempt, since there's nothing to pick from. Only enforced for the
		// real patient-facing booking flow — the admin "Add Appointment"
		// modal has no clinic picker yet, so admin_override bookings simply
		// accept whatever (or no) clinic_id was posted rather than blocking
		// the admin on a field their form doesn't offer.
		$clinic_id = isset( $data['clinic_id'] ) ? (int) $data['clinic_id'] : 0;

		if ( self::TYPE_CLINIC === $type && empty( $data['admin_override'] ) ) {
			$doctor_physical_clinic_ids = wp_list_pluck(
				array_filter(
					Clinics::get_for_doctor( $doctor_id ),
					function ( $clinic ) {
						return Clinics::TYPE_PHYSICAL === $clinic['type'];
					}
				),
				'id'
			);

			if ( ! empty( $doctor_physical_clinic_ids ) && ! in_array( $clinic_id, $doctor_physical_clinic_ids, true ) ) {
				return new \WP_Error( 'doctor_ak_invalid_clinic', __( "Please choose which of the doctor's clinics you'd like to visit.", 'doctor-ak-portal' ) );
			}

			if ( empty( $doctor_physical_clinic_ids ) ) {
				$clinic_id = 0;
			}
		} elseif ( self::TYPE_VIDEO === $type ) {
			$clinic_id = 0;
		}

		$date = isset( $data['date'] ) ? sanitize_text_field( $data['date'] ) : '';
		$time = isset( $data['time'] ) ? sanitize_text_field( $data['time'] ) : '';

		if ( ! self::is_valid_date( $date ) ) {
			return new \WP_Error( 'doctor_ak_invalid_date', __( 'Please choose a valid appointment date.', 'doctor-ak-portal' ) );
		}

		if ( ! self::is_valid_time( $time ) ) {
			return new \WP_Error( 'doctor_ak_invalid_time', __( 'Please choose a valid appointment time.', 'doctor-ak-portal' ) );
		}

		if ( self::is_slot_taken( $doctor_id, $date, $time ) ) {
			return new \WP_Error( 'doctor_ak_slot_taken', __( 'That time slot has just been booked by someone else. Please choose another time.', 'doctor-ak-portal' ) );
		}

		$patient_id  = isset( $data['patient_id'] ) ? (int) $data['patient_id'] : 0;
		$guest_name  = '';
		$guest_email = '';
		$guest_phone = '';

		if ( $patient_id <= 0 ) {
			$guest_name  = isset( $data['guest_name'] ) ? sanitize_text_field( $data['guest_name'] ) : '';
			$guest_email = isset( $data['guest_email'] ) ? sanitize_email( $data['guest_email'] ) : '';
			$guest_phone = isset( $data['guest_phone'] ) ? sanitize_text_field( $data['guest_phone'] ) : '';

			if ( '' === $guest_name || '' === $guest_email || ! is_email( $guest_email ) ) {
				return new \WP_Error( 'doctor_ak_invalid_guest_details', __( 'Please provide your name and a valid email address.', 'doctor-ak-portal' ) );
			}
		}

		$notes         = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '';
		$patient_label = $patient_id > 0 ? self::patient_display_name( $patient_id ) : $guest_name;

		$service_id       = 0;
		$service_ids      = array();
		$service_name     = '';
		$charge           = 0.0;
		$base_charge      = 0.0;
		$discount_percent = 0;
		$payment_mode     = self::PAYMENT_MODE_MANUAL;

		if ( self::TYPE_VIDEO === $type ) {
			// Video consultations use the doctor's fixed (possibly
			// discounted) price instead of a picked service.
			$service_name = __( 'Video Consultation', 'doctor-ak-portal' );
			$pricing      = Video_Pricing::effective_price_for_doctor( $doctor_id );
			$charge       = $pricing['final_price'];
			$base_charge  = $pricing['base_price'];

			if ( $pricing['discount_active'] ) {
				$discount_percent = $pricing['discount_percent'];
			}
		} else {
			$resolved = self::resolve_services( $data, $doctor_id, $type );

			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}

			$service_id   = $resolved['service_id'];
			$service_ids  = $resolved['service_ids'];
			$service_name = $resolved['service_name'];
			$charge       = $resolved['charge'];
			$base_charge  = $resolved['base_charge'];
		}

		// The instant-booking surcharge is a video-consultation pricing knob
		// (see the TYPE_VIDEO branch above) — gating it to that type matters
		// beyond video bookings too: a clinic walk-in (see
		// Encounter_Handler::handle_create_encounter()) is always booked a
		// minute in the future, which would otherwise fall inside any
		// doctor's configured instant-lead window and silently add their
		// video surcharge to a clinic visit's charge.
		$is_instant = self::TYPE_VIDEO === $type && Video_Pricing::is_instant_booking( $doctor_id, $date, $time );
		$surcharge  = $is_instant ? Video_Pricing::instant_surcharge_for_doctor( $doctor_id ) : 0.0;
		$charge    += $surcharge;

		$data['charge'] = $charge;

		if ( ! empty( $data['admin_override'] ) ) {
			// The admin dashboard's "+ Add Appointment" lets an admin book on a
			// patient's behalf and immediately choose whether they collected
			// payment themselves (Paid) or the patient still needs to pay
			// (Pending) — unlike the patient-facing Booking_Handler flow, which
			// must never let the client dictate its own payment status.
			$status_options = self::status_options();
			$status         = isset( $data['status'] ) && array_key_exists( $data['status'], $status_options ) ? $data['status'] : self::STATUS_CONFIRMED;
			$payment_status = isset( $data['payment_status'] ) && self::PAYMENT_STATUS_PAID === $data['payment_status'] ? self::PAYMENT_STATUS_PAID : self::PAYMENT_STATUS_PENDING;
			$payment_mode   = isset( $data['payment_mode'] ) && self::PAYMENT_MODE_ONLINE === $data['payment_mode'] ? self::PAYMENT_MODE_ONLINE : self::PAYMENT_MODE_MANUAL;

			// Same rule Appointments::update() enforces when editing an
			// existing appointment (see its own copy of this check below) —
			// "completed" is meant to imply the visit happened and was paid
			// for, so an admin can't create a brand-new appointment that's
			// already both Completed and Payment Pending either. A free ($0)
			// appointment has nothing to collect, so it's exempt.
			if ( self::STATUS_COMPLETED === $status && $charge > 0 && self::PAYMENT_STATUS_PAID !== $payment_status ) {
				return new \WP_Error( 'doctor_ak_appointment_payment_pending', __( 'This appointment still has a pending payment — mark it paid before completing it.', 'doctor-ak-portal' ) );
			}
		} else {
			$requires_payment = apply_filters( 'doctor_ak_appointment_requires_payment', false, $data );
			$status           = $requires_payment ? self::STATUS_PENDING_PAYMENT : self::STATUS_CONFIRMED;
			$payment_mode     = $requires_payment ? self::PAYMENT_MODE_ONLINE : self::PAYMENT_MODE_MANUAL;

			/**
			 * Filters a new appointment's payment status at creation time.
			 *
			 * Defaults to "pending" for every booking until a payment module is
			 * built; that module would hook here to mark cash/gateway-confirmed
			 * bookings as PAYMENT_STATUS_PAID immediately.
			 *
			 * @param string $payment_status Default PAYMENT_STATUS_PENDING.
			 * @param array  $data           Raw data passed to create().
			 */
			$payment_status = apply_filters( 'doctor_ak_appointment_payment_status', self::PAYMENT_STATUS_PENDING, $data );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				/* translators: 1: patient/guest name, 2: doctor's display name, 3: date, 4: time. */
				'post_title'  => sprintf( __( '%1$s with Dr. %2$s — %3$s %4$s', 'doctor-ak-portal' ), $patient_label, $doctor->display_name, $date, $time ),
				'post_status' => 'publish',
				'post_author' => $patient_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, 'doctor_ak_appointment_doctor_id', $doctor_id );
		update_post_meta( $post_id, 'doctor_ak_appointment_patient_id', $patient_id );
		update_post_meta( $post_id, 'doctor_ak_appointment_guest_name', $guest_name );
		update_post_meta( $post_id, 'doctor_ak_appointment_guest_email', $guest_email );
		update_post_meta( $post_id, 'doctor_ak_appointment_guest_phone', $guest_phone );
		update_post_meta( $post_id, 'doctor_ak_appointment_type', $type );
		update_post_meta( $post_id, 'doctor_ak_appointment_clinic_id', $clinic_id );
		update_post_meta( $post_id, 'doctor_ak_appointment_date', $date );
		update_post_meta( $post_id, 'doctor_ak_appointment_time', $time );
		update_post_meta( $post_id, 'doctor_ak_appointment_status', $status );
		update_post_meta( $post_id, 'doctor_ak_appointment_payment_status', $payment_status );
		update_post_meta( $post_id, 'doctor_ak_appointment_notes', $notes );
		update_post_meta( $post_id, 'doctor_ak_appointment_service_id', $service_id );
		update_post_meta( $post_id, 'doctor_ak_appointment_service_ids', wp_json_encode( $service_ids ) );
		update_post_meta( $post_id, 'doctor_ak_appointment_service_name', $service_name );
		update_post_meta( $post_id, 'doctor_ak_appointment_charge', $charge );
		update_post_meta( $post_id, 'doctor_ak_appointment_base_charge', $base_charge );
		update_post_meta( $post_id, 'doctor_ak_appointment_discount_percent', $discount_percent );
		update_post_meta( $post_id, 'doctor_ak_appointment_payment_mode', $payment_mode );
		update_post_meta( $post_id, 'doctor_ak_appointment_is_instant', $is_instant ? 1 : 0 );
		update_post_meta( $post_id, 'doctor_ak_appointment_surcharge', $surcharge );

		if ( self::TYPE_VIDEO === $type ) {
			// A random, unguessable Jitsi Meet room per video appointment —
			// anyone with the URL can join, so it must not be predictable
			// from the appointment ID alone.
			update_post_meta( $post_id, 'doctor_ak_appointment_video_room', 'dak-' . wp_generate_password( 24, false, false ) );
		}

		/**
		 * Fires after a new appointment is saved.
		 *
		 * A future payment module can hook here to redirect to checkout when
		 * the appointment's status is STATUS_PENDING_PAYMENT.
		 *
		 * @param int   $post_id New appointment post ID.
		 * @param array $data    Raw data passed to create().
		 */
		do_action( 'doctor_ak_appointment_created', $post_id, $data );

		if ( self::PAYMENT_STATUS_PAID === $payment_status ) {
			/** This action is documented in mark_paid() above. */
			do_action( 'doctor_ak_appointment_paid', $post_id );
		}

		return $post_id;
	}

	/**
	 * Updates every field of an existing appointment — used by the admin
	 * dashboard's Appointments "Edit" action, which (unlike patient booking)
	 * can re-target the doctor/patient/service and directly set status and
	 * payment status.
	 *
	 * @param int   $appointment_id Appointment post ID.
	 * @param array $data           Same shape as create(), plus optional 'status', 'payment_status', 'payment_mode'.
	 * @return true|\WP_Error
	 */
	public static function update( $appointment_id, array $data ) {
		$post = get_post( $appointment_id );

		$was_already_paid = self::PAYMENT_STATUS_PAID === get_post_meta( $appointment_id, 'doctor_ak_appointment_payment_status', true );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'doctor_ak_invalid_appointment', __( 'That appointment no longer exists.', 'doctor-ak-portal' ) );
		}

		$doctor_id = isset( $data['doctor_id'] ) ? (int) $data['doctor_id'] : 0;
		$doctor    = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
			return new \WP_Error( 'doctor_ak_invalid_doctor', __( 'Please choose a valid doctor.', 'doctor-ak-portal' ) );
		}

		$type = isset( $data['type'] ) && self::TYPE_VIDEO === $data['type'] ? self::TYPE_VIDEO : self::TYPE_CLINIC;

		if ( self::TYPE_VIDEO === $type && ! Clinics::doctor_has_active_video_clinic( $doctor_id ) ) {
			return new \WP_Error( 'doctor_ak_video_not_offered', __( 'This doctor does not offer online video consultations.', 'doctor-ak-portal' ) );
		}

		$date = isset( $data['date'] ) ? sanitize_text_field( $data['date'] ) : '';
		$time = isset( $data['time'] ) ? sanitize_text_field( $data['time'] ) : '';

		if ( ! self::is_valid_date( $date ) ) {
			return new \WP_Error( 'doctor_ak_invalid_date', __( 'Please choose a valid appointment date.', 'doctor-ak-portal' ) );
		}

		if ( ! self::is_valid_time( $time ) ) {
			return new \WP_Error( 'doctor_ak_invalid_time', __( 'Please choose a valid appointment time.', 'doctor-ak-portal' ) );
		}

		// Only enforced for the patient-facing booking flow — an admin
		// booking on a patient's behalf (admin_override, see below) may
		// legitimately need to log a walk-in visit that already happened.
		if ( empty( $data['admin_override'] ) ) {
			$requested_start = strtotime( $date . ' ' . $time );

			if ( false !== $requested_start && $requested_start < current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of a posted local date/time string, not doing math that needs UTC.
				return new \WP_Error( 'doctor_ak_appointment_in_past', __( 'Please choose a date and time in the future.', 'doctor-ak-portal' ) );
			}
		}

		$patient_id  = isset( $data['patient_id'] ) ? (int) $data['patient_id'] : 0;
		$guest_name  = '';
		$guest_email = '';
		$guest_phone = '';

		if ( $patient_id <= 0 ) {
			$guest_name  = isset( $data['guest_name'] ) ? sanitize_text_field( $data['guest_name'] ) : '';
			$guest_email = isset( $data['guest_email'] ) ? sanitize_email( $data['guest_email'] ) : '';
			$guest_phone = isset( $data['guest_phone'] ) ? sanitize_text_field( $data['guest_phone'] ) : '';

			if ( '' === $guest_name || '' === $guest_email || ! is_email( $guest_email ) ) {
				return new \WP_Error( 'doctor_ak_invalid_guest_details', __( "Please provide the patient's name and a valid email address.", 'doctor-ak-portal' ) );
			}
		}

		$notes         = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '';
		$patient_label = $patient_id > 0 ? self::patient_display_name( $patient_id ) : $guest_name;

		$service_id       = 0;
		$service_ids      = array();
		$service_name     = '';
		$charge           = 0.0;
		$base_charge      = 0.0;
		$discount_percent = 0;

		if ( self::TYPE_VIDEO === $type ) {
			// Video consultations use the doctor's fixed (possibly
			// discounted) price instead of a picked service.
			$service_name = __( 'Video Consultation', 'doctor-ak-portal' );
			$pricing      = Video_Pricing::effective_price_for_doctor( $doctor_id );
			$charge       = $pricing['final_price'];
			$base_charge  = $pricing['base_price'];

			if ( $pricing['discount_active'] ) {
				$discount_percent = $pricing['discount_percent'];
			}
		} else {
			$resolved = self::resolve_services( $data, $doctor_id, $type );

			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}

			$service_id   = $resolved['service_id'];
			$service_ids  = $resolved['service_ids'];
			$service_name = $resolved['service_name'];
			$charge       = $resolved['charge'];
			$base_charge  = $resolved['base_charge'];
		}

		$status_options = self::status_options();
		$status         = isset( $data['status'] ) && array_key_exists( $data['status'], $status_options ) ? $data['status'] : self::STATUS_CONFIRMED;
		$payment_status = isset( $data['payment_status'] ) && self::PAYMENT_STATUS_PAID === $data['payment_status'] ? self::PAYMENT_STATUS_PAID : self::PAYMENT_STATUS_PENDING;
		$payment_mode   = isset( $data['payment_mode'] ) && self::PAYMENT_MODE_ONLINE === $data['payment_mode'] ? self::PAYMENT_MODE_ONLINE : self::PAYMENT_MODE_MANUAL;

		// "Completed" is meant to imply the visit happened and was paid for
		// (see mark_completed()) — an admin can't set both Completed and
		// Payment Pending at once for a chargeable appointment either. A
		// free ($0) appointment has nothing to collect, so it's exempt.
		if ( self::STATUS_COMPLETED === $status && $charge > 0 && self::PAYMENT_STATUS_PAID !== $payment_status ) {
			return new \WP_Error( 'doctor_ak_appointment_payment_pending', __( 'This appointment still has a pending payment — mark it paid before completing it.', 'doctor-ak-portal' ) );
		}

		// Once a patient is checked in, the clinical Encounter (see
		// Encounters class) is the only place that's supposed to move the
		// appointment off "Checked In" — closing it calls checkout(), which
		// sets Completed the same way this edit form would. Editing the
		// appointment directly to any other status while that encounter is
		// still open would silently orphan it (still open, with nothing
		// pointing back at its now-changed appointment), so block that here
		// instead and point the admin at the actual fix.
		$current_status = get_post_meta( $appointment_id, 'doctor_ak_appointment_status', true );

		if ( self::STATUS_CHECKED_IN === $current_status && $status !== $current_status
			&& Encounters::find_by_appointment( $appointment_id, Encounters::STATUS_OPEN )
		) {
			return new \WP_Error( 'doctor_ak_appointment_encounter_open', __( 'This patient is checked in with an open encounter — close the encounter first.', 'doctor-ak-portal' ) );
		}

		wp_update_post(
			array(
				'ID'          => $appointment_id,
				/* translators: 1: patient/guest name, 2: doctor's display name, 3: date, 4: time. */
				'post_title'  => sprintf( __( '%1$s with Dr. %2$s — %3$s %4$s', 'doctor-ak-portal' ), $patient_label, $doctor->display_name, $date, $time ),
				'post_author' => $patient_id,
			)
		);

		update_post_meta( $appointment_id, 'doctor_ak_appointment_doctor_id', $doctor_id );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_patient_id', $patient_id );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_guest_name', $guest_name );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_guest_email', $guest_email );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_guest_phone', $guest_phone );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_type', $type );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_date', $date );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_time', $time );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_status', $status );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_payment_status', $payment_status );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_notes', $notes );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_service_id', $service_id );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_service_ids', wp_json_encode( $service_ids ) );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_service_name', $service_name );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_charge', $charge );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_base_charge', $base_charge );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_discount_percent', $discount_percent );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_payment_mode', $payment_mode );

		// Covers the "offline patient" flow: an admin adds a walk-in
		// patient's appointment as Pending, they pay at the desk, and the
		// admin then edits it to Paid — the same invoice email + PDF an
		// online payment triggers should fire here too, but only once (not
		// on every subsequent edit of an already-paid appointment).
		if ( self::PAYMENT_STATUS_PAID === $payment_status && ! $was_already_paid ) {
			/** This action is documented in mark_paid() above. */
			do_action( 'doctor_ak_appointment_paid', $appointment_id );
		}

		return true;
	}

	/**
	 * Permanently deletes an appointment — used by the admin dashboard's
	 * Appointments "Delete" action.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return bool
	 */
	public static function delete( $appointment_id ) {
		$post = get_post( $appointment_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return (bool) wp_delete_post( $appointment_id, true );
	}

	/**
	 * Per-day slot counts for a whole calendar month — powers the booking
	 * page's calendar dots (green "many slots" / amber "few left" / grey
	 * "full or past"). Fetches the doctor's clinics and appointments once
	 * up front rather than per day.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $type      'clinic' or 'video'.
	 * @param int    $year      Four-digit year.
	 * @param int    $month     1-12.
	 * @return array 'YYYY-MM-DD' => array( 'total' => int, 'available' => int ).
	 */
	public static function month_availability_summary( $doctor_id, $type, $year, $month ) {
		$clinics = Clinics::get_for_doctor( $doctor_id );

		$booked = array();

		foreach ( self::for_doctor( $doctor_id ) as $appointment ) {
			if ( self::PAYMENT_STATUS_PAID === $appointment['payment_status'] && self::STATUS_CANCELLED !== $appointment['status'] ) {
				$booked[ $appointment['date'] ][ $appointment['time'] ] = true;
			}
		}

		$days_in_month = (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) );
		$today         = current_time( 'Y-m-d' );
		$now           = current_time( 'H:i' );
		$summary       = array();

		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			$grid = Clinics::slot_grid_from_clinics( $clinics, $type, $date );

			$available = 0;

			foreach ( $grid as $slot ) {
				if ( isset( $booked[ $date ][ $slot ] ) ) {
					continue;
				}

				if ( $date === $today && $slot <= $now ) {
					continue;
				}

				$available++;
			}

			$summary[ $date ] = array(
				'total'     => count( $grid ),
				'available' => $available,
			);
		}

		return $summary;
	}

	/**
	 * Every configured time slot for a doctor on a given date, each tagged
	 * with its booking status — for the booking page's slot-card calendar,
	 * which shows the whole day's grid (not just openings) color-coded by
	 * status. Built from the doctor's full session grid
	 * (Clinics::slot_grid_for_date()), with slots already locked by a paid,
	 * non-cancelled booking marked 'booked', slots earlier than the current
	 * time on today marked 'past', and everything else 'available'.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $type      'clinic' or 'video'.
	 * @param string $date      'YYYY-MM-DD'.
	 * @return array List of `array( 'time' => 'HH:MM', 'status' => 'available'|'booked'|'past', 'is_instant' => bool, 'surcharge' => float )`, sorted ascending.
	 */
	public static function slot_statuses_for_date( $doctor_id, $type, $date ) {
		$grid = Clinics::slot_grid_for_date( $doctor_id, $type, $date );

		if ( empty( $grid ) ) {
			return array();
		}

		$booked = array();

		foreach ( self::for_doctor( $doctor_id ) as $appointment ) {
			if ( $appointment['date'] === $date
				&& self::PAYMENT_STATUS_PAID === $appointment['payment_status']
				&& self::STATUS_CANCELLED !== $appointment['status']
			) {
				$booked[ $appointment['time'] ] = true;
			}
		}

		$today             = current_time( 'Y-m-d' );
		$now               = current_time( 'H:i' );
		$instant_surcharge = Video_Pricing::instant_surcharge_for_doctor( $doctor_id );

		return array_map(
			function ( $slot ) use ( $booked, $date, $today, $now, $doctor_id, $instant_surcharge ) {
				if ( isset( $booked[ $slot ] ) ) {
					$status = 'booked';
				} elseif ( $date === $today && $slot <= $now ) {
					$status = 'past';
				} else {
					$status = 'available';
				}

				$is_instant = 'available' === $status && Video_Pricing::is_instant_booking( $doctor_id, $date, $slot );

				return array(
					'time'       => $slot,
					'status'     => $status,
					'is_instant' => $is_instant,
					'surcharge'  => $is_instant ? $instant_surcharge : 0.0,
				);
			},
			$grid
		);
	}

	/**
	 * Appointments booked with a given doctor, most recent last.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array
	 */
	public static function for_doctor( $doctor_id ) {
		return self::query( 'doctor_ak_appointment_doctor_id', $doctor_id );
	}

	/**
	 * Count of distinct patients (registered or guest) who have ever booked
	 * an appointment with a given doctor — for the doctor dashboard's "Total
	 * Patients" stat, so it reflects that doctor's own patients rather than
	 * every patient account site-wide.
	 *
	 * @param int        $doctor_id    Doctor's user ID.
	 * @param array|null $appointments Optional pre-fetched self::for_doctor() result, so a caller that already has it (e.g. Doctor_Dashboard::prepare_data()) doesn't trigger a second identical query.
	 * @return int
	 */
	public static function unique_patient_count_for_doctor( $doctor_id, $appointments = null ) {
		$seen = array();

		foreach ( ( null !== $appointments ? $appointments : self::for_doctor( $doctor_id ) ) as $appointment ) {
			$key = $appointment['patient_id'] > 0 ? 'u' . $appointment['patient_id'] : 'g' . strtolower( $appointment['guest_email'] );

			$seen[ $key ] = true;
		}

		return count( $seen );
	}

	/**
	 * Appointments booked by a given (logged-in) patient, most recent last.
	 *
	 * @param int $patient_id Patient's user ID.
	 * @return array
	 */
	public static function for_patient( $patient_id ) {
		return self::query( 'doctor_ak_appointment_patient_id', $patient_id );
	}

	/**
	 * Whether a patient "belongs" to a doctor — either they have (or have
	 * had) an appointment with that doctor, or the doctor added them
	 * directly (doctor_ak_added_by_doctor, see Doctor_Patient_Handler).
	 * Gates the doctor dashboard's Patients tab edit action so a doctor can
	 * never edit another doctor's patient.
	 *
	 * @param int $doctor_id  Doctor's user ID.
	 * @param int $patient_id Patient's user ID.
	 * @return bool
	 */
	public static function is_doctors_patient( $doctor_id, $patient_id ) {
		if ( (int) get_user_meta( $patient_id, 'doctor_ak_added_by_doctor', true ) === (int) $doctor_id ) {
			return true;
		}

		foreach ( self::for_doctor( $doctor_id ) as $appointment ) {
			if ( (int) $appointment['patient_id'] === (int) $patient_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every registered patient "belonging" to a doctor (see
	 * is_doctors_patient()) — from appointment history and/or having been
	 * added directly by that doctor — for the doctor dashboard's Patients
	 * tab. Guests (no account) aren't included since there's no profile to
	 * edit. Most-recently-registered patient first.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array List of `array( 'id', 'name', 'email', 'phone', 'clinic_name', 'last_visit' )`, 'last_visit' '' if none yet.
	 */
	public static function patients_for_doctor( $doctor_id ) {
		$last_visit  = array();
		$visit_count = array();

		foreach ( self::for_doctor( $doctor_id ) as $appointment ) {
			if ( $appointment['patient_id'] <= 0 ) {
				continue;
			}

			$patient_id = (int) $appointment['patient_id'];

			if ( ! isset( $last_visit[ $patient_id ] ) || $appointment['date'] > $last_visit[ $patient_id ] ) {
				$last_visit[ $patient_id ] = $appointment['date'];
			}

			$visit_count[ $patient_id ] = isset( $visit_count[ $patient_id ] ) ? $visit_count[ $patient_id ] + 1 : 1;
		}

		$added_query = new \WP_User_Query(
			array(
				'role'       => Roles::PATIENT_ROLE,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no better lookup available; small per-doctor result set.
					array(
						'key'   => 'doctor_ak_added_by_doctor',
						'value' => $doctor_id,
					),
				),
				'fields'     => 'ID',
			)
		);

		$patient_ids = array_unique( array_merge( array_keys( $last_visit ), array_map( 'intval', $added_query->get_results() ) ) );

		$rows = array();

		foreach ( $patient_ids as $patient_id ) {
			$patient = get_userdata( $patient_id );

			if ( ! $patient ) {
				continue;
			}

			$clinic_id   = (int) get_user_meta( $patient_id, 'doctor_ak_added_by_clinic', true );
			$clinic_name = $clinic_id > 0 ? Clinics::clinic_name_for_doctor( $doctor_id, $clinic_id ) : '';

			$rows[] = array(
				'id'          => $patient_id,
				'name'        => self::patient_display_name( $patient_id ),
				'first_name'  => $patient->first_name,
				'last_name'   => $patient->last_name,
				'email'       => $patient->user_email,
				'phone'       => get_user_meta( $patient_id, 'doctor_ak_phone_number', true ),
				'clinic_name' => $clinic_name,
				'last_visit'  => isset( $last_visit[ $patient_id ] ) ? $last_visit[ $patient_id ] : '',
				'visit_count' => isset( $visit_count[ $patient_id ] ) ? $visit_count[ $patient_id ] : 0,
				'avatar_url'  => self::avatar_url_for_user( $patient_id ),
				'registered_date' => mysql2date( get_option( 'date_format' ), $patient->user_registered ),
				'registered_at'   => $patient->user_registered,
				'clinic_location_id' => (int) get_user_meta( $patient_id, Clinic_Locations::PATIENT_META_KEY, true ),
			);
		}

		// Most-recently-registered patient first, per the "Patients" tab's
		// listing convention used across the portal.
		usort(
			$rows,
			function ( $a, $b ) {
				return strcmp( $b['registered_at'], $a['registered_at'] );
			}
		);

		return $rows;
	}

	/**
	 * Everything the patient dashboard needs about a patient's upcoming
	 * appointments in one call: the nearest one, unpaid totals, and the
	 * full list grouped into Today/Tomorrow/This Week/Later — each row
	 * enriched with the doctor's name/avatar/specialization and a
	 * human countdown label.
	 *
	 * @param int $patient_id Patient's user ID.
	 * @return array {
	 *     @type array|null $next_appointment     Nearest upcoming row, or null.
	 *     @type int        $unpaid_count         Unpaid upcoming appointment count.
	 *     @type float      $unpaid_total         Sum of unpaid upcoming charges.
	 *     @type array      $groups               'today'|'tomorrow'|'this_week'|'later' => array of rows.
	 *     @type int        $total_upcoming_count Total upcoming (non-cancelled) appointments.
	 * }
	 */
	public static function patient_dashboard_data( $patient_id ) {
		$today = current_time( 'Y-m-d' );
		$now   = current_time( 'H:i' );

		$upcoming = array_values(
			array_filter(
				self::for_patient( $patient_id ),
				function ( $appointment ) use ( $today, $now ) {
					if ( self::STATUS_CANCELLED === $appointment['status'] ) {
						return false;
					}

					if ( $appointment['date'] > $today ) {
						return true;
					}

					return $appointment['date'] === $today && $appointment['time'] >= $now;
				}
			)
		);

		usort(
			$upcoming,
			function ( $a, $b ) {
				return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] );
			}
		);

		$groups = array(
			'today'     => array(),
			'tomorrow'  => array(),
			'this_week' => array(),
			'later'     => array(),
		);

		$tomorrow    = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );
		$week_cutoff = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );

		$unpaid_count = 0;
		$unpaid_total = 0.0;

		foreach ( $upcoming as $appointment ) {
			$row = self::patient_dashboard_row( $appointment, $today, $now );

			if ( $appointment['date'] === $today ) {
				$groups['today'][] = $row;
			} elseif ( $appointment['date'] === $tomorrow ) {
				$groups['tomorrow'][] = $row;
			} elseif ( $appointment['date'] <= $week_cutoff ) {
				$groups['this_week'][] = $row;
			} else {
				$groups['later'][] = $row;
			}

			if ( self::PAYMENT_STATUS_PAID !== $appointment['payment_status'] ) {
				++$unpaid_count;
				$unpaid_total += (float) $appointment['charge'];
			}
		}

		return array(
			'next_appointment'     => ! empty( $upcoming ) ? self::patient_dashboard_row( $upcoming[0], $today, $now ) : null,
			'unpaid_count'         => $unpaid_count,
			'unpaid_total'         => $unpaid_total,
			'groups'               => $groups,
			'total_upcoming_count' => count( $upcoming ),
		);
	}

	/**
	 * A patient's real payment history for the dashboard's "Payments" tab —
	 * every appointment they've actually paid for (via Swich, or marked paid
	 * by an admin), most recent appointment date first. There's no separate
	 * "paid at" timestamp anywhere in the system (Swich's callback only
	 * flips payment_status, see mark_paid()), so each entry is dated by its
	 * real appointment date/time rather than an invented payment date.
	 *
	 * @param int $patient_id Patient's user ID.
	 * @return array {
	 *     @type array $rows        List of enriched rows, see patient_dashboard_row().
	 *     @type float $total_paid  Sum of every row's charge.
	 * }
	 */
	public static function payment_history_for_patient( $patient_id ) {
		$today = current_time( 'Y-m-d' );
		$now   = current_time( 'H:i' );

		$paid = array_values(
			array_filter(
				self::for_patient( $patient_id ),
				function ( $appointment ) {
					return self::PAYMENT_STATUS_PAID === $appointment['payment_status'];
				}
			)
		);

		usort(
			$paid,
			function ( $a, $b ) {
				return strcmp( $b['date'] . $b['time'], $a['date'] . $a['time'] );
			}
		);

		$total_paid = 0.0;
		$rows       = array();

		foreach ( $paid as $appointment ) {
			$rows[]      = self::patient_dashboard_row( $appointment, $today, $now );
			$total_paid += (float) $appointment['charge'];
		}

		return array(
			'rows'       => $rows,
			'total_paid' => $total_paid,
		);
	}

	/**
	 * Whether a video appointment's "Join Call" link is usable right now,
	 * and the Jitsi Meet room URL to use — the room is only ever generated
	 * for video appointments (see create()), and joining requires the
	 * appointment to be paid, not cancelled, and within the join window
	 * around its scheduled start time.
	 *
	 * @param array $appointment Appointment array from get()/for_patient()/for_doctor().
	 * @return array {
	 *     @type bool   $applicable  Whether this is even a video appointment with a room.
	 *     @type bool   $can_join    Whether the link is clickable right now.
	 *     @type string $room_url    The Jitsi Meet URL, or '' if no room exists.
	 *     @type string $hint        Human label for why it's disabled, or when it opens.
	 * }
	 */
	public static function video_call_info( array $appointment ) {
		if ( self::TYPE_VIDEO !== $appointment['type'] ) {
			return array(
				'applicable' => false,
				'can_join'   => false,
				'room_url'   => '',
				'hint'       => '',
			);
		}

		$video_room = $appointment['video_room'];

		if ( '' === $video_room ) {
			// Only reachable for a video appointment that predates create()
			// generating a room (or was retargeted to video by an admin
			// edit afterwards) — generate one lazily so Join Call still works.
			$video_room = 'dak-' . wp_generate_password( 24, false, false );
			update_post_meta( $appointment['id'], 'doctor_ak_appointment_video_room', $video_room );
		}

		$room_url = 'https://meet.jit.si/' . rawurlencode( $video_room );

		if ( self::STATUS_CANCELLED === $appointment['status'] ) {
			return array(
				'applicable' => true,
				'can_join'   => false,
				'room_url'   => $room_url,
				'hint'       => __( 'Appointment cancelled', 'doctor-ak-portal' ),
			);
		}

		if ( self::STATUS_COMPLETED === $appointment['status'] ) {
			return array(
				'applicable' => true,
				'can_join'   => false,
				'room_url'   => $room_url,
				'hint'       => __( 'Appointment completed', 'doctor-ak-portal' ),
			);
		}

		if ( self::STATUS_CHECKED_IN === $appointment['status'] ) {
			return array(
				'applicable' => true,
				'can_join'   => false,
				'room_url'   => $room_url,
				'hint'       => __( 'Patient already checked in', 'doctor-ak-portal' ),
			);
		}

		if ( self::PAYMENT_STATUS_PAID !== $appointment['payment_status'] ) {
			return array(
				'applicable' => true,
				'can_join'   => false,
				'room_url'   => $room_url,
				'hint'       => __( 'Complete payment to get your call link', 'doctor-ak-portal' ),
			);
		}

		$start = strtotime( $appointment['date'] . ' ' . $appointment['time'] );

		if ( false === $start ) {
			return array(
				'applicable' => true,
				'can_join'   => false,
				'room_url'   => $room_url,
				'hint'       => '',
			);
		}

		$now       = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against a strtotime() of a stored local date/time string, not doing math that needs UTC.
		$opens_at  = $start - self::VIDEO_JOIN_WINDOW_BEFORE_MINUTES * MINUTE_IN_SECONDS;
		$closes_at = $start + self::VIDEO_JOIN_WINDOW_AFTER_MINUTES * MINUTE_IN_SECONDS;

		if ( $now < $opens_at ) {
			/* translators: %d: minutes before the appointment the call opens. */
			$hint = sprintf( __( 'Video consultation link will be available %d minutes before your appointment', 'doctor-ak-portal' ), self::VIDEO_JOIN_WINDOW_BEFORE_MINUTES );

			return array(
				'applicable' => true,
				'can_join'   => false,
				'room_url'   => $room_url,
				'hint'       => $hint,
			);
		}

		if ( $now > $closes_at ) {
			return array(
				'applicable' => true,
				'can_join'   => false,
				'room_url'   => $room_url,
				'hint'       => __( 'Call window has ended', 'doctor-ak-portal' ),
			);
		}

		return array(
			'applicable' => true,
			'can_join'   => true,
			'room_url'   => $room_url,
			'hint'       => '',
		);
	}

	/**
	 * Builds a single patient-dashboard row view-model from an appointment
	 * array — resolves the doctor's name/avatar/specialization and adds a
	 * human countdown label alongside the existing status/type labels.
	 *
	 * @param array  $appointment Appointment array from get().
	 * @param string $today       'YYYY-MM-DD', today per current_time().
	 * @param string $now         'HH:MM', now per current_time().
	 * @return array
	 */
	private static function patient_dashboard_row( array $appointment, $today, $now ) {
		$doctor      = $appointment['doctor_id'] > 0 ? get_userdata( $appointment['doctor_id'] ) : false;
		$doctor_name = '';

		if ( $doctor ) {
			$doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$doctor_name = '' !== $doctor_name ? $doctor_name : $doctor->display_name;
		}

		$specialization_label = '';

		if ( $doctor ) {
			$all_specializations  = Specializations::get_all();
			$specialization_slugs = (array) get_user_meta( $doctor->ID, 'doctor_ak_specializations', true );

			foreach ( $specialization_slugs as $slug ) {
				if ( isset( $all_specializations[ $slug ] ) ) {
					$specialization_label = $all_specializations[ $slug ];
					break;
				}
			}
		}

		$avatar_url = '';

		if ( $doctor ) {
			$picture_id = (int) get_user_meta( $doctor->ID, 'doctor_ak_profile_picture_id', true );

			if ( $picture_id > 0 ) {
				$url = wp_get_attachment_image_url( $picture_id, 'thumbnail' );

				if ( $url ) {
					$avatar_url = $url;
				}
			}
		}

		return array(
			'id'                    => $appointment['id'],
			'doctor_name'           => '' !== $doctor_name ? $doctor_name : __( 'Unknown Doctor', 'doctor-ak-portal' ),
			'doctor_avatar_url'     => $avatar_url,
			'doctor_specialization' => $specialization_label,
			'type'                  => $appointment['type'],
			'type_label'            => self::type_label( $appointment['type'] ),
			'date'                  => $appointment['date'],
			'time'                  => $appointment['time'],
			'datetime_label'        => self::datetime_label( $appointment['date'], $appointment['time'] ),
			'status'                => $appointment['status'],
			'status_label'          => self::status_label( $appointment['status'] ),
			'status_badge_class'    => self::status_badge_class( $appointment['status'] ),
			'payment_status'        => $appointment['payment_status'],
			'is_paid'               => self::PAYMENT_STATUS_PAID === $appointment['payment_status'],
			'payment_mode'          => $appointment['payment_mode'],
			'service_name'          => $appointment['service_name'],
			'charge'                => $appointment['charge'],
			'is_instant'            => $appointment['is_instant'],
			'surcharge'             => $appointment['surcharge'],
			'countdown_label'       => self::countdown_label( $appointment['date'], $appointment['time'], $today, $now ),
			'refund_eligible'       => Video_Pricing::is_cancellation_refund_eligible( $appointment['doctor_id'], $appointment['date'], $appointment['time'] ),
			'refund_status'         => $appointment['refund_status'],
			'video_call'            => self::video_call_info( $appointment ),
			'reschedulable'         => self::is_reschedulable( $appointment ),
		);
	}

	/**
	 * Everything the doctor dashboard needs about a doctor's upcoming
	 * appointments — every patient (or guest) who has booked them, grouped
	 * into Today/Tomorrow/This Week/Later, mirroring patient_dashboard_data()
	 * but scoped by doctor instead of by patient.
	 *
	 * @param int        $doctor_id    Doctor's user ID.
	 * @param array|null $appointments Optional pre-fetched self::for_doctor() result, so a caller that already has it doesn't trigger a second identical query.
	 * @return array {
	 *     @type array $groups               'today'|'tomorrow'|'this_week'|'later' => array of rows.
	 *     @type int   $total_upcoming_count Total upcoming (non-cancelled) appointments.
	 * }
	 */
	public static function doctor_dashboard_data( $doctor_id, $appointments = null ) {
		$today = current_time( 'Y-m-d' );
		$now   = current_time( 'H:i' );

		$upcoming = array_values(
			array_filter(
				null !== $appointments ? $appointments : self::for_doctor( $doctor_id ),
				function ( $appointment ) use ( $today, $now ) {
					if ( self::STATUS_CANCELLED === $appointment['status'] ) {
						return false;
					}

					if ( $appointment['date'] > $today ) {
						return true;
					}

					return $appointment['date'] === $today && $appointment['time'] >= $now;
				}
			)
		);

		usort(
			$upcoming,
			function ( $a, $b ) {
				return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] );
			}
		);

		$groups = array(
			'today'     => array(),
			'tomorrow'  => array(),
			'this_week' => array(),
			'later'     => array(),
		);

		$tomorrow    = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );
		$week_cutoff = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );

		foreach ( $upcoming as $appointment ) {
			$row = self::doctor_dashboard_row( $appointment, $today, $now );

			if ( $appointment['date'] === $today ) {
				$groups['today'][] = $row;
			} elseif ( $appointment['date'] === $tomorrow ) {
				$groups['tomorrow'][] = $row;
			} elseif ( $appointment['date'] <= $week_cutoff ) {
				$groups['this_week'][] = $row;
			} else {
				$groups['later'][] = $row;
			}
		}

		return array(
			'groups'               => $groups,
			'total_upcoming_count' => count( $upcoming ),
		);
	}

	/**
	 * Builds a single doctor-dashboard row view-model from an appointment
	 * array — resolves the patient's (or guest's) name/avatar instead of
	 * the doctor's, since this is the mirror image of patient_dashboard_row().
	 *
	 * @param array  $appointment Appointment array from get().
	 * @param string $today       'YYYY-MM-DD', today per current_time().
	 * @param string $now         'HH:MM', now per current_time().
	 * @return array
	 */
	private static function doctor_dashboard_row( array $appointment, $today, $now ) {
		$patient_name = self::patient_display_name_for( $appointment );
		$avatar_url   = '';

		if ( $appointment['patient_id'] > 0 ) {
			$picture_id = (int) get_user_meta( $appointment['patient_id'], 'doctor_ak_profile_picture_id', true );

			if ( $picture_id > 0 ) {
				$url = wp_get_attachment_image_url( $picture_id, 'thumbnail' );

				if ( $url ) {
					$avatar_url = $url;
				}
			}
		}

		return array(
			'id'                 => $appointment['id'],
			'patient_name'       => '' !== $patient_name ? $patient_name : __( 'Unknown Patient', 'doctor-ak-portal' ),
			'patient_avatar_url' => $avatar_url,
			'is_guest'           => 0 === (int) $appointment['patient_id'],
			'type'               => $appointment['type'],
			'type_label'         => self::type_label( $appointment['type'] ),
			'date'               => $appointment['date'],
			'time'               => $appointment['time'],
			'status'             => $appointment['status'],
			'status_label'       => self::status_label( $appointment['status'] ),
			'status_badge_class' => self::status_badge_class( $appointment['status'] ),
			'payment_status'     => $appointment['payment_status'],
			'is_paid'            => self::PAYMENT_STATUS_PAID === $appointment['payment_status'],
			'payment_mode'       => $appointment['payment_mode'],
			'service_name'       => $appointment['service_name'],
			'charge'             => $appointment['charge'],
			'countdown_label'    => self::countdown_label( $appointment['date'], $appointment['time'], $today, $now ),
			'video_call'         => self::video_call_info( $appointment ),
			'reschedulable'      => self::is_reschedulable( $appointment ),
		);
	}

	/**
	 * A human "In 2 hours" / "Tomorrow" / "In 3 days" label for an
	 * upcoming appointment's date/time relative to now.
	 *
	 * @param string $date  'YYYY-MM-DD'.
	 * @param string $time  'HH:MM'.
	 * @param string $today 'YYYY-MM-DD', today per current_time().
	 * @param string $now   'HH:MM', now per current_time().
	 * @return string
	 */
	private static function countdown_label( $date, $time, $today, $now ) {
		if ( $date === $today ) {
			$diff_minutes = self::time_to_minutes( $time ) - self::time_to_minutes( $now );

			if ( $diff_minutes <= 60 ) {
				return __( 'Soon', 'doctor-ak-portal' );
			}

			$hours = (int) round( $diff_minutes / 60 );

			/* translators: %d: number of hours. */
			return sprintf( _n( 'In %d hour', 'In %d hours', $hours, 'doctor-ak-portal' ), $hours );
		}

		$tomorrow = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );

		if ( $date === $tomorrow ) {
			return __( 'Tomorrow', 'doctor-ak-portal' );
		}

		$days = (int) round( ( strtotime( $date ) - strtotime( $today ) ) / DAY_IN_SECONDS );

		/* translators: %d: number of days. */
		return sprintf( _n( 'In %d day', 'In %d days', $days, 'doctor-ak-portal' ), $days );
	}

	/**
	 * Converts an 'HH:MM' time string to minutes since midnight.
	 *
	 * @param string $time 'HH:MM'.
	 * @return int
	 */
	private static function time_to_minutes( $time ) {
		$parts = array_map( 'intval', explode( ':', $time ) );

		return ( isset( $parts[0] ) ? $parts[0] * 60 : 0 ) + ( isset( $parts[1] ) ? $parts[1] : 0 );
	}

	/**
	 * Real recent-activity feed for the patient dashboard's "Recent
	 * Activity" card, derived from the patient's own appointment records
	 * (each post's real last-modified time, which changes exactly when its
	 * status/payment_status changes — e.g. mark_paid() or cancel()) rather
	 * than any fabricated notification content.
	 *
	 * @param int $patient_id Patient's user ID.
	 * @param int $limit      Max entries to return. Default 5.
	 * @return array List of `array( 'label' => string, 'date' => string )`, most recent first.
	 */
	public static function recent_activity_for_patient( $patient_id, $limit = 5 ) {
		$entries = array();

		foreach ( self::for_patient( $patient_id ) as $appointment ) {
			$post      = get_post( $appointment['id'] );
			$timestamp = $post ? strtotime( $post->post_modified ) : 0;

			$doctor      = $appointment['doctor_id'] > 0 ? get_userdata( $appointment['doctor_id'] ) : false;
			$doctor_name = '';

			if ( $doctor ) {
				$doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
				$doctor_name = '' !== $doctor_name ? $doctor_name : $doctor->display_name;
			}

			$doctor_name = '' !== $doctor_name ? $doctor_name : __( 'the doctor', 'doctor-ak-portal' );

			if ( self::STATUS_CANCELLED === $appointment['status'] ) {
				/* translators: %s: doctor's name. */
				$label = sprintf( __( 'Appointment with Dr. %s was cancelled', 'doctor-ak-portal' ), $doctor_name );
				$type  = 'cancelled';
			} elseif ( self::PAYMENT_STATUS_PAID === $appointment['payment_status'] ) {
				/* translators: %s: doctor's name. */
				$label = sprintf( __( 'Payment confirmed for your visit with Dr. %s', 'doctor-ak-portal' ), $doctor_name );
				$type  = 'paid';
			} else {
				/* translators: %s: doctor's name. */
				$label = sprintf( __( 'Appointment booked with Dr. %s', 'doctor-ak-portal' ), $doctor_name );
				$type  = 'booked';
			}

			$entries[] = array(
				'label'     => $label,
				'type'      => $type,
				'timestamp' => $timestamp,
				'date'      => $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp ) : '',
			);
		}

		usort(
			$entries,
			function ( $a, $b ) {
				return $b['timestamp'] - $a['timestamp'];
			}
		);

		return array_slice( $entries, 0, $limit );
	}

	/**
	 * Cancels an appointment on the patient's own behalf — used by the
	 * patient dashboard's "Cancel" action. Ownership-checked: only the
	 * patient who booked it can cancel it this way (admin cancellation
	 * would go through Appointments::update() instead).
	 *
	 * Refund eligibility is computed at the moment of cancellation, against
	 * the doctor's configured refund window (Video_Pricing::is_cancellation_refund_eligible()),
	 * and returned for the caller to relay to the patient — there is no
	 * automatic refund mechanism (the payment gateway has no refund API),
	 * so this is informational only, not a tracked status.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @param int $patient_id     Patient's user ID, must match the appointment's owner.
	 * @return array|false `array( 'refund_eligible' => bool )` on success, false if not found/not owned.
	 */
	public static function cancel( $appointment_id, $patient_id ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) || (int) $appointment['patient_id'] !== (int) $patient_id ) {
			return false;
		}

		return self::perform_cancel( $appointment );
	}

	/**
	 * Cancels an appointment on the treating doctor's behalf — same effect
	 * as cancel() above, just ownership-checked against doctor_id instead of
	 * patient_id (used by the doctor dashboard's own "Cancel" action).
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @param int $doctor_id      Doctor's user ID, must match the appointment's doctor.
	 * @return array|false `array( 'refund_eligible' => bool )` on success, false if not found/not owned.
	 */
	public static function cancel_by_doctor( $appointment_id, $doctor_id ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) || (int) $appointment['doctor_id'] !== (int) $doctor_id ) {
			return false;
		}

		return self::perform_cancel( $appointment );
	}

	/**
	 * Shared cancellation logic for cancel()/cancel_by_doctor() — the
	 * caller is responsible for the ownership check.
	 *
	 * @param array $appointment Decoded appointment array (see get()).
	 * @return array `array( 'refund_eligible' => bool )`.
	 */
	private static function perform_cancel( array $appointment ) {
		$refund_eligible = Video_Pricing::is_cancellation_refund_eligible( $appointment['doctor_id'], $appointment['date'], $appointment['time'] );

		update_post_meta( $appointment['id'], 'doctor_ak_appointment_status', self::STATUS_CANCELLED );

		/**
		 * Fires after an appointment is cancelled.
		 *
		 * @param int $appointment_id Cancelled appointment's post ID.
		 */
		do_action( 'doctor_ak_appointment_cancelled', $appointment['id'] );

		return array( 'refund_eligible' => $refund_eligible );
	}

	/**
	 * Admin-initiated cancellation with an immediate refund for a
	 * paid-online appointment — used when deactivating a doctor with
	 * upcoming appointments (see Admin_User_Handler::handle_toggle_status()).
	 * Unlike cancel()/cancel_by_doctor() (which only flag refund-eligibility
	 * for the patient to separately request), this processes the actual
	 * gateway refund right away, since the cancellation is the clinic's
	 * decision, not the patient's — there's no reason to make them ask for
	 * their money back separately. A manually-paid appointment can't be
	 * refunded through the gateway at all; the caller is told so it can
	 * flag it for the admin to handle offline.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return array|false `array( 'needs_manual_refund' => bool, 'refund_error' => string )` (both false/'' when there was nothing to refund or the refund succeeded), or false if the appointment doesn't exist or was already resolved (cancelled/completed).
	 */
	public static function cancel_by_admin( $appointment_id ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) || in_array( $appointment['status'], array( self::STATUS_CANCELLED, self::STATUS_COMPLETED ), true ) ) {
			return false;
		}

		update_post_meta( $appointment_id, 'doctor_ak_appointment_status', self::STATUS_CANCELLED );

		/** This action is documented in perform_cancel() above. */
		do_action( 'doctor_ak_appointment_cancelled', $appointment_id );

		$result = array(
			'needs_manual_refund' => false,
			'refund_error'        => '',
		);

		if ( self::PAYMENT_STATUS_PAID !== $appointment['payment_status'] ) {
			return $result;
		}

		if ( self::PAYMENT_MODE_ONLINE !== $appointment['payment_mode'] ) {
			$result['needs_manual_refund'] = true;
			return $result;
		}

		$order_id       = get_post_meta( $appointment_id, Swich_Payment::META_ORDER_ID, true );
		$refund_outcome = Swich_Payment::refund( $order_id, __( 'Doctor account deactivated — appointment cancelled by the clinic.', 'doctor-ak-portal' ), $appointment['charge'] );

		if ( is_wp_error( $refund_outcome ) ) {
			$result['refund_error'] = $refund_outcome->get_error_message();
			return $result;
		}

		self::mark_refund_processed( $appointment_id, $appointment['charge'] );

		return $result;
	}

	/**
	 * A doctor's upcoming, not-yet-resolved appointments — used to warn an
	 * admin before deactivating a doctor (see Admin_User_Handler::handle_toggle_status()).
	 * "Upcoming" means still on the calendar (confirmed, paid, rescheduled,
	 * or still awaiting payment) and not already in the past; already
	 * cancelled/completed appointments don't need anyone's attention.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array Rows from all_for_admin() (admin_row_data() shape).
	 */
	public static function upcoming_for_doctor( $doctor_id ) {
		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of stored local date/time strings, not doing math that needs UTC.

		return array_values(
			array_filter(
				self::all_for_admin( array( 'doctor_id' => $doctor_id ) ),
				function ( $row ) use ( $now ) {
					if ( ! in_array( $row['status'], array( self::STATUS_CONFIRMED, self::STATUS_PAID, self::STATUS_RESCHEDULED, self::STATUS_PENDING_PAYMENT ), true ) ) {
						return false;
					}

					$start = strtotime( $row['date'] . ' ' . $row['time'] );

					return false !== $start && $start > $now;
				}
			)
		);
	}

	/**
	 * Moves an appointment to a new date/time and flags it 'rescheduled' —
	 * used by the doctor dashboard, patient dashboard, and admin Edit
	 * modal's Reschedule actions. Ownership is the caller's responsibility
	 * (see Doctor_Appointment_Handler::handle_reschedule() and
	 * Patient_Appointment_Handler::handle_reschedule(), which check the
	 * appointment belongs to the requesting doctor/patient before calling
	 * this); admin reschedules go through this too but skip the ownership
	 * check entirely since the admin can already retarget the appointment.
	 *
	 * @param int    $appointment_id Appointment post ID.
	 * @param string $date           New date, 'YYYY-MM-DD'.
	 * @param string $time           New time, 'HH:MM'.
	 * @return true|\WP_Error
	 */
	public static function reschedule( $appointment_id, $date, $time ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) ) {
			return new \WP_Error( 'doctor_ak_invalid_appointment', __( 'That appointment could not be found.', 'doctor-ak-portal' ) );
		}

		if ( in_array( $appointment['status'], array( self::STATUS_CANCELLED, self::STATUS_COMPLETED ), true ) ) {
			return new \WP_Error( 'doctor_ak_appointment_not_reschedulable', __( 'This appointment can no longer be rescheduled.', 'doctor-ak-portal' ) );
		}

		$current_start = strtotime( $appointment['date'] . ' ' . $appointment['time'] );

		if ( false !== $current_start ) {
			$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of a stored local date/time string, not doing math that needs UTC.

			if ( $now > $current_start - self::RESCHEDULE_CUTOFF_MINUTES_BEFORE * MINUTE_IN_SECONDS ) {
				/* translators: %d: minutes before the appointment. */
				return new \WP_Error( 'doctor_ak_reschedule_cutoff', sprintf( __( 'This appointment can no longer be rescheduled — it starts in less than %d minutes (or has already started).', 'doctor-ak-portal' ), self::RESCHEDULE_CUTOFF_MINUTES_BEFORE ) );
			}
		}

		if ( ! self::is_valid_date( $date ) ) {
			return new \WP_Error( 'doctor_ak_invalid_date', __( 'Please choose a valid appointment date.', 'doctor-ak-portal' ) );
		}

		if ( ! self::is_valid_time( $time ) ) {
			return new \WP_Error( 'doctor_ak_invalid_time', __( 'Please choose a valid appointment time.', 'doctor-ak-portal' ) );
		}

		$new_start = strtotime( $date . ' ' . $time );

		if ( false !== $new_start && $new_start < current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of a posted local date/time string, not doing math that needs UTC.
			return new \WP_Error( 'doctor_ak_reschedule_in_past', __( 'Please choose a date and time in the future.', 'doctor-ak-portal' ) );
		}

		if ( self::is_slot_taken( $appointment['doctor_id'], $date, $time, $appointment_id ) ) {
			return new \WP_Error( 'doctor_ak_slot_taken', __( 'That time slot is already booked. Please choose another time.', 'doctor-ak-portal' ) );
		}

		update_post_meta( $appointment_id, 'doctor_ak_appointment_date', $date );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_time', $time );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_status', self::STATUS_RESCHEDULED );

		/**
		 * Fires after an appointment is rescheduled to a new date/time.
		 *
		 * @param int $appointment_id Rescheduled appointment's post ID.
		 */
		do_action( 'doctor_ak_appointment_rescheduled', $appointment_id );

		return true;
	}

	/**
	 * Whether an appointment is still within the reschedule window — used by
	 * dashboard templates to hide the "Reschedule" action once it would just
	 * fail server-side (see reschedule()'s own authoritative check).
	 *
	 * @param array $appointment Appointment array (must have 'status', 'date', 'time').
	 * @return bool
	 */
	public static function is_reschedulable( array $appointment ) {
		if ( in_array( $appointment['status'], array( self::STATUS_CANCELLED, self::STATUS_COMPLETED ), true ) ) {
			return false;
		}

		$start = strtotime( $appointment['date'] . ' ' . $appointment['time'] );

		if ( false === $start ) {
			return true;
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of a stored local date/time string, not doing math that needs UTC.

		return $now <= $start - self::RESCHEDULE_CUTOFF_MINUTES_BEFORE * MINUTE_IN_SECONDS;
	}

	/**
	 * Records a patient's refund request on a cancelled, paid, online
	 * appointment — used by the patient dashboard's "Request Refund" action.
	 * Doesn't touch the payment gateway itself; an admin reviews the request
	 * and triggers the actual Swich refund from the admin Appointments
	 * section (see Appointment_Handler::handle_admin_process_refund() and
	 * mark_refund_processed() below). Ownership-checked: only the patient who
	 * paid can request it, and only once per appointment.
	 *
	 * @param int    $appointment_id Appointment post ID.
	 * @param int    $patient_id     Patient's user ID, must match the appointment's owner.
	 * @param string $reason         Patient's refund reason (max REFUND_REASON_MAX_LENGTH chars).
	 * @return true|\WP_Error
	 */
	public static function mark_refund_requested( $appointment_id, $patient_id, $reason ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) || (int) $appointment['patient_id'] !== (int) $patient_id ) {
			return new \WP_Error( 'doctor_ak_invalid_appointment', __( 'That appointment could not be found.', 'doctor-ak-portal' ) );
		}

		if ( self::STATUS_CANCELLED !== $appointment['status'] ) {
			return new \WP_Error( 'doctor_ak_refund_not_cancelled', __( 'Only a cancelled appointment can be refunded.', 'doctor-ak-portal' ) );
		}

		if ( self::PAYMENT_STATUS_PAID !== $appointment['payment_status'] || self::PAYMENT_MODE_ONLINE !== $appointment['payment_mode'] ) {
			return new \WP_Error( 'doctor_ak_refund_not_paid_online', __( 'Only an online payment can be refunded this way. Please contact us for a manual payment.', 'doctor-ak-portal' ) );
		}

		if ( '' !== $appointment['refund_status'] ) {
			return new \WP_Error( 'doctor_ak_refund_already_requested', __( 'A refund has already been requested for this appointment.', 'doctor-ak-portal' ) );
		}

		$reason = trim( (string) $reason );

		if ( '' === $reason ) {
			return new \WP_Error( 'doctor_ak_refund_reason_required', __( 'Please tell us why you\'re requesting a refund.', 'doctor-ak-portal' ) );
		}

		if ( mb_strlen( $reason ) > self::REFUND_REASON_MAX_LENGTH ) {
			/* translators: %d: maximum character count. */
			return new \WP_Error( 'doctor_ak_refund_reason_too_long', sprintf( __( 'Please keep the reason under %d characters.', 'doctor-ak-portal' ), self::REFUND_REASON_MAX_LENGTH ) );
		}

		update_post_meta( $appointment_id, 'doctor_ak_appointment_refund_status', self::REFUND_STATUS_REQUESTED );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_refund_reason', $reason );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_refund_amount', $appointment['charge'] );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_refund_requested_at', current_time( 'mysql' ) );

		/**
		 * Fires after a patient requests a refund — Notifications/
		 * Notification_Center hook here to email/portal-notify every admin.
		 *
		 * @param int $appointment_id Appointment post ID.
		 */
		do_action( 'doctor_ak_appointment_refund_requested', $appointment_id );

		return true;
	}

	/**
	 * Records that an admin has successfully processed a refund through
	 * Swich's refund API — called only after that API call actually
	 * succeeds (see Appointment_Handler::handle_admin_process_refund()).
	 *
	 * @param int   $appointment_id Appointment post ID.
	 * @param float $amount         Amount actually refunded (may be a partial refund).
	 * @return void
	 */
	public static function mark_refund_processed( $appointment_id, $amount ) {
		update_post_meta( $appointment_id, 'doctor_ak_appointment_refund_status', self::REFUND_STATUS_PROCESSED );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_refund_amount', (float) $amount );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_refund_processed_at', current_time( 'mysql' ) );

		/**
		 * Fires after an admin successfully processes a refund.
		 *
		 * @param int $appointment_id Appointment post ID.
		 */
		do_action( 'doctor_ak_appointment_refund_processed', $appointment_id );
	}

	/**
	 * Reads a single appointment's meta into a plain array. Public wrapper
	 * around get() for callers outside this class (e.g. Swich_Payment).
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return array Empty array if the ID isn't a valid appointment.
	 */
	public static function find( $appointment_id ) {
		return self::get( $appointment_id );
	}

	/**
	 * Everything a notification email needs about an appointment: the same
	 * enriched shape as admin_row_data() (doctor/patient names, type/status
	 * labels, date/time, service/charge) plus a resolved email address for
	 * each side — the guest email when there's no account, otherwise the
	 * WP user's email, same fallback Swich_Payment::build_payment_url() uses.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return array Empty array if the ID isn't a valid appointment.
	 */
	public static function notification_data( $appointment_id ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) ) {
			return array();
		}

		$row = self::admin_row_data( $appointment );

		$row['patient_email'] = '' !== $appointment['guest_email']
			? $appointment['guest_email']
			: ( $appointment['patient_id'] > 0 && get_userdata( $appointment['patient_id'] ) ? get_userdata( $appointment['patient_id'] )->user_email : '' );

		return $row;
	}

	/**
	 * Upcoming appointments on a given date that haven't had a reminder
	 * email sent yet, for the daily reminder cron job.
	 *
	 * @param string $date 'YYYY-MM-DD'.
	 * @return array List of decoded appointment arrays (see get()).
	 */
	public static function due_for_reminder( $date ) {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, non-public post type; no better lookup available.
					array(
						'key'   => 'doctor_ak_appointment_date',
						'value' => $date,
					),
					array(
						'key'     => 'doctor_ak_appointment_reminder_sent',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$appointments = array_map( array( __CLASS__, 'get' ), wp_list_pluck( $query->posts, 'ID' ) );

		return array_values(
			array_filter(
				$appointments,
				function ( $appointment ) {
					return ! in_array( $appointment['status'], array( self::STATUS_CANCELLED, self::STATUS_COMPLETED ), true );
				}
			)
		);
	}

	/**
	 * Marks an appointment as having had its reminder email sent, so the
	 * hourly reminder cron job never emails it twice.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function mark_reminder_sent( $appointment_id ) {
		update_post_meta( $appointment_id, 'doctor_ak_appointment_reminder_sent', 1 );
	}

	/**
	 * Saves the admin's clinical note for a completed appointment — the
	 * "Encounters" section's visit log (see Admin_Dashboard's 'encounters'
	 * section). Deliberately just a plain text note, not a full clinical
	 * record (no diagnosis/prescription/vitals fields).
	 *
	 * @param int    $appointment_id Appointment post ID.
	 * @param string $note           Already-sanitized note text.
	 * @return true|\WP_Error
	 */
	public static function save_encounter_note( $appointment_id, $note ) {
		$post = get_post( $appointment_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'doctor_ak_invalid_appointment', __( 'That appointment no longer exists.', 'doctor-ak-portal' ) );
		}

		update_post_meta( $appointment_id, 'doctor_ak_appointment_encounter_notes', $note );

		return true;
	}

	/**
	 * Saves a doctor's own clinical note for one of their own completed
	 * appointments — the doctor dashboard's equivalent of
	 * save_encounter_note(), ownership- and status-checked so a doctor can
	 * only annotate visits that were actually theirs and actually happened
	 * (mirrors mark_completed()'s status list, since a note only makes sense
	 * once the visit is done).
	 *
	 * @param int    $appointment_id Appointment post ID.
	 * @param int    $doctor_id      Doctor's user ID, must match the appointment's doctor.
	 * @param string $note           Already-sanitized note text.
	 * @return true|\WP_Error
	 */
	public static function save_encounter_note_by_doctor( $appointment_id, $doctor_id, $note ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) || (int) $appointment['doctor_id'] !== (int) $doctor_id ) {
			return new \WP_Error( 'doctor_ak_invalid_appointment', __( 'That appointment no longer exists.', 'doctor-ak-portal' ) );
		}

		if ( self::STATUS_COMPLETED !== $appointment['status'] ) {
			return new \WP_Error( 'doctor_ak_appointment_not_completed', __( 'You can only add a visit note once the appointment is marked completed.', 'doctor-ak-portal' ) );
		}

		return self::save_encounter_note( $appointment_id, $note );
	}

	/**
	 * Paid, upcoming video appointments whose join window (see
	 * VIDEO_JOIN_WINDOW_BEFORE_MINUTES) has just opened but haven't had
	 * their "your video link is ready" email sent yet — for the video-link
	 * cron job. Matches on today's date plus a small look-ahead window
	 * rather than an exact minute, since the cron only runs every few
	 * minutes.
	 *
	 * @return array List of decoded appointment arrays (see get()).
	 */
	public static function due_for_video_link_email() {
		$today = current_time( 'Y-m-d' );

		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, non-public post type; no better lookup available.
					array(
						'key'   => 'doctor_ak_appointment_date',
						'value' => $today,
					),
					array(
						'key'   => 'doctor_ak_appointment_type',
						'value' => self::TYPE_VIDEO,
					),
					array(
						'key'   => 'doctor_ak_appointment_payment_status',
						'value' => self::PAYMENT_STATUS_PAID,
					),
					array(
						'key'     => 'doctor_ak_appointment_video_link_sent',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$appointments = array_map( array( __CLASS__, 'get' ), wp_list_pluck( $query->posts, 'ID' ) );
		$now          = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against a strtotime() of a stored local date/time string, not doing math that needs UTC.

		return array_values(
			array_filter(
				$appointments,
				function ( $appointment ) use ( $now ) {
					if ( in_array( $appointment['status'], array( self::STATUS_CANCELLED, self::STATUS_COMPLETED ), true ) ) {
						return false;
					}

					$start = strtotime( $appointment['date'] . ' ' . $appointment['time'] );

					if ( false === $start ) {
						return false;
					}

					$opens_at = $start - self::VIDEO_JOIN_WINDOW_BEFORE_MINUTES * MINUTE_IN_SECONDS;

					// The join window has opened, and the appointment hasn't
					// started yet — once it's started there's no more "get
					// ready" value in emailing the link.
					return $now >= $opens_at && $now < $start;
				}
			)
		);
	}

	/**
	 * Marks an appointment as having had its "your video link is ready"
	 * email sent, so the video-link cron job never emails it twice.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function mark_video_link_sent( $appointment_id ) {
		update_post_meta( $appointment_id, 'doctor_ak_appointment_video_link_sent', 1 );
	}

	/**
	 * Marks an appointment as paid and confirmed. Called once a Swich
	 * PayIn transaction for the appointment succeeds (via callback, or the
	 * return-page inquiry fallback).
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function mark_paid( $appointment_id ) {
		update_post_meta( $appointment_id, 'doctor_ak_appointment_payment_status', self::PAYMENT_STATUS_PAID );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_status', self::STATUS_PAID );

		/**
		 * Fires after an appointment is marked paid (Swich callback or return-page fallback).
		 *
		 * @param int $appointment_id Paid appointment's post ID.
		 */
		do_action( 'doctor_ak_appointment_paid', $appointment_id );
	}

	/**
	 * Marks an appointment as completed on the doctor's own behalf — used by
	 * the doctor dashboard's "Mark as Completed" action. Ownership-checked
	 * (only the doctor it belongs to can complete it this way; admin
	 * completion would go through Appointments::update() instead), and only
	 * allowed from 'confirmed' (booked), 'paid', or 'rescheduled' — an
	 * already-pending-payment-status or already-cancelled appointment was
	 * never actually seen, so there's nothing to complete. A chargeable
	 * appointment whose payment is still pending can't be completed either —
	 * "completed" is meant to imply the visit happened and was paid for, and
	 * a doctor accidentally completing (and thus losing track of) an unpaid
	 * visit was a real gap; a free ($0) appointment has nothing to collect,
	 * so it's exempt from this check.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @param int $doctor_id      Doctor's user ID, must match the appointment's doctor.
	 * @return true|\WP_Error
	 */
	public static function mark_completed( $appointment_id, $doctor_id ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) || (int) $appointment['doctor_id'] !== (int) $doctor_id ) {
			return new \WP_Error( 'doctor_ak_appointment_not_completable', __( 'That appointment could not be found or marked completed.', 'doctor-ak-portal' ) );
		}

		if ( self::STATUS_CHECKED_IN === $appointment['status'] ) {
			return new \WP_Error( 'doctor_ak_appointment_encounter_open', __( 'This patient is checked in with an open encounter — close the encounter first.', 'doctor-ak-portal' ) );
		}

		if ( ! in_array( $appointment['status'], array( self::STATUS_CONFIRMED, self::STATUS_PAID, self::STATUS_RESCHEDULED ), true ) ) {
			return new \WP_Error( 'doctor_ak_appointment_not_completable', __( 'That appointment could not be found or marked completed.', 'doctor-ak-portal' ) );
		}

		if ( (float) $appointment['charge'] > 0 && self::PAYMENT_STATUS_PAID !== $appointment['payment_status'] ) {
			return new \WP_Error( 'doctor_ak_appointment_payment_pending', __( 'This appointment still has a pending payment — mark it paid before completing it.', 'doctor-ak-portal' ) );
		}

		update_post_meta( $appointment_id, 'doctor_ak_appointment_status', self::STATUS_COMPLETED );

		/**
		 * Fires after an appointment is marked completed (manually or automatically).
		 *
		 * @param int $appointment_id Completed appointment's post ID.
		 */
		do_action( 'doctor_ak_appointment_completed', $appointment_id );

		return true;
	}

	/**
	 * Checks a patient in for their appointment — opens a clinical Encounter
	 * against it (see Encounters::check_in(), which calls this). Callable by
	 * the treating doctor OR admin/receptionist (authorization is enforced
	 * by the caller, e.g. Encounter_Handler — this method itself doesn't
	 * ownership-check, matching mark_paid()'s pattern of trusting the
	 * caller). Only allowed from the same "booked" states mark_completed()
	 * accepts ('confirmed'/'paid'/'rescheduled'), only once payment is
	 * settled (or the appointment is free), and only while the appointment
	 * hasn't already passed — checking in a patient for a slot that's
	 * already over doesn't make sense; use Reschedule instead. Once checked
	 * in, the old "Mark Completed" shortcut stops applying to this
	 * appointment (its status no longer matches mark_completed()'s allowed
	 * set) — Close Encounter (see checkout()) becomes the only way to
	 * finish the visit.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @param int $clinic_id      Clinic this visit is happening at (0 if none/not applicable — e.g. a video-only doctor). Only backfilled onto the appointment if it didn't already have one.
	 * @return true|\WP_Error
	 */
	public static function check_in( $appointment_id, $clinic_id ) {
		$appointment = self::get( $appointment_id );

		if ( empty( $appointment ) || ! in_array( $appointment['status'], array( self::STATUS_CONFIRMED, self::STATUS_PAID, self::STATUS_RESCHEDULED ), true ) ) {
			return new \WP_Error( 'doctor_ak_appointment_not_checkinable', __( 'That appointment could not be found or checked in.', 'doctor-ak-portal' ) );
		}

		if ( (float) $appointment['charge'] > 0 && self::PAYMENT_STATUS_PAID !== $appointment['payment_status'] ) {
			return new \WP_Error( 'doctor_ak_appointment_payment_pending', __( 'This appointment still has a pending payment — mark it paid before checking the patient in.', 'doctor-ak-portal' ) );
		}

		$start = strtotime( $appointment['date'] . ' ' . $appointment['time'] );

		if ( false !== $start && $start < current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of a stored local date/time string, not doing math that needs UTC.
			return new \WP_Error( 'doctor_ak_appointment_overdue', __( "This appointment's time has already passed — reschedule it before checking the patient in.", 'doctor-ak-portal' ) );
		}

		update_post_meta( $appointment_id, 'doctor_ak_appointment_status', self::STATUS_CHECKED_IN );

		if ( $clinic_id > 0 && 0 === (int) $appointment['clinic_id'] ) {
			update_post_meta( $appointment_id, 'doctor_ak_appointment_clinic_id', $clinic_id );
		}

		/**
		 * Fires after a patient is checked in.
		 *
		 * @param int $appointment_id Checked-in appointment's post ID.
		 */
		do_action( 'doctor_ak_appointment_checked_in', $appointment_id );

		return true;
	}

	/**
	 * Checks a patient out — the counterpart to check_in(), called only from
	 * Encounters::close() once a doctor closes the clinical encounter. Marks
	 * the appointment completed via the same status/action as
	 * mark_completed(), so anything already listening for
	 * 'doctor_ak_appointment_completed' (e.g. the invoice email) still runs.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function checkout( $appointment_id ) {
		update_post_meta( $appointment_id, 'doctor_ak_appointment_status', self::STATUS_COMPLETED );

		/** This action is documented in mark_completed() above. */
		do_action( 'doctor_ak_appointment_completed', $appointment_id );
	}

	/**
	 * Auto-completes booked (confirmed) appointments once enough time has
	 * passed since their scheduled start that the visit is assumed over —
	 * a safety net for doctors who don't manually mark_completed(). Run
	 * hourly from Notifications::CRON_HOOK (see class-plugin.php).
	 *
	 * @return void
	 */
	public static function auto_complete_past_appointments() {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, non-public post type; no better lookup available.
					array(
						'key'     => 'doctor_ak_appointment_status',
						'value'   => array( self::STATUS_CONFIRMED, self::STATUS_PAID, self::STATUS_RESCHEDULED ),
						'compare' => 'IN',
					),
				),
			)
		);

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of stored local date/time strings, not doing math that needs UTC.

		foreach ( wp_list_pluck( $query->posts, 'ID' ) as $appointment_id ) {
			$appointment = self::get( $appointment_id );
			$start       = strtotime( $appointment['date'] . ' ' . $appointment['time'] );

			if ( false === $start ) {
				continue;
			}

			if ( $now <= $start + self::AUTO_COMPLETE_HOURS_AFTER * HOUR_IN_SECONDS ) {
				continue;
			}

			// Same rule as mark_completed(): a chargeable appointment with a
			// still-pending payment never auto-completes — it stays
			// 'confirmed' until the payment clears (or a doctor completes it
			// manually once it's paid), so "completed" keeps meaning "paid".
			if ( (float) $appointment['charge'] > 0 && self::PAYMENT_STATUS_PAID !== $appointment['payment_status'] ) {
				continue;
			}

			update_post_meta( $appointment_id, 'doctor_ak_appointment_status', self::STATUS_COMPLETED );

			/** This action is documented in mark_completed() above. */
			do_action( 'doctor_ak_appointment_completed', $appointment_id );
		}
	}

	/**
	 * Doctors available to book, for populating a <select>.
	 *
	 * @return array User ID => display name.
	 */
	public static function doctor_options() {
		$query = new \WP_User_Query(
			array(
				'role'    => Roles::DOCTOR_ROLE,
				'orderby' => 'display_name',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$options = array();

		foreach ( $query->get_results() as $doctor ) {
			$options[ $doctor->ID ] = $doctor->display_name;
		}

		return $options;
	}

	/**
	 * User IDs of every doctor whose account isn't deactivated — the
	 * patient-facing subset of doctor_options()'s full doctor-role list
	 * (which intentionally still includes deactivated doctors for
	 * admin-only pickers). Used to build any patient-facing per-doctor data
	 * map, e.g. Booking_Page's service/pricing/booking-rules lookups, so a
	 * deactivated doctor's data isn't reachable there either.
	 *
	 * @return int[]
	 */
	public static function active_doctor_ids() {
		$query = new \WP_User_Query(
			array(
				'role'       => Roles::DOCTOR_ROLE,
				'fields'     => 'ID',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no better lookup available; excludes deactivated doctors, same pattern as Booking_Page::doctor_cards_data().
					'relation' => 'OR',
					array(
						'key'     => 'doctor_ak_account_disabled',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'doctor_ak_account_disabled',
						'value'   => 'yes',
						'compare' => '!=',
					),
				),
			)
		);

		return array_map( 'intval', $query->get_results() );
	}

	/**
	 * Whether a user is a doctor whose account isn't deactivated — the
	 * check every booking-related AJAX endpoint should run before acting on
	 * a client-supplied doctor_id, so a deactivated doctor can't still
	 * receive new bookings or leak availability/pricing via a direct
	 * request that bypasses the booking page's own (already-filtered)
	 * doctor picker.
	 *
	 * @param int $doctor_id User ID to check.
	 * @return bool
	 */
	public static function is_active_doctor( $doctor_id ) {
		if ( $doctor_id <= 0 ) {
			return false;
		}

		$doctor = get_userdata( $doctor_id );

		return $doctor
			&& in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true )
			&& 'yes' !== get_user_meta( $doctor_id, 'doctor_ak_account_disabled', true );
	}

	/**
	 * Registered patients, for the admin Appointments modal's patient picker.
	 *
	 * @return array User ID => display name.
	 */
	public static function patient_options() {
		$query = new \WP_User_Query(
			array(
				'role'    => Roles::PATIENT_ROLE,
				'orderby' => 'display_name',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$options = array();

		foreach ( $query->get_results() as $patient ) {
			$options[ $patient->ID ] = $patient->display_name;
		}

		return $options;
	}

	/**
	 * Total number of appointments ever booked, for the admin dashboard's
	 * "Total Appointments" stat card.
	 *
	 * @return int
	 */
	public static function total_count() {
		$counts = wp_count_posts( self::POST_TYPE );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * Every appointment across every doctor and patient, most recent first,
	 * as flat view-model rows ready for the admin "Appointments" table.
	 *
	 * Also used, with a `doctor_id` and/or `patient_id` filter, to back the
	 * doctor and patient dashboards' own "Appointments" tabs — the same flat
	 * row shape (see admin_row_data()) works for all three audiences.
	 *
	 * @param array $filters {
	 *     Optional. All filters are ANDed together; omit or pass '' / 0 to skip one.
	 *
	 *     @type int    $patient_id     Only this patient's appointments.
	 *     @type int    $doctor_id      Only this doctor's appointments.
	 *     @type string $date           'YYYY-MM-DD', only appointments on this date.
	 *     @type string $date_from      'YYYY-MM-DD', only appointments on/after this date.
	 *     @type string $date_to        'YYYY-MM-DD', only appointments on/before this date.
	 *     @type string $status         One of the STATUS_* constants.
	 *     @type string $payment_status One of the PAYMENT_STATUS_* constants.
	 *     @type string $payment_mode   One of the PAYMENT_MODE_* constants.
	 * }
	 * @return array
	 */
	public static function all_for_admin( array $filters = array() ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => ! empty( $filters['number'] ) ? (int) $filters['number'] : 200,
			'no_found_rows'  => true,
		);

		$meta_query = array();

		if ( ! empty( $filters['patient_id'] ) ) {
			$meta_query[] = array(
				'key'   => 'doctor_ak_appointment_patient_id',
				'value' => (int) $filters['patient_id'],
			);
		}

		if ( ! empty( $filters['doctor_id'] ) ) {
			$meta_query[] = array(
				'key'   => 'doctor_ak_appointment_doctor_id',
				'value' => (int) $filters['doctor_id'],
			);
		}

		if ( ! empty( $filters['date'] ) ) {
			$meta_query[] = array(
				'key'   => 'doctor_ak_appointment_date',
				'value' => sanitize_text_field( $filters['date'] ),
			);
		}

		if ( ! empty( $filters['date_from'] ) && ! empty( $filters['date_to'] ) ) {
			$meta_query[] = array(
				'key'     => 'doctor_ak_appointment_date',
				'value'   => array( sanitize_text_field( $filters['date_from'] ), sanitize_text_field( $filters['date_to'] ) ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			);
		} else {
			if ( ! empty( $filters['date_from'] ) ) {
				$meta_query[] = array(
					'key'     => 'doctor_ak_appointment_date',
					'value'   => sanitize_text_field( $filters['date_from'] ),
					'compare' => '>=',
					'type'    => 'DATE',
				);
			}

			if ( ! empty( $filters['date_to'] ) ) {
				$meta_query[] = array(
					'key'     => 'doctor_ak_appointment_date',
					'value'   => sanitize_text_field( $filters['date_to'] ),
					'compare' => '<=',
					'type'    => 'DATE',
				);
			}
		}

		if ( ! empty( $filters['status'] ) ) {
			$meta_query[] = array(
				'key'   => 'doctor_ak_appointment_status',
				'value' => sanitize_text_field( $filters['status'] ),
			);
		}

		if ( ! empty( $filters['payment_status'] ) ) {
			$meta_query[] = array(
				'key'   => 'doctor_ak_appointment_payment_status',
				'value' => sanitize_text_field( $filters['payment_status'] ),
			);
		}

		if ( ! empty( $filters['payment_mode'] ) ) {
			$meta_query[] = array(
				'key'   => 'doctor_ak_appointment_payment_mode',
				'value' => sanitize_text_field( $filters['payment_mode'] ),
			);
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, non-public post type; no better lookup available.
		}

		$query = new \WP_Query( $args );

		$appointments = array_map( array( __CLASS__, 'get' ), wp_list_pluck( $query->posts, 'ID' ) );

		usort(
			$appointments,
			function ( $a, $b ) {
				return strcmp( $b['date'] . $b['time'], $a['date'] . $a['time'] );
			}
		);

		return array_map( array( __CLASS__, 'admin_row_data' ), $appointments );
	}

	/**
	 * Revenue stat cards for the admin "Billing" section and Dashboard
	 * overview: all-time, this month, and today — the CLINIC'S/PLATFORM'S
	 * own share of each paid appointment (see Revenue_Split), not the full
	 * amount the patient paid. A commission-based doctor keeps the rest; a
	 * salary-based doctor's appointments count fully as clinic revenue,
	 * since they're paid separately from what they collect. A payment
	 * counts on the day the *appointment* falls on, not the day it was
	 * paid — matching how the Billing table itself groups invoices by
	 * appointment date.
	 *
	 * @return array {
	 *     @type float total              Hospital/platform share, all-time.
	 *     @type float this_month         Hospital/platform share, this calendar month.
	 *     @type float today              Hospital/platform share, today.
	 *     @type int   invoice_count      Paid appointments, all-time.
	 *     @type float gross_total        Full charge sum (before any split), all-time, for reference.
	 *     @type float doctor_share_total Sum of every doctor's own share, all-time, for reference.
	 * }
	 */
	public static function revenue_summary() {
		$all_paid = self::all_for_admin( array( 'payment_status' => self::PAYMENT_STATUS_PAID ) );
		$today    = current_time( 'Y-m-d' );
		$month    = gmdate( 'Y-m', strtotime( $today ) );

		$total              = 0.0;
		$this_month         = 0.0;
		$today_total        = 0.0;
		$gross_total        = 0.0;
		$doctor_share_total = 0.0;

		foreach ( $all_paid as $row ) {
			$split           = Revenue_Split::split( $row['doctor_id'], $row['charge'] );
			$hospital_share  = $split['hospital_share'];
			$gross_total    += (float) $row['charge'];
			$doctor_share_total += $split['doctor_share'];
			$total          += $hospital_share;

			if ( 0 === strpos( $row['date'], $month ) ) {
				$this_month += $hospital_share;
			}

			if ( $row['date'] === $today ) {
				$today_total += $hospital_share;
			}
		}

		return array(
			'total'              => $total,
			'this_month'         => $this_month,
			'today'              => $today_total,
			'invoice_count'      => count( $all_paid ),
			'gross_total'        => $gross_total,
			'doctor_share_total' => $doctor_share_total,
		);
	}

	/**
	 * Daily HOSPITAL/PLATFORM-share revenue (see Revenue_Split, and
	 * revenue_summary()'s docblock) for the last $days days (today
	 * inclusive) — for the admin Dashboard overview's revenue trend chart.
	 * Every day in the range is present (0 where nothing was paid that
	 * day), so the chart never has to interpolate a gap.
	 *
	 * @param int $days How many days back to include (today counts as one).
	 * @return array List of `array( 'date', 'label', 'total' )`, oldest first.
	 */
	public static function revenue_by_day( $days = 14 ) {
		$today = current_time( 'Y-m-d' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- building a local-date range, not doing UTC math.
		$from  = gmdate( 'Y-m-d', strtotime( $today . " -{$days} days" ) );

		$paid = self::all_for_admin(
			array(
				'date_from'      => $from,
				'date_to'        => $today,
				'payment_status' => self::PAYMENT_STATUS_PAID,
			)
		);

		$totals = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$totals[ gmdate( 'Y-m-d', strtotime( $today . " -{$i} days" ) ) ] = 0.0;
		}

		foreach ( $paid as $row ) {
			if ( isset( $totals[ $row['date'] ] ) ) {
				$totals[ $row['date'] ] += Revenue_Split::split( $row['doctor_id'], $row['charge'] )['hospital_share'];
			}
		}

		$rows = array();

		foreach ( $totals as $date => $total ) {
			$rows[] = array(
				'date'  => $date,
				'label' => date_i18n( 'M j', strtotime( $date ) ),
				'total' => $total,
			);
		}

		return $rows;
	}

	/**
	 * Revenue stat cards for a single doctor's own "Earnings" tab — their
	 * own share (see Revenue_Split) of every one of their paid
	 * appointments: all-time, this month, and today. Mirrors
	 * revenue_summary()'s shape/date logic, scoped to one doctor and
	 * reporting the doctor's share instead of the hospital's.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array { @type float total, @type float this_month, @type float today, @type int invoice_count, @type array current_split (see Revenue_Split::get_for_doctor()) }
	 */
	public static function doctor_revenue_summary( $doctor_id ) {
		$all_paid = self::all_for_admin(
			array(
				'doctor_id'      => $doctor_id,
				'payment_status' => self::PAYMENT_STATUS_PAID,
			)
		);
		$today    = current_time( 'Y-m-d' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of stored local date/time strings, not doing math that needs UTC.
		$month    = gmdate( 'Y-m', strtotime( $today ) );

		$total       = 0.0;
		$this_month  = 0.0;
		$today_total = 0.0;

		foreach ( $all_paid as $row ) {
			$doctor_share = Revenue_Split::split( $doctor_id, $row['charge'] )['doctor_share'];
			$total       += $doctor_share;

			if ( 0 === strpos( $row['date'], $month ) ) {
				$this_month += $doctor_share;
			}

			if ( $row['date'] === $today ) {
				$today_total += $doctor_share;
			}
		}

		return array(
			'total'         => $total,
			'this_month'    => $this_month,
			'today'         => $today_total,
			'invoice_count' => count( $all_paid ),
			'current_split' => Revenue_Split::get_for_doctor( $doctor_id ),
		);
	}

	/**
	 * Net settlement for a single doctor — "who owes whom" — mirroring how
	 * a clinic actually reconciles a doctor's account: for a CASH
	 * appointment (payment_mode = manual) the doctor collected the
	 * patient's payment directly, so they owe the clinic its share; for an
	 * ONLINE appointment the clinic collected the payment through the
	 * gateway, so it owes the doctor their share back. The two
	 * owe-directions net into one figure rather than settling as two
	 * separate transfers.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array {
	 *     @type float owed_by_doctor Hospital's share of this doctor's PAID, cash-collected (manual) appointments — what the doctor owes the clinic.
	 *     @type float owed_to_doctor Doctor's share of this doctor's PAID, platform-collected (online) appointments — what the clinic owes the doctor.
	 *     @type float net            owed_to_doctor minus owed_by_doctor. Negative means the doctor owes the clinic; positive means the clinic owes the doctor.
	 * }
	 */
	public static function net_dues_for_doctor( $doctor_id ) {
		$paid = self::all_for_admin(
			array(
				'doctor_id'      => $doctor_id,
				'payment_status' => self::PAYMENT_STATUS_PAID,
			)
		);

		$owed_by_doctor = 0.0;
		$owed_to_doctor = 0.0;

		foreach ( $paid as $row ) {
			$split = Revenue_Split::split( $doctor_id, $row['charge'] );

			if ( self::PAYMENT_MODE_ONLINE === $row['payment_mode'] ) {
				$owed_to_doctor += $split['doctor_share'];
			} else {
				$owed_by_doctor += $split['hospital_share'];
			}
		}

		return array(
			'owed_by_doctor' => $owed_by_doctor,
			'owed_to_doctor' => $owed_to_doctor,
			'net'            => $owed_to_doctor - $owed_by_doctor,
		);
	}

	/**
	 * net_dues_for_doctor() for every active doctor — for the admin
	 * Billing section's settlement table. Sorted so the doctors with the
	 * largest amount owed (in either direction) surface first.
	 *
	 * @return array List of `array( 'doctor_id', 'doctor_name', 'owed_by_doctor', 'owed_to_doctor', 'net' )`.
	 */
	public static function net_dues_by_doctor() {
		$rows = array();

		foreach ( self::active_doctor_ids() as $doctor_id ) {
			$doctor = get_userdata( $doctor_id );
			$name   = $doctor ? trim( $doctor->first_name . ' ' . $doctor->last_name ) : '';
			$name   = '' !== $name ? $name : ( $doctor ? $doctor->display_name : __( 'Unknown Doctor', 'doctor-ak-portal' ) );

			$rows[] = array_merge(
				array(
					'doctor_id'   => $doctor_id,
					'doctor_name' => $name,
				),
				self::net_dues_for_doctor( $doctor_id )
			);
		}

		usort(
			$rows,
			function ( $a, $b ) {
				return abs( $b['net'] ) <=> abs( $a['net'] );
			}
		);

		return $rows;
	}

	/**
	 * Default number of buckets shown per period on the admin Dashboard
	 * overview's "Appointments" clustered bar chart, when the caller doesn't
	 * ask for a specific count.
	 *
	 * @var array
	 */
	const CHART_PERIOD_DEFAULT_BUCKETS = array(
		'day'   => 7,
		'week'  => 8,
		'month' => 6,
	);

	/**
	 * Appointment counts per status, bucketed by day/week/month — for the
	 * admin Dashboard overview's "Appointments" clustered bar chart (one
	 * group of bars per bucket, one bar per status). Every bucket in the
	 * range is present and every status appears in every bucket (0 where
	 * unused), so the chart never has to interpolate a gap.
	 *
	 * @param string $period               'day', 'week', or 'month'. Defaults to 'day' if anything else is passed.
	 * @param int    $bucket_count         How many buckets back to include (today/this week/this month counts as one). 0 uses CHART_PERIOD_DEFAULT_BUCKETS.
	 * @param array  $clinic_location_ids  Optional — restrict to appointments at these Clinic_Locations rows (a video consultation, clinic_location_id 0, is only counted if 0 is included). Empty array = no restriction (every clinic, matching the "unassigned receptionist" convention elsewhere).
	 * @return array List of `array( 'key', 'label', 'counts' => array( status_slug => int ) )`, oldest bucket first.
	 */
	public static function status_counts_by_period( $period = 'day', $bucket_count = 0, array $clinic_location_ids = array() ) {
		$period       = in_array( $period, array( 'day', 'week', 'month' ), true ) ? $period : 'day';
		$bucket_count = $bucket_count > 0 ? $bucket_count : self::CHART_PERIOD_DEFAULT_BUCKETS[ $period ];

		$today   = current_time( 'Y-m-d' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- building a local-date range, not doing UTC math.
		$buckets = array();

		for ( $i = $bucket_count - 1; $i >= 0; $i-- ) {
			if ( 'month' === $period ) {
				$timestamp = strtotime( "{$today} -{$i} months" );
				$key       = gmdate( 'Y-m', $timestamp );
				$label     = date_i18n( 'M Y', $timestamp );
			} elseif ( 'week' === $period ) {
				$day_of_week   = (int) gmdate( 'N', strtotime( $today ) ); // 1 (Mon) - 7 (Sun).
				$current_monday = strtotime( "{$today} -" . ( $day_of_week - 1 ) . ' days' );
				$timestamp      = strtotime( "-{$i} weeks", $current_monday );
				$key            = gmdate( 'Y-m-d', $timestamp );
				$label          = date_i18n( 'M j', $timestamp );
			} else {
				$timestamp = strtotime( "{$today} -{$i} days" );
				$key       = gmdate( 'Y-m-d', $timestamp );
				$label     = date_i18n( 'M j', $timestamp );
			}

			$buckets[ $key ] = $label;
		}

		$bucket_keys = array_keys( $buckets );
		$first_key   = reset( $bucket_keys );
		$date_from   = 'month' === $period ? $first_key . '-01' : $first_key;

		$statuses = array_keys( self::status_options() );
		$counts   = array();

		foreach ( $buckets as $key => $label ) {
			$counts[ $key ] = array_fill_keys( $statuses, 0 );
		}

		foreach ( self::all_for_admin( array( 'date_from' => $date_from, 'date_to' => $today ) ) as $row ) {
			if ( ! empty( $clinic_location_ids ) ) {
				$clinic_location_id = 0;

				if ( ! empty( $row['clinic_id'] ) ) {
					$clinic              = Clinics::find( $row['clinic_id'] );
					$clinic_location_id = $clinic ? (int) $clinic['clinic_location_id'] : 0;
				}

				if ( ! in_array( $clinic_location_id, $clinic_location_ids, true ) ) {
					continue;
				}
			}

			if ( 'month' === $period ) {
				$bucket_key = gmdate( 'Y-m', strtotime( $row['date'] ) );
			} elseif ( 'week' === $period ) {
				$row_timestamp = strtotime( $row['date'] );
				$row_day       = (int) gmdate( 'N', $row_timestamp );
				$bucket_key    = gmdate( 'Y-m-d', strtotime( $row['date'] . ' -' . ( $row_day - 1 ) . ' days' ) );
			} else {
				$bucket_key = $row['date'];
			}

			if ( isset( $counts[ $bucket_key ][ $row['status'] ] ) ) {
				++$counts[ $bucket_key ][ $row['status'] ];
			}
		}

		$rows = array();

		foreach ( $buckets as $key => $label ) {
			$rows[] = array(
				'key'    => $key,
				'label'  => $label,
				'counts' => $counts[ $key ],
			);
		}

		return $rows;
	}

	/**
	 * Builds a single admin table row from an appointment array — resolves
	 * the doctor's name and adds human-readable labels for type/status, a
	 * 'd/m/Y h:i A'-formatted 'datetime_label', and 'is_overdue' (the
	 * appointment's date/time has already passed and it was never resolved
	 * — i.e. not cancelled, completed, or already checked in) for templates
	 * that restrict a lapsed appointment's actions to Reschedule only. A
	 * checked-in appointment is excluded even if its time has technically
	 * passed: the patient already showed up, so "time passed"/Reschedule
	 * don't apply — there's nothing left to reschedule.
	 *
	 * @param array $appointment Appointment array from get().
	 * @return array
	 */
	private static function admin_row_data( array $appointment ) {
		$doctor      = $appointment['doctor_id'] > 0 ? get_userdata( $appointment['doctor_id'] ) : false;
		$doctor_name = '';

		if ( $doctor ) {
			$doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$doctor_name = '' !== $doctor_name ? $doctor_name : $doctor->display_name;
		}

		$patient_name = self::patient_display_name_for( $appointment );

		$clinic_label = '';

		if ( $appointment['clinic_id'] > 0 ) {
			$clinic = Clinics::find( $appointment['clinic_id'] );

			if ( $clinic ) {
				$clinic_label = $clinic['name'];
				$address_line = implode( ', ', array_filter( array( $clinic['address'], $clinic['area_label'], $clinic['city_label'] ) ) );

				if ( '' !== $address_line ) {
					$clinic_label .= ' — ' . $address_line;
				}
			}
		}

		$start_timestamp = strtotime( $appointment['date'] . ' ' . $appointment['time'] );
		$is_overdue       = false !== $start_timestamp
			&& $start_timestamp < current_time( 'timestamp' ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against strtotime() of a stored local date/time string, not doing math that needs UTC.
			&& ! in_array( $appointment['status'], array( self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_CHECKED_IN ), true );

		return array(
			'id'                => $appointment['id'],
			'patient_id'        => $appointment['patient_id'],
			'patient_name'      => $patient_name,
			'patient_initials'  => self::initials( $patient_name ),
			'patient_avatar_url' => $appointment['patient_id'] > 0 ? self::avatar_url_for_user( $appointment['patient_id'] ) : '',
			'guest_name'        => $appointment['guest_name'],
			'guest_email'       => $appointment['guest_email'],
			'guest_phone'       => $appointment['guest_phone'],
			'doctor_id'         => $appointment['doctor_id'],
			'doctor_name'       => '' !== $doctor_name ? $doctor_name : __( 'Unknown Doctor', 'doctor-ak-portal' ),
			'doctor_email'      => $doctor ? $doctor->user_email : '',
			'doctor_avatar_url' => $doctor ? self::avatar_url_for_user( $doctor->ID ) : '',
			'type'              => $appointment['type'],
			'type_label'        => self::type_label( $appointment['type'] ),
			'clinic_id'         => $appointment['clinic_id'],
			'clinic_label'      => $clinic_label,
			'date'              => $appointment['date'],
			'time'              => $appointment['time'],
			'datetime_label'    => false !== $start_timestamp ? date_i18n( 'd/m/Y h:i A', $start_timestamp ) : trim( $appointment['date'] . ' ' . $appointment['time'] ),
			'is_overdue'        => $is_overdue,
			'status'            => $appointment['status'],
			'status_label'      => self::status_label( $appointment['status'] ),
			'status_badge_class' => self::status_badge_class( $appointment['status'] ),
			'payment_status'    => $appointment['payment_status'],
			'is_paid'           => self::PAYMENT_STATUS_PAID === $appointment['payment_status'],
			'payment_mode'      => $appointment['payment_mode'],
			'service_id'        => $appointment['service_id'],
			'service_name'      => $appointment['service_name'],
			'charge'            => $appointment['charge'],
			'base_charge'       => $appointment['base_charge'],
			'discount_percent'  => $appointment['discount_percent'],
			'notes'             => $appointment['notes'],
			'encounter_notes'   => $appointment['encounter_notes'],
			'is_instant'        => $appointment['is_instant'],
			'surcharge'         => $appointment['surcharge'],
			'video_call'        => self::video_call_info( $appointment ),
			'refund_status'     => $appointment['refund_status'],
			'refund_reason'     => $appointment['refund_reason'],
			'refund_amount'     => $appointment['refund_amount'],
			'reschedulable'     => self::is_reschedulable( $appointment ),
		);
	}

	/**
	 * Resolves a user's (doctor or patient) uploaded profile picture, or ''
	 * if none set. Shared helper for every row-builder that shows an avatar.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function avatar_url_for_user( $user_id ) {
		$picture_id = (int) get_user_meta( $user_id, 'doctor_ak_profile_picture_id', true );

		if ( $picture_id > 0 ) {
			$url = wp_get_attachment_image_url( $picture_id, 'thumbnail' );

			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Valid "range" quick-filter values for an Appointments list — '' means
	 * "All", shared by the admin, doctor, and patient dashboards' filter
	 * forms. 'upcoming' is each list's own default on first load (no
	 * `range` present in the URL/request at all) — see each dashboard
	 * controller's own read of $_GET['range'].
	 *
	 * @return array slug => label.
	 */
	public static function range_options() {
		return array(
			''         => __( 'All', 'doctor-ak-portal' ),
			'upcoming' => __( 'Upcoming', 'doctor-ak-portal' ),
			'past'     => __( 'Past', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Combines the All/Upcoming/Past quick filter with any manually-entered
	 * date range: 'upcoming' raises $date_from to today (never lowers an
	 * already-later manual date_from); 'past' lowers $date_to to yesterday
	 * (never raises an already-earlier manual date_to); '' (All) leaves
	 * both untouched. Shared by the admin, doctor, and patient Appointments
	 * lists so "today" is computed identically everywhere.
	 *
	 * @param string $range     '', 'upcoming', or 'past' — see range_options().
	 * @param string $date_from Manually-entered 'YYYY-MM-DD', or ''.
	 * @param string $date_to   Manually-entered 'YYYY-MM-DD', or ''.
	 * @return array `array( $date_from, $date_to )`, either possibly adjusted.
	 */
	public static function apply_range_filter( $range, $date_from, $date_to ) {
		$today_timestamp = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- deriving today's local date for the Upcoming/Past quick filter, not doing UTC math.

		if ( 'upcoming' === $range ) {
			$today = date_i18n( 'Y-m-d', $today_timestamp );

			if ( '' === $date_from || $date_from < $today ) {
				$date_from = $today;
			}
		} elseif ( 'past' === $range ) {
			$yesterday = date_i18n( 'Y-m-d', $today_timestamp - DAY_IN_SECONDS );

			if ( '' === $date_to || $date_to > $yesterday ) {
				$date_to = $yesterday;
			}
		}

		return array( $date_from, $date_to );
	}

	/**
	 * Every recognised appointment status, slug => label, for the admin
	 * Edit modal's status <select> and for validating update() input.
	 *
	 * @return array
	 */
	public static function status_options() {
		return array(
			self::STATUS_CONFIRMED       => __( 'Booked', 'doctor-ak-portal' ),
			self::STATUS_PENDING_PAYMENT => __( 'Pending Payment', 'doctor-ak-portal' ),
			self::STATUS_PAID            => __( 'Paid', 'doctor-ak-portal' ),
			self::STATUS_CHECKED_IN      => __( 'Checked In', 'doctor-ak-portal' ),
			self::STATUS_CANCELLED       => __( 'Cancelled', 'doctor-ak-portal' ),
			self::STATUS_COMPLETED       => __( 'Completed', 'doctor-ak-portal' ),
			self::STATUS_RESCHEDULED     => __( 'Rescheduled', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Every recognised payment mode, slug => label, for the admin
	 * Appointments table's filter <select>.
	 *
	 * @return array
	 */
	public static function payment_mode_options() {
		return array(
			self::PAYMENT_MODE_MANUAL => __( 'Manual', 'doctor-ak-portal' ),
			self::PAYMENT_MODE_ONLINE => __( 'Online', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Human-readable label for an appointment status.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @return string
	 */
	private static function status_label( $status ) {
		$options = self::status_options();

		return isset( $options[ $status ] ) ? $options[ $status ] : $status;
	}

	/**
	 * The `dak-status-badge` modifier class for an appointment status.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @return string
	 */
	private static function status_badge_class( $status ) {
		if ( self::STATUS_CANCELLED === $status ) {
			return 'is-disabled';
		}

		if ( self::STATUS_CONFIRMED === $status || self::STATUS_COMPLETED === $status || self::STATUS_PAID === $status ) {
			return 'is-active';
		}

		if ( self::STATUS_RESCHEDULED === $status ) {
			return 'is-rescheduled';
		}

		if ( self::STATUS_CHECKED_IN === $status ) {
			return 'is-checked-in';
		}

		return 'is-pending';
	}

	/**
	 * An appointment's date+time as a single 'd/m/Y h:i A' string (e.g.
	 * "10/08/2026 04:20 PM") — the one format used everywhere an
	 * appointment's date/time is displayed across the admin, doctor, and
	 * patient dashboards.
	 *
	 * @param string $date 'YYYY-MM-DD'.
	 * @param string $time 'HH:MM'.
	 * @return string
	 */
	private static function datetime_label( $date, $time ) {
		$timestamp = strtotime( $date . ' ' . $time );

		return false !== $timestamp ? date_i18n( 'd/m/Y h:i A', $timestamp ) : trim( $date . ' ' . $time );
	}

	/**
	 * One or two uppercase initials from a display name, for the admin
	 * table's avatar circle.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	private static function initials( $name ) {
		$words    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			if ( '' !== $word ) {
				$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
			}
		}

		return '' !== $initials ? $initials : '?';
	}

	/**
	 * Renders the patient dashboard's upcoming-appointments card.
	 *
	 * @param \WP_User $user Currently viewed patient.
	 * @return void
	 */
	public static function render_patient_dashboard_appointments( \WP_User $user ) {
		$today    = current_time( 'Y-m-d' );
		$upcoming = array_filter(
			self::for_patient( $user->ID ),
			function ( $appointment ) use ( $today ) {
				return $appointment['date'] >= $today && self::STATUS_CANCELLED !== $appointment['status'];
			}
		);

		if ( empty( $upcoming ) ) {
			return;
		}

		$template_loader = new Template_Loader();

		foreach ( array_slice( $upcoming, 0, 5 ) as $appointment ) {
			$doctor = get_userdata( $appointment['doctor_id'] );

			echo $template_loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
				'dashboard/partials/appointment-item.php',
				array(
					'name'           => $doctor ? sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $doctor->display_name ) : __( 'Doctor', 'doctor-ak-portal' ),
					'note'           => self::type_label( $appointment['type'] ),
					'type'           => $appointment['type'],
					'date'           => $appointment['date'],
					'time'           => $appointment['time'],
					'payment_status' => $appointment['payment_status'],
					'video_call'     => self::video_call_info( $appointment ),
				)
			);
		}
	}

	/**
	 * Renders the doctor dashboard's "Upcoming Today" card.
	 *
	 * @param \WP_User $user Currently viewed doctor.
	 * @return void
	 */
	public static function render_doctor_dashboard_appointments( \WP_User $user ) {
		$today        = current_time( 'Y-m-d' );
		$appointments = array_filter(
			self::for_doctor( $user->ID ),
			function ( $appointment ) use ( $today ) {
				return $appointment['date'] === $today && self::STATUS_CANCELLED !== $appointment['status'];
			}
		);

		if ( empty( $appointments ) ) {
			return;
		}

		$template_loader = new Template_Loader();

		foreach ( $appointments as $appointment ) {
			echo $template_loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
				'dashboard/partials/appointment-item.php',
				array(
					'name'           => self::patient_display_name_for( $appointment ),
					'note'           => self::type_label( $appointment['type'] ),
					'type'           => $appointment['type'],
					'date'           => $appointment['date'],
					'time'           => $appointment['time'],
					'payment_status' => $appointment['payment_status'],
					'video_call'     => self::video_call_info( $appointment ),
				)
			);
		}
	}

	/**
	 * Renders the doctor dashboard's "Recent Patients" card — one row per
	 * distinct patient/guest, most recently booked first.
	 *
	 * @param \WP_User $user Currently viewed doctor.
	 * @return void
	 */
	public static function render_doctor_dashboard_recent_patients( \WP_User $user ) {
		$appointments = self::for_doctor( $user->ID );

		usort(
			$appointments,
			function ( $a, $b ) {
				return strcmp( $b['date'] . $b['time'], $a['date'] . $a['time'] );
			}
		);

		$seen = array();
		$rows = array();

		foreach ( $appointments as $appointment ) {
			$key = $appointment['patient_id'] > 0 ? 'u' . $appointment['patient_id'] : 'g' . strtolower( $appointment['guest_email'] );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$rows[]       = $appointment;

			if ( count( $rows ) >= 5 ) {
				break;
			}
		}

		if ( empty( $rows ) ) {
			return;
		}

		$template_loader = new Template_Loader();

		foreach ( $rows as $appointment ) {
			echo $template_loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
				'dashboard/partials/recent-patient-item.php',
				array(
					'name'       => self::patient_display_name_for( $appointment ),
					'last_visit' => $appointment['date'],
				)
			);
		}
	}

	/**
	 * Filter callback: today's appointment count for the doctor dashboard's stat card.
	 *
	 * @param int      $count Default count (0).
	 * @param \WP_User $user  Currently viewed doctor.
	 * @return int
	 */
	public static function filter_today_appointments_count( $count, \WP_User $user ) {
		$today = current_time( 'Y-m-d' );

		return count(
			array_filter(
				self::for_doctor( $user->ID ),
				function ( $appointment ) use ( $today ) {
					return $appointment['date'] === $today && self::STATUS_CANCELLED !== $appointment['status'];
				}
			)
		);
	}

	/**
	 * Filter callback: total video-consult count for the doctor dashboard's stat card.
	 *
	 * @param int      $count Default count (0).
	 * @param \WP_User $user  Currently viewed doctor.
	 * @return int
	 */
	public static function filter_video_consults_count( $count, \WP_User $user ) {
		return count(
			array_filter(
				self::for_doctor( $user->ID ),
				function ( $appointment ) {
					return self::TYPE_VIDEO === $appointment['type'] && self::STATUS_CANCELLED !== $appointment['status'];
				}
			)
		);
	}

	/**
	 * Reads a single appointment's meta into a plain array.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return array Empty array if the ID isn't a valid appointment.
	 */
	private static function get( $appointment_id ) {
		$post = get_post( $appointment_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return array();
		}

		return array(
			'id'             => $post->ID,
			'doctor_id'      => (int) get_post_meta( $post->ID, 'doctor_ak_appointment_doctor_id', true ),
			'patient_id'     => (int) get_post_meta( $post->ID, 'doctor_ak_appointment_patient_id', true ),
			'guest_name'     => get_post_meta( $post->ID, 'doctor_ak_appointment_guest_name', true ),
			'guest_email'    => get_post_meta( $post->ID, 'doctor_ak_appointment_guest_email', true ),
			'guest_phone'    => get_post_meta( $post->ID, 'doctor_ak_appointment_guest_phone', true ),
			'type'           => get_post_meta( $post->ID, 'doctor_ak_appointment_type', true ),
			'clinic_id'      => (int) get_post_meta( $post->ID, 'doctor_ak_appointment_clinic_id', true ),
			'date'           => get_post_meta( $post->ID, 'doctor_ak_appointment_date', true ),
			'time'           => get_post_meta( $post->ID, 'doctor_ak_appointment_time', true ),
			'status'         => get_post_meta( $post->ID, 'doctor_ak_appointment_status', true ),
			'payment_status' => get_post_meta( $post->ID, 'doctor_ak_appointment_payment_status', true ),
			'notes'          => get_post_meta( $post->ID, 'doctor_ak_appointment_notes', true ),
			'encounter_notes' => get_post_meta( $post->ID, 'doctor_ak_appointment_encounter_notes', true ),
			'service_id'     => (int) get_post_meta( $post->ID, 'doctor_ak_appointment_service_id', true ),
			'service_ids'    => (function() use ( $post ) {
				$raw = json_decode( (string) get_post_meta( $post->ID, 'doctor_ak_appointment_service_ids', true ), true );
				return is_array( $raw ) ? array_map( 'absint', $raw ) : array();
			})(),
			'service_name'   => get_post_meta( $post->ID, 'doctor_ak_appointment_service_name', true ),
			'charge'            => (float) get_post_meta( $post->ID, 'doctor_ak_appointment_charge', true ),
			'base_charge'       => (float) get_post_meta( $post->ID, 'doctor_ak_appointment_base_charge', true ),
			'discount_percent'  => (int) get_post_meta( $post->ID, 'doctor_ak_appointment_discount_percent', true ),
			'payment_mode'   => get_post_meta( $post->ID, 'doctor_ak_appointment_payment_mode', true ),
			'is_instant'     => (bool) get_post_meta( $post->ID, 'doctor_ak_appointment_is_instant', true ),
			'surcharge'      => (float) get_post_meta( $post->ID, 'doctor_ak_appointment_surcharge', true ),
			'video_room'     => get_post_meta( $post->ID, 'doctor_ak_appointment_video_room', true ),
			'refund_status'       => get_post_meta( $post->ID, 'doctor_ak_appointment_refund_status', true ),
			'refund_reason'       => get_post_meta( $post->ID, 'doctor_ak_appointment_refund_reason', true ),
			'refund_amount'       => (float) get_post_meta( $post->ID, 'doctor_ak_appointment_refund_amount', true ),
			'refund_requested_at' => get_post_meta( $post->ID, 'doctor_ak_appointment_refund_requested_at', true ),
			'refund_processed_at' => get_post_meta( $post->ID, 'doctor_ak_appointment_refund_processed_at', true ),
		);
	}

	/**
	 * Whether a doctor already has a paid (confirmed) appointment at the
	 * given date/time. Pending (unpaid) requests don't block the slot —
	 * only a paid booking locks it, so multiple pending requests for the
	 * same slot can exist until one of them is paid.
	 *
	 * @param int    $doctor_id Doctor's user ID.
	 * @param string $date      'YYYY-MM-DD'.
	 * @param string $time      'HH:MM'.
	 * @return bool
	 */
	private static function is_slot_taken( $doctor_id, $date, $time, $exclude_appointment_id = 0 ) {
		foreach ( self::for_doctor( $doctor_id ) as $appointment ) {
			if ( $appointment['date'] === $date
				&& $appointment['time'] === $time
				&& self::PAYMENT_STATUS_PAID === $appointment['payment_status']
				&& self::STATUS_CANCELLED !== $appointment['status']
				&& (int) $appointment['id'] !== (int) $exclude_appointment_id
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Queries appointments by a single meta field, returned as plain arrays.
	 *
	 * @param string $meta_key Meta key to match.
	 * @param int    $value    Meta value to match.
	 * @return array
	 */
	private static function query( $meta_key, $value ) {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'no_found_rows'  => true,
				'meta_key'       => $meta_key,
				'meta_value'     => $value,
			)
		);

		$appointments = array_map( array( __CLASS__, 'get' ), wp_list_pluck( $query->posts, 'ID' ) );

		usort(
			$appointments,
			function ( $a, $b ) {
				return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] );
			}
		);

		return $appointments;
	}

	/**
	 * Resolves a registered patient's display name.
	 *
	 * @param int $patient_id Patient's user ID.
	 * @return string
	 */
	private static function patient_display_name( $patient_id ) {
		$user = get_userdata( $patient_id );

		if ( ! $user ) {
			return '';
		}

		$name = trim( $user->first_name . ' ' . $user->last_name );

		return '' !== $name ? $name : $user->display_name;
	}

	/**
	 * Resolves the name to show for an appointment row — the registered
	 * patient's name, or the guest's manually-entered name.
	 *
	 * @param array $appointment Appointment array from get().
	 * @return string
	 */
	private static function patient_display_name_for( array $appointment ) {
		if ( $appointment['patient_id'] > 0 ) {
			return self::patient_display_name( $appointment['patient_id'] );
		}

		return '' !== $appointment['guest_name'] ? $appointment['guest_name'] : __( 'Guest', 'doctor-ak-portal' );
	}

	/**
	 * Human-readable label for an appointment type.
	 *
	 * @param string $type 'clinic' or 'video'.
	 * @return string
	 */
	private static function type_label( $type ) {
		return self::TYPE_VIDEO === $type ? __( 'Video Consultation', 'doctor-ak-portal' ) : __( 'Clinic Appointment', 'doctor-ak-portal' );
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
	 * Validates a time string in 24-hour HH:MM format.
	 *
	 * @param string $time Time string.
	 * @return bool
	 */
	private static function is_valid_time( $time ) {
		return (bool) preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time );
	}
}
