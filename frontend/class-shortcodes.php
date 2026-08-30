<?php
/**
 * Registers the public-facing shortcodes.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcodes
 *
 * Each shortcode renders only the body content for its page; the active
 * theme continues to supply the header, navigation and footer. The two
 * dashboard shortcodes delegate to dedicated controllers that also handle
 * access control; the rest render directly through the template loader.
 */
class Shortcodes {

	/**
	 * Template loader used to render the non-dashboard shortcodes' markup.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Doctor dashboard controller.
	 *
	 * @var Doctor_Dashboard
	 */
	private $doctor_dashboard;

	/**
	 * Patient dashboard controller.
	 *
	 * @var Patient_Dashboard
	 */
	private $patient_dashboard;

	/**
	 * Profile editor controller.
	 *
	 * @var Profile_Handler
	 */
	private $profile_handler;

	/**
	 * Doctors directory controller.
	 *
	 * @var Doctors_Directory
	 */
	private $doctors_directory;

	/**
	 * Public doctor profile view controller.
	 *
	 * @var Doctor_Profile_View
	 */
	private $doctor_profile_view;

	/**
	 * Administrator dashboard controller.
	 *
	 * @var Admin_Dashboard
	 */
	private $admin_dashboard;

	/**
	 * Booking page controller.
	 *
	 * @var Booking_Page
	 */
	private $booking_page;

	/**
	 * Homepage featured-doctors slider controller.
	 *
	 * @var Featured_Doctors
	 */
	private $featured_doctors;

	/**
	 * Services directory controller.
	 *
	 * @var Services_Directory
	 */
	private $services_directory;

	/**
	 * Public service profile view controller.
	 *
	 * @var Service_Profile_View
	 */
	private $service_profile_view;

	/**
	 * Home page controller.
	 *
	 * @var Home_Page
	 */
	private $home_page;

	/**
	 * Sets up collaborators.
	 *
	 * @param Doctor_Dashboard     $doctor_dashboard     Doctor dashboard controller.
	 * @param Patient_Dashboard    $patient_dashboard    Patient dashboard controller.
	 * @param Profile_Handler      $profile_handler      Profile editor controller.
	 * @param Doctors_Directory    $doctors_directory    Doctors directory controller.
	 * @param Doctor_Profile_View  $doctor_profile_view  Public doctor profile view controller.
	 * @param Admin_Dashboard      $admin_dashboard      Administrator dashboard controller.
	 * @param Booking_Page         $booking_page         Booking page controller.
	 * @param Featured_Doctors     $featured_doctors     Homepage featured-doctors slider controller.
	 * @param Services_Directory   $services_directory   Services directory controller.
	 * @param Service_Profile_View $service_profile_view Public service profile view controller.
	 * @param Home_Page            $home_page            Home page controller.
	 */
	public function __construct( Doctor_Dashboard $doctor_dashboard, Patient_Dashboard $patient_dashboard, Profile_Handler $profile_handler, Doctors_Directory $doctors_directory, Doctor_Profile_View $doctor_profile_view, Admin_Dashboard $admin_dashboard, Booking_Page $booking_page, Featured_Doctors $featured_doctors, Services_Directory $services_directory, Service_Profile_View $service_profile_view, Home_Page $home_page ) {
		$this->template_loader      = new Template_Loader();
		$this->doctor_dashboard     = $doctor_dashboard;
		$this->patient_dashboard    = $patient_dashboard;
		$this->profile_handler      = $profile_handler;
		$this->doctors_directory    = $doctors_directory;
		$this->doctor_profile_view  = $doctor_profile_view;
		$this->admin_dashboard      = $admin_dashboard;
		$this->booking_page         = $booking_page;
		$this->featured_doctors     = $featured_doctors;
		$this->services_directory   = $services_directory;
		$this->service_profile_view = $service_profile_view;
		$this->home_page            = $home_page;
	}

