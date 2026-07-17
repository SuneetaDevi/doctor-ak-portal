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

	const STATUS_PENDING         = 'pending';
	const STATUS_PENDING_PAYMENT = 'pending_payment';
	const STATUS_CONFIRMED       = 'confirmed';
	const STATUS_CANCELLED       = 'cancelled';
	const STATUS_COMPLETED       = 'completed';

	const TYPE_CLINIC = 'clinic';
	const TYPE_VIDEO  = 'video';

	const PAYMENT_STATUS_PENDING = 'pending';
	const PAYMENT_STATUS_PAID    = 'paid';

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
	 * Validates and saves a new appointment.
	 *
	 * @param array $data {
	 *     @type int    $doctor_id   Doctor's user ID.
	 *     @type int    $patient_id  Logged-in patient's user ID, or 0 for a guest booking.
	 *     @type string $guest_name  Guest's name (ignored when patient_id is set).
	 *     @type string $guest_email Guest's email (ignored when patient_id is set).
	 *     @type string $guest_phone Guest's phone (ignored when patient_id is set).
	 *     @type string $type        'clinic' or 'video'.
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

		$requires_payment = apply_filters( 'doctor_ak_appointment_requires_payment', false, $data );
		$status           = $requires_payment ? self::STATUS_PENDING_PAYMENT : self::STATUS_PENDING;

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
		update_post_meta( $post_id, 'doctor_ak_appointment_date', $date );
		update_post_meta( $post_id, 'doctor_ak_appointment_time', $time );
		update_post_meta( $post_id, 'doctor_ak_appointment_status', $status );
		update_post_meta( $post_id, 'doctor_ak_appointment_payment_status', $payment_status );
		update_post_meta( $post_id, 'doctor_ak_appointment_notes', $notes );

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

		return $post_id;
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
	 * Appointments booked by a given (logged-in) patient, most recent last.
	 *
	 * @param int $patient_id Patient's user ID.
	 * @return array
	 */
	public static function for_patient( $patient_id ) {
		return self::query( 'doctor_ak_appointment_patient_id', $patient_id );
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
	 * Marks an appointment as paid and confirmed. Called once a Swich
	 * PayIn transaction for the appointment succeeds (via callback, or the
	 * return-page inquiry fallback).
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function mark_paid( $appointment_id ) {
		update_post_meta( $appointment_id, 'doctor_ak_appointment_payment_status', self::PAYMENT_STATUS_PAID );
		update_post_meta( $appointment_id, 'doctor_ak_appointment_status', self::STATUS_CONFIRMED );
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
			'date'           => get_post_meta( $post->ID, 'doctor_ak_appointment_date', true ),
			'time'           => get_post_meta( $post->ID, 'doctor_ak_appointment_time', true ),
			'status'         => get_post_meta( $post->ID, 'doctor_ak_appointment_status', true ),
			'payment_status' => get_post_meta( $post->ID, 'doctor_ak_appointment_payment_status', true ),
			'notes'          => get_post_meta( $post->ID, 'doctor_ak_appointment_notes', true ),
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
	private static function is_slot_taken( $doctor_id, $date, $time ) {
		foreach ( self::for_doctor( $doctor_id ) as $appointment ) {
			if ( $appointment['date'] === $date
				&& $appointment['time'] === $time
				&& self::PAYMENT_STATUS_PAID === $appointment['payment_status']
				&& self::STATUS_CANCELLED !== $appointment['status']
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
