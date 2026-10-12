<?php
/**
 * "How was your appointment?" review-request emails and their secure links.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Review_Requests
 *
 * After an appointment is marked Completed, the patient is emailed once,
 * a configurable number of hours later (default 2), asking them to review
 * the doctor through the website's existing review system (Doctor_Reviews:
 * one review per patient per doctor, shown on the doctor's profile).
 *
 * Scheduling is durable and idempotent:
 *  - completion stores a due time on the appointment and schedules a
 *    single WP-Cron event for it; the hourly cron also sweeps for anything
 *    due that a lost/missed event left behind;
 *  - sending re-checks eligibility every time and claims a per-appointment
 *    lock (a unique options-table row, so two cron runs can't both send),
 *    and a sent marker means it is never sent twice — whatever repeats
 *    (status re-saves, retries, overlapping cron runs).
 *
 * Links carry a random, single-use token (only its keyed hash is stored),
 * scoped to one appointment and valid for TOKEN_TTL_DAYS. Opening a link
 * never writes anything: the landing request moves the token into a
 * short-lived HttpOnly cookie and redirects to a clean URL (so the token
 * never reaches page analytics or referrers), the doctor's profile page
 * then shows the review form with the chosen rating preselected, and only
 * the patient pressing Submit (a POST, with a nonce) saves the review.
 */
class Review_Requests {

	const OPTION_ENABLED      = 'doctor_ak_notify_review_request';
	const OPTION_DELAY_HOURS  = 'doctor_ak_review_request_delay_hours';
	const OPTION_INCLUDE_AUTO = 'doctor_ak_review_request_include_auto';

	const DEFAULT_DELAY_HOURS = 2;
	const MAX_DELAY_HOURS     = 168;

	const CRON_HOOK = 'doctor_ak_send_review_request';

	const META_DUE           = 'doctor_ak_review_request_due_at';
	const META_SENT          = 'doctor_ak_review_request_sent_at';
	const META_SKIPPED       = 'doctor_ak_review_request_skipped';
	const META_ATTEMPTS      = 'doctor_ak_review_request_attempts';
	const META_TOKEN_HASH    = 'doctor_ak_review_token_hash';
	const META_TOKEN_EXPIRES = 'doctor_ak_review_token_expires';
	const META_TOKEN_USED    = 'doctor_ak_review_token_used_at';

	/**
	 * How an appointment reached Completed — set by Appointments right
	 * before it fires 'doctor_ak_appointment_completed'.
	 */
	const META_COMPLETED_VIA = 'doctor_ak_appointment_completed_via';

	const TOKEN_TTL_DAYS = 30;
	const MAX_ATTEMPTS   = 3;

	const COOKIE         = 'dak_review_token';
	const COOKIE_TTL     = 2 * HOUR_IN_SECONDS;
	const QUERY_TOKEN    = 'review_token';
	const QUERY_RATING   = 'rating';
	const QUERY_STATUS   = 'review_status';
	const SUBMIT_ACTION  = 'doctor_ak_review_request_submit';
	const PANEL_ANCHOR   = 'dak-review-request';

	/**
	 * Whether review-request emails are switched on (Settings → Email
	 * Notifications; default on).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return '1' === get_option( self::OPTION_ENABLED, '1' );
	}

	/**
	 * Hours to wait after completion before emailing.
	 *
	 * @return int
	 */
	public static function delay_hours() {
		$hours = get_option( self::OPTION_DELAY_HOURS, self::DEFAULT_DELAY_HOURS );

		return max( 0, min( self::MAX_DELAY_HOURS, (int) $hours ) );
	}

	/**
	 * Whether appointments the hourly job completed on its own (no doctor
	 * or encounter confirming the visit happened) also get a request.
	 * Off by default: those may be no-shows.
	 *
	 * @return bool
	 */
	public static function include_auto_completed() {
		return '1' === get_option( self::OPTION_INCLUDE_AUTO, '0' );
	}

