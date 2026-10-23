<?php
/**
 * Shared layout for every system email the plugin sends.
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
 * Class Email_Template
 *
 * One email design for patients, doctors, receptionists and administrators:
 * a light outer background, a centred ~600px white card with the site logo,
 * a heading, an optional greeting and short explanation, an optional
 * labelled details block, one primary button (with a copy-the-link
 * fallback underneath), small notes, and a compact footer with the site's
 * support contact details. Built from nested tables with inline styles so
 * it holds up in Gmail, Outlook (including the Word renderer), Apple Mail
 * and phone clients — no scripts, forms, web fonts, icon fonts or
 * flexbox/grid. Every message also carries a plain-text alternative,
 * attached through 'phpmailer_init' since wp_mail() itself only takes one
 * body.
 *
 * An email is described as an array — every key optional except heading:
 *
 *     'preheader'   Inbox preview text (hidden in the body).
 *     'eyebrow'     Small label above the heading.
 *     'heading'     The heading.
 *     'greeting'    e.g. "Hi Ayesha," — omitted when ''.
 *     'paragraphs'  Plain-text paragraphs.
 *     'details'     [ [label, value], … ] — plain text, shown as a labelled block.
 *     'details_title' Optional caption above the details block.
 *     'body_html'   Pre-escaped extra block after the details (e.g. the review stars).
 *     'body_text'   Its plain-text version.
 *     'button'      [ 'label' => …, 'url' => … ] — the one primary action.
 *     'links'       [ [label, url], … ] — secondary text links under the button.
 *     'notes'       Small print — strings, or [ 'text' => …, 'link_label' => …, 'url' => … ].
 *     'footer_note' Why the recipient got this email.
 */
class Email_Template {

	/**
	 * Brand colours — the site's green on neutral greys.
	 */
	const COLOR_BRAND      = '#16634b';
	const COLOR_BRAND_TINT = '#eaf4ef';
	const COLOR_TEXT       = '#1f2933';
	const COLOR_MUTED      = '#5b6670';
	const COLOR_BORDER     = '#e3e8e6';
	const COLOR_PAGE       = '#f3f5f4';
	const COLOR_PANEL      = '#f7f9f8';

	const FONT_STACK = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

	/**
	 * Logo height in the email header, in CSS pixels.
	 *
	 * @var int
	 */
	const LOGO_HEIGHT = 56;

	/**
	 * Plain-text body waiting for the next PHPMailer instance.
	 *
	 * @var string|null
	 */
	private static $pending_text = null;

	/**
	 * Sends one email in the shared layout, with a plain-text alternative.
	 * Invalid or empty addresses are skipped silently, as before. Fires
	 * 'doctor_ak_email_sent' afterwards so the CRM can show the email in
	 * the recipient's history (see Crm_Email::on_email_sent()).
	 *
	 * @param string   $to            Recipient address.
	 * @param string   $subject       Subject line (plain text).
	 * @param array    $email         Email description, see the class docblock.
	 * @param string[] $attachments   File paths to attach.
	 * @param string[] $extra_headers Extra headers, e.g. List-Unsubscribe.
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public static function send( $to, $subject, array $email, array $attachments = array(), array $extra_headers = array() ) {
		if ( '' === (string) $to || ! is_email( $to ) ) {
			return false;
		}

		$rendered = self::render( $subject, $email );
		$headers  = array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), self::from_headers(), $extra_headers );

		self::$pending_text = $rendered['text'];
		add_action( 'phpmailer_init', array( __CLASS__, 'attach_text_body' ) );

		$sent = wp_mail( $to, $subject, $rendered['html'], $headers, $attachments );

		remove_action( 'phpmailer_init', array( __CLASS__, 'attach_text_body' ) );
		self::$pending_text = null;

		do_action( 'doctor_ak_email_sent', $to, $subject, (bool) $sent );

		return (bool) $sent;
	}

	/**
	 * 'phpmailer_init' callback: adds the plain-text alternative, which
	 * turns the message into multipart/alternative.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer about to send.
	 * @return void
	 */
	public static function attach_text_body( $phpmailer ) {
		if ( null !== self::$pending_text && '' !== self::$pending_text ) {
			$phpmailer->AltBody = self::$pending_text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's own property.
		}
	}

