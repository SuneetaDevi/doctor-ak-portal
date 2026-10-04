<?php
/**
 * Backs the [clinics_directory] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinics_Directory
 *
 * A public clinic finder — one card per published clinic (Clinic_Locations
 * row), each linking to that clinic's own [clinic_profile_view] page. Search
 * (name/address), city, area and sort live in the URL (?q=&city=&area=&sort=),
 * so the server renders the filtered list itself (works without JavaScript,
 * and Back from a clinic page restores the same results); the page script
 * then filters the complete list instantly as the visitor types or picks,
 * keeping the URL in step. There is no pagination — every clinic is always
 * on the page, so filters always cover the whole dataset.
 */
class Clinics_Directory {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'clinics_directory';

	/**
	 * Sort options: key => label. The first is the default.
	 *
	 * @return array
	 */
	public static function sort_options() {
		return array(
			'name'    => __( 'Name (A–Z)', 'doctor-ak-portal' ),
			'name-za' => __( 'Name (Z–A)', 'doctor-ak-portal' ),
			'doctors' => __( 'Most doctors', 'doctor-ak-portal' ),
		);
	}

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
	 * Enqueues the finder script on pages containing [clinics_directory].
	 * Styles come from the shared public stylesheet (Public_Pages).
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_directory_page() ) {
			return;
		}

		wp_enqueue_script(
			'doctor-ak-portal-clinic-finder',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-clinic-finder.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-clinic-finder.js' ),
			true
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		global $wpdb;

		$locations  = Clinic_Public_Data::locations();
		$load_error = empty( $locations ) && '' !== (string) $wpdb->last_error;
		$cities     = Clinic_Public_Data::cities();
		$filters    = $this->requested_filters( $cities );
		$sorted     = $this->sorted( $locations, $filters['sort'] );
		$visible    = 0;
		$cards_html = array();

		foreach ( $sorted as $location ) {
			$matches = $this->matches( $location, $filters );

			if ( $matches ) {
				++$visible;
			}

			$cards_html[] = $this->template_loader->get_template(
				'directory/clinic-card.php',
				array(
					'clinic'  => $location,
					'hidden'  => ! $matches,
					'heading' => 'h2',
				)
			);
		}

		return $this->template_loader->get_template(
			'directory/clinics-directory.php',
			array(
				'cards_html'    => $cards_html,
				'total'         => count( $locations ),
				'visible'       => $visible,
				'filters'       => $filters,
				'cities'        => $cities,
				'sort_options'  => self::sort_options(),
				'city_label'    => Clinic_Public_Data::city_list_label(),
				'booking_line'  => Clinic_Public_Data::booking_line(),
				'load_error'    => $load_error,
				'home_url'      => home_url( '/' ),
				'directory_url' => get_permalink(),
			)
		);
	}

	/**
	 * The search/filter/sort state from the URL, validated against the
	 * published cities and areas (anything unknown is ignored).
	 *
	 * @param array $cities Clinic_Public_Data::cities().
	 * @return array { q, city, area, sort, city_label, area_label }
	 */
	private function requested_filters( array $cities ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only public filters.
		$q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$city = isset( $_GET['city'] ) ? sanitize_key( wp_unslash( $_GET['city'] ) ) : '';
		$area = isset( $_GET['area'] ) ? sanitize_key( wp_unslash( $_GET['area'] ) ) : '';
		$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : '';
		// phpcs:enable

		$city_label = '';
		$area_label = '';

		foreach ( $cities as $row ) {
			if ( $row['slug'] !== $city ) {
				continue;
			}

			$city_label = $row['label'];

			foreach ( $row['areas'] as $area_row ) {
				if ( $area_row['slug'] === $area ) {
					$area_label = $area_row['label'];
				}
			}
		}

		if ( '' === $city_label ) {
			$city = '';
		}

		if ( '' === $area_label ) {
			$area = '';
		}

		if ( ! array_key_exists( $sort, self::sort_options() ) ) {
			$sort = 'name';
		}

		return array(
			'q'          => mb_substr( $q, 0, 100 ),
			'city'       => $city,
			'area'       => $area,
			'sort'       => $sort,
			'city_label' => $city_label,
			'area_label' => $area_label,
		);
	}

	/**
	 * Whether a clinic matches the active filters — the same rules the
	 * finder script applies client-side (search text is matched against the
	 * name, address, area and city, case-insensitively).
	 *
	 * @param array $location One Clinic_Public_Data::locations() row.
	 * @param array $filters  requested_filters().
	 * @return bool
	 */
	private function matches( array $location, array $filters ) {
		if ( '' !== $filters['city'] && $location['city'] !== $filters['city'] ) {
			return false;
		}

		if ( '' !== $filters['area'] && $location['area'] !== $filters['area'] ) {
			return false;
		}

		if ( '' === $filters['q'] ) {
			return true;
		}

		$haystack = mb_strtolower( implode( ' ', array( $location['name'], $location['address'], $location['area_label'], $location['city_label'] ) ) );

		foreach ( preg_split( '/\s+/', mb_strtolower( trim( $filters['q'] ) ) ) as $word ) {
			if ( '' !== $word && false === mb_strpos( $haystack, $word ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Locations in the requested order.
	 *
	 * @param array  $locations Clinic_Public_Data::locations().
	 * @param string $sort      Sort key.
	 * @return array
	 */
	private function sorted( array $locations, $sort ) {
		usort(
			$locations,
			function ( $a, $b ) use ( $sort ) {
				$by_name = strcasecmp( $a['name'], $b['name'] );

				if ( 'name-za' === $sort ) {
					return -$by_name;
				}

				if ( 'doctors' === $sort && $a['doctor_count'] !== $b['doctor_count'] ) {
					return $b['doctor_count'] - $a['doctor_count'];
				}

				return $by_name;
			}
		);

		return $locations;
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * directory shortcode.
	 *
	 * @return bool
	 */
	private function is_directory_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
