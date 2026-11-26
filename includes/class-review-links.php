<?php
/**
 * Shareable "leave a review" links, one per doctor.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Review_Links
 *
 * An admin copies a doctor's review link from Dashboard → Reviews and sends
 * it to anyone (WhatsApp, SMS, a QR code at the clinic). Opening it shows a
 * review form on that doctor's profile — no account or appointment needed.
 *
 * Because anyone holding the link can use it, every review sent this way is
 * saved as 'pending' and only appears on the profile (and in its rating)
 * once an admin publishes it (Doctor_Reviews). Resetting a doctor's link
 * makes the old one stop working, e.g. if it has spread somewhere unwanted.
 *
 * Spam checks on submit: a hidden honeypot field, a signed form timestamp
 * (a form sent within MIN_SECONDS of loading is a bot), and a per-IP cap.
 * The IP itself is never stored — only a keyed hash, in a short transient.
 */
class Review_Links {

	const META_TOKEN    = 'doctor_ak_review_link_token';
	const QUERY_TOKEN   = 'review_link';
	const QUERY_STATUS  = 'review_status';
	const SUBMIT_ACTION = 'doctor_ak_review_link_submit';
	const HONEYPOT      = 'dak_website';

	const MIN_SECONDS     = 3;
	const RATE_LIMIT      = 20;
	const RATE_WINDOW     = HOUR_IN_SECONDS;

	/**
	 * The doctor's current link token, created on first use.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return string
	 */
	public static function token( $doctor_id ) {
		$token = (string) get_user_meta( (int) $doctor_id, self::META_TOKEN, true );

		if ( ! preg_match( '/^[A-Za-z0-9_-]{16,64}$/', $token ) ) {
			$token = self::reset( $doctor_id );
		}

		return $token;
	}

	/**
	 * Replaces the doctor's link token — the old link stops working.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return string New token.
	 */
	public static function reset( $doctor_id ) {
		$token = rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe encoding of random bytes.

		update_user_meta( (int) $doctor_id, self::META_TOKEN, $token );

		return $token;
	}

	/**
	 * The shareable link: the doctor's profile, opened at the review form.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return string '' if the doctor profile page can't be found.
	 */
	public static function url( $doctor_id ) {
		// The doctor's own page when published, else the old ?doctor_id= URL.
		$profile_url = Doctor_Pages::url( $doctor_id, array( self::QUERY_TOKEN => self::token( $doctor_id ) ) );

		return '' !== $profile_url ? $profile_url . '#' . Review_Requests::PANEL_ANCHOR : '';
	}

	/**
	 * Whether a token is the doctor's current one.
	 *
	 * @param int    $doctor_id Doctor user ID.
	 * @param string $token     Token from the request.
	 * @return bool
	 */
	public static function is_valid( $doctor_id, $token ) {
		$stored = (string) get_user_meta( (int) $doctor_id, self::META_TOKEN, true );

		return '' !== $stored && '' !== (string) $token && hash_equals( $stored, (string) $token );
	}

