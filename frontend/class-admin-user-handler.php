<?php
/**
 * AJAX handlers backing the [admin_dashboard] Users (Doctors/Patients) table.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Authentication;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Doctor_Awards;
use DoctorAKPortal\Includes\Locations;
use DoctorAKPortal\Includes\Phone;
use DoctorAKPortal\Includes\Profile_Picture_Uploader;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Specializations;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_User_Handler
 *
 * Every endpoint here re-checks `manage_options` independently of the
 * dashboard page gate in Admin_Dashboard, since AJAX endpoints are
 * reachable directly regardless of which page rendered the form.
 */
class Admin_User_Handler {

	/**
	 * Profile picture upload service.
	 *
	 * @var Profile_Picture_Uploader
	 */
	private $profile_picture_uploader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Profile_Picture_Uploader $profile_picture_uploader Upload service.
	 */
	public function __construct( Profile_Picture_Uploader $profile_picture_uploader ) {
		$this->profile_picture_uploader = $profile_picture_uploader;
	}

	/**
	 * AJAX handler: uploads a doctor/patient's profile picture immediately.
	 * When editing an existing account (`user_id` > 0) the attachment is
	 * owned by that user right away; when adding a brand-new account
	 * (`user_id` = 0, not created yet) it's uploaded unclaimed and
	 * transferred to the new account once handle_save_user() creates it —
	 * same two-step dance Registration_Handler uses for guest uploads.
	 *
	 * @return void
	 */
	public function handle_upload_profile_picture() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( empty( $_FILES['profile_picture'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was received.', 'doctor-ak-portal' ) ) );
		}

		$user_id       = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$attachment_id = $this->profile_picture_uploader->upload( $_FILES['profile_picture'], $user_id );

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
	 * AJAX handler: creates a new doctor/patient account, or updates an
	 * existing one when `user_id` is present.
	 *
	 * @return void
	 */
	public function handle_save_user() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$errors  = array();

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';

		if ( '' === $first_name ) {
			$errors['first_name'] = __( 'First name is required.', 'doctor-ak-portal' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$errors['email'] = __( 'Please provide a valid email address.', 'doctor-ak-portal' );
		}

		$existing_user = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( $user_id > 0 && ! $existing_user ) {
			wp_send_json_error( array( 'message' => __( 'That account no longer exists.', 'doctor-ak-portal' ) ) );
		}

		// Patients/receptionists have no specialization field at all — only
		// require/save it for doctors (existing doctor being edited, or a
		// brand-new account whose role was explicitly posted as 'doctor').
		if ( $existing_user ) {
			$existing_roles = (array) $existing_user->roles;

			if ( in_array( Roles::DOCTOR_ROLE, $existing_roles, true ) ) {
				$target_role = Roles::DOCTOR_ROLE;
			} elseif ( in_array( Roles::RECEPTIONIST_ROLE, $existing_roles, true ) ) {
				$target_role = Roles::RECEPTIONIST_ROLE;
			} else {
				$target_role = Roles::PATIENT_ROLE;
			}
		} else {
			$target_role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		}

		$is_for_doctor = Roles::DOCTOR_ROLE === $target_role;

		$qualification              = '';
		$short_description          = '';
		$expertise                  = '';
		$years_experience           = null;
		$awards                     = array();
		$clinic_fields_list         = array();
		$specializations            = array();
		$video_consultation_allowed = true;

		if ( $is_for_doctor ) {
			$qualification = isset( $_POST['qualification'] ) ? sanitize_text_field( wp_unslash( $_POST['qualification'] ) ) : '';

			if ( '' === $qualification ) {
				$errors['qualification'] = __( 'Please provide the doctor\'s qualification(s), e.g. MBBS, FCPS.', 'doctor-ak-portal' );
			}

			$years_experience = isset( $_POST['years_experience'] ) && '' !== $_POST['years_experience']
				? absint( wp_unslash( $_POST['years_experience'] ) )
				: null;

			if ( null === $years_experience || $years_experience > 80 ) {
				$errors['years_experience'] = __( 'Please provide a valid number of years of experience.', 'doctor-ak-portal' );
			}

			$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
			$city    = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
			$area    = isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '';

			if ( '' === $country || ! Locations::is_valid_country( $country ) ) {
				$errors['country'] = __( "Please select the doctor's country.", 'doctor-ak-portal' );
			} elseif ( '' === $city || ! Locations::is_valid_city( $country, $city ) ) {
				$errors['city'] = __( "Please select the doctor's city.", 'doctor-ak-portal' );
			} elseif ( '' === $area || ! Locations::is_valid_area( $country, $city, $area ) ) {
				$errors['area'] = __( "Please select the doctor's area.", 'doctor-ak-portal' );
			}

			$short_description = isset( $_POST['short_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['short_description'] ) ) : '';

			$expertise = isset( $_POST['expertise'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expertise'] ) ) : '';
			$awards    = Doctor_Awards::sanitize_from_request( $errors );

			$video_consultation_allowed = isset( $_POST['video_consultation_allowed'] );

			// Specializations not in the canonical dropdown are allowed here
			// (admin-added custom tags, see doctor-ak-registration.js's
			// initMultiSelect( ..., { allowCustom: true } )) — this endpoint
			// is manage_options-gated, unlike doctor self-registration/
			// self-edit, which still only accept the canonical list.
			$specializations = array();

			if ( isset( $_POST['specializations'] ) && is_array( $_POST['specializations'] ) ) {
				foreach ( wp_unslash( $_POST['specializations'] ) as $raw ) {
					$raw  = (string) $raw;
					$slug = sanitize_key( $raw );

					if ( Specializations::is_valid( $slug ) ) {
						$specializations[] = $slug;
						continue;
					}

					$custom = sanitize_text_field( $raw );

					if ( '' !== $custom ) {
						$specializations[] = $custom;
					}
				}

				$specializations = array_values( array_unique( $specializations ) );
			}

			if ( empty( $specializations ) ) {
				$errors['specializations'] = __( 'Please select at least one specialization.', 'doctor-ak-portal' );
			}

			// Optional "align to clinics" — only attempted for whichever
			// clinics were actually picked from the master Clinic_Locations
			// list, so leaving it unset (the common case when the doctor
			// already has clinics, or will be aligned later from the Doctor
			// Sessions section) is never an error. Each picked clinic becomes
			// its own Clinics row below, once the account itself is saved.
			$posted_clinic_location_ids = isset( $_POST['clinic_location_ids'] ) && is_array( $_POST['clinic_location_ids'] )
				? array_unique( array_map( 'absint', wp_unslash( $_POST['clinic_location_ids'] ) ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash() applied above before array_map().
				: array();

			foreach ( $posted_clinic_location_ids as $posted_clinic_location_id ) {
				if ( $posted_clinic_location_id <= 0 ) {
					continue;
				}

				$clinic_location = Clinic_Locations::find( $posted_clinic_location_id );

				if ( ! $clinic_location ) {
					$errors['clinic_location_ids'] = __( 'One of the chosen clinics could not be found.', 'doctor-ak-portal' );
					continue;
				}

				$clinic_fields = Clinics::sanitize_clinic_fields_from_request(
					array(
						'name'               => $clinic_location['name'],
						'address'            => $clinic_location['address'],
						'country'            => $clinic_location['country'],
						'city'               => $clinic_location['city'],
						'area'               => $clinic_location['area'],
						'phone'              => $clinic_location['phone'],
						'clinic_location_id' => $posted_clinic_location_id,
					),
					Clinics::TYPE_PHYSICAL
				);

				if ( is_wp_error( $clinic_fields ) ) {
					$errors['clinic_location_ids'] = $clinic_fields->get_error_message();
					continue;
				}

				$clinic_fields_list[] = $clinic_fields;
			}
		} else {
			$phone_number = Phone::sanitize_from_request(
				isset( $_POST['phone_country_code'] ) ? wp_unslash( $_POST['phone_country_code'] ) : '',
				isset( $_POST['phone_number'] ) ? wp_unslash( $_POST['phone_number'] ) : ''
			);

			if ( is_wp_error( $phone_number ) ) {
				$errors['phone_number'] = $phone_number->get_error_message();
			}
		}

		// Patients (not receptionists) are registered under exactly one
		// clinic from the master Clinic_Locations directory — never
		// required for/consulted by the video consultation flow.
		$patient_clinic_location_id = 0;

		if ( Roles::PATIENT_ROLE === $target_role ) {
			$patient_clinic_location_id = isset( $_POST['clinic_location_id'] ) ? absint( wp_unslash( $_POST['clinic_location_id'] ) ) : 0;

			if ( $patient_clinic_location_id <= 0 || ! Clinic_Locations::find( $patient_clinic_location_id ) ) {
				$errors['clinic_location_id'] = __( "Please select the patient's home clinic.", 'doctor-ak-portal' );
			}
		}

		$profile_picture_id = isset( $_POST['profile_picture_id'] ) ? absint( wp_unslash( $_POST['profile_picture_id'] ) ) : 0;

		if ( $existing_user && strtolower( $email ) !== strtolower( $existing_user->user_email ) && email_exists( $email ) ) {
			$errors['email'] = __( 'An account with that email address already exists.', 'doctor-ak-portal' );
		} elseif ( ! $existing_user && '' !== $email && email_exists( $email ) ) {
			$errors['email'] = __( 'An account with that email address already exists.', 'doctor-ak-portal' );
		}

		if ( ! $existing_user ) {
			$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';

			if ( ! in_array( $role, array( Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE, Roles::RECEPTIONIST_ROLE ), true ) ) {
				$errors['role'] = __( 'Please choose whether this account is a Doctor, a Patient, or a Receptionist.', 'doctor-ak-portal' );
			}

			if ( '' !== $password && strlen( $password ) < 8 ) {
				$errors['password'] = __( 'Password must be at least 8 characters long.', 'doctor-ak-portal' );
			}
		} elseif ( '' !== $password && strlen( $password ) < 8 ) {
			$errors['password'] = __( 'Password must be at least 8 characters long.', 'doctor-ak-portal' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		if ( $existing_user ) {
			$update = array(
				'ID'           => $existing_user->ID,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'user_email'   => $email,
			);

			if ( '' !== $password ) {
				$update['user_pass'] = $password;
			}

			$result = wp_update_user( $update );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			$saved_user_id = $existing_user->ID;
		} else {
			$user_login = self::unique_username_from_email( $email );

			$password_was_generated = ( '' === $password );

			if ( $password_was_generated ) {
				$password = wp_generate_password( 20, true, true );
			}

			$authentication = new Authentication();
			$saved_user_id  = $authentication->register_user(
				array(
					'user_login'   => $user_login,
					'user_email'   => $email,
					'user_pass'    => $password,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'display_name' => trim( $first_name . ' ' . $last_name ),
				),
				$role
			);

			if ( is_wp_error( $saved_user_id ) ) {
				wp_send_json_error( array( 'message' => $saved_user_id->get_error_message() ) );
			}

			if ( $password_was_generated ) {
				wp_new_user_notification( $saved_user_id, null, 'user' );
			}
		}

		if ( $is_for_doctor ) {
			update_user_meta( $saved_user_id, 'doctor_ak_specializations', $specializations );
			update_user_meta( $saved_user_id, 'doctor_ak_qualification', $qualification );
			update_user_meta( $saved_user_id, 'doctor_ak_country', $country );
			update_user_meta( $saved_user_id, 'doctor_ak_city', $city );
			update_user_meta( $saved_user_id, 'doctor_ak_area', $area );
			update_user_meta( $saved_user_id, 'doctor_ak_years_experience', $years_experience );
			update_user_meta( $saved_user_id, 'doctor_ak_short_description', $short_description );
			update_user_meta( $saved_user_id, 'doctor_ak_expertise', $expertise );
			update_user_meta( $saved_user_id, Clinics::VIDEO_CONSULTATION_ALLOWED_META_KEY, $video_consultation_allowed ? '1' : '0' );
			update_user_meta( $saved_user_id, Doctor_Awards::META_KEY, Doctor_Awards::encode( $awards ) );

			foreach ( $clinic_fields_list as $clinic_fields ) {
				Clinics::create( $saved_user_id, $clinic_fields, Clinics::empty_sessions() );
			}
		} else {
			update_user_meta( $saved_user_id, 'doctor_ak_phone_number', $phone_number );

			if ( Roles::PATIENT_ROLE === $target_role ) {
				update_user_meta( $saved_user_id, Clinic_Locations::PATIENT_META_KEY, $patient_clinic_location_id );
			}
		}

		if ( $profile_picture_id > 0 ) {
			// When adding a brand-new account the picture was uploaded
			// unclaimed (owner 0, see handle_upload_profile_picture()) —
			// claim it now that the account exists. When editing an
			// existing account the upload endpoint already set that
			// account as the owner directly, so this is a no-op there.
			if ( ! $existing_user && $this->profile_picture_uploader->is_unclaimed_image( $profile_picture_id ) ) {
				$this->profile_picture_uploader->claim( $profile_picture_id, $saved_user_id );
			}

			update_user_meta( $saved_user_id, 'doctor_ak_profile_picture_id', $profile_picture_id );
		}

		wp_send_json_success(
			array(
				'message' => $existing_user
					? __( 'Account updated successfully.', 'doctor-ak-portal' )
					: __( 'Account created successfully.', 'doctor-ak-portal' ),
			)
		);
	}

	/**
	 * AJAX handler: permanently deletes a doctor/patient account.
	 *
	 * @return void
	 */
	public function handle_delete_user() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( ! $user || ! array_intersect( array( Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE, Roles::RECEPTIONIST_ROLE ), (array) $user->roles ) ) {
			wp_send_json_error( array( 'message' => __( 'That account no longer exists.', 'doctor-ak-portal' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		if ( ! wp_delete_user( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The account could not be deleted.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Account deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX handler: toggles a doctor/patient account between active and
	 * deactivated, blocking or restoring their ability to log in.
	 *
	 * @return void
	 */
	public function handle_toggle_status() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( ! $user || ! array_intersect( array( Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE, Roles::RECEPTIONIST_ROLE ), (array) $user->roles ) ) {
			wp_send_json_error( array( 'message' => __( 'That account no longer exists.', 'doctor-ak-portal' ) ) );
		}

		$is_disabled     = 'yes' === get_user_meta( $user_id, 'doctor_ak_account_disabled', true );
		$is_deactivating = ! $is_disabled;
		$is_doctor       = in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true );
		$upcoming        = ( $is_deactivating && $is_doctor ) ? Appointments::upcoming_for_doctor( $user_id ) : array();

		// Deactivating a doctor with upcoming appointments needs a second,
		// explicit choice from the admin (cancel them, or leave them as-is)
		// before we actually touch anything — the JS shows a follow-up
		// confirm() and resubmits with 'cancel_appointments' set to '1' or
		// '0' once the admin has answered. This first pass makes no choice.
		if ( ! empty( $upcoming ) && ! isset( $_POST['cancel_appointments'] ) ) {
			wp_send_json_success(
				array(
					'needs_confirmation' => true,
					'appointment_count'  => count( $upcoming ),
				)
			);
		}

		$should_cancel_appointments = isset( $_POST['cancel_appointments'] ) && '1' === wp_unslash( $_POST['cancel_appointments'] );

		update_user_meta( $user_id, 'doctor_ak_account_disabled', $is_deactivating ? 'yes' : 'no' );

		$message = $is_deactivating
			? __( 'Account deactivated.', 'doctor-ak-portal' )
			: __( 'Account reactivated.', 'doctor-ak-portal' );

		if ( $is_deactivating && $should_cancel_appointments && ! empty( $upcoming ) ) {
			$message .= ' ' . self::cancel_doctors_upcoming_appointments( $upcoming );
		}

		wp_send_json_success(
			array(
				'is_disabled' => $is_deactivating,
				'message'     => $message,
			)
		);
	}

	/**
	 * Cancels every appointment in $upcoming (a deactivated doctor's
	 * upcoming/unresolved appointments, see Appointments::upcoming_for_doctor())
	 * — each cancellation fires the same 'doctor_ak_appointment_cancelled'
	 * action a patient/doctor self-cancel does (emails + portal
	 * notifications to both sides), and a paid-online appointment is
	 * refunded immediately via Appointments::cancel_by_admin(), since this
	 * is the clinic's decision, not something the patient should have to
	 * separately request.
	 *
	 * @param array $upcoming Rows from Appointments::upcoming_for_doctor().
	 * @return string A human-readable summary to append to the toggle's success message.
	 */
	private static function cancel_doctors_upcoming_appointments( array $upcoming ) {
		$cancelled_count     = 0;
		$manual_refund_count = 0;
		$refund_error_count  = 0;

		foreach ( $upcoming as $row ) {
			$result = Appointments::cancel_by_admin( $row['id'] );

			if ( ! $result ) {
				continue;
			}

			++$cancelled_count;

			if ( $result['needs_manual_refund'] ) {
				++$manual_refund_count;
			}

			if ( '' !== $result['refund_error'] ) {
				++$refund_error_count;
			}
		}

		$message = sprintf(
			/* translators: %d: number of cancelled appointments. */
			_n( '%d upcoming appointment was cancelled and the patient/doctor notified by email.', '%d upcoming appointments were cancelled and the patients/doctor notified by email.', $cancelled_count, 'doctor-ak-portal' ),
			$cancelled_count
		);

		if ( $manual_refund_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of manually-paid appointments needing a refund. */
				_n( '%d of them was paid manually and needs a refund handled outside the site.', '%d of them were paid manually and need refunds handled outside the site.', $manual_refund_count, 'doctor-ak-portal' ),
				$manual_refund_count
			);
		}

		if ( $refund_error_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of online refunds that failed. */
				_n( '%d online refund failed and needs to be processed manually from the Appointments section.', '%d online refunds failed and need to be processed manually from the Appointments section.', $refund_error_count, 'doctor-ak-portal' ),
				$refund_error_count
			);
		}

		return $message;
	}

	/**
	 * Derives a unique WordPress username from an email address's local
	 * part, since the admin-add form only collects name/email/password
	 * (no separate username field).
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function unique_username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );

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
}
