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

		// Reuses the doctors directory's search-bar/specialization-filter
		// styles (.dak-directory-search etc.) for the Doctor step's own
		// filters — see templates/booking/booking-page.php.
		wp_enqueue_style(
			'doctor-ak-portal-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directory.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-directory.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-booking-page',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-booking-page.css',
			array( 'doctor-ak-portal-auth', 'doctor-ak-portal-directory' ),
			Assets::version( 'assets/css/doctor-ak-booking-page.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-booking-page',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-booking-page.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-booking-page.js' ),
			true
		);

		$selection = $this->resolved_selection();

		wp_localize_script(
			'doctor-ak-portal-booking-page',
			'dakBookingPage',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
				'isLoggedIn'  => is_user_logged_in() && $this->is_patient(),
				'isStaff'     => self::is_staff(),
				'user'        => $this->logged_in_patient_data(),
				'loginUrl'    => Page_Finder::url_for_shortcode( 'doctor_login' ),
				'registerUrl' => Page_Finder::url_for_shortcode( 'doctor_register' ),
				'profileUrl'  => Page_Finder::url_for_shortcode( 'doctor_profile' ),
				'pageUrl'     => Page_Finder::url_for_shortcode( self::SHORTCODE_TAG ),
				'services'    => $this->services_by_doctor_and_type(),
				'videoPricing' => $this->video_pricing_by_doctor(),
				'bookingRules' => $this->booking_rules_by_doctor(),
				'clinics'      => $this->clinics_by_doctor(),
				// Drives the Selection/Identity steps' "skip entirely if
				// already known" behaviour — see resolved_selection() and
				// identity_fully_known(). Re-checked client-side too (against
				// the 'services'/'clinics' maps above) before being trusted,
				// in case something became invalid between render and load.
				'selectionFullyKnown' => $selection['selection_fully_known'],
				'identityFullyKnown'  => $this->identity_fully_known(),
			)
		);
	}

	/**
	 * Whether the current viewer is an Administrator or a Receptionist with
	 * appointment-management access — booking on behalf of a patient instead
	 * of for themselves, so step 3 shows a patient picker instead of the
	 * normal Login/Register/Guest choice.
	 *
	 * @return bool
	 */
	private static function is_staff() {
		return current_user_can( 'manage_options' ) || current_user_can( 'doctor_ak_manage_appointments' );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$selection = $this->resolved_selection();
		$doctor    = $selection['doctor'];

		// Staff (admin/receptionist) arriving from the Patients table's
		// "Book Appointment" action already has a patient in mind — skip
		// re-searching for them in step 3's patient picker.
		$selected_patient_id = 0;

		if ( self::is_staff() && isset( $_GET['patient_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$requested_patient_id = absint( $_GET['patient_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$patient               = $requested_patient_id > 0 ? get_userdata( $requested_patient_id ) : false;

			if ( $patient && in_array( Roles::PATIENT_ROLE, (array) $patient->roles, true ) ) {
				$selected_patient_id = $patient->ID;
			}
		}

		$selected_doctor_name = '';

		if ( $doctor ) {
			$selected_doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$selected_doctor_name = '' !== $selected_doctor_name ? $selected_doctor_name : $doctor->display_name;
		}

		$doctor_cards = $this->doctor_cards_data();

		return $this->template_loader->get_template(
			'booking/booking-page.php',
			array(
				'doctor_cards'            => $doctor_cards,
				'specialization_options'  => self::specialization_options_for_cards( $doctor_cards ),
				'selected_doctor_id'      => $doctor ? $doctor->ID : 0,
				'selected_doctor_name'    => $selected_doctor_name,
				'selected_type'           => $selection['type'],
				'video_disabled'          => $selection['video_disabled'],
				'selected_service_ids'    => $selection['selected_service_ids'],
				'selected_clinic_id'      => $selection['selected_clinic_id'],
				'selection_fully_known'   => $selection['selection_fully_known'],
				'identity_fully_known'    => $this->identity_fully_known(),
				'contact_url'             => self::contact_url(),
				'is_staff'                => self::is_staff(),
				'patient_options'         => self::is_staff() ? Appointments::patient_options() : array(),
				'selected_patient_id'     => $selected_patient_id,
			)
		);
	}

	/**
	 * Reads and validates the "already known" navigation state from the
	 * URL — doctor, appointment type, service(s), and clinic — so the
	 * Selection step can be skipped entirely when a patient already arrives
	 * with everything it would ask already decided (e.g. from a doctor's
	 * profile page, or a service's "Book" link). Invalid/foreign ids (e.g.
	 * a service belonging to a different doctor, or a stale/tampered
	 * clinic_id) are silently dropped rather than erroring — Selection
	 * still shows in that case, just without that one bad preselection,
	 * exactly like $doctor_id already behaved before this method existed.
	 * Called from both render() (to pick the initial step/prefill) and
	 * enqueue_assets() (to localize the same flag for the client-side
	 * re-check before goToStep() runs) — cheap enough to compute twice per
	 * request, and keeps both call sites from ever disagreeing.
	 *
	 * @return array {
	 *     @type \WP_User|false $doctor                 Validated doctor, or false.
	 *     @type string         $type                   'clinic' or 'video'.
	 *     @type bool           $video_disabled          Whether the doctor has no active video clinic.
	 *     @type int[]          $selected_service_ids   Validated service ids (clinic type only, empty for video).
	 *     @type int            $selected_clinic_id     Validated Clinics row id (0 if none requested/applicable).
	 *     @type bool           $selection_fully_known  Whether the Selection step can be skipped entirely.
	 * }
	 */
	private function resolved_selection() {
		$requested_doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
		$doctor               = $requested_doctor_id > 0 ? get_userdata( $requested_doctor_id ) : false;
		$doctor               = ( $doctor && in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) ? $doctor : false;
		$doctor               = ( $doctor && 'yes' !== get_user_meta( $doctor->ID, 'doctor_ak_account_disabled', true ) ) ? $doctor : false;

		$type           = ( isset( $_GET['type'] ) && 'video' === $_GET['type'] ) ? 'video' : 'clinic'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
		$video_disabled = false;

		if ( $doctor ) {
			$video_disabled = ! Clinics::doctor_has_active_video_clinic( $doctor->ID );

			if ( $video_disabled ) {
				$type = 'clinic';
			}
		}

		$selected_service_ids  = array();
		$selected_clinic_id    = 0;
		$selection_fully_known = false;

		if ( $doctor && 'video' === $type ) {
			$selection_fully_known = true;
		} elseif ( $doctor ) {
			$requested_service_ids = array();

			if ( isset( $_GET['service_ids'] ) && is_array( $_GET['service_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
				$requested_service_ids = array_map( 'absint', wp_unslash( $_GET['service_ids'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			} elseif ( isset( $_GET['service_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
				$requested_service_ids = array( absint( $_GET['service_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			}

			$valid_service_ids = wp_list_pluck( Services::active_for_doctor( $doctor->ID, 'clinic' ), 'id' );

			foreach ( $requested_service_ids as $requested_service_id ) {
				if ( $requested_service_id > 0 && in_array( $requested_service_id, $valid_service_ids, true ) ) {
					$selected_service_ids[] = $requested_service_id;
				}
			}

			$selected_service_ids = array_values( array_unique( $selected_service_ids ) );

			$doctor_clinic_ids = wp_list_pluck(
				array_filter(
					Clinics::get_for_doctor( $doctor->ID ),
					function ( $clinic ) {
						return Clinics::TYPE_PHYSICAL === $clinic['type'];
					}
				),
				'id'
			);

			$requested_clinic_id = isset( $_GET['clinic_id'] ) ? absint( $_GET['clinic_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
			$clinic_valid        = empty( $doctor_clinic_ids ) || in_array( $requested_clinic_id, $doctor_clinic_ids, true );

			if ( $clinic_valid && ! empty( $doctor_clinic_ids ) ) {
				$selected_clinic_id = $requested_clinic_id;
			}

			$selection_fully_known = ! empty( $selected_service_ids ) && $clinic_valid;
		}

		return array(
			'doctor'                => $doctor,
			'type'                  => $type,
			'video_disabled'        => $video_disabled,
			'selected_service_ids'  => $selected_service_ids,
			'selected_clinic_id'    => $selected_clinic_id,
			'selection_fully_known' => $selection_fully_known,
		);
	}

	/**
	 * Whether the Identity step can be skipped entirely — true only for a
	 * logged-in patient (never staff or a guest) who already has a phone
	 * number on file, regardless of clinic vs video booking: a clinic visit
	 * needs nothing else from them, and a video booking's only extra
	 * requirement (a phone on file, see Booking_Handler's phone check) is
	 * already satisfied too.
	 *
	 * @return bool
	 */
	private function identity_fully_known() {
		if ( self::is_staff() || ! $this->is_patient() ) {
			return false;
		}

		return '' !== get_user_meta( wp_get_current_user()->ID, 'doctor_ak_phone_number', true );
	}

	/**
	 * Specialization slug => label, restricted to only the specializations
	 * at least one listed doctor actually has — for the Doctor step's
	 * filter dropdown. Same "only what's actually in the list" convention
	 * Doctors_Directory::render() already uses for its own filters.
	 *
	 * @param array $doctor_cards Rows from doctor_cards_data().
	 * @return array
	 */
	private static function specialization_options_for_cards( array $doctor_cards ) {
		$all_specializations = Specializations::get_all();
		$options              = array();

		foreach ( $doctor_cards as $card ) {
			foreach ( $card['specialization_slugs'] as $slug ) {
				if ( isset( $all_specializations[ $slug ] ) ) {
					$options[ $slug ] = $all_specializations[ $slug ];
				}
			}
		}

		asort( $options );

		return $options;
	}

	/**
	 * Resolves a "Contact Us" page URL for the sidebar's "Need help with
	 * booking?" link, falling back to the home page if no such page exists.
	 *
	 * @return string
	 */
	private static function contact_url() {
		foreach ( array( 'contact', 'contact-us' ) as $slug ) {
			$page = get_page_by_path( $slug );

			if ( $page instanceof \WP_Post ) {
				return get_permalink( $page );
			}
		}

		return home_url( '/' );
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
				'role'       => Roles::DOCTOR_ROLE,
				'orderby'    => 'display_name',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- no better lookup available; excludes deactivated doctors from the booking wizard's doctor picker.
					'relation' => 'OR',
					array(
						'key'     => 'doctor_ak_account_disabled',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'doctor_ak_account_disabled',
						'value'   => 'yes',
						'compare' => '!=',
					),
				),
			)
		);

		$all_specializations = Specializations::get_all();
		$cards                = array();

		foreach ( $query->get_results() as $doctor ) {
			$display_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$display_name = '' !== $display_name ? $display_name : $doctor->display_name;

			$specialization_slugs = array_values(
				array_filter(
					(array) get_user_meta( $doctor->ID, 'doctor_ak_specializations', true ),
					function ( $slug ) use ( $all_specializations ) {
						return isset( $all_specializations[ $slug ] );
					}
				)
			);
			$specialization_label = ! empty( $specialization_slugs ) ? $all_specializations[ $specialization_slugs[0] ] : '';

			$cards[] = array(
				'id'                   => $doctor->ID,
				'name'                 => $display_name,
				'initials'             => self::initials( $display_name ),
				'specialization'       => $specialization_label,
				'specialization_slugs' => $specialization_slugs,
				'avatar_url'           => self::avatar_url( $doctor->ID ),
				'video_disabled'       => ! Clinics::doctor_has_active_video_clinic( $doctor->ID ),
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

		foreach ( Appointments::active_doctor_ids() as $doctor_id ) {
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
							// Clinic_Locations id => this doctor's own override
							// price at that clinic for this service, when set
							// (see Services::decode_row()) — lets the JS show/
							// sum the correct price as the patient's clinic
							// selection changes, instead of always the flat
							// charge above.
							'clinic_charges'   => $service['clinic_charges'],
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

		foreach ( Appointments::active_doctor_ids() as $doctor_id ) {
			$map[ $doctor_id ] = Video_Pricing::effective_price_for_doctor( $doctor_id );
		}

		return $map;
	}

	/**
	 * Every doctor's physical clinic locations, for the "Clinic Visit" step
	 * to let the patient pick which one they're visiting when a doctor
	 * practices at more than one — id, name, a composed address line, and
	 * phone.
	 *
	 * @return array doctor_id => [{id, name, address, phone}, ...].
	 */
	private function clinics_by_doctor() {
		$map = array();

		foreach ( Appointments::active_doctor_ids() as $doctor_id ) {
			$clinics = array_values(
				array_filter(
					Clinics::get_for_doctor( $doctor_id ),
					function ( $clinic ) {
						return Clinics::TYPE_PHYSICAL === $clinic['type'];
					}
				)
			);

			if ( empty( $clinics ) ) {
				continue;
			}

			$map[ $doctor_id ] = array_map(
				function ( $clinic ) {
					$address_line = implode(
						', ',
						array_filter( array( $clinic['address'], $clinic['area_label'], $clinic['city_label'] ) )
					);

					return array(
						'id'                 => $clinic['id'],
						'name'               => $clinic['name'],
						'address'            => $address_line,
						'phone'              => $clinic['phone'],
						// The shared Clinic_Locations id this Clinics row
						// points at — a different id space than 'id' above —
						// used client-side to look up a service's
						// clinic_charges override for this specific clinic.
						'clinic_location_id' => $clinic['clinic_location_id'],
					);
				},
				$clinics
			);
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

		foreach ( Appointments::active_doctor_ids() as $doctor_id ) {
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