	/**
	 * From header, from Settings → Email Notifications (falling back to the
	 * clinic name and the site admin address, as before).
	 *
	 * @return string[]
	 */
	public static function from_headers() {
		$name  = get_option( Notifications::OPTION_FROM_NAME, '' );
		$email = get_option( Notifications::OPTION_FROM_EMAIL, '' );

		if ( '' === $name ) {
			$name = get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$email = get_option( 'admin_email' );
		}

		return array( sprintf( 'From: %s <%s>', $name, $email ) );
	}

	/**
	 * Renders an email to HTML and plain text.
	 *
	 * @param string $subject Subject line, used as the HTML <title>.
	 * @param array  $email   Email description, see the class docblock.
	 * @return array { html: string, text: string }
	 */
	public static function render( $subject, array $email ) {
		$email = wp_parse_args(
			$email,
			array(
				'preheader'     => '',
				'eyebrow'       => '',
				'heading'       => '',
				'greeting'      => '',
				'paragraphs'    => array(),
				'details'       => array(),
				'details_title' => '',
				'body_html'     => '',
				'body_text'     => '',
				'button'        => array(),
				'links'         => array(),
				'notes'         => array(),
				'footer_note'   => '',
			)
		);

		$email['notes'] = array_values( array_filter( (array) $email['notes'] ) );

		return array(
			'html' => self::render_html( $subject, $email ),
			'text' => self::render_text( $email ),
		);
	}

	/**
	 * The site's name as shown in emails.
	 *
	 * @return string
	 */
	public static function site_name() {
		$name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		return '' !== trim( $name ) ? $name : Site_Footer::BRAND_DOMAIN;
	}

