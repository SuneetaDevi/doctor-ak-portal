<?php
/**
 * The core plugin class.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

use DoctorAKPortal\Admin\Swich_Settings;
use DoctorAKPortal\Frontend\Admin_Dashboard;
use DoctorAKPortal\Frontend\Admin_User_Handler;
use DoctorAKPortal\Frontend\Appointment_Handler;
use DoctorAKPortal\Frontend\Booking_Handler;
use DoctorAKPortal\Frontend\Booking_Page;
use DoctorAKPortal\Frontend\Booking_Trigger;
use DoctorAKPortal\Frontend\Clinic_Handler;
use DoctorAKPortal\Frontend\Doctor_Dashboard;
use DoctorAKPortal\Frontend\Doctor_Profile_View;
use DoctorAKPortal\Frontend\Doctors_Directory;
use DoctorAKPortal\Frontend\Forgot_Password_Handler;
use DoctorAKPortal\Frontend\Login_Handler;
use DoctorAKPortal\Frontend\Patient_Appointment_Handler;
use DoctorAKPortal\Frontend\Patient_Dashboard;
use DoctorAKPortal\Frontend\Profile_Handler;
use DoctorAKPortal\Frontend\Registration_Handler;
use DoctorAKPortal\Frontend\Service_Handler;
use DoctorAKPortal\Frontend\Shortcodes;
use DoctorAKPortal\Frontend\Site_Header;
use DoctorAKPortal\Frontend\Theme_Handler;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Orchestrates the plugin by loading dependencies and wiring hooks through
 * the Loader. Admin and public hook registration are populated in later
 * development phases (roles, authentication, shortcodes, dashboards).
 */
class Plugin {

