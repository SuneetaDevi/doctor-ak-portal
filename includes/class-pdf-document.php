<?php
/**
 * Shared low-level PDF-writing primitives for every PDF this plugin
 * generates (receipt, appointment slip, prescription, bill, statement).
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
 * the PDF file format directly instead: A4 pages, the built-in
 * Helvetica/Helvetica-Bold fonts (no font embedding needed, text stays
 * selectable), ruled lines, and the logo embedded as a JPEG XObject (via
 * GD, which ships with PHP) when GD can read it.
 *
 * Text is written in WinAnsi (Windows-1252) — the encoding those built-in
 * fonts use — so dashes, bullets, curly quotes and accented Latin letters
 * print as typed. Widths come from the fonts' published metrics (see
 * text_width()), so right-aligned figures and wrapped lines land where
 * they're measured to. The page layout itself lives in Pdf_Builder.
 */
abstract class Pdf_Document {

	/**
	 * A4 page size in PDF points (1/72 inch).
	 */
	const PAGE_WIDTH  = 595.28;
	const PAGE_HEIGHT = 841.89;

	/**
	 * Helvetica advance widths (1/1000 em) for WinAnsi codes 32–126, from
	 * the standard Adobe font metrics.
	 *
	 * @var int[]
	 */
	private static $helvetica_widths = array(
		278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
		556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
		1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
		667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
		333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
		556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
	);

	/**
	 * Helvetica-Bold advance widths (1/1000 em) for WinAnsi codes 32–126.
	 *
	 * @var int[]
	 */
	private static $helvetica_bold_widths = array(
		278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
		556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
		975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
		667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
		333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
		611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
	);

	/**
	 * Widths for the WinAnsi codes above 126 that documents commonly
	 * contain (same for both weights unless noted by a pair).
	 *
	 * @var array code => width, or code => array( regular, bold ).
	 */
	private static $extended_widths = array(
		128 => 556,                 // Euro.
		130 => array( 222, 278 ),   // Single low quote.
		133 => 1000,                // Ellipsis.
		145 => array( 222, 278 ),   // Left single quote.
		146 => array( 222, 278 ),   // Right single quote / apostrophe.
		147 => array( 333, 500 ),   // Left double quote.
		148 => array( 333, 500 ),   // Right double quote.
		149 => 350,                 // Bullet.
		150 => 556,                 // En dash.
		151 => 1000,                // Em dash.
		153 => 1000,                // Trademark.
		160 => 278,                 // No-break space.
		176 => 400,                 // Degree.
		177 => 584,                 // Plus-minus.
		181 => array( 556, 611 ),   // Micro.
		183 => 278,                 // Middle dot.
		215 => 584,                 // Multiplication.
		247 => 584,                 // Division.
	);

	/**
	 * Loads the configured logo and re-encodes it as JPEG via GD, so it can
	 * be embedded as a simple DCTDecode XObject regardless of its original
	 * format — skipped entirely (not a fatal error) if GD isn't available,
	 * the file can't be read, or it's an SVG (GD can't rasterize those);
	 * the document still renders fine without a logo.
	 *
	 * @param float $max_height Display height cap, PDF points.
	 * @param float $max_width  Display width cap, PDF points.
	 * @return array|null { @type string jpeg, @type int width_px, @type int height_px, @type float width, @type float height } (width/height in PDF points, aspect ratio preserved), or null.
	 */
	protected static function load_logo_jpeg( $max_height = 56, $max_width = 200 ) {
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
		imagejpeg( $flat, null, 90 );
		$jpeg = ob_get_clean();

		imagedestroy( $image );
		imagedestroy( $flat );

		if ( false === $jpeg || '' === $jpeg ) {
			return null;
		}

		// One scale factor for both sides keeps the logo's own aspect ratio.
		$ratio = min( $max_width / $width, $max_height / $height, 1 );

		return array(
			'jpeg'      => $jpeg,
			'width_px'  => $width,
			'height_px' => $height,
			'width'     => $width * $ratio,
			'height'    => $height * $ratio,
		);
	}

