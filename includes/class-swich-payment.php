<?php
/**
 * Swich PayIn gateway integration for paid online video consultations.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Swich_Payment
 *
 * Wires the "Landing Page / PWA (GET)" redirect flow described in Swich's
 * PayIn integration guide: a patient booking a video consultation is sent
 * to a Swich-hosted payment page, Swich charges them there, and then:
 *  - calls our `doctor_ak_swich_callback` endpoint server-to-server with a
 *    checksum-signed result (the authoritative source of truth), and
 *  - redirects the patient's browser back to `doctor_ak_swich_return`
 *    (only on success), which double-checks via the Inquire API in case
 *    the callback hasn't landed yet.
 *
 * Credentials and the flat per-consultation fee are stored as options,
 * editable from Settings → Swich Payments (Admin\Swich_Settings), with an
 * optional constant override for sites that prefer wp-config.php secrets.
 */
class Swich_Payment {

	const OPTION_ENVIRONMENT    = 'doctor_ak_swich_environment';
	const OPTION_CLIENT_ID      = 'doctor_ak_swich_client_id';
	const OPTION_CLIENT_SECRET  = 'doctor_ak_swich_client_secret';
	const OPTION_PWA_SECRET_KEY = 'doctor_ak_swich_pwa_secret_key';
	const OPTION_FEE            = 'doctor_ak_swich_video_fee';

	const META_TRANSACTION_ID = 'doctor_ak_appointment_swich_transaction_id';
	const META_ORDER_ID       = 'doctor_ak_appointment_swich_order_id';

	const TOKEN_TRANSIENT = 'doctor_ak_swich_bearer_token_';

	/**
	 * Filter callback for `doctor_ak_appointment_requires_payment`: both
	 * clinic (onsite) and video appointments only require payment right away
	 * when the patient explicitly chose "Pay Now" over "Pay Later" on the
	 * booking page — a video consultation can be booked unpaid too, staying
	 * "Pending Payment" (no Join Call link, see Appointments::video_call_info())
	 * until the patient pays from their own dashboard.
	 *
	 * @param bool  $requires_payment Default value from Appointments::create().
	 * @param array $data             Raw data passed to Appointments::create(), including 'payment_choice' ('now'|'later').
	 * @return bool
	 */
	public static function requires_payment( $requires_payment, array $data ) {
		if ( ! self::is_configured() ) {
			return $requires_payment;
		}

		$charge = isset( $data['charge'] ) ? (float) $data['charge'] : 0.0;

		if ( $charge <= 0 ) {
			return false;
		}

		return isset( $data['payment_choice'] ) && 'now' === $data['payment_choice'];
	}

