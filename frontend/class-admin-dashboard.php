<?php
/**
 * Backs the [admin_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Doctor_Awards;
use DoctorAKPortal\Includes\Locations;
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
 * Class Admin_Dashboard
 *
 * Gates access to WordPress users who can 'manage_options' (the built-in
 * Administrator role). The sidebar mirrors the full clinic-management menu
 * (Dashboard/Appointments/Encounters, Doctor Requests/Patients/Doctors/
 * Receptionist, Clinic/Services/Doctor Sessions) so the shape is in place
 * end to end, but only the Doctor Requests, Doctors, Patients, and
 * Appointments sections are backed by real data today — every other
 * section renders an honest "coming soon" placeholder instead of
 * fabricated numbers or forms.
 */
class Admin_Dashboard {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'admin_dashboard';

	/**
	 * Nonce action shared by the admin user-management AJAX endpoints.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_admin_users';

	/**
	 * Sidebar structure: group label => ordered list of section slug => label.
	 * The 'doctors' and 'patients' sections are the only ones backed by real
	 * CRUD today; every other slug renders a placeholder.
	 *
	 * @var array
	 */
	const NAV_GROUPS = array(
		'Main'   => array(
			'dashboard'   => 'Dashboard',
			'appointments' => 'Appointments',
			'encounters'  => 'Encounters',
			'notifications' => 'Notifications',
		),
		'Users'  => array(
			'doctor-requests' => 'Doctor Requests',
			'patients'    => 'Patients',
			'doctors'     => 'Doctors',
			'receptionist' => 'Receptionist',
		),
		'Clinic'  => array(
			'clinic'             => 'Clinic',
			'services'           => 'Services',
			'video-consultation' => 'Video Consultation',
			'doctor-sessions'    => 'Doctor Sessions',
		),
		'Account' => array(
			'role-permissions' => 'Roles & Permissions',
			'locations'        => 'Locations',
			'settings'         => 'Settings',
		),
	);

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
	 * Enqueues dashboard assets only on pages containing [admin_dashboard].
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
			'doctor-ak-portal-booking-modal',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-booking-modal.css',
			array( 'doctor-ak-portal-dashboard' ),
			Assets::version( 'assets/css/doctor-ak-booking-modal.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-registration',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-registration.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-admin-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-admin-dashboard.css',
			array( 'doctor-ak-portal-booking-modal', 'doctor-ak-portal-registration' ),
			Assets::version( 'assets/css/doctor-ak-admin-dashboard.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-awards-editor',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-awards-editor.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-awards-editor.js' ),
			true
		);

		// Registration.js defines and exposes the shared tag-style
		// window.DoctorAKPortal.initMultiSelect() enhancer the Add/Edit
		// Doctor/Patient form's specialization field reuses (same one the
		// registration form and the doctor's own profile-edit page use) —
		// every other init function it runs on load is a safe no-op here
		// since none of its target elements exist on this page.
		wp_enqueue_script(
			'doctor-ak-portal-registration',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-registration.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-registration.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-city-area-select',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-city-area-select.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-city-area-select.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-admin-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-dashboard.js',
			array( 'doctor-ak-portal-awards-editor', 'doctor-ak-portal-registration', 'doctor-ak-portal-city-area-select' ),
			Assets::version( 'assets/js/doctor-ak-admin-dashboard.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-admin-dashboard',
			'dakAdminUsers',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( self::NONCE_ACTION ),
				'confirmDelete'  => __( 'Permanently delete this account? This cannot be undone.', 'doctor-ak-portal' ),
				'confirmDisable' => __( 'Deactivate this account? They will not be able to log in until reactivated.', 'doctor-ak-portal' ),
				'confirmEnable'  => __( 'Reactivate this account?', 'doctor-ak-portal' ),
				'genericError'   => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
				'locations'      => Locations::get_all(),
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

		if ( 'doctor-sessions' === self::requested_section() ) {
			wp_enqueue_style(
				'doctor-ak-portal-registration',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
				array( 'doctor-ak-portal-auth' ),
				Assets::version( 'assets/css/doctor-ak-registration.css' )
			);

			wp_enqueue_style(
				'doctor-ak-portal-clinics',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-clinics.css',
				array( 'doctor-ak-portal-registration' ),
				Assets::version( 'assets/css/doctor-ak-clinics.css' )
			);

			wp_enqueue_script(
				'doctor-ak-portal-city-area-select',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-city-area-select.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-city-area-select.js' ),
				true
			);

			wp_enqueue_script(
				'doctor-ak-portal-admin-sessions',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-sessions.js',
				array( 'doctor-ak-portal-city-area-select' ),
				Assets::version( 'assets/js/doctor-ak-admin-sessions.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-sessions',
				'dakAdminSessions',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
					'locations'       => Locations::get_all(),
					'clinicLocations' => Clinic_Locations::get_all(),
				)
			);

			if ( self::is_session_form_view() ) {
				wp_enqueue_script(
					'doctor-ak-portal-admin-session-form',
					DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-session-form.js',
					array( 'doctor-ak-portal-admin-sessions', 'doctor-ak-portal-city-area-select' ),
					Assets::version( 'assets/js/doctor-ak-admin-session-form.js' ),
					true
				);
			}
		}

		if ( 'clinic' === self::requested_section() ) {
			wp_enqueue_style(
				'doctor-ak-portal-registration',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
				array( 'doctor-ak-portal-auth' ),
				Assets::version( 'assets/css/doctor-ak-registration.css' )
			);

			wp_enqueue_script(
				'doctor-ak-portal-city-area-select',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-city-area-select.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-city-area-select.js' ),
				true
			);

			wp_enqueue_script(
				'doctor-ak-portal-admin-clinic-locations',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-clinic-locations.js',
				array( 'doctor-ak-portal-city-area-select' ),
				Assets::version( 'assets/js/doctor-ak-admin-clinic-locations.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-clinic-locations',
				'dakAdminClinicLocations',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
					'locations' => Locations::get_all(),
				)
			);
		}

		if ( 'role-permissions' === self::requested_section() ) {
			wp_enqueue_script(
				'doctor-ak-portal-admin-role-permissions',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-role-permissions.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-role-permissions.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-role-permissions',
				'dakRolePermissions',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( Role_Permissions_Handler::NONCE_ACTION ),
				)
			);
		}

		if ( 'locations' === self::requested_section() ) {
			wp_enqueue_script(
				'doctor-ak-portal-admin-locations',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-locations.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-locations.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-locations',
				'dakLocations',
				array(
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'nonce'            => wp_create_nonce( Locations_Handler::NONCE_ACTION ),
					'defaultCountries' => Locations::default_seed_data(),
				)
			);
		}

		if ( 'services' === self::requested_section() ) {
			wp_enqueue_style(
				'doctor-ak-portal-registration',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
				array( 'doctor-ak-portal-auth' ),
				Assets::version( 'assets/css/doctor-ak-registration.css' )
			);

			wp_enqueue_style(
				'doctor-ak-portal-clinics',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-clinics.css',
				array( 'doctor-ak-portal-registration' ),
				Assets::version( 'assets/css/doctor-ak-clinics.css' )
			);

			wp_enqueue_script(
				'doctor-ak-portal-admin-services',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-services.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-services.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-services',
				'dakAdminServices',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				)
			);
		}

		if ( 'video-consultation' === self::requested_section() ) {
			wp_enqueue_style(
				'doctor-ak-portal-registration',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
				array( 'doctor-ak-portal-auth' ),
				Assets::version( 'assets/css/doctor-ak-registration.css' )
			);

			wp_enqueue_style(
				'doctor-ak-portal-clinics',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-clinics.css',
				array( 'doctor-ak-portal-registration' ),
				Assets::version( 'assets/css/doctor-ak-clinics.css' )
			);

			wp_enqueue_script(
				'doctor-ak-portal-admin-video-pricing',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-video-pricing.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-video-pricing.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-video-pricing',
				'dakAdminVideoPricing',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				)
			);
		}

