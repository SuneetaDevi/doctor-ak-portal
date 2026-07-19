<?php
/**
 * Backs the [doctor_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

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

		wp_enqueue_script(
			'doctor-ak-portal-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-dashboard.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-dashboard.js' ),
			true
		);

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
	 * 'clinics' or 'settings'.
	 *
	 * @return string
	 */
	private static function requested_tab() {
		if ( ! isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			return 'dashboard';
		}

		$tab = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.

		return in_array( $tab, array( 'profile', 'clinics', 'services', 'video-consultation', 'settings' ), true ) ? $tab : 'dashboard';
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

		$user_counts = count_users();
		$active_tab  = self::requested_tab();
		$dashboard_url = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );

		return array(
			'user'                  => $user,
			'avatar_url'            => $profile_picture_id > 0 ? wp_get_attachment_image_url( $profile_picture_id, 'thumbnail' ) : '',
			'profile_completion'    => $profile_completion,
			'years_experience'      => '' !== $years_experience_meta ? (int) $years_experience_meta : 0,
			'specializations'       => $specializations,
			'specialization_labels' => $specialization_labels,
			'clinic_location'       => $primary_clinic_location,
			'video_consultation'    => Clinics::doctor_has_active_video_clinic( $user->ID ),
			'theme'                 => Theme_Preference::get( $user->ID ),
			'active_tab'            => $active_tab,
			'profile_form_html'     => 'profile' === $active_tab ? $this->render_profile_form( $user ) : '',
			'clinics_tab_html'      => 'clinics' === $active_tab ? $this->render_clinics_tab( $user ) : '',
			'services_tab_html'     => 'services' === $active_tab ? $this->render_services_tab( $user ) : '',
			'video_consultation_tab_html' => 'video-consultation' === $active_tab ? $this->render_video_consultation_tab( $user ) : '',
			'settings_tab_html'     => 'settings' === $active_tab ? $this->render_settings_tab() : '',
			'dashboard_url'         => $dashboard_url,
			'profile_url'           => $dashboard_url ? add_query_arg( 'tab', 'profile', $dashboard_url ) : '',
			'clinics_url'           => $dashboard_url ? add_query_arg( 'tab', 'clinics', $dashboard_url ) : '',
			'services_url'          => $dashboard_url ? add_query_arg( 'tab', 'services', $dashboard_url ) : '',
			'video_consultation_url' => $dashboard_url ? add_query_arg( 'tab', 'video-consultation', $dashboard_url ) : '',
			'settings_url'          => $dashboard_url ? add_query_arg( 'tab', 'settings', $dashboard_url ) : '',
			'logout_url'            => wp_logout_url( Page_Finder::url_for_shortcode( 'doctor_login' ) ),
			'total_patients'        => isset( $user_counts['avail_roles'][ Roles::PATIENT_ROLE ] ) ? (int) $user_counts['avail_roles'][ Roles::PATIENT_ROLE ] : 0,
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
