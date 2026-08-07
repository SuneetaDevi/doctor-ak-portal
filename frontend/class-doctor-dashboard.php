<?php
/**
 * Backs the [doctor_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Notification_Center;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Services;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Template_Loader;
use DoctorAKPortal\Includes\Theme_Preference;
use DoctorAKPortal\Includes\Video_Pricing;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Dashboard
 *
 * Gates access (must be logged in and hold the Doctor role), then assembles
 * real, computed data — no fabricated statistics. Appointments and
 * notifications are rendered through action hooks so a future booking
 * module (KiviCare, WooCommerce, etc.) can populate them without touching
 * this class or its template.
 */
class Doctor_Dashboard {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'doctor_dashboard';

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader $template_loader Template loader.
	 */
	public function __construct( Template_Loader $template_loader ) {
		$this->template_loader = $template_loader;
	}

	/**
	 * Enqueues dashboard assets only on pages containing [doctor_dashboard].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-dashboard.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-dashboard.css' )
		);

		// The "Upcoming Appointments" card reuses the exact same row/status-pill
		// markup and classes as the patient dashboard's appointment list (see
		// dashboard/partials/doctor-appointment-row.php), so it needs this
		// stylesheet too even though it's not the patient-facing page.
		wp_enqueue_style(
			'doctor-ak-portal-patient-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-patient-dashboard.css',
			array( 'doctor-ak-portal-dashboard' ),
			Assets::version( 'assets/css/doctor-ak-patient-dashboard.css' )
		);

		// The Add/Edit Patient and Reschedule Appointment modals reuse the
		// same .dak-modal overlay/dialog styling as the public booking modal.
		wp_enqueue_style(
			'doctor-ak-portal-booking-modal',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-booking-modal.css',
			array( 'doctor-ak-portal-dashboard' ),
			Assets::version( 'assets/css/doctor-ak-booking-modal.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-dashboard.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-dashboard.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-doctor-appointments',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-doctor-appointments.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-doctor-appointments.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-live-filters',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-live-filters.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-live-filters.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-dashboard-search',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-dashboard-search.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-dashboard-search.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-doctor-appointments',
			'dakDoctorAppointments',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( Doctor_Appointment_Handler::NONCE_ACTION ),
				'confirmMessage'       => __( 'Mark this appointment as completed?', 'doctor-ak-portal' ),
				'confirmCancelMessage' => __( 'Cancel this appointment? This cannot be undone.', 'doctor-ak-portal' ),
				'genericError'         => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
			)
		);

		wp_enqueue_script(
			'doctor-ak-portal-doctor-encounters',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-doctor-encounters.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-doctor-encounters.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-doctor-encounters',
			'dakDoctorEncounters',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Doctor_Appointment_Handler::NONCE_ACTION ),
			)
		);

		wp_enqueue_script(
			'doctor-ak-portal-appointment-reschedule',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-appointment-reschedule.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-appointment-reschedule.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-appointment-reschedule',
			'dakAppointmentReschedule',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( Doctor_Appointment_Handler::NONCE_ACTION ),
				'action'   => 'doctor_ak_doctor_reschedule_appointment',
				'genericError' => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
			)
		);

		wp_enqueue_script(
			'doctor-ak-portal-notifications',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-notifications.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-notifications.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-notifications',
			'dakNotifications',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Notification_Handler::NONCE_ACTION ),
			)
		);

		// The Add/Edit Patient modal exists on the overview (default) tab
		// and the Patients tab (see doctor-dashboard.php / doctor-patients-tab.php).
		if ( in_array( self::requested_tab(), array( 'dashboard', 'patients' ), true ) ) {
			wp_enqueue_script(
				'doctor-ak-portal-doctor-add-patient',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-doctor-add-patient.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-doctor-add-patient.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-doctor-add-patient',
				'dakDoctorAddPatient',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( Doctor_Patient_Handler::NONCE_ACTION ),
				)
			);
		}

		// The Profile tab renders the same form as the standalone
		// [doctor_profile] page (see Profile_Handler), so it needs its
		// assets too — but only when that tab is actually showing. The
		// Clinics tab's own assets are enqueued independently by
		// Clinic_Handler::enqueue_assets().
		if ( 'profile' === self::requested_tab() ) {
			wp_enqueue_style(
				'doctor-ak-portal-registration',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
				array( 'doctor-ak-portal-auth' ),
				Assets::version( 'assets/css/doctor-ak-registration.css' )
			);

			wp_enqueue_style(
				'doctor-ak-portal-profile',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-profile.css',
				array( 'doctor-ak-portal-registration' ),
				Assets::version( 'assets/css/doctor-ak-profile.css' )
			);

			wp_enqueue_script(
				'doctor-ak-portal-registration',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-registration.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-registration.js' ),
				true
			);

			wp_enqueue_script(
				'doctor-ak-portal-profile',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-profile.js',
				array( 'doctor-ak-portal-registration' ),
				Assets::version( 'assets/js/doctor-ak-profile.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-profile',
				'dakProfile',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( Profile_Handler::NONCE_ACTION ),
				)
			);
		}
	}

	/**
	 * Reads the current 'tab' query var: 'dashboard' (default), 'profile',
	 * 'clinics', 'services', 'video-consultation', 'appointments' or 'settings'.
	 *
	 * @return string
	 */
	private static function requested_tab() {
		if ( ! isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			return 'dashboard';
		}

		$tab = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		if ( ! in_array( $tab, array( 'profile', 'clinics', 'services', 'video-consultation', 'appointments', 'patients', 'notifications', 'settings' ), true ) ) {
			return 'dashboard';
		}

		// An admin can turn a tab off entirely (Settings → Roles &
		// Permissions) — a direct/bookmarked link to it then falls back to
		// the Dashboard tab exactly like an unrecognized slug would.
		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, $tab ) ) {
			return 'dashboard';
		}

		return $tab;
	}

	/**
	 * A tab's nav URL, or '' if the dashboard URL isn't resolvable or an
	 * admin has turned that tab off for doctors (Settings → Roles &
	 * Permissions) — the template only renders a nav link when this is
	 * non-empty, so a disallowed tab's link simply doesn't appear.
	 *
	 * @param string $dashboard_url Base dashboard URL, or ''.
	 * @param string $tab           Tab slug.
	 * @return string
	 */
	private static function tab_url( $dashboard_url, $tab ) {
		if ( '' === $dashboard_url || ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, $tab ) ) {
			return '';
		}

		return add_query_arg( 'tab', $tab, $dashboard_url );
	}

	/**
	 * Renders the Notifications tab: every notification recorded for this
	 * doctor, via Notification_Center::for_user().
	 *
	 * @param \WP_User $user Currently logged-in doctor.
	 * @return string
	 */
	private function render_notifications_tab( \WP_User $user ) {
		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$selected_date = self::requested_appointments_date();

		return $this->template_loader->get_template(
			'dashboard/partials/notifications-list.php',
			array(
				'notification_groups' => Notification_Center::group_by_recency( Notification_Center::for_user( $user->ID, 100, $selected_date ) ),
				'appointments_url'    => self::tab_url( $dashboard_url, 'appointments' ),
				'selected_date'       => $selected_date,
				'filter_field_name'   => 'tab',
				'filter_field_value'  => 'notifications',
			)
		);
	}

	/**
	 * Reads the current 'date_from'/'date_to'/'payment_status' query vars for
	 * the Appointments tab's filter form.
	 *
	 * @return array { date_from: string, date_to: string, payment_status: string }
	 */
	private static function requested_appointments_filters() {
		$date_from      = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
		$date_to        = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
		$payment_status = isset( $_GET['payment_status'] ) ? sanitize_key( wp_unslash( $_GET['payment_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		$payment_status_options = array(
			Appointments::PAYMENT_STATUS_PENDING => __( 'Pending', 'doctor-ak-portal' ),
			Appointments::PAYMENT_STATUS_PAID    => __( 'Paid', 'doctor-ak-portal' ),
		);

		return array(
			'date_from'      => $date_from,
			'date_to'        => $date_to,
			'payment_status' => array_key_exists( $payment_status, $payment_status_options ) ? $payment_status : '',
		);
	}

	/**
	 * Reads the current 'date' query var for the Appointments tab's filter.
	 *
	 * @return string
	 */
	private static function requested_appointments_date() {
		if ( ! isset( $_GET['date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			return '';
		}

		return sanitize_text_field( wp_unslash( $_GET['date'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
	}

	/**
	 * Renders the Appointments tab: every appointment this doctor has ever
	 * had, filterable by date range and payment status.
	 *
	 * @param \WP_User $user Currently logged-in doctor.
	 * @return string
	 */
	private function render_appointments_tab( \WP_User $user ) {
		$filters           = self::requested_appointments_filters();
		$dashboard_url     = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$appointments_url  = self::tab_url( $dashboard_url, 'appointments' );

		return $this->template_loader->get_template(
			'dashboard/partials/doctor-appointments-list.php',
			array(
				'rows' => Appointments::all_for_admin(
					array_merge( array( 'doctor_id' => $user->ID ), $filters )
				),
				'appointments_url' => $appointments_url,
				'filters'          => $filters,
			)
		);
	}

	/**
	 * Renders the shortcode: an access-denied state, or the full dashboard.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return $this->template_loader->get_template(
				'dashboard/access-denied.php',
				array(
					'reason'    => 'logged_out',
					'login_url' => Page_Finder::url_for_shortcode( 'doctor_login' ),
				)
			);
		}

		$user = wp_get_current_user();

		if ( ! in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			return $this->template_loader->get_template(
				'dashboard/access-denied.php',
				array(
					'reason'        => 'wrong_role',
					'dashboard_url' => Page_Finder::url_for_shortcode( 'patient_dashboard' ),
				)
			);
		}

		return $this->template_loader->get_template( 'dashboard/doctor-dashboard.php', $this->prepare_data( $user ) );
	}

	/**
	 * AJAX: the topbar search box — up to 5 matches each of this doctor's
	 * own Patients and Appointments whose name contains the query, for a
	 * click-to-jump dropdown (no "Doctors" category — a doctor only
	 * searches their own data, never other doctors). Patients link to
	 * `#dak-patient-{id}` in the Patients tab; Appointments link to
	 * `#dak-appointment-{id}` in the Appointments tab — both anchors reuse
	 * the existing `:target` highlight already built for notification
	 * deep-links.
	 *
	 * @return void
	 */
	public function handle_search() {
		if ( ! check_ajax_referer( Doctor_Appointment_Handler::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$user = wp_get_current_user();

		if ( ! in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

		if ( mb_strlen( $query ) < 2 ) {
			wp_send_json_success(
				array(
					'patients'     => array(),
					'appointments' => array(),
				)
			);
		}

		$needle            = mb_strtolower( $query );
		$dashboard_url     = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$patients_url      = self::tab_url( $dashboard_url, 'patients' );
		$appointments_url  = self::tab_url( $dashboard_url, 'appointments' );

		$patient_results = array();

		foreach ( Appointments::patients_for_doctor( $user->ID ) as $patient ) {
			$haystack = mb_strtolower( $patient['name'] . ' ' . $patient['email'] );

			if ( false === mb_strpos( $haystack, $needle ) ) {
				continue;
			}

			$patient_results[] = array(
				'label'    => $patient['name'],
				'sublabel' => $patient['email'],
				'url'      => $patients_url ? esc_url_raw( $patients_url . '#dak-patient-' . $patient['id'] ) : '',
			);

			if ( count( $patient_results ) >= 5 ) {
				break;
			}
		}

		$appointment_results = array();

		foreach ( Appointments::all_for_admin( array( 'doctor_id' => $user->ID ) ) as $row ) {
			$haystack = mb_strtolower( $row['patient_name'] . ' ' . $row['guest_name'] );

			if ( false === mb_strpos( $haystack, $needle ) ) {
				continue;
			}

			$appointment_results[] = array(
				'label'    => $row['patient_name'],
				'sublabel' => $row['date'] . ' · ' . $row['time'],
				'url'      => $appointments_url ? esc_url_raw( $appointments_url . '#dak-appointment-' . $row['id'] ) : '',
			);

			if ( count( $appointment_results ) >= 5 ) {
				break;
			}
		}

		wp_send_json_success(
			array(
				'patients'     => $patient_results,
				'appointments' => $appointment_results,
			)
		);
	}

	/**
	 * AJAX: re-renders the Appointments tab for a new set of filters,
	 * without a full page reload. Reuses render_appointments_tab() unchanged
	 * by populating $_GET from the posted filters first, so the returned
	 * markup is byte-for-byte what a normal page load with the same query
	 * args would have produced.
	 *
	 * @return void
	 */
	public function handle_filter_appointments() {
		if ( ! check_ajax_referer( Doctor_Appointment_Handler::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$user = wp_get_current_user();

		if ( ! in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		foreach ( array( 'date_from', 'date_to', 'payment_status' ) as $key ) {
			$_GET[ $key ] = isset( $_POST[ $key ] ) ? $_POST[ $key ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce already verified above; render_appointments_tab() sanitizes each value itself, same as it does for a real $_GET.
		}

		wp_send_json_success( array( 'html' => $this->render_appointments_tab( $user ) ) );
	}

	/**
	 * Renders the Profile tab's form content, sharing the exact same markup,
	 * data and AJAX handlers as the standalone [doctor_profile] page instead
	 * of duplicating them.
	 *
	 * @param \WP_User $user Currently logged-in doctor.
	 * @return string
	 */
	private function render_profile_form( \WP_User $user ) {
		$form_context = Profile_Handler::build_form_context( $user );

		return $this->template_loader->get_template( 'profile/profile-form.php', $form_context );
	}

	/**
	 * Renders the Clinics tab: a doctor's clinics (physical locations and/or
	 * a video-consultation entry) and each one's weekly session schedule.
	 *
	 * @param \WP_User $user Currently logged-in doctor.
	 * @return string
	 */
	private function render_clinics_tab( \WP_User $user ) {
		return $this->template_loader->get_template(
			'dashboard/partials/doctor-clinics-tab.php',
			array(
				'clinics'      => Clinics::get_for_doctor( $user->ID ),
				'session_days' => Clinics::session_days(),
			)
		);
	}

	/**
	 * Renders the Patients tab: every patient "belonging" to this doctor
	 * (Appointments::patients_for_doctor()) — from appointment history
	 * and/or added directly by them — each editable in place.
	 *
	 * @param \WP_User $user Currently logged-in doctor.
	 * @return string
	 */
	private function render_patients_tab( \WP_User $user ) {
		return $this->template_loader->get_template(
			'dashboard/partials/doctor-patients-tab.php',
			array(
				'patients' => Appointments::patients_for_doctor( $user->ID ),
			)
		);
	}

	/**
	 * Renders the Services tab: a doctor's bookable services (e.g. "OPD
	 * Consultation"), each with its own category, charge, and duration.
	 *
	 * @param \WP_User $user Currently logged-in doctor.
	 * @return string
	 */
	private function render_services_tab( \WP_User $user ) {
		return $this->template_loader->get_template(
			'dashboard/partials/doctor-services-tab.php',
			array(
				'services'   => Services::get_for_doctor( $user->ID ),
				'categories' => Specializations::get_all(),
			)
		);
	}

	/**
	 * Renders the Video Consultation tab: the doctor's fixed video-consult
	 * price and its optional time-limited discount.
	 *
	 * @param \WP_User $user Currently logged-in doctor.
	 * @return string
	 */
	private function render_video_consultation_tab( \WP_User $user ) {
		return $this->template_loader->get_template(
			'dashboard/partials/doctor-video-consultation-tab.php',
			array(
				'pricing' => Video_Pricing::get_for_doctor( $user->ID ),
				'effective' => Video_Pricing::effective_price_for_doctor( $user->ID ),
			)
		);
	}

	/**
	 * Renders the Settings tab's content (currently just the light/dark
	 * theme toggle; a natural home for future account-level preferences).
	 *
	 * @return string
	 */
	private function render_settings_tab() {
		return $this->template_loader->get_template( 'dashboard/partials/dashboard-settings-tab.php' );
	}

	/**
	 * Gathers the data the dashboard template needs, computed from real
	 * user meta rather than placeholder values.
	 *
	 * @param \WP_User $user Currently logged-in doctor.
	 * @return array
	 */
	private function prepare_data( \WP_User $user ) {
		$years_experience_meta = get_user_meta( $user->ID, 'doctor_ak_years_experience', true );
		$specializations       = (array) get_user_meta( $user->ID, 'doctor_ak_specializations', true );
		$profile_picture_id    = (int) get_user_meta( $user->ID, 'doctor_ak_profile_picture_id', true );
		$clinics               = Clinics::get_for_doctor( $user->ID );

		$primary_clinic_location = '';

		foreach ( $clinics as $clinic ) {
			if ( Clinics::TYPE_PHYSICAL === $clinic['type'] ) {
				$primary_clinic_location = '' !== $clinic['address'] ? $clinic['address'] : $clinic['name'];
				break;
			}
		}

		$completion_checks = array(
			'' !== trim( (string) $user->first_name ),
			'' !== trim( (string) $user->last_name ),
			is_email( $user->user_email ),
			'' !== $years_experience_meta,
			! empty( $specializations ),
			! empty( $clinics ),
			$profile_picture_id > 0,
		);

		$profile_completion = (int) round( ( count( array_filter( $completion_checks ) ) / count( $completion_checks ) ) * 100 );

		$specialization_labels = array_map(
			function ( $slug ) {
				$all = Specializations::get_all();
				return isset( $all[ $slug ] ) ? $all[ $slug ] : $slug;
			},
			$specializations
		);

		$active_tab  = self::requested_tab();
		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );

		// Fetched once and reused for both the dashboard's upcoming-appointment
		// groups and the "Total Patients" stat below, instead of each running
		// its own identical for_doctor() query.
		$doctor_appointments     = Appointments::for_doctor( $user->ID );
		$appointment_data        = Appointments::doctor_dashboard_data( $user->ID, $doctor_appointments );
		$appointment_groups_html = array();

		foreach ( $appointment_data['groups'] as $group_key => $rows ) {
			$appointment_groups_html[ $group_key ] = array_map(
				function ( $row ) {
					return $this->template_loader->get_template( 'dashboard/partials/doctor-appointment-row.php', array( 'appointment' => $row ) );
				},
				$rows
			);
		}

		return array(
			'user'                  => $user,
			'avatar_url'            => $profile_picture_id > 0 ? wp_get_attachment_image_url( $profile_picture_id, 'thumbnail' ) : '',
			'profile_completion'    => $profile_completion,
			'years_experience'      => '' !== $years_experience_meta ? (int) $years_experience_meta : 0,
			'specializations'       => $specializations,
			'specialization_labels' => $specialization_labels,
			'clinic_location'       => $primary_clinic_location,
			'video_consultation'    => Clinics::doctor_has_active_video_clinic( $user->ID ),
			'appointment_groups'    => $appointment_groups_html,
			'total_upcoming_appointments' => $appointment_data['total_upcoming_count'],
			'theme'                 => Theme_Preference::get( $user->ID ),
			'active_tab'            => $active_tab,
			'profile_form_html'     => 'profile' === $active_tab ? $this->render_profile_form( $user ) : '',
			'clinics_tab_html'      => 'clinics' === $active_tab ? $this->render_clinics_tab( $user ) : '',
			'services_tab_html'     => 'services' === $active_tab ? $this->render_services_tab( $user ) : '',
			'video_consultation_tab_html' => 'video-consultation' === $active_tab ? $this->render_video_consultation_tab( $user ) : '',
			'appointments_tab_html' => 'appointments' === $active_tab ? $this->render_appointments_tab( $user ) : '',
			'patients_tab_html'     => 'patients' === $active_tab ? $this->render_patients_tab( $user ) : '',
			'notifications_tab_html' => 'notifications' === $active_tab ? $this->render_notifications_tab( $user ) : '',
			'unread_notifications_count' => Notification_Center::unread_count( $user->ID ),
			'settings_tab_html'     => 'settings' === $active_tab ? $this->render_settings_tab() : '',
			'dashboard_url'         => $dashboard_url,
			'profile_url'           => self::tab_url( $dashboard_url, 'profile' ),
			'clinics_url'           => self::tab_url( $dashboard_url, 'clinics' ),
			'services_url'          => self::tab_url( $dashboard_url, 'services' ),
			'video_consultation_url' => self::tab_url( $dashboard_url, 'video-consultation' ),
			'appointments_url'      => self::tab_url( $dashboard_url, 'appointments' ),
			'patients_url'          => self::tab_url( $dashboard_url, 'patients' ),
			'notifications_url'     => self::tab_url( $dashboard_url, 'notifications' ),
			'settings_url'          => self::tab_url( $dashboard_url, 'settings' ),
			'logout_url'            => wp_logout_url( Page_Finder::url_for_shortcode( 'doctor_login' ) ),
			'total_patients'        => Appointments::unique_patient_count_for_doctor( $user->ID, $doctor_appointments ),
			'doctor_clinics'        => $clinics,
			'clinic_locations'      => Clinic_Locations::get_all(),
			/**
			 * Filters the doctor dashboard's "Today's Appointments" stat.
			 *
			 * No appointments/booking system exists yet, so this defaults to
			 * zero; a future booking module can filter in a real count
			 * without this class needing to know about it.
			 *
			 * @param int      $count Appointment count for today. Default 0.
			 * @param \WP_User $user  Currently viewed doctor.
			 */
			'today_appointments'   => (int) apply_filters( 'doctor_ak_doctor_dashboard_today_appointments_count', 0, $user ),
			/**
			 * Filters the doctor dashboard's "Video Consults" stat.
			 *
			 * @param int      $count Completed video consult count. Default 0.
			 * @param \WP_User $user  Currently viewed doctor.
			 */
			'video_consults'       => (int) apply_filters( 'doctor_ak_doctor_dashboard_video_consults_count', 0, $user ),
			/**
			 * Filters the doctor dashboard's rating stat.
			 *
			 * No ratings/reviews system exists yet, so this defaults to null
			 * (rendered as an honest empty state) rather than a fabricated
			 * number.
			 *
			 * @param float|null $rating       Average rating, or null if none yet.
			 * @param \WP_User   $user         Currently viewed doctor.
			 */
			'rating'                => apply_filters( 'doctor_ak_doctor_dashboard_rating', null, $user ),
			/**
			 * Filters the doctor dashboard's review count, shown alongside the rating.
			 *
			 * @param int      $count Review count. Default 0.
			 * @param \WP_User $user  Currently viewed doctor.
			 */
			'review_count'          => (int) apply_filters( 'doctor_ak_doctor_dashboard_review_count', 0, $user ),
		);
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * doctor dashboard shortcode.
	 *
	 * @return bool
	 */
	private function is_dashboard_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
