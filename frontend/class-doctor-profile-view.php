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
use DoctorAKPortal\Includes\Doctor_Reviews;
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

		wp_localize_script(
			'doctor-ak-portal-doctor-profile-clinics',
			'dakDoctorProfile',
			array(
				'chooseConsultationLabel' => __( 'Choose consultation', 'doctor-ak-portal' ),
				'chooseDateTimeLabel'     => __( 'Choose date & time', 'doctor-ak-portal' ),
				'chooseTypeLabel'         => __( 'Choose a visit type to get started.', 'doctor-ak-portal' ),
				'chooseClinicLabel'       => __( 'Choose a clinic to see its services and fees.', 'doctor-ak-portal' ),
				'chooseServiceLabel'      => __( 'Choose a service to see the fee.', 'doctor-ak-portal' ),
				/* translators: %s: clinic name — replaced client-side, keep the literal %s. */
				'chooseServiceAtLabel'    => __( 'Choose a service at %s to see the fee.', 'doctor-ak-portal' ),
				'videoSummaryLabel'       => __( 'Online Video Consultation', 'doctor-ak-portal' ),
				'feeLabel'                => __( 'Fee', 'doctor-ak-portal' ),
			)
		);

		wp_enqueue_script(
			'doctor-ak-portal-doctor-reviews',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-doctor-reviews.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-doctor-reviews.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-doctor-reviews',
			'dakDoctorReviews',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Doctor_Review_Handler::NONCE_ACTION ),
			)
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

		// Only real specializations — a stray free-typed value in a
		// doctor's meta (e.g. a procedure/condition) isn't one, and would
		// otherwise show up as its own tag on the profile.
		$specialization_labels = array_values(
			array_filter(
				array_map(
					function ( $slug ) use ( $all_specializations ) {
						return isset( $all_specializations[ $slug ] ) ? $all_specializations[ $slug ] : '';
					},
					$specialization_slugs
				)
			)
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

		// Separate from $clinics' raw count (which also includes any TYPE_VIDEO
		// row) — the header's "N Locations" stat must only ever count real,
		// physical places a patient can walk into, never the online option
		// (that's its own "Online Video Consults" badge, see $video_consultation).
		$physical_clinic_count = count(
			array_filter(
				$raw_clinics,
				function ( $clinic ) {
					return Clinics::TYPE_PHYSICAL === $clinic['type'];
				}
			)
		);

		$clinics_with_services = $this->clinics_with_services( $doctor->ID, $raw_clinics );

		return $this->template_loader->get_template(
			'directory/doctor-profile-view.php',
			array(
				'doctor'                 => array(
					'id'                     => $doctor->ID,
					'name'                   => $display_name,
					'avatar_url'             => self::avatar_url( $doctor->ID ),
					'specialization_labels'  => $specialization_labels,
					'keywords'               => array_filter( (array) get_user_meta( $doctor->ID, 'doctor_ak_keywords', true ) ),
					'clinics'                => $clinics,
					'physical_clinic_count'  => $physical_clinic_count,
					'clinics_with_services'  => $clinics_with_services,
					'video_fee_label'        => self::video_fee_label( $doctor->ID ),
					'years_experience'       => get_user_meta( $doctor->ID, 'doctor_ak_years_experience', true ),
					'qualification'          => get_user_meta( $doctor->ID, 'doctor_ak_qualification', true ),
					'short_description'      => get_user_meta( $doctor->ID, 'doctor_ak_short_description', true ),
					'expertise'              => get_user_meta( $doctor->ID, 'doctor_ak_expertise', true ),
					'awards'                 => Doctor_Awards::get_for_doctor( $doctor->ID ),
					'video_consultation'     => Clinics::doctor_has_active_video_clinic( $doctor->ID ),
					'phone'                  => $phone,
				),
				'reviews'                => Doctor_Reviews::get_for_doctor( $doctor->ID ),
				'review_summary'         => Doctor_Reviews::summary( $doctor->ID ),
				'can_review'             => is_user_logged_in() && Doctor_Reviews::can_review( get_current_user_id(), $doctor->ID ),
				'my_review'              => is_user_logged_in() ? Doctor_Reviews::find_for_patient( get_current_user_id(), $doctor->ID ) : null,
				'is_logged_in'           => is_user_logged_in(),
				'directory_url'          => Page_Finder::url_for_shortcode( 'doctors_directory' ),
				'starting_fee_summary'   => $this->starting_fee_summary( $doctor->ID ),
				'cancellation_note'      => $this->cancellation_note( $doctor->ID ),
			)
		);
	}

	/**
	 * A validated doctor's browser-tab/SEO title — "Dr. {Name} —
	 * {Specialty}", or '' for an invalid/unpublished id (same validation
	 * render() already applies). Called from Public_Pages::filter_title()
	 * rather than duplicating this doctor lookup/validation there.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	public static function page_title_for_doctor( $doctor_id ) {
		$doctor = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) || 'yes' === get_user_meta( $doctor->ID, 'doctor_ak_account_disabled', true ) ) {
			return '';
		}

		$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
		$display_name = '' !== $display_name ? $display_name : $doctor->display_name;

		$specialization_slugs = (array) get_user_meta( $doctor->ID, 'doctor_ak_specializations', true );
		$all_specializations   = Specializations::get_all();
		$first_specialization   = '';

		foreach ( $specialization_slugs as $slug ) {
			if ( isset( $all_specializations[ $slug ] ) ) {
				$first_specialization = $all_specializations[ $slug ];
				break;
			}
		}

		$title = sprintf( 'Dr. %s', $display_name );

		return '' !== $first_specialization ? $title . ' — ' . $first_specialization : $title;
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
			: self::clinic_fee_label( $doctor_id, $clinic['clinic_location_id'] );

		return $clinic;
	}

	/**
	 * This doctor's physical clinics, each with the services actually
	 * offered there and that clinic's own exact fee for each — "Clinics &
	 * Fees" replaces the old flat, service-first "Services" list (which
	 * repeated the same clinic name/address/hours once per service) with a
	 * clinic-first one for the "pick a clinic, then see its services"
	 * selection sequence: a patient picks a clinic once, then sees that
	 * clinic's address/hours a single time plus every service actually
	 * offered there at its exact price.
	 *
	 * Uses the same clinic_charges scoping rule clinic_fee_label() already
	 * uses (a service with no clinics picked in its own "Available at" list
	 * — see Services::decode_row()'s 'clinic_charges' — applies to every
	 * physical clinic at its flat charge; one scoped to specific clinics
	 * only applies to those, at each one's own override price), just
	 * grouped by clinic instead of by service. Never trusts a service's own
	 * baked-in price_label (still subject to Services::decode_row()'s
	 * "0 ⇒ Free" rule) — always reformats from the raw charge via
	 * Services::single_price_label().
	 *
	 * @param int   $doctor_id   Doctor's user ID.
	 * @param array $raw_clinics Clinics::get_for_doctor( $doctor_id ) — passed in rather than re-queried, render() already has it.
	 * @return array List of { clinic_id (this doctor's own Clinics row ID, for the booking link), name, meta, hours_label, services: [ { id, name, charge, price_label } ] } — physical clinics with at least one service only.
	 */
	private function clinics_with_services( $doctor_id, array $raw_clinics ) {
		$services = Services::active_for_doctor( $doctor_id, 'clinic' );
		$out      = array();

		foreach ( $raw_clinics as $dak_doctor_clinic ) {
			if ( Clinics::TYPE_PHYSICAL !== $dak_doctor_clinic['type'] ) {
				continue;
			}

			$dak_clinic_location_id = (int) $dak_doctor_clinic['clinic_location_id'];
			$service_rows            = array();

			foreach ( $services as $service ) {
				$charge = null;

				if ( ! empty( $service['clinic_charges'] ) ) {
					if ( isset( $service['clinic_charges'][ $dak_clinic_location_id ] ) ) {
						$charge = (float) $service['clinic_charges'][ $dak_clinic_location_id ];
					}
				} else {
					$charge = (float) $service['charge'];
				}

				if ( null === $charge ) {
					continue;
				}

				$service_rows[] = array(
					'id'          => $service['id'],
					'name'        => $service['name'],
					'charge'      => $charge,
					'price_label' => Services::single_price_label( $charge ),
				);
			}

			if ( empty( $service_rows ) ) {
				continue;
			}

			$out[] = array(
				'clinic_id'   => $dak_doctor_clinic['id'],
				'name'        => $dak_doctor_clinic['name'],
				'meta'        => implode( ', ', array_filter( array( $dak_doctor_clinic['address'], $dak_doctor_clinic['area_label'], $dak_doctor_clinic['city_label'] ) ) ),
				'hours_label' => self::sessions_hours_label( $dak_doctor_clinic['sessions'] ),
				'services'    => $service_rows,
			);
		}

		return $out;
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
	 * A clinic (onsite) visit's fee at one specific physical clinic, from
	 * the doctor's real configured services — never a fabricated
	 * placeholder, and never "Free" for a service that was simply never
	 * priced (see Services::price_summary()). A service with no clinics
	 * picked in its own "Available at" list (see Services::decode_row()'s
	 * 'clinic_charges') counts at every clinic, at its flat charge; a
	 * service scoped to specific clinics only counts here when this one is
	 * among them, at that clinic's own override price. Empty string if
	 * nothing configured applies to this clinic at all.
	 *
	 * @param int $doctor_id          Doctor's user ID.
	 * @param int $clinic_location_id This clinic's Clinic_Locations ID (0 for a legacy clinic not linked to one — only unscoped services count there).
	 * @return string
	 */
	private static function clinic_fee_label( $doctor_id, $clinic_location_id ) {
		$services = Services::active_for_doctor( $doctor_id, 'clinic' );

		if ( empty( $services ) ) {
			return '';
		}

		$charges = array();

		foreach ( $services as $service ) {
			if ( ! empty( $service['clinic_charges'] ) ) {
				if ( isset( $service['clinic_charges'][ $clinic_location_id ] ) ) {
					$charges[] = (float) $service['clinic_charges'][ $clinic_location_id ];
				}

				continue;
			}

			$charges[] = (float) $service['charge'];
		}

		return Services::price_summary( $charges )['label'];
	}

	/**
	 * A video consultation's fee, from the doctor's real video pricing
	 * settings — "Select a service to view the fee." rather than "Free"
	 * when nothing has actually been priced (see Services::price_summary()).
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private static function video_fee_label( $doctor_id ) {
		$pricing = Video_Pricing::effective_price_for_doctor( $doctor_id );

		return Services::single_price_label( $pricing['final_price'] );
	}

	/**
	 * The cheapest of the doctor's clinic and video fees, for the sidebar's
	 * pre-selection teaser. Returns the full { state, label } pair (not just
	 * a string) so the template can phrase it correctly: 'paid'/'range'
	 * reads as "Consultation from {label}", 'unset' reads as just the
	 * label on its own (already a complete sentence — "Select a service to
	 * view the fee."), and 'none' hides the teaser entirely. Never "Free"
	 * for a merely-unpriced service (see Services::price_summary()).
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return array { @type string state, @type string label }
	 */
	private function starting_fee_summary( $doctor_id ) {
		$clinic_services = Services::active_for_doctor( $doctor_id, 'clinic' );
		$video_pricing   = Clinics::doctor_has_active_video_clinic( $doctor_id ) ? Video_Pricing::effective_price_for_doctor( $doctor_id ) : null;

		$candidates = array();

		foreach ( $clinic_services as $service ) {
			$candidates[] = (float) $service['charge'];
		}

		if ( null !== $video_pricing ) {
			$candidates[] = (float) $video_pricing['final_price'];
		}

		return Services::price_summary( $candidates );
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
