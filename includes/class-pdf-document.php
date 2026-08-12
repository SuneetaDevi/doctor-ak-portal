<?php
/**
 * Shared low-level PDF-writing primitives for every PDF this plugin
 * generates (invoice, prescription, encounter bill).
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

use DoctorAKPortal\Frontend\Site_Footer;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pdf_Document
 *
 * A minimal, dependency-free PDF writer base class — the plugin has no
 * Composer/vendor setup, and a general-purpose library (Dompdf, mPDF, etc.)
 * can't be installed without one, so every PDF this plugin produces writes
 * the PDF file format directly instead: a single A4 page, built-in
 * Helvetica/Helvetica-Bold fonts (no font embedding needed), simple ruled
 * lines for tables, and the clinic logo embedded as a JPEG XObject (via GD,
 * which ships with PHP) when one is configured and GD can read it. Each
 * concrete subclass (Invoice_Pdf, Prescription_Pdf, Encounter_Bill_Pdf)
 * only owns its own content-stream layout, via its own `build()` method.
 */
abstract class Pdf_Document {

	/**
	 * A4 page size in PDF points (1/72 inch).
	 */
	const PAGE_WIDTH  = 595.28;
	const PAGE_HEIGHT = 841.89;

	/**
	 * Loads the configured clinic logo and re-encodes it as JPEG via GD, so
	 * it can be embedded as a simple DCTDecode XObject regardless of its
	 * original format — skipped entirely (not a fatal error) if GD isn't
	 * available, the file can't be read, or it's an SVG (GD can't rasterize
	 * those); the document still renders fine without a logo.
	 *
	 * @return array|null { @type string jpeg, @type int width_px, @type int height_px, @type float width, @type float height } (width/height in PDF points, capped to a max display size), or null.
	 */
	protected static function load_logo_jpeg() {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagejpeg' ) ) {
			return null;
		}

		$path = Site_Footer::bundled_logo_path();

		if ( '' === $path || 0 === strcasecmp( pathinfo( $path, PATHINFO_EXTENSION ), 'svg' ) ) {
			return null;
		}

		$data = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset, not a remote/user-supplied URL.

		if ( false === $data ) {
			return null;
		}

		$image = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- malformed/unsupported image files should just skip the logo, not fatal.

		if ( false === $image ) {
			return null;
		}

		// Flatten transparency onto white — JPEG has no alpha channel.
		$width  = imagesx( $image );
		$height = imagesy( $image );
		$flat   = imagecreatetruecolor( $width, $height );
		imagefill( $flat, 0, 0, imagecolorallocate( $flat, 255, 255, 255 ) );
		imagecopy( $flat, $image, 0, 0, 0, 0, $width, $height );

		ob_start();
		imagejpeg( $flat, null, 85 );
		$jpeg = ob_get_clean();

		imagedestroy( $image );
		imagedestroy( $flat );

		if ( false === $jpeg || '' === $jpeg ) {
			return null;
		}

		// Cap the display size so a huge source logo doesn't dominate the page.
		$max_height = 56;
		$max_width  = 200;
		$ratio      = min( $max_width / $width, $max_height / $height, 1 );

