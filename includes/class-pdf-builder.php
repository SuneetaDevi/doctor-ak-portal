<?php
/**
 * Shared page layout and medical-stationery design for every PDF this
 * plugin generates.
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
 * Class Pdf_Builder
 *
 * A flowing, multi-page A4 layout on top of Pdf_Document's primitives:
 * documents add blocks top to bottom (header, people, sections, tables,
 * totals) and the builder moves to a new page whenever the next block
 * wouldn't fit above the footer — repeating a table's column headings on
 * the new page, never leaving a section heading alone at a page bottom,
 * and numbering every page ("Page 2 of 3") once the total is known.
 *
 * Design: white page, dark text, the brand green only for the document
 * title and section headings, thin horizontal rules instead of boxes or
 * shaded panels — so every document stays legible in black-and-white.
 * Platform branding (logo, site name, contact) sits in the header and
 * footer; the clinic that actually provided care is shown separately, from
 * that visit's own clinic record, and only when that record exists.
 */
class Pdf_Builder extends Pdf_Document {

	/**
	 * Page geometry, PDF points.
	 */
	const MARGIN_X       = 50;
	const TOP_Y          = 798;
	const CONTENT_BOTTOM = 72;
	const FOOTER_RULE_Y  = 54;

	/**
	 * Colours: gray levels (0 = black) and the brand green as RGB.
	 */
	const TEXT  = 0.1;
	const MUTED = 0.36;
	const LIGHT = 0.8;
	const DARK  = 0.25;

	/**
	 * Body type: 10 pt on 13.5 pt lines; secondary text 8.5 pt.
	 */
	const BODY_SIZE  = 10;
	const BODY_LEAD  = 13.5;
	const SMALL_SIZE = 8.5;
	const SMALL_LEAD = 11.5;
	const LABEL_SIZE = 7.5;

	/**
	 * Brand green (#16634b) as PDF RGB.
	 *
	 * @var float[]
	 */
	private static $brand = array( 0.086, 0.388, 0.294 );

	/**
	 * Left/right content edges and width.
	 *
	 * @var float
	 */
	public $left;
	public $right;
	public $width;

	/**
	 * Current baseline cursor (moves down the page).
	 *
	 * @var float
	 */
	public $y;

	/**
	 * Finished page content streams, and the one being written.
	 *
	 * @var string[]
	 */
	private $pages  = array();
	private $stream = '';

	/**
	 * @var array|null See Pdf_Document::load_logo_jpeg().
	 */
	private $logo;

	/**
	 * @var string Document title, e.g. "Prescription".
	 */
	private $title;

	/**
	 * @var string Main reference, e.g. "RX-0012", for continuation headers.
	 */
	private $reference;

	/**
	 * @var array|null The table currently being drawn, so a page break can
	 *                 repeat its column headings.
	 */
	private $active_table = null;

	/**
	 * @var float|null Cursor position right after the latest section
	 *                 heading, so a table starting there can use the
	 *                 heading's rule as its own top rule.
	 */
	private $section_end_y = null;

	/**
	 * @param string $title     Document title.
	 * @param string $reference Main document reference.
	 */
	public function __construct( $title, $reference ) {
		$this->title     = $title;
		$this->reference = $reference;
		$this->left      = self::MARGIN_X;
		$this->right     = self::PAGE_WIDTH - self::MARGIN_X;
		$this->width     = $this->right - $this->left;
		$this->y         = self::TOP_Y;
		$this->logo      = self::load_logo_jpeg( 50, 90 );
	}

	/* ------------------------------------------------------------------ */
	/* Shared data — platform brand, people and the place of care          */
	/* ------------------------------------------------------------------ */

