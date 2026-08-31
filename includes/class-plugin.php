<?php
/**
 * The core plugin class.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

use DoctorAKPortal\Admin\Footer_Settings;
use DoctorAKPortal\Admin\Locations_Settings;
use DoctorAKPortal\Admin\Role_Permissions_Settings;
use DoctorAKPortal\Admin\Notification_Settings;
use DoctorAKPortal\Admin\Swich_Settings;
use DoctorAKPortal\Frontend\Admin_Dashboard;
use DoctorAKPortal\Frontend\Admin_User_Handler;
use DoctorAKPortal\Frontend\Appointment_Handler;
use DoctorAKPortal\Frontend\Booking_Handler;
use DoctorAKPortal\Frontend\Booking_Page;
use DoctorAKPortal\Frontend\Booking_Trigger;
use DoctorAKPortal\Frontend\Clinic_Handler;
use DoctorAKPortal\Frontend\Doctor_Appointment_Handler;
use DoctorAKPortal\Frontend\Clinic_Branding_Handler;
use DoctorAKPortal\Frontend\Locations_Handler;
use DoctorAKPortal\Frontend\Role_Permissions_Handler;
use DoctorAKPortal\Frontend\Doctor_Patient_Handler;
use DoctorAKPortal\Frontend\Doctor_Dashboard;
use DoctorAKPortal\Frontend\Doctor_Profile_View;
use DoctorAKPortal\Frontend\Doctor_Requests_Handler;
use DoctorAKPortal\Frontend\Doctors_Directory;
use DoctorAKPortal\Frontend\Encounter_Handler;
use DoctorAKPortal\Frontend\Featured_Doctors;
use DoctorAKPortal\Frontend\Forgot_Password_Handler;
use DoctorAKPortal\Frontend\Google_Reviews_Handler;
use DoctorAKPortal\Frontend\Home_Page;
use DoctorAKPortal\Frontend\Home_Testimonials_Handler;
use DoctorAKPortal\Frontend\Home_Videos_Handler;
use DoctorAKPortal\Frontend\Login_Handler;
use DoctorAKPortal\Frontend\Notification_Handler;
use DoctorAKPortal\Frontend\Patient_Appointment_Handler;
use DoctorAKPortal\Frontend\Patient_Dashboard;
use DoctorAKPortal\Frontend\Profile_Handler;
use DoctorAKPortal\Frontend\Registration_Handler;
use DoctorAKPortal\Frontend\Clinic_Location_Handler;
use DoctorAKPortal\Frontend\Dashboard_Layout;
use DoctorAKPortal\Frontend\Service_Handler;
use DoctorAKPortal\Frontend\Service_Profile_View;
use DoctorAKPortal\Frontend\Services_Directory;
use DoctorAKPortal\Frontend\Settlement_Handler;
use DoctorAKPortal\Frontend\Shortcodes;
use DoctorAKPortal\Frontend\Site_Footer;
use DoctorAKPortal\Frontend\Site_Header;
use DoctorAKPortal\Frontend\Theme_Handler;
use DoctorAKPortal\Frontend\Video_Pricing_Handler;

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
		Roles::maybe_upgrade();

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

		$notification_settings = new Notification_Settings();
		$this->loader->add_action( 'admin_menu', $notification_settings, 'register_menu' );
		$this->loader->add_action( 'admin_init', $notification_settings, 'register_settings' );

		$footer_settings = new Footer_Settings();
		$this->loader->add_action( 'admin_menu', $footer_settings, 'register_menu' );
		$this->loader->add_action( 'admin_init', $footer_settings, 'register_settings' );

		$locations_settings = new Locations_Settings();
		$this->loader->add_action( 'admin_menu', $locations_settings, 'register_menu' );
		$this->loader->add_action( 'admin_init', $locations_settings, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $locations_settings, 'enqueue_assets' );

		$role_permissions_settings = new Role_Permissions_Settings();
		$this->loader->add_action( 'admin_menu', $role_permissions_settings, 'register_menu' );
		$this->loader->add_action( 'admin_init', $role_permissions_settings, 'register_settings' );
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

		$site_footer = new Site_Footer( new Template_Loader() );
		$this->loader->add_action( 'after_setup_theme', $site_footer, 'register_menu_locations' );
		$this->loader->add_action( 'wp_enqueue_scripts', $site_footer, 'enqueue_assets' );
		$this->loader->add_action( 'wp_footer', $site_footer, 'render' );

		$doctor_dashboard  = new Doctor_Dashboard( new Template_Loader() );
		$patient_dashboard = new Patient_Dashboard( new Template_Loader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $doctor_dashboard, 'enqueue_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $patient_dashboard, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_appointments_filter', $doctor_dashboard, 'handle_filter_appointments' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_patient_appointments_filter', $patient_dashboard, 'handle_filter_appointments' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_dashboard_search', $doctor_dashboard, 'handle_search' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_patient_dashboard_search', $patient_dashboard, 'handle_search' );

		$profile_handler = new Profile_Handler( new Template_Loader(), new Profile_Picture_Uploader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $profile_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_profile_upload_picture', $profile_handler, 'handle_upload_profile_picture' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_profile_update', $profile_handler, 'handle_update_profile' );

		$theme_handler = new Theme_Handler();
		$this->loader->add_action( 'wp_enqueue_scripts', $theme_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_toggle_theme', $theme_handler, 'handle_toggle_theme' );

		$dashboard_layout = new Dashboard_Layout();
		$this->loader->add_filter( 'template_include', $dashboard_layout, 'template_include' );

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

		$featured_doctors = new Featured_Doctors( new Template_Loader(), $doctors_directory );
		$this->loader->add_action( 'wp_enqueue_scripts', $featured_doctors, 'enqueue_assets' );

		$home_page = new Home_Page( new Template_Loader(), $doctors_directory );
		$this->loader->add_action( 'wp_enqueue_scripts', $home_page, 'enqueue_assets' );

		$services_directory   = new Services_Directory( new Template_Loader() );
		$service_profile_view = new Service_Profile_View( new Template_Loader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $services_directory, 'enqueue_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $service_profile_view, 'enqueue_assets' );

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
		$this->loader->add_action( 'wp_ajax_doctor_ak_booking_rules', $booking_handler, 'handle_get_booking_rules' );
		$this->loader->add_action( 'wp_ajax_nopriv_doctor_ak_booking_rules', $booking_handler, 'handle_get_booking_rules' );

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

		$notifications = new Notifications();
		$this->loader->add_action( 'doctor_ak_appointment_created', $notifications, 'notify_created', 10, 2 );
		$this->loader->add_action( 'doctor_ak_appointment_cancelled', $notifications, 'notify_cancelled' );
		$this->loader->add_action( 'doctor_ak_appointment_rescheduled', $notifications, 'notify_rescheduled' );
		$this->loader->add_action( 'doctor_ak_appointment_paid', $notifications, 'notify_paid' );
		$this->loader->add_action( 'doctor_ak_appointment_refund_requested', $notifications, 'notify_refund_requested' );
		$this->loader->add_action( 'doctor_ak_appointment_refund_processed', $notifications, 'notify_refund_processed' );
		$this->loader->add_action( 'doctor_ak_doctor_registered', $notifications, 'notify_doctor_registered' );
		$this->loader->add_action( 'doctor_ak_doctor_registered', $notifications, 'notify_doctor_registration_received' );
		$this->loader->add_action( 'doctor_ak_doctor_approved', $notifications, 'notify_doctor_approved' );
		$this->loader->add_action( 'doctor_ak_patient_added', $notifications, 'notify_patient_added' );
		$this->loader->add_action( 'doctor_ak_service_created', $notifications, 'notify_new_service', 10, 3 );
		$this->loader->add_action( 'doctor_ak_appointment_reminder_sent', $notifications, 'notify_manual_reminder' );
		$this->loader->add_action( 'transition_post_status', $notifications, 'notify_new_blog_post', 10, 3 );
		$this->loader->add_action( Notifications::CRON_HOOK, $notifications, 'send_reminders' );
		$this->loader->add_filter( 'cron_schedules', 'DoctorAKPortal\\Includes\\Notifications', 'add_cron_interval' );
		$this->loader->add_action( Notifications::VIDEO_LINK_CRON_HOOK, $notifications, 'send_video_link_emails' );

		// Self-healing: installs that were activated before this cron event
		// existed only get it scheduled on Activator::activate(), which
		// doesn't re-run on a plugin code update — so make sure it's there
		// on every request too, not just fresh activations. wp_next_scheduled()
		// makes this a no-op once it's actually scheduled.
		$this->loader->add_action( 'init', 'DoctorAKPortal\\Includes\\Notifications', 'ensure_video_link_cron_scheduled' );

		// Self-healing: an already-active install never re-runs
		// Activator::activate(), so a page added to Page_Installer::PAGES
		// after the site was first activated (e.g. the new Home page) would
		// otherwise never get created. Page_Installer::maybe_install() is a
		// no-op once it has already run for the current PAGES version.
		$this->loader->add_action( 'init', 'DoctorAKPortal\\Includes\\Page_Installer', 'maybe_install' );

		// In-app "Notifications" tab (doctor/patient/admin dashboards) — the
		// same lifecycle events as above, written to the notifications table
		// instead of emailed.
		$this->loader->add_action( 'doctor_ak_appointment_created', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_created', 10, 2 );
		$this->loader->add_action( 'doctor_ak_appointment_cancelled', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_cancelled' );
		$this->loader->add_action( 'doctor_ak_appointment_rescheduled', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_rescheduled' );
		$this->loader->add_action( 'doctor_ak_appointment_paid', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_paid' );
		$this->loader->add_action( 'doctor_ak_appointment_completed', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_completed' );
		$this->loader->add_action( 'doctor_ak_appointment_refund_requested', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_refund_requested' );
		$this->loader->add_action( 'doctor_ak_appointment_refund_processed', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_refund_processed' );
		$this->loader->add_action( 'doctor_ak_doctor_registered', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_doctor_registered' );
		$this->loader->add_action( 'doctor_ak_doctor_approved', 'DoctorAKPortal\\Includes\\Notification_Center', 'notify_doctor_approved' );

		$notification_handler = new Notification_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_notification_mark_read', $notification_handler, 'handle_mark_read' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_notification_mark_all_read', $notification_handler, 'handle_mark_all_read' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_notification_preferences_save', $notification_handler, 'handle_save_preferences' );

		// Revenue ledger — posts a doctor+clinic-wise transaction the moment
		// an appointment becomes paid, and reverses it if that payment is
		// later refunded. See includes/class-revenue-ledger.php.
		$this->loader->add_action( 'doctor_ak_appointment_paid', 'DoctorAKPortal\\Includes\\Revenue_Ledger', 'post_for_appointment' );
		$this->loader->add_action( 'doctor_ak_appointment_refund_processed', 'DoctorAKPortal\\Includes\\Revenue_Ledger', 'reverse_for_appointment' );

		// Any extra charges a doctor added during an encounter (on top of
		// the appointment's own charge) also need to land in billing —
		// posted the moment the encounter is closed. See
		// Encounters::close() and Revenue_Ledger::post_for_encounter_extra().
		$this->loader->add_action( 'doctor_ak_encounter_closed', 'DoctorAKPortal\\Includes\\Revenue_Ledger', 'post_for_encounter_extra', 10, 2 );

		$settlement_handler = new Settlement_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_create_settlement', $settlement_handler, 'handle_create' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_settlement_mark_paid', $settlement_handler, 'handle_mark_paid' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_settlement_mark_received', $settlement_handler, 'handle_mark_received' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_platform_fee_save', $settlement_handler, 'handle_save_platform_fee' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_get_doctor_clinic_details', $settlement_handler, 'handle_get_doctor_clinic_details' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_statement_download', $settlement_handler, 'handle_download_statement' );

		$admin_dashboard = new Admin_Dashboard( new Template_Loader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $admin_dashboard, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointments_filter', $admin_dashboard, 'handle_filter_appointments' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointments_chart', $admin_dashboard, 'handle_appointments_chart' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_users_filter', $admin_dashboard, 'handle_filter_users' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_dashboard_search', $admin_dashboard, 'handle_search' );

		$admin_user_handler = new Admin_User_Handler( new Profile_Picture_Uploader() );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_save_user', $admin_user_handler, 'handle_save_user' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_delete_user', $admin_user_handler, 'handle_delete_user' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_toggle_status', $admin_user_handler, 'handle_toggle_status' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_toggle_discharge', $admin_user_handler, 'handle_toggle_discharge' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_upload_profile_picture', $admin_user_handler, 'handle_upload_profile_picture' );

		$doctor_requests_handler = new Doctor_Requests_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_approve_doctor', $doctor_requests_handler, 'handle_approve' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_reject_doctor', $doctor_requests_handler, 'handle_reject' );

		$service_handler = new Service_Handler( new Profile_Picture_Uploader() );
		$this->loader->add_action( 'wp_enqueue_scripts', $service_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_service_save', $service_handler, 'handle_save_service' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_service_delete', $service_handler, 'handle_delete_service' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_service_save', $service_handler, 'handle_admin_save_service' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_service_delete', $service_handler, 'handle_admin_delete_service' );

		$clinic_location_handler = new Clinic_Location_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_clinic_location_save', $clinic_location_handler, 'handle_admin_save' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_clinic_location_delete', $clinic_location_handler, 'handle_admin_delete' );

		$video_pricing_handler = new Video_Pricing_Handler();
		$this->loader->add_action( 'wp_enqueue_scripts', $video_pricing_handler, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_video_pricing_save', $video_pricing_handler, 'handle_save_price' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_video_pricing_save', $video_pricing_handler, 'handle_admin_save_price' );

		$appointment_handler = new Appointment_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_save', $appointment_handler, 'handle_admin_save_appointment' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_delete', $appointment_handler, 'handle_admin_delete_appointment' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_encounter_note_save', $appointment_handler, 'handle_admin_save_encounter_note' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_print', $appointment_handler, 'handle_print' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_invoice_download', $appointment_handler, 'handle_download_invoice' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_process_refund', $appointment_handler, 'handle_admin_process_refund' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_mark_paid', $appointment_handler, 'handle_mark_paid' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_pay_now', $appointment_handler, 'handle_pay_now' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_appointment_send_reminder', $appointment_handler, 'handle_send_reminder' );

		$patient_appointment_handler = new Patient_Appointment_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_patient_cancel_appointment', $patient_appointment_handler, 'handle_cancel_appointment' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_patient_pay_now', $patient_appointment_handler, 'handle_pay_now' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_patient_reschedule_appointment', $patient_appointment_handler, 'handle_reschedule' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_patient_request_refund', $patient_appointment_handler, 'handle_request_refund' );

		$doctor_appointment_handler = new Doctor_Appointment_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_mark_completed', $doctor_appointment_handler, 'handle_mark_completed' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_mark_paid', $doctor_appointment_handler, 'handle_mark_paid' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_pay_now', $doctor_appointment_handler, 'handle_pay_now' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_reschedule_appointment', $doctor_appointment_handler, 'handle_reschedule' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_cancel_appointment', $doctor_appointment_handler, 'handle_cancel_appointment' );

		$encounter_handler = new Encounter_Handler( new Encounter_Report_Uploader() );
		$this->loader->add_action( 'wp_ajax_doctor_ak_check_in', $encounter_handler, 'handle_check_in' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_create_encounter', $encounter_handler, 'handle_create_encounter' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_close_encounter', $encounter_handler, 'handle_close_encounter' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_get_encounter', $encounter_handler, 'handle_get_encounter' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_add_encounter_problem', $encounter_handler, 'handle_add_problem' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_delete_encounter_problem', $encounter_handler, 'handle_delete_problem' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_add_encounter_prescription', $encounter_handler, 'handle_add_prescription' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_delete_encounter_prescription', $encounter_handler, 'handle_delete_prescription' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_add_encounter_bill_item', $encounter_handler, 'handle_add_bill_item' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_delete_encounter_bill_item', $encounter_handler, 'handle_delete_bill_item' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_upload_encounter_report', $encounter_handler, 'handle_upload_report' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_delete_encounter_report', $encounter_handler, 'handle_delete_report' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_delete_encounter', $encounter_handler, 'handle_delete_encounter' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_prescription_pdf_download', $encounter_handler, 'handle_download_prescription_pdf' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_encounter_bill_pdf_download', $encounter_handler, 'handle_download_bill_pdf' );

		$role_permissions_handler = new Role_Permissions_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_role_permissions_save', $role_permissions_handler, 'handle_save' );

		$locations_handler = new Locations_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_locations_save', $locations_handler, 'handle_save' );

		$clinic_branding_handler = new Clinic_Branding_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_clinic_branding_save', $clinic_branding_handler, 'handle_save' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_clinic_branding_upload_logo', $clinic_branding_handler, 'handle_upload_logo' );

		$home_videos_handler = new Home_Videos_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_home_video_upload', $home_videos_handler, 'handle_upload_video' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_home_video_poster_upload', $home_videos_handler, 'handle_upload_poster' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_home_videos_save', $home_videos_handler, 'handle_save' );

		$home_testimonials_handler = new Home_Testimonials_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_home_testimonials_save', $home_testimonials_handler, 'handle_save' );

		$google_reviews_handler = new Google_Reviews_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_admin_google_reviews_save', $google_reviews_handler, 'handle_save' );

		$doctor_patient_handler = new Doctor_Patient_Handler();
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_add_patient', $doctor_patient_handler, 'handle_add_patient' );
		$this->loader->add_action( 'wp_ajax_doctor_ak_doctor_edit_patient', $doctor_patient_handler, 'handle_edit_patient' );

		// Reuses the existing hourly reminder cron (Notifications::CRON_HOOK)
		// rather than scheduling a second event just for this.
		$this->loader->add_action( Notifications::CRON_HOOK, 'DoctorAKPortal\\Includes\\Appointments', 'auto_complete_past_appointments' );

		$shortcodes = new Shortcodes( $doctor_dashboard, $patient_dashboard, $profile_handler, $doctors_directory, $doctor_profile_view, $admin_dashboard, $booking_page, $featured_doctors, $services_directory, $service_profile_view, $home_page );
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
