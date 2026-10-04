<?php
/**
 * Shared page-level behaviour for the public clinics directory and clinic
 * detail pages: body classes, the shared public stylesheet and
 * script, descriptive document titles, and keeping the first-load
 * promotional popup off these pages.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Public_Pages
 */
class Public_Pages {

	/**
	 * Shortcode tag => page kind.
	 *
	 * @var array
	 */
	const PAGES = array(
		Clinics_Directory::SHORTCODE_TAG   => 'clinics',
		Clinic_Profile_View::SHORTCODE_TAG => 'clinic',
	);

	/**
	 * One 20×20 stroke icon (decorative — always aria-hidden), shared by the
	 * three pages so icon style and size stay consistent.
	 *
	 * @param string $name Icon name.
	 * @return string SVG markup (static, safe to echo).
	 */
	public static function icon( $name ) {
		$paths = array(
			'pin'      => '<path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/>',
			'search'   => '<circle cx="8.8" cy="8.8" r="5.3"/><path d="M17 17l-3.8-3.8"/>',
			'phone'    => '<path d="M5 3.5h2.3l1 3.3-1.6 1.4a9 9 0 0 0 4.1 4.1l1.4-1.6 3.3 1v2.3c0 .8-.7 1.4-1.5 1.3C8.7 15 5 11.3 4.2 6c-.1-.8.5-1.5 1.3-1.5z"/>',
			'map'      => '<path d="M7 3.5 3 5v11.5l4-1.5 6 1.5 4-1.5V3.5l-4 1.5-6-1.5z"/><path d="M7 3.5v11.5M13 5v11.5"/>',
			'users'    => '<circle cx="7.5" cy="7" r="2.8"/><path d="M2.5 16c0-2.8 2.2-4.6 5-4.6s5 1.8 5 4.6"/><path d="M13 4.6a2.6 2.6 0 0 1 0 5"/><path d="M14.5 11.6c1.8.5 3 1.9 3 4.4"/>',
			'user'     => '<circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.5 4-5 6.5-5s5.5 1.5 6.5 5"/>',
			'steth'    => '<path d="M5.6 3.4v3.9a3 3 0 0 0 6 0V3.4"/><path d="M4.2 3.4h2.6M10.4 3.4H13"/><path d="M8.6 10.3v1.9a3.6 3.6 0 0 0 7.2 0v-1.4"/><circle cx="15.8" cy="9" r="1.6"/>',
			'calendar' => '<rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/>',
			'clock'    => '<circle cx="10" cy="10" r="7.2"/><path d="M10 6v4l3 2"/>',
			'video'    => '<rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M17.5 7.5 12.5 10l5 2.5z"/>',
			'arrow'    => '<path d="M3.5 10h12"/><path d="M11 5.5l4.5 4.5-4.5 4.5"/>',
			'external' => '<path d="M11 3.5h5.5V9"/><path d="M16.5 3.5 9 11"/><path d="M14 12.5v3a1 1 0 0 1-1 1H4.5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h3"/>',
			'info'     => '<circle cx="10" cy="10" r="7.2"/><path d="M10 9v4.5"/><path d="M10 6.3h.01"/>',
			'alert'    => '<path d="M10 3 2.5 16.5h15z"/><path d="M10 8.5v3.5"/><path d="M10 14.3h.01"/>',
			'building' => '<rect x="4" y="2.5" width="12" height="15" rx="1"/><path d="M8 17.5v-3h4v3"/><path d="M7.5 6h1M11.5 6h1M7.5 9h1M11.5 9h1M7.5 12h1M11.5 12h1"/>',
			'x'        => '<path d="M5.5 5.5l9 9M14.5 5.5l-9 9"/>',
			'tag'      => '<path d="M10 2.5l6.5 6.5-7.5 7.5-6.5-6.5V3.5z"/><circle cx="6.5" cy="6.5" r="1.2"/>',
			'play'     => '<circle cx="10" cy="10" r="7.5"/><path d="M8.3 7l4.2 3-4.2 3z"/>',
			'whatsapp' => '<path d="M10 2.5a7.5 7.5 0 0 0-6.4 11.4L2.5 17.5l3.7-1A7.5 7.5 0 1 0 10 2.5z"/><path d="M7.6 7.2c0 2.6 2.6 5.2 5.2 5.2l.9-1-1.6-.9-.7.6a3.6 3.6 0 0 1-1.7-1.7l.6-.7-.9-1.6z"/>',
			'check'    => '<path d="M4.5 10.5l3.5 3.5 7.5-8"/>',
			'list'     => '<path d="M7 5.5h10M7 10h10M7 14.5h10"/><path d="M3.5 5.5h.01M3.5 10h.01M3.5 14.5h.01"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Which of these pages is being viewed: 'clinics', 'clinic', or ''.
	 *
	 * @return string
	 */
	public static function current() {
		$post = is_singular() ? get_queried_object() : null;

		if ( ! ( $post instanceof \WP_Post ) && isset( $GLOBALS['post'] ) ) {
			$post = $GLOBALS['post'];
		}

		if ( ! ( $post instanceof \WP_Post ) ) {
			return '';
		}

		foreach ( self::PAGES as $tag => $kind ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return $kind;
			}
		}

		return '';
	}

	/**
	 * body_class filter.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function add_body_class( $classes ) {
		$kind = self::current();

		if ( '' !== $kind ) {
			$classes[] = 'dak-pub-page';
			$classes[] = 'dak-pub-page-' . $kind;
		}

		return $classes;
	}

	/**
	 * Enqueues the shared public stylesheet and script on these pages only.
	 * Runs late so it lands after the theme and Elementor kit styles.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( '' === self::current() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-public',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-public.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-public.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-public',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-public.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-public.js' ),
			true
		);
	}

	/**
	 * Descriptive title for the clinics directory and each clinic page —
	 * both otherwise inherit a doctor-specific title from the SEO plugin.
	 * Used for the document title and the SEO plugin's own title/social
	 * title filters. Leaves every other page's title untouched.
	 *
	 * @param string $title Title WordPress or the SEO plugin was about to use.
	 * @return string
	 */
	public function filter_title( $title ) {
		$kind = self::current();

		if ( 'clinics' !== $kind && 'clinic' !== $kind ) {
			return $title;
		}

		$site = get_bloginfo( 'name' );

		if ( 'clinics' === $kind ) {
			$cities = Clinic_Public_Data::city_list_label();

			$page_title = '' !== $cities
				/* translators: %s: list of cities, e.g. "Karachi, Hyderabad and Quetta". */
				? sprintf( __( 'Our clinics in %s', 'doctor-ak-portal' ), $cities )
				: __( 'Our clinics', 'doctor-ak-portal' );
		} else {
			$clinic_id = isset( $_GET['clinic_id'] ) ? absint( $_GET['clinic_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public lookup.
			$clinic    = $clinic_id > 0 ? Clinic_Public_Data::find( $clinic_id ) : null;

			if ( $clinic ) {
				$page_title = '' !== $clinic['place']
					/* translators: 1: clinic name, 2: "Area, City". */
					? sprintf( __( '%1$s, %2$s — doctors and directions', 'doctor-ak-portal' ), $clinic['name'], $clinic['place'] )
					/* translators: %s: clinic name. */
					: sprintf( __( '%s — doctors and directions', 'doctor-ak-portal' ), $clinic['name'] );
			} else {
				$page_title = __( 'Clinic not found', 'doctor-ak-portal' );
			}
		}

		return '' !== $site ? $page_title . ' | ' . $site : $page_title;
	}
}
