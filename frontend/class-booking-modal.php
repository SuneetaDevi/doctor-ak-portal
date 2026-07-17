<?php
/**
 * Global "Book Consultation" modal, rendered on every front-end page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Booking_Modal
 *
 * Unlike the shortcode-backed controllers, this renders on every front-end
 * request (via wp_footer) — the same reasoning as Site_Header: the header's
 * "Book Appointment" links, directory cards, the profile-view page, and the
 * patient dashboard all need to open the same modal instance regardless of
 * which page they're on.
 */
class Booking_Modal {

	/**
	 * Nonce action for the booking AJAX endpoint (shared with Booking_Handler).
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_booking';

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
	 * Enqueues the modal's assets on every front-end page.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-booking-modal',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-booking-modal.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-booking-modal.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-booking-modal',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-booking-modal.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-booking-modal.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-booking-modal',
			'dakBooking',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
				'isLoggedIn'  => is_user_logged_in() && $this->is_patient(),
				'user'        => $this->logged_in_patient_data(),
				'loginUrl'    => Page_Finder::url_for_shortcode( 'doctor_login' ),
				'registerUrl' => Page_Finder::url_for_shortcode( 'doctor_register' ),
			)
		);
	}

	/**
	 * Renders the modal markup. Hooked to wp_footer so it sits at the end of
	 * <body>, clear of any theme header/stacking-context conflicts.
	 *
	 * @return void
	 */
	public function render() {
		if ( is_admin() ) {
			return;
		}

		echo $this->template_loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
			'modal/booking-modal.php',
			array( 'doctor_options' => Appointments::doctor_options() )
		);
	}

	/**
	 * Whether the current user is logged in as a patient (doctors booking
	 * on behalf of themselves isn't a supported flow).
	 *
	 * @return bool
	 */
	private function is_patient() {
		$user = wp_get_current_user();

		return in_array( Roles::PATIENT_ROLE, (array) $user->roles, true );
	}

	/**
	 * Logged-in patient's identity, for the modal's JS to prefill.
	 *
	 * @return array
	 */
	private function logged_in_patient_data() {
		if ( ! $this->is_patient() ) {
			return array(
				'name'  => '',
				'email' => '',
				'phone' => '',
			);
		}

		$user = wp_get_current_user();
		$name = trim( $user->first_name . ' ' . $user->last_name );

		return array(
			'name'  => '' !== $name ? $name : $user->display_name,
			'email' => $user->user_email,
			'phone' => get_user_meta( $user->ID, 'doctor_ak_phone_number', true ),
		);
	}
}
