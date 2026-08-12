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
use DoctorAKPortal\Includes\Encounters;
use DoctorAKPortal\Includes\Locations;
use DoctorAKPortal\Includes\Medicines;
use DoctorAKPortal\Includes\Notification_Center;
use DoctorAKPortal\Includes\Notifications;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Revenue_Split;
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
 * Gates access to WordPress Administrators ('manage_options') and, with a
 * cut-down view, the Receptionist role (see is_receptionist(),
 * RECEPTIONIST_ALLOWED_SECTIONS): full management of Doctor and Patient
 * accounts, Services, Doctor Sessions/clinic locations, and Appointments
 * (create/edit/cancel/reschedule/mark paid). Everything else (Doctor
 * Requests, Receptionist staff-account management, Billing/Revenue,
 * Encounters, Video Consultation pricing, Roles & Permissions, Locations)
 * stays Administrator-only.
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
			'billing'     => 'Billing',
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
			'medicines'          => 'Medicines',
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
	 * Section slugs a logged-in Receptionist could ever structurally reach —
	 * the hard ceiling; everything else (Doctor Requests, the Receptionist
	 * staff-account tab itself, Billing/Revenue, Encounters, Video
	 * Consultation pricing, Roles & Permissions, Locations) stays
	 * administrator-only no matter what. Within this ceiling, Settings →
	 * Roles & Permissions lets an admin further switch individual sections
	 * off per-install (see receptionist_can_access(), which ANDs this list
	 * with Role_Permissions::is_tab_allowed()). Enforced server-side in
	 * requested_section() so a receptionist can't reach a disallowed section
	 * just by typing the URL — the sidebar only ever links to allowed ones,
	 * but that alone isn't a security boundary.
	 *
	 * @var array
	 */
	const RECEPTIONIST_ALLOWED_SECTIONS = array( 'dashboard', 'appointments', 'patients', 'doctors', 'clinic', 'services', 'medicines', 'doctor-sessions', 'settings', 'encounter' );

	/**
	 * Section slugs that exist and are reachable, but deliberately have no
	 * sidebar link — reached only by navigating there directly (e.g. the
	 * clinical Encounter detail screen, opened via "Open Encounter"/"Check
	 * In" on an appointment row, not a permanent nav item). Kept separate
	 * from NAV_GROUPS (which drives the sidebar) but still needs to pass
	 * requested_section()'s validation.
	 *
	 * @var array
	 */
	const HIDDEN_SECTIONS = array( 'encounter' );

	/**
	 * Whether a logged-in Receptionist may access a given section — must be
	 * within the hard RECEPTIONIST_ALLOWED_SECTIONS ceiling AND not switched
	 * off for the Receptionist role via Settings → Roles & Permissions.
	 *
	 * @param string $slug Section slug.
	 * @return bool
	 */
	private static function receptionist_can_access( $slug ) {
		return in_array( $slug, self::RECEPTIONIST_ALLOWED_SECTIONS, true )
			&& Role_Permissions::is_tab_allowed( Roles::RECEPTIONIST_ROLE, $slug );
	}

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
			'doctor-ak-portal-patient-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-patient-dashboard.css',
			array( 'doctor-ak-portal-dashboard' ),
			Assets::version( 'assets/css/doctor-ak-patient-dashboard.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-admin-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-admin-dashboard.css',
			array( 'doctor-ak-portal-booking-modal', 'doctor-ak-portal-registration', 'doctor-ak-portal-patient-dashboard' ),
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

		wp_enqueue_script(
			'doctor-ak-portal-notification-preferences',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-notification-preferences.js',
			array( 'doctor-ak-portal-notifications' ),
			Assets::version( 'assets/js/doctor-ak-notification-preferences.js' ),
			true
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

		if ( 'settings' === self::requested_section() ) {
			wp_enqueue_script(
				'doctor-ak-portal-admin-clinic-branding',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-clinic-branding.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-clinic-branding.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-clinic-branding',
				'dakClinicBranding',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( Clinic_Branding_Handler::NONCE_ACTION ),
				)
			);

			// Only a full admin gets the combined "Save Settings" button
			// (Clinic Branding + Notification Preferences) — a receptionist
			// has no Clinic Branding section on their cut-down Settings view
			// (see prepare_data()'s 'settings' branch), so keeps the
			// Notification Preferences card's own standalone save button.
			if ( ! self::is_receptionist() ) {
				wp_enqueue_script(
					'doctor-ak-portal-admin-settings-save',
					DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-settings-save.js',
					array( 'doctor-ak-portal-admin-clinic-branding', 'doctor-ak-portal-notification-preferences' ),
					Assets::version( 'assets/js/doctor-ak-admin-settings-save.js' ),
					true
				);
			}
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

		if ( 'medicines' === self::requested_section() ) {
			wp_enqueue_style(
				'doctor-ak-portal-registration',
				DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
				array( 'doctor-ak-portal-auth' ),
				Assets::version( 'assets/css/doctor-ak-registration.css' )
			);

			wp_enqueue_script(
				'doctor-ak-portal-admin-medicines',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-medicines.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-medicines.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-medicines',
				'dakAdminMedicines',
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

		if ( in_array( self::requested_section(), array( 'doctor-requests', 'dashboard' ), true ) ) {
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

		// Dashboard overview's "Latest appointments" widget only needs the
		// standalone Mark Paid action (see doctor-ak-admin-appointments.js),
		// not the full Add/Edit/View/Refund modal machinery the Appointments
		// section above enqueues its extra CSS for.
		if ( 'dashboard' === self::requested_section() ) {
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

		// The "Check In" action appears on both the Appointments table and
		// the Dashboard overview's "Latest appointments" widget.
		if ( in_array( self::requested_section(), array( 'appointments', 'dashboard' ), true ) ) {
			wp_enqueue_script(
				'doctor-ak-portal-check-in',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-check-in.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-check-in.js' ),
				true
			);

			$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );

			wp_localize_script(
				'doctor-ak-portal-check-in',
				'dakCheckIn',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( Encounter_Handler::NONCE_ACTION ),
					'confirmMessage' => __( 'Check this patient in and open their encounter?', 'doctor-ak-portal' ),
					'genericError'   => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
					'encounterUrl'   => $dashboard_url ? add_query_arg( 'section', 'encounter', $dashboard_url ) : '',
				)
			);
		}

		if ( 'encounter' === self::requested_section() ) {
			wp_enqueue_script(
				'doctor-ak-portal-encounter',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-encounter.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-encounter.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-encounter',
				'dakEncounter',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( Encounter_Handler::NONCE_ACTION ),
					'genericError' => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
					'confirmCloseMessage' => __( 'Close this encounter and check the patient out? This cannot be undone.', 'doctor-ak-portal' ),
				)
			);
		}

		if ( 'encounters' === self::requested_section() ) {
			wp_enqueue_script(
				'doctor-ak-portal-admin-encounters',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-encounters.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-encounters.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-encounters',
				'dakAdminEncounters',
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
			$section = 'dashboard';
		} else {
			$section = sanitize_key( wp_unslash( $_GET['section'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$section = ( array_key_exists( $section, self::all_sections() ) || in_array( $section, self::HIDDEN_SECTIONS, true ) ) ? $section : 'dashboard';
		}

		if ( self::is_receptionist() && ! self::receptionist_can_access( $section ) ) {
			return 'dashboard';
		}

		return $section;
	}

	/**
	 * Whether the current user is logged in as a Receptionist (and not also
	 * a full Administrator — `manage_options` always wins/sees everything).
	 *
	 * @return bool
	 */
	private static function is_receptionist() {
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		return in_array( Roles::RECEPTIONIST_ROLE, (array) wp_get_current_user()->roles, true );
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

		$user             = wp_get_current_user();
		$is_receptionist  = in_array( Roles::RECEPTIONIST_ROLE, (array) $user->roles, true );

		if ( ! current_user_can( 'manage_options' ) && ! $is_receptionist ) {
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
	 * AJAX: the topbar search box — up to 5 matches each of Doctors,
	 * Patients, and Appointments whose name/email contains the query, for a
	 * click-to-jump dropdown. Doctors/Patients link to their row in the
	 * Users table (`#dak-user-{id}`, see admin-user-table.php); Appointments
	 * link to their row in the Appointments section (`#dak-appointment-{id}`).
	 * Both anchors reuse the existing `:target` highlight already built for
	 * notification deep-links.
	 *
	 * @return void
	 */
	public function handle_search() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! self::is_receptionist() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

		if ( mb_strlen( $query ) < 2 ) {
			wp_send_json_success(
				array(
					'doctors'      => array(),
					'patients'     => array(),
					'appointments' => array(),
				)
			);
		}

		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$doctors_url   = $dashboard_url ? add_query_arg( 'section', 'doctors', $dashboard_url ) : '';
		$patients_url  = $dashboard_url ? add_query_arg( 'section', 'patients', $dashboard_url ) : '';
		$appts_url     = $dashboard_url ? add_query_arg( 'section', 'appointments', $dashboard_url ) : '';

		wp_send_json_success(
			array(
				'doctors'      => self::search_users_for_dropdown( Roles::DOCTOR_ROLE, $query, $doctors_url ),
				'patients'     => self::search_users_for_dropdown( Roles::PATIENT_ROLE, $query, $patients_url ),
				'appointments' => self::search_appointments_for_dropdown( $query, $appts_url ),
			)
		);
	}

	/**
	 * Up to 5 users of a given role whose name/email match the query, shaped
	 * for handle_search()'s dropdown response.
	 *
	 * @param string $role       WP role slug.
	 * @param string $query      Search term.
	 * @param string $section_url Base URL of the section the result links into ('' if unresolvable).
	 * @return array
	 */
	private static function search_users_for_dropdown( $role, $query, $section_url ) {
		$users = get_users(
			array(
				'role'           => $role,
				'search'         => '*' . $query . '*',
				'search_columns' => array( 'display_name', 'user_email' ),
				'number'         => 5,
				'orderby'        => 'display_name',
			)
		);

		return array_map(
			function ( $user ) use ( $section_url ) {
				return array(
					'label'    => $user->display_name,
					'sublabel' => $user->user_email,
					'url'      => $section_url ? esc_url_raw( $section_url . '#dak-user-' . $user->ID ) : '',
				);
			},
			$users
		);
	}

	/**
	 * Up to 5 appointments whose patient/doctor/guest name contains the
	 * query, shaped for handle_search()'s dropdown response. Scans the same
	 * (already capped-at-200) recent-appointments set the Appointments
	 * section itself loads, so this stays cheap without a dedicated query.
	 *
	 * @param string $query         Search term.
	 * @param string $appointments_url Base URL of the Appointments section ('' if unresolvable).
	 * @return array
	 */
	private static function search_appointments_for_dropdown( $query, $appointments_url ) {
		$needle  = mb_strtolower( $query );
		$results = array();

		foreach ( Appointments::all_for_admin() as $row ) {
			$haystack = mb_strtolower( $row['patient_name'] . ' ' . $row['doctor_name'] . ' ' . $row['guest_name'] );

			if ( false === mb_strpos( $haystack, $needle ) ) {
				continue;
			}

			$results[] = array(
				'label'    => sprintf(
					/* translators: 1: patient name, 2: doctor name. */
					__( '%1$s with Dr. %2$s', 'doctor-ak-portal' ),
					$row['patient_name'],
					$row['doctor_name']
				),
				'sublabel' => $row['date'] . ' · ' . $row['time'],
				'url'      => $appointments_url ? esc_url_raw( $appointments_url . '#dak-appointment-' . $row['id'] ) : '',
			);

			if ( count( $results ) >= 5 ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * AJAX: re-renders the Appointments section for a new set of filters,
	 * without a full page reload. Reuses section_content_html() unchanged by
	 * populating $_GET from the posted filters first, so the returned markup
	 * is byte-for-byte what a normal page load with the same query args
	 * would have produced.
	 *
	 * @return void
	 */
	public function handle_filter_appointments() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! self::is_receptionist() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		foreach ( array( 'patient_id', 'date_from', 'date_to', 'doctor_id', 'payment_status', 'range' ) as $key ) {
			$_GET[ $key ] = isset( $_POST[ $key ] ) ? $_POST[ $key ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce already verified above; section_content_html() sanitizes each value itself, same as it does for a real $_GET.
		}

		wp_send_json_success( array( 'html' => $this->section_content_html( 'appointments' ) ) );
	}

	/**
	 * AJAX: re-renders the Doctors/Patients/Receptionists table for a new
	 * set of filters, without a full page reload. Same $_GET-populating
	 * approach as handle_filter_appointments().
	 *
	 * @return void
	 */
	public function handle_filter_users() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';

		if ( ! in_array( $section, array( 'doctors', 'patients', 'receptionist' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid section.', 'doctor-ak-portal' ) ), 400 );
		}

		$is_receptionist = self::is_receptionist();

		if ( ! current_user_can( 'manage_options' ) && ! ( $is_receptionist && self::receptionist_can_access( $section ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		foreach ( array( 'status', 'specialization' ) as $key ) {
			$_GET[ $key ] = isset( $_POST[ $key ] ) ? $_POST[ $key ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce already verified above; users_section_html() sanitizes each value itself, same as it does for a real $_GET.
		}

		wp_send_json_success( array( 'html' => $this->users_section_html( $section ) ) );
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
		$is_receptionist            = self::is_receptionist();

		$nav_groups = array();

		foreach ( self::NAV_GROUPS as $group_label => $items ) {
			$group_items = array();

			foreach ( $items as $slug => $label ) {
				if ( $is_receptionist && ! self::receptionist_can_access( $slug ) ) {
					continue;
				}

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

			if ( empty( $group_items ) ) {
				continue;
			}

			$nav_groups[] = array(
				'label' => $group_label,
				'items' => $group_items,
			);
		}

		$is_users_section  = in_array( $section, array( 'doctors', 'patients', 'receptionist' ), true );
		// Receptionists get full Add/Edit access to the Doctors/Patients
		// tables (see RECEPTIONIST_ALLOWED_SECTIONS) but the 'receptionist'
		// staff-account tab itself stays administrator-only — it's not in
		// RECEPTIONIST_ALLOWED_SECTIONS so requested_section() already
		// redirects a receptionist away from it before this point, but the
		// explicit check here is defense-in-depth against a form view being
		// reached some other way.
		$is_user_form_view = $is_users_section && self::is_user_form_view()
			&& ( ! $is_receptionist || in_array( $section, array( 'doctors', 'patients' ), true ) );

		$modal_html = '';

		if ( 'services' === $section ) {
			$modal_html = $this->service_modal_html();
		} elseif ( 'medicines' === $section ) {
			$modal_html = $this->medicine_modal_html();
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
			'is_receptionist'   => $is_receptionist,
			'theme'             => Theme_Preference::get( get_current_user_id() ),
			'unread_notifications_count' => $unread_notifications_count,
			'notifications_url'          => $dashboard_url ? add_query_arg( 'section', 'notifications', $dashboard_url ) : '',
		);
	}

	/**
	 * A user's display name, preferring first+last name over the WordPress
	 * display_name (which may just be their username) — same fallback
	 * row_data() uses for the accounts table.
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	private static function display_name( \WP_User $user ) {
		$name = trim( $user->first_name . ' ' . $user->last_name );

		return '' !== $name ? $name : $user->display_name;
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
	 * @param string $section 'doctors', 'patients', or 'receptionist'.
	 * @return string
	 */
	private function role_for_section( $section ) {
		if ( 'patients' === $section ) {
			return Roles::PATIENT_ROLE;
		}

		if ( 'receptionist' === $section ) {
			return Roles::RECEPTIONIST_ROLE;
		}

		return Roles::DOCTOR_ROLE;
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
			$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
			$selected_date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$unread_count  = Notification_Center::unread_count( get_current_user_id() );

			return $this->template_loader->get_template(
				'dashboard/partials/notifications-list.php',
				array(
					'notification_groups' => Notification_Center::group_by_recency( Notification_Center::for_user( get_current_user_id(), 100, $selected_date ) ),
					'appointments_url'    => $dashboard_url ? add_query_arg( 'section', 'appointments', $dashboard_url ) : '',
					'selected_date'       => $selected_date,
					'filter_field_name'   => 'section',
					'filter_field_value'  => 'notifications',
					'page_title'          => __( 'Notifications', 'doctor-ak-portal' ),
					'page_subtitle'       => sprintf(
						/* translators: %d: unread notification count. */
						_n( '%d unread · Clinic-wide activity', '%d unread · Clinic-wide activity', $unread_count, 'doctor-ak-portal' ),
						$unread_count
					),
				)
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
			$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$doctor_id  = isset( $_GET['doctor_id'] ) ? absint( wp_unslash( $_GET['doctor_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$payment_status = isset( $_GET['payment_status'] ) ? sanitize_key( wp_unslash( $_GET['payment_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			// Defaults to 'upcoming' only on first load (no `range` in the
			// request at all) — once a range is explicitly submitted
			// (including '' for "All"), that choice sticks.
			$range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'upcoming'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$range = array_key_exists( $range, Appointments::range_options() ) ? $range : 'upcoming';

			// Query bounds only — $date_from/$date_to themselves stay as
			// whatever the admin actually typed, so the From/To fields don't
			// appear to have a value the admin never entered.
			list( $query_date_from, $query_date_to ) = Appointments::apply_range_filter( $range, $date_from, $date_to );

			$payment_status_options = array(
				Appointments::PAYMENT_STATUS_PENDING => __( 'Pending', 'doctor-ak-portal' ),
				Appointments::PAYMENT_STATUS_PAID    => __( 'Paid', 'doctor-ak-portal' ),
			);
			$payment_status = array_key_exists( $payment_status, $payment_status_options ) ? $payment_status : '';

			$patient      = $patient_id > 0 ? get_userdata( $patient_id ) : false;
			$patient_name = '';

			if ( $patient ) {
				$patient_name = trim( $patient->first_name . ' ' . $patient->last_name );
				$patient_name = '' !== $patient_name ? $patient_name : $patient->display_name;
			}

			$dashboard_url     = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
			$appointments_url  = $dashboard_url ? add_query_arg( 'section', 'appointments', $dashboard_url ) : '';

			$doctors = get_users(
				array(
					'role'    => Roles::DOCTOR_ROLE,
					'orderby' => 'display_name',
					'fields'  => array( 'ID', 'display_name' ),
				)
			);

			return $this->template_loader->get_template(
				'dashboard/partials/admin-appointments.php',
				array(
					'appointments'      => Appointments::all_for_admin(
						array(
							'patient_id'     => $patient_id,
							'date_from'      => $query_date_from,
							'date_to'        => $query_date_to,
							'doctor_id'      => $doctor_id,
							'payment_status' => $payment_status,
						)
					),
					'filtered_patient'  => $patient_name,
					'appointments_url'  => $appointments_url,
					'doctors'                => $doctors,
					'payment_status_options' => $payment_status_options,
					'range_options'          => Appointments::range_options(),
					'is_receptionist'        => self::is_receptionist(),
					'filters'           => array(
						'patient_id'     => $patient_id,
						'date_from'      => $date_from,
						'date_to'        => $date_to,
						'doctor_id'      => $doctor_id,
						'payment_status' => $payment_status,
						'range'          => $range,
					),
				)
			);
		}

		if ( 'encounters' === $section ) {
			$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$doctor_id  = isset( $_GET['doctor_id'] ) ? absint( wp_unslash( $_GET['doctor_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$patient_id = isset( $_GET['patient_id'] ) ? absint( wp_unslash( $_GET['patient_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$status     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$status     = in_array( $status, array( Encounters::STATUS_OPEN, Encounters::STATUS_CLOSED ), true ) ? $status : '';

			$dashboard_url    = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
			$encounters_url   = $dashboard_url ? add_query_arg( 'section', 'encounters', $dashboard_url ) : '';
			$encounter_url    = $dashboard_url ? add_query_arg( 'section', 'encounter', $dashboard_url ) : '';
			$filtered_patient = $patient_id > 0 ? get_userdata( $patient_id ) : false;

			$doctors = get_users(
				array(
					'role'    => Roles::DOCTOR_ROLE,
					'orderby' => 'display_name',
					'fields'  => array( 'ID', 'display_name' ),
				)
			);

			return $this->template_loader->get_template(
				'dashboard/partials/admin-encounters.php',
				array(
					'encounters'       => Encounters::all_flat_for_admin(
						array(
							'date_from'  => $date_from,
							'date_to'    => $date_to,
							'doctor_id'  => $doctor_id,
							'patient_id' => $patient_id,
							'status'     => $status,
						)
					),
					'encounters_url'   => $encounters_url,
					'encounter_url'    => $encounter_url,
					'doctors'          => $doctors,
					'filtered_patient' => $filtered_patient ? self::display_name( $filtered_patient ) : '',
					'filters'          => array(
						'date_from'  => $date_from,
						'date_to'    => $date_to,
						'doctor_id'  => $doctor_id,
						'patient_id' => $patient_id,
						'status'     => $status,
					),
				)
			);
		}

		if ( 'billing' === $section ) {
			$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

			$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
			$billing_url   = $dashboard_url ? add_query_arg( 'section', 'billing', $dashboard_url ) : '';

			return $this->template_loader->get_template(
				'dashboard/partials/admin-billing.php',
				array(
					'invoices'    => Appointments::all_for_admin(
						array(
							'payment_status' => Appointments::PAYMENT_STATUS_PAID,
							'date_from'      => $date_from,
							'date_to'        => $date_to,
						)
					),
					'revenue'     => Appointments::revenue_summary(),
					'net_dues'    => Appointments::net_dues_by_doctor(),
					'billing_url' => $billing_url,
					'filters'     => array(
						'date_from' => $date_from,
						'date_to'   => $date_to,
					),
				)
			);
		}

		if ( 'doctor-sessions' === $section ) {
			if ( self::is_session_form_view() ) {
				return $this->session_form_screen_html( $section );
			}

			$doctor_id       = isset( $_GET['doctor_id'] ) ? absint( wp_unslash( $_GET['doctor_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$filtered_doctor = $doctor_id > 0 ? get_user_by( 'id', $doctor_id ) : false;

			return $this->template_loader->get_template(
				'dashboard/partials/admin-doctor-sessions.php',
				array(
					'clinics'          => Clinics::all_flat_for_admin( array( 'doctor_id' => $doctor_id ) ),
					'section_url'      => add_query_arg( 'section', $section, Page_Finder::url_for_shortcode( self::SHORTCODE_TAG ) ),
					'filtered_doctor'  => $filtered_doctor ? self::display_name( $filtered_doctor ) : '',
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
			$doctor_id       = isset( $_GET['doctor_id'] ) ? absint( wp_unslash( $_GET['doctor_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$filtered_doctor = $doctor_id > 0 ? get_user_by( 'id', $doctor_id ) : false;

			return $this->template_loader->get_template(
				'dashboard/partials/admin-services.php',
				array(
					'services'         => Services::all_flat_for_admin( array( 'doctor_id' => $doctor_id ) ),
					'section_url'      => add_query_arg( 'section', $section, Page_Finder::url_for_shortcode( self::SHORTCODE_TAG ) ),
					'filtered_doctor'  => $filtered_doctor ? self::display_name( $filtered_doctor ) : '',
				)
			);
		}

		if ( 'medicines' === $section ) {
			$doctor_id       = isset( $_GET['doctor_id'] ) ? absint( wp_unslash( $_GET['doctor_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$filtered_doctor = $doctor_id > 0 ? get_user_by( 'id', $doctor_id ) : false;

			return $this->template_loader->get_template(
				'dashboard/partials/admin-medicines.php',
				array(
					'medicines'        => Medicines::all_flat_for_admin( array( 'doctor_id' => $doctor_id ) ),
					'section_url'      => add_query_arg( 'section', $section, Page_Finder::url_for_shortcode( self::SHORTCODE_TAG ) ),
					'filtered_doctor'  => $filtered_doctor ? self::display_name( $filtered_doctor ) : '',
				)
			);
		}

		if ( 'video-consultation' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-video-consultation.php',
				array( 'pricing_rows' => Video_Pricing::all_flat_for_admin() )
			);
		}

		if ( 'encounter' === $section ) {
			$encounter_id = isset( $_GET['encounter_id'] ) ? absint( wp_unslash( $_GET['encounter_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$encounter    = $encounter_id > 0 ? Encounters::find( $encounter_id ) : null;

			if ( empty( $encounter )
				|| ( self::is_receptionist() && ! current_user_can( 'doctor_ak_manage_appointments' ) )
			) {
				$encounter_id = 0;
			}

			$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );

			// Reuses the doctor dashboard's own encounter template verbatim —
			// the screen (and its JS, see Doctor_Dashboard::enqueue_assets())
			// is identical for both audiences, just reached via a different
			// dashboard shell/URL scheme ('tab' vs 'section').
			return $this->template_loader->get_template(
				'dashboard/partials/doctor-encounter.php',
				array(
					'encounter_id'     => $encounter_id,
					'appointments_url' => $dashboard_url ? add_query_arg( 'section', 'appointments', $dashboard_url ) : '',
				)
			);
		}

		if ( 'settings' === $section ) {
			$notify_preferences = Notifications::user_preferences( get_current_user_id() );
			$settings_tab_html  = $this->template_loader->get_template(
				'dashboard/partials/dashboard-settings-tab.php',
				array(
					'notify_booking'       => $notify_preferences['booking'],
					'notify_paid'          => $notify_preferences['paid'],
					'notify_cancelled'     => $notify_preferences['cancelled'],
					'notify_announcements' => $notify_preferences['announcements'],
					// A full admin gets one combined "Save Settings" button
					// covering this plus Clinic Branding (see
					// admin-settings-section.php) instead of this section's
					// own button; a receptionist has no Clinic Branding
					// section to combine with, so keeps its own button.
					'show_save_button' => self::is_receptionist(),
				)
			);

			// Receptionists get the same cut-down Settings view as the
			// Doctor/Patient dashboards (Appearance + their own Notification
			// preferences) — Clinic Branding is global site config and stays
			// Administrator-only, same as everything else RECEPTIONIST_ALLOWED_SECTIONS
			// doesn't carve an exception for.
			if ( self::is_receptionist() ) {
				return '<div class="dak-dashboard-greeting"><h1>' . esc_html__( 'Settings', 'doctor-ak-portal' ) . '</h1><p>' . esc_html__( 'Appearance and your own notification preferences', 'doctor-ak-portal' ) . '</p></div><section class="dak-dashboard-card dak-dashboard-profile-form">' . $settings_tab_html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $settings_tab_html is dashboard-settings-tab.php output, which escapes its own output; the surrounding markup is static/escaped inline.
			}

			return $this->template_loader->get_template(
				'dashboard/partials/admin-settings-section.php',
				array(
					'settings_tab_html' => $settings_tab_html,
					'clinic_name'       => get_option( Site_Footer::OPTION_CLINIC_NAME, '' ),
					'clinic_phone'      => get_option( Site_Footer::OPTION_CLINIC_PHONE, '' ),
					'clinic_address'    => get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' ),
					'clinic_logo_url'   => Site_Footer::bundled_logo_url(),
				)
			);
		}

		if ( 'role-permissions' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-role-permissions.php',
				array(
					'doctor_tabs'       => Role_Permissions::doctor_tabs(),
					'patient_tabs'      => Role_Permissions::patient_tabs(),
					'receptionist_tabs' => Role_Permissions::receptionist_tabs(),
					'saved'             => Role_Permissions::get_all(),
				)
			);
		}

		if ( 'locations' === $section ) {
			return $this->template_loader->get_template(
				'dashboard/partials/admin-locations.php',
				array(
					'countries'       => Locations::get_all(),
					'country_names'   => Locations::all_country_names(),
					'city_names'      => Locations::suggested_city_names(),
					'area_names'      => Locations::suggested_area_names(),
				)
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
	 * Renders the "Add/Edit Medicine" modal (shared single instance,
	 * populated client-side from the clicked row's data).
	 *
	 * @return string
	 */
	private function medicine_modal_html() {
		return $this->template_loader->get_template(
			'modal/admin-medicine-modal.php',
			array(
				'doctor_options' => $this->doctor_options(),
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
	 * Doctor user ID => { name, is_disabled }, for every admin doctor-picker
	 * <select> (Add/Edit Session, Service, Video Pricing, Appointment) — a
	 * deactivated doctor still appears (so existing records referencing them
	 * stay legible) but is rendered disabled with a "(deactivated)" suffix,
	 * see e.g. admin-session-form-screen.php.
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
			$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );

			$options[ $doctor->ID ] = array(
				'name'        => '' !== $display_name ? $display_name : $doctor->display_name,
				'is_disabled' => 'yes' === get_user_meta( $doctor->ID, 'doctor_ak_account_disabled', true ),
			);
		}

		return $options;
	}

	/**
	 * Gathers the real counts the Dashboard overview can honestly show.
	 *
	 * @return array
	 */
	private function overview_data() {
		$user_counts   = count_users();
		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$today         = current_time( 'Y-m-d' );

		$upcoming = Appointments::all_for_admin( array( 'date_from' => $today ) );
		// all_for_admin() sorts furthest-future-first; the dashboard widget
		// wants soonest-first instead.
		$latest_appointments = array_slice( array_reverse( $upcoming ), 0, 6 );

		return array(
			'total_doctors'        => isset( $user_counts['avail_roles'][ Roles::DOCTOR_ROLE ] ) ? (int) $user_counts['avail_roles'][ Roles::DOCTOR_ROLE ] : 0,
			'total_patients'       => isset( $user_counts['avail_roles'][ Roles::PATIENT_ROLE ] ) ? (int) $user_counts['avail_roles'][ Roles::PATIENT_ROLE ] : 0,
			'total_clinics'        => Clinics::total_count(),
			'total_appointments'   => Appointments::total_count(),
			'appointments_today'   => count( Appointments::all_for_admin( array( 'date' => $today ) ) ),
			'total_revenue'        => Appointments::revenue_summary()['total'],
			'revenue_this_month'   => Appointments::revenue_summary()['this_month'],
			'pending_doctors_count' => self::pending_doctors_count(),
			'pending_doctors'      => array_slice( $this->pending_doctors(), 0, 3 ),
			'latest_appointments'  => $latest_appointments,
			'revenue_chart'        => Appointments::revenue_by_day( 14 ),
			'status_chart'         => Appointments::status_counts(),
			'clinic_name'          => get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' ),
			'clinic_address'       => get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' ),
			'appointments_url'     => $dashboard_url ? add_query_arg( 'section', 'appointments', $dashboard_url ) : '',
			'doctor_requests_url'  => $dashboard_url ? add_query_arg( 'section', 'doctor-requests', $dashboard_url ) : '',
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

		$results           = $query->get_results();
		$clinics_by_doctor = Clinics::get_for_doctors( wp_list_pluck( $results, 'ID' ) );

		return array_map(
			function ( $user ) use ( $clinics_by_doctor ) {
				return $this->row_data( $user, $clinics_by_doctor );
			},
			$results
		);
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

		$clinics_by_doctor = 'doctors' === $section ? Clinics::get_for_doctors( wp_list_pluck( $results, 'ID' ) ) : array();

		$users = array_map(
			function ( $user ) use ( $clinics_by_doctor ) {
				return $this->row_data( $user, $clinics_by_doctor );
			},
			$results
		);

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
				// Encounters is administrator-only (not in
				// RECEPTIONIST_ALLOWED_SECTIONS), so this stays empty for a
				// receptionist viewing the read-only Patients table — same
				// as how the template already hides admin-only actions for
				// them via $read_only.
				'encounters_url'     => ( $dashboard_url && current_user_can( 'manage_options' ) ) ? add_query_arg( 'section', 'encounters', $dashboard_url ) : '',
				'services_url'       => $dashboard_url ? add_query_arg( 'section', 'services', $dashboard_url ) : '',
				'doctor_sessions_url' => $dashboard_url ? add_query_arg( 'section', 'doctor-sessions', $dashboard_url ) : '',
				'section_url'        => $section_url,
				'specializations'    => $is_doctors_section ? Specializations::get_all() : array(),
				'filters'            => array(
					'status'         => $status,
					'specialization' => $specialization,
				),
				// A receptionist has full Add/Edit/Deactivate/Delete access
				// to the Doctors/Patients tables. Only a real administrator
				// reaches the 'receptionist' staff-account section at all
				// (see RECEPTIONIST_ALLOWED_SECTIONS) — the section-slug
				// check below is defense-in-depth in case that section is
				// ever reached some other way.
				'read_only'          => self::is_receptionist() && ! in_array( $section, array( 'doctors', 'patients' ), true ),
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
	 * @param \WP_User   $user              Doctor or patient user.
	 * @param array|null $clinics_by_doctor Optional pre-fetched doctor_id => clinics map (see Clinics::get_for_doctors()) — pass this when building many rows at once so each row doesn't run its own clinics query. Falls back to a single-doctor query when omitted (fine for one-off lookups like the Add/Edit Doctor form).
	 * @return array
	 */
	private function row_data( \WP_User $user, $clinics_by_doctor = null ) {
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

		$location            = '';
		$clinic_labels       = array();
		$clinic_location_id  = 0;
		$clinic_location_label = '';

		if ( in_array( Roles::PATIENT_ROLE, (array) $user->roles, true ) ) {
			$clinic_location_id = (int) get_user_meta( $user->ID, Clinic_Locations::PATIENT_META_KEY, true );

			if ( $clinic_location_id > 0 ) {
				$clinic_location = Clinic_Locations::find( $clinic_location_id );

				if ( $clinic_location ) {
					$clinic_location_label = sprintf( '%1$s — %2$s, %3$s', $clinic_location['name'], $clinic_location['area_label'], $clinic_location['city_label'] );
				}
			}
		}

		if ( in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			$doctor_clinics = null !== $clinics_by_doctor
				? ( isset( $clinics_by_doctor[ $user->ID ] ) ? $clinics_by_doctor[ $user->ID ] : array() )
				: Clinics::get_for_doctor( $user->ID );
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
			'clinic_location_id'          => $clinic_location_id,
			'clinic_location_label'       => $clinic_location_label,
			'is_disabled'                 => 'yes' === get_user_meta( $user->ID, 'doctor_ak_account_disabled', true ),
			'is_discharged'               => 'yes' === get_user_meta( $user->ID, 'doctor_ak_patient_discharged', true ),
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
			'payment_model'               => Revenue_Split::get_for_doctor( $user->ID )['payment_model'],
			'doctor_share_percent'        => Revenue_Split::get_for_doctor( $user->ID )['doctor_share_percent'],
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
