<?php
/**
 * Backs the [symptom_checker] shortcode — "Find a doctor by symptoms".
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Symptom_Mappings;
use DoctorAKPortal\Includes\Symptom_Safety_Rules;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Symptom_Checker
 *
 * Patients enter symptoms, confirm what was recognised, answer any
 * clinician-approved follow-up questions, and see doctors in the
 * specialties those symptoms (and answers) are mapped to, nearest first.
 * Approved safety rules show their instructions as soon as a matching
 * answer is given. It helps people find a doctor — it gives no diagnosis,
 * risk score or treatment advice.
 *
 * Everything the visitor types or answers, and their location, stays in
 * their browser (doctor-ak-symptom-match.js / doctor-ak-symptom-finder.js):
 * no AJAX, no URL parameters, no storage. The page only receives the
 * published (approved) versions of live symptoms and safety rules, the
 * approved emergency notice and the same public doctor/clinic details the
 * Find a Doctor page shows — never drafts, notes or reviewer details.
 *
 * Patients see it only when Symptom_Mappings::is_public(); until then,
 * administrators and clinical reviewers get a labelled preview and everyone
 * else a short "not available" notice.
 */
class Symptom_Checker {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'symptom_checker';

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * What this request shows, worked out once.
	 *
	 * @var string|null 'live', 'preview' or 'unavailable'.
	 */
	private $state = null;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader $template_loader Template loader.
	 */
	public function __construct( Template_Loader $template_loader ) {
		$this->template_loader = $template_loader;
	}

	/**
	 * Registers the shortcode.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render' ) );
	}

	/**
	 * URL of the page when patients may use it, else ''. Entry points (home
	 * page, site header) only appear when this is set.
	 *
	 * @return string
	 */
	public static function public_url() {
		return Symptom_Mappings::is_public() ? Page_Finder::url_for_shortcode( self::SHORTCODE_TAG ) : '';
	}

	/**
	 * Whether the current user is staff who may preview the page before
	 * it's public.
	 *
	 * @return bool
	 */
	private static function is_staff() {
		return Symptom_Mappings::can_manage();
	}

