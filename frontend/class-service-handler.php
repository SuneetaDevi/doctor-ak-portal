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

		$fields = Services::sanitize_fields_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Services::sanitize_fields_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			wp_send_json_error( array( 'errors' => array( 'name' => $fields->get_error_message() ) ) );
		}

		$image_id = $this->resolve_image_id();

		if ( is_wp_error( $image_id ) ) {
			wp_send_json_error( array( 'errors' => array( 'image' => $image_id->get_error_message() ) ) );
		}

		$service_id = isset( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0;
		$result     = $this->save_one( get_current_user_id(), get_current_user_id(), $service_id, $fields, $image_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Service saved successfully.', 'doctor-ak-portal' ),
				'service_id' => $result,
			)
		);
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
	 * creates/updates a service. The Doctor field is a multi-select — since
	 * each Services row still belongs to exactly one doctor (booking/
	 * revenue-split both key off a single doctor per appointment), picking
	 * several only makes sense when *adding* a brand-new service: this
	 * creates one identical row per selected doctor (same name/description/
	 * image/category/duration/clinic pricing), each independently editable
	 * afterward. Editing an existing row always targets exactly one doctor
	 * — only the first selection applies there, same as before this field
	 * became multi-select.
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

		$doctor_ids = array();

		if ( isset( $_POST['doctor_ids'] ) && is_array( $_POST['doctor_ids'] ) ) {
			foreach ( wp_unslash( $_POST['doctor_ids'] ) as $doctor_id ) {
				$doctor_id = absint( $doctor_id );

				if ( $doctor_id > 0 && get_userdata( $doctor_id ) ) {
					$doctor_ids[] = $doctor_id;
				}
			}
		}

		$doctor_ids = array_values( array_unique( $doctor_ids ) );

		if ( empty( $doctor_ids ) ) {
			wp_send_json_error( array( 'errors' => array( 'doctor_id' => __( 'Please select at least one doctor.', 'doctor-ak-portal' ) ) ) );
		}

		$fields = Services::sanitize_fields_from_request( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Services::sanitize_fields_from_request() unslashes/sanitizes each field itself.

		if ( is_wp_error( $fields ) ) {
			wp_send_json_error( array( 'errors' => array( 'name' => $fields->get_error_message() ) ) );
		}

		// Resolved once, even when this creates several rows below, so a
		// newly uploaded image isn't re-uploaded (as a separate attachment)
		// once per selected doctor.
		$image_id = $this->resolve_image_id();

		if ( is_wp_error( $image_id ) ) {
			wp_send_json_error( array( 'errors' => array( 'image' => $image_id->get_error_message() ) ) );
		}

		$service_id = isset( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0;

		if ( $service_id > 0 ) {
			$result = $this->save_one( $doctor_ids[0], null, $service_id, $fields, $image_id );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'message'    => __( 'Service saved successfully.', 'doctor-ak-portal' ),
					'service_id' => $result,
				)
			);
		}

		$created_count = 0;

		foreach ( $doctor_ids as $doctor_id ) {
			$result = $this->save_one( $doctor_id, null, 0, $fields, $image_id );

			if ( ! is_wp_error( $result ) ) {
				++$created_count;
			}
		}

		if ( 0 === $created_count ) {
			wp_send_json_error( array( 'message' => __( 'The service could not be saved. Please try again.', 'doctor-ak-portal' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => $created_count > 1
					? sprintf(
						/* translators: %d: number of doctors. */
						_n( 'Service created for %d doctor.', 'Service created for %d doctors.', $created_count, 'doctor-ak-portal' ),
						$created_count
					)
					: __( 'Service saved successfully.', 'doctor-ak-portal' ),
			)
		);
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
	 * Resolves the "Image" field's attachment ID for the current request —
	 * only the admin modal's form posts one. A freshly chosen file (if any)
	 * is uploaded and takes precedence over the posted `image_id` (which
	 * otherwise just carries forward whatever the row already had).
	 *
	 * @return int|null|\WP_Error Attachment ID; null when the request has no
	 *                             Image field at all (a doctor's own save —
	 *                             tells Services::update() to leave the
	 *                             existing image untouched); WP_Error on an
	 *                             invalid upload.
	 */
	private function resolve_image_id() {
		$image_id = isset( $_POST['image_id'] ) ? absint( wp_unslash( $_POST['image_id'] ) ) : null;

		if ( ! empty( $_FILES['image'] ) && UPLOAD_ERR_NO_FILE !== ( $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$uploaded_image_id = $this->image_uploader->upload( $_FILES['image'], get_current_user_id() );

			if ( is_wp_error( $uploaded_image_id ) ) {
				return $uploaded_image_id;
			}

			$image_id = $uploaded_image_id;
		}

		return $image_id;
	}

	/**
	 * Shared save logic for both the doctor-facing and admin-facing
	 * endpoints — creates one row (or updates the given one) and returns
	 * its ID, rather than emitting a JSON response itself, so
	 * handle_admin_save_service() can call this once per selected doctor
	 * when bulk-creating a new service for several of them at once.
	 *
	 * @param int      $owner_doctor_id Doctor ID the service is created under (for new services).
	 * @param int|null $ownership_check Doctor ID an existing service must belong to, or null to skip the check (admin context).
	 * @param int      $service_id      Existing service ID to update, or 0 to create a new one.
	 * @param array    $fields          Sanitized service fields, see Services::sanitize_fields_from_request().
	 * @param int|null $image_id        See resolve_image_id().
	 * @return int|\WP_Error Service ID on success, WP_Error on failure.
	 */
	private function save_one( $owner_doctor_id, $ownership_check, $service_id, array $fields, $image_id ) {
		if ( $service_id > 0 ) {
			$existing = Services::find( $service_id );

			if ( ! $existing || ( null !== $ownership_check && (int) $existing['doctor_id'] !== (int) $ownership_check ) ) {
				return new \WP_Error( 'doctor_ak_service_not_found', __( 'That service no longer exists.', 'doctor-ak-portal' ) );
			}

			$saved = Services::update( $service_id, $fields, $ownership_check, $image_id );

			return $saved ? $service_id : new \WP_Error( 'doctor_ak_service_save_failed', __( 'The service could not be saved. Please try again.', 'doctor-ak-portal' ) );
		}

		$saved = Services::create( $owner_doctor_id, $fields, (int) $image_id );

		return $saved ? $saved : new \WP_Error( 'doctor_ak_service_save_failed', __( 'The service could not be saved. Please try again.', 'doctor-ak-portal' ) );
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
