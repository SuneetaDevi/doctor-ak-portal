<?php
/**
 * CRM email: templates, campaigns to a group of contacts, one-off emails,
 * the log of every email a contact received, and unsubscribing from
 * offers and news.
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
 * Class Crm_Email
 *
 * Every email has a purpose:
 * - "Care message": about the person's own visits, bookings or follow-up
 *   care. Sent to anyone with an email address.
 * - "Offers & news": health camps, new services, clinic news. Carries an
 *   unsubscribe link (and List-Unsubscribe headers); people who
 *   unsubscribed, or whose patient account has announcement emails turned
 *   off, are skipped. This is the same opt-out rule the site already uses
 *   for its announcement emails (see Notifications::user_wants()).
 *
 * Campaigns are queued as one row per recipient and sent in small batches:
 * by the CRM screen while it's open, and by a WP-Cron event otherwise, so
 * nothing is lost if the page is closed. A row is claimed before it's sent,
 * so the two can't send the same email twice; a row left half-sent by a
 * crashed request is marked failed rather than resent.
 *
 * Other emails the plugin sends (booking confirmations, reminders…) are
 * logged against the contact with the same address, subject only (see
 * on_email_sent()), so the history shows everything the person received.
 */
class Crm_Email {

	const T_TEMPLATES = 'dak_crm_templates';
	const T_CAMPAIGNS = 'dak_crm_campaigns';
	const T_MESSAGES  = 'dak_crm_messages';

	const PURPOSE_CARE      = 'care';
	const PURPOSE_MARKETING = 'marketing';

	/** WP-Cron event that sends queued campaign emails. */
	const QUEUE_HOOK = 'doctor_ak_crm_email_queue';

	/** Query parameter of the unsubscribe link. */
	const UNSUB_PARAM = 'dak_crm_unsubscribe';

	const SEEDED_OPTION   = 'dak_crm_templates_seeded';
	const SEGMENTS_OPTION = 'dak_crm_segments';

	/** Most people one campaign can go to. */
	const MAX_RECIPIENTS = 5000;

	/** Rows claimed but not finished after this long are marked failed. */
	const STUCK_MINUTES = 15;

	const MAX_SEGMENTS = 50;

	/**
	 * Set while this class sends, so on_email_sent() doesn't log the same
	 * email twice.
	 *
	 * @var bool
	 */
	private static $sending = false;

	/* ---------------------------------------------------------------------
	 * Vocabulary
	 * ------------------------------------------------------------------- */

	/**
	 * @return array slug => { label, help }
	 */
	public static function purposes() {
		return array(
			self::PURPOSE_CARE      => array(
				'label' => __( 'Care message', 'doctor-ak-portal' ),
				'help'  => __( 'About their own visits, bookings or follow-up care, e.g. a review-visit reminder. Goes to everyone with an email address.', 'doctor-ak-portal' ),
			),
			self::PURPOSE_MARKETING => array(
				'label' => __( 'Offers & news', 'doctor-ak-portal' ),
				'help'  => __( 'Health camps, new services, offers and clinic news. Includes an unsubscribe link. People who unsubscribed, or turned off announcements in their account, are skipped.', 'doctor-ak-portal' ),
			),
		);
	}