	/**
	 * Builds the HTML version.
	 *
	 * @param string $subject Subject line.
	 * @param array  $email   Normalised email description.
	 * @return string
	 */
	private static function render_html( $subject, array $email ) {
		$font = self::FONT_STACK;

		$content  = '';

		if ( '' !== $email['eyebrow'] ) {
			$content .= '<p class="dak-brand" style="margin:0 0 8px;font-family:' . $font . ';font-size:13px;line-height:18px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:' . self::COLOR_BRAND . ';">' . esc_html( $email['eyebrow'] ) . '</p>';
		}

		$content .= '<h1 class="dak-text" style="margin:0 0 16px;font-family:' . $font . ';font-size:24px;line-height:32px;font-weight:700;color:' . self::COLOR_TEXT . ';">' . esc_html( $email['heading'] ) . '</h1>';

		if ( '' !== $email['greeting'] ) {
			$content .= self::paragraph_html( $email['greeting'] );
		}

		foreach ( (array) $email['paragraphs'] as $paragraph ) {
			if ( '' !== trim( (string) $paragraph ) ) {
				$content .= self::paragraph_html( $paragraph );
			}
		}

		if ( ! empty( $email['details'] ) ) {
			$content .= self::details_html( $email['details'], $email['details_title'] );
		}

		if ( '' !== $email['body_html'] ) {
			$content .= $email['body_html'];
		}

		if ( ! empty( $email['button']['url'] ) && ! empty( $email['button']['label'] ) ) {
			$content .= self::button_html( $email['button']['label'], $email['button']['url'] );
		}

		if ( ! empty( $email['links'] ) ) {
			$links = array();

			foreach ( $email['links'] as $link ) {
				$links[] = '<a class="dak-link" href="' . esc_url( $link[1] ) . '" style="color:' . self::COLOR_BRAND . ';text-decoration:underline;">' . esc_html( $link[0] ) . '</a>';
			}

			$content .= '<p style="margin:0 0 16px;font-family:' . $font . ';font-size:15px;line-height:24px;">' . implode( ' &nbsp;&middot;&nbsp; ', $links ) . '</p>';
		}

		foreach ( (array) $email['notes'] as $note ) {
			$content .= self::note_html( $note );
		}

		$preheader = '';

		if ( '' !== $email['preheader'] ) {
			// The filler keeps clients from pulling body text into the preview.
			$preheader = '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">'
				. esc_html( $email['preheader'] )
				. str_repeat( '&#847;&zwnj;&nbsp;', 40 )
				. '</div>';
		}

		return '<!DOCTYPE html>'
			. '<html lang="' . esc_attr( str_replace( '_', '-', (string) get_locale() ) ) . '" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">'
			. '<head>'
			. '<meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta http-equiv="X-UA-Compatible" content="IE=edge">'
			. '<meta name="x-apple-disable-message-reformatting">'
			. '<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">'
			. '<meta name="color-scheme" content="light dark">'
			. '<meta name="supported-color-schemes" content="light dark">'
			. '<title>' . esc_html( $subject ) . '</title>'
			. '<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->'
			. '<style>' . self::head_css() . '</style>'
			. '</head>'
			. '<body class="dak-body" style="margin:0;padding:0;width:100%;background-color:' . self::COLOR_PAGE . ';-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">'
			. $preheader
			. '<table role="presentation" class="dak-body" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . self::COLOR_PAGE . '" style="background-color:' . self::COLOR_PAGE . ';">'
			. '<tr><td align="center" style="padding:24px 12px 32px;">'
			. '<!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->'
			. '<table role="presentation" class="dak-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">'
			. '<tr><td style="padding:0 4px 16px;">' . self::logo_html() . '</td></tr>'
			. '<tr><td class="dak-card" bgcolor="#ffffff" style="background-color:#ffffff;border:1px solid ' . self::COLOR_BORDER . ';border-top:4px solid ' . self::COLOR_BRAND . ';border-radius:12px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
			. '<tr><td class="dak-px" style="padding:32px 40px 28px;">' . $content . '</td></tr>'
			. '</table>'
			. '</td></tr>'
			. '<tr><td style="padding:20px 16px 0;">' . self::footer_html( $email['footer_note'] ) . '</td></tr>'
			. '</table>'
			. '<!--[if mso]></td></tr></table><![endif]-->'
			. '</td></tr>'
			. '</table>'
			. '</body>'
			. '</html>';
	}

	/**
	 * Small <style> block: mobile tweaks and dark-mode colours for clients
	 * that support them (Apple Mail, iOS, Outlook.com). Everything still
	 * reads correctly where it's stripped — the inline styles carry the
	 * design.
	 *
	 * @return string
	 */
	private static function head_css() {
		return 'body{margin:0;padding:0;}'
			. 'table{border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;}'
			. 'img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}'
			. 'a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important;}'
			. '@media only screen and (max-width:620px){'
			. '.dak-container{width:100%!important;max-width:100%!important;}'
			. '.dak-px{padding-left:22px!important;padding-right:22px!important;}'
			. '.dak-label,.dak-value{display:block!important;width:auto!important;}'
			. '.dak-label{padding:12px 16px 0 16px!important;border-bottom:0!important;}'
			. '.dak-value{padding:2px 16px 12px 16px!important;}'
			. '.dak-btn-table{width:100%!important;}'
			. '.dak-btn-link{display:block!important;}'
			. '.dak-stars a{width:44px!important;}'
			. '}'
			. '@media (prefers-color-scheme:dark){'
			. '.dak-body{background-color:#0f1312!important;}'
			. '.dak-card{background-color:#181d1b!important;border-color:#2b3330!important;border-top-color:#3aa679!important;}'
			. '.dak-text{color:#e8ecea!important;}'
			. '.dak-muted{color:#a8b2ad!important;}'
			. '.dak-panel{background-color:#202725!important;border-color:#2b3330!important;}'
			. '.dak-rule{border-color:#2b3330!important;}'
			. '.dak-brand,.dak-link{color:#7fd3ab!important;}'
			. '.dak-btn-cell{background-color:#1d7a5b!important;}'
			. '.dak-btn-link{color:#ffffff!important;}'
			. '}'
			. '[data-ogsc] .dak-text{color:#e8ecea!important;}'
			. '[data-ogsc] .dak-muted{color:#a8b2ad!important;}'
			. '[data-ogsc] .dak-brand,[data-ogsc] .dak-link{color:#7fd3ab!important;}'
			. '[data-ogsb] .dak-body{background-color:#0f1312!important;}'
			. '[data-ogsb] .dak-card{background-color:#181d1b!important;}'
			. '[data-ogsb] .dak-panel{background-color:#202725!important;}'
			. '[data-ogsb] .dak-btn-cell{background-color:#1d7a5b!important;}';
	}

