<?php
/**
 * Backs the [patient_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Notification_Center;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Template_Loader;
use DoctorAKPortal\Includes\Theme_Preference;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Patient_Dashboard
 *
 * Gates access (must be logged in and hold the Patient role), then
 * assembles real, computed data. Appointments and notifications render
 * through action hooks so a future booking module can populate them.
 */
class Patient_Dashboard {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'patient_dashboard';

	/**
	 * Nonce action for the dashboard's Pay Now / Cancel AJAX actions.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_patient_dashboard';

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
	 * Enqueues dashboard assets only on pages containing [patient_dashboard].
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

		wp_enqueue_style(
			'doctor-ak-portal-patient-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-patient-dashboard.css',
			array( 'doctor-ak-portal-dashboard' ),
			Assets::version( 'assets/css/doctor-ak-patient-dashboard.css' )
		);

		// The "Book Appointment" FAB and the Reschedule Appointment modal
		// both use the .dak-modal overlay/dialog styling.
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
			'doctor-ak-portal-patient-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-patient-dashboard.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-patient-dashboard.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-patient-dashboard',
			'dakPatientDashboard',
			array(
				'ajaxUrl'                       => admin_url( 'admin-ajax.php' ),
				'nonce'                          => wp_create_nonce( self::NONCE_ACTION ),
				'confirmCancelRefundEligible'    => __( "Cancel this appointment? You're within the refund window, so you'll be eligible for a refund. This cannot be undone.", 'doctor-ak-portal' ),
				'confirmCancelNoRefund'          => __( "Cancel this appointment? This is after the doctor's refund window, so no refund will apply. This cannot be undone.", 'doctor-ak-portal' ),
				'genericError'                   => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
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
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'action'   => 'doctor_ak_patient_reschedule_appointment',
				'genericError' => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
			)
		);

		wp_enqueue_script(
			'doctor-ak-portal-request-refund',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-request-refund.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-request-refund.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-request-refund',
			'dakRequestRefund',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
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

		// The Profile tab renders the same form as the standalone
		// [doctor_profile] page (see Profile_Handler), so it needs its
		// assets too — but only when that tab is actually showing.
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
	 * 'appointments', 'settings', 'medical-history', or 'payments'.
	 *
	 * @return string
	 */
	private static function requested_tab() {
		if ( ! isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			return 'dashboard';
		}

		$tab = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		if ( ! in_array( $tab, array( 'profile', 'appointments', 'notifications', 'settings', 'medical-history', 'payments' ), true ) ) {
			return 'dashboard';
		}

		// An admin can turn a tab off entirely (Settings → Roles &
		// Permissions) — a direct/bookmarked link to it then falls back to
		// the Dashboard tab exactly like an unrecognized slug would.
		if ( ! Role_Permissions::is_tab_allowed( Roles::PATIENT_ROLE, $tab ) ) {
			return 'dashboard';
		}

		return $tab;
	}

	/**
	 * A tab's nav URL, or '' if the dashboard URL isn't resolvable or an
	 * admin has turned that tab off for patients (Settings → Roles &
	 * Permissions) — the template only renders a nav link when this is
	 * non-empty, so a disallowed tab's link simply doesn't appear.
	 *
	 * @param string $dashboard_url Base dashboard URL, or ''.
	 * @param string $tab           Tab slug.
	 * @return string
	 */
	private static function tab_url( $dashboard_url, $tab ) {
		if ( '' === $dashboard_url || ! Role_Permissions::is_tab_allowed( Roles::PATIENT_ROLE, $tab ) ) {
			return '';
		}

		return add_query_arg( 'tab', $tab, $dashboard_url );
	}

