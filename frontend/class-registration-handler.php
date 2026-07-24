<?php
/**
 * Handles doctor/patient registration submissions.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Authentication;
use DoctorAKPortal\Includes\Profile_Picture_Uploader;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Specialization_Request;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Registration_Handler
 *
 * Backs the [doctor_register] shortcode. All state changes happen over
 * AJAX so the form never reloads the page; the shortcode template only
 * renders markup and delegates behaviour to assets/js/doctor-ak-registration.js.
 */
class Registration_Handler {

	/**
	 * Shared nonce action name used for every AJAX call this handler serves.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_register';

	/**
	 * Authentication service.
	 *
	 * @var Authentication
	 */
	private $authentication;

	/**
	 * Profile picture upload service.
	 *
	 * @var Profile_Picture_Uploader
	 */
	private $profile_picture_uploader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Authentication            $authentication           Authentication service.
	 * @param Profile_Picture_Uploader $profile_picture_uploader Upload service.
	 */
	public function __construct( Authentication $authentication, Profile_Picture_Uploader $profile_picture_uploader ) {
		$this->authentication           = $authentication;
		$this->profile_picture_uploader = $profile_picture_uploader;
	}

	/**
	 * Enqueues registration assets only on pages containing [doctor_register].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_registration_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-registration',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-registration.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-registration.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-password-toggle',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-password-toggle.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-password-toggle.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-registration',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-registration.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-registration.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-registration',
			'dakRegister',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * AJAX handler: uploads a guest-submitted profile picture immediately,
	 * ahead of full form submission, so the form can preview it.
	 *
	 * @return void
	 */
	public function handle_profile_picture_upload() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( empty( $_FILES['profile_picture'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was received.', 'doctor-ak-portal' ) ) );
		}

		$attachment_id = $this->profile_picture_uploader->upload( $_FILES['profile_picture'] );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			)
		);
	}

	/**
	 * AJAX handler: records a "can't find your specialization?" request.
	 *
	 * @return void
	 */
	public function handle_specialization_request() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$specialization_name = isset( $_POST['specialization_name'] ) ? sanitize_text_field( wp_unslash( $_POST['specialization_name'] ) ) : '';
		$requester_email     = isset( $_POST['requester_email'] ) ? sanitize_email( wp_unslash( $_POST['requester_email'] ) ) : '';

		$result = Specialization_Request::create( $specialization_name, $requester_email );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Thanks! Your request has been submitted for review.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: validates and processes the full registration form.
	 *
	 * @return void
	 */
	public function handle_register() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You are already logged in.', 'doctor-ak-portal' ) ) );
		}

		$register_as = isset( $_POST['register_as'] ) ? sanitize_key( wp_unslash( $_POST['register_as'] ) ) : '';

		if ( ! in_array( $register_as, array( Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose whether you are registering as a doctor or a patient.', 'doctor-ak-portal' ) ) );
		}

		$errors = array();

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
		$confirm    = isset( $_POST['confirm_password'] ) ? (string) $_POST['confirm_password'] : '';

		if ( '' === $first_name ) {
			$errors['first_name'] = __( 'First name is required.', 'doctor-ak-portal' );
		}

		if ( '' === $last_name ) {
			$errors['last_name'] = __( 'Last name is required.', 'doctor-ak-portal' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$errors['email'] = __( 'Please provide a valid email address.', 'doctor-ak-portal' );
		} elseif ( email_exists( $email ) ) {
			$errors['email'] = __( 'An account with that email address already exists.', 'doctor-ak-portal' );
		}

		if ( strlen( $password ) < 8 ) {
			$errors['password'] = __( 'Password must be at least 8 characters long.', 'doctor-ak-portal' );
		} elseif ( $password !== $confirm ) {
			$errors['confirm_password'] = __( 'Passwords do not match.', 'doctor-ak-portal' );
		}

		$meta = Roles::DOCTOR_ROLE === $register_as
			? $this->validate_doctor_fields( $errors )
			: $this->validate_patient_fields( $errors );

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		$username = $this->generate_unique_username( $first_name, $last_name, $email );

		$userdata = array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
		);

		$user_id = $this->authentication->register_user( $userdata, $register_as );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		foreach ( $meta as $meta_key => $meta_value ) {
			update_user_meta( $user_id, $meta_key, $meta_value );
		}

		if ( ! empty( $meta['doctor_ak_profile_picture_id'] ) ) {
			$this->profile_picture_uploader->claim( $meta['doctor_ak_profile_picture_id'], $user_id );
		}

		if ( Roles::DOCTOR_ROLE === $register_as ) {
			// Doctor accounts start locked out — Authentication::login() blocks
			// them until an admin approves the request (see the "Doctor
			// Requests" tab on the admin dashboard).
			update_user_meta( $user_id, 'doctor_ak_registration_status', 'pending' );

			/**
			 * Fires once a new doctor account is created, still pending admin
			 * approval. Notifications/Notification_Center hook this to alert
			 * every administrator.
			 *
			 * @param int $user_id New doctor's user ID.
			 */
			do_action( 'doctor_ak_doctor_registered', $user_id );

			wp_send_json_success(
				array(
					'message' => __( 'Your account has been created and is now awaiting admin approval. We will email you once it has been approved.', 'doctor-ak-portal' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Your account has been created successfully. You can now log in.', 'doctor-ak-portal' ),
			)
		);
	}

	/**
	 * Validates and sanitizes doctor-only fields.
	 *
	 * @param array $errors Reference to the accumulating error list.
	 * @return array Meta key/value pairs ready to save on success.
	 */
	private function validate_doctor_fields( array &$errors ) {
		$meta = array();

		$years_experience = isset( $_POST['years_experience'] ) && '' !== $_POST['years_experience']
			? absint( wp_unslash( $_POST['years_experience'] ) )
			: null;

		if ( null === $years_experience || $years_experience > 80 ) {
			$errors['years_experience'] = __( 'Please provide a valid number of years of experience.', 'doctor-ak-portal' );
		} else {
			$meta['doctor_ak_years_experience'] = $years_experience;
		}

		$qualification = isset( $_POST['qualification'] ) ? sanitize_text_field( wp_unslash( $_POST['qualification'] ) ) : '';

		if ( '' === $qualification ) {
			$errors['qualification'] = __( 'Please provide your qualification(s), e.g. MBBS, FCPS.', 'doctor-ak-portal' );
		} else {
			$meta['doctor_ak_qualification'] = $qualification;
		}

		// Optional — a free-text field for anything not already covered by
		// the structured specialization list (e.g. a niche procedure or
		// interest area).
		$expertise = isset( $_POST['expertise'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expertise'] ) ) : '';

		if ( '' !== $expertise ) {
			$meta['doctor_ak_expertise'] = $expertise;
		}

		$specializations = array();

		if ( isset( $_POST['specializations'] ) && is_array( $_POST['specializations'] ) ) {
			foreach ( wp_unslash( $_POST['specializations'] ) as $slug ) {
				$slug = sanitize_key( $slug );

				if ( Specializations::is_valid( $slug ) ) {
					$specializations[] = $slug;
				}
			}
		}

		if ( empty( $specializations ) ) {
			$errors['specializations'] = __( 'Please select at least one specialization.', 'doctor-ak-portal' );
		} else {
			$meta['doctor_ak_specializations'] = $specializations;
		}

		if ( ! empty( $_POST['profile_picture_id'] ) ) {
			$attachment_id = absint( $_POST['profile_picture_id'] );

			if ( ! $this->profile_picture_uploader->is_unclaimed_image( $attachment_id ) ) {
				$errors['profile_picture'] = __( 'Please upload your profile picture again.', 'doctor-ak-portal' );
			} else {
				$meta['doctor_ak_profile_picture_id'] = $attachment_id;
			}
		}

		return $meta;
	}

	/**
	 * Generates a unique username from the doctor/patient's name (falling
	 * back to their email's local part), since the registration form no
	 * longer collects one directly — login happens by email instead.
	 *
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param string $email      Validated email address.
	 * @return string
	 */
	private function generate_unique_username( $first_name, $last_name, $email ) {
		$base = sanitize_user( strtolower( trim( $first_name . '.' . $last_name, '.' ) ), true );

		if ( '' === $base ) {
			$email_local = substr( $email, 0, (int) strpos( $email, '@' ) );
			$base        = sanitize_user( $email_local, true );
		}

		if ( '' === $base ) {
			$base = 'user';
		}

		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			++$suffix;
			$username = $base . $suffix;
		}

		return $username;
	}

	/**
	 * Validates and sanitizes patient-only fields.
	 *
	 * @param array $errors Reference to the accumulating error list.
	 * @return array Meta key/value pairs ready to save on success.
	 */
	private function validate_patient_fields( array &$errors ) {
		$meta = array();

		$phone_number = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';

		if ( '' === $phone_number ) {
			$errors['phone_number'] = __( 'Phone number is required.', 'doctor-ak-portal' );
		} elseif ( ! preg_match( '/^[0-9+\-\s()]{7,20}$/', $phone_number ) ) {
			$errors['phone_number'] = __( 'Please provide a valid phone number.', 'doctor-ak-portal' );
		} else {
			$meta['doctor_ak_phone_number'] = $phone_number;
		}

		return $meta;
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * registration shortcode.
	 *
	 * @return bool
	 */
	private function is_registration_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, 'doctor_register' );
	}
}