	/**
	 * The header: the site logo at its own aspect ratio (alt text carries
	 * the site name when images are blocked), linking home.
	 *
	 * @return string
	 */
	private static function logo_html() {
		$font  = self::FONT_STACK;
		$name  = self::site_name();
		$url   = Site_Footer::bundled_logo_url();
		$home  = home_url( '/' );
		$label = '<span class="dak-text" style="font-family:' . $font . ';font-size:18px;line-height:24px;font-weight:700;color:' . self::COLOR_TEXT . ';">' . esc_html( $name ) . '</span>';

		if ( '' === $url ) {
			return '<a href="' . esc_url( $home ) . '" style="text-decoration:none;">' . $label . '</a>';
		}

		$height = self::LOGO_HEIGHT;
		$width  = 0;
		$path   = Site_Footer::bundled_logo_path();

		if ( '' !== $path && is_readable( $path ) ) {
			$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing/odd file just falls back to height-only sizing.

			if ( is_array( $size ) && $size[0] > 0 && $size[1] > 0 ) {
				$width = (int) round( $size[0] * $height / $size[1] );
			}
		}

		$img = '<img src="' . esc_url( $url ) . '"'
			. ( $width > 0 ? ' width="' . $width . '"' : '' )
			. ' height="' . $height . '"'
			. ' alt="' . esc_attr( $name ) . '"'
			. ' style="display:block;height:' . $height . 'px;' . ( $width > 0 ? 'width:' . $width . 'px;' : 'width:auto;' ) . 'border:0;font-family:' . $font . ';font-size:11px;line-height:13px;font-weight:700;color:' . self::COLOR_BRAND . ';">';

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
			. '<td valign="middle" style="padding:0 12px 0 0;"><a href="' . esc_url( $home ) . '" style="text-decoration:none;">' . $img . '</a></td>'
			. '<td valign="middle"><a href="' . esc_url( $home ) . '" style="text-decoration:none;" aria-hidden="true">' . $label . '</a></td>'
			. '</tr></table>';
	}

	/**
	 * One body paragraph.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function paragraph_html( $text ) {
		return '<p class="dak-text" style="margin:0 0 16px;font-family:' . self::FONT_STACK . ';font-size:16px;line-height:26px;color:' . self::COLOR_TEXT . ';">' . nl2br( esc_html( $text ) ) . '</p>';
	}

	/**
	 * The labelled details block — two columns on wide screens, stacked on
	 * phones (see head_css()).
	 *
	 * @param array  $rows  [ [label, value], … ].
	 * @param string $title Optional caption.
	 * @return string
	 */
	private static function details_html( array $rows, $title ) {
		$font = self::FONT_STACK;
		$html = '';
		$rows = array_values(
			array_filter(
				$rows,
				function ( $row ) {
					return isset( $row[1] ) && '' !== trim( (string) $row[1] );
				}
			)
		);
		$count = count( $rows );
		$index = 0;

		foreach ( $rows as $row ) {
			++$index;

			$border = $index < $count ? 'border-bottom:1px solid ' . self::COLOR_BORDER . ';' : '';
			$strong = ! empty( $row[2] );

			$html .= '<tr>'
				. '<td class="dak-label dak-muted dak-rule" valign="top" width="36%" style="width:36%;padding:10px 12px 10px 16px;' . $border . 'font-family:' . $font . ';font-size:14px;line-height:20px;color:' . self::COLOR_MUTED . ';">' . esc_html( $row[0] ) . '</td>'
				. '<td class="dak-value dak-text dak-rule" valign="top" style="padding:10px 16px 10px 0;' . $border . 'font-family:' . $font . ';font-size:15px;line-height:22px;font-weight:' . ( $strong ? '700' : '600' ) . ';color:' . self::COLOR_TEXT . ';word-break:break-word;">' . nl2br( esc_html( $row[1] ) ) . '</td>'
				. '</tr>';
		}

		if ( '' === $html ) {
			return '';
		}

		$caption = '' !== $title
			? '<p class="dak-muted" style="margin:8px 0 8px;font-family:' . $font . ';font-size:13px;line-height:18px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:' . self::COLOR_MUTED . ';">' . esc_html( $title ) . '</p>'
			: '';

		return $caption
			. '<table role="presentation" class="dak-panel" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . self::COLOR_PANEL . '" style="width:100%;margin:0 0 24px;background-color:' . self::COLOR_PANEL . ';border:1px solid ' . self::COLOR_BORDER . ';border-radius:8px;">'
			. $html
			. '</table>';
	}

