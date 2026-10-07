<?php
/**
 * Link-preview (Open Graph / Twitter card) image for every page.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Social_Share
 *
 * When a link to the site is shared (WhatsApp, Facebook, X, LinkedIn…), the
 * preview card shows the site logo — the same logo the header, footer and
 * PDFs use — instead of whichever photo the SEO plugin would otherwise
 * pick (a site-wide default image, or a person's photo inherited from the
 * site's earlier single-doctor setup).
 *
 * With Yoast SEO active, its own tags are kept and only their image is
 * swapped (see filter_presentation()/filter_twitter_image()). Without an
 * SEO plugin, the minimal set of share tags is printed directly (see
 * render_fallback_tags()), so previews still show the logo and page title.
 */
class Social_Share {

	/**
	 * The logo as a share image — a raster file (link previews don't render
	 * SVG), with its real pixel size so previews reserve the right shape.
	 *
	 * @return array|null { @type string url, @type int width, @type int height, @type string type }, or null when there's no usable logo.
	 */
	public static function logo_image() {
		$url  = Site_Footer::bundled_logo_url();
		$path = Site_Footer::bundled_logo_path();

		// An uploaded SVG logo can't be a preview image — use the bundled PNG.
		if ( '' === $url || 0 === strcasecmp( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ), 'svg' ) ) {
			$png = DOCTOR_AK_PORTAL_PATH . 'assets/images/logo.png';

			if ( ! file_exists( $png ) ) {
				return null;
			}

			$url  = DOCTOR_AK_PORTAL_URL . 'assets/images/logo.png';
			$path = $png;
		}

		$size = ( '' !== $path && file_exists( $path ) ) ? @getimagesize( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an unreadable file just means no width/height tags.

		return array(
			'url'    => $url,
			'width'  => $size ? (int) $size[0] : 0,
			'height' => $size ? (int) $size[1] : 0,
			'type'   => $size && ! empty( $size['mime'] ) ? $size['mime'] : '',
		);
	}

	/**
	 * Yoast SEO: replaces the page's Open Graph image(s) with the logo.
	 * Hooked to `wpseo_frontend_presentation`.
	 *
	 * @param object $presentation Yoast's indexable presentation for this request.
	 * @return object
	 */
	public function filter_presentation( $presentation ) {
		$logo = self::logo_image();

		if ( ! $logo || ! is_object( $presentation ) ) {
			return $presentation;
		}

		$image = array( 'url' => $logo['url'] );

		if ( $logo['width'] > 0 && $logo['height'] > 0 ) {
			$image['width']  = $logo['width'];
			$image['height'] = $logo['height'];
		}

		if ( '' !== $logo['type'] ) {
			$image['type'] = $logo['type'];
		}

		$presentation->open_graph_images = array( $logo['url'] => $image );

		return $presentation;
	}

	/**
	 * Yoast SEO: the Twitter/X card image. Hooked to `wpseo_twitter_image`.
	 *
	 * @param string $image Image URL Yoast was about to use.
	 * @return string
	 */
	public function filter_twitter_image( $image ) {
		$logo = self::logo_image();

		return $logo ? $logo['url'] : $image;
	}

	/**
	 * Yoast SEO: a square logo reads best as the compact card, not the wide
	 * banner one. Hooked to `wpseo_twitter_card_type`.
	 *
	 * @param string $type Card type Yoast was about to use.
	 * @return string
	 */
	public function filter_twitter_card_type( $type ) {
		return self::logo_image() ? 'summary' : $type;
	}

	/**
	 * No SEO plugin: prints the share tags itself. Hooked to `wp_head`.
	 * Does nothing when Yoast SEO, Rank Math, All in One SEO or SEOPress is
	 * active — those print their own tags (Yoast's image is swapped above).
	 *
	 * @return void
	 */
	public function render_fallback_tags() {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
			return;
		}

		$logo = self::logo_image();

		if ( ! $logo ) {
			return;
		}

		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );

		printf( '<meta property="og:type" content="website">' . "\n" );
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $logo['url'] ) );

		if ( $logo['width'] > 0 && $logo['height'] > 0 ) {
			printf( '<meta property="og:image:width" content="%d">' . "\n", (int) $logo['width'] );
			printf( '<meta property="og:image:height" content="%d">' . "\n", (int) $logo['height'] );
		}

		printf( '<meta name="twitter:card" content="summary">' . "\n" );
		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $logo['url'] ) );
	}
}