	/**
	 * The platform's own branding: name, web address and contact phone —
	 * the same settings the site footer and "Clinic Branding" use.
	 *
	 * @return array { @type string name, @type string domain, @type string phone }
	 */
	public static function brand() {
		$name = trim( (string) get_option( Site_Footer::OPTION_CLINIC_NAME, '' ) );

		if ( '' === $name ) {
			$name = trim( (string) get_option( Site_Footer::OPTION_COPYRIGHT_NAME, '' ) );
		}

		if ( '' === $name ) {
			$name = get_bloginfo( 'name' );
		}

		$phone = trim( (string) get_option( Site_Footer::OPTION_CLINIC_PHONE, '' ) );

		if ( '' === $phone ) {
			$phone = trim( (string) get_option( Site_Footer::OPTION_PHONE, '0303-3638304' ) );
		}

		return array(
			'name'   => $name,
			'domain' => Site_Footer::BRAND_DOMAIN,
			'phone'  => $phone,
		);
	}

	/**
	 * Lines describing the patient, for a people column.
	 *
	 * @param string $name       Display name.
	 * @param int    $patient_id Registered patient's user ID, or 0 for a guest booking.
	 * @param string $age        Age in years, or ''.
	 * @param string $phone      Phone, or ''.
	 * @return array Lines for people().
	 */
	public static function patient_lines( $name, $patient_id, $age = '', $phone = '' ) {
		$lines = array( array( 'text' => '' !== trim( (string) $name ) ? $name : __( 'Patient', 'doctor-ak-portal' ), 'style' => 'strong' ) );
		$meta  = array( $patient_id > 0 ? sprintf( 'P-%03d', $patient_id ) : __( 'Guest booking', 'doctor-ak-portal' ) );

		if ( '' !== (string) $age ) {
			/* translators: %s: age in years. */
			$meta[] = sprintf( __( '%s years', 'doctor-ak-portal' ), $age );
		}

		$lines[] = array( 'text' => implode( '  ·  ', $meta ), 'style' => 'muted' );

		if ( '' !== trim( (string) $phone ) ) {
			$lines[] = array( 'text' => $phone, 'style' => 'muted' );
		}

		return $lines;
	}

	/**
	 * Lines describing the doctor: name, then their saved qualification and
	 * specialities — each line only when the doctor's profile has it.
	 *
	 * @param int    $doctor_id   Doctor's user ID.
	 * @param string $doctor_name Display name (no "Dr." prefix).
	 * @return array Lines for people().
	 */
	public static function doctor_lines( $doctor_id, $doctor_name ) {
		/* translators: %s: doctor's name. */
		$lines = array( array( 'text' => sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $doctor_name ), 'style' => 'strong' ) );

		if ( $doctor_id > 0 ) {
			$qualification = trim( (string) get_user_meta( $doctor_id, 'doctor_ak_qualification', true ) );

			if ( '' !== $qualification ) {
				$lines[] = array( 'text' => $qualification, 'style' => 'body' );
			}

			$labels = array();
			$all    = Specializations::get_all();

			foreach ( (array) get_user_meta( $doctor_id, 'doctor_ak_specializations', true ) as $slug ) {
				if ( isset( $all[ $slug ] ) ) {
					$labels[] = $all[ $slug ];
				}
			}

			if ( ! empty( $labels ) ) {
				$lines[] = array( 'text' => implode( ', ', $labels ), 'style' => 'muted' );
			}
		}