	/**
	 * 'doctor_ak_appointment_completed' callback: records when the request
	 * is due and schedules it. Re-completing an appointment never schedules
	 * a second email once one has been sent.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function on_completed( $appointment_id ) {
		$appointment_id = (int) $appointment_id;

		if ( $appointment_id < 1 || ! self::is_enabled() || '' !== (string) get_post_meta( $appointment_id, self::META_SENT, true ) ) {
			return;
		}

		$due = time() + self::delay_hours() * HOUR_IN_SECONDS;

		// A previously skipped request (e.g. it was un-completed and is now
		// completed again) gets a fresh evaluation.
		delete_post_meta( $appointment_id, self::META_SKIPPED );
		update_post_meta( $appointment_id, self::META_DUE, $due );

		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $appointment_id ) ) ) {
			wp_schedule_single_event( $due, self::CRON_HOOK, array( $appointment_id ) );
		}
	}

	/**
	 * Single-event callback for one appointment.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return void
	 */
	public static function send_scheduled( $appointment_id ) {
		self::maybe_send( (int) $appointment_id );
	}

	/**
	 * Hourly safety net: sends anything already due that hasn't been sent
	 * or skipped (an event lost to a cron hiccup, a failed send to retry).
	 *
	 * @return void
	 */
	public static function sweep() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$ids = get_posts(
			array(
				'post_type'      => Appointments::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, non-public post type.
					'relation' => 'AND',
					array(
						'key'     => self::META_DUE,
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::META_SENT,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_SKIPPED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $ids as $appointment_id ) {
			self::maybe_send( (int) $appointment_id );
		}
	}

	/**
	 * Sends the request if — checked now, not when it was scheduled — the
	 * appointment is still eligible and nothing has been sent for it yet.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return string 'sent', 'already_sent', 'not_due', 'locked', 'failed', or 'skipped:<reason>'.
	 */
	public static function maybe_send( $appointment_id ) {
		if ( '' !== (string) get_post_meta( $appointment_id, self::META_SENT, true ) ) {
			return 'already_sent';
		}

		// Already decided against (until the appointment is completed again,
		// which clears this — see on_completed()).
		$skipped = (string) get_post_meta( $appointment_id, self::META_SKIPPED, true );

		if ( '' !== $skipped ) {
			return 'skipped:' . $skipped;
		}

		$due = (int) get_post_meta( $appointment_id, self::META_DUE, true );

		if ( $due < 1 || $due > time() ) {
			return 'not_due';
		}

		if ( ! self::claim_lock( $appointment_id ) ) {
			return 'locked';
		}

		// Re-read under the lock — another run may have just finished.
		wp_cache_delete( $appointment_id, 'post_meta' );

		if ( '' !== (string) get_post_meta( $appointment_id, self::META_SENT, true ) ) {
			self::release_lock( $appointment_id );

			return 'already_sent';
		}

		$eligible = self::eligibility( $appointment_id );

		if ( true !== $eligible ) {
			update_post_meta( $appointment_id, self::META_SKIPPED, $eligible );
			self::release_lock( $appointment_id );

			return 'skipped:' . $eligible;
		}

		$appt  = Appointments::notification_data( $appointment_id );
		$token = self::issue_token( $appointment_id );

		if ( Notifications::send_review_request( $appt, $token ) ) {
			update_post_meta( $appointment_id, self::META_SENT, time() );
			self::release_lock( $appointment_id );

			return 'sent';
		}

		$attempts = (int) get_post_meta( $appointment_id, self::META_ATTEMPTS, true ) + 1;

		update_post_meta( $appointment_id, self::META_ATTEMPTS, $attempts );

		// A mail transport that keeps failing shouldn't be retried forever.
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			update_post_meta( $appointment_id, self::META_SKIPPED, 'send_failed' );
		}

		self::release_lock( $appointment_id );

		return 'failed';
	}

	/**
	 * Whether a review request may be sent for the appointment right now.
	 * Only a Completed visit qualifies — never one that is still booked,
	 * cancelled, rescheduled or (being assumed over by the hourly job,
	 * see include_auto_completed()) possibly a no-show.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return true|string True, or the reason it isn't eligible.
	 */
	public static function eligibility( $appointment_id ) {
		if ( ! self::is_enabled() ) {
			return 'disabled';
		}

		$appointment = Appointments::notification_data( $appointment_id );

		if ( empty( $appointment ) ) {
			return 'not_found';
		}

		if ( Appointments::STATUS_COMPLETED !== $appointment['status'] ) {
			return 'not_completed';
		}

		if ( 'auto' === get_post_meta( $appointment_id, self::META_COMPLETED_VIA, true ) && ! self::include_auto_completed() ) {
			return 'auto_completed';
		}

		$patient_id = (int) $appointment['patient_id'];
		$patient    = $patient_id > 0 ? get_userdata( $patient_id ) : false;

		// Reviews belong to a patient account (Doctor_Reviews), so a guest
		// booking has nowhere to put one.
		if ( ! $patient ) {
			return 'no_patient_account';
		}

		if ( ! is_email( $patient->user_email ) ) {
			return 'no_email';
		}

		if ( ! Notifications::user_wants( $patient_id, 'review_request' ) ) {
			return 'opted_out';
		}

		if ( ! Doctor_Reviews::can_review( $patient_id, (int) $appointment['doctor_id'] ) ) {
			return 'cannot_review';
		}

		// One review per patient per doctor — someone who has already
		// reviewed this doctor isn't asked again.
		if ( Doctor_Reviews::find_for_patient( $patient_id, (int) $appointment['doctor_id'] ) ) {
			return 'already_reviewed';
		}

		return true;
	}

