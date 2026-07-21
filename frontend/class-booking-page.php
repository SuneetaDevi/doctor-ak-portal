<?php
/**
 * Backs the [book_appointment] shortcode — the full booking page (doctor,
 * service, calendar with slot-card time picker, identity, and submit),
 * which replaces the old popup booking modal.
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
use DoctorAKPortal\Includes\Video_Pricing;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Booking_Page
 *
 * Reached by navigating (not a popup) from any "Book Appointment" trigger
 * across the site (see Booking_Trigger), optionally with `?doctor_id=` and
 * `?type=` query args to preselect a doctor/type. Posts to the same AJAX
 * endpoints the old modal used (Booking_Handler), so the booking/payment
 * flow itself is unchanged — only how the patient gets there and picks a
 * date/time changed.
 */
class Booking_Page {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'book_appointment';

	/**
	 * Nonce action shared with Booking_Handler.
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
	 * Enqueues the page's assets only on pages containing [book_appointment].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_booking_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-booking-page',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-booking-page.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-booking-page.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-booking-page',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-booking-page.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-booking-page.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-booking-page',
			'dakBookingPage',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
				'isLoggedIn'  => is_user_logged_in() && $this->is_patient(),
				'user'        => $this->logged_in_patient_data(),
				'loginUrl'    => Page_Finder::url_for_shortcode( 'doctor_login' ),
				'registerUrl' => Page_Finder::url_for_shortcode( 'doctor_register' ),
				'profileUrl'  => Page_Finder::url_for_shortcode( 'doctor_profile' ),
				'pageUrl'     => Page_Finder::url_for_shortcode( self::SHORTCODE_TAG ),
				'services'    => $this->services_by_doctor_and_type(),
				'videoPricing' => $this->video_pricing_by_doctor(),
				'bookingRules' => $this->booking_rules_by_doctor(),
			)
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$requested_doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
		$doctor               = $requested_doctor_id > 0 ? get_userdata( $requested_doctor_id ) : false;
		$doctor               = ( $doctor && in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) ? $doctor : false;

		$type = ( isset( $_GET['type'] ) && 'video' === $_GET['type'] ) ? 'video' : 'clinic'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		$selected_doctor_name = '';
		$video_disabled       = false;

		if ( $doctor ) {
			$selected_doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$selected_doctor_name = '' !== $selected_doctor_name ? $selected_doctor_name : $doctor->display_name;
			$video_disabled       = ! Clinics::doctor_has_active_video_clinic( $doctor->ID );

			if ( $video_disabled ) {
				$type = 'clinic';
			}
		}

		return $this->template_loader->get_template(
			'booking/booking-page.php',
			array(
				'doctor_cards'         => $this->doctor_cards_data(),
				'selected_doctor_id'   => $doctor ? $doctor->ID : 0,
				'selected_doctor_name' => $selected_doctor_name,
				'selected_type'        => $type,
				'video_disabled'       => $video_disabled,
			)
		);
	}

	/**
	 * Doctor cards for the "Doctor & Service" step: id, name, first
	 * specialization label, avatar (or initials fallback), and whether
	 * they offer video consultations.
	 *
	 * @return array
	 */
	private function doctor_cards_data() {
		$query = new \WP_User_Query(
			array(
				'role'    => Roles::DOCTOR_ROLE,
				'orderby' => 'display_name',
			)
		);

		$all_specializations = Specializations::get_all();
		$cards                = array();

		foreach ( $query->get_results() as $doctor ) {
			$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$display_name = '' !== $display_name ? $display_name : $doctor->display_name;

			$specialization_slugs = (array) get_user_meta( $doctor->ID, 'doctor_ak_specializations', true );
			$specialization_label = '';

			foreach ( $specialization_slugs as $slug ) {
				if ( isset( $all_specializations[ $slug ] ) ) {
					$specialization_label = $all_specializations[ $slug ];
					break;
				}
			}

			$cards[] = array(
				'id'             => $doctor->ID,
				'name'           => $display_name,
				'initials'       => self::initials( $display_name ),
				'specialization' => $specialization_label,
				'avatar_url'     => self::avatar_url( $doctor->ID ),
				'video_disabled' => ! Clinics::doctor_has_active_video_clinic( $doctor->ID ),
			);
		}

		return $cards;
	}

