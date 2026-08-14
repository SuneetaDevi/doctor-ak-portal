<?php
/**
 * Template: Admin dashboard "Roles & Permissions" section — lets an admin
 * turn dashboard tabs/modules on/off for any of the four portals (Admin,
 * Doctor, Patient, Receptionist), the front-end equivalent of wp-admin's
 * Settings → Roles & Permissions page.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $admin_tabs        Tab slug => label, see Role_Permissions::admin_tabs().
 * @var array $doctor_tabs       Tab slug => label, see Role_Permissions::doctor_tabs().
 * @var array $patient_tabs      Tab slug => label, see Role_Permissions::patient_tabs().
 * @var array $receptionist_tabs Tab slug => label, see Role_Permissions::receptionist_tabs().
 * @var array $saved             role => tab slug => bool, see Role_Permissions::get_all().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Union of every toggle-able module across all four dashboards — one row
// per module, with a real checkbox in every one of the four role columns.
$dak_all_tab_slugs = $admin_tabs + $doctor_tabs + $patient_tabs + $receptionist_tabs;

// Each portal's own native tab list — a cell whose module belongs to that
// portal's own dashboard defaults to checked (unchanged behaviour); a cell
// for a module that portal has no page for at all (e.g. Patient/"Clinics")
// defaults to UNCHECKED instead, since it was never really "on" to begin
// with — it's just not a meaningful choice for that portal.
$dak_portals = array(
	'admin'        => array( __( 'Admin', 'doctor-ak-portal' ), $admin_tabs ),
	'doctor'       => array( __( 'Doctor', 'doctor-ak-portal' ), $doctor_tabs ),
	'patient'      => array( __( 'Patient', 'doctor-ak-portal' ), $patient_tabs ),
	'receptionist' => array( __( 'Receptionist', 'doctor-ak-portal' ), $receptionist_tabs ),
);
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Role & Permissions', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Choose which dashboard pages each portal can see. Turning a page off hides its menu link and blocks direct access to it — the Dashboard overview and this Roles & Permissions page itself are always available, so a mistake here can always be undone.', 'doctor-ak-portal' ); ?></p>
</div>

<section class="dak-dashboard-card dak-admin-users-card" id="dak-role-permissions-form">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Permission matrix', 'doctor-ak-portal' ); ?></h2>
	</div>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-role-permissions-error" role="alert"></div>
	<div class="dak-alert dak-alert-success dak-hidden" id="dak-role-permissions-success" role="status"></div>

	<div class="dak-table-scroll">
		<table class="dak-admin-users-table dak-permission-matrix">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Capability', 'doctor-ak-portal' ); ?></th>
					<?php foreach ( $dak_portals as $dak_portal ) : ?>
						<th><?php echo esc_html( $dak_portal[0] ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dak_all_tab_slugs as $dak_tab_slug => $dak_tab_label ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $dak_tab_label ); ?></strong></td>
						<?php foreach ( $dak_portals as $dak_portal_key => $dak_portal ) : ?>
							<?php
							list( , $dak_portal_own_tabs ) = $dak_portal;
							$dak_saved_portal              = isset( $saved[ $dak_portal_key ] ) ? $saved[ $dak_portal_key ] : array();
							$dak_default_checked            = isset( $dak_portal_own_tabs[ $dak_tab_slug ] );
							$dak_is_checked                 = isset( $dak_saved_portal[ $dak_tab_slug ] ) ? $dak_saved_portal[ $dak_tab_slug ] : $dak_default_checked;
							?>
							<td>
								<input
									type="checkbox"
									name="permissions[<?php echo esc_attr( $dak_portal_key ); ?>][<?php echo esc_attr( $dak_tab_slug ); ?>]"
									value="1"
									<?php checked( $dak_is_checked ); ?>
								>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<p class="dak-field-hint"><?php esc_html_e( 'Changes apply on next login.', 'doctor-ak-portal' ); ?></p>

	<button type="button" class="dak-button dak-button-primary" id="dak-role-permissions-save">
		<span class="dak-button-label"><?php esc_html_e( 'Save Permissions', 'doctor-ak-portal' ); ?></span>
	</button>
</section>