	/**
	 * Creates the appointment's review token (replacing any earlier one)
	 * and returns it. Only an HMAC of it is stored.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return string Raw token, for the email links only.
	 */
	public static function issue_token( $appointment_id ) {
		$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe encoding of random bytes.

		update_post_meta( $appointment_id, self::META_TOKEN_HASH, self::hash_token( $token ) );
		update_post_meta( $appointment_id, self::META_TOKEN_EXPIRES, time() + self::TOKEN_TTL_DAYS * DAY_IN_SECONDS );
		delete_post_meta( $appointment_id, self::META_TOKEN_USED );

		return $token;
	}

	/**
	 * Keyed hash of a token, as stored.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	private static function hash_token( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) . '|dak-review' );
	}

	/**
	 * Looks a token up and reports what it may be used for.
	 *
	 * @param string $token Raw token.
	 * @return array { status: 'valid'|'invalid'|'expired'|'used'|'ineligible', appointment_id: int }
	 */
	public static function resolve_token( $token ) {
		$token = (string) $token;

		if ( ! preg_match( '/^[A-Za-z0-9_-]{40,64}$/', $token ) ) {
			return array( 'status' => 'invalid', 'appointment_id' => 0 );
		}

		$hash = self::hash_token( $token );
		$ids  = get_posts(
			array(
				'post_type'      => Appointments::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::META_TOKEN_HASH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed meta_key lookup.
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$appointment_id = $ids ? (int) $ids[0] : 0;

		if ( $appointment_id < 1 || ! hash_equals( (string) get_post_meta( $appointment_id, self::META_TOKEN_HASH, true ), $hash ) ) {
			return array( 'status' => 'invalid', 'appointment_id' => 0 );
		}

		if ( '' !== (string) get_post_meta( $appointment_id, self::META_TOKEN_USED, true ) ) {
			return array( 'status' => 'used', 'appointment_id' => $appointment_id );
		}

		if ( (int) get_post_meta( $appointment_id, self::META_TOKEN_EXPIRES, true ) < time() ) {
			return array( 'status' => 'expired', 'appointment_id' => $appointment_id );
		}

		$appointment = Appointments::notification_data( $appointment_id );

		if ( empty( $appointment ) || Appointments::STATUS_COMPLETED !== $appointment['status']
			|| ! Doctor_Reviews::can_review( (int) $appointment['patient_id'], (int) $appointment['doctor_id'] ) ) {
			return array( 'status' => 'ineligible', 'appointment_id' => $appointment_id );
		}

		return array( 'status' => 'valid', 'appointment_id' => $appointment_id );
	}

	/**
	 * The link for one star (or, with $rating 0, the "Write a review"
	 * button) — the doctor's own profile page, where reviews live.
	 *
	 * @param int    $doctor_id Doctor user ID.
	 * @param string $token     Raw token.
	 * @param int    $rating    1-5 to preselect, or 0.
	 * @return string '' if the doctor profile page can't be found.
	 */
	public static function review_url( $doctor_id, $token, $rating = 0 ) {
		$profile_url = Page_Finder::url_for_shortcode( 'doctor_profile_view' );

		if ( '' === $profile_url ) {
			return '';
		}

		$args = array(
			'doctor_id'       => (int) $doctor_id,
			self::QUERY_TOKEN => $token,
		);

		if ( $rating >= 1 && $rating <= 5 ) {
			$args[ self::QUERY_RATING ] = (int) $rating;
		}

		return add_query_arg( $args, $profile_url );
	}

	/**
	 * 'template_redirect' callback: a review link was opened. Moves the
	 * token into an HttpOnly cookie and redirects to the same page without
	 * it, so the token never sits in the address bar, browser history,
	 * analytics or a Referer header. Writes nothing else — a mail
	 * scanner following the link changes nothing.
	 *
	 * @return void
	 */
	public static function handle_landing() {
		if ( ! isset( $_GET[ self::QUERY_TOKEN ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token itself is the credential, checked on use.
			return;
		}

		$token     = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_TOKEN ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		// Scoped to the doctor in the link, so the review panel only ever
		// shows on that doctor's page.
		self::set_cookie( $doctor_id . '.' . $token, time() + self::COOKIE_TTL );

		$clean = remove_query_arg( array( self::QUERY_TOKEN, self::QUERY_STATUS ) );

		wp_safe_redirect( $clean . '#' . self::PANEL_ANCHOR, 303 );
		exit;
	}

	/**
	 * The token from the review cookie, if it was set for this doctor.
	 *
	 * @param int $doctor_id Doctor the page/request is about.
	 * @return string Token, or ''.
	 */
	private static function cookie_token( $doctor_id ) {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		$parts = explode( '.', $value, 2 );

		if ( 2 !== count( $parts ) || (int) $parts[0] !== (int) $doctor_id || (int) $doctor_id < 1 ) {
			return '';
		}

		return $parts[1];
	}

	/**
	 * Sets (or, with a past expiry, clears) the token cookie.
	 *
	 * @param string $value   Token.
	 * @param int    $expires Unix time.
	 * @return void
	 */
	private static function set_cookie( $value, $expires ) {
		$options = array(
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);

		if ( COOKIE_DOMAIN ) {
			$options['domain'] = COOKIE_DOMAIN;
		}

		setcookie( self::COOKIE, $value, $options ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie -- HttpOnly credential cookie for the review page.
	}

	/**
	 * The review-link state for the doctor profile page being rendered —
	 * null when no review link was opened for this doctor.
	 *
	 * @param int $doctor_id Doctor being viewed.
	 * @return array|null {
	 *     @type string $status         'valid', 'invalid', 'expired', 'used', 'ineligible', 'wrong_account' or 'submitted'.
	 *     @type int    $rating         Preselected rating (1-5) or 0.
	 *     @type array  $appointment    Summary: doctor_name, date_label, visit_label (valid/used only).
	 *     @type bool   $has_review     Whether the patient already has a review of this doctor.
	 *     @type string $error          Submission error code, or ''.
	 * }
	 */
	public static function page_context( $doctor_id ) {
		$token = self::cookie_token( $doctor_id );

		if ( '' === $token ) {
			return null;
		}

		$resolved = self::resolve_token( $token );
		$rating   = isset( $_GET[ self::QUERY_RATING ] ) ? absint( $_GET[ self::QUERY_RATING ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only preselection.
		$flag     = isset( $_GET[ self::QUERY_STATUS ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_STATUS ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.

		$appointment = $resolved['appointment_id'] > 0 ? Appointments::notification_data( $resolved['appointment_id'] ) : array();

		// A link for a different doctor's appointment shows nothing here.
		if ( ! empty( $appointment ) && (int) $appointment['doctor_id'] !== (int) $doctor_id ) {
			return null;
		}

		// Any page that shows the review form must never be cached/shared.
		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$status = $resolved['status'];

		if ( 'used' === $status && 'submitted' === $flag ) {
			$status = 'submitted';
		}

		if ( 'valid' === $status && is_user_logged_in() && get_current_user_id() !== (int) $appointment['patient_id'] ) {
			$status = 'wrong_account';
		}

		$summary = array();

		if ( ! empty( $appointment ) ) {
			$data    = Appointments::notification_data( $resolved['appointment_id'] );
			$summary = array(
				'doctor_name' => $data['doctor_name'],
				'date_label'  => Notifications::date_label( $data ),
				'visit_label' => Notifications::visit_label( $data, false ),
			);
		}

		return array(
			'status'      => $status,
			'rating'      => $rating >= 1 && $rating <= 5 ? $rating : 0,
			'appointment' => $summary,
			'has_review'  => ! empty( $appointment ) && null !== Doctor_Reviews::find_for_patient( (int) $appointment['patient_id'], (int) $appointment['doctor_id'] ),
			'error'       => in_array( $flag, array( 'error_rating', 'error_save', 'error_expired', 'error_session' ), true ) ? $flag : '',
			'action_url'  => admin_url( 'admin-post.php' ),
			'nonce'       => wp_create_nonce( self::SUBMIT_ACTION ),
		);
	}

	/**
	 * admin-post callback (logged in or not): saves the review the patient
	 * submitted from the review form. POST only — following a link can
	 * never get here with a valid request.
	 *
	 * @return void
	 */
	public static function handle_submit() {
		$profile_url = Page_Finder::url_for_shortcode( 'doctor_profile_view' );
		$doctor_id   = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		$rating      = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		$back        = function ( $flag ) use ( $profile_url, $doctor_id, $rating ) {
			$args = array( 'doctor_id' => $doctor_id, self::QUERY_STATUS => $flag );

			if ( $rating >= 1 && $rating <= 5 ) {
				$args[ self::QUERY_RATING ] = $rating;
			}

			wp_safe_redirect( add_query_arg( $args, $profile_url ? $profile_url : home_url( '/' ) ) . '#' . self::PANEL_ANCHOR, 303 );
			exit;
		};

		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
			$back( 'error_session' );
		}

		if ( ! isset( $_POST['_dak_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_dak_review_nonce'] ) ), self::SUBMIT_ACTION ) ) {
			$back( 'error_session' );
		}

		$resolved = self::resolve_token( self::cookie_token( $doctor_id ) );

		if ( 'used' === $resolved['status'] ) {
			$back( 'submitted' );
		}

		if ( 'valid' !== $resolved['status'] ) {
			$back( 'error_expired' );
		}

		$appointment = Appointments::notification_data( $resolved['appointment_id'] );
		$patient_id  = (int) $appointment['patient_id'];

		if ( (int) $appointment['doctor_id'] !== $doctor_id || ( is_user_logged_in() && get_current_user_id() !== $patient_id ) ) {
			$back( 'error_expired' );
		}

		if ( $rating < 1 || $rating > 5 ) {
			$back( 'error_rating' );
		}

		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';

		// One submission per link, even with a double-clicked button.
		if ( ! self::claim_lock( $resolved['appointment_id'], 'submit' ) ) {
			$back( 'submitted' );
		}

		wp_cache_delete( $resolved['appointment_id'], 'post_meta' );

		if ( '' !== (string) get_post_meta( $resolved['appointment_id'], self::META_TOKEN_USED, true ) ) {
			self::release_lock( $resolved['appointment_id'], 'submit' );
			$back( 'submitted' );
		}

		// The same save as the profile page's own review form — same
		// one-per-patient-per-doctor rule, same publishing.
		$saved = Doctor_Reviews::save( $patient_id, $doctor_id, $rating, $comment );

		if ( $saved ) {
			update_post_meta( $resolved['appointment_id'], self::META_TOKEN_USED, time() );
		}

		self::release_lock( $resolved['appointment_id'], 'submit' );
		$back( $saved ? 'submitted' : 'error_save' );
	}

	/**
	 * Claims an exclusive per-appointment lock. Uses the options table's
	 * unique option_name, so the insert either succeeds for exactly one
	 * caller or not at all. A lock left by a crashed run expires after
	 * ten minutes.
	 *
	 * @param int    $appointment_id Appointment post ID.
	 * @param string $purpose        Lock name.
	 * @return bool
	 */
	private static function claim_lock( $appointment_id, $purpose = 'send' ) {
		global $wpdb;

		$name = '_dak_review_' . $purpose . '_lock_' . (int) $appointment_id;

		$inserted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) time() )
		);

		if ( 1 === (int) $inserted ) {
			return true;
		}

		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $since > 0 && $since < time() - 10 * MINUTE_IN_SECONDS ) {
			// Take over a stale lock — only one caller's UPDATE matches.
			$taken = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) time(), $name, (string) $since )
			);

			return 1 === (int) $taken;
		}

		return false;
	}

	/**
	 * Releases a lock from claim_lock().
	 *
	 * @param int    $appointment_id Appointment post ID.
	 * @param string $purpose        Lock name.
	 * @return void
	 */
	private static function release_lock( $appointment_id, $purpose = 'send' ) {
		global $wpdb;

		$wpdb->delete( $wpdb->options, array( 'option_name' => '_dak_review_' . $purpose . '_lock_' . (int) $appointment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
