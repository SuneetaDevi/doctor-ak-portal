<?php
/**
 * AJAX handlers backing a doctor's "Clinics" tab (dashboard) and the
 * admin dashboard's "Doctor Sessions" edit/delete actions.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinic_Locations;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Locations;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinic_Handler
 *
 * Doctors manage only their own clinics (ownership enforced on every
 * request); administrators can edit or delete any doctor's clinic from the
 * "Doctor Sessions" admin section, reusing the exact same save/delete logic
 * instead of duplicating it, gated by a separate nonce + `manage_options`.
 */
class Clinic_Handler {

	/**
	 * Nonce action for the doctor-facing Clinics tab.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_clinics';

	/**
	 * Enqueues clinics-tab assets only when the doctor dashboard's Clinics
	 * tab is the active tab.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_clinics_tab_page() ) {
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
			'doctor-ak-portal-clinics',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-clinics.css',
			array( 'doctor-ak-portal-registration' ),
			Assets::version( 'assets/css/doctor-ak-clinics.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-registration',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-registration.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-registration.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-city-area-select',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-city-area-select.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-city-area-select.js' ),
			true
		);

		wp_enqueue_script(
			'doctor-ak-portal-clinics',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-clinics.js',
			array( 'doctor-ak-portal-registration', 'doctor-ak-portal-city-area-select' ),
			Assets::version( 'assets/js/doctor-ak-clinics.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-clinics',
			'dakClinics',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
				'locations'       => Locations::get_all(),
				'clinicLocations' => Clinic_Locations::get_all(),
			)
		);
	}

	/**
	 * AJAX handler: creates/updates a clinic belonging to the logged-in doctor.
	 *
	 * @return void
	 */
	public function handle_save_clinic() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'clinics' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Clinics page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$this->process_save( get_current_user_id(), get_current_user_id(), true );
	}

	/**
	 * AJAX handler: deletes a clinic belonging to the logged-in doctor.
	 *
	 * @return void
	 */
	public function handle_delete_clinic() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'clinics' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Clinics page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$this->process_delete( get_current_user_id() );
	}

	/**
	 * AJAX handler: admin creates/updates any doctor's clinic from the
	 * "Doctor Sessions" section.
	 *
	 * @return void
	 */
	public function handle_admin_save_clinic() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( wp_unslash( $_POST['doctor_id'] ) ) : 0;

		if ( $doctor_id <= 0 || ! get_userdata( $doctor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That doctor no longer exists.', 'doctor-ak-portal' ) ) );
		}

		$this->process_save( $doctor_id, null, true );
	}

	/**
	 * AJAX handler: admin deletes any doctor's clinic from the
	 * "Doctor Sessions" section.
	 *
	 * @return void
	 */
	public function handle_admin_delete_clinic() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$this->process_delete( null );
	}

	/**
	 * Shared save logic for both the doctor-facing and admin-facing endpoints.
	 *
	 * @param int      $owner_doctor_id      Doctor ID the clinic is created under (for new clinics).
	 * @param int|null $ownership_check      Doctor ID an existing clinic must belong to, or null to skip the check (admin context).
	 * @param bool     $requires_clinic_location Whether a new physical clinic must be aligned to a Clinic_Locations master record instead of free-typed name/address/location — true from both the admin "Doctor Sessions" form and the doctor's own "Clinics" tab, so every physical clinic (new or already saved) is always one of the admin-managed locations, never a one-off free-typed entry.
	 * @return void
	 */
	private function process_save( $owner_doctor_id, $ownership_check, $requires_clinic_location = false ) {
		$clinic_id = isset( $_POST['clinic_id'] ) ? absint( wp_unslash( $_POST['clinic_id'] ) ) : 0;
		$type      = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : Clinics::TYPE_PHYSICAL;

		if ( $clinic_id > 0 ) {
			$existing = Clinics::find( $clinic_id );

			if ( ! $existing || ( null !== $ownership_check && (int) $existing['doctor_id'] !== (int) $ownership_check ) ) {
				wp_send_json_error( array( 'message' => __( 'That clinic no longer exists.', 'doctor-ak-portal' ) ) );
			}
		}

		// Both the admin "Doctor Sessions" form and the doctor's own
		// "Clinics" tab align a physical clinic to one of the admin-managed
		// master clinics (Clinic_Locations) instead of typing a
		// name/address/location freehand — the posted clinic_location_id's
		// fields override whatever was (or wasn't) posted, before validating
		// below.
		$clinic_location_id = isset( $_POST['clinic_location_id'] ) ? absint( wp_unslash( $_POST['clinic_location_id'] ) ) : 0;

		if ( Clinics::TYPE_PHYSICAL === $type && $clinic_location_id > 0 ) {
			$clinic_location = Clinic_Locations::find( $clinic_location_id );

			if ( ! $clinic_location ) {
				wp_send_json_error( array( 'errors' => array( 'clinic_location_id' => __( 'Please choose a valid clinic.', 'doctor-ak-portal' ) ) ) );
			}

			$_POST['address'] = $clinic_location['address'];
			$_POST['country'] = $clinic_location['country'];
			$_POST['city']    = $clinic_location['city'];
			$_POST['area']    = $clinic_location['area'];
		} elseif ( Clinics::TYPE_PHYSICAL === $type && $requires_clinic_location ) {
			if ( $clinic_id > 0 && isset( $existing ) && $existing ) {
				// Editing a clinic that predates this feature (never aligned
				// to a master Clinic_Locations record) — keep its own
				// already-saved address/location instead of forcing the
				// admin to pick one just to change, say, its weekly hours.
				$_POST['address'] = $existing['address'];
				$_POST['country'] = $existing['country'];
				$_POST['city']    = $existing['city'];
				$_POST['area']    = $existing['area'];
			} else {
				wp_send_json_error( array( 'errors' => array( 'clinic_location_id' => __( 'Please choose a clinic.', 'doctor-ak-portal' ) ) ) );
			}
		}

		$fields = Clinics::sanitize_clinic_fields_from_request( $_POST, $type ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Clinics::sanitize_clinic_fields_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			$field_by_error_code = array(
				'doctor_ak_clinic_name_required'    => 'name',
				'doctor_ak_clinic_address_required' => 'address',
				'doctor_ak_clinic_country_required' => 'country',
				'doctor_ak_clinic_city_required'    => 'city',
				'doctor_ak_clinic_area_required'    => 'area',
				'doctor_ak_clinic_phone_invalid'    => 'phone',
				'doctor_ak_clinic_email_invalid'    => 'contact_email',
			);
			$field = isset( $field_by_error_code[ $fields->get_error_code() ] ) ? $field_by_error_code[ $fields->get_error_code() ] : 'name';

			wp_send_json_error( array( 'errors' => array( $field => $fields->get_error_message() ) ) );
		}

		$posted_sessions = isset( $_POST['sessions'] ) && is_array( $_POST['sessions'] ) ? $_POST['sessions'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Clinics::sanitize_sessions_from_request() unslashes/sanitizes each field itself.
		$sessions        = Clinics::sanitize_sessions_from_request( $posted_sessions );

		if ( is_wp_error( $sessions ) ) {
			wp_send_json_error( array( 'errors' => array( 'sessions' => $sessions->get_error_message() ) ) );
		}

		if ( $clinic_id > 0 ) {
			$saved = Clinics::update( $clinic_id, $fields, $sessions, $ownership_check );
		} else {
			$saved = Clinics::create( $owner_doctor_id, $fields, $sessions );
		}

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'The clinic could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Clinic saved successfully.', 'doctor-ak-portal' ),
				'clinic_id' => $clinic_id > 0 ? $clinic_id : $saved,
			)
		);
	}

	/**
	 * Shared delete logic for both the doctor-facing and admin-facing endpoints.
	 *
	 * @param int|null $ownership_check Doctor ID the clinic must belong to, or null to skip the check (admin context).
	 * @return void
	 */
	private function process_delete( $ownership_check ) {
		$clinic_id = isset( $_POST['clinic_id'] ) ? absint( wp_unslash( $_POST['clinic_id'] ) ) : 0;
		$existing  = $clinic_id > 0 ? Clinics::find( $clinic_id ) : null;

		if ( ! $existing || ( null !== $ownership_check && (int) $existing['doctor_id'] !== (int) $ownership_check ) ) {
			wp_send_json_error( array( 'message' => __( 'That clinic no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Clinics::delete( $clinic_id, $ownership_check ) ) {
			wp_send_json_error( array( 'message' => __( 'The clinic could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Clinic deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Checks whether the current request is for the doctor dashboard's
	 * Clinics tab.
	 *
	 * @return bool
	 */
	private function is_clinics_tab_page() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) || ! has_shortcode( $post->post_content, Doctor_Dashboard::SHORTCODE_TAG ) ) {
			return false;
		}

		return isset( $_GET['tab'] ) && 'clinics' === $_GET['tab']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
	}
}