	/**
	 * The primary button — a padded table cell, so it keeps its colour and
	 * size in Outlook — plus the link written out underneath for clients
	 * that block or mangle buttons.
	 *
	 * @param string $label Button text.
	 * @param string $url   Destination.
	 * @return string
	 */
	public static function button_html( $label, $url ) {
		$font = self::FONT_STACK;

		return '<table role="presentation" class="dak-btn-table" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 12px;">'
			. '<tr><td class="dak-btn-cell" align="center" bgcolor="' . self::COLOR_BRAND . '" style="background-color:' . self::COLOR_BRAND . ';border-radius:8px;">'
			. '<a class="dak-btn-link" href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;padding:14px 28px;font-family:' . $font . ';font-size:16px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;">' . esc_html( $label ) . '</a>'
			. '</td></tr>'
			. '</table>'
			. '<p class="dak-muted" style="margin:0 0 20px;font-family:' . $font . ';font-size:13px;line-height:20px;color:' . self::COLOR_MUTED . ';">'
			. esc_html__( 'If the button doesn’t work, copy this link into your browser:', 'doctor-ak-portal' ) . '<br>'
			. '<a class="dak-link" href="' . esc_url( $url ) . '" style="color:' . self::COLOR_BRAND . ';text-decoration:underline;word-break:break-all;">' . esc_html( $url ) . '</a>'
			. '</p>';
	}

	/**
	 * One line of small print, optionally ending in a link.
	 *
	 * @param string|array $note Text, or [ 'text', 'link_label', 'url' ].
	 * @return string
	 */
	private static function note_html( $note ) {
		$text = is_array( $note ) ? (string) $note['text'] : (string) $note;
		$link = '';

		if ( is_array( $note ) && ! empty( $note['url'] ) && ! empty( $note['link_label'] ) ) {
			$link = ' <a class="dak-link" href="' . esc_url( $note['url'] ) . '" style="color:' . self::COLOR_BRAND . ';text-decoration:underline;">' . esc_html( $note['link_label'] ) . '</a>';
		}

		return '<p class="dak-muted" style="margin:0 0 12px;font-family:' . self::FONT_STACK . ';font-size:14px;line-height:22px;color:' . self::COLOR_MUTED . ';">' . esc_html( $text ) . $link . '</p>';
	}

