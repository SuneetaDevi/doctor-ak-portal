<?php
/**
 * AJAX handlers backing a doctor's "Services" tab (dashboard) and the
 * admin dashboard's "Services" section.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Profile_Picture_Uploader;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Handler
 *
 * Doctors manage only their own services (ownership enforced on every
 * request); administrators can add/edit/delete any doctor's service from the
 * "Services" admin section, reusing the exact same save/delete logic instead
 * of duplicating it, gated by a separate nonce + `manage_options`. Mirrors
 * Clinic_Handler's dual doctor/admin endpoint pattern. The admin side also
 * doubles as this plugin's public "service portfolio" editor — the same
 * saved rows drive the public [services_directory]/[service_profile_view]
 * pages (see Services::active_for_public_directory()), so an admin can add
 * a description/image/clinics to any service without a separate screen.
 */
class Service_Handler {

	/**
	 * Nonce action for the doctor-facing Services tab.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_services';

	/**
	 * Image upload service, for the admin-only "Image" field.
	 *
	 * @var Profile_Picture_Uploader
	 */
	private $image_uploader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Profile_Picture_Uploader $image_uploader Image upload service.
	 */
	public function __construct( Profile_Picture_Uploader $image_uploader ) {
		$this->image_uploader = $image_uploader;
	}

	/**
	 * Enqueues services-tab assets only when the doctor dashboard's Services
	 * tab is the active tab.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_services_tab_page() ) {
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

		wp_enqueue_style(
			'doctor-ak-portal-booking-modal',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-booking-modal.css',
			array( 'doctor-ak-portal-clinics' ),
			Assets::version( 'assets/css/doctor-ak-booking-modal.css' )
		);

		// The Services tab's table markup (.dak-admin-users-table,
		// .dak-admin-users-actions, .dak-icon-button) reuses the admin
		// dashboard's table styles, so those styles need to be loaded here too.
		wp_enqueue_style(
			'doctor-ak-portal-admin-dashboard',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-admin-dashboard.css',
			array( 'doctor-ak-portal-booking-modal' ),
			Assets::version( 'assets/css/doctor-ak-admin-dashboard.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-services-tab',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-services-tab.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-services-tab.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-services-tab',
			'dakServicesTab',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * AJAX handler: creates/updates a service belonging to the logged-in doctor.
	 *
	 * @return void
	 */
	public function handle_save_service() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'services' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Services page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$this->process_save( get_current_user_id(), get_current_user_id() );
	}

	/**
	 * AJAX handler: deletes a service belonging to the logged-in doctor.
	 *
	 * @return void
	 */
	public function handle_delete_service() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! is_user_logged_in() || ! in_array( Roles::DOCTOR_ROLE, (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in as a doctor.', 'doctor-ak-portal' ) ), 401 );
		}

		if ( ! Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'services' ) ) {
			wp_send_json_error( array( 'message' => __( 'An administrator has turned off the Services page for your account.', 'doctor-ak-portal' ) ), 403 );
		}

		$this->process_delete( get_current_user_id() );
	}

	/**
	 * AJAX handler: admin (or a Receptionist with doctor_ak_manage_services)
	 * creates/updates a service for any doctor.
	 *
	 * @return void
	 */
	public function handle_admin_save_service() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_services' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( wp_unslash( $_POST['doctor_id'] ) ) : 0;

		if ( $doctor_id <= 0 || ! get_userdata( $doctor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That doctor no longer exists.', 'doctor-ak-portal' ) ) );
		}

		$this->process_save( $doctor_id, null );
	}

	/**
	 * AJAX handler: admin (or a Receptionist with doctor_ak_manage_services)
	 * deletes any doctor's service.
	 *
	 * @return void
	 */
	public function handle_admin_delete_service() {
		if ( ! check_ajax_referer( Admin_Dashboard::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'doctor_ak_manage_services' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		$this->process_delete( null );
	}

	/**
	 * Shared save logic for both the doctor-facing and admin-facing endpoints.
	 *
	 * @param int      $owner_doctor_id Doctor ID the service is created under (for new services).
	 * @param int|null $ownership_check Doctor ID an existing service must belong to, or null to skip the check (admin context).
	 * @return void
	 */
	private function process_save( $owner_doctor_id, $ownership_check ) {
		$service_id = isset( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0;

		if ( $service_id > 0 ) {
			$existing = Services::find( $service_id );

			if ( ! $existing || ( null !== $ownership_check && (int) $existing['doctor_id'] !== (int) $ownership_check ) ) {
				wp_send_json_error( array( 'message' => __( 'That service no longer exists.', 'doctor-ak-portal' ) ) );
			}
		}

		$fields = Services::sanitize_fields_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Services::sanitize_fields_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			wp_send_json_error( array( 'errors' => array( 'name' => $fields->get_error_message() ) ) );
		}

		// Only the admin modal's form posts an "Image" field — null tells
		// Services::update() to leave whatever image is already on the row
		// untouched, so a doctor editing their own service (whose form has
		// no such field) can never wipe out an image an admin already set.
		$image_id = isset( $_POST['image_id'] ) ? absint( wp_unslash( $_POST['image_id'] ) ) : null;

		if ( ! empty( $_FILES['image'] ) && UPLOAD_ERR_NO_FILE !== ( $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$uploaded_image_id = $this->image_uploader->upload( $_FILES['image'], get_current_user_id() );

			if ( is_wp_error( $uploaded_image_id ) ) {
				wp_send_json_error( array( 'errors' => array( 'image' => $uploaded_image_id->get_error_message() ) ) );
			}

			$image_id = $uploaded_image_id;
		}

		if ( $service_id > 0 ) {
			$saved = Services::update( $service_id, $fields, $ownership_check, $image_id );
		} else {
			$saved = Services::create( $owner_doctor_id, $fields, $image_id );
		}

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'The service could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Service saved successfully.', 'doctor-ak-portal' ),
				'service_id' => $service_id > 0 ? $service_id : $saved,
			)
		);
	}

	/**
	 * Shared delete logic for both the doctor-facing and admin-facing endpoints.
	 *
	 * @param int|null $ownership_check Doctor ID the service must belong to, or null to skip the check (admin context).
	 * @return void
	 */
	private function process_delete( $ownership_check ) {
		$service_id = isset( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0;
		$existing   = $service_id > 0 ? Services::find( $service_id ) : null;

		if ( ! $existing || ( null !== $ownership_check && (int) $existing['doctor_id'] !== (int) $ownership_check ) ) {
			wp_send_json_error( array( 'message' => __( 'That service no longer exists.', 'doctor-ak-portal' ) ) );
		}

		if ( ! Services::delete( $service_id, $ownership_check ) ) {
			wp_send_json_error( array( 'message' => __( 'The service could not be deleted. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Service deleted.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Checks whether the current request is for the doctor dashboard's
	 * Services tab.
	 *
	 * @return bool
	 */
	private function is_services_tab_page() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) || ! has_shortcode( $post->post_content, Doctor_Dashboard::SHORTCODE_TAG ) ) {
			return false;
		}

		return isset( $_GET['tab'] ) && 'services' === $_GET['tab']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state, not a form submission.
	}
}
