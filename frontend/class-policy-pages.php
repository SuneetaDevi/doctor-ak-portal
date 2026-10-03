<?php
/**
 * Shared layout for the site's three policy pages — Terms and Conditions,
 * Privacy Policy and Cancellation Policy.
 *
 * These are ordinary WordPress pages (content edited in WP admin). With the
 * active theme's default page template they rendered the theme's own header
 * and copyright footer underneath/above this plugin's Site_Header/
 * Site_Footer, plus the Elementor kit's global typography (which
 * capitalizes every word of body text). This class swaps in one shared
 * policy template instead — same pattern Dashboard_Layout uses for the
 * plugin's own pages — and only for these three pages.
 *
 * Content source: the page's stored content is always respected. For each
 * page, an editorially corrected version (grammar, casing, formatting,
 * consistent naming — no change in meaning) ships in
 * templates/policy/content/. It is shown only while the stored page content
 * is still exactly the version it was edited from (compared by a
 * fingerprint of its text); as soon as anyone edits the page in WordPress,
 * the stored content is shown instead, so WP admin stays the source of truth.
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
 * Class Policy_Pages
 */
class Policy_Pages {

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
	 * The policy pages, keyed by page slug, in navigation order. 'file' is
	 * the edited-content file under templates/policy/content/.
	 *
	 * @return array
	 */
	public static function policies() {
		return array(
			'terms-and-conditions' => array(
				'label' => __( 'Terms and Conditions', 'doctor-ak-portal' ),
				'short' => __( 'Terms', 'doctor-ak-portal' ),
				'intro' => __( 'Please read these terms before using our website or booking a consultation with us.', 'doctor-ak-portal' ),
				'file'  => 'terms-and-conditions.php',
			),
			'privacy-policy'       => array(
				'label' => __( 'Privacy Policy', 'doctor-ak-portal' ),
				'short' => __( 'Privacy', 'doctor-ak-portal' ),
				'intro' => __( 'How we collect, use, share and protect your personal information.', 'doctor-ak-portal' ),
				'file'  => 'privacy-policy.php',
			),
			'cancellation-policy'  => array(
				'label' => __( 'Cancellation Policy', 'doctor-ak-portal' ),
				'short' => __( 'Cancellation', 'doctor-ak-portal' ),
				'intro' => __( 'How to cancel an appointment, and when a refund applies.', 'doctor-ak-portal' ),
				'file'  => 'cancellation-policy.php',
			),
		);
	}

	/**
	 * Slug of the policy page being viewed, or '' when it isn't one.
	 *
	 * @return string
	 */
	public static function current_slug() {
		if ( ! is_page() ) {
			return '';
		}

		$post = get_queried_object();

		if ( ! ( $post instanceof \WP_Post ) ) {
			return '';
		}

		return array_key_exists( $post->post_name, self::policies() ) ? $post->post_name : '';
	}

	/**
	 * template_include filter: serve the shared policy template.
	 *
	 * @param string $template Template path WordPress was about to load.
	 * @return string
	 */
	public function template_include( $template ) {
		if ( '' === self::current_slug() ) {
			return $template;
		}

		return DOCTOR_AK_PORTAL_PATH . 'templates/policy/policy-page.php';
	}

	/**
	 * body_class filter.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function add_body_class( $classes ) {
		if ( '' !== self::current_slug() ) {
			$classes[] = 'dak-policy-page';
		}

		return $classes;
	}

	/**
	 * Enqueues the policy stylesheet on policy pages only.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( '' === self::current_slug() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-policy',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-policy.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-policy.css' )
		);
	}

	/**
	 * Everything the policy template needs for the current page: its
	 * label/intro, the content HTML (with heading anchors added), the
	 * "On this page" entries, and the verified effective date if any.
	 *
	 * @param string $slug       Current policy slug.
	 * @param string $stored_html The page's stored content after the_content filters.
	 * @return array
	 */
	public static function view_data( $slug, $stored_html ) {
		$policies = self::policies();
		$policy   = $policies[ $slug ];
		$edited   = self::edited_content( $policy['file'] );
		$html     = $stored_html;
		$meta     = array();

		if ( $edited && hash_equals( $edited['fingerprint'], self::fingerprint( $stored_html ) ) ) {
			$html = $edited['html'];
			$meta = isset( $edited['meta'] ) ? $edited['meta'] : array();
		}

		$anchored = self::add_heading_anchors( $html );

		return array(
			'slug'     => $slug,
			'policy'   => $policy,
			'policies' => $policies,
			'html'     => $anchored['html'],
			'toc'      => $anchored['toc'],
			'meta'     => $meta,
			'home_url' => home_url( '/' ),
		);
	}

	/**
	 * Loads an edited-content file: array{ fingerprint, html, meta }.
	 *
	 * @param string $file File name under templates/policy/content/.
	 * @return array|null
	 */
	private static function edited_content( $file ) {
		$path = DOCTOR_AK_PORTAL_PATH . 'templates/policy/content/' . $file;

		if ( ! file_exists( $path ) ) {
			return null;
		}

		$data = include $path;

		return is_array( $data ) && isset( $data['fingerprint'], $data['html'] ) ? $data : null;
	}

	/**
	 * A stable fingerprint of a page's visible text — tags, entities,
	 * whitespace and letter case ignored — used to tell whether the stored
	 * content is still the version an edited file was prepared from.
	 *
	 * @param string $html Rendered content HTML.
	 * @return string
	 */
	public static function fingerprint( $html ) {
		$text = html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\x{2018}\x{2019}]/u', "'", $text );
		$text = preg_replace( '/[\x{201C}\x{201D}]/u', '"', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return md5( mb_strtolower( trim( $text ) ) );
	}

	/**
	 * Gives every section heading an id (kept if it already has one) and
	 * returns the "On this page" list built from them. Sections are h2s, or
	 * h3s when the content has no h2 at all (the stored Privacy and
	 * Cancellation pages use h3 for their sections).
	 *
	 * @param string $html Content HTML.
	 * @return array{ html: string, toc: array }
	 */
	private static function add_heading_anchors( $html ) {
		$toc   = array();
		$used  = array();
		$level = preg_match( '/<h2[\s>]/i', $html ) ? 'h2' : 'h3';

		$html = preg_replace_callback(
			'/<' . $level . '(\s[^>]*)?>(.*?)<\/' . $level . '>/is',
			function ( $m ) use ( &$toc, &$used, $level ) {
				$attrs = isset( $m[1] ) ? $m[1] : '';
				$label = trim( html_entity_decode( wp_strip_all_tags( $m[2] ), ENT_QUOTES, 'UTF-8' ) );

				if ( preg_match( '/\sid="([^"]+)"/', $attrs, $id_match ) ) {
					$id = $id_match[1];
				} else {
					$base = sanitize_title( preg_replace( '/^\d+\.\s*/', '', $label ) );
					$base = '' !== $base ? $base : 'section';
					$id   = $base;
					$n    = 2;

					while ( isset( $used[ $id ] ) ) {
						$id = $base . '-' . $n++;
					}

					$attrs .= ' id="' . esc_attr( $id ) . '"';
				}

				$used[ $id ] = true;
				$toc[]       = array(
					'id'    => $id,
					'label' => $label,
				);

				return '<' . $level . $attrs . '>' . $m[2] . '</' . $level . '>';
			},
			$html
		);

		return array(
			'html' => $html,
			'toc'  => $toc,
		);
	}
}