		if ( 'doctor-requests' === self::requested_section() ) {
			wp_enqueue_script(
				'doctor-ak-portal-admin-doctor-requests',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-doctor-requests.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-doctor-requests.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-doctor-requests',
				'dakAdminDoctorRequests',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( self::NONCE_ACTION ),
					'confirmApprove' => __( 'Approve this doctor? They will be able to log in immediately.', 'doctor-ak-portal' ),
					'confirmReject'  => __( 'Reject this registration? They will not be able to log in.', 'doctor-ak-portal' ),
					'genericError'   => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
				)
			);
		}

		if ( 'appointments' === self::requested_section() ) {
			wp_enqueue_style(
				'doctor-ak-portal-registration',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
				array( 'doctor-ak-portal-auth' ),
				Assets::version( 'assets/css/doctor-ak-registration.css' )
			);

			wp_enqueue_style(
				'doctor-ak-portal-clinics',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-clinics.css',
				array( 'doctor-ak-portal-registration' ),
				Assets::version( 'assets/css/doctor-ak-clinics.css' )
			);

			wp_enqueue_script(
				'doctor-ak-portal-admin-appointments',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-appointments.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-appointments.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-appointments',
				'dakAdminAppointments',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				)
			);
		}
	}

	/**
	 * Reads the current 'section' query var, validated against the sidebar's
	 * known section slugs. Defaults to 'dashboard'.
	 *
	 * @return string
	 */
	public static function requested_section() {
		if ( ! isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			return 'dashboard';
		}

		$section = sanitize_key( wp_unslash( $_GET['section'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		return array_key_exists( $section, self::all_sections() ) ? $section : 'dashboard';
	}

	/**
	 * Flattens NAV_GROUPS into a single section slug => label map.
	 *
	 * @return array
	 */
	private static function all_sections() {
		return array_merge( ...array_values( self::NAV_GROUPS ) );
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

		if ( ! current_user_can( 'manage_options' ) ) {
			$user               = wp_get_current_user();
			$fallback_shortcode = in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ? 'doctor_dashboard' : 'patient_dashboard';

			return $this->template_loader->get_template(
				'dashboard/access-denied.php',
				array(
					'reason'        => 'wrong_role',
					'dashboard_url' => Page_Finder::url_for_shortcode( $fallback_shortcode ),
				)
			);
		}

		return $this->template_loader->get_template( 'dashboard/admin-dashboard.php', $this->prepare_data() );
	}

	/**
	 * Gathers the data the admin dashboard template needs: the sidebar's nav
	 * structure plus whichever content the active section requires.
	 *
	 * @return array
	 */
	private function prepare_data() {
		$section       = self::requested_section();
		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$all_sections  = self::all_sections();

		$unread_notifications_count = Notification_Center::unread_count( get_current_user_id() );
		$pending_doctors_count      = self::pending_doctors_count();

		$nav_groups = array();

		foreach ( self::NAV_GROUPS as $group_label => $items ) {
			$group_items = array();

			foreach ( $items as $slug => $label ) {
				$badge = 0;

				if ( 'notifications' === $slug ) {
					$badge = $unread_notifications_count;
				} elseif ( 'doctor-requests' === $slug ) {
					$badge = $pending_doctors_count;
				}

				$group_items[] = array(
					'slug'      => $slug,
					'label'     => $label,
					'url'       => $dashboard_url ? add_query_arg( 'section', $slug, $dashboard_url ) : '',
					'is_active' => $slug === $section,
					'badge'     => $badge,
				);
			}

			$nav_groups[] = array(
				'label' => $group_label,
				'items' => $group_items,
			);
		}

		$is_users_section  = in_array( $section, array( 'doctors', 'patients' ), true );
		$is_user_form_view = $is_users_section && self::is_user_form_view();

		$modal_html = '';

		if ( 'services' === $section ) {
			$modal_html = $this->service_modal_html();
		} elseif ( 'video-consultation' === $section ) {
			$modal_html = $this->video_pricing_modal_html();
		} elseif ( 'appointments' === $section ) {
			$modal_html = $this->appointment_modal_html();
		} elseif ( 'clinic' === $section ) {
			$modal_html = $this->clinic_location_modal_html();
		}

		if ( $is_user_form_view ) {
			$content_html = $this->user_form_screen_html( $section );
		} elseif ( $is_users_section ) {
			$content_html = $this->users_section_html( $section );
		} else {
			$content_html = $this->section_content_html( $section );
		}

		return array(
			'section'           => $section,
			'section_label'     => isset( $all_sections[ $section ] ) ? $all_sections[ $section ] : $section,
			'nav_groups'        => $nav_groups,
			'dashboard_url'     => $dashboard_url,
			'logout_url'        => wp_logout_url( Page_Finder::url_for_shortcode( 'doctor_login' ) ),
			'current_user'      => wp_get_current_user(),
			'content_html'      => $content_html,
			'modal_html'        => $modal_html,
			'role'              => $is_users_section ? $this->role_for_section( $section ) : '',
			'is_users_section'  => $is_users_section,
			'is_user_form_view' => $is_user_form_view,
			'theme'             => Theme_Preference::get( get_current_user_id() ),
		);
	}

	/**
	 * Whether the current request wants the full-screen Add/Edit Doctor or
	 * Patient form instead of the accounts table (`?view=form`, optionally
	 * with `&user_id=X` to edit; without it, it's the "Add" form).
	 *
	 * @return bool
	 */
	private static function is_user_form_view() {
		return isset( $_GET['view'] ) && 'form' === sanitize_key( wp_unslash( $_GET['view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
	}

	/**
	 * Whether the current request wants the full-screen Add/Edit Session
	 * form instead of the Doctor Sessions table (`?view=form`, optionally
	 * with `&clinic_id=X` to edit; without it, it's the "Add" form). Mirrors
	 * is_user_form_view() for the 'doctor-sessions'/'clinic' section.
	 *
	 * @return bool
	 */
	private static function is_session_form_view() {
		return isset( $_GET['view'] ) && 'form' === sanitize_key( wp_unslash( $_GET['view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
	}

	/**
	 * Renders the full-screen Add/Edit Session form (replaces the Doctor
	 * Sessions table content area when `?view=form` is present) — a
	 * Country/City/Area-aware clinic form plus a Morning/Afternoon/Evening
	 * weekly sessions grid, submitted to the same
	 * Clinic_Handler::handle_admin_save_clinic() AJAX endpoint the old modal
	 * used, just rendered in-page instead of a popup so there's room to work.
	 *
	 * @param string $section 'doctor-sessions' or 'clinic'.
	 * @return string
	 */
	private function session_form_screen_html( $section ) {
		$clinic_id = isset( $_GET['clinic_id'] ) ? absint( wp_unslash( $_GET['clinic_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
		$editing   = $clinic_id > 0 ? Clinics::find( $clinic_id ) : null;

		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );

		return $this->template_loader->get_template(
			'dashboard/partials/admin-session-form-screen.php',
			array(
				'session_days'     => Clinics::session_days(),
				'session_periods'  => Clinics::session_periods(),
				'doctor_options'   => $this->doctor_options(),
				'clinic_locations' => Clinic_Locations::get_all(),
				'list_url'         => $dashboard_url ? add_query_arg( 'section', $section, $dashboard_url ) : '',
				'editing_clinic'   => $editing,
			)
		);
	}

	/**
	 * Maps a Users section slug to the WordPress role it manages.
	 *
	 * @param string $section 'doctors' or 'patients'.
	 * @return string
	 */
	private function role_for_section( $section ) {
		return 'patients' === $section ? Roles::PATIENT_ROLE : Roles::DOCTOR_ROLE;
	}

	/**
	 * Renders the appropriate content for a non-Users section: the real
	 * stats overview for 'dashboard', or an honest placeholder for every
	 * other section that doesn't have a real feature behind it yet.
	 *
	 * @param string $section Active section slug.
	 * @return string
	 */
	private function section_content_html( $section ) {
		if ( 'dashboard' === $section ) {
			return $this->template_loader->get_template( 'dashboard/partials/admin-overview.php', $this->overview_data() );
		}

		if ( 'notifications' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/notifications-list.php',
				array( 'notifications' => Notification_Center::for_user( get_current_user_id() ) )
			);
		}

		if ( 'doctor-requests' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-doctor-requests.php',
				array( 'pending_doctors' => $this->pending_doctors() )
			);
		}

		if ( 'appointments' === $section ) {
			$patient_id = isset( $_GET['patient_id'] ) ? absint( wp_unslash( $_GET['patient_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$date       = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$status     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$payment_mode = isset( $_GET['payment_mode'] ) ? sanitize_key( wp_unslash( $_GET['payment_mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

			$status       = array_key_exists( $status, Appointments::status_options() ) ? $status : '';
			$payment_mode = array_key_exists( $payment_mode, Appointments::payment_mode_options() ) ? $payment_mode : '';

			$patient      = $patient_id > 0 ? get_userdata( $patient_id ) : false;
			$patient_name = '';

			if ( $patient ) {
				$patient_name = trim( $patient->first_name . ' ' . $patient->last_name );
				$patient_name = '' !== $patient_name ? $patient_name : $patient->display_name;
			}

			$dashboard_url     = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
			$appointments_url  = $dashboard_url ? add_query_arg( 'section', 'appointments', $dashboard_url ) : '';

			return $this->template_loader->get_template(
				'dashboard/partials/admin-appointments.php',
				array(
					'appointments'      => Appointments::all_for_admin(
						array(
							'patient_id'   => $patient_id,
							'date'         => $date,
							'status'       => $status,
							'payment_mode' => $payment_mode,
						)
					),
					'filtered_patient'  => $patient_name,
					'appointments_url'  => $appointments_url,
					'status_options'    => Appointments::status_options(),
					'payment_mode_options' => Appointments::payment_mode_options(),
					'filters'           => array(
						'patient_id'   => $patient_id,
						'date'         => $date,
						'status'       => $status,
						'payment_mode' => $payment_mode,
					),
				)
			);
		}

		if ( 'doctor-sessions' === $section ) {
			if ( self::is_session_form_view() ) {
				return $this->session_form_screen_html( $section );
			}

			return $this->template_loader->get_template(
				'dashboard/partials/admin-doctor-sessions.php',
				array(
					'clinics'    => Clinics::all_flat_for_admin(),
					'section_url' => add_query_arg( 'section', $section, Page_Finder::url_for_shortcode( self::SHORTCODE_TAG ) ),
				)
			);
		}

		if ( 'clinic' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-clinic-locations.php',
				array( 'clinic_locations' => Clinic_Locations::get_all() )
			);
		}

		if ( 'services' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-services.php',
				array( 'services' => Services::all_flat_for_admin() )
			);
		}

		if ( 'video-consultation' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-video-consultation.php',
				array( 'pricing_rows' => Video_Pricing::all_flat_for_admin() )
			);
		}

		if ( 'settings' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-settings-section.php',
				array( 'settings_tab_html' => $this->template_loader->get_template( 'dashboard/partials/dashboard-settings-tab.php' ) )
			);
		}

		if ( 'role-permissions' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-role-permissions.php',
				array(
					'doctor_tabs'  => Role_Permissions::doctor_tabs(),
					'patient_tabs' => Role_Permissions::patient_tabs(),
					'saved'        => Role_Permissions::get_all(),
				)
			);
		}

		if ( 'locations' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-locations.php',
				array( 'countries' => Locations::get_all() )
			);
		}

		$all_sections = self::all_sections();

		return $this->template_loader->get_template(
			'dashboard/partials/admin-placeholder.php',
			array(
				'section_label' => isset( $all_sections[ $section ] ) ? $all_sections[ $section ] : $section,
			)
		);
	}


	/**
	 * Renders the "Add/Edit Clinic" modal (shared single instance, populated
	 * client-side from the clicked row's data) for the "Clinic" section's
	 * master Clinic_Locations list.
	 *
	 * @return string
	 */
	private function clinic_location_modal_html() {
		return $this->template_loader->get_template( 'modal/admin-clinic-location-modal.php', array() );
	}

	/**
	 * Renders the "Add/Edit Service" modal (shared single instance,
	 * populated client-side from the clicked row's data).
	 *
	 * @return string
	 */
	private function service_modal_html() {
		return $this->template_loader->get_template(
			'modal/admin-service-modal.php',
			array(
				'doctor_options' => $this->doctor_options(),
				'categories'     => Specializations::get_all(),
			)
		);
	}

	/**
	 * Renders the "Edit Video Pricing" modal (shared single instance,
	 * populated client-side from the clicked row's data).
	 *
	 * @return string
	 */
	private function video_pricing_modal_html() {
		return $this->template_loader->get_template(
			'modal/admin-video-pricing-modal.php',
			array(
				'doctor_options' => $this->doctor_options(),
			)
		);
	}

	/**
	 * Renders the "Add/Edit Appointment" modal (shared single instance,
	 * populated client-side from the clicked row's data).
	 *
	 * @return string
	 */
	private function appointment_modal_html() {
		return $this->template_loader->get_template(
			'modal/admin-appointment-modal.php',
			array(
				'doctor_options'  => $this->doctor_options(),
				'patient_options' => Appointments::patient_options(),
				'status_options'  => Appointments::status_options(),
				'services'        => $this->services_by_doctor_and_type(),
			)
		);
	}

	/**
	 * Every doctor's services, shaped for the Appointments modal's JS to
	 * filter client-side as [doctor_id][type] => [{id, name, charge}, ...].
	 * Same shape Booking_Page builds for the patient-facing booking page.
	 *
	 * @return array
	 */
	private function services_by_doctor_and_type() {
		$map = array();

		foreach ( array_keys( $this->doctor_options() ) as $doctor_id ) {
			foreach ( array( 'clinic', 'video' ) as $type ) {
				$services = Services::get_for_doctor( $doctor_id, $type );

				if ( empty( $services ) ) {
					continue;
				}

				if ( ! isset( $map[ $doctor_id ] ) ) {
					$map[ $doctor_id ] = array();
				}

				$map[ $doctor_id ][ $type ] = array_map(
					function ( $service ) {
						return array(
							'id'     => $service['id'],
							'name'   => $service['name'],
							'charge' => $service['charge'],
						);
					},
					$services
				);
			}
		}

		return $map;
	}

	/**
	 * Doctor user ID => display name, for the Doctor Sessions "Add Session"
	 * modal's doctor picker.
	 *
	 * @return array
	 */
	private function doctor_options() {
		$query = new \WP_User_Query(
			array(
				'role'    => Roles::DOCTOR_ROLE,
				'orderby' => 'display_name',
			)
		);

		$options = array();

		foreach ( $query->get_results() as $doctor ) {
			$display_name          = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$options[ $doctor->ID ] = '' !== $display_name ? $display_name : $doctor->display_name;
		}

		return $options;
	}

	/**
	 * Gathers the real counts the Dashboard overview can honestly show.
	 *
	 * @return array
	 */
	private function overview_data() {
		$user_counts = count_users();

		return array(
			'total_doctors'      => isset( $user_counts['avail_roles'][ Roles::DOCTOR_ROLE ] ) ? (int) $user_counts['avail_roles'][ Roles::DOCTOR_ROLE ] : 0,
			'total_patients'     => isset( $user_counts['avail_roles'][ Roles::PATIENT_ROLE ] ) ? (int) $user_counts['avail_roles'][ Roles::PATIENT_ROLE ] : 0,
			'total_clinics'      => Clinics::total_count(),
			'total_appointments' => Appointments::total_count(),
		);
	}

	/**
	 * Doctor accounts still awaiting admin approval, most recently
	 * registered first (see Registration_Handler::handle_register()).
	 *
	 * @return array Row view-models, see row_data().
	 */
	private function pending_doctors() {
		$query = new \WP_User_Query(
			array(
				'role'       => Roles::DOCTOR_ROLE,
				'meta_key'   => 'doctor_ak_registration_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'    => 'registered',
				'order'      => 'DESC',
			)
		);

		return array_map( array( $this, 'row_data' ), $query->get_results() );
	}

	/**
	 * Count of doctor accounts awaiting admin approval, for the sidebar's
	 * "Doctor Requests" badge.
	 *
	 * @return int
	 */
	private static function pending_doctors_count() {
		$query = new \WP_User_Query(
			array(
				'role'        => Roles::DOCTOR_ROLE,
				'meta_key'    => 'doctor_ak_registration_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'      => 'ID',
				'count_total' => true,
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * Renders the Doctors/Patients accounts table.
	 *
	 * @param string $section 'doctors' or 'patients'.
	 * @return string
	 */
	private function users_section_html( $section ) {
		$role = $this->role_for_section( $section );

		$query = new \WP_User_Query(
			array(
				'role'    => $role,
				'orderby' => 'display_name',
			)
		);

		$results = $query->get_results();

		if ( 'doctors' === $section ) {
			// Pending/rejected registrations live in the "Doctor Requests"
			// tab, not the main roster — a pending doctor isn't "Active"
			// yet, and a rejected one was never accepted.
			$results = array_values(
				array_filter(
					$results,
					function ( $user ) {
						$status = get_user_meta( $user->ID, 'doctor_ak_registration_status', true );

						return '' === $status || 'approved' === $status;
					}
				)
			);
		}

		$users = array_map( array( $this, 'row_data' ), $results );

		$is_doctors_section = 'doctors' === $section;

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
		$status = in_array( $status, array( 'active', 'disabled' ), true ) ? $status : '';

		$specialization = '';

		if ( $is_doctors_section ) {
			$specialization = isset( $_GET['specialization'] ) ? sanitize_key( wp_unslash( $_GET['specialization'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$specialization = Specializations::is_valid( $specialization ) ? $specialization : '';
		}

		if ( '' !== $status ) {
			$users = array_values(
				array_filter(
					$users,
					function ( $row ) use ( $status ) {
						return ( 'disabled' === $status ) === $row['is_disabled'];
					}
				)
			);
		}

		if ( '' !== $specialization ) {
			$users = array_values(
				array_filter(
					$users,
					function ( $row ) use ( $specialization ) {
						return in_array( $specialization, $row['specializations'], true );
					}
				)
			);
		}

		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$section_url   = $dashboard_url ? add_query_arg( 'section', $section, $dashboard_url ) : '';

		return $this->template_loader->get_template(
			'dashboard/partials/admin-user-table.php',
			array(
				'users'              => $users,
				'section'            => $section,
				'appointments_url'   => $dashboard_url ? add_query_arg( 'section', 'appointments', $dashboard_url ) : '',
				'section_url'        => $section_url,
				'specializations'    => $is_doctors_section ? Specializations::get_all() : array(),
				'filters'            => array(
					'status'         => $status,
					'specialization' => $specialization,
				),
			)
		);
	}

	/**
	 * Renders the full-screen Add/Edit Doctor or Patient form (replaces the
	 * accounts table content area when `?view=form` is present) — same
	 * fields the old modal had, just rendered in-page instead of a popup.
	 *
	 * @param string $section 'doctors' or 'patients'.
	 * @return string
	 */
	private function user_form_screen_html( $section ) {
		$role    = $this->role_for_section( $section );
		$user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;
		$editing = ( $user && in_array( $role, (array) $user->roles, true ) ) ? $this->row_data( $user ) : null;

		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );

		return $this->template_loader->get_template(
			'dashboard/partials/admin-user-form-screen.php',
			array(
				'role'             => $role,
				'specializations'  => Specializations::get_all(),
				'section'          => $section,
				'list_url'         => $dashboard_url ? add_query_arg( 'section', $section, $dashboard_url ) : '',
				'editing_user'     => $editing,
				'clinic_locations' => Clinic_Locations::get_all(),
			)
		);
	}

	/**
	 * Builds a single user row's view-model for the table.
	 *
	 * @param \WP_User $user Doctor or patient user.
	 * @return array
	 */
	private function row_data( \WP_User $user ) {
		$specialization_slugs  = (array) get_user_meta( $user->ID, 'doctor_ak_specializations', true );
		$all_specializations   = Specializations::get_all();
		$specialization_labels = array_map(
			function ( $slug ) use ( $all_specializations ) {
				return isset( $all_specializations[ $slug ] ) ? $all_specializations[ $slug ] : $slug;
			},
			$specialization_slugs
		);

		$display_name = trim( $user->first_name . ' ' . $user->last_name );
		$display_name = '' !== $display_name ? $display_name : $user->display_name;

		$location      = '';
		$clinic_labels = array();

		if ( in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			$doctor_clinics = Clinics::get_for_doctor( $user->ID );
			$clinic_count   = count( $doctor_clinics );

			if ( $clinic_count > 0 ) {
				/* translators: %d: number of clinics. */
				$location = sprintf( _n( '%d clinic', '%d clinics', $clinic_count, 'doctor-ak-portal' ), $clinic_count );
			}

			$clinic_labels = array_values(
				array_unique(
					array_map(
						function ( $clinic ) {
							return Clinics::TYPE_VIDEO === $clinic['type'] ? __( 'Video Consultation', 'doctor-ak-portal' ) : $clinic['name'];
						},
						$doctor_clinics
					)
				)
			);
		}

		return array(
			'id'                          => $user->ID,
			'first_name'                  => $user->first_name,
			'last_name'                   => $user->last_name,
			'name'                        => $display_name,
			'email'                       => $user->user_email,
			'location'                    => $location,
			'phone'                       => get_user_meta( $user->ID, 'doctor_ak_phone_number', true ),
			'specializations'             => $specialization_slugs,
			'specialization_label'        => implode( ', ', $specialization_labels ),
			'specialization_labels'       => $specialization_labels,
			'clinic_labels'               => $clinic_labels,
			'is_disabled'                 => 'yes' === get_user_meta( $user->ID, 'doctor_ak_account_disabled', true ),
			'years_experience'            => get_user_meta( $user->ID, 'doctor_ak_years_experience', true ),
			'qualification'               => get_user_meta( $user->ID, 'doctor_ak_qualification', true ),
			'country'                     => get_user_meta( $user->ID, 'doctor_ak_country', true ),
			'city'                        => get_user_meta( $user->ID, 'doctor_ak_city', true ),
			'area'                        => get_user_meta( $user->ID, 'doctor_ak_area', true ),
			'short_description'           => get_user_meta( $user->ID, 'doctor_ak_short_description', true ),
			'expertise'                   => get_user_meta( $user->ID, 'doctor_ak_expertise', true ),
			'video_consultation_allowed'  => '0' !== get_user_meta( $user->ID, Clinics::VIDEO_CONSULTATION_ALLOWED_META_KEY, true ),
			'awards'                      => Doctor_Awards::get_for_doctor( $user->ID ),
			'avatar_url'                  => self::avatar_url( $user->ID ),
			'registered_date'             => mysql2date( get_option( 'date_format' ), $user->user_registered ),
		);
	}

	/**
	 * Resolves a user's uploaded profile picture, or '' if none set.
	 *
	 * @param int $user_id User ID.
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
	 * admin dashboard shortcode.
	 *
	 * @return bool
	 */
	private function is_dashboard_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