	/**
	 * Whether the minimum settings needed to charge a patient are present.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$config = self::config();

		return '' !== $config['client_id'] && '' !== $config['pwa_secret_key'];
	}

	/**
	 * Builds the Swich "Landing Page / PWA (GET)" URL a patient should be
	 * redirected to in order to pay for a pending-payment appointment.
	 *
	 * @param int $appointment_id Appointment post ID (must have status pending_payment).
	 * @return string|\WP_Error Redirect URL, or an error if unable to build one.
	 */
	public static function build_payment_url( $appointment_id ) {
		if ( ! self::is_configured() ) {
			return new \WP_Error( 'doctor_ak_swich_not_configured', __( 'Online payment is not set up yet. Please contact the site administrator.', 'doctor-ak-portal' ) );
		}

		$appointment = Appointments::find( $appointment_id );

		if ( empty( $appointment ) ) {
			return new \WP_Error( 'doctor_ak_swich_invalid_appointment', __( 'Appointment not found.', 'doctor-ak-portal' ) );
		}

		$payer_name  = '' !== $appointment['guest_name'] ? $appointment['guest_name'] : self::patient_name( $appointment['patient_id'] );
		$payer_email = '' !== $appointment['guest_email'] ? $appointment['guest_email'] : self::patient_email( $appointment['patient_id'] );
		$raw_phone   = '' !== $appointment['guest_phone'] ? $appointment['guest_phone'] : self::patient_phone( $appointment['patient_id'] );
		$payer_phone = self::normalize_msisdn( $raw_phone );

		if ( '' === $payer_phone ) {
			return new \WP_Error( 'doctor_ak_swich_missing_phone', __( 'A valid mobile number (e.g. 03xxxxxxxxx) is required for online payment.', 'doctor-ak-portal' ) );
		}

		$config                  = self::config();
		$customer_transaction_id = 'DAK' . $appointment_id . '-' . substr( uniqid(), -8 );

		update_post_meta( $appointment_id, self::META_TRANSACTION_ID, $customer_transaction_id );

		$item     = '' !== $appointment['service_name'] ? $appointment['service_name'] : 'VideoConsultation';
		$amount   = number_format( (float) $appointment['charge'], 2, '.', '' );
		$checksum = hash_hmac( 'sha256', 'Swich:' . $customer_transaction_id . ':' . $item . ':' . $amount, $config['pwa_secret_key'] );

		$params = array(
			'clientId'              => $config['client_id'],
			'customerTransactionId' => $customer_transaction_id,
			'item'                  => $item,
			'amount'                => $amount,
			'channel'               => 0,
			'description'           => sprintf( 'Online video consultation appointment #%d', $appointment_id ),
			'PayeeName'             => $payer_name,
			'Email'                 => $payer_email,
			'MSISDN'                => $payer_phone,
			'currency'              => 'PKR',
			'checksum'              => $checksum,
			'successRedirectUrl'    => self::return_url( $appointment_id, $customer_transaction_id ),
		);

		return self::pwa_landing_url() . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * AJAX callback (wp_ajax[_nopriv]_doctor_ak_swich_callback): Swich's
	 * server-to-server notification once a PayIn transaction settles.
	 * Registered for both logged-in and guest traffic since Swich, not a
	 * site visitor, calls this endpoint.
	 *
	 * @return void
	 */
	public static function handle_callback() {
		$status                  = isset( $_GET['Status'] ) ? sanitize_text_field( wp_unslash( $_GET['Status'] ) ) : '';
		$order_id                = isset( $_GET['OrderId'] ) ? sanitize_text_field( wp_unslash( $_GET['OrderId'] ) ) : '';
		$customer_transaction_id = isset( $_GET['CustomerTransactionId'] ) ? sanitize_text_field( wp_unslash( $_GET['CustomerTransactionId'] ) ) : '';
		$amount                  = isset( $_GET['Amount'] ) ? sanitize_text_field( wp_unslash( $_GET['Amount'] ) ) : '';
		$checksum                = isset( $_GET['Checksum'] ) ? sanitize_text_field( wp_unslash( $_GET['Checksum'] ) ) : '';

		$config = self::config();

		if ( '' === $customer_transaction_id || '' === $checksum || '' === $config['pwa_secret_key'] ) {
			status_header( 400 );
			wp_send_json( array( 'status' => 'failed' ) );
		}

		$expected = hash_hmac( 'sha256', 'SWCallback:' . $customer_transaction_id . ':' . $order_id . ':' . $amount . ':' . $status, $config['pwa_secret_key'] );

		if ( ! hash_equals( $expected, $checksum ) ) {
			status_header( 401 );
			wp_send_json( array( 'status' => 'failed' ) );
		}

		$appointment_id = self::find_appointment_id( $customer_transaction_id );

		if ( $appointment_id ) {
			update_post_meta( $appointment_id, self::META_ORDER_ID, $order_id );

			if ( 'success' === strtolower( $status ) ) {
				Appointments::mark_paid( $appointment_id );
			}
		}

		wp_send_json( array( 'status' => 'success' ) );
	}

	/**
	 * Parses the request's raw query string for the return endpoint,
	 * normalizing "&amp;" back to "&" first.
	 *
	 * Swich's hosted payment page appears to build this redirect through
	 * server-side templating that HTML-encodes the URL instead of decoding
	 * it before navigating the browser, so the actual query string this
	 * endpoint receives can literally contain "&amp;" instead of "&" —
	 * which makes PHP's normal $_GET parsing fold "appointment_id" into a
	 * garbled "amp;appointment_id" key. Re-parsing the raw string after
	 * normalizing works around that on our side.
	 *
	 * @return array
	 */
	private static function return_query_params() {
		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed via parse_str() below, then every value is read individually with its own sanitizer.
		$query_string = str_replace( '&amp;', '&', $query_string );

		$query_params = array();
		parse_str( $query_string, $query_params );

		return $query_params;
	}

	/**
	 * AJAX callback (wp_ajax[_nopriv]_doctor_ak_swich_return): where the
	 * patient's browser lands after a successful payment on Swich's page.
	 * The callback above is the source of truth, but since it can arrive
	 * slightly after the browser redirect, this re-checks via the Inquire
	 * API as a fallback before telling the patient their payment succeeded.
	 *
	 * @return void
	 */
	public static function handle_return() {
		$query_params = self::return_query_params();
		$appointment_id = isset( $query_params['appointment_id'] ) ? absint( $query_params['appointment_id'] ) : 0;
		$appointment     = $appointment_id ? Appointments::find( $appointment_id ) : array();

		if ( empty( $appointment ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Doctor AK Portal: Swich return could not find appointment_id=%d. Raw query string: %s', $appointment_id, isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			wp_die( esc_html__( 'Appointment not found.', 'doctor-ak-portal' ) );
		}

		if ( Appointments::PAYMENT_STATUS_PENDING === $appointment['payment_status'] ) {
			$customer_transaction_id = get_post_meta( $appointment_id, self::META_TRANSACTION_ID, true );
			$inquiry                 = self::inquire( $customer_transaction_id );

			if ( ! is_wp_error( $inquiry ) && isset( $inquiry['transaction']['transactionStatus'] ) && 'success' === $inquiry['transaction']['transactionStatus'] ) {
				Appointments::mark_paid( $appointment_id );
				$appointment['payment_status'] = Appointments::PAYMENT_STATUS_PAID;
			}
		}

		$paid = Appointments::PAYMENT_STATUS_PAID === $appointment['payment_status'];

		// A guest booking has no account, so there's no dashboard for them
		// to land on or check back at — send them home instead, and show
		// the appointment details right here since this page is the only
		// record of it they'll see.
		$is_guest = 0 === (int) $appointment['patient_id'];

		if ( $is_guest ) {
			$continue_url   = home_url( '/' );
			$continue_label = __( 'Return to Home', 'doctor-ak-portal' );
		} else {
			$dashboard_url  = Page_Finder::url_for_shortcode( 'patient_dashboard' );
			$continue_url   = '' !== $dashboard_url ? $dashboard_url : home_url( '/' );
			$continue_label = __( 'Go to Dashboard', 'doctor-ak-portal' );
		}

		$heading = $paid
			? __( 'Payment received', 'doctor-ak-portal' )
			: __( "We're still confirming your payment", 'doctor-ak-portal' );

		if ( $is_guest ) {
			$message = $paid
				? __( 'Your online video consultation has been confirmed. Details are below — please save or screenshot this page, as it will not be emailed again.', 'doctor-ak-portal' )
				: __( "We haven't received confirmation from the payment gateway yet. If the amount was deducted, this will update shortly. Please save this page and check back, or contact us with the details below if it doesn't update.", 'doctor-ak-portal' );
		} else {
			$message = $paid
				? __( 'Your online video consultation has been confirmed. You can view it from your dashboard.', 'doctor-ak-portal' )
				: __( "We haven't received confirmation from the payment gateway yet. If the amount was deducted, this will update shortly — please check your dashboard in a few minutes.", 'doctor-ak-portal' );
		}

		$details_html = '';

		if ( $is_guest ) {
			$doctor      = $appointment['doctor_id'] ? get_userdata( $appointment['doctor_id'] ) : false;
			$doctor_name = $doctor ? trim( $doctor->first_name . ' ' . $doctor->last_name ) : '';
			$doctor_name = '' !== $doctor_name ? $doctor_name : ( $doctor ? $doctor->display_name : __( 'Unknown Doctor', 'doctor-ak-portal' ) );

			$details_html = sprintf(
				'<table style="width:100%%;border-collapse:collapse;margin:0 0 24px;text-align:left;">
					<tr><td style="padding:6px 0;color:#666;">%1$s</td><td style="padding:6px 0;font-weight:600;text-align:right;">%2$s</td></tr>
					<tr><td style="padding:6px 0;color:#666;">%3$s</td><td style="padding:6px 0;font-weight:600;text-align:right;">%4$s</td></tr>
					<tr><td style="padding:6px 0;color:#666;">%5$s</td><td style="padding:6px 0;font-weight:600;text-align:right;">%6$s</td></tr>
					<tr><td style="padding:6px 0;color:#666;">%7$s</td><td style="padding:6px 0;font-weight:600;text-align:right;">%8$s</td></tr>
				</table>',
				esc_html__( 'Doctor', 'doctor-ak-portal' ),
				esc_html( sprintf( 'Dr. %s', $doctor_name ) ),
				esc_html__( 'Date', 'doctor-ak-portal' ),
				esc_html( $appointment['date'] ),
				esc_html__( 'Time', 'doctor-ak-portal' ),
				esc_html( $appointment['time'] ),
				esc_html__( 'Reference', 'doctor-ak-portal' ),
				esc_html( '#' . $appointment['id'] )
			);
		}

		wp_die(
			sprintf(
				'<div style="font-family:sans-serif;max-width:520px;margin:80px auto;text-align:center;">
					<h1 style="margin-bottom:8px;">%1$s</h1>
					<p style="color:#555;margin-bottom:24px;">%2$s</p>
					%3$s
					<a href="%4$s" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;">%5$s</a>
				</div>',
				esc_html( $heading ),
				esc_html( $message ),
				$details_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from esc_html()-wrapped pieces.
				esc_url( $continue_url ),
				esc_html( $continue_label )
			),
			esc_html( $heading ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Calls the Inquire API for a given transaction's current status.
	 *
	 * @param string $customer_transaction_id Our system-generated transaction id.
	 * @return array|\WP_Error Decoded response body, or an error.
	 */
	public static function inquire( $customer_transaction_id ) {
		if ( '' === $customer_transaction_id ) {
			return new \WP_Error( 'doctor_ak_swich_missing_transaction', __( 'Missing transaction reference.', 'doctor-ak-portal' ) );
		}

		$token = self::get_bearer_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = add_query_arg(
			array( 'CustomerTransactionId' => rawurlencode( $customer_transaction_id ) ),
			self::api_base_url() . '/gateway/payin/v2.0/inquire'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : array();
	}

	/**
	 * Normalizes a loosely-formatted phone number to Swich's preferred
	 * "03xxxxxxxxx" MSISDN format, returning an empty string if it can't be.
	 *
	 * @param string $phone Raw phone number (may include +92, spaces, dashes).
	 * @return string
	 */
	public static function normalize_msisdn( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );

		if ( '' === $digits ) {
			return '';
		}

		if ( 0 === strpos( $digits, '92' ) && 12 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 2 );
		} elseif ( 0 !== strpos( $digits, '0' ) && 10 === strlen( $digits ) ) {
			$digits = '0' . $digits;
		}

		return (bool) preg_match( '/^03\d{9}$/', $digits ) ? $digits : '';
	}

	/**
	 * Resolves the request-time bearer token for server-to-server API
	 * calls (Inquire, etc.), caching it for its stated lifetime.
	 *
	 * @return string|\WP_Error
	 */
	private static function get_bearer_token() {
		$config        = self::config();
		$transient_key = self::TOKEN_TRANSIENT . ( self::is_sandbox() ? 'sandbox' : 'production' );
		$cached        = get_transient( $transient_key );

		if ( $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			self::auth_url(),
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $config['client_id'],
					'client_secret' => $config['client_secret'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new \WP_Error( 'doctor_ak_swich_auth_failed', __( 'Unable to authenticate with the payment gateway.', 'doctor-ak-portal' ) );
		}

		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;
		set_transient( $transient_key, $body['access_token'], max( 60, $expires_in - 60 ) );

		return $body['access_token'];
	}

	/**
	 * Looks up an appointment by its stored Swich customer transaction id.
	 *
	 * @param string $customer_transaction_id Transaction id.
	 * @return int Appointment post ID, or 0 if not found.
	 */
	private static function find_appointment_id( $customer_transaction_id ) {
		$query = new \WP_Query(
			array(
				'post_type'      => Appointments::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => self::META_TRANSACTION_ID,
				'meta_value'     => $customer_transaction_id,
				'fields'         => 'ids',
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * The URL Swich redirects the patient's browser back to after a
	 * successful payment. Built by us (not templated from Swich's own
	 * redirect), so its query args are ones we control and trust.
	 *
	 * @param int    $appointment_id          Appointment post ID.
	 * @param string $customer_transaction_id Transaction id.
	 * @return string
	 */
	private static function return_url( $appointment_id, $customer_transaction_id ) {
		return add_query_arg(
			array(
				'action'         => 'doctor_ak_swich_return',
				'appointment_id' => $appointment_id,
				't'              => $customer_transaction_id,
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * @param int $patient_id Logged-in patient's user ID, or 0 for guests.
	 * @return string
	 */
	private static function patient_name( $patient_id ) {
		$user = $patient_id > 0 ? get_userdata( $patient_id ) : false;

		if ( ! $user ) {
			return '';
		}

		$name = trim( $user->first_name . ' ' . $user->last_name );

		return '' !== $name ? $name : $user->display_name;
	}

	/**
	 * @param int $patient_id Logged-in patient's user ID, or 0 for guests.
	 * @return string
	 */
	private static function patient_email( $patient_id ) {
		$user = $patient_id > 0 ? get_userdata( $patient_id ) : false;

		return $user ? $user->user_email : '';
	}

	/**
	 * @param int $patient_id Logged-in patient's user ID, or 0 for guests.
	 * @return string
	 */
	private static function patient_phone( $patient_id ) {
		return $patient_id > 0 ? get_user_meta( $patient_id, 'doctor_ak_phone_number', true ) : '';
	}

	/**
	 * Reads the gateway's current settings, preferring a same-named
	 * wp-config.php constant (DOCTOR_AK_SWICH_*) over the stored option.
	 *
	 * @return array
	 */
	public static function config() {
		return array(
			'environment'    => self::setting( self::OPTION_ENVIRONMENT, 'DOCTOR_AK_SWICH_ENVIRONMENT', 'sandbox' ),
			'client_id'      => self::setting( self::OPTION_CLIENT_ID, 'DOCTOR_AK_SWICH_CLIENT_ID', '' ),
			'client_secret'  => self::setting( self::OPTION_CLIENT_SECRET, 'DOCTOR_AK_SWICH_CLIENT_SECRET', '' ),
			'pwa_secret_key' => self::setting( self::OPTION_PWA_SECRET_KEY, 'DOCTOR_AK_SWICH_PWA_SECRET_KEY', '' ),
			'fee'            => self::setting( self::OPTION_FEE, 'DOCTOR_AK_SWICH_VIDEO_FEE', '500' ),
		);
	}

	/**
	 * @param string $option_name   wp_options key.
	 * @param string $constant_name wp-config.php constant name that overrides it.
	 * @param mixed  $default       Fallback when neither is set.
	 * @return mixed
	 */
	private static function setting( $option_name, $constant_name, $default ) {
		if ( defined( $constant_name ) ) {
			return constant( $constant_name );
		}

		return get_option( $option_name, $default );
	}

	/**
	 * @return bool
	 */
	private static function is_sandbox() {
		return 'production' !== self::config()['environment'];
	}

	/**
	 * @return string
	 */
	private static function auth_url() {
		return self::is_sandbox() ? 'https://sandbox-auth.swichnow.com/connect/token' : 'https://auth.swichnow.com/connect/token';
	}

	/**
	 * @return string
	 */
	private static function api_base_url() {
		return self::is_sandbox() ? 'https://sandbox-api.swichnow.com' : 'https://api.swichnow.com';
	}

	/**
	 * @return string
	 */
	private static function pwa_landing_url() {
		return self::is_sandbox() ? 'https://sandbox-payin-pwa.swichnow.com/' : 'https://payin-pwa.swichnow.com/';
	}
}
