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
	 * Pseudo-role key for the Admin column of the permission matrix — not a
	 * real Roles::*_ROLE (there's no separate "admin" WordPress role in this
	 * plugin; full admins are identified by the `manage_options` capability
	 * instead). This only lets the matrix store/show a checkbox per module
	 * for Admin alongside Doctor/Patient/Receptionist; no admin-facing page
	 * currently reads it to restrict anything.
	 *
	 * @var string
	 */
	const ADMIN_KEY = 'admin';

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
			'earnings'           => __( 'Earnings', 'doctor-ak-portal' ),
			'encounters'         => __( 'Encounters', 'doctor-ak-portal' ),
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
	 * Receptionist's toggle-able admin dashboard sections, slug => label.
	 * Matches Admin_Dashboard::RECEPTIONIST_ALLOWED_SECTIONS (minus
	 * 'dashboard', which — like every role's Dashboard tab — is never
	 * toggle-able) — that constant is the hard ceiling of sections a
	 * receptionist could ever structurally reach; this lets an admin further
	 * restrict which of those a given install actually wants receptionists
	 * using.
	 *
	 * @return array
	 */
	public static function receptionist_tabs() {
		return array(
			'appointments'    => __( 'Appointments', 'doctor-ak-portal' ),
			'patients'        => __( 'Patients', 'doctor-ak-portal' ),
			'doctors'         => __( 'Doctors', 'doctor-ak-portal' ),
			'clinic'          => __( 'Clinic', 'doctor-ak-portal' ),
			'services'        => __( 'Services', 'doctor-ak-portal' ),
			'doctor-sessions' => __( 'Doctor Sessions', 'doctor-ak-portal' ),
			'encounters'      => __( 'Encounters', 'doctor-ak-portal' ),
			'notifications'   => __( 'Notifications', 'doctor-ak-portal' ),
			'settings'        => __( 'Settings', 'doctor-ak-portal' ),
		);
	}

	/**
	 * The full Admin dashboard's toggle-able sections, slug => label.
	 * Matches Admin_Dashboard::NAV_GROUPS minus 'dashboard' and
	 * 'role-permissions' — those two are a hard safety ceiling (see
	 * is_tab_allowed()) so an admin can never lock themselves out of the
	 * one screen that lets them undo a mistake here.
	 *
	 * @return array
	 */
	public static function admin_tabs() {
		return array(
			'appointments'       => __( 'Appointments', 'doctor-ak-portal' ),
			'billing'            => __( 'Billing', 'doctor-ak-portal' ),
			'encounters'         => __( 'Encounters', 'doctor-ak-portal' ),
			'notifications'      => __( 'Notifications', 'doctor-ak-portal' ),
			'all-users'          => __( 'All Users', 'doctor-ak-portal' ),
			'doctor-requests'    => __( 'Doctor Requests', 'doctor-ak-portal' ),
			'patients'           => __( 'Patients', 'doctor-ak-portal' ),
			'doctors'            => __( 'Doctors', 'doctor-ak-portal' ),
			'receptionist'       => __( 'Receptionist', 'doctor-ak-portal' ),
			'clinic'             => __( 'Clinic', 'doctor-ak-portal' ),
			'services'           => __( 'Services', 'doctor-ak-portal' ),
			'video-consultation' => __( 'Video Consultation', 'doctor-ak-portal' ),
			'doctor-sessions'    => __( 'Doctor Sessions', 'doctor-ak-portal' ),
			'locations'          => __( 'Locations', 'doctor-ak-portal' ),
			'settings'           => __( 'Settings', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Union of every toggle-able module across all four dashboards, slug =>
	 * label — Doctor's own tabs first, then any Patient-only,
	 * Receptionist-only, or Admin-only ones. The permission matrix shows one
	 * row per entry here, with a checkbox in every role column (even where
	 * that module isn't actually built into that role's own dashboard yet —
	 * see is_tab_allowed()'s docblock).
	 *
	 * @return array
	 */
	public static function all_tabs() {
		return self::doctor_tabs() + self::patient_tabs() + self::receptionist_tabs() + self::admin_tabs();
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
	 * true (allowed) for any tab without an explicit stored value.
	 *
	 * 'dashboard' and 'role-permissions' are a hard safety ceiling, always
	 * allowed regardless of what's saved — every account needs a landing
	 * page, and an admin must always be able to reach Roles & Permissions
	 * itself to undo a mistake here (otherwise a self-inflicted lockout
	 * would need a direct database edit to recover from).
	 *
	 * The permission matrix shows a checkbox for every module in every role
	 * column (see all_tabs()), including combinations no dashboard
	 * controller checks (e.g. Patient/"Clinics") — unchecking one of those
	 * saves the value here but has no visible effect until that dashboard's
	 * own controller is wired to call this method for it, same as every
	 * other tab already is.
	 *
	 * @param string $role Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE, Roles::RECEPTIONIST_ROLE, or self::ADMIN_KEY.
	 * @param string $tab  Tab slug.
	 * @return bool
	 */
	public static function is_tab_allowed( $role, $tab ) {
		if ( 'dashboard' === $tab || 'role-permissions' === $tab ) {
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
	 * every module (see all_tabs()) is explicitly written for all four
	 * columns — there's no partial/sparse save. Every column now covers the
	 * full module list, not just the tabs that role's own dashboard
	 * currently has, so the matrix can have a real, savable checkbox in
	 * every cell (see is_tab_allowed()'s docblock for what that does and
	 * doesn't enforce today).
	 *
	 * @param array $posted Raw `dak_role_permissions[role][tab]` nested array (only checked boxes present).
	 * @return array Sanitized map, see get_all().
	 */
	public static function sanitize_from_request( array $posted ) {
		$roles = array(
			self::ADMIN_KEY,
			Roles::DOCTOR_ROLE,
			Roles::PATIENT_ROLE,
			Roles::RECEPTIONIST_ROLE,
		);

		$tabs      = self::all_tabs();
		$sanitized = array();

		foreach ( $roles as $role ) {
			$posted_role = isset( $posted[ $role ] ) && is_array( $posted[ $role ] ) ? $posted[ $role ] : array();

			foreach ( $tabs as $tab_slug => $tab_label ) {
				$sanitized[ $role ][ $tab_slug ] = ! empty( $posted_role[ $tab_slug ] );
			}
		}

		return $sanitized;
	}
}
