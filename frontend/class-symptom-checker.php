<?php
/**
 * Backs the [symptom_checker] shortcode — the public Symptom Checker page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Symptom_Rules;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Symptom_Checker
 *
 * The page embeds the published ruleset plus the same public doctor and
 * clinic information the Find a Doctor page shows; everything the visitor
 * types, answers or shares (including their location) is processed in
 * their browser by doctor-ak-symptom-engine.js and never sent to the site —
 * no AJAX, no URL parameters, no storage.
 *
 * Patients only see it once a designated clinical reviewer has approved a
 * (non-demonstration) version and an administrator has switched it on
 * (Symptom_Rules::is_public()). Until then administrators get a clearly
 * labelled preview of the draft, and everyone else a short "not available"
 * notice.
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
	 * @var array|null
	 */
	private $mode = null;

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
	 * URL of the Symptom Checker page when patients may use it, else ''.
	 * Entry points (home page, site header) only appear when this is set.
	 *
	 * @return string
	 */
	public static function public_url() {
		return Symptom_Rules::is_public() ? Page_Finder::url_for_shortcode( self::SHORTCODE_TAG ) : '';
	}

	/**
	 * Which rules this request uses: the published version for everyone
	 * when the checker is public; for an administrator, the draft (with
	 * ?sc_preview=draft, or whenever it isn't public yet); nothing for
	 * other visitors while it isn't public.
	 *
	 * @return array { state: 'live'|'preview'|'unavailable', rules: array|null, label: string, errors: string[], is_demo: bool }
	 */
	private function mode() {
		if ( null !== $this->mode ) {
			return $this->mode;
		}

		$is_admin  = current_user_can( 'manage_options' );
		$published = Symptom_Rules::get_published();
		$wants     = isset( $_GET['sc_preview'] ) && 'draft' === sanitize_key( wp_unslash( $_GET['sc_preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, staff-only preview switch.

		if ( Symptom_Rules::is_public() && ! ( $is_admin && $wants ) ) {
			$this->mode = array(
				'state'   => 'live',
				'rules'   => $published['rules'],
				'label'   => '',
				'errors'  => array(),
				'is_demo' => false,
			);

			return $this->mode;
		}

		if ( ! $is_admin ) {
			$this->mode = array(
				'state'   => 'unavailable',
				'rules'   => null,
				'label'   => '',
				'errors'  => array(),
				'is_demo' => false,
			);

			return $this->mode;
		}

		$draft  = Symptom_Rules::get_draft();
		$parsed = '' !== trim( $draft['json'] ) ? Symptom_Rules::parse( $draft['json'] ) : null;

		if ( $parsed && $parsed['rules'] ) {
			$rules = $parsed['rules'];
			/* translators: %s: ruleset name. */
			$label = sprintf( __( 'Draft: %s', 'doctor-ak-portal' ), isset( $rules['meta']['name'] ) ? $rules['meta']['name'] : __( 'untitled', 'doctor-ak-portal' ) );
		} elseif ( $published ) {
			$rules = $published['rules'];
			/* translators: %d: version number. */
			$label = sprintf( __( 'Approved version %d', 'doctor-ak-portal' ), $published['version'] );
		} else {
			$rules = null;
			$label = '';
		}

		$this->mode = array(
			'state'   => $rules ? 'preview' : 'unavailable',
			'rules'   => $rules,
			'label'   => $label,
			'errors'  => $parsed ? $parsed['errors'] : array(),
			'is_demo' => $rules && Symptom_Rules::is_demo( $rules ),
		);

		return $this->mode;
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

		$mode = $this->mode();

		if ( ! $mode['rules'] ) {
			return;
		}

		wp_enqueue_script( 'doctor-ak-portal-symptom-engine', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-symptom-engine.js', array(), Assets::version( 'assets/js/doctor-ak-symptom-engine.js' ), true );
		wp_enqueue_script( 'doctor-ak-portal-symptom-checker', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-symptom-checker.js', array( 'doctor-ak-portal-symptom-engine' ), Assets::version( 'assets/js/doctor-ak-symptom-checker.js' ), true );

		wp_localize_script( 'doctor-ak-portal-symptom-checker', 'dakSymptomChecker', $this->script_data( $mode['rules'] ) );
	}

	/**
	 * Data the page script needs — the rules and public directory data only.
	 *
	 * @param array $rules Ruleset.
	 * @return array
	 */
	private function script_data( array $rules ) {
		$directory   = new Doctors_Directory( $this->template_loader );
		$specialties = Specializations::get_all();
		$doctors     = array();
		$cities      = array();
		$places      = array();

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
					'lat'            => $clinic['lat'],
					'lng'            => $clinic['lng'],
					'fee'            => 'unset' !== $clinic['fee']['state'] ? $clinic['fee']['label'] : '',
					'next_available' => $clinic['next_available'],
					'booking_url'    => $clinic['booking_url'],
					'map_url'        => $clinic['map_url'],
				);

				if ( '' !== $clinic['city'] && ! isset( $cities[ $clinic['city'] ] ) ) {
					$cities[ $clinic['city'] ] = '' !== $clinic['city_label'] ? $clinic['city_label'] : ucwords( str_replace( '-', ' ', $clinic['city'] ) );
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

		// Named places for "Near …": shared clinics with map coordinates.
		foreach ( Clinic_Locations::get_all() as $location ) {
			if ( null === $location['latitude'] ) {
				continue;
			}

			$places[] = array(
				'label' => '' !== $location['area_label'] ? $location['area_label'] : $location['city_label'],
				'lat'   => $location['latitude'],
				'lng'   => $location['longitude'],
			);
		}

		asort( $cities );

		$city_list = array();

		foreach ( $cities as $slug => $label ) {
			$city_list[] = array(
				'slug'  => $slug,
				'label' => $label,
			);
		}

		return array(
			'rules'           => $rules,
			'doctors'         => $doctors,
			'specialtyLabels' => $specialties,
			'cities'          => $city_list,
			'places'          => $places,
			'directoryUrl'    => Page_Finder::url_for_shortcode( 'doctors_directory' ),
			'radiusKm'        => 25,
			'expandedKm'      => 100,
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$mode = $this->mode();

		return $this->template_loader->get_template(
			'directory/symptom-checker.php',
			array(
				'state'         => $mode['state'],
				'preview_label' => $mode['label'],
				'is_demo'       => $mode['is_demo'],
				'errors'        => $mode['errors'],
				'directory_url' => Page_Finder::url_for_shortcode( 'doctors_directory' ),
				'admin_url'     => current_user_can( 'manage_options' ) && Page_Finder::url_for_shortcode( Admin_Dashboard::SHORTCODE_TAG ) ? add_query_arg( 'section', 'symptom-checker', Page_Finder::url_for_shortcode( Admin_Dashboard::SHORTCODE_TAG ) ) : '',
			)
		);
	}
}