	/**
	 * The review-link state for the doctor profile being rendered — null
	 * when the page wasn't opened from a review link.
	 *
	 * @param int $doctor_id Doctor being viewed.
	 * @return array|null {
	 *     @type string $status     'open', 'submitted' or 'invalid'.
	 *     @type string $error      Error code from a failed submit, or ''.
	 *     @type string $token      The link token (for the form).
	 *     @type string $stamp      Signed form timestamp.
	 *     @type string $action_url Form action.
	 * }
	 */
	public static function page_context( $doctor_id ) {
		if ( ! isset( $_GET[ self::QUERY_TOKEN ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is checked below.
			return null;
		}

		$token = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_TOKEN ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flag  = isset( $_GET[ self::QUERY_STATUS ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_STATUS ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.

		// The form carries a fresh timestamp, so the page must never be cached.
		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$valid  = self::is_valid( $doctor_id, $token );
		$status = $valid ? 'open' : 'invalid';

		if ( $valid && 'link_submitted' === $flag ) {
			$status = 'submitted';
		}

		$errors = array( 'link_error_rating', 'link_error_name', 'link_error_spam', 'link_error_limit', 'link_error_save' );

		return array(
			'status'     => $status,
			'error'      => in_array( $flag, $errors, true ) ? $flag : '',
			'token'      => $valid ? $token : '',
			'stamp'      => self::stamp( $doctor_id ),
			'action_url' => admin_url( 'admin-post.php' ),
		);
	}

	/**
	 * A signed "form loaded at" timestamp.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return string
	 */
	private static function stamp( $doctor_id ) {
		$time = time();

		return $time . '.' . self::sign( $time . '|' . (int) $doctor_id );
	}

	/**
	 * Keyed hash for stamps and IP keys.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function sign( $value ) {
		return substr( hash_hmac( 'sha256', (string) $value, wp_salt( 'nonce' ) . '|dak-review-link' ), 0, 32 );
	}

	/**
	 * Seconds since the form was loaded, or -1 if the stamp is forged.
	 *
	 * @param string $stamp     Stamp from the form.
	 * @param int    $doctor_id Doctor user ID.
	 * @return int
	 */
	private static function stamp_age( $stamp, $doctor_id ) {
		$parts = explode( '.', (string) $stamp, 2 );

		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! hash_equals( self::sign( $parts[0] . '|' . (int) $doctor_id ), $parts[1] ) ) {
			return -1;
		}

		return time() - (int) $parts[0];
	}

	/**
	 * Counts this submission against the sender's IP; false once over the cap.
	 *
	 * @return bool
	 */
	private static function within_rate_limit() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( '' === $ip ) {
			return true;
		}

		$key   = 'dak_rl_' . self::sign( 'ip|' . $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );

		return true;
	}

	/**
	 * admin-post callback (logged in or not): saves a review sent from a
	 * review link as 'pending'. POST only.
	 *
	 * @return void
	 */
	public static function handle_submit() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form; the link token, a signed timestamp, a honeypot and a rate limit guard it, and nothing is published without an admin.
		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		$token     = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$rating    = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
		$name      = isset( $_POST['reviewer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['reviewer_name'] ) ) : '';
		$contact   = isset( $_POST['reviewer_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['reviewer_contact'] ) ) : '';
		$comment   = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
		$honeypot  = isset( $_POST[ self::HONEYPOT ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::HONEYPOT ] ) ) : '';
		$stamp     = isset( $_POST['stamp'] ) ? sanitize_text_field( wp_unslash( $_POST['stamp'] ) ) : '';
		// phpcs:enable

		$back = function ( $flag ) use ( $doctor_id, $token ) {
			$profile_url = Doctor_Pages::url(
				$doctor_id,
				array(
					self::QUERY_TOKEN  => $token,
					self::QUERY_STATUS => $flag,
				)
			);

			wp_safe_redirect( ( '' !== $profile_url ? $profile_url : home_url( '/' ) ) . '#' . Review_Requests::PANEL_ANCHOR, 303 );
			exit;
		};

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$doctor = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( 'POST' !== $method || ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) || ! self::is_valid( $doctor_id, $token ) ) {
			// A bad or reset link: back to the page, which explains it.
			$back( 'link_invalid' );
		}

		// A bot filled the hidden field or sent the form impossibly fast:
		// it is told it worked (so it learns nothing) and nothing is saved.
		$age = self::stamp_age( $stamp, $doctor_id );

		if ( '' !== $honeypot || ( $age >= 0 && $age < self::MIN_SECONDS ) ) {
			$back( 'link_submitted' );
		}

		// A missing or tampered timestamp — not something a real form sends.
		if ( $age < 0 ) {
			$back( 'link_error_spam' );
		}

		if ( $rating < 1 || $rating > 5 ) {
			$back( 'link_error_rating' );
		}

		if ( mb_strlen( trim( $name ) ) < 2 ) {
			$back( 'link_error_name' );
		}

		if ( ! self::within_rate_limit() ) {
			$back( 'link_error_limit' );
		}

		$review_id = Doctor_Reviews::add_from_link( $doctor_id, $name, $contact, $rating, $comment );

		if ( $review_id < 1 ) {
			$back( 'link_error_save' );
		}

		/**
		 * A review arrived from a doctor's review link and awaits approval.
		 *
		 * @param int $review_id Review row ID.
		 * @param int $doctor_id Doctor user ID.
		 * @param int $rating    1-5.
		 */
		do_action( 'doctor_ak_review_link_submitted', $review_id, $doctor_id, $rating );

		$back( 'link_submitted' );
	}
}
