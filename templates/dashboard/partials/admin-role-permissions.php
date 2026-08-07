<?php
/**
 * Template: Admin dashboard "Roles & Permissions" section — lets an admin
 * turn dashboard tabs on/off per role (doctor/patient), the front-end
 * equivalent of wp-admin's Settings → Roles & Permissions page.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_tabs       Tab slug => label, see Role_Permissions::doctor_tabs().
 * @var array $patient_tabs      Tab slug => label, see Role_Permissions::patient_tabs().
 * @var array $receptionist_tabs Tab slug => label, see Role_Permissions::receptionist_tabs().
 * @var array $saved             role => tab slug => bool, see Role_Permissions::get_all().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Union of every toggle-able tab across all three roles, in Doctor's own
// order first (then any Patient-only, then Receptionist-only tabs appended)
// — one row per tab, with a checkbox in whichever role column(s) that tab
// actually applies to.
$dak_all_tab_slugs     = $doctor_tabs + $patient_tabs + $receptionist_tabs;
$dak_saved_doctor      = isset( $saved['doctor'] ) ? $saved['doctor'] : array();
$dak_saved_patient     = isset( $saved['patient'] ) ? $saved['patient'] : array();
$dak_saved_receptionist = isset( $saved['receptionist'] ) ? $saved['receptionist'] : array();
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Role & Permissions', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Choose which dashboard pages doctors, patients, and receptionists can see. Turning a page off hides its menu link and blocks direct access to it — the Dashboard overview itself is always available. Admins always have full access.', 'doctor-ak-portal' ); ?></p>
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
					<th><?php esc_html_e( 'Admin', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Receptionist', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dak_all_tab_slugs as $dak_tab_slug => $dak_tab_label ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $dak_tab_label ); ?></strong></td>
						<td>
							<input type="checkbox" checked disabled title="<?php esc_attr_e( 'Admins always have full access', 'doctor-ak-portal' ); ?>">
						</td>
						<td>
							<?php if ( isset( $doctor_tabs[ $dak_tab_slug ] ) ) : ?>
								<input
									type="checkbox"
									name="permissions[doctor][<?php echo esc_attr( $dak_tab_slug ); ?>]"
									value="1"
									<?php checked( ! isset( $dak_saved_doctor[ $dak_tab_slug ] ) || $dak_saved_doctor[ $dak_tab_slug ] ); ?>
								>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td>
							<?php if ( isset( $patient_tabs[ $dak_tab_slug ] ) ) : ?>
								<input
									type="checkbox"
									name="permissions[patient][<?php echo esc_attr( $dak_tab_slug ); ?>]"
									value="1"
									<?php checked( ! isset( $dak_saved_patient[ $dak_tab_slug ] ) || $dak_saved_patient[ $dak_tab_slug ] ); ?>
								>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td>
							<?php if ( isset( $receptionist_tabs[ $dak_tab_slug ] ) ) : ?>
								<input
									type="checkbox"
									name="permissions[receptionist][<?php echo esc_attr( $dak_tab_slug ); ?>]"
									value="1"
									<?php checked( ! isset( $dak_saved_receptionist[ $dak_tab_slug ] ) || $dak_saved_receptionist[ $dak_tab_slug ] ); ?>
								>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
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