	/**
	 * Encodes text for the built-in fonts: tags stripped, UTF-8 converted to
	 * Windows-1252 (characters outside it are transliterated where iconv can,
	 * dropped otherwise), control characters removed.
	 *
	 * @param string $text Raw text.
	 * @return string Windows-1252 bytes, not yet PDF-escaped.
	 */
	protected static function encode_text( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = str_replace( array( "\r", "\n", "\t" ), ' ', $text );

		if ( function_exists( 'iconv' ) ) {
			$encoded = @iconv( 'UTF-8', 'CP1252//TRANSLIT//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- falls back below if iconv refuses the string.

			if ( false !== $encoded ) {
				return $encoded;
			}
		}

		// No iconv: keep plain ASCII only rather than emit invalid bytes.
		return preg_replace( '/[^\x20-\x7E]/', '?', remove_accents( $text ) );
	}

	/**
	 * Escapes a plain string for a PDF literal string `(...)`.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	protected static function pdf_escape( $text ) {
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), self::encode_text( $text ) );
	}

	/**
	 * Rendered width of a string, PDF points.
	 *
	 * @param string $text     Raw text.
	 * @param string $font     'F1' (Helvetica) or 'F2' (Helvetica-Bold).
	 * @param float  $size     Font size.
	 * @param float  $tracking Extra space after every character (PDF Tc), points.
	 * @return float
	 */
	protected static function text_width( $text, $font, $size, $tracking = 0.0 ) {
		$bytes  = self::encode_text( $text );
		$bold   = 'F2' === $font;
		$widths = $bold ? self::$helvetica_bold_widths : self::$helvetica_widths;
		$units  = 0;
		$length = strlen( $bytes );

		for ( $i = 0; $i < $length; $i++ ) {
			$code = ord( $bytes[ $i ] );

			if ( $code >= 32 && $code <= 126 ) {
				$units += $widths[ $code - 32 ];
			} elseif ( isset( self::$extended_widths[ $code ] ) ) {
				$width  = self::$extended_widths[ $code ];
				$units += is_array( $width ) ? $width[ $bold ? 1 : 0 ] : $width;
			} elseif ( $code >= 192 && $code < 224 ) {
				$units += $bold ? 722 : 667; // Accented capitals.
			} else {
				$units += 556; // Accented lowercase and other Latin-1 letters.
			}
		}

		return $units * $size / 1000 + $tracking * $length;
	}

	/**
	 * Splits text into lines that each fit `$max_width` — never truncates.
	 * Hard line breaks in the source are kept; a single word longer than
	 * the line is broken across lines rather than overflowing.
	 *
	 * @param string $text      Text to wrap.
	 * @param string $font      'F1' or 'F2'.
	 * @param float  $size      Font size.
	 * @param float  $max_width Available width, PDF points.
	 * @return string[] One or more lines ('' for a blank source line).
	 */
	protected static function wrap_text( $text, $font, $size, $max_width ) {
		$lines = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $paragraph ) {
			$paragraph = trim( preg_replace( '/[ \t]+/', ' ', $paragraph ) );

			if ( '' === $paragraph ) {
				$lines[] = '';
				continue;
			}

			$line = '';

			foreach ( explode( ' ', $paragraph ) as $word ) {
				$candidate = '' === $line ? $word : $line . ' ' . $word;

				if ( self::text_width( $candidate, $font, $size ) <= $max_width ) {
					$line = $candidate;
					continue;
				}

				if ( '' !== $line ) {
					$lines[] = $line;
				}

				// A word wider than the whole line: break it by characters.
				while ( self::text_width( $word, $font, $size ) > $max_width && self::mb_len( $word ) > 1 ) {
					$cut = self::mb_len( $word ) - 1;

					while ( $cut > 1 && self::text_width( self::mb_sub( $word, 0, $cut ), $font, $size ) > $max_width ) {
						--$cut;
					}

					$lines[] = self::mb_sub( $word, 0, $cut );
					$word    = self::mb_sub( $word, $cut );
				}

				$line = $word;
			}

			$lines[] = $line;
		}

		// Drop blank lines at the very start/end; keep intentional inner ones.
		while ( ! empty( $lines ) && '' === $lines[0] ) {
			array_shift( $lines );
		}

		while ( ! empty( $lines ) && '' === end( $lines ) ) {
			array_pop( $lines );
		}

		return empty( $lines ) ? array( '' ) : $lines;
	}

	/**
	 * @param string $text UTF-8 text.
	 * @return int
	 */
	private static function mb_len( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * @param string   $text   UTF-8 text.
	 * @param int      $start  Start character.
	 * @param int|null $length Length, or null for the rest.
	 * @return string
	 */
	private static function mb_sub( $text, $start, $length = null ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, $start, $length, 'UTF-8' ) : substr( $text, $start, null === $length ? strlen( $text ) : $length );
	}

	/**
	 * Content-stream fragment drawing one line of text.
	 *
	 * @param float        $x        Left edge, points from the page's left.
	 * @param float        $y        Baseline, points from the page's bottom.
	 * @param string       $font     'F1' (Helvetica) or 'F2' (Helvetica-Bold).
	 * @param float        $size     Font size in points.
	 * @param string       $text     Plain text.
	 * @param float|array  $color    Gray level 0 (black)–1 (white), or array( r, g, b ) 0–1.
	 * @param float        $tracking Character spacing, points.
	 * @return string
	 */
	protected static function draw_text( $x, $y, $font, $size, $text, $color = 0, $tracking = 0.0 ) {
		if ( '' === (string) $text ) {
			return '';
		}

		return sprintf(
			"BT\n%s\n/%s %s Tf\n%s Tc\n%s %s Td\n(%s) Tj\nET\n",
			self::fill_color( $color ),
			$font,
			self::num( $size ),
			self::num( $tracking ),
			self::num( $x ),
			self::num( $y ),
			self::pdf_escape( $text )
		);
	}

