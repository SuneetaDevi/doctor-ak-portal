<?php
/**
 * Builds one doctor's revenue statement — every clinic (and video
 * consultations) they earned from in a period, as separate line items,
 * plus a compact appointments list — as a standalone PDF, for the admin
 * Billing screen's per-doctor "Download" action. Modeled on a referral
 * platform's own billing-statement layout (location-wise line items,
 * platform charges, net payable, appointments list).
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
 * Class Doctor_Statement_Pdf
 *
 * Pdf_Document only assembles a single page, so the appointments list at
 * the bottom is capped (MAX_APPOINTMENT_ROWS) with a "+N more" note rather
 * than paginating — the full list is always available online via the
 * Billing ledger/filters.
 */
class Doctor_Statement_Pdf extends Pdf_Document {

	/**
	 * Max appointment rows listed before truncating with a "+N more" note.
	 *
	 * @var int
	 */
	const MAX_APPOINTMENT_ROWS = 18;

	/**
	 * Truncates text (appending '...') so its estimated rendered width never
	 * exceeds `$max_width` — the table/header layout here uses fixed column
	 * x-positions with no native clipping, so a long clinic name/address/
	 * patient name would otherwise overlap the next column.
	 *
	 * @param string $text      Text to fit.
	 * @param string $font      'F1' or 'F2' — must match what it'll be drawn with.
	 * @param float  $size      Font size — must match what it'll be drawn with.
	 * @param float  $max_width Maximum rendered width, in PDF points.
	 * @return string
	 */
	private static function fit_text( $text, $font, $size, $max_width ) {
		if ( $max_width <= 0 ) {
			return $text;
		}

		$bold             = 'F2' === $font;
		$avg_char_width   = $size * ( $bold ? 0.60 : 0.52 );
		$estimated_width  = self::mb_strlen_safe( $text ) * $avg_char_width;

		if ( $estimated_width <= $max_width ) {
			return $text;
		}

		$max_chars = max( 1, (int) floor( $max_width / $avg_char_width ) - 3 );

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_chars ) . '...' : substr( $text, 0, $max_chars ) . '...';
	}

	/**
	 * Word-wraps text to fit within `$max_width`, up to `$max_lines` lines —
	 * used for the columns most likely to hold a long clinic name/address
	 * (rather than truncating them to a single "..." line, see fit_text()).
	 * If the text still doesn't fit within `$max_lines`, the last line is
	 * truncated with '...' via fit_text().
	 *
	 * @param string $text      Text to wrap.
	 * @param string $font      'F1' or 'F2' — must match what it'll be drawn with.
	 * @param float  $size      Font size — must match what it'll be drawn with.
	 * @param float  $max_width Maximum rendered width per line, in PDF points.
	 * @param int    $max_lines Maximum number of lines. Default 2.
	 * @return array List of line strings, length 1 to $max_lines.
	 */
	private static function wrap_lines( $text, $font, $size, $max_width, $max_lines = 2 ) {
		if ( $max_width <= 0 || '' === trim( (string) $text ) ) {
			return array( (string) $text );
		}

		$bold             = 'F2' === $font;
		$avg_char_width   = $size * ( $bold ? 0.60 : 0.52 );
		$max_chars        = max( 1, (int) floor( $max_width / $avg_char_width ) );

		$words = preg_split( '/\s+/', trim( (string) $text ) );
		$lines = array( '' );

		foreach ( $words as $word ) {
			$line_index = count( $lines ) - 1;
			$candidate  = '' === $lines[ $line_index ] ? $word : $lines[ $line_index ] . ' ' . $word;

			if ( self::mb_strlen_safe( $candidate ) <= $max_chars ) {
				$lines[ $line_index ] = $candidate;
				continue;
			}

			if ( $line_index + 1 >= $max_lines ) {
				// No room for another line — truncate this line (plus the
				// word that didn't fit) and stop, dropping any later words.
				$lines[ $line_index ] = self::fit_text( $candidate, $font, $size, $max_width );
				return $lines;
			}

			$lines[] = $word;
		}

		return $lines;
	}

	/**
	 * Builds the statement PDF for one doctor.
	 *
	 * @param \WP_User $doctor        The doctor.
	 * @param array    $line_items    Rows from Revenue_Ledger::balances_by_doctor_and_clinic(), filtered to this doctor — each augmented with a 'label' key by the caller.
	 * @param array    $ledger_rows   This doctor's rows from Revenue_Ledger::all_flat_for_admin(), for the appointments list.
	 * @param string   $period_label  e.g. 'Aug 2026', or a date range string.
	 * @return string Raw PDF file bytes.
	 */
	public static function build( \WP_User $doctor, array $line_items, array $ledger_rows, $period_label ) {
		$clinic_name    = get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' );
		$clinic_address = get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' );
		$clinic_phone   = get_option( Site_Footer::OPTION_CLINIC_PHONE, '' );

		$doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
		$doctor_name = '' !== $doctor_name ? $doctor_name : $doctor->display_name;

		$statement_number = sprintf( 'STMT-%05d-%s', $doctor->ID, gmdate( 'Ym' ) );

		$logo = self::load_logo_jpeg();

		$left   = 50;
		$right  = self::PAGE_WIDTH - 50;
		$y      = self::PAGE_HEIGHT - 60;
		$stream = '';

		$text_x = $left;

		if ( $logo ) {
			$stream .= self::draw_image( 'Im1', $left, $y - $logo['height'] + 10, $logo['width'], $logo['height'] );
			$text_x  = $left + $logo['width'] + 15;
		}

		// The clinic name's first line shares its row with the right-aligned
		// "REVENUE STATEMENT" title, so it must never be wide enough to reach
		// it — any overflow wraps onto a second line below instead, clear of
		// the title.
		$title_width     = self::mb_strlen_safe( __( 'REVENUE STATEMENT', 'doctor-ak-portal' ) ) * 14 * 0.60;
		$clinic_name_max = ( $right - $title_width - 20 ) - $text_x;

		$clinic_name_lines = self::wrap_lines( $clinic_name, 'F2', 14, $clinic_name_max, 2 );

		$stream .= self::draw_text( $text_x, $y, 'F2', 14, $clinic_name_lines[0] );
		$y      -= 16;

		for ( $i = 1, $n = count( $clinic_name_lines ); $i < $n; $i++ ) {
			$stream .= self::draw_text( $text_x, $y, 'F2', 14, self::fit_text( $clinic_name_lines[ $i ], 'F2', 14, $right - $text_x ) );
			$y      -= 16;
		}

		if ( '' !== $clinic_address ) {
			$stream .= self::draw_text( $text_x, $y, 'F1', 9, self::fit_text( $clinic_address, 'F1', 9, $right - $text_x ), 0.4 );
			$y      -= 12;
		}

		if ( '' !== $clinic_phone ) {
			$stream .= self::draw_text( $text_x, $y, 'F1', 9, $clinic_phone, 0.4 );
			$y      -= 12;
		}

		// "STATEMENT" block, top-right.
		$header_block_y = self::PAGE_HEIGHT - 60;
		$stream        .= self::draw_text_right( $right, $header_block_y, 'F2', 14, __( 'REVENUE STATEMENT', 'doctor-ak-portal' ) );
		$stream        .= self::draw_text_right( $right, $header_block_y - 16, 'F1', 9, $statement_number, 0.4 );
		$stream        .= self::draw_text_right( $right, $header_block_y - 28, 'F1', 9, $period_label, 0.4 );

		$y = min( $y, $header_block_y - 28 ) - 40;

		$stream .= self::draw_text( $left, $y, 'F1', 8, strtoupper( __( 'Doctor', 'doctor-ak-portal' ) ), 0.4 );
		$y      -= 14;
		$stream .= self::draw_text( $left, $y, 'F2', 11, $doctor_name );
		$y      -= 24;

		// Line-items table header.
		$stream .= self::draw_line( $left, $y, $right, $y, 0.1, 0.1, 0.1, 1.2 );
		$y      -= 14;
		$stream .= self::draw_table_row(
			array(
				array( 'x' => $left, 'text' => strtoupper( __( 'Clinic / Type', 'doctor-ak-portal' ) ) ),
				array( 'x' => $left + 260, 'text' => strtoupper( __( 'Avg. Charge', 'doctor-ak-portal' ) ), 'align' => 'right' ),
				array( 'x' => $left + 360, 'text' => strtoupper( __( 'Qty', 'doctor-ak-portal' ) ), 'align' => 'right' ),
				array( 'x' => $right, 'text' => strtoupper( __( 'Amount', 'doctor-ak-portal' ) ), 'align' => 'right' ),
			),
			$y,
			'F2',
			9
		);
		$y      -= 8;
		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 16;

		$gross_total     = 0.0;
		$platform_fees   = 0.0;
		$doctor_total    = 0.0;

		foreach ( $line_items as $item ) {
			$avg_price   = $item['appointment_count'] > 0 ? $item['gross_total'] / $item['appointment_count'] : 0.0;
			$label_lines = self::wrap_lines( $item['label'], 'F1', 9, 240, 2 );

			$stream .= self::draw_table_row(
				array(
					array( 'x' => $left, 'text' => $label_lines[0] ),
					array( 'x' => $left + 260, 'text' => 'PKR ' . number_format( $avg_price, 0 ), 'align' => 'right' ),
					array( 'x' => $left + 360, 'text' => (string) $item['appointment_count'], 'align' => 'right' ),
					array( 'x' => $right, 'text' => 'PKR ' . number_format( $item['gross_total'], 0 ), 'align' => 'right' ),
				),
				$y,
				'F1',
				9
			);

			for ( $i = 1, $n = count( $label_lines ); $i < $n; $i++ ) {
				$y      -= 11;
				$stream .= self::draw_text( $left, $y, 'F1', 9, $label_lines[ $i ] );
			}

			$y -= 16;

			$gross_total += $item['gross_total'];
		}

		foreach ( $ledger_rows as $row ) {
			$platform_fees += $row['platform_fee'];
			$doctor_total  += $row['doctor_amount'];
		}

		if ( $platform_fees > 0 ) {
			$stream .= self::draw_table_row(
				array(
					array( 'x' => $left, 'text' => __( 'Platform/gateway charges', 'doctor-ak-portal' ) ),
					array( 'x' => $right, 'text' => 'PKR ' . number_format( $platform_fees, 0 ), 'align' => 'right' ),
				),
				$y,
				'F1',
				9
			);
			$y -= 16;
		}

		$y -= 4;
		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 20;

		$stream .= self::draw_text_right( $right - 150, $y, 'F1', 10, __( 'Gross collected', 'doctor-ak-portal' ), 0.3 );
		$stream .= self::draw_text_right( $right, $y, 'F1', 10, 'PKR ' . number_format( $gross_total, 0 ) );
		$y      -= 16;

		$stream .= self::draw_text_right( $right - 150, $y, 'F2', 11, __( "Doctor's net share", 'doctor-ak-portal' ) );
		$stream .= self::draw_text_right( $right, $y, 'F2', 11, 'PKR ' . number_format( $doctor_total, 0 ) );
		$y      -= 30;

		// Appointments list.
		$stream .= self::draw_line( $left, $y, $right, $y, 0.1, 0.1, 0.1, 1.2 );
		$y      -= 14;
		$stream .= self::draw_table_row(
			array(
				array( 'x' => $left, 'text' => strtoupper( __( 'Location', 'doctor-ak-portal' ) ) ),
				array( 'x' => $left + 180, 'text' => strtoupper( __( 'Date', 'doctor-ak-portal' ) ) ),
				array( 'x' => $left + 280, 'text' => strtoupper( __( 'Patient', 'doctor-ak-portal' ) ) ),
				array( 'x' => $right, 'text' => strtoupper( __( 'Amount', 'doctor-ak-portal' ) ), 'align' => 'right' ),
			),
			$y,
			'F2',
			8
		);
		$y      -= 8;
		$stream .= self::draw_line( $left, $y, $right, $y, 0.6, 0.6, 0.6 );
		$y      -= 14;

		$listed = 0;

		foreach ( $ledger_rows as $row ) {
			if ( Revenue_Ledger::TRANSACTION_REFUND === $row['transaction_type'] ) {
				continue;
			}

			if ( $listed >= self::MAX_APPOINTMENT_ROWS ) {
				break;
			}

			$appointment = Appointments::notification_data( $row['appointment_id'] );

			if ( empty( $appointment ) ) {
				continue;
			}

			if ( 0 === $row['clinic_id'] ) {
				$location = __( 'Video Consultation', 'doctor-ak-portal' );
			} else {
				// clinic_label is "Name — full address"; only the name fits this column.
				$location = '' !== $appointment['clinic_label'] ? strtok( $appointment['clinic_label'], '—' ) : $appointment['type_label'];
				$location = trim( $location );
			}

			$location_lines = self::wrap_lines( $location, 'F1', 8, 170, 2 );

			$stream .= self::draw_table_row(
				array(
					array( 'x' => $left, 'text' => $location_lines[0] ),
					array( 'x' => $left + 180, 'text' => $row['transaction_date'] ),
					array( 'x' => $left + 280, 'text' => self::fit_text( $appointment['patient_name'], 'F1', 8, 140 ) ),
					array( 'x' => $right, 'text' => 'PKR ' . number_format( $row['gross_amount'], 0 ), 'align' => 'right' ),
				),
				$y,
				'F1',
				8
			);

			for ( $i = 1, $n = count( $location_lines ); $i < $n; $i++ ) {
				$y      -= 10;
				$stream .= self::draw_text( $left, $y, 'F1', 8, $location_lines[ $i ] );
			}

			$y -= 13;
			++$listed;
		}

		$remaining = count(
			array_filter(
				$ledger_rows,
				function ( $row ) {
					return Revenue_Ledger::TRANSACTION_REFUND !== $row['transaction_type'];
				}
			)
		) - $listed;

		if ( $remaining > 0 ) {
			$y -= 4;
			/* translators: %d: number of additional appointments not shown on this page. */
			$stream .= self::draw_text( $left, $y, 'F1', 8, sprintf( __( '+ %d more — see the full ledger online.', 'doctor-ak-portal' ), $remaining ), 0.45 );
		}

		return self::assemble_single_page( $stream, $logo );
	}
}