	/**
	 * Footer: why this email was sent, then the site and its support
	 * contact details (the same ones the website footer shows).
	 *
	 * @param string $footer_note Why the recipient got this email.
	 * @return string
	 */
	private static function footer_html( $footer_note ) {
		$font    = self::FONT_STACK;
		$contact = Site_Footer::support_contact();
		$style   = 'margin:0 0 6px;font-family:' . $font . ';font-size:13px;line-height:20px;color:' . self::COLOR_MUTED . ';text-align:center;';
		$parts   = array( '<a class="dak-link" href="' . esc_url( home_url( '/' ) ) . '" style="color:' . self::COLOR_BRAND . ';text-decoration:none;font-weight:600;">' . esc_html( Site_Footer::BRAND_DOMAIN ) . '</a>' );

		if ( '' !== $contact['phone'] ) {
			$parts[] = '<a class="dak-link" href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $contact['phone'] ) ) . '" style="color:' . self::COLOR_MUTED . ';text-decoration:none;">' . esc_html( $contact['phone'] ) . '</a>';
		}

		if ( '' !== $contact['email'] ) {
			$parts[] = '<a class="dak-link" href="' . esc_url( 'mailto:' . $contact['email'] ) . '" style="color:' . self::COLOR_MUTED . ';text-decoration:none;">' . esc_html( $contact['email'] ) . '</a>';
		}

		$html = '';

		if ( '' !== $footer_note ) {
			$html .= '<p class="dak-muted" style="' . $style . '">' . esc_html( $footer_note ) . '</p>';
		}

		$html .= '<p class="dak-muted" style="' . $style . '">' . implode( ' &nbsp;&middot;&nbsp; ', $parts ) . '</p>';
		$html .= '<p class="dak-muted" style="' . $style . 'margin:0;">' . esc_html( sprintf( '© %1$s %2$s', gmdate( 'Y' ), self::site_name() ) ) . '</p>';

		return $html;
	}

	/**
	 * Builds the plain-text version from the same description.
	 *
	 * @param array $email Normalised email description.
	 * @return string
	 */
	private static function render_text( array $email ) {
		$lines = array();

		$lines[] = self::site_name();
		$lines[] = '';
		$lines[] = $email['heading'];
		$lines[] = str_repeat( '=', min( 60, max( 3, mb_strlen( $email['heading'] ) ) ) );
		$lines[] = '';

		if ( '' !== $email['greeting'] ) {
			$lines[] = $email['greeting'];
			$lines[] = '';
		}

		foreach ( (array) $email['paragraphs'] as $paragraph ) {
			if ( '' !== trim( (string) $paragraph ) ) {
				$lines[] = $paragraph;
				$lines[] = '';
			}
		}

		$details = array_filter(
			(array) $email['details'],
			function ( $row ) {
				return isset( $row[1] ) && '' !== trim( (string) $row[1] );
			}
		);

		if ( ! empty( $details ) ) {
			if ( '' !== $email['details_title'] ) {
				$lines[] = mb_strtoupper( $email['details_title'] );
			}

			foreach ( $details as $row ) {
				$lines[] = $row[0] . ': ' . str_replace( "\n", ', ', (string) $row[1] );
			}

			$lines[] = '';
		}

		if ( '' !== $email['body_text'] ) {
			$lines[] = $email['body_text'];
			$lines[] = '';
		}

		if ( ! empty( $email['button']['url'] ) && ! empty( $email['button']['label'] ) ) {
			$lines[] = $email['button']['label'] . ':';
			$lines[] = $email['button']['url'];
			$lines[] = '';
		}

		foreach ( (array) $email['links'] as $link ) {
			$lines[] = $link[0] . ': ' . $link[1];
		}

		if ( ! empty( $email['links'] ) ) {
			$lines[] = '';
		}

		foreach ( (array) $email['notes'] as $note ) {
			if ( is_array( $note ) ) {
				$lines[] = $note['text'] . ( ! empty( $note['url'] ) ? ' ' . $note['link_label'] . ': ' . $note['url'] : '' );
			} else {
				$lines[] = $note;
			}
		}

		if ( ! empty( $email['notes'] ) ) {
			$lines[] = '';
		}

		$lines[] = '--';

		if ( '' !== $email['footer_note'] ) {
			$lines[] = $email['footer_note'];
		}

		$contact = Site_Footer::support_contact();
		$lines[] = implode( ' · ', array_filter( array( Site_Footer::BRAND_DOMAIN, $contact['phone'], $contact['email'] ) ) );

		return wp_specialchars_decode( implode( "\n", $lines ), ENT_QUOTES );
	}
}