	/**
	 * The hook loader responsible for registering actions and filters.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Unique plugin identifier, used as the text domain.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Sets plugin metadata and loads the hook loader.
	 */
	public function __construct() {
		$this->version     = defined( 'DOCTOR_AK_PORTAL_VERSION' ) ? DOCTOR_AK_PORTAL_VERSION : '1.0.0';
		$this->plugin_name = 'doctor-ak-portal';

		$this->loader = new Loader();

		Db_Installer::maybe_upgrade();

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Loads the plugin text domain for translation.
	 *
	 * @return void
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	/**
	 * Callback that loads the plugin text domain.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'doctor-ak-portal',
			false,
			dirname( DOCTOR_AK_PORTAL_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Registers hooks for admin-facing functionality.
	 *
	 * Populated in later phases (admin settings, role management, etc.).
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		$swich_settings = new Swich_Settings();
		$this->loader->add_action( 'admin_menu', $swich_settings, 'register_menu' );
		$this->loader->add_action( 'admin_init', $swich_settings, 'register_settings' );
	}

	/**
	 * Registers hooks for public-facing functionality.
	 *
	 * @return void
	 */
	private function define_public_hooks() {
		$site_header = new Site_Header( new Template_Loader() );
		$this->loader->add_action( 'after_setup_theme', $site_header, 'register_menu_location' );
		$this->loader->add_action( 'wp_enqueue_scripts', $site_header, 'enqueue_assets' );
		$this->loader->add_action( 'wp_body_open', $site_header, 'render' );
		$this->loader->add_filter( 'nav_menu_link_attributes', $site_header, 'add_booking_trigger_attributes', 10, 3 );

		$doctor_dashboard  = new Doctor_Dashboard( new Template_Loader() );
		$patient_dashboard = new Patient_Dashboard( new Template_Loader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $doctor_dashboard, 'enqueue_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $patient_dashboard, 'enqueue_assets' );

		$profile_handler = new Profile_Handler( new Template_Loader(), new Profile_Picture_Uploader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $profile_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_profile_upload_picture', $profile_handler, 'handle_upload_profile_picture' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_profile_update', $profile_handler, 'handle_update_profile' );

		$theme_handler = new Theme_Handler();
		$this->loader->add_action( 'wp_enqueue_scripts', $theme_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_toggle_theme', $theme_handler, 'handle_toggle_theme' );

		$clinic_handler = new Clinic_Handler();
		$this->loader->add_action( 'wp_enqueue_scripts', $clinic_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_clinic_save', $clinic_handler, 'handle_save_clinic' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_clinic_delete', $clinic_handler, 'handle_delete_clinic' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_clinic_save', $clinic_handler, 'handle_admin_save_clinic' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_clinic_delete', $clinic_handler, 'handle_admin_delete_clinic' );

		$doctors_directory   = new Doctors_Directory( new Template_Loader() );
		$doctor_profile_view = new Doctor_Profile_View( new Template_Loader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $doctors_directory, 'enqueue_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $doctor_profile_view, 'enqueue_assets' );

		$booking_page = new Booking_Page( new Template_Loader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $booking_page, 'enqueue_assets' );

		$booking_trigger = new Booking_Trigger();
		$this->loader->add_action( 'wp_enqueue_scripts', $booking_trigger, 'enqueue_assets' );

		$booking_handler = new Booking_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_book_appointment', $booking_handler, 'handle_book_appointment' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_book_appointment', $booking_handler, 'handle_book_appointment' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_available_slots', $booking_handler, 'handle_get_available_slots' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_available_slots', $booking_handler, 'handle_get_available_slots' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_month_availability', $booking_handler, 'handle_get_month_availability' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_month_availability', $booking_handler, 'handle_get_month_availability' );

		$appointments = new Appointments();
		$this->loader->add_action( 'init', $appointments, 'register_post_type' );
		$this->loader->add_action( 'doctor_ak_patient_dashboard_appointments', 'DoctorAKPortal\\Includes\\Appointments', 'render_patient_dashboard_appointments' );
		$this->loader->add_action( 'doctor_ak_doctor_dashboard_appointments', 'DoctorAKPortal\\Includes\\Appointments', 'render_doctor_dashboard_appointments' );
		$this->loader->add_action( 'doctor_ak_doctor_dashboard_recent_patients', 'DoctorAKPortal\\Includes\\Appointments', 'render_doctor_dashboard_recent_patients' );
		$this->loader->add_filter( 'doctor_ak_doctor_dashboard_today_appointments_count', 'DoctorAKPortal\\Includes\\Appointments', 'filter_today_appointments_count', 10, 2 );
		$this->loader->add_filter( 'doctor_ak_doctor_dashboard_video_consults_count', 'DoctorAKPortal\\Includes\\Appointments', 'filter_video_consults_count', 10, 2 );

		$this->loader->add_filter( 'doctor_ak_appointment_requires_payment', 'DoctorAKPortal\\Includes\\Swich_Payment', 'requires_payment', 10, 2 );
		$this->loader->add_action( 'wp_ajax_doctor_ak_swich_callback', 'DoctorAKPortal\\Includes\\Swich_Payment', 'handle_callback' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_swich_callback', 'DoctorAKPortal\\Includes\\Swich_Payment', 'handle_callback' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_swich_return', 'DoctorAKPortal\\Includes\\Swich_Payment', 'handle_return' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_swich_return', 'DoctorAKPortal\\Includes\\Swich_Payment', 'handle_return' );

		$admin_dashboard = new Admin_Dashboard( new Template_Loader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $admin_dashboard, 'enqueue_assets' );

		$admin_user_handler = new Admin_User_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_save_user', $admin_user_handler, 'handle_save_user' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_delete_user', $admin_user_handler, 'handle_delete_user' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_toggle_status', $admin_user_handler, 'handle_toggle_status' );

		$service_handler = new Service_Handler();
		$this->loader->add_action( 'wp_enqueue_scripts', $service_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_service_save', $service_handler, 'handle_save_service' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_service_delete', $service_handler, 'handle_delete_service' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_service_save', $service_handler, 'handle_admin_save_service' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_service_delete', $service_handler, 'handle_admin_delete_service' );

		$appointment_handler = new Appointment_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_save', $appointment_handler, 'handle_admin_save_appointment' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_delete', $appointment_handler, 'handle_admin_delete_appointment' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_print', $appointment_handler, 'handle_print' );

		$patient_appointment_handler = new Patient_Appointment_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_patient_cancel_appointment', $patient_appointment_handler, 'handle_cancel_appointment' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_patient_pay_now', $patient_appointment_handler, 'handle_pay_now' );

		$shortcodes = new Shortcodes( $doctor_dashboard, $patient_dashboard, $profile_handler, $doctors_directory, $doctor_profile_view, $admin_dashboard, $booking_page );
		$this->loader->add_action( 'init', $shortcodes, 'register' );

		$specialization_requests = new Specialization_Request();
		$this->loader->add_action( 'init', $specialization_requests, 'register_post_type' );

		$registration_handler = new Registration_Handler( new Authentication(), new Profile_Picture_Uploader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $registration_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_register', $registration_handler, 'handle_register' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_register', $registration_handler, 'handle_register' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_upload_profile_picture', $registration_handler, 'handle_profile_picture_upload' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_upload_profile_picture', $registration_handler, 'handle_profile_picture_upload' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_specialization_request', $registration_handler, 'handle_specialization_request' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_specialization_request', $registration_handler, 'handle_specialization_request' );

		$login_handler = new Login_Handler( new Authentication() );
		$this->loader->add_action( 'wp_enqueue_scripts', $login_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_login', $login_handler, 'handle_login' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_login', $login_handler, 'handle_login' );

		$forgot_password_handler = new Forgot_Password_Handler();
		$this->loader->add_action( 'wp_enqueue_scripts', $forgot_password_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_request_password_reset', $forgot_password_handler, 'handle_request_reset' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_request_password_reset', $forgot_password_handler, 'handle_request_reset' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_reset_password', $forgot_password_handler, 'handle_reset_password' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_reset_password', $forgot_password_handler, 'handle_reset_password' );

		$this->loader->add_action( 'save_post', 'DoctorAKPortal\\Includes\\Page_Finder', 'flush_cache' );
	}

	/**
	 * Runs the loader to execute all registered hooks with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Gets the plugin name/text domain identifier.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Gets the loader instance.
	 *
	 * @return Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Gets the current plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
