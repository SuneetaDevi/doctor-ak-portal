<?php
/**
 * Backs the [clinic_profile_view] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinic_Profile_View
 *
 * A public detail page for one clinic (Clinic_Locations row), reached via
 * `?clinic_id=` on whichever page contains [clinic_profile_view]. Lists each
 * active doctor practising there with what applies at THIS clinic — their
 * days/hours here and lowest fee here (from that doctor's own Clinics row
 * and services) — and books them with this clinic preselected: the booking
 * link carries the doctor's own Clinics row id (Booking_Page's clinic_id),
 * not the Clinic_Locations id in this page's URL.
 */
class Clinic_Profile_View {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'clinic_profile_view';

	/**
	 * Show the doctor search/specialty filter from this many doctors up.
	 *
	 * @var int
	 */
	const FILTER_THRESHOLD = 4;

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Doctors directory controller — supplies each doctor's name, photo,
	 * specialties, experience and profile link.
	 *
	 * @var Doctors_Directory
	 */
	private $doctors_directory;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader   $template_loader   Template loader.
	 * @param Doctors_Directory $doctors_directory Doctors directory controller.
	 */
	public function __construct( Template_Loader $template_loader, Doctors_Directory $doctors_directory ) {
		$this->template_loader   = $template_loader;
		$this->doctors_directory = $doctors_directory;
	}

