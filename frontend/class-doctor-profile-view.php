<?php
/**
 * Backs the [doctor_profile_view] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Doctor_Awards;
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
 * Class Doctor_Profile_View
 *
 * A deliberately minimal, public, read-only profile page (the "View
 * Profile" destination from the directory), showing only what's already
 * collected at registration. No editing, reviews, or bio field — those are
 * a later phase.
 */
class Doctor_Profile_View {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'doctor_profile_view';

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
	 * Enqueues assets only on pages containing [doctor_profile_view].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_profile_view_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directory.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-directory.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-doctor-profile-clinics',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-doctor-profile-clinics.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-doctor-profile-clinics.js' ),
			true
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public profile lookup.
		$doctor    = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) || 'yes' === get_user_meta( $doctor->ID, 'doctor_ak_account_disabled', true ) ) {
			return $this->template_loader->get_template( 'directory/doctor-profile-view.php', array( 'doctor' => null ) );
		}

		$specialization_slugs = (array) get_user_meta( $doctor->ID, 'doctor_ak_specializations', true );
		$all_specializations   = Specializations::get_all();

		$specialization_labels = array_map(
			function ( $slug ) use ( $all_specializations ) {
				return isset( $all_specializations[ $slug ] ) ? $all_specializations[ $slug ] : $slug;
			},
			$specialization_slugs
		);

		$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
		$display_name = '' !== $display_name ? $display_name : $doctor->display_name;

		$raw_clinics = Clinics::get_for_doctor( $doctor->ID );
		$clinics     = array_map(
			array( $this, 'enrich_clinic' ),
			$raw_clinics,
			array_fill( 0, count( $raw_clinics ), $doctor->ID )
		);

		$phone = '';

		foreach ( $clinics as $clinic ) {
			if ( '' !== $clinic['phone'] ) {
				$phone = $clinic['phone'];
				break;
			}
		}

		return $this->template_loader->get_template(
			'directory/doctor-profile-view.php',
			array(
				'doctor'                 => array(
					'id'                    => $doctor->ID,
					'name'                  => $display_name,
					'avatar_url'            => self::avatar_url( $doctor->ID ),
					'specialization_labels' => $specialization_labels,
					'clinics'               => $clinics,
					'years_experience'      => get_user_meta( $doctor->ID, 'doctor_ak_years_experience', true ),
					'qualification'         => get_user_meta( $doctor->ID, 'doctor_ak_qualification', true ),
					'short_description'     => get_user_meta( $doctor->ID, 'doctor_ak_short_description', true ),
					'expertise'             => get_user_meta( $doctor->ID, 'doctor_ak_expertise', true ),
					'awards'                => Doctor_Awards::get_for_doctor( $doctor->ID ),
					'video_consultation'    => Clinics::doctor_has_active_video_clinic( $doctor->ID ),
					'phone'                 => $phone,
				),
				'directory_url'          => Page_Finder::url_for_shortcode( 'doctors_directory' ),
				'starting_fee_label'     => $this->starting_fee_label( $doctor->ID ),
				'cancellation_note'      => $this->cancellation_note( $doctor->ID ),
			)
		);
	}

	/**
	 * Adds a human 'hours_label' (from the clinic's weekly sessions) and
	 * 'fee_label' (from real service/video pricing, never fabricated) to one
	 * Clinics::get_for_doctor() row.
	 *
	 * @param array $clinic    One clinic row.
	 * @param int   $doctor_id Doctor's user ID.
	 * @return array
	 */
	private function enrich_clinic( array $clinic, $doctor_id ) {
		$clinic['hours_label'] = self::sessions_hours_label( $clinic['sessions'] );
		$clinic['fee_label']   = Clinics::TYPE_VIDEO === $clinic['type']
			? self::video_fee_label( $doctor_id )
			: self::clinic_fee_label( $doctor_id );

		return $clinic;
	}

	/**
	 * Earliest start / latest end across a clinic's enabled weekdays, as a
	 * single "10:00 AM – 2:00 PM" range. Empty if no day is enabled.
	 *
	 * @param array $sessions Clinic's sessions structure (see Clinics::empty_sessions()).
	 * @return string
	 */
	private static function sessions_hours_label( array $sessions ) {
		$start = '';
		$end   = '';

		foreach ( $sessions as $day ) {
			foreach ( $day as $period ) {
				if ( empty( $period['enabled'] ) ) {
					continue;
				}

				if ( '' === $start || $period['start'] < $start ) {
					$start = $period['start'];
				}

				if ( '' === $end || $period['end'] > $end ) {
					$end = $period['end'];
				}
			}
		}

		if ( '' === $start || '' === $end ) {
			return '';
		}

		return self::format_time( $start ) . ' – ' . self::format_time( $end );
	}

	/**
	 * Formats a 'HH:MM' 24-hour time string as '9:00 AM'.
	 *
	 * @param string $time 'HH:MM'.
	 * @return string
	 */
	private static function format_time( $time ) {
		$timestamp = strtotime( $time );

		return $timestamp ? date_i18n( 'g:i A', $timestamp ) : $time;
	}

	/**
	 * A clinic (onsite) visit's fee, from the doctor's real configured
	 * services — never a fabricated placeholder. Empty string if the doctor
	 * hasn't configured any clinic services yet.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private static function clinic_fee_label( $doctor_id ) {
		$services = Services::active_for_doctor( $doctor_id, 'clinic' );

		if ( empty( $services ) ) {
			return '';
		}

		$charges = array_map(
			function ( $service ) {
				return (float) $service['charge'];
			},
			$services
		);

		$min = min( $charges );

		if ( 0.0 === $min ) {
			return __( 'Free', 'doctor-ak-portal' );
		}

		$label = 'PKR ' . number_format_i18n( $min );

		return count( array_unique( $charges ) ) > 1 ? sprintf(
			/* translators: %s: lowest configured service charge. */
			__( 'From %s', 'doctor-ak-portal' ),
			$label
		) : $label;
	}

	/**
	 * A video consultation's fee, from the doctor's real video pricing
	 * settings.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private static function video_fee_label( $doctor_id ) {
		$pricing = Video_Pricing::effective_price_for_doctor( $doctor_id );

		if ( ! ( $pricing['final_price'] > 0 ) ) {
			return __( 'Free', 'doctor-ak-portal' );
		}

		return 'PKR ' . number_format_i18n( $pricing['final_price'] );
	}

	/**
	 * The cheapest of the doctor's clinic and video fees, for the sidebar's
	 * "Consultation from" teaser. Empty if nothing is configured for either.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private function starting_fee_label( $doctor_id ) {
		$clinic_services = Services::active_for_doctor( $doctor_id, 'clinic' );
		$video_pricing   = Clinics::doctor_has_active_video_clinic( $doctor_id ) ? Video_Pricing::effective_price_for_doctor( $doctor_id ) : null;

		$candidates = array();

		foreach ( $clinic_services as $service ) {
			$candidates[] = (float) $service['charge'];
		}

		if ( null !== $video_pricing ) {
			$candidates[] = (float) $video_pricing['final_price'];
		}

		if ( empty( $candidates ) ) {
			return '';
		}

		$min = min( $candidates );

		return $min > 0 ? 'PKR ' . number_format_i18n( $min ) : __( 'Free', 'doctor-ak-portal' );
	}

	/**
	 * The doctor's real cancellation policy (same source the booking page's
	 * summary sidebar reads), for the profile page's booking card.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private function cancellation_note( $doctor_id ) {
		$settings = Video_Pricing::get_for_doctor( $doctor_id );
		$hours    = (float) $settings['cancel_refund_hours'];

		if ( $hours > 0 ) {
			return sprintf(
				/* translators: %s: number of hours. */
				_n( 'Free cancellation up to %s hour before your appointment.', 'Free cancellation up to %s hours before your appointment.', $hours, 'doctor-ak-portal' ),
				number_format_i18n( $hours )
			);
		}

		return __( 'Free cancellation any time before your appointment starts.', 'doctor-ak-portal' );
	}

	/**
	 * Resolves a doctor's uploaded profile picture, falling back to a
	 * generic avatar if they haven't uploaded one.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private static function avatar_url( $doctor_id ) {
		$picture_id = (int) get_user_meta( $doctor_id, 'doctor_ak_profile_picture_id', true );

		if ( $picture_id > 0 ) {
			$url = wp_get_attachment_image_url( $picture_id, 'medium' );

			if ( $url ) {
				return $url;
			}
		}

		return get_avatar_url( $doctor_id, array( 'size' => 200 ) );
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * profile-view shortcode.
	 *
	 * @return bool
	 */
	private function is_profile_view_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
