<?php
/**
 * Backs the [admin_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Page_Finder;
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
 * (Dashboard/Appointments/Encounters, Patients/Doctors/Receptionist,
 * Clinic/Services/Doctor Sessions) so the shape is in place end to end, but
 * only the Doctors, Patients, and Appointments sections are backed by real
 * data today — every other section renders an honest "coming soon"
 * placeholder instead of fabricated numbers or forms.
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
		),
		'Users'  => array(
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
			'settings' => 'Settings',
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
			'doctor-ak-portal-admin-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-admin-dashboard.css',
			array( 'doctor-ak-portal-booking-modal' ),
			Assets::version( 'assets/css/doctor-ak-admin-dashboard.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-admin-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-dashboard.js',
			array(),
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
				'doctor-ak-portal-admin-sessions',
				DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-admin-sessions.js',
				array(),
				Assets::version( 'assets/js/doctor-ak-admin-sessions.js' ),
				true
			);

			wp_localize_script(
				'doctor-ak-portal-admin-sessions',
				'dakAdminSessions',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
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

		$nav_groups = array();

		foreach ( self::NAV_GROUPS as $group_label => $items ) {
			$group_items = array();

			foreach ( $items as $slug => $label ) {
				$group_items[] = array(
					'slug'      => $slug,
					'label'     => $label,
					'url'       => $dashboard_url ? add_query_arg( 'section', $slug, $dashboard_url ) : '',
					'is_active' => $slug === $section,
				);
			}

			$nav_groups[] = array(
				'label' => $group_label,
				'items' => $group_items,
			);
		}

		$is_users_section = in_array( $section, array( 'doctors', 'patients' ), true );

		$modal_html = '';

		if ( $is_users_section ) {
			$modal_html = $this->user_modal_html( $section );
		} elseif ( 'doctor-sessions' === $section ) {
			$modal_html = $this->doctor_sessions_modal_html();
		} elseif ( 'services' === $section ) {
			$modal_html = $this->service_modal_html();
		} elseif ( 'video-consultation' === $section ) {
			$modal_html = $this->video_pricing_modal_html();
		} elseif ( 'appointments' === $section ) {
			$modal_html = $this->appointment_modal_html();
		}

		return array(
			'section'           => $section,
			'section_label'     => isset( $all_sections[ $section ] ) ? $all_sections[ $section ] : $section,
			'nav_groups'        => $nav_groups,
			'dashboard_url'     => $dashboard_url,
			'logout_url'        => wp_logout_url( Page_Finder::url_for_shortcode( 'doctor_login' ) ),
			'current_user'      => wp_get_current_user(),
			'content_html'      => $is_users_section ? $this->users_section_html( $section ) : $this->section_content_html( $section ),
			'modal_html'        => $modal_html,
			'role'              => $is_users_section ? $this->role_for_section( $section ) : '',
			'theme'             => Theme_Preference::get( get_current_user_id() ),
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
			return $this->template_loader->get_template(
				'dashboard/partials/admin-doctor-sessions.php',
				array( 'clinics' => Clinics::all_flat_for_admin() )
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

		$all_sections = self::all_sections();

		return $this->template_loader->get_template(
			'dashboard/partials/admin-placeholder.php',
			array(
				'section_label' => isset( $all_sections[ $section ] ) ? $all_sections[ $section ] : $section,
			)
		);
	}

	/**
	 * Renders the "Doctor Sessions" edit modal (shared single instance,
	 * populated client-side from the clicked row's data).
	 *
	 * @return string
	 */
	private function doctor_sessions_modal_html() {
		return $this->template_loader->get_template(
			'modal/admin-doctor-session-modal.php',
			array(
				'session_days'    => Clinics::session_days(),
				'doctor_options'  => $this->doctor_options(),
			)
		);
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

		$users = array_map( array( $this, 'row_data' ), $query->get_results() );

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
	 * Renders the Add/Edit Doctor or Patient modal.
	 *
	 * @param string $section 'doctors' or 'patients'.
	 * @return string
	 */
	private function user_modal_html( $section ) {
		return $this->template_loader->get_template(
			'modal/admin-user-modal.php',
			array(
				'role'            => $this->role_for_section( $section ),
				'specializations' => Specializations::get_all(),
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

		$location = '';

		if ( in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) ) {
			$clinic_count = count( Clinics::get_for_doctor( $user->ID ) );

			if ( $clinic_count > 0 ) {
				/* translators: %d: number of clinics. */
				$location = sprintf( _n( '%d clinic', '%d clinics', $clinic_count, 'doctor-ak-portal' ), $clinic_count );
			}
		}

		return array(
			'id'                    => $user->ID,
			'first_name'            => $user->first_name,
			'last_name'             => $user->last_name,
			'name'                  => $display_name,
			'email'                 => $user->user_email,
			'location'              => $location,
			'phone'                 => get_user_meta( $user->ID, 'doctor_ak_phone_number', true ),
			'specializations'       => $specialization_slugs,
			'specialization_label'  => implode( ', ', $specialization_labels ),
			'is_disabled'           => 'yes' === get_user_meta( $user->ID, 'doctor_ak_account_disabled', true ),
		);
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