	/**
	 * Registers all shortcode tags with WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'doctor_register', array( $this, 'render_doctor_register' ) );
		add_shortcode( 'doctor_login', array( $this, 'render_doctor_login' ) );
		add_shortcode( 'doctor_forgot_password', array( $this, 'render_doctor_forgot_password' ) );
		add_shortcode( 'doctor_dashboard', array( $this, 'render_doctor_dashboard' ) );
		add_shortcode( 'patient_dashboard', array( $this, 'render_patient_dashboard' ) );
		add_shortcode( 'doctor_profile', array( $this, 'render_doctor_profile' ) );
		add_shortcode( 'doctors_directory', array( $this, 'render_doctors_directory' ) );
		add_shortcode( 'doctor_profile_view', array( $this, 'render_doctor_profile_view' ) );
		add_shortcode( 'admin_dashboard', array( $this, 'render_admin_dashboard' ) );
		add_shortcode( 'book_appointment', array( $this, 'render_book_appointment' ) );
		add_shortcode( 'featured_doctors', array( $this, 'render_featured_doctors' ) );
		add_shortcode( 'services_directory', array( $this, 'render_services_directory' ) );
		add_shortcode( 'service_profile_view', array( $this, 'render_service_profile_view' ) );
		add_shortcode( 'dak_home', array( $this, 'render_home_page' ) );
	}

	/**
	 * Renders the [doctor_register] shortcode.
	 *
	 * @return string
	 */
	public function render_doctor_register() {
		return $this->template_loader->get_template( 'auth/registration-form.php' );
	}

	/**
	 * Renders the [doctor_login] shortcode.
	 *
	 * @return string
	 */
	public function render_doctor_login() {
		return $this->template_loader->get_template( 'auth/login-form.php' );
	}

	/**
	 * Renders the [doctor_forgot_password] shortcode.
	 *
	 * @return string
	 */
	public function render_doctor_forgot_password() {
		return $this->template_loader->get_template( 'auth/forgot-password-form.php' );
	}

	/**
	 * Renders the [doctor_dashboard] shortcode.
	 *
	 * @return string
	 */
	public function render_doctor_dashboard() {
		return $this->doctor_dashboard->render();
	}

	/**
	 * Renders the [patient_dashboard] shortcode.
	 *
	 * @return string
	 */
	public function render_patient_dashboard() {
		return $this->patient_dashboard->render();
	}

	/**
	 * Renders the [doctor_profile] shortcode.
	 *
	 * @return string
	 */
	public function render_doctor_profile() {
		return $this->profile_handler->render();
	}

	/**
	 * Renders the [doctors_directory] shortcode.
	 *
	 * @return string
	 */
	public function render_doctors_directory() {
		return $this->doctors_directory->render();
	}

	/**
	 * Renders the [doctor_profile_view] shortcode.
	 *
	 * @return string
	 */
	public function render_doctor_profile_view() {
		return $this->doctor_profile_view->render();
	}

	/**
	 * Renders the [admin_dashboard] shortcode.
	 *
	 * @return string
	 */
	public function render_admin_dashboard() {
		return $this->admin_dashboard->render();
	}

	/**
	 * Renders the [book_appointment] shortcode.
	 *
	 * @return string
	 */
	public function render_book_appointment() {
		return $this->booking_page->render();
	}

	/**
	 * Renders the [featured_doctors] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes (`limit`).
	 * @return string
	 */
	public function render_featured_doctors( $atts ) {
		return $this->featured_doctors->render( $atts );
	}

	/**
	 * Renders the [services_directory] shortcode.
	 *
	 * @return string
	 */
	public function render_services_directory() {
		return $this->services_directory->render();
	}

	/**
	 * Renders the [service_profile_view] shortcode.
	 *
	 * @return string
	 */
	public function render_service_profile_view() {
		return $this->service_profile_view->render();
	}

	/**
	 * Renders the [dak_home] shortcode.
	 *
	 * @return string
	 */
	public function render_home_page() {
		return $this->home_page->render();
	}
}
