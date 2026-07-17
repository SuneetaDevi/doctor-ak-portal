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
 * Defines the "doctor" and "patient" roles and their capabilities.
 * Capabilities are namespaced with the `doctor_ak_` prefix to avoid
 * collisions with other plugins/themes.
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
	 * Registers the plugin roles, or refreshes their capabilities if the
	 * roles already exist (e.g. after a plugin update adds new capabilities).
	 *
	 * @return void
	 */
	public static function add_roles() {
		self::add_or_update_role( self::DOCTOR_ROLE, __( 'Doctor', 'doctor-ak-portal' ), self::doctor_capabilities() );
		self::add_or_update_role( self::PATIENT_ROLE, __( 'Patient', 'doctor-ak-portal' ), self::patient_capabilities() );
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
}