	/**
	 * Renders the Notifications tab: every notification recorded for this
	 * patient, via Notification_Center::for_user().
	 *
	 * @param \WP_User $user Currently logged-in patient.
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
	 * Reads the current 'status' query var for the Appointments tab's filter.
	 *
	 * @return string
	 */
	private static function requested_appointments_status() {
		if ( ! isset( $_GET['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			return '';
		}

		$status = sanitize_key( wp_unslash( $_GET['status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		return array_key_exists( $status, Appointments::status_options() ) ? $status : '';
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
	 * Renders the Appointments tab: every appointment this patient has ever
	 * booked, filterable by date and status.
	 *
	 * @param \WP_User $user Currently logged-in patient.
	 * @return string
	 */
	private function render_appointments_tab( \WP_User $user ) {
		$date   = self::requested_appointments_date();
		$status = self::requested_appointments_status();

		return $this->template_loader->get_template(
			'dashboard/partials/patient-appointments-list.php',
			array(
				'rows'            => Appointments::all_for_admin(
					array(
						'patient_id' => $user->ID,
						'date'       => $date,
						'status'     => $status,
					)
				),
				'status_options'  => Appointments::status_options(),
				'selected_date'   => $date,
				'selected_status' => $status,
			)
		);
	}

	/**
	 * Renders the Profile tab's form content, sharing the exact same markup,
	 * data and AJAX handlers as the standalone [doctor_profile] page instead
	 * of duplicating them (mirrors Doctor_Dashboard::render_profile_form()).
	 *
	 * @param \WP_User $user Currently logged-in patient.
	 * @return string
	 */
	private function render_profile_form( \WP_User $user ) {
		return $this->template_loader->get_template( 'profile/profile-form.php', Profile_Handler::build_form_context( $user ) );
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

		if ( ! in_array( Roles::PATIENT_ROLE, (array) $user->roles, true ) ) {
			return $this->template_loader->get_template(
				'dashboard/access-denied.php',
				array(
					'reason'        => 'wrong_role',
					'dashboard_url' => Page_Finder::url_for_shortcode( 'doctor_dashboard' ),
				)
			);
		}

		return $this->template_loader->get_template( 'dashboard/patient-dashboard.php', $this->prepare_data( $user ) );
	}

	/**
	 * Gathers the data the dashboard template needs, computed from real
	 * user meta rather than placeholder values.
	 *
	 * @param \WP_User $user Currently logged-in patient.
	 * @return array
	 */
	private function prepare_data( \WP_User $user ) {
		$phone_number       = get_user_meta( $user->ID, 'doctor_ak_phone_number', true );
		$profile_picture_id = (int) get_user_meta( $user->ID, 'doctor_ak_profile_picture_id', true );

		$completion_checks = array(
			array(
				'is_complete' => '' !== trim( (string) $user->first_name ) && '' !== trim( (string) $user->last_name ),
				'label'       => __( 'Add your full name', 'doctor-ak-portal' ),
			),
			array(
				'is_complete' => is_email( $user->user_email ),
				'label'       => __( 'Add a valid email address', 'doctor-ak-portal' ),
			),
			array(
				'is_complete' => '' !== $phone_number,
				'label'       => __( 'Add your phone number', 'doctor-ak-portal' ),
			),
			array(
				'is_complete' => $profile_picture_id > 0,
				'label'       => __( 'Upload a profile photo', 'doctor-ak-portal' ),
			),
		);

		$missing_profile_items = array();
		$complete_count        = 0;

		foreach ( $completion_checks as $check ) {
			if ( $check['is_complete'] ) {
				++$complete_count;
			} else {
				$missing_profile_items[] = $check['label'];
			}
		}

		$profile_completion = (int) round( ( $complete_count / count( $completion_checks ) ) * 100 );
		$active_tab          = self::requested_tab();
		$dashboard_url        = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$booking_page_url     = Page_Finder::url_for_shortcode( 'book_appointment' );
		$dashboard_data       = Appointments::patient_dashboard_data( $user->ID );

		$appointment_groups_html = array();

		foreach ( $dashboard_data['groups'] as $group_key => $rows ) {
			$appointment_groups_html[ $group_key ] = array_map(
				function ( $row ) {
					return $this->template_loader->get_template( 'dashboard/partials/patient-appointment-row.php', array( 'appointment' => $row ) );
				},
				$rows
			);
		}

		return array(
			'user'                  => $user,
			'avatar_url'            => self::avatar_url( $user->ID ),
			'profile_completion'    => $profile_completion,
			'missing_profile_items' => $missing_profile_items,
			'phone_number'          => $phone_number,
			'next_appointment'      => $dashboard_data['next_appointment'],
			'unpaid_count'          => $dashboard_data['unpaid_count'],
			'unpaid_total'          => $dashboard_data['unpaid_total'],
			'appointment_groups'    => $appointment_groups_html,
			'total_upcoming_count'  => $dashboard_data['total_upcoming_count'],
			'recent_activity'       => Appointments::recent_activity_for_patient( $user->ID ),
			'booking_url'           => $booking_page_url,
			'video_booking_url'     => $booking_page_url ? add_query_arg( 'type', 'video', $booking_page_url ) : '',
			'profile_url'           => self::tab_url( $dashboard_url, 'profile' ),
			'directory_url'         => Page_Finder::url_for_shortcode( 'doctors_directory' ),
			'logout_url'            => wp_logout_url( Page_Finder::url_for_shortcode( 'doctor_login' ) ),
			'contact_url'           => self::contact_url(),
			'theme'                 => Theme_Preference::get( $user->ID ),
			'active_tab'            => $active_tab,
			'dashboard_url'         => $dashboard_url,
			'settings_url'          => self::tab_url( $dashboard_url, 'settings' ),
			'medical_history_url'   => self::tab_url( $dashboard_url, 'medical-history' ),
			'payments_url'          => self::tab_url( $dashboard_url, 'payments' ),
			'appointments_url'      => self::tab_url( $dashboard_url, 'appointments' ),
			'notifications_url'     => self::tab_url( $dashboard_url, 'notifications' ),
			'unread_notifications_count' => Notification_Center::unread_count( $user->ID ),
			'profile_tab_html'      => 'profile' === $active_tab ? $this->render_profile_form( $user ) : '',
			'settings_tab_html'     => 'settings' === $active_tab ? $this->template_loader->get_template( 'dashboard/partials/dashboard-settings-tab.php' ) : '',
			'payments_tab_html'     => 'payments' === $active_tab ? $this->render_payments_tab( $user ) : '',
			'appointments_tab_html' => 'appointments' === $active_tab ? $this->render_appointments_tab( $user ) : '',
			'notifications_tab_html' => 'notifications' === $active_tab ? $this->render_notifications_tab( $user ) : '',
			'medical_history_tab_html' => 'medical-history' === $active_tab ? $this->render_medical_history_tab( $user ) : '',
		);
	}

	/**
	 * Renders the Medical History tab: every completed visit this patient
	 * has had, with whichever doctor added a clinical note for it (see
	 * Appointments::save_encounter_note_by_doctor()) — a visit with no note
	 * on file is shown honestly as such, never a fabricated summary.
	 *
	 * @param \WP_User $user Currently logged-in patient.
	 * @return string
	 */
	private function render_medical_history_tab( \WP_User $user ) {
		return $this->template_loader->get_template(
			'dashboard/partials/patient-medical-history.php',
			array(
				'visits' => Appointments::all_for_admin(
					array(
						'patient_id' => $user->ID,
						'status'     => 'completed',
					)
				),
			)
		);
	}

	/**
	 * Renders the Payments tab: every appointment the patient has actually
	 * paid for, via Appointments::payment_history_for_patient().
	 *
	 * @param \WP_User $user Currently logged-in patient.
	 * @return string
	 */
	private function render_payments_tab( \WP_User $user ) {
		$history = Appointments::payment_history_for_patient( $user->ID );

		return $this->template_loader->get_template(
			'dashboard/partials/patient-payments-tab.php',
			array(
				'rows'       => $history['rows'],
				'total_paid' => $history['total_paid'],
				'booking_url' => Page_Finder::url_for_shortcode( 'book_appointment' ),
			)
		);
	}

	/**
	 * Best-effort "Contact Us" page URL for the sidebar's Contact Support
	 * button — checks for a page at the common 'contact'/'contact-us'
	 * slugs (no dedicated shortcode exists for this in the plugin), falling
	 * back to the site's home page rather than a dead link.
	 *
	 * @return string
	 */
	private static function contact_url() {
		foreach ( array( 'contact', 'contact-us' ) as $slug ) {
			$page = get_page_by_path( $slug );

			if ( $page instanceof \WP_Post ) {
				return get_permalink( $page );
			}
		}

		return home_url( '/' );
	}

	/**
	 * Resolves the patient's uploaded profile picture, or '' if none set.
	 *
	 * @param int $user_id Patient's user ID.
	 * @return string
	 */
	private static function avatar_url( $user_id ) {
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
	 * Checks whether the current request is for a page containing the
	 * patient dashboard shortcode.
	 *
	 * @return bool
	 */
	private function is_dashboard_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