	/**
	 * Enqueues the page script on pages containing [clinic_profile_view].
	 * Styles come from the shared public stylesheet (Public_Pages).
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_profile_view_page() ) {
			return;
		}

		wp_enqueue_script(
			'doctor-ak-portal-clinic-page',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-clinic-page.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-clinic-page.js' ),
			true
		);
	}

	/**
	 * template_redirect: an unknown or missing clinic_id is a "not found"
	 * page — sent with a 404 status so it isn't indexed as a real clinic.
	 *
	 * @return void
	 */
	public function maybe_not_found_status() {
		if ( 'clinic' !== Public_Pages::current() ) {
			return;
		}

		if ( ! self::requested_clinic() ) {
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * The clinic named by ?clinic_id=, or null.
	 *
	 * @return array|null Clinic_Public_Data::locations() row.
	 */
	private static function requested_clinic() {
		$clinic_id = isset( $_GET['clinic_id'] ) ? absint( $_GET['clinic_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public lookup.

		return $clinic_id > 0 ? Clinic_Public_Data::find( $clinic_id ) : null;
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$clinic  = self::requested_clinic();
		$doctors = $clinic ? $this->doctors_at( $clinic ) : array();

		$specialties = array();

		foreach ( $doctors as $doctor ) {
			foreach ( $doctor['specialties'] as $label ) {
				$specialties[ mb_strtolower( $label ) ] = $label;
			}
		}

		asort( $specialties );

		return $this->template_loader->get_template(
			'directory/clinic-profile-view.php',
			array(
				'clinic'        => $clinic,
				'doctors'       => $doctors,
				'specialties'   => $specialties,
				'show_filters'  => count( $doctors ) >= self::FILTER_THRESHOLD,
				'booking_line'  => Clinic_Public_Data::booking_line(),
				'directory_url' => Page_Finder::url_for_shortcode( Clinics_Directory::SHORTCODE_TAG ),
				'home_url'      => home_url( '/' ),
			)
		);
	}

	/**
	 * Every active doctor at this clinic, with this clinic's details first.
	 *
	 * @param array $clinic Clinic_Public_Data::locations() row.
	 * @return array List of {
	 *     @type int      $id               Doctor user ID.
	 *     @type string   $name             "Dr. Full Name".
	 *     @type string   $initials         Fallback when there's no photo.
	 *     @type string   $photo_url        Uploaded photo URL, or ''.
	 *     @type string[] $specialties      Specialty labels (may be empty).
	 *     @type int      $years            Years of experience, 0 if not set.
	 *     @type string[] $schedule         Days/hours at this clinic (may be empty).
	 *     @type float    $fee_from         Lowest service fee at this clinic, 0 if none.
	 *     @type string[] $other_locations  Other clinics where they practise.
	 *     @type string   $profile_url      Doctor profile URL.
	 *     @type string   $book_url         Booking URL with this doctor + clinic preselected, or ''.
	 *     @type string   $video_url        Online-consultation booking URL, or '' if not offered.
	 * }
	 */
	private function doctors_at( array $clinic ) {
		$rows_by_doctor = array();

		foreach ( $clinic['doctor_rows'] as $row ) {
			$rows_by_doctor[ (int) $row['doctor_id'] ] = $row;
		}

		if ( empty( $rows_by_doctor ) ) {
			return array();
		}

		$cards       = $this->doctors_directory->doctor_cards_data_for_ids( array_keys( $rows_by_doctor ) );
		$all_clinics = Clinics::get_for_doctors( array_keys( $rows_by_doctor ) );
		$booking_url = Page_Finder::url_for_shortcode( Booking_Page::SHORTCODE_TAG );
		$doctors     = array();

		foreach ( $cards as $card ) {
			$doctor_id = (int) $card['id'];

			if ( ! isset( $rows_by_doctor[ $doctor_id ] ) ) {
				continue;
			}

			$row   = $rows_by_doctor[ $doctor_id ];
			$other = array();

			foreach ( isset( $all_clinics[ $doctor_id ] ) ? $all_clinics[ $doctor_id ] : array() as $doctor_clinic ) {
				if ( Clinics::TYPE_PHYSICAL !== $doctor_clinic['type'] || (int) $doctor_clinic['id'] === (int) $row['id'] || (int) $doctor_clinic['clinic_location_id'] === (int) $clinic['id'] ) {
					continue;
				}

				$label = '' !== $doctor_clinic['name'] ? $doctor_clinic['name'] : $doctor_clinic['address'];
				$place = implode( ', ', array_filter( array( $doctor_clinic['area_label'], $doctor_clinic['city_label'] ) ) );

				if ( '' !== $label ) {
					$other[] = '' !== $place ? $label . ' — ' . $place : $label;
				}
			}

			$photo_id  = (int) get_user_meta( $doctor_id, 'doctor_ak_profile_picture_id', true );
			$photo_url = $photo_id > 0 ? (string) wp_get_attachment_image_url( $photo_id, 'medium' ) : '';

			$doctors[] = array(
				'id'              => $doctor_id,
				/* translators: %s: doctor's name. */
				'name'            => sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $card['name'] ),
				'initials'        => self::initials( $card['name'] ),
				'photo_url'       => $photo_url,
				'specialties'     => $card['specialization_labels'],
				'years'           => '' !== (string) $card['years_experience'] ? (int) $card['years_experience'] : 0,
				'schedule'        => Clinic_Public_Data::schedule_lines( $row ),
				'fee_from'        => Clinic_Public_Data::lowest_fee_at( $doctor_id, (int) $clinic['id'] ),
				'other_locations' => array_values( array_unique( $other ) ),
				'profile_url'     => $card['profile_url'],
				'book_url'        => $booking_url ? add_query_arg(
					array(
						'doctor_id' => $doctor_id,
						'type'      => 'clinic',
						'clinic_id' => (int) $row['id'],
					),
					$booking_url
				) : '',
				'video_url'       => $booking_url && ! empty( $card['video_consultation'] ) ? add_query_arg(
					array(
						'doctor_id' => $doctor_id,
						'type'      => 'video',
					),
					$booking_url
				) : '',
			);
		}

		return $doctors;
	}

	/**
	 * Up to two initials from a name, for the photo fallback.
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	private static function initials( $name ) {
		$initials = '';

		foreach ( array_slice( preg_split( '/\s+/', trim( (string) $name ) ), 0, 2 ) as $part ) {
			$initials .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
		}

		return $initials;
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