	/**
	 * 'live' for everyone once public; 'preview' for staff (before that, or
	 * with ?sc_preview=1); otherwise 'unavailable'.
	 *
	 * @return string
	 */
	private function state() {
		if ( null !== $this->state ) {
			return $this->state;
		}

		$wants_preview = isset( $_GET['sc_preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, staff-only preview switch.

		if ( Symptom_Mappings::is_public() && ! ( self::is_staff() && $wants_preview ) ) {
			$this->state = 'live';
		} elseif ( self::is_staff() ) {
			$this->state = 'preview';
		} else {
			$this->state = 'unavailable';
		}

		return $this->state;
	}

	/**
	 * Enqueues assets only on the page containing the shortcode.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) || ! has_shortcode( $post->post_content, self::SHORTCODE_TAG ) ) {
			return;
		}

		wp_enqueue_style( 'doctor-ak-portal-auth', DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css', array(), Assets::version( 'assets/css/doctor-ak-auth.css' ) );
		wp_enqueue_style( 'doctor-ak-portal-symptom-checker', DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-symptom-checker.css', array( 'doctor-ak-portal-auth' ), Assets::version( 'assets/css/doctor-ak-symptom-checker.css' ) );

		if ( 'unavailable' === $this->state() ) {
			return;
		}

		wp_enqueue_script( 'doctor-ak-portal-symptom-match', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-symptom-match.js', array(), Assets::version( 'assets/js/doctor-ak-symptom-match.js' ), true );
		wp_enqueue_script( 'doctor-ak-portal-symptom-finder', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-symptom-finder.js', array( 'doctor-ak-portal-symptom-match' ), Assets::version( 'assets/js/doctor-ak-symptom-finder.js' ), true );

		wp_localize_script( 'doctor-ak-portal-symptom-finder', 'dakSymptomFinder', $this->script_data() );
	}

	/**
	 * Data the page script needs: mappings, specialty names and public
	 * directory details. Nothing about the visitor.
	 *
	 * @return array
	 */
	private function script_data() {
		$preview     = 'live' !== $this->state();
		$specialties = Specializations::get_all();
		$known       = array_keys( $specialties );
		$mappings    = array();

		foreach ( Symptom_Mappings::public_items( $preview ) as $item ) {
			$item['specialties'] = array_values( array_intersect( $item['specialties'], $known ) );

			foreach ( $item['questions'] as &$question ) {
				foreach ( $question['options'] as &$option ) {
					$option['specialties'] = array_values( array_intersect( (array) $option['specialties'], $known ) );
				}
				unset( $option );
			}
			unset( $question );

			if ( ! $preview ) {
				unset( $item['status'] );
			}

			$mappings[] = $item;
		}

		$rules = array();

		foreach ( Symptom_Safety_Rules::public_items( $preview ) as $rule ) {
			$rules[] = array(
				'id'        => (int) $rule['id'],
				'title'     => $rule['title'],
				'level'     => $rule['level'],
				'message'   => $rule['message'],
				'interrupt' => (bool) $rule['interrupt'],
				'triggers'  => $rule['triggers'],
			);
		}

		$directory = new Doctors_Directory( $this->template_loader );
		$doctors   = array();
		$cities    = array();

		foreach ( $directory->cards_with_clinic_details() as $card ) {
			$slugs = array_values( array_intersect( (array) $card['specialization_slugs'], array_keys( $specialties ) ) );

			if ( empty( $slugs ) ) {
				continue;
			}

			$clinics = array();

			foreach ( $card['clinic_details'] as $clinic ) {
				$clinics[] = array(
					'id'             => $clinic['id'],
					'label'          => $clinic['name'],
					'place'          => $clinic['place'],
					'city_slug'      => $clinic['city'],
					'area_slug'      => $clinic['area'],
					'lat'            => $clinic['lat'],
					'lng'            => $clinic['lng'],
					'fee'            => 'unset' !== $clinic['fee']['state'] ? $clinic['fee']['label'] : '',
					'next_available' => $clinic['next_available'],
					'booking_url'    => $clinic['booking_url'],
				);

				if ( '' !== $clinic['city'] ) {
					if ( ! isset( $cities[ $clinic['city'] ] ) ) {
						$cities[ $clinic['city'] ] = array(
							'slug'  => $clinic['city'],
							'label' => '' !== $clinic['city_label'] ? $clinic['city_label'] : ucwords( str_replace( '-', ' ', $clinic['city'] ) ),
							'areas' => array(),
						);
					}

					if ( '' !== $clinic['area'] ) {
						$cities[ $clinic['city'] ]['areas'][ $clinic['area'] ] = array(
							'slug'  => $clinic['area'],
							'label' => '' !== $clinic['area_label'] ? $clinic['area_label'] : ucwords( str_replace( '-', ' ', $clinic['area'] ) ),
						);
					}
				}
			}

			$doctors[] = array(
				'id'          => (int) $card['id'],
				'name'        => $card['display_name'],
				'avatar'      => $card['avatar_url'],
				'specialties' => $slugs,
				'profile_url' => $card['profile_url'],
				'next_at'     => $card['next_at'],
				'clinics'     => $clinics,
				'video'       => array(
					'offered'        => ! empty( $card['video_consultation'] ) && '' !== $card['video_booking_url'],
					'fee'            => $card['video_fee'] && 'unset' !== $card['video_fee']['state'] ? $card['video_fee']['label'] : '',
					'next_available' => $card['video_next_available'],
					'booking_url'    => $card['video_booking_url'],
				),
			);
		}

		uasort(
			$cities,
			function ( $a, $b ) {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		$locations = array();

		foreach ( $cities as $city ) {
			$areas = array_values( $city['areas'] );
			usort(
				$areas,
				function ( $a, $b ) {
					return strcasecmp( $a['label'], $b['label'] );
				}
			);
			$city['areas'] = $areas;
			$locations[]   = $city;
		}

		// Named clinic areas, to say "near Clifton" instead of coordinates.
		$places = array();

		foreach ( Clinic_Locations::get_all() as $location ) {
			if ( null !== $location['latitude'] ) {
				$places[] = array(
					'label' => '' !== $location['area_label'] ? $location['area_label'] : $location['city_label'],
					'lat'   => $location['latitude'],
					'lng'   => $location['longitude'],
				);
			}
		}

		$directory_url = Page_Finder::url_for_shortcode( 'doctors_directory' );

		// A staff preview can open with one symptom already chosen (from
		// the admin screen's "Preview" button). Never used for patients.
		$preview_symptom = 0;

		if ( $preview && isset( $_GET['sc_symptom'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, staff-only preview.
			$preview_symptom = absint( wp_unslash( $_GET['sc_symptom'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return array(
			'preview'         => $preview,
			'previewSymptom'  => $preview_symptom,
			'mappings'        => $mappings,
			'rules'           => $rules,
			'specialtyLabels' => $specialties,
			'doctors'         => $doctors,
			'locations'       => $locations,
			'places'          => $places,
			'directoryUrl'    => $directory_url,
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$state  = $this->state();
		$notice = Symptom_Mappings::notice();

		return $this->template_loader->get_template(
			'directory/symptom-checker.php',
			array(
				'state'          => $state,
				// Patients see the approved wording; a staff preview shows the
				// working copy (labelled while it awaits approval).
				'notice_text'    => 'preview' === $state ? $notice['text'] : $notice['approved_text'],
				'notice_pending' => 'preview' === $state && 'approved' !== $notice['status'],
				'missing'        => Symptom_Mappings::missing_requirements(),
				'directory_url'  => Page_Finder::url_for_shortcode( 'doctors_directory' ),
				'manage_url'     => self::is_staff() ? self::manage_url() : '',
			)
		);
	}

	/**
	 * Where staff manage the mappings: the admin dashboard section, or the
	 * reviewer tab on the doctor dashboard.
	 *
	 * @return string
	 */
	private static function manage_url() {
		if ( current_user_can( 'manage_options' ) ) {
			$url = Page_Finder::url_for_shortcode( Admin_Dashboard::SHORTCODE_TAG );
			return $url ? add_query_arg( 'section', 'symptom-checker', $url ) : '';
		}

		$url = Page_Finder::url_for_shortcode( Doctor_Dashboard::SHORTCODE_TAG );

		return $url ? add_query_arg( 'tab', 'symptom-review', $url ) : '';
	}
}