	/**
	 * Right-aligned text ending at `$right_x`.
	 *
	 * @return string
	 */
	protected static function draw_text_right( $right_x, $y, $font, $size, $text, $color = 0, $tracking = 0.0 ) {
		return self::draw_text( $right_x - self::text_width( $text, $font, $size, $tracking ) + $tracking, $y, $font, $size, $text, $color, $tracking );
	}

	/**
	 * Content-stream fragment for a straight line.
	 *
	 * @param float       $x1    Start x.
	 * @param float       $y1    Start y.
	 * @param float       $x2    End x.
	 * @param float       $y2    End y.
	 * @param float|array $color Gray level or array( r, g, b ).
	 * @param float       $width Line width, points.
	 * @return string
	 */
	protected static function draw_line( $x1, $y1, $x2, $y2, $color = 0, $width = 0.5 ) {
		return sprintf(
			"%s\n%s w\n%s %s m\n%s %s l\nS\n",
			self::stroke_color( $color ),
			self::num( $width ),
			self::num( $x1 ),
			self::num( $y1 ),
			self::num( $x2 ),
			self::num( $y2 )
		);
	}

	/**
	 * @return string Content-stream fragment placing an embedded image XObject.
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
	 * @param float|array $color Gray level or array( r, g, b ).
	 * @return string Fill-colour operator.
	 */
	private static function fill_color( $color ) {
		return is_array( $color )
			? sprintf( '%s %s %s rg', self::num( $color[0] ), self::num( $color[1] ), self::num( $color[2] ) )
			: sprintf( '%s g', self::num( $color ) );
	}

	/**
	 * @param float|array $color Gray level or array( r, g, b ).
	 * @return string Stroke-colour operator.
	 */
	private static function stroke_color( $color ) {
		return is_array( $color )
			? sprintf( '%s %s %s RG', self::num( $color[0] ), self::num( $color[1] ), self::num( $color[2] ) )
			: sprintf( '%s G', self::num( $color ) );
	}

	/**
	 * Formats a number for PDF syntax — no scientific notation, no
	 * unnecessary trailing zeros.
	 *
	 * @param float $n Number.
	 * @return string
	 */
	protected static function num( $n ) {
		$formatted = rtrim( rtrim( number_format( (float) $n, 3, '.', '' ), '0' ), '.' );

		return '-0' === $formatted ? '0' : $formatted;
	}

	/**
	 * Assembles a complete PDF file of one or more A4 pages: catalog, page
	 * tree, the two base-14 fonts, the optional logo XObject, each page and
	 * its content stream, a document-information dictionary, and a valid
	 * cross-reference table and trailer.
	 *
	 * @param string[]   $page_streams One content stream per page, in order.
	 * @param array|null $logo         See load_logo_jpeg(), or null.
	 * @param string     $title        Document title for the PDF's metadata.
	 * @return string Raw PDF bytes.
	 */
	protected static function assemble_pages( array $page_streams, $logo, $title = '' ) {
		$objects = array();

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[5] = sprintf(
			'<< /Title (%s) /Producer (%s) /CreationDate (D:%s) >>',
			self::pdf_escape( $title ),
			self::pdf_escape( 'Doctor AK Portal' ),
			gmdate( 'YmdHis' ) . 'Z'
		);

		$next      = 6;
		$resources = '/Font << /F1 3 0 R /F2 4 0 R >>';

		if ( $logo ) {
			$objects[ $next ] = array(
				'dict_extra' => sprintf(
					'/Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode',
					$logo['width_px'],
					$logo['height_px']
				),
				'stream'     => $logo['jpeg'],
			);
			$resources .= sprintf( ' /XObject << /Im1 %d 0 R >>', $next );
			++$next;
		}

		$kids = array();

		foreach ( $page_streams as $stream ) {
			$page_number = $next;
			$kids[]      = $page_number . ' 0 R';

			$objects[ $page_number ] = sprintf(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << %s >> /Contents %d 0 R >>',
				self::num( self::PAGE_WIDTH ),
				self::num( self::PAGE_HEIGHT ),
				$resources,
				$page_number + 1
			);
			$objects[ $page_number + 1 ] = array( 'stream' => $stream );

			$next += 2;
		}

		$objects[2] = sprintf( '<< /Type /Pages /Kids [%s] /Count %d >>', implode( ' ', $kids ), count( $kids ) );

		ksort( $objects );

		return self::write_pdf( $objects, 5 );
	}

	/**
	 * Serializes a flat object map (1-indexed, no gaps) into a complete PDF
	 * file with a working cross-reference table.
	 *
	 * @param array $objects     Object number => either a plain dict string, or `array( 'stream' => ..., 'dict_extra' => optional )`.
	 * @param int   $info_object Object number of the document-information dictionary, or 0.
	 * @return string
	 */
	protected static function write_pdf( array $objects, $info_object = 0 ) {
		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
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

		$info = $info_object > 0 ? sprintf( ' /Info %d 0 R', $info_object ) : '';
		$pdf .= sprintf( "trailer\n<< /Size %d /Root 1 0 R%s >>\nstartxref\n%d\n%%%%EOF", $count + 1, $info, $xref_offset );

		return $pdf;
	}
}