	/**
	 * Merge fields usable in the subject, heading and message.
	 *
	 * @return array token => label
	 */
	public static function merge_fields() {
		return array(
			'first_name'       => __( 'First name', 'doctor-ak-portal' ),
			'name'             => __( 'Full name', 'doctor-ak-portal' ),
			'last_visit'       => __( 'Last visit date', 'doctor-ak-portal' ),
			'next_appointment' => __( 'Next appointment', 'doctor-ak-portal' ),
			'clinic_name'      => __( 'Clinic name', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Message statuses (campaign recipients and one-off emails).
	 *
	 * @return array slug => label
	 */
	public static function statuses() {
		return array(
			'queued'    => __( 'Waiting to send', 'doctor-ak-portal' ),
			'sending'   => __( 'Sending', 'doctor-ak-portal' ),
			'sent'      => __( 'Sent', 'doctor-ak-portal' ),
			'failed'    => __( 'Failed', 'doctor-ak-portal' ),
			'skipped'   => __( 'Skipped', 'doctor-ak-portal' ),
			'cancelled' => __( 'Cancelled', 'doctor-ak-portal' ),
		);
	}

	private static function table( $name ) {
		return Crm::table( $name );
	}

	private static function cut( $text, $max ) {
		$text = trim( (string) $text );

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	private static function clinic_name() {
		return (string) get_option( Site_Footer::OPTION_CLINIC_NAME, 'Main Clinic' );
	}

	/* ---------------------------------------------------------------------
	 * Content (shared by templates, campaigns and one-off emails)
	 * ------------------------------------------------------------------- */

	/**
	 * Validates email content.
	 *
	 * @param array $input     purpose, subject, heading, body, button_label, button_url (and name).
	 * @param bool  $need_name Whether a name is required (templates).
	 * @return array|\WP_Error Clean content.
	 */
	public static function clean_content( array $input, $need_name = false ) {
		$get = function ( $key ) use ( $input ) {
			return isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
		};

		$out = array(
			'name'         => self::cut( sanitize_text_field( $get( 'name' ) ), 191 ),
			'purpose'      => $get( 'purpose' ),
			'subject'      => self::cut( sanitize_text_field( $get( 'subject' ) ), 191 ),
			'heading'      => self::cut( sanitize_text_field( $get( 'heading' ) ), 191 ),
			'body'         => self::cut( sanitize_textarea_field( $get( 'body' ) ), 5000 ),
			'button_label' => self::cut( sanitize_text_field( $get( 'button_label' ) ), 60 ),
			'button_url'   => self::cut( esc_url_raw( trim( $get( 'button_url' ) ), array( 'http', 'https' ) ), 500 ),
		);

		if ( $need_name && '' === $out['name'] ) {
			return new \WP_Error( 'doctor_ak_crm_email_name', __( 'Give the template a name, e.g. “Review visit reminder”.', 'doctor-ak-portal' ), array( 'field' => 'name' ) );
		}

		if ( ! isset( self::purposes()[ $out['purpose'] ] ) ) {
			return new \WP_Error( 'doctor_ak_crm_email_purpose', __( 'Choose whether this is a care message or offers & news.', 'doctor-ak-portal' ), array( 'field' => 'purpose' ) );
		}

		if ( '' === $out['subject'] ) {
			return new \WP_Error( 'doctor_ak_crm_email_subject', __( 'Write a subject line.', 'doctor-ak-portal' ), array( 'field' => 'subject' ) );
		}

		if ( '' === trim( $out['body'] ) ) {
			return new \WP_Error( 'doctor_ak_crm_email_body', __( 'Write the message.', 'doctor-ak-portal' ), array( 'field' => 'body' ) );
		}

		if ( '' !== trim( $get( 'button_url' ) ) && '' === $out['button_url'] ) {
			return new \WP_Error( 'doctor_ak_crm_email_url', __( 'Enter a full button link starting with https://.', 'doctor-ak-portal' ), array( 'field' => 'button_url' ) );
		}

		if ( ( '' === $out['button_label'] ) !== ( '' === $out['button_url'] ) ) {
			return new \WP_Error( 'doctor_ak_crm_email_button', __( 'A button needs both a label and a link — fill in both or leave both empty.', 'doctor-ak-portal' ), array( 'field' => '' === $out['button_label'] ? 'button_label' : 'button_url' ) );
		}

		preg_match_all( '/\{([a-z_]+)\}/', $out['subject'] . ' ' . $out['heading'] . ' ' . $out['body'], $found );
		$unknown = array_diff( array_unique( $found[1] ), array_keys( self::merge_fields() ) );

		if ( $unknown ) {
			return new \WP_Error(
				'doctor_ak_crm_email_field',
				sprintf(
					/* translators: 1: unknown fields, 2: allowed fields. */
					__( 'Unknown merge field: %1$s. You can use %2$s.', 'doctor-ak-portal' ),
					'{' . implode( '}, {', $unknown ) . '}',
					'{' . implode( '}, {', array_keys( self::merge_fields() ) ) . '}'
				),
				array( 'field' => 'body' )
			);
		}

		return $out;
	}

	/**
	 * Merge-field values for one contact.
	 *
	 * @param array $contact Contact.
	 * @return array token => value
	 */
	public static function merge_values( array $contact ) {
		$name  = trim( (string) $contact['name'] );
		$first = preg_replace( '/^(mr|mrs|ms|miss|dr)\.?\s+/iu', '', $name );
		$first = (string) strtok( $first, ' ' );

		return array(
			'first_name'       => $first,
			'name'             => $name,
			'last_visit'       => ! empty( $contact['last_visit'] ) ? date_i18n( 'j F Y', strtotime( $contact['last_visit'] ) ) : '',
			'next_appointment' => ! empty( $contact['next_appointment'] ) ? date_i18n( 'l j F, g:i a', strtotime( $contact['next_appointment'] ) ) : '',
			'clinic_name'      => self::clinic_name(),
		);
	}

	private static function apply( $text, array $values ) {
		foreach ( $values as $token => $value ) {
			$text = str_replace( '{' . $token . '}', $value, $text );
		}

		// Tidy what an empty field leaves behind ("on ." / double spaces).
		return trim( preg_replace( '/[ \t]{2,}/', ' ', preg_replace( '/[ \t]+([.,!?])/', '$1', $text ) ) );
	}

	/**
	 * Builds the email for one contact.
	 *
	 * @param array $content Clean content.
	 * @param array $contact Contact.
	 * @param bool  $test    A test copy: the unsubscribe link goes nowhere.
	 * @return array { subject, email (see Email_Template), headers, text }
	 */
	public static function compose( array $content, array $contact, $test = false ) {
		$values     = self::merge_values( $contact );
		$subject    = self::apply( $content['subject'], $values );
		$heading    = '' !== trim( (string) $content['heading'] ) ? self::apply( $content['heading'], $values ) : $subject;
		$body       = self::apply( $content['body'], $values );
		$paragraphs = array_values( array_filter( array_map( 'trim', preg_split( '/\R[ \t]*\R/', $body ) ), 'strlen' ) );
		$marketing  = self::PURPOSE_MARKETING === $content['purpose'];
		$clinic     = self::clinic_name();
		$headers    = array();
		$notes      = array();

		if ( $marketing ) {
			$url = $test ? home_url( '/' ) : self::unsubscribe_url( $contact );

			$notes[] = array(
				'text'       => $test ? __( 'Test copy — the real email’s unsubscribe link works for each person.', 'doctor-ak-portal' ) : __( 'Don’t want offers and news from us?', 'doctor-ak-portal' ),
				'link_label' => __( 'Unsubscribe', 'doctor-ak-portal' ),
				'url'        => $url,
			);

			if ( ! $test ) {
				$headers[] = 'List-Unsubscribe: <' . esc_url_raw( $url ) . '>';
				$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
			}
		}

		return array(
			'subject' => $subject,
			'email'   => array(
				'preheader'   => self::cut( preg_replace( '/\s+/', ' ', isset( $paragraphs[0] ) ? $paragraphs[0] : '' ), 140 ),
				'heading'     => $heading,
				'greeting'    => '' !== $values['first_name'] ? sprintf( /* translators: %s: first name. */ __( 'Hi %s,', 'doctor-ak-portal' ), $values['first_name'] ) : __( 'Hello,', 'doctor-ak-portal' ),
				'paragraphs'  => $paragraphs,
				'button'      => '' !== $content['button_label'] && '' !== $content['button_url'] ? array(
					'label' => $content['button_label'],
					'url'   => $content['button_url'],
				) : array(),
				'notes'       => $notes,
				'footer_note' => $marketing
					/* translators: %s: clinic name. */
					? sprintf( __( 'You’re receiving this because you’ve visited or contacted %s.', 'doctor-ak-portal' ), $clinic )
					/* translators: %s: clinic name. */
					: sprintf( __( 'You’re receiving this from %s about your care with us.', 'doctor-ak-portal' ), $clinic ),
			),
			'headers' => $headers,
			'text'    => $body,
		);
	}

	/**
	 * Example person for previews when no real recipient is at hand.
	 *
	 * @return array Contact-like array.
	 */
	public static function sample_contact() {
		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local dates.

		return array(
			'id'                => 0,
			'user_id'           => 0,
			'name'              => __( 'Sara Ahmed', 'doctor-ak-portal' ),
			'email'             => '',
			'email_norm'        => '',
			'last_visit'        => gmdate( 'Y-m-d', strtotime( '-4 months', $now ) ),
			'next_appointment'  => gmdate( 'Y-m-d 10:30:00', strtotime( '+5 days', $now ) ),
			'marketing_opt_out' => null,
		);
	}

	/**
	 * Rendered HTML for the preview frame.
	 *
	 * @return array { subject, html }
	 */
	public static function preview( array $content, array $contact ) {
		$composed = self::compose( $content, $contact, true );
		$rendered = Email_Template::render( $composed['subject'], $composed['email'] );

		return array(
			'subject' => $composed['subject'],
			'html'    => $rendered['html'],
		);
	}

	private static function deliver( $to, array $composed ) {
		self::$sending = true;
		$ok            = Email_Template::send( $to, $composed['subject'], $composed['email'], array(), $composed['headers'] );
		self::$sending = false;

		return $ok;
	}

	/* ---------------------------------------------------------------------
	 * Templates
	 * ------------------------------------------------------------------- */

	/**
	 * @param bool $archived Include archived templates.
	 * @return array[]
	 */
	public static function templates( $archived = true ) {
		global $wpdb;

		self::maybe_seed_templates();

		$sql  = 'SELECT * FROM ' . self::table( self::T_TEMPLATES ) . ( $archived ? '' : " WHERE status = 'active'" ) . ' ORDER BY status ASC, name ASC';
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name and fixed SQL.

		return array_map( array( __CLASS__, 'shape_template' ), $rows ? $rows : array() );
	}

	public static function find_template( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( self::T_TEMPLATES ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		return $row ? self::shape_template( $row ) : null;
	}

	private static function shape_template( array $row ) {
		$row['id']         = (int) $row['id'];
		$row['updated_by'] = self::user_name( (int) $row['updated_by'] );

		return $row;
	}

	/**
	 * Creates or updates a template.
	 *
	 * @param int   $id      Template ID, 0 for a new one.
	 * @param array $input   Content including name.
	 * @param int   $user_id Acting user.
	 * @return int|\WP_Error Template ID.
	 */
	public static function save_template( $id, array $input, $user_id ) {
		global $wpdb;

		$content = self::clean_content( $input, true );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$now  = current_time( 'mysql' );
		$data = array_merge(
			$content,
			array(
				'updated_by' => (int) $user_id,
				'updated_at' => $now,
			)
		);

		if ( $id ) {
			if ( ! self::find_template( $id ) ) {
				return new \WP_Error( 'doctor_ak_crm_template_missing', __( 'That template no longer exists.', 'doctor-ak-portal' ) );
			}

			$wpdb->update( self::table( self::T_TEMPLATES ), $data, array( 'id' => (int) $id ) );

			return (int) $id;
		}

		$wpdb->insert(
			self::table( self::T_TEMPLATES ),
			array_merge(
				$data,
				array(
					'status'     => 'active',
					'created_by' => (int) $user_id,
					'created_at' => $now,
				)
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Archives or restores a template (templates are never deleted, so past
	 * campaigns still make sense).
	 */
	public static function set_template_status( $id, $active, $user_id ) {
		global $wpdb;

		if ( ! self::find_template( $id ) ) {
			return new \WP_Error( 'doctor_ak_crm_template_missing', __( 'That template no longer exists.', 'doctor-ak-portal' ) );
		}

		$wpdb->update(
			self::table( self::T_TEMPLATES ),
			array(
				'status'     => $active ? 'active' : 'archived',
				'updated_by' => (int) $user_id,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);

		return (int) $id;
	}

	/**
	 * Three starter care templates, matching the CRM's saved views
	 * (inactive patients, possible no-shows, unpaid bookings). Added once;
	 * staff can edit or archive them.
	 */
	private static function maybe_seed_templates() {
		global $wpdb;

		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}

		update_option( self::SEEDED_OPTION, '1', false );

		if ( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( self::T_TEMPLATES ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
			return;
		}

		$book = Page_Finder::url_for_shortcode( 'doctors_directory' );
		$book = $book ? $book : home_url( '/' );

		$seeds = array(
			array(
				'name'    => __( 'Review visit reminder', 'doctor-ak-portal' ),
				'subject' => __( 'Time for your review visit', 'doctor-ak-portal' ),
				'body'    => __( "It has been a while since your last visit on {last_visit}, and your doctor may want to see how you are doing.\n\nYou can choose a time that suits you online, or reply to this email and we will help you book.", 'doctor-ak-portal' ),
			),
			array(
				'name'    => __( 'Missed appointment', 'doctor-ak-portal' ),
				'subject' => __( 'Sorry we missed you', 'doctor-ak-portal' ),
				'body'    => __( "We noticed you weren't able to make your recent appointment. We hope everything is all right.\n\nIf you would still like to be seen, you can book a new time online, or reply to this email and we will help.", 'doctor-ak-portal' ),
			),
			array(
				'name'    => __( 'Unpaid booking', 'doctor-ak-portal' ),
				'subject' => __( 'Your booking isn’t confirmed yet', 'doctor-ak-portal' ),
				'body'    => __( "You started booking an appointment with us, but the payment wasn't completed, so the time isn't confirmed yet.\n\nYou can book again online, or reply to this email if you need help with the payment.", 'doctor-ak-portal' ),
			),
		);

		foreach ( $seeds as $seed ) {
			self::save_template(
				0,
				array_merge(
					$seed,
					array(
						'purpose'      => self::PURPOSE_CARE,
						'heading'      => $seed['subject'],
						'button_label' => __( 'Book an appointment', 'doctor-ak-portal' ),
						'button_url'   => $book,
					)
				),
				0
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Who can receive what
	 * ------------------------------------------------------------------- */

	/** Whether this record itself (or its account) has turned offers & news off. */
	private static function own_opt_out( array $contact ) {
		return ! empty( $contact['marketing_opt_out'] )
			|| ( (int) $contact['user_id'] > 0 && ! Notifications::user_wants( (int) $contact['user_id'], 'announcements' ) );
	}

	/**
	 * Whether offers & news may go to this contact. Unsubscribing belongs to
	 * the email address: if anyone on file with the same address turned
	 * offers & news off, that address doesn't get them.
	 */
	public static function can_market( array $contact ) {
		global $wpdb;

		if ( self::own_opt_out( $contact ) ) {
			return false;
		}

		if ( '' === (string) $contact['email_norm'] ) {
			return true;
		}

		$others = $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, marketing_opt_out FROM ' . Crm::table( Crm::T_CONTACTS ) . ' WHERE email_norm = %s AND id <> %d', $contact['email_norm'], (int) $contact['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		foreach ( $others ? $others : array() as $other ) {
			if ( self::own_opt_out( $other ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Every address that has turned offers & news off (one pass, for
	 * working through a whole group).
	 *
	 * @return array email_norm => true
	 */
	private static function blocked_addresses() {
		global $wpdb;

		$table   = Crm::table( Crm::T_CONTACTS );
		$out     = array_fill_keys( $wpdb->get_col( "SELECT DISTINCT email_norm FROM {$table} WHERE email_norm <> '' AND marketing_opt_out IS NOT NULL" ), true ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$account = $wpdb->get_results( "SELECT user_id, email_norm FROM {$table} WHERE user_id > 0 AND email_norm <> ''", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$account = $account ? $account : array();

		if ( $account && function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'user', wp_list_pluck( $account, 'user_id' ) );
		}

		foreach ( $account as $a ) {
			if ( ! Notifications::user_wants( (int) $a['user_id'], 'announcements' ) ) {
				$out[ $a['email_norm'] ] = true;
			}
		}

		return $out;
	}

	/**
	 * can_market() for working through a group: the blocked addresses are
	 * looked up once and reused.
	 *
	 * @param array      $contact Contact.
	 * @param array|null $blocked Blocked addresses (filled on first use).
	 * @return bool
	 */
	private static function offers_allowed( array $contact, &$blocked ) {
		if ( null === $blocked ) {
			$blocked = self::blocked_addresses();
		}

		return ! isset( $blocked[ (string) $contact['email_norm'] ] ) && ! self::own_opt_out( $contact );
	}

	/**
	 * A contact's offers & news status, for the side panel.
	 *
	 * @return array { allowed, optedOutAt, via, accountOff, sharedOff }
	 */
	public static function marketing_status( array $contact ) {
		$allowed = self::can_market( $contact );

		return array(
			'allowed'    => $allowed,
			'optedOutAt' => (string) $contact['marketing_opt_out'],
			'via'        => (string) $contact['opt_out_via'],
			'accountOff' => (int) $contact['user_id'] > 0 && ! Notifications::user_wants( (int) $contact['user_id'], 'announcements' ),
			// Off only because someone else with this address unsubscribed.
			'sharedOff'  => ! $allowed && ! self::own_opt_out( $contact ),
		);
	}

	/**
	 * Splits contacts into who gets the email and who is skipped (and why).
	 * One email per address: family members sharing an address get one copy.
	 *
	 * @param array[] $contacts Contacts.
	 * @param string  $purpose  Purpose.
	 * @return array { send: contacts, skip: [ [contact, reason], … ], counts: { no_email, unsubscribed, duplicate } }
	 */
	public static function recipients( array $contacts, $purpose ) {
		$send    = array();
		$skip    = array();
		$seen    = array();
		$blocked = null;
		$counts  = array(
			'no_email'     => 0,
			'unsubscribed' => 0,
			'duplicate'    => 0,
		);

		foreach ( $contacts as $c ) {
			$email = (string) $c['email_norm'];

			if ( '' === $email ) {
				$reason = 'no_email';
			} elseif ( self::PURPOSE_MARKETING === $purpose && ! self::offers_allowed( $c, $blocked ) ) {
				$reason = 'unsubscribed';
			} elseif ( isset( $seen[ $email ] ) ) {
				$reason = 'duplicate';
			} else {
				$seen[ $email ] = true;
				$send[]         = $c;
				continue;
			}

			++$counts[ $reason ];
			$skip[] = array( $c, $reason );
		}

		return array(
			'send'   => $send,
			'skip'   => $skip,
			'counts' => $counts,
		);
	}

	private static function skip_reason( $reason ) {
		$reasons = array(
			'no_email'     => __( 'No email address', 'doctor-ak-portal' ),
			'unsubscribed' => __( 'Unsubscribed from offers and news', 'doctor-ak-portal' ),
			'duplicate'    => __( 'Same email as another recipient', 'doctor-ak-portal' ),
			'removed'      => __( 'Contact was removed', 'doctor-ak-portal' ),
		);

		return isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : $reason;
	}

	/**
	 * The contacts a campaign goes to: chosen ones, or everyone matching
	 * the list filters.
	 *
	 * @param array $filters Crm::query() filters (used when $ids is empty).
	 * @param int[] $ids     Chosen contact IDs.
	 * @return array[]|\WP_Error Contacts.
	 */
	public static function audience( array $filters, array $ids ) {
		if ( $ids ) {
			$out = array();

			foreach ( array_slice( array_unique( array_map( 'intval', $ids ) ), 0, 500 ) as $id ) {
				$c = Crm::find( $id );

				if ( $c ) {
					$out[] = $c;
				}
			}

			return $out;
		}

		$out = array();

		for ( $page = 1; ; $page++ ) {
			$result = Crm::query( array_merge( $filters, array( 'page' => $page, 'per_page' => 500, 'sort' => 'created' ) ) );

			if ( $result['total'] > self::MAX_RECIPIENTS ) {
				return new \WP_Error(
					'doctor_ak_crm_email_too_many',
					/* translators: 1: number matching, 2: limit. */
					sprintf( __( '%1$s people match. Narrow the filters to %2$s or fewer per email.', 'doctor-ak-portal' ), number_format_i18n( $result['total'] ), number_format_i18n( self::MAX_RECIPIENTS ) )
				);
			}

			$out = array_merge( $out, $result['rows'] );

			if ( $page >= $result['pages'] ) {
				break;
			}
		}

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Sending
	 * ------------------------------------------------------------------- */

	/**
	 * Sends one email to one contact now (from their side panel).
	 *
	 * @return int|\WP_Error Message ID.
	 */
	public static function send_single( $contact_id, array $input, $user_id ) {
		global $wpdb;

		$contact = Crm::find( $contact_id );
		$content = self::clean_content( $input );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		if ( ! $contact ) {
			return new \WP_Error( 'doctor_ak_crm_missing', __( 'That contact no longer exists.', 'doctor-ak-portal' ) );
		}

		if ( '' === $contact['email_norm'] ) {
			return new \WP_Error( 'doctor_ak_crm_email_none', __( 'This person has no email address.', 'doctor-ak-portal' ) );
		}

		if ( self::PURPOSE_MARKETING === $content['purpose'] && ! self::can_market( $contact ) ) {
			return new \WP_Error( 'doctor_ak_crm_email_unsubscribed', __( 'They’ve unsubscribed from offers and news, so this can’t be sent. Only send a care message if it’s about their own care.', 'doctor-ak-portal' ), array( 'field' => 'purpose' ) );
		}

		$composed = self::compose( $content, $contact );
		$ok       = self::deliver( $contact['email'], $composed );
		$now      = current_time( 'mysql' );

		$wpdb->insert(
			self::table( self::T_MESSAGES ),
			array(
				'contact_id'  => $contact['id'],
				'campaign_id' => 0,
				'kind'        => 'single',
				'purpose'     => $content['purpose'],
				'to_email'    => self::cut( $contact['email'], 191 ),
				'subject'     => self::cut( $composed['subject'], 191 ),
				'body'        => $composed['text'],
				'status'      => $ok ? 'sent' : 'failed',
				'error'       => $ok ? '' : __( 'The mail server didn’t accept it.', 'doctor-ak-portal' ),
				'sent_by'     => (int) $user_id,
				'created_at'  => $now,
				'sent_at'     => $now,
			)
		);

		if ( ! $ok ) {
			return new \WP_Error( 'doctor_ak_crm_email_failed', __( 'The email couldn’t be sent. Check the site’s email settings and try again.', 'doctor-ak-portal' ) );
		}

		Crm::touch( $contact['id'], __( 'Email sent', 'doctor-ak-portal' ) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Sends a test copy to the person writing the email. Not logged.
	 *
	 * @param array      $input   Content.
	 * @param array|null $contact Real recipient to fill the merge fields from, or null for the example.
	 * @param string     $to      Address to send to.
	 * @return true|\WP_Error
	 */
	public static function send_test( array $input, $contact, $to ) {
		$content = self::clean_content( $input );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		if ( ! is_email( $to ) ) {
			return new \WP_Error( 'doctor_ak_crm_email_me', __( 'Your account has no valid email address to send the test to.', 'doctor-ak-portal' ) );
		}

		$composed            = self::compose( $content, $contact ? $contact : self::sample_contact(), true );
		$composed['subject'] = sprintf( /* translators: %s: subject. */ __( '[Test] %s', 'doctor-ak-portal' ), $composed['subject'] );

		return self::deliver( $to, $composed ) ? true : new \WP_Error( 'doctor_ak_crm_email_failed', __( 'The test couldn’t be sent. Check the site’s email settings and try again.', 'doctor-ak-portal' ) );
	}

	/**
	 * Queues a campaign: one row per person (skipped people included, with
	 * the reason, so staff can see who didn't get it and why).
	 *
	 * @param array   $input          Content (name optional; defaults to the subject).
	 * @param array[] $contacts       Audience.
	 * @param string  $audience_label Who it went to, in words.
	 * @param int     $user_id        Acting user.
	 * @return array|\WP_Error { id, total, skipped }
	 */
	public static function create_campaign( array $input, array $contacts, $audience_label, $user_id ) {
		global $wpdb;

		$content = self::clean_content( $input );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$split = self::recipients( $contacts, $content['purpose'] );

		if ( ! $split['send'] ) {
			return new \WP_Error( 'doctor_ak_crm_email_nobody', __( 'No one in this group can receive this email (no email address, or unsubscribed from offers and news).', 'doctor-ak-portal' ) );
		}

		$now = current_time( 'mysql' );

		$wpdb->insert(
			self::table( self::T_CAMPAIGNS ),
			array_merge(
				$content,
				array(
					'name'           => '' !== $content['name'] ? $content['name'] : $content['subject'],
					'template_id'    => isset( $input['template_id'] ) ? (int) $input['template_id'] : 0,
					'audience_label' => self::cut( sanitize_text_field( (string) $audience_label ), 500 ),
					'status'         => 'sending',
					'total'          => count( $split['send'] ),
					'skipped'        => count( $split['skip'] ),
					'created_by'     => (int) $user_id,
					'created_at'     => $now,
					'finished_at'    => null,
				)
			)
		);

		$campaign_id = (int) $wpdb->insert_id;

		if ( ! $campaign_id ) {
			return new \WP_Error( 'doctor_ak_crm_email_save', __( 'The email couldn’t be saved. Please try again.', 'doctor-ak-portal' ) );
		}

		$rows = array();

		foreach ( $split['send'] as $c ) {
			$rows[] = array( $c['id'], $c['email'], 'queued', '' );
		}

		foreach ( $split['skip'] as $pair ) {
			$rows[] = array( $pair[0]['id'], $pair[0]['email'], 'skipped', self::skip_reason( $pair[1] ) );
		}

		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			$values = array();
			$args   = array();

			foreach ( $chunk as $r ) {
				$values[] = '( %d, %d, %s, %s, %s, %s, %s, %s, %d, %s )';
				array_push( $args, (int) $r[0], $campaign_id, 'campaign', $content['purpose'], self::cut( $r[1], 191 ), self::cut( $content['subject'], 191 ), $r[2], $r[3], (int) $user_id, $now );
			}

			$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table( self::T_MESSAGES ) . ' ( contact_id, campaign_id, kind, purpose, to_email, subject, status, error, sent_by, created_at ) VALUES ' . implode( ', ', $values ), $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders only.
		}

		self::schedule_queue();

		return array(
			'id'      => $campaign_id,
			'total'   => count( $split['send'] ),
			'skipped' => count( $split['skip'] ),
		);
	}

	/**
	 * Sends up to $limit queued campaign emails.
	 *
	 * @param int $limit       Emails to send.
	 * @param int $campaign_id Only this campaign (0 = any).
	 * @return int Emails handled.
	 */
	public static function process_queue( $limit, $campaign_id = 0 ) {
		global $wpdb;

		if ( ! Crm::tables_ready() ) {
			return 0;
		}

		$m      = self::table( self::T_MESSAGES );
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::STUCK_MINUTES * MINUTE_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with current_time( 'mysql' ) values.

		// A request that died mid-send: don't risk sending twice.
		$wpdb->query( $wpdb->prepare( "UPDATE {$m} SET status = 'failed', error = %s WHERE status = 'sending' AND sent_at < %s", __( 'Interrupted while sending — it may or may not have arrived.', 'doctor-ak-portal' ), $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		$ids = $campaign_id
			? $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$m} WHERE status = 'queued' AND campaign_id = %d ORDER BY id ASC LIMIT %d", (int) $campaign_id, (int) $limit ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
			: $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$m} WHERE status = 'queued' AND campaign_id > 0 ORDER BY id ASC LIMIT %d", (int) $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		$campaigns = array();
		$handled   = 0;
		$blocked   = null;

		foreach ( $ids as $id ) {
			// Claim the row; if another request got there first, leave it.
			if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE {$m} SET status = 'sending', sent_at = %s WHERE id = %d AND status = 'queued'", current_time( 'mysql' ), (int) $id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
				continue;
			}

			++$handled;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$m} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
			$cid = (int) $row['campaign_id'];

			if ( ! isset( $campaigns[ $cid ] ) ) {
				$campaigns[ $cid ] = self::find_campaign( $cid );
			}

			$campaign = $campaigns[ $cid ];
			$contact  = Crm::find( (int) $row['contact_id'] );
			$result   = array(
				'status' => 'skipped',
				'error'  => '',
			);

			if ( ! $campaign || 'sending' !== $campaign['status'] ) {
				$result = array(
					'status' => 'cancelled',
					'error'  => '',
				);
			} elseif ( ! $contact ) {
				$result['error'] = self::skip_reason( 'removed' );
			} elseif ( '' === $contact['email_norm'] ) {
				$result['error'] = self::skip_reason( 'no_email' );
			} elseif ( self::PURPOSE_MARKETING === $campaign['purpose'] && ! self::offers_allowed( $contact, $blocked ) ) {
				// Unsubscribed after the campaign was queued.
				$result['error'] = self::skip_reason( 'unsubscribed' );
			} else {
				$composed = self::compose( $campaign, $contact );
				$ok       = self::deliver( $contact['email'], $composed );
				$result   = array(
					'status'   => $ok ? 'sent' : 'failed',
					'error'    => $ok ? '' : __( 'The mail server didn’t accept it.', 'doctor-ak-portal' ),
					'subject'  => self::cut( $composed['subject'], 191 ),
					'to_email' => self::cut( $contact['email'], 191 ),
				);
			}

			$result['sent_at'] = current_time( 'mysql' );
			$wpdb->update( $m, $result, array( 'id' => (int) $id ) );
		}

		self::finish_campaigns();

		return $handled;
	}

	/** Marks campaigns with nothing left to send as sent. */
	private static function finish_campaigns() {
		global $wpdb;

		$c = self::table( self::T_CAMPAIGNS );
		$m = self::table( self::T_MESSAGES );

		$wpdb->query( $wpdb->prepare( "UPDATE {$c} SET status = 'sent', finished_at = %s WHERE status = 'sending' AND NOT EXISTS ( SELECT 1 FROM {$m} WHERE {$m}.campaign_id = {$c}.id AND {$m}.status IN ( 'queued', 'sending' ) )", current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names.
	}

	/** Emails still waiting (all campaigns, or one). */
	public static function queued_count( $campaign_id = 0 ) {
		global $wpdb;

		$m = self::table( self::T_MESSAGES );

		return $campaign_id
			? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$m} WHERE status = 'queued' AND campaign_id = %d", (int) $campaign_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
			: (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$m} WHERE status = 'queued' AND campaign_id > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
	}

	/** Makes sure the background sender runs within a minute. */
	public static function schedule_queue() {
		if ( ! wp_next_scheduled( self::QUEUE_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::QUEUE_HOOK );
		}
	}

	/** WP-Cron: send a batch, and come back if more are waiting. */
	public static function run_queue() {
		self::process_queue( 25 );

		if ( self::queued_count() ) {
			self::schedule_queue();
		}
	}

	/* ---------------------------------------------------------------------
	 * Campaigns
	 * ------------------------------------------------------------------- */

	public static function find_campaign( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( self::T_CAMPAIGNS ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.

		return $row ? $row : null;
	}

	/**
	 * Recipients per status for some campaigns.
	 *
	 * @param int[] $ids Campaign IDs.
	 * @return array campaign ID => status => count
	 */
	private static function counts( array $ids ) {
		global $wpdb;

		$out = array();

		foreach ( $ids as $id ) {
			$out[ (int) $id ] = array_fill_keys( array_keys( self::statuses() ), 0 );
		}

		if ( ! $ids ) {
			return $out;
		}

		$rows = $wpdb->get_results( 'SELECT campaign_id, status, COUNT(*) AS n FROM ' . self::table( self::T_MESSAGES ) . ' WHERE campaign_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ') GROUP BY campaign_id, status', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name and integers.

		foreach ( $rows ? $rows : array() as $r ) {
			$out[ (int) $r['campaign_id'] ][ $r['status'] ] = (int) $r['n'];
		}

		return $out;
	}

	private static function shape_campaign( array $c, array $counts ) {
		return array(
			'id'           => (int) $c['id'],
			'name'         => $c['name'],
			'purpose'      => $c['purpose'],
			'subject'      => $c['subject'],
			'heading'      => $c['heading'],
			'body'         => $c['body'],
			'buttonLabel'  => $c['button_label'],
			'buttonUrl'    => $c['button_url'],
			'audience'     => $c['audience_label'],
			'status'       => $c['status'],
			'createdBy'    => self::user_name( (int) $c['created_by'] ),
			'createdAt'    => $c['created_at'],
			'finishedAt'   => (string) $c['finished_at'],
			'counts'       => $counts,
		);
	}

	/**
	 * Latest campaigns.
	 *
	 * @return array { rows, pages }
	 */
	public static function campaigns( $page = 1 ) {
		global $wpdb;

		$per   = 20;
		$page  = max( 1, (int) $page );
		$table = self::table( self::T_CAMPAIGNS );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$rows  = $rows ? $rows : array();
		$all   = self::counts( wp_list_pluck( $rows, 'id' ) );

		return array(
			'rows'  => array_map(
				function ( $c ) use ( $all ) {
					return self::shape_campaign( $c, $all[ (int) $c['id'] ] );
				},
				$rows
			),
			'page'  => $page,
			'pages' => max( 1, (int) ceil( $total / $per ) ),
		);
	}

	/**
	 * One campaign with a page of its recipients.
	 *
	 * @param int    $id     Campaign ID.
	 * @param string $status Only recipients with this status ('' = all).
	 * @param int    $page   Page of recipients.
	 * @return array|null
	 */
	public static function campaign( $id, $status = '', $page = 1 ) {
		global $wpdb;

		$c = self::find_campaign( $id );

		if ( ! $c ) {
			return null;
		}

		$per      = 50;
		$page     = max( 1, (int) $page );
		$m        = self::table( self::T_MESSAGES );
		$contacts = Crm::table( Crm::T_CONTACTS );
		$where    = 'm.campaign_id = %d';
		$args     = array( (int) $id );

		if ( isset( self::statuses()[ $status ] ) ) {
			$where .= ' AND m.status = %s';
			$args[] = $status;
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$m} m WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed SQL.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT m.id, m.contact_id, m.to_email, m.status, m.error, m.sent_at, c.name FROM {$m} m LEFT JOIN {$contacts} c ON c.id = m.contact_id WHERE {$where} ORDER BY m.id ASC LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names and fixed SQL.

		$out               = self::shape_campaign( $c, self::counts( array( (int) $id ) )[ (int) $id ] );
		$out['recipients'] = array_map(
			function ( $r ) {
				return array(
					'contactId' => (int) $r['contact_id'],
					'name'      => null !== $r['name'] ? $r['name'] : __( '(removed)', 'doctor-ak-portal' ),
					'email'     => $r['to_email'],
					'status'    => $r['status'],
					'error'     => $r['error'],
					'sentAt'    => (string) $r['sent_at'],
				);
			},
			$rows ? $rows : array()
		);
		$out['page']       = $page;
		$out['pages']      = max( 1, (int) ceil( $total / $per ) );

		return $out;
	}

	/**
	 * Stops a campaign: emails not sent yet are cancelled.
	 */
	public static function cancel_campaign( $id ) {
		global $wpdb;

		$c = self::find_campaign( $id );

		if ( ! $c ) {
			return new \WP_Error( 'doctor_ak_crm_campaign_missing', __( 'That email no longer exists.', 'doctor-ak-portal' ) );
		}

		$wpdb->update( self::table( self::T_MESSAGES ), array( 'status' => 'cancelled' ), array( 'campaign_id' => (int) $id, 'status' => 'queued' ) );

		if ( 'sending' === $c['status'] ) {
			$wpdb->update(
				self::table( self::T_CAMPAIGNS ),
				array(
					'status'      => 'cancelled',
					'finished_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $id )
			);
		}

		return (int) $id;
	}

	/**
	 * Queues failed emails of a campaign again.
	 *
	 * @return int|\WP_Error Emails queued again.
	 */
	public static function retry_failed( $id ) {
		global $wpdb;

		if ( ! self::find_campaign( $id ) ) {
			return new \WP_Error( 'doctor_ak_crm_campaign_missing', __( 'That email no longer exists.', 'doctor-ak-portal' ) );
		}

		$n = (int) $wpdb->update(
			self::table( self::T_MESSAGES ),
			array(
				'status' => 'queued',
				'error'  => '',
			),
			array(
				'campaign_id' => (int) $id,
				'status'      => 'failed',
			)
		);

		if ( $n ) {
			$wpdb->update(
				self::table( self::T_CAMPAIGNS ),
				array(
					'status'      => 'sending',
					'finished_at' => null,
				),
				array( 'id' => (int) $id )
			);
			self::schedule_queue();
		}

		return $n;
	}

	/* ---------------------------------------------------------------------
	 * History
	 * ------------------------------------------------------------------- */

	/**
	 * 'doctor_ak_email_sent': logs other emails the plugin sends against
	 * the contact(s) with that address — subject only.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param bool   $sent    Whether the mailer accepted it.
	 */
	public static function on_email_sent( $to, $subject, $sent ) {
		global $wpdb;

		if ( self::$sending || ! Crm::tables_ready() ) {
			return;
		}

		$email = Crm::norm_email( $to );

		if ( '' === $email ) {
			return;
		}

		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Crm::table( Crm::T_CONTACTS ) . ' WHERE email_norm = %s ORDER BY id ASC LIMIT 3', $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$now = current_time( 'mysql' );

		foreach ( $ids as $id ) {
			$wpdb->insert(
				self::table( self::T_MESSAGES ),
				array(
					'contact_id'  => (int) $id,
					'campaign_id' => 0,
					'kind'        => 'system',
					'purpose'     => self::PURPOSE_CARE,
					'to_email'    => self::cut( $to, 191 ),
					'subject'     => self::cut( $subject, 191 ),
					'body'        => null,
					'status'      => $sent ? 'sent' : 'failed',
					'error'       => '',
					'sent_by'     => 0,
					'created_at'  => $now,
					'sent_at'     => $now,
				)
			);
		}
	}

	/**
	 * A contact's emails as timeline items (see Crm::timeline()).
	 *
	 * @return array[]
	 */
	public static function timeline_items( $contact_id ) {
		global $wpdb;

		$m        = self::table( self::T_MESSAGES );
		$c        = self::table( self::T_CAMPAIGNS );
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT m.*, c.name AS campaign_name FROM {$m} m LEFT JOIN {$c} c ON c.id = m.campaign_id WHERE m.contact_id = %d ORDER BY m.id DESC LIMIT 100", (int) $contact_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names.
		$purposes = self::purposes();
		$statuses = self::statuses();
		$items    = array();

		foreach ( $rows ? $rows : array() as $r ) {
			$system = 'system' === $r['kind'];
			$detail = array();

			if ( ! $system ) {
				$detail[] = isset( $purposes[ $r['purpose'] ] ) ? $purposes[ $r['purpose'] ]['label'] : $r['purpose'];
			}

			if ( 'sent' !== $r['status'] ) {
				$detail[] = ( isset( $statuses[ $r['status'] ] ) ? $statuses[ $r['status'] ] : $r['status'] ) . ( '' !== $r['error'] ? ': ' . $r['error'] : '' );
			}

			if ( $r['campaign_name'] ) {
				/* translators: %s: campaign name. */
				$detail[] = sprintf( __( 'Group email “%s”', 'doctor-ak-portal' ), $r['campaign_name'] );
			}

			if ( (int) $r['sent_by'] ) {
				/* translators: %s: name. */
				$detail[] = sprintf( __( 'by %s', 'doctor-ak-portal' ), self::user_name( (int) $r['sent_by'] ) );
			}

			$items[] = array(
				'at'     => $r['sent_at'] ? $r['sent_at'] : $r['created_at'],
				'type'   => 'email',
				/* translators: %s: subject. */
				'title'  => sprintf( $system ? __( 'Automatic email: %s', 'doctor-ak-portal' ) : __( 'Email: %s', 'doctor-ak-portal' ), $r['subject'] ),
				'detail' => implode( ' · ', $detail ),
				'meta'   => array(
					'campaign_id' => (int) $r['campaign_id'],
					'body'        => 'single' === $r['kind'] ? (string) $r['body'] : '',
				),
			);
		}

		return $items;
	}

	/* ---------------------------------------------------------------------
	 * Unsubscribing from offers and news
	 * ------------------------------------------------------------------- */

	private static function token( array $contact ) {
		return substr( hash_hmac( 'sha256', 'crm-unsubscribe|' . (int) $contact['id'] . '|' . $contact['email_norm'], wp_salt( 'auth' ) ), 0, 24 );
	}

	public static function unsubscribe_url( array $contact ) {
		return add_query_arg( self::UNSUB_PARAM, (int) $contact['id'] . '-' . self::token( $contact ), home_url( '/' ) );
	}

	/**
	 * Turns offers & news off (or on again) for a contact. Turning it off
	 * also turns off announcement emails on their patient account; turning
	 * it on only clears the CRM's own flag — if they switched announcements
	 * off in their account, only they can switch it back on.
	 *
	 * @param int    $contact_id Contact.
	 * @param bool   $out        Unsubscribe (true) or allow again.
	 * @param string $via        'link' or 'staff'.
	 * @param int    $user_id    Acting user (0 for the link).
	 * @return array|\WP_Error Contact.
	 */
	public static function set_opt_out( $contact_id, $out, $via, $user_id ) {
		$contact = Crm::find( $contact_id );

		if ( ! $contact ) {
			return new \WP_Error( 'doctor_ak_crm_missing', __( 'That contact no longer exists.', 'doctor-ak-portal' ) );
		}

		if ( $out ) {
			if ( empty( $contact['marketing_opt_out'] ) ) {
				Crm::update_row(
					$contact['id'],
					array(
						'marketing_opt_out' => current_time( 'mysql' ),
						'opt_out_via'       => 'link' === $via ? 'link' : 'staff',
					)
				);
			}

			if ( $contact['user_id'] > 0 ) {
				update_user_meta( $contact['user_id'], 'doctor_ak_notify_announcements', '0' );
			}

			Crm::log( $contact['id'], $user_id, 'consent', 'link' === $via ? __( 'Unsubscribed from offers and news (link in an email).', 'doctor-ak-portal' ) : __( 'Marked as not wanting offers and news.', 'doctor-ak-portal' ) );
		} else {
			Crm::update_row(
				$contact['id'],
				array(
					'marketing_opt_out' => null,
					'opt_out_via'       => '',
				)
			);
			Crm::log( $contact['id'], $user_id, 'consent', __( 'Offers and news emails allowed again.', 'doctor-ak-portal' ) );
		}

		return Crm::find( $contact['id'] );
	}

	/**
	 * 'init': the unsubscribe link. Opening it asks for confirmation (so a
	 * mail scanner following links can't unsubscribe anyone); confirming —
	 * or a mail app's one-click POST (RFC 8058) — unsubscribes.
	 */
	public static function handle_unsubscribe() {
		if ( ! isset( $_GET[ self::UNSUB_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the check.
			return;
		}

		nocache_headers();

		$raw     = sanitize_text_field( wp_unslash( $_GET[ self::UNSUB_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the check.
		$parts   = explode( '-', $raw, 2 );
		$contact = Crm::tables_ready() && 2 === count( $parts ) ? Crm::find( (int) $parts[0] ) : null;
		$clinic  = esc_html( self::clinic_name() );
		$title   = sprintf( /* translators: %s: clinic name. */ __( 'Email preferences — %s', 'doctor-ak-portal' ), self::clinic_name() );
		$home    = '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Go to the website', 'doctor-ak-portal' ) . '</a></p>';

		if ( ! $contact || '' === $contact['email_norm'] || ! hash_equals( self::token( $contact ), $parts[1] ) ) {
			wp_die(
				'<h1>' . esc_html__( 'This link doesn’t work', 'doctor-ak-portal' ) . '</h1><p>' . esc_html__( 'It may be incomplete or out of date. Reply to any of our emails and we’ll unsubscribe you.', 'doctor-ak-portal' ) . '</p>' . $home, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( $title ),
				array( 'response' => 400 )
			);
		}

		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );

		if ( $is_post ) {
			self::set_opt_out( $contact['id'], true, 'link', 0 );

			wp_die(
				'<h1>' . esc_html__( 'You’re unsubscribed', 'doctor-ak-portal' ) . '</h1><p>' . sprintf( /* translators: %s: clinic name. */ esc_html__( 'You won’t get offers and news emails from %s any more. You’ll still get emails about your own appointments and care.', 'doctor-ak-portal' ), $clinic ) . '</p>' . $home, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( $title ),
				array( 'response' => 200 )
			);
		}

		if ( ! self::can_market( $contact ) ) {
			wp_die(
				'<h1>' . esc_html__( 'You’re already unsubscribed', 'doctor-ak-portal' ) . '</h1><p>' . sprintf( /* translators: %s: clinic name. */ esc_html__( 'You don’t get offers and news emails from %s. You’ll still get emails about your own appointments and care.', 'doctor-ak-portal' ), $clinic ) . '</p>' . $home, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( $title ),
				array( 'response' => 200 )
			);
		}

		$action = add_query_arg( self::UNSUB_PARAM, rawurlencode( $raw ), home_url( '/' ) );

		wp_die(
			'<h1>' . esc_html__( 'Unsubscribe from offers and news?', 'doctor-ak-portal' ) . '</h1>'
				. '<p>' . sprintf( /* translators: 1: email address, 2: clinic name. */ esc_html__( '%1$s will stop getting offers and news emails from %2$s. Emails about your own appointments and care will still arrive.', 'doctor-ak-portal' ), '<strong>' . esc_html( self::mask( $contact['email'] ) ) . '</strong>', $clinic ) . '</p>'
				. '<form method="post" action="' . esc_url( $action ) . '"><p><button type="submit" class="button button-large">' . esc_html__( 'Unsubscribe', 'doctor-ak-portal' ) . '</button></p></form>'
				. $home, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_html( $title ),
			array( 'response' => 200 )
		);
	}

	/** "s***@example.com". */
	private static function mask( $email ) {
		$at = strpos( (string) $email, '@' );

		return false === $at ? '' : substr( $email, 0, 1 ) . str_repeat( '*', max( 2, $at - 1 ) ) . substr( $email, $at );
	}

	/* ---------------------------------------------------------------------
	 * Saved segments (named sets of list filters)
	 * ------------------------------------------------------------------- */

	/** Filter keys a segment may hold. */
	public static function segment_keys() {
		return array( 'q', 'stage', 'source', 'doctor', 'owner', 'tag', 'date_from', 'date_to', 'last_visit_from', 'last_visit_to', 'sort' );
	}

	/**
	 * @return array[] { id, name, view, filters }
	 */
	public static function segments() {
		$list = get_option( self::SEGMENTS_OPTION, array() );

		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * Saves the current view and filters under a name.
	 *
	 * @return array|\WP_Error All segments.
	 */
	public static function save_segment( $name, $view, array $filters, $user_id ) {
		$name = self::cut( sanitize_text_field( (string) $name ), 60 );
		$list = self::segments();

		if ( '' === $name ) {
			return new \WP_Error( 'doctor_ak_crm_segment_name', __( 'Give the segment a name, e.g. “Diabetic patients, no visit in 6 months”.', 'doctor-ak-portal' ) );
		}

		foreach ( $list as $s ) {
			if ( 0 === strcasecmp( $s['name'], $name ) ) {
				return new \WP_Error( 'doctor_ak_crm_segment_taken', __( 'A segment with this name already exists. Choose another name.', 'doctor-ak-portal' ) );
			}
		}

		if ( count( $list ) >= self::MAX_SEGMENTS ) {
			return new \WP_Error( 'doctor_ak_crm_segment_full', __( 'There are already 50 saved segments. Delete one you no longer use first.', 'doctor-ak-portal' ) );
		}

		$clean = array();

		foreach ( self::segment_keys() as $key ) {
			if ( isset( $filters[ $key ] ) && '' !== (string) $filters[ $key ] ) {
				$clean[ $key ] = sanitize_text_field( (string) $filters[ $key ] );
			}
		}

		$list[] = array(
			'id'         => substr( md5( uniqid( '', true ) ), 0, 10 ),
			'name'       => $name,
			'view'       => isset( Crm::views()[ $view ] ) ? $view : 'all',
			'filters'    => $clean,
			'created_by' => (int) $user_id,
		);

		update_option( self::SEGMENTS_OPTION, $list, false );

		return self::segments();
	}

	/**
	 * @return array All segments.
	 */
	public static function delete_segment( $id ) {
		$list = array_values(
			array_filter(
				self::segments(),
				function ( $s ) use ( $id ) {
					return $s['id'] !== $id;
				}
			)
		);

		update_option( self::SEGMENTS_OPTION, $list, false );

		return $list;
	}

	private static function user_name( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;

		return $user ? $user->display_name : '';
	}
}