		return array(
			'jpeg'      => $jpeg,
			'width_px'  => $width,
			'height_px' => $height,
			'width'     => $width * $ratio,
			'height'    => $height * $ratio,
		);
	}

	/**
	 * @param float  $x    Left edge, PDF points from the page's left.
	 * @param float  $y    Baseline, PDF points from the page's bottom.
	 * @param string $font 'F1' (Helvetica) or 'F2' (Helvetica-Bold).
	 * @param float  $size Font size in points.
	 * @param string $text Plain text (Latin-1 range only — see pdf_escape()).
	 * @param float  $gray 0 (black) to 1 (white). Default 0.
	 * @return string Content-stream fragment.
	 */
	protected static function draw_text( $x, $y, $font, $size, $text, $gray = 0 ) {
		return sprintf(
			"BT\n%s g\n/%s %s Tf\n%s %s Td\n(%s) Tj\nET\n",
			self::num( $gray ),
			$font,
			self::num( $size ),
			self::num( $x ),
			self::num( $y ),
			self::pdf_escape( $text )
		);
	}

	/**
	 * Right-aligned text — PDF has no native alignment, so this estimates
	 * the string's rendered width from Helvetica's average glyph width
	 * (close enough for a receipt/table; not pixel-perfect kerning).
	 */
	protected static function draw_text_right( $right_x, $y, $font, $size, $text, $gray = 0 ) {
		$bold             = 'F2' === $font;
		$avg_char_width   = $size * ( $bold ? 0.60 : 0.52 );
		$estimated_width  = self::mb_strlen_safe( $text ) * $avg_char_width;

		return self::draw_text( $right_x - $estimated_width, $y, $font, $size, $text, $gray );
	}

	/**
	 * Right-aligned text with a horizontal strike-through line — used for a
	 * pre-discount price. PDF has no native strikethrough, so this draws a
	 * thin line over the middle of the estimated text width (see
	 * draw_text_right()).
	 */
	protected static function draw_text_right_struck( $right_x, $y, $font, $size, $text, $gray = 0 ) {
		$bold            = 'F2' === $font;
		$avg_char_width  = $size * ( $bold ? 0.60 : 0.52 );
		$estimated_width = self::mb_strlen_safe( $text ) * $avg_char_width;
		$x               = $right_x - $estimated_width;

		return self::draw_text( $x, $y, $font, $size, $text, $gray )
			. self::draw_line( $x - 1, $y + $size * 0.32, $right_x + 1, $y + $size * 0.32, $gray, $gray, $gray, 0.6 );
	}

	/**
	 * @return string Content-stream fragment for a straight line.
	 */
	protected static function draw_line( $x1, $y1, $x2, $y2, $r = 0, $g = 0, $b = 0, $width = 0.5 ) {
		return sprintf(
			"%s %s %s RG\n%s w\n%s %s m\n%s %s l\nS\n",
			self::num( $r ),
			self::num( $g ),
			self::num( $b ),
			self::num( $width ),
			self::num( $x1 ),
			self::num( $y1 ),
			self::num( $x2 ),
			self::num( $y2 )
		);
	}

	/**
	 * @return string Content-stream fragment placing the (already embedded)
	 *                'Im1' image XObject at the given position/size.
	 */
	protected static function draw_image( $name, $x, $y, $width, $height ) {
		return sprintf(
			"q\n%s 0 0 %s %s %s cm\n/%s Do\nQ\n",
			self::num( $width ),
			self::num( $height ),
			self::num( $x ),
			self::num( $y ),
			$name
		);
	}

	/**
	 * Draws one row of a simple left/right-aligned table — used by any
	 * document with a multi-row table (unlike an invoice's single line
	 * item, a prescription/bill has several).
	 *
	 * @param array  $columns List of `array( 'x' => float, 'text' => string, 'align' => 'left'|'right' (default 'left') )`.
	 * @param float  $y       Baseline, PDF points from the page's bottom.
	 * @param string $font    'F1' or 'F2'. Default 'F1'.
	 * @param float  $size    Font size. Default 9.
	 * @param float  $gray    0 (black) to 1 (white). Default 0.
	 * @return string Content-stream fragment.
	 */
	protected static function draw_table_row( array $columns, $y, $font = 'F1', $size = 9, $gray = 0 ) {
		$stream = '';

		foreach ( $columns as $column ) {
			$align = isset( $column['align'] ) ? $column['align'] : 'left';

			if ( 'right' === $align ) {
				$stream .= self::draw_text_right( $column['x'], $y, $font, $size, $column['text'], $gray );
			} else {
				$stream .= self::draw_text( $column['x'], $y, $font, $size, $column['text'], $gray );
			}
		}

		return $stream;
	}

	/**
	 * Formats a number for PDF syntax — no scientific notation, no
	 * unnecessary trailing zeros.
	 *
	 * @param float $n Number.
	 * @return string
	 */
	protected static function num( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 3, '.', '' ), '0' ), '.' );
	}

	/**
	 * Escapes a plain string for a PDF literal string `(...)`, and
	 * transliterates to Latin-1 since the base-14 fonts used here have no
	 * Unicode support without embedding a real font file.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	protected static function pdf_escape( $text ) {
		$text   = wp_strip_all_tags( (string) $text );
		$text   = remove_accents( $text );
		$latin1 = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- falls back to the original string if iconv is unavailable.

		if ( false !== $latin1 ) {
			$text = $latin1;
		}

		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
	}

	/**
	 * @param string $text Text as it will actually be drawn (post pdf_escape()).
	 * @return int
	 */
	protected static function mb_strlen_safe( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( self::pdf_escape( $text ), 'ISO-8859-1' ) : strlen( self::pdf_escape( $text ) );
	}

	/**
	 * Assembles a complete single-page PDF file: catalog, pages, page,
	 * content stream, the two base-14 fonts, and — if one was loaded — the
	 * logo image XObject, followed by a valid cross-reference table and
	 * trailer.
	 *
	 * @param string     $content_stream Page content-stream commands.
	 * @param array|null $logo           See load_logo_jpeg(), or null.
	 * @return string Raw PDF bytes.
	 */
	protected static function assemble_single_page( $content_stream, $logo ) {
		$objects = array();

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';

		$resources = '/Font << /F1 5 0 R /F2 6 0 R >>';

		if ( $logo ) {
			$resources .= ' /XObject << /Im1 7 0 R >>';
		}

		$objects[3] = sprintf(
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << %s >> /Contents 4 0 R >>',
			self::num( self::PAGE_WIDTH ),
			self::num( self::PAGE_HEIGHT ),
			$resources
		);

		$objects[4] = array(
			'stream' => $content_stream,
		);

		$objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

		if ( $logo ) {
			$objects[7] = array(
				'dict_extra' => sprintf(
					'/Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode',
					$logo['width_px'],
					$logo['height_px']
				),
				'stream'     => $logo['jpeg'],
				'binary'     => true,
			);
		}

		return self::write_pdf( $objects );
	}

	/**
	 * Serializes a flat object map (1-indexed, no gaps) into a complete PDF
	 * file with a working cross-reference table.
	 *
	 * @param array $objects Object number => either a plain dict string, or `array( 'stream' => ..., 'dict_extra' => optional, 'binary' => optional )`.
	 * @return string
	 */
	protected static function write_pdf( array $objects ) {
		$pdf     = "%PDF-1.4\n";
		$offsets = array();
		$count   = count( $objects );

		for ( $i = 1; $i <= $count; $i++ ) {
			$offsets[ $i ] = strlen( $pdf );

			$obj = $objects[ $i ];

			if ( is_array( $obj ) ) {
				$dict_extra = isset( $obj['dict_extra'] ) ? $obj['dict_extra'] . ' ' : '';
				$stream     = $obj['stream'];
				$pdf       .= sprintf( "%d 0 obj\n<< %s/Length %d >>\nstream\n", $i, $dict_extra, strlen( $stream ) );
				$pdf       .= $stream;
				$pdf       .= "\nendstream\nendobj\n";
			} else {
				$pdf .= sprintf( "%d 0 obj\n%s\nendobj\n", $i, $obj );
			}
		}

		$xref_offset = strlen( $pdf );
		$pdf        .= sprintf( "xref\n0 %d\n", $count + 1 );
		$pdf        .= "0000000000 65535 f \n";

		for ( $i = 1; $i <= $count; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}

		$pdf .= sprintf( "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF", $count + 1, $xref_offset );

		return $pdf;
	}
}
