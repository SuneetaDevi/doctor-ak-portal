<?php
/**
 * Registers and manages the custom user roles used by the plugin.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Roles
 *
 * Defines the "doctor", "patient", and "receptionist" roles and their
 * capabilities. Capabilities are namespaced with the `doctor_ak_` prefix to
 * avoid collisions with other plugins/themes.
 */
class Roles {

	/**
	 * Doctor role slug.
	 *
	 * @var string
	 */
	const DOCTOR_ROLE = 'doctor';

	/**
	 * Patient role slug.
	 *
	 * @var string
	 */
	const PATIENT_ROLE = 'patient';

	/**
	 * Receptionist role slug — front-desk staff added by an administrator
	 * (no public self-registration). Logs in like a doctor/patient but is
	 * routed to a cut-down view of the admin dashboard: read-only Doctors/
	 * Patients directories, marking appointment payments received, and
	 * managing doctors' clinic locations/session hours. Everything else
	 * (Settings, Services, Video Consultation pricing, account management,
	 * booking/editing/cancelling appointments) stays administrator-only.
	 *
	 * @var string
	 */
	const RECEPTIONIST_ROLE = 'receptionist';

	/**
	 * Option name tracking which version of the role definitions below is
	 * currently applied.
	 *
	 * @var string
	 */
	const ROLES_VERSION_OPTION = 'dak_roles_version';

	/**
	 * Current role definitions version. Bump this whenever a role's
	 * capabilities change (or a role is added/removed) so maybe_upgrade()
	 * re-syncs it on sites that already had the plugin active.
	 *
	 * @var string
	 */
	const ROLES_VERSION = '1.1.0';

	/**
	 * Registers the plugin roles, or refreshes their capabilities if the
	 * roles already exist (e.g. after a plugin update adds new capabilities).
	 *
	 * @return void
	 */
	public static function add_roles() {
		self::add_or_update_role( self::DOCTOR_ROLE, __( 'Doctor', 'doctor-ak-portal' ), self::doctor_capabilities() );
		self::add_or_update_role( self::PATIENT_ROLE, __( 'Patient', 'doctor-ak-portal' ), self::patient_capabilities() );
		self::add_or_update_role( self::RECEPTIONIST_ROLE, __( 'Receptionist', 'doctor-ak-portal' ), self::receptionist_capabilities() );
	}

	/**
	 * Safety net for sites where the plugin was already active when the
	 * Receptionist role (or a capability change) was introduced: activation
	 * hooks only fire on (re)activation, so an in-place file update (no
	 * deactivate/reactivate) would otherwise never create/update roles.
	 * Cheap to check on every request (a single autoloaded option
	 * comparison); only re-syncs roles when the stored version doesn't match.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::ROLES_VERSION === get_option( self::ROLES_VERSION_OPTION ) ) {
			return;
		}

		self::add_roles();
		update_option( self::ROLES_VERSION_OPTION, self::ROLES_VERSION );
	}

	/**
	 * Removes the plugin roles.
	 *
	 * Users previously assigned these roles are not deleted; WordPress
	 * simply leaves them without a role, consistent with core behaviour
	 * when a role is removed.
	 *
	 * @return void
	 */
	public static function remove_roles() {
		remove_role( self::DOCTOR_ROLE );
		remove_role( self::PATIENT_ROLE );
		remove_role( self::RECEPTIONIST_ROLE );
	}

	/**
	 * Creates a role if it does not exist, or synchronises its capabilities
	 * if it already does.
	 *
	 * @param string $role         Role slug.
	 * @param string $display_name Human readable role name.
	 * @param array  $capabilities Map of capability => bool.
	 * @return void
	 */
	private static function add_or_update_role( $role, $display_name, array $capabilities ) {
		$existing_role = get_role( $role );

		if ( null === $existing_role ) {
			add_role( $role, $display_name, $capabilities );
			return;
		}

		foreach ( $capabilities as $capability => $granted ) {
			if ( $granted ) {
				$existing_role->add_cap( $capability );
			} else {
				$existing_role->remove_cap( $capability );
			}
		}
	}

	/**
	 * Capabilities granted to the Doctor role.
	 *
	 * @return array
	 */
	public static function doctor_capabilities() {
		return array(
			'read'                          => true,
			'upload_files'                  => true,
			'doctor_ak_view_dashboard'      => true,
			'doctor_ak_manage_profile'      => true,
			'doctor_ak_manage_availability' => true,
		);
	}

	/**
	 * Capabilities granted to the Patient role.
	 *
	 * @return array
	 */
	public static function patient_capabilities() {
		return array(
			'read'                     => true,
			'upload_files'             => true,
			'doctor_ak_view_dashboard' => true,
			'doctor_ak_manage_profile' => true,
		);
	}

	/**
	 * Capabilities granted to the Receptionist role.
	 *
	 * @return array
	 */
	public static function receptionist_capabilities() {
		return array(
			'read'                       => true,
			'doctor_ak_view_dashboard'   => true,
			'doctor_ak_view_directory'   => true,
			'doctor_ak_manage_payments'  => true,
			'doctor_ak_manage_clinics'   => true,
		);
	}
}