		return $lines;
	}

	/**
	 * Lines describing where care was provided — the visit's own clinic
	 * (name, address, and its phone/email when recorded), or the online
	 * video consultation. Never falls back to another clinic's details.
	 *
	 * @param string $type           Appointment type (Appointments::TYPE_VIDEO or clinic).
	 * @param int    $clinic_id      The visit's clinic ID, or 0.
	 * @param string $clinic_name    Already-resolved clinic name, or ''.
	 * @param string $clinic_address Already-resolved clinic address, or ''.
	 * @return array Lines for people().
	 */
	public static function place_lines( $type, $clinic_id, $clinic_name = '', $clinic_address = '' ) {
		if ( Appointments::TYPE_VIDEO === $type ) {
			return array(
				array( 'text' => __( 'Online video consultation', 'doctor-ak-portal' ), 'style' => 'strong' ),
				/* translators: %s: platform web address. */
				array( 'text' => sprintf( __( 'Held online via %s', 'doctor-ak-portal' ), Site_Footer::BRAND_DOMAIN ), 'style' => 'muted' ),
			);
		}

		$clinic = $clinic_id > 0 ? Clinics::find( $clinic_id ) : null;

		if ( $clinic ) {
			$clinic_name    = $clinic['name'];
			$clinic_address = implode( ', ', array_filter( array( $clinic['address'], $clinic['area_label'], $clinic['city_label'] ) ) );
		}

		if ( '' === trim( (string) $clinic_name ) ) {
			return array( array( 'text' => __( 'Clinic not recorded', 'doctor-ak-portal' ), 'style' => 'muted' ) );
		}

		$lines = array( array( 'text' => $clinic_name, 'style' => 'strong' ) );

		// Some clinics' saved street address starts with the clinic's own
		// name — drop that repeat so the name isn't printed twice.
		$clinic_address = (string) $clinic_address;

		if ( '' !== $clinic_address && 0 === stripos( $clinic_address, $clinic_name ) ) {
			$clinic_address = (string) preg_replace( '/^[\s,.;:\x{2013}\x{2014}-]+/u', '', substr( $clinic_address, strlen( $clinic_name ) ) );
		}

		if ( '' !== trim( $clinic_address ) ) {
			$lines[] = array( 'text' => $clinic_address, 'style' => 'body' );
		}

		if ( $clinic ) {
			// Phone and email on their own lines, so neither wraps mid-way.
			foreach ( array( $clinic['phone'], $clinic['contact_email'] ) as $contact ) {
				if ( '' !== trim( (string) $contact ) ) {
					$lines[] = array( 'text' => trim( (string) $contact ), 'style' => 'muted' );
				}
			}
		}

		return $lines;
	}

	/* ------------------------------------------------------------------ */
	/* Blocks                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * First-page header: logo and platform name/contact on the left, the
	 * document title and its reference/date rows on the right, then a
	 * brand-coloured rule.
	 *
	 * @param array $meta List of array( label, value ) rows shown under the title.
	 * @return void
	 */
	public function header( array $meta ) {
		$brand = self::brand();
		$top   = self::TOP_Y;

		// Right block: title, then label/value rows, all right-aligned.
		$this->stream .= self::draw_text_right( $this->right, $top - 14, 'F2', 18, $this->title, self::$brand );

		$meta_y      = $top - 31;
		$right_width = self::text_width( $this->title, 'F2', 18 );

		foreach ( $meta as $row ) {
			$value_width = self::text_width( $row[1], 'F2', 9 );
			$label       = $row[0];

			$this->stream .= self::draw_text_right( $this->right, $meta_y, 'F2', 9, $row[1], self::TEXT );
			$this->stream .= self::draw_text_right( $this->right - $value_width - 6, $meta_y, 'F1', 8.5, $label, self::MUTED );

			$right_width = max( $right_width, $value_width + 6 + self::text_width( $label, 'F1', 8.5 ) );
			$meta_y     -= 12.5;
		}

		// Left block: logo, then the platform's name and contact.
		$text_x = $this->left;
		$bottom = $meta_y + 12.5;

		if ( $this->logo ) {
			$this->stream .= self::draw_image( 'Im1', $this->left, $top - $this->logo['height'], $this->logo['width'], $this->logo['height'] );
			$text_x        = $this->left + $this->logo['width'] + 10;
			$bottom        = min( $bottom, $top - $this->logo['height'] );
		}

		$brand_width = max( 80, $this->right - $right_width - 24 - $text_x );
		$line_y      = $top - 14;

		foreach ( self::wrap_text( $brand['name'], 'F2', 12.5, $brand_width ) as $line ) {
			$this->stream .= self::draw_text( $text_x, $line_y, 'F2', 12.5, $line, self::TEXT );
			$line_y       -= 15;
		}

		$contact = implode( '  ·  ', array_filter( array( $brand['domain'], $brand['phone'] ) ) );

		foreach ( self::wrap_text( $contact, 'F1', 8.5, $brand_width ) as $line ) {
			$this->stream .= self::draw_text( $text_x, $line_y + 2, 'F1', 8.5, $line, self::MUTED );
			$line_y       -= 11.5;
		}

		$bottom = min( $bottom, $line_y + 2 );

		$rule_y        = $bottom - 10;
		$this->stream .= self::draw_line( $this->left, $rule_y, $this->right, $rule_y, self::$brand, 1.1 );
		$this->y       = $rule_y - 22;
	}

	/**
	 * Side-by-side labelled columns — patient / doctor / place of care.
	 *
	 * @param array $columns List of array( 'label' => string, 'lines' => array of array( 'text', 'style' => 'strong'|'body'|'muted'|'large' ) ).
	 * @return void
	 */
	public function people( array $columns ) {
		$columns = array_values( array_filter( $columns ) );
		$count   = count( $columns );

		if ( 0 === $count ) {
			return;
		}

		$gap       = 20;
		$col_width = ( $this->width - $gap * ( $count - 1 ) ) / $count;
		$prepared  = array();
		$height    = 0;

		foreach ( $columns as $column ) {
			$rows = array();
			$h    = 13;

			foreach ( $column['lines'] as $line ) {
				list( $font, $size, $lead, $color ) = self::line_style( isset( $line['style'] ) ? $line['style'] : 'body' );

				foreach ( self::wrap_text( $line['text'], $font, $size, $col_width ) as $wrapped ) {
					$rows[] = array( $wrapped, $font, $size, $lead, $color );
					$h     += $lead;
				}
			}

			$prepared[] = array( $column['label'], $rows );
			$height     = max( $height, $h );
		}

		$this->ensure( $height + 6 );

		foreach ( $prepared as $index => $column ) {
			$x       = $this->left + $index * ( $col_width + $gap );
			$line_y  = $this->y;
			$this->stream .= $this->label_text( $x, $line_y, $column[0] );
			$line_y -= 15;

			foreach ( $column[1] as $row ) {
				$this->stream .= self::draw_text( $x, $line_y, $row[1], $row[2], $row[0], $row[4] );
				$line_y       -= $row[3];
			}
		}

		$this->y -= $height + 14;
	}

	/**
	 * A section heading in the brand colour with a thin rule under it.
	 * Always keeps at least `$keep_with` points of the section's content on
	 * the same page, so a heading never ends a page on its own.
	 *
	 * @param string $title     Heading text.
	 * @param float  $keep_with Space the first part of the section needs.
	 * @return void
	 */
	public function section( $title, $keep_with = 40 ) {
		// Breathing room above a heading that follows other content.
		if ( $this->y < self::TOP_Y - 60 ) {
			$this->y -= 8;
		}

		$this->ensure( 24 + $keep_with );

		$this->stream .= self::draw_text( $this->left, $this->y, 'F2', 10.5, $title, self::$brand );
		$this->stream .= self::draw_line( $this->left, $this->y - 6, $this->right, $this->y - 6, self::LIGHT, 0.5 );
		$this->y      -= 22;
		$this->section_end_y = $this->y;
	}

	/**
	 * Label/value rows — label in a fixed muted column, value wrapping in
	 * the rest of the width. Empty values are skipped (never "null").
	 *
	 * @param array $pairs       List of array( label, value ) or array( label, value, 'strong' ).
	 * @param float $label_width Label column width.
	 * @param float $x           Left edge (defaults to the content edge).
	 * @param float $width       Total width (defaults to the content width).
	 * @return void
	 */
	public function details( array $pairs, $label_width = 130, $x = null, $width = null ) {
		$x     = null === $x ? $this->left : $x;
		$width = null === $width ? $this->width : $width;

		foreach ( $pairs as $pair ) {
			if ( '' === trim( (string) $pair[1] ) ) {
				continue;
			}

			$font  = ( isset( $pair[2] ) && 'strong' === $pair[2] ) ? 'F2' : 'F1';
			$lines = self::wrap_text( $pair[1], $font, 9.5, $width - $label_width );

			$this->ensure( count( $lines ) * 13 );

			$this->stream .= self::draw_text( $x, $this->y, 'F1', 9, $pair[0], self::MUTED );

			foreach ( $lines as $line ) {
				$this->stream .= self::draw_text( $x + $label_width, $this->y, $font, 9.5, $line, self::TEXT );
				$this->y      -= 13;
			}

			$this->y -= 3;
		}
	}

	/**
	 * Large key facts in a row — e.g. an appointment's date, time, doctor
	 * and visit type, so they can be found at a glance.
	 *
	 * @param array $facts List of array( label, value ).
	 * @return void
	 */
	public function facts( array $facts ) {
		$columns = array();

		foreach ( $facts as $fact ) {
			$columns[] = array(
				'label' => $fact[0],
				'lines' => array( array( 'text' => $fact[1], 'style' => 'large' ) ),
			);
		}

		$this->people( $columns );
	}

	/**
	 * Wrapped paragraph, flowing across pages line by line.
	 *
	 * @param string $text  Text (hard line breaks kept).
	 * @param string $style 'body' or 'muted'.
	 * @param float  $x     Left edge (defaults to the content edge).
	 * @param float  $width Width (defaults to the content width).
	 * @return void
	 */
	public function paragraph( $text, $style = 'body', $x = null, $width = null ) {
		$x     = null === $x ? $this->left : $x;
		$width = null === $width ? $this->width : $width;

		list( $font, $size, $lead, $color ) = self::line_style( $style );

		foreach ( self::wrap_text( $text, $font, $size, $width ) as $line ) {
			$this->ensure( $lead );
			$this->stream .= self::draw_text( $x, $this->y, $font, $size, $line, $color );
			$this->y      -= $lead;
		}

		$this->y -= 4;
	}

	/**
	 * A numbered/bulleted item: the marker in the margin, the text wrapping
	 * with a hanging indent so continuation lines align under the text.
	 *
	 * @param string $marker Marker, e.g. "1.".
	 * @param string $text   Item text (kept exactly; hard line breaks kept).
	 * @param string $style  'strong', 'body' or 'muted'.
	 * @return void
	 */
	public function list_item( $marker, $text, $style = 'body' ) {
		list( $font, $size, $lead, $color ) = self::line_style( $style );

		$indent = 18;
		$lines  = self::wrap_text( $text, $font, $size, $this->width - $indent );

		foreach ( $lines as $index => $line ) {
			$this->ensure( $lead );

			if ( 0 === $index ) {
				$this->stream .= self::draw_text( $this->left, $this->y, $font, $size, $marker, $color );
			}

			$this->stream .= self::draw_text( $this->left + $indent, $this->y, $font, $size, $line, $color );
			$this->y      -= $lead;
		}
	}

	/**
	 * Left edge for text indented under a list item.
	 *
	 * @return float
	 */
	public function indent_x() {
		return $this->left + 18;
	}

	/**
	 * A ruled table: column headings, rows separated by thin rules, cells
	 * that wrap instead of clipping, and headings repeated after a page
	 * break. A row is never split across pages unless it is taller than a
	 * whole page; its optional full-width note (e.g. medicine instructions)
	 * flows line by line.
	 *
	 * @param array $columns List of array( 'label' => string, 'width' => float|null (null = take the remaining width), 'align' => 'left'|'right' ).
	 * @param array $rows    List of array( 'cells' => array of string|array( 'text', 'bold' => bool, 'sub' => string ), 'note' => string, 'note_label' => string ).
	 * @return void
	 */
	public function table( array $columns, array $rows ) {
		$fixed = 0;
		$auto  = 0;

		foreach ( $columns as $column ) {
			if ( null === $column['width'] ) {
				++$auto;
			} else {
				$fixed += $column['width'];
			}
		}

		$x = $this->left;

		foreach ( $columns as $index => $column ) {
			$columns[ $index ]['width'] = null === $column['width'] ? ( $this->width - $fixed ) / max( 1, $auto ) : $column['width'];
			$columns[ $index ]['x']     = $x;
			$x                         += $columns[ $index ]['width'];
		}

		$this->active_table = $columns;

		// Straight after a section heading, its rule already tops the table.
		if ( null !== $this->section_end_y && abs( $this->y - $this->section_end_y ) < 0.01 ) {
			$this->y += 9;
			$this->table_header( false );
		} else {
			$this->ensure( 34 );
			$this->table_header();
		}

		foreach ( $rows as $row ) {
			$this->table_row( $columns, $row );
		}

		$this->active_table = null;
		$this->y           -= 6;
	}

	/**
	 * Right-hand totals block. The emphasised row gets a rule above it.
	 *
	 * @param array $rows List of array( label, value, style ) — style 'normal', 'muted' or 'strong'.
	 * @return void
	 */
	public function totals( array $rows ) {
		$block_width = 250;
		$x           = $this->right - $block_width;

		$this->ensure( count( $rows ) * 17 + 10 );

		foreach ( $rows as $row ) {
			$style = isset( $row[2] ) ? $row[2] : 'normal';

			if ( 'strong' === $style ) {
				$this->y      -= 8;
				$this->stream .= self::draw_line( $x, $this->y + 14, $this->right, $this->y + 14, self::DARK, 0.8 );
				$this->y      -= 5;
				$this->stream .= self::draw_text( $x, $this->y, 'F2', 11, $row[0], self::TEXT );
				$this->stream .= self::draw_text_right( $this->right, $this->y, 'F2', 11, $row[1], self::TEXT );
				$this->y      -= 19;
				continue;
			}

			$color         = 'muted' === $style ? self::MUTED : self::TEXT;
			$this->stream .= self::draw_text( $x, $this->y, 'F1', 9.5, $row[0], $color );
			$this->stream .= self::draw_text_right( $this->right, $this->y, 'F1', 9.5, $row[1], $color );
			$this->y      -= 15;
		}

		$this->y -= 6;
	}

	/**
	 * Signature line on the right with the signer's name under it — a
	 * place to sign by hand; nothing is drawn that the record doesn't hold.
	 *
	 * @param string $name Signer's name.
	 * @param string $sub  Optional second line (e.g. qualification).
	 * @return void
	 */
	public function signature( $name, $sub = '' ) {
		$this->ensure( 70 );
		$this->y -= 30;

		$x             = $this->right - 200;
		$this->stream .= self::draw_line( $x, $this->y, $this->right, $this->y, self::DARK, 0.6 );
		$this->y      -= 13;
		$this->stream .= self::draw_text( $x, $this->y, 'F2', 9.5, $name, self::TEXT );

		if ( '' !== trim( (string) $sub ) ) {
			foreach ( self::wrap_text( $sub, 'F1', 8.5, 200 ) as $line ) {
				$this->y      -= 11.5;
				$this->stream .= self::draw_text( $x, $this->y, 'F1', 8.5, $line, self::MUTED );
			}
		}

		$this->y -= 16;
	}

	/**
	 * Vertical space.
	 *
	 * @param float $points Space to add.
	 * @return void
	 */
	public function space( $points ) {
		$this->y -= $points;
	}

	/**
	 * Finishes the document: footer and page number on every page.
	 *
	 * @return string Raw PDF bytes.
	 */
	public function render() {
		$this->pages[] = $this->stream;
		$this->stream  = '';

		$brand     = self::brand();
		$total     = count( $this->pages );
		$generated = sprintf(
			/* translators: %s: date and time the document was generated. */
			__( 'Computer-generated document · Generated %s', 'doctor-ak-portal' ),
			date_i18n( 'd M Y, h:i A' )
		);
		$contact   = implode( '  ·  ', array_filter( array( $brand['name'], $brand['domain'], $brand['phone'] ) ) );
		$streams   = array();

		foreach ( $this->pages as $index => $stream ) {
			$footer  = self::draw_line( $this->left, self::FOOTER_RULE_Y, $this->right, self::FOOTER_RULE_Y, self::LIGHT, 0.5 );
			$footer .= self::draw_text( $this->left, self::FOOTER_RULE_Y - 13, 'F1', 7.5, $contact, self::MUTED );
			$footer .= self::draw_text( $this->left, self::FOOTER_RULE_Y - 23, 'F1', 7.5, $generated, self::MUTED );
			/* translators: 1: page number, 2: total pages. */
			$footer .= self::draw_text_right( $this->right, self::FOOTER_RULE_Y - 13, 'F1', 7.5, sprintf( __( 'Page %1$d of %2$d', 'doctor-ak-portal' ), $index + 1, $total ), self::MUTED );

			$streams[] = $stream . $footer;
		}

		return self::assemble_pages( $streams, $this->logo, $this->title . ( '' !== $this->reference ? ' ' . $this->reference : '' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Internals                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Starts a new page when `$height` more points wouldn't fit above the
	 * footer — repeating the active table's headings.
	 *
	 * @param float $height Points about to be used.
	 * @return void
	 */
	private function ensure( $height ) {
		if ( $this->y - $height >= self::CONTENT_BOTTOM ) {
			return;
		}

		$this->new_page();

		if ( null !== $this->active_table ) {
			$this->table_header();
		}
	}

	/**
	 * Closes the current page and opens the next with a compact
	 * continuation header.
	 *
	 * @return void
	 */
	private function new_page() {
		$this->pages[] = $this->stream;
		$this->stream  = '';

		$brand = self::brand();
		$top   = self::TOP_Y;

		$this->stream .= self::draw_text( $this->left, $top - 10, 'F2', 9, $brand['name'], self::TEXT );
		/* translators: 1: document title, 2: document reference. */
		$this->stream .= self::draw_text_right( $this->right, $top - 10, 'F1', 8.5, trim( sprintf( __( '%1$s %2$s (continued)', 'doctor-ak-portal' ), $this->title, $this->reference ) ), self::MUTED );
		$this->stream .= self::draw_line( $this->left, $top - 18, $this->right, $top - 18, self::LIGHT, 0.5 );

		$this->y = $top - 38;
	}

	/**
	 * Draws the active table's column headings at the cursor.
	 *
	 * @return void
	 */
	private function table_header( $top_rule = true ) {
		if ( $top_rule ) {
			$this->stream .= self::draw_line( $this->left, $this->y, $this->right, $this->y, self::DARK, 0.8 );
		}

		$this->y -= 12;

		foreach ( $this->active_table as $column ) {
			$this->stream .= $this->cell_text( $column, $this->y, 'F2', 7.5, strtoupper( $column['label'] ), self::MUTED, 0.3 );
		}

		$this->y      -= 6;
		$this->stream .= self::draw_line( $this->left, $this->y, $this->right, $this->y, self::LIGHT, 0.5 );
		$this->y      -= 14;
	}

	/**
	 * Draws one table row (and its note), breaking pages as needed.
	 *
	 * @param array $columns Resolved columns (with x/width).
	 * @param array $row     See table().
	 * @return void
	 */
	private function table_row( array $columns, array $row ) {
		$pad    = 8;
		$cells  = array();
		$height = 0;

		foreach ( $columns as $index => $column ) {
			$cell  = isset( $row['cells'][ $index ] ) ? $row['cells'][ $index ] : '';
			$cell  = is_array( $cell ) ? $cell : array( 'text' => (string) $cell );
			$font  = ! empty( $cell['bold'] ) ? 'F2' : 'F1';
			$inner = $column['width'] - $pad;
			$main  = self::wrap_text( $cell['text'], $font, 9.5, $inner );
			$sub   = ( isset( $cell['sub'] ) && '' !== trim( (string) $cell['sub'] ) ) ? self::wrap_text( $cell['sub'], 'F1', 8.5, $inner ) : array();

			$cells[] = array( $main, $sub, $font );
			$height  = max( $height, count( $main ) * 12.5 + count( $sub ) * 11 );
		}

		// A note starts under the first real column (past a narrow '#' column).
		$note_x     = ( count( $columns ) > 1 && $columns[0]['width'] < 32 ) ? $columns[1]['x'] : $this->left;
		$note_lines = array();

		if ( isset( $row['note'] ) && '' !== trim( (string) $row['note'] ) ) {
			$label      = isset( $row['note_label'] ) ? $row['note_label'] . ': ' : '';
			$note_lines = self::wrap_text( $label . $row['note'], 'F1', 8.5, $this->right - $note_x );
		}

		$total = $height + count( $note_lines ) * 11 + 10;

		// Keep the row together when it fits on a page at all.
		if ( $total <= ( self::TOP_Y - 60 ) - self::CONTENT_BOTTOM ) {
			$this->ensure( $total );
		} else {
			$this->ensure( $height + 10 );
		}

		$top = $this->y;

		foreach ( $columns as $index => $column ) {
			$line_y = $top;

			foreach ( $cells[ $index ][0] as $line ) {
				$this->stream .= $this->cell_text( $column, $line_y, $cells[ $index ][2], 9.5, $line, self::TEXT );
				$line_y       -= 12.5;
			}

			foreach ( $cells[ $index ][1] as $line ) {
				$this->stream .= $this->cell_text( $column, $line_y, 'F1', 8.5, $line, self::MUTED );
				$line_y       -= 11;
			}
		}

		$this->y = $top - $height;

		foreach ( $note_lines as $line ) {
			$this->ensure( 11 );
			$this->stream .= self::draw_text( $note_x, $this->y, 'F1', 8.5, $line, self::DARK );
			$this->y      -= 11;
		}

		$this->y      += 5;
		$this->stream .= self::draw_line( $this->left, $this->y - 2, $this->right, $this->y - 2, self::LIGHT, 0.4 );
		$this->y      -= 13.5;
	}

	/**
	 * Text placed in a table column, honouring its alignment.
	 *
	 * @return string
	 */
	private function cell_text( array $column, $y, $font, $size, $text, $color, $tracking = 0.0 ) {
		$align = isset( $column['align'] ) ? $column['align'] : 'left';

		if ( 'right' === $align ) {
			return self::draw_text_right( $column['x'] + $column['width'], $y, $font, $size, $text, $color, $tracking );
		}

		return self::draw_text( $column['x'], $y, $font, $size, $text, $color, $tracking );
	}

	/**
	 * Small uppercase, letter-spaced label.
	 *
	 * @return string
	 */
	private function label_text( $x, $y, $text ) {
		return self::draw_text( $x, $y, 'F2', self::LABEL_SIZE, strtoupper( $text ), self::MUTED, 0.5 );
	}

	/**
	 * Font, size, line height and colour for a line style.
	 *
	 * @param string $style 'strong', 'large', 'muted' or 'body'.
	 * @return array
	 */
	private static function line_style( $style ) {
		switch ( $style ) {
			case 'large':
				return array( 'F2', 13, 16, self::TEXT );
			case 'strong':
				return array( 'F2', 10.5, 14, self::TEXT );
			case 'muted':
				return array( 'F1', self::SMALL_SIZE, self::SMALL_LEAD, self::MUTED );
			default:
				return array( 'F1', 9.5, 12.5, self::TEXT );
		}
	}
}
