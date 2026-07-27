<?php
/**
 * Backs the [doctor_profile] shortcode (shared by doctors and patients).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Doctor_Awards;
use DoctorAKPortal\Includes\Locations;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Profile_Picture_Uploader;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Profile_Handler
 *
 * Unlike registration, the visitor here already has a WordPress account, so
 * profile picture uploads are attached to (and saved against) the real user
 * immediately, and password changes are optional and require the current
 * password. Field validation mirrors Registration_Handler's per-role rules.
 */
class Profile_Handler {

	/**
	 * Nonce action shared by every AJAX endpoint in this handler.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_profile';

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Profile picture upload service.
	 *
	 * @var Profile_Picture_Uploader
	 */
	private $profile_picture_uploader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader          $template_loader          Template loader.
	 * @param Profile_Picture_Uploader $profile_picture_uploader Upload service.
	 */
	public function __construct( Template_Loader $template_loader, Profile_Picture_Uploader $profile_picture_uploader ) {
		$this->template_loader          = $template_loader;
		$this->profile_picture_uploader = $profile_picture_uploader;
	}

	/**
	 * Enqueues profile assets only on pages containing [doctor_profile].
	 *
	 * Reuses the registration page's stylesheet/script for the multi-select,
	 * availability rows and toggle switch it already implements, rather than
	 * duplicating that code.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_profile_page() ) {
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

		wp_enqueue_style(
			'doctor-ak-portal-profile',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-profile.css',
			array( 'doctor-ak-portal-registration' ),
			Assets::version( 'assets/css/doctor-ak-profile.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-city-area-select',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-city-area-select.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-city-area-select.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-registration',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-registration.js',
			array( 'doctor-ak-portal-city-area-select' ),
			Assets::version( 'assets/js/doctor-ak-registration.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-awards-editor',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-awards-editor.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-awards-editor.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-profile',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-profile.js',
			array( 'doctor-ak-portal-registration', 'doctor-ak-portal-awards-editor', 'doctor-ak-portal-city-area-select' ),
			Assets::version( 'assets/js/doctor-ak-profile.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-profile',
			'dakProfile',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'locations' => Locations::get_all(),
			)
		);
	}

	/**
	 * Renders the shortcode: an access-denied state, or the profile editor.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return $this->template_loader->get_template(
				'dashboard/access-denied.php',
				array(
					'reason'    => 'logged_out',
					'login_url' => Page_Finder::url_for_shortcode( 'doctor_login' ),
				)
			);
		}

		$user      = wp_get_current_user();
		$is_doctor = in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true );
		$role      = $is_doctor ? Roles::DOCTOR_ROLE : Roles::PATIENT_ROLE;

		if ( ! Role_Permissions::is_tab_allowed( $role, 'profile' ) ) {
			return $this->template_loader->get_template(
				'dashboard/access-denied.php',
				array(
					'reason'        => 'not_permitted',
					'dashboard_url' => Page_Finder::url_for_shortcode( $is_doctor ? 'doctor_dashboard' : 'patient_dashboard' ),
				)
			);
		}

		$form_context = self::build_form_context( $user );

		return $this->template_loader->get_template(
			'profile/edit-profile.php',
			array(
				'profile_form_html' => $this->template_loader->get_template( 'profile/profile-form.php', $form_context ),
				'dashboard_url'      => Page_Finder::url_for_shortcode( $is_doctor ? 'doctor_dashboard' : 'patient_dashboard' ),
			)
		);
	}

	/**
	 * Builds the variables templates/profile/profile-form.php needs, from a
	 * user's current meta. Shared by this handler's standalone [doctor_profile]
	 * page and the doctor dashboard's in-page "Profile" tab, so both render
	 * the exact same form instead of drifting apart. Clinic location and
	 * video-consultation availability are managed from the dashboard's
	 * Clinics tab instead (see Clinics), not here.
	 *
	 * @param \WP_User $user User whose profile is being edited.
	 * @return array
	 */
	public static function build_form_context( \WP_User $user ) {
		$is_doctor = in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true );

