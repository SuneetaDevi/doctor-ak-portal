<?php
/**
 * Admin-controlled per-role dashboard tab visibility ("Roles & Permissions").
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Role_Permissions
 *
 * Lets an administrator turn off specific dashboard tabs/pages per role —
 * e.g. disabling "Profile" for doctors means they no longer see an Edit
 * Profile link, visiting it directly falls back to the Dashboard tab, and
 * the standalone [doctor_profile] page shows an access-denied state instead
 * of the form. The "Dashboard" (overview) tab itself is never toggle-able —
 * every account needs a landing page. Stored as a single wp_option, keyed by
 * role => tab slug => bool; a tab with no explicit entry defaults to
 * allowed, so this is purely additive/restrictive over the existing
 * behaviour and safe for installs that predate this feature.
 */
class Role_Permissions {

	/**
	 * Option name the permissions map is stored under.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'dak_role_permissions';

	/**
	 * Doctor dashboard's toggle-able tabs, slug => label. Matches the
	 * whitelist in Doctor_Dashboard::requested_tab().
	 *
	 * @return array
	 */
	public static function doctor_tabs() {
		return array(
			'profile'            => __( 'Profile', 'doctor-ak-portal' ),
			'clinics'            => __( 'Clinics', 'doctor-ak-portal' ),
			'services'           => __( 'Services', 'doctor-ak-portal' ),
			'video-consultation' => __( 'Video Consultation', 'doctor-ak-portal' ),
			'appointments'       => __( 'Appointments', 'doctor-ak-portal' ),
			'patients'           => __( 'Patients', 'doctor-ak-portal' ),
			'notifications'      => __( 'Notifications', 'doctor-ak-portal' ),
			'settings'           => __( 'Settings', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Patient dashboard's toggle-able tabs, slug => label. Matches the
	 * whitelist in Patient_Dashboard::requested_tab().
	 *
	 * @return array
	 */
	public static function patient_tabs() {
		return array(
			'profile'         => __( 'Profile', 'doctor-ak-portal' ),
			'appointments'    => __( 'Appointments', 'doctor-ak-portal' ),
			'notifications'   => __( 'Notifications', 'doctor-ak-portal' ),
			'medical-history' => __( 'Medical History', 'doctor-ak-portal' ),
			'payments'        => __( 'Payments', 'doctor-ak-portal' ),
			'settings'        => __( 'Settings', 'doctor-ak-portal' ),
		);
	}

	/**
	 * The saved permissions map: role => tab slug => bool.
	 *
	 * @return array
	 */
	public static function get_all() {
		$saved = get_option( self::OPTION_KEY, array() );

		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Whether a role is allowed to see/use a given tab. Defaults to
	 * true (allowed) for any tab without an explicit stored value —
	 * including 'dashboard', which is never stored/toggle-able.
	 *
	 * @param string $role Roles::DOCTOR_ROLE or Roles::PATIENT_ROLE.
	 * @param string $tab  Tab slug.
	 * @return bool
	 */
	public static function is_tab_allowed( $role, $tab ) {
		if ( 'dashboard' === $tab ) {
			return true;
		}

		$saved = self::get_all();

		if ( ! isset( $saved[ $role ][ $tab ] ) ) {
			return true;
		}

		return (bool) $saved[ $role ][ $tab ];
	}

	/**
	 * Validates and sanitizes the Settings → Roles & Permissions admin
	 * form's posted checkboxes into this class's stored shape. A missing
	 * checkbox means "off" (unchecked checkboxes aren't posted at all), so
	 * every known tab for both roles is explicitly written — there's no
	 * partial/sparse save.
	 *
	 * @param array $posted Raw `dak_role_permissions[role][tab]` nested array (only checked boxes present).
	 * @return array Sanitized map, see get_all().
	 */
	public static function sanitize_from_request( array $posted ) {
		$roles = array(
			Roles::DOCTOR_ROLE  => self::doctor_tabs(),
			Roles::PATIENT_ROLE => self::patient_tabs(),
		);

		$sanitized = array();

		foreach ( $roles as $role => $tabs ) {
			$posted_role = isset( $posted[ $role ] ) && is_array( $posted[ $role ] ) ? $posted[ $role ] : array();

			foreach ( $tabs as $tab_slug => $tab_label ) {
				$sanitized[ $role ][ $tab_slug ] = ! empty( $posted_role[ $tab_slug ] );
			}
		}

		return $sanitized;
	}
}