	/**
	 * Resolves a doctor's uploaded profile picture, or '' if none set.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private static function avatar_url( $doctor_id ) {
		$picture_id = (int) get_user_meta( $doctor_id, 'doctor_ak_profile_picture_id', true );

		if ( $picture_id > 0 ) {
			$url = wp_get_attachment_image_url( $picture_id, 'thumbnail' );

			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * One or two uppercase initials from a display name, for a doctor
	 * card's avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	private static function initials( $name ) {
		$words    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			if ( '' !== $word ) {
				$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
			}
		}

		return '' !== $initials ? $initials : '?';
	}

	/**
	 * Every doctor's active onsite (clinic) services, shaped for the page's
	 * JS to filter client-side as [doctor_id]['clinic'] => [{id, name,
	 * charge, duration_minutes}, ...]. Video consultations no longer use a
	 * service list — see video_pricing_by_doctor().
	 *
	 * @return array
	 */
	private function services_by_doctor_and_type() {
		$map = array();

		foreach ( array_keys( Appointments::doctor_options() ) as $doctor_id ) {
			$services = Services::active_for_doctor( $doctor_id, 'clinic' );

			if ( empty( $services ) ) {
				continue;
			}

			$map[ $doctor_id ] = array(
				'clinic' => array_map(
					function ( $service ) {
						return array(
							'id'               => $service['id'],
							'name'             => $service['name'],
							'charge'           => $service['charge'],
							'duration_minutes' => $service['duration_minutes'],
						);
					},
					$services
				),
			);
		}

		return $map;
	}

	/**
	 * Every doctor's fixed video-consultation price (with discount already
	 * applied), for the page's JS to show a single price card instead of a
	 * service list when the patient picks "Online Video".
	 *
	 * @return array doctor_id => Video_Pricing::effective_price_for_doctor() result.
	 */
	private function video_pricing_by_doctor() {
		$map = array();

		foreach ( array_keys( Appointments::doctor_options() ) as $doctor_id ) {
			$map[ $doctor_id ] = Video_Pricing::effective_price_for_doctor( $doctor_id );
		}

		return $map;
	}

	/**
	 * Every doctor's instant-booking and cancellation-refund settings, for
	 * the page's JS to flag instant slots with their surcharge and show the
	 * doctor's real cancellation policy instead of a generic hardcoded note.
	 *
	 * @return array doctor_id => { instant_lead_hours, instant_surcharge, cancel_refund_hours }.
	 */
	private function booking_rules_by_doctor() {
		$map = array();

		foreach ( array_keys( Appointments::doctor_options() ) as $doctor_id ) {
			$settings           = Video_Pricing::get_for_doctor( $doctor_id );
			$map[ $doctor_id ] = array(
				'instant_lead_hours'  => $settings['instant_lead_hours'],
				'instant_surcharge'   => $settings['instant_surcharge'],
				'cancel_refund_hours' => $settings['cancel_refund_hours'],
			);
		}

		return $map;
	}

	/**
	 * Whether the current user is logged in as a patient.
	 *
	 * @return bool
	 */
	private function is_patient() {
		$user = wp_get_current_user();

		return in_array( Roles::PATIENT_ROLE, (array) $user->roles, true );
	}

	/**
	 * Logged-in patient's identity, for the page's JS to prefill.
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

	/**
	 * Checks whether the current request is for a page containing the
	 * booking-page shortcode.
	 *
	 * @return bool
	 */
	private function is_booking_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