		return array(
			'user'                       => $user,
			'is_doctor'                  => $is_doctor,
			'specializations'            => Specializations::get_all(),
			'current_specializations'    => (array) get_user_meta( $user->ID, 'doctor_ak_specializations', true ),
			'current_years_experience'   => get_user_meta( $user->ID, 'doctor_ak_years_experience', true ),
			'current_qualification'      => get_user_meta( $user->ID, 'doctor_ak_qualification', true ),
			'current_country'            => get_user_meta( $user->ID, 'doctor_ak_country', true ),
			'current_city'               => get_user_meta( $user->ID, 'doctor_ak_city', true ),
			'current_area'               => get_user_meta( $user->ID, 'doctor_ak_area', true ),
			'current_short_description'  => get_user_meta( $user->ID, 'doctor_ak_short_description', true ),
			'current_expertise'          => get_user_meta( $user->ID, 'doctor_ak_expertise', true ),
			'current_awards'             => Doctor_Awards::get_for_doctor( $user->ID ),
			'current_phone_number'       => get_user_meta( $user->ID, 'doctor_ak_phone_number', true ),
			'current_profile_picture_id' => (int) get_user_meta( $user->ID, 'doctor_ak_profile_picture_id', true ),
		);
	}

	/**
	 * AJAX handler: uploads a new profile picture for the logged-in user
	 * and saves it immediately (unlike registration, there is no separate
	 * "claim" step — the user already exists).
	 *
	 * @return void
	 */
	public function handle_upload_profile_picture() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'doctor-ak-portal' ) ), 401 );
		}

		if ( empty( $_FILES['profile_picture'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was received.', 'doctor-ak-portal' ) ) );
		}

		$user_id       = get_current_user_id();
		$attachment_id = $this->profile_picture_uploader->upload( $_FILES['profile_picture'], $user_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		update_user_meta( $user_id, 'doctor_ak_profile_picture_id', $attachment_id );

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			)
		);
	}

	/**
	 * AJAX handler: validates and saves profile changes, including an
	 * optional password change.
	 *
	 * @return void
	 */
	public function handle_update_profile() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'doctor-ak-portal' ) ), 401 );
		}

		$user      = wp_get_current_user();
		$is_doctor = in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true );

		if ( ! Role_Permissions::is_tab_allowed( $is_doctor ? Roles::DOCTOR_ROLE : Roles::PATIENT_ROLE, 'profile' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off profile editing for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$errors = array();

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( '' === $first_name ) {
			$errors['first_name'] = __( 'First name is required.', 'doctor-ak-portal' );
		}

		if ( '' === $last_name ) {
			$errors['last_name'] = __( 'Last name is required.', 'doctor-ak-portal' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$errors['email'] = __( 'Please provide a valid email address.', 'doctor-ak-portal' );
		} elseif ( strtolower( $email ) !== strtolower( $user->user_email ) && email_exists( $email ) ) {
			$errors['email'] = __( 'An account with that email address already exists.', 'doctor-ak-portal' );
		}

		$current_password = isset( $_POST['current_password'] ) ? (string) $_POST['current_password'] : '';
		$new_password      = isset( $_POST['new_password'] ) ? (string) $_POST['new_password'] : '';
		$confirm_password  = isset( $_POST['confirm_new_password'] ) ? (string) $_POST['confirm_new_password'] : '';

		// Only the "new password" field signals intent to change it. Relying on
		// current_password here as well misfires when a browser autofills that
		// field with a saved login password on an otherwise unrelated edit.
		$wants_password_change = ( '' !== $new_password );

		if ( $wants_password_change ) {
			if ( '' === $current_password || ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
				$errors['current_password'] = __( 'Your current password is incorrect.', 'doctor-ak-portal' );
			}

			if ( strlen( $new_password ) < 8 ) {
				$errors['new_password'] = __( 'New password must be at least 8 characters long.', 'doctor-ak-portal' );
			} elseif ( $new_password !== $confirm_password ) {
				$errors['confirm_new_password'] = __( 'Passwords do not match.', 'doctor-ak-portal' );
			}
		}

		$meta = $is_doctor
			? $this->validate_doctor_fields( $errors )
			: $this->validate_patient_fields( $errors );

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		$update = array(
			'ID'           => $user->ID,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
			'user_email'   => $email,
		);

		if ( $wants_password_change ) {
			$update['user_pass'] = $new_password;
		}

		$result = wp_update_user( $update );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		foreach ( $meta as $meta_key => $meta_value ) {
			update_user_meta( $user->ID, $meta_key, $meta_value );
		}

		if ( $wants_password_change ) {
			// Changing the password invalidates the auth cookie WordPress just
			// issued for this request (it's derived from the password hash),
			// so re-issue it to avoid logging the user out of their own session.
			wp_clear_auth_cookie();
			wp_set_auth_cookie( $user->ID );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Your profile has been updated successfully.', 'doctor-ak-portal' ),
			)
		);
	}

	/**
	 * Validates and sanitizes doctor-only profile fields.
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

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$city    = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$area    = isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '';

		if ( '' === $country || ! Locations::is_valid_country( $country ) ) {
			$errors['country'] = __( 'Please select your country.', 'doctor-ak-portal' );
		} elseif ( '' === $city || ! Locations::is_valid_city( $country, $city ) ) {
			$errors['city'] = __( 'Please select your city.', 'doctor-ak-portal' );
		} elseif ( '' === $area || ! Locations::is_valid_area( $country, $city, $area ) ) {
			$errors['area'] = __( 'Please select your area.', 'doctor-ak-portal' );
		} else {
			$meta['doctor_ak_country'] = $country;
			$meta['doctor_ak_city']    = $city;
			$meta['doctor_ak_area']    = $area;
		}

		$short_description = isset( $_POST['short_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['short_description'] ) ) : '';

		$meta['doctor_ak_short_description'] = $short_description;

		$meta['doctor_ak_expertise'] = isset( $_POST['expertise'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expertise'] ) ) : '';
		$meta[ Doctor_Awards::META_KEY ] = Doctor_Awards::encode( Doctor_Awards::sanitize_from_request( $errors ) );

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

		return $meta;
	}

	/**
	 * Validates and sanitizes patient-only profile fields.
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
	 * profile shortcode.
	 *
	 * @return bool
	 */
	private function is_profile_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, 'doctor_profile' );
	}
}
