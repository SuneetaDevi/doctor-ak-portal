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

// Only a page that exists for a portal gets a checkbox (Role_Permissions::
// is_supported()), ticked per Role_Permissions::is_tab_allowed() — so what
// the matrix shows is exactly what each portal gets. Receptionists' extra
// pages (Video Consultation, Billing, …) start unticked.
$dak_portals = array(
	'admin'        => array( __( 'Admin', 'doctor-ak-portal' ), $admin_tabs ),
	'doctor'       => array( __( 'Doctor', 'doctor-ak-portal' ), $doctor_tabs ),
	'patient'      => array( __( 'Patient', 'doctor-ak-portal' ), $patient_tabs ),
	'receptionist' => array( __( 'Receptionist', 'doctor-ak-portal' ), $receptionist_tabs ),
);
?>
<div class="dak-list-page dak-form-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Roles & Permissions', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Choose which dashboard pages each portal can see. Turning a page off hides its menu link and blocks direct access — the Dashboard overview and this page are always available, so a mistake here can always be undone. Receptionists can also be given Video Consultation, Billing, Doctor Requests, Reviews, Blogs and Locations; pages marked “Admin only” can’t be given to them.', 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<section class="dak-results" id="dak-role-permissions-form" aria-labelledby="dak-role-permissions-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-role-permissions-title"><?php esc_html_e( 'Permission matrix', 'doctor-ak-portal' ); ?></h2>
		<span class="dak-cell-sub"><?php esc_html_e( 'Changes apply the next time each person opens or refreshes a page.', 'doctor-ak-portal' ); ?></span>
	</div>

	<div class="dak-results-body dak-results-messages">
		<div class="dak-alert dak-alert-error dak-hidden" id="dak-role-permissions-error" role="alert"></div>
		<div class="dak-alert dak-alert-success dak-hidden" id="dak-role-permissions-success" role="status"></div>
	</div>

	<div class="dak-data-table-wrap">
		<table class="dak-data-table dak-ui-table dak-permission-matrix">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Page / module', 'doctor-ak-portal' ); ?></th>
					<?php foreach ( $dak_portals as $dak_portal ) : ?>
						<th scope="col"><?php echo esc_html( $dak_portal[0] ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dak_all_tab_slugs as $dak_tab_slug => $dak_tab_label ) : ?>
					<tr data-row>
						<th scope="row" class="dak-col-primary"><?php echo esc_html( $dak_tab_label ); ?></th>
						<?php foreach ( $dak_portals as $dak_portal_key => $dak_portal ) : ?>
							<?php $dak_portal_label = $dak_portal[0]; ?>
							<?php if ( ! \DoctorAKPortal\Includes\Role_Permissions::is_supported( $dak_portal_key, $dak_tab_slug ) ) : ?>
								<?php
								// No such page for this portal, so a checkbox here
								// would change nothing — say so instead.
								$dak_admin_only = 'receptionist' === $dak_portal_key && isset( $admin_tabs[ $dak_tab_slug ] );
								$dak_na_text    = $dak_admin_only
									? __( 'Admin only', 'doctor-ak-portal' )
									/* translators: %s: portal name, e.g. Doctor. */
									: sprintf( __( 'Not in the %s dashboard', 'doctor-ak-portal' ), $dak_portal_label );
								?>
								<td class="dak-permission-na" data-label="<?php echo esc_attr( $dak_portal_label ); ?>" title="<?php echo esc_attr( $dak_na_text ); ?>">
									<?php if ( $dak_admin_only ) : ?>
										<span class="dak-permission-na-tag"><?php esc_html_e( 'Admin only', 'doctor-ak-portal' ); ?></span>
									<?php else : ?>
										<span aria-hidden="true">—</span><span class="dak-visually-hidden"><?php echo esc_html( $dak_na_text ); ?></span>
									<?php endif; ?>
								</td>
							<?php else : ?>
								<td data-label="<?php echo esc_attr( $dak_portal_label ); ?>">
									<input
										type="checkbox"
										name="permissions[<?php echo esc_attr( $dak_portal_key ); ?>][<?php echo esc_attr( $dak_tab_slug ); ?>]"
										value="1"
										aria-label="<?php echo esc_attr( sprintf( /* translators: 1: page/module name, 2: portal name. */ __( '%1$s — %2$s portal', 'doctor-ak-portal' ), $dak_tab_label, $dak_portal_label ) ); ?>"
										<?php checked( \DoctorAKPortal\Includes\Role_Permissions::is_tab_allowed( $dak_portal_key, $dak_tab_slug ) ); ?>
									>
								</td>
							<?php endif; ?>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="dak-results-footer">
		<button type="button" class="dak-button dak-button-primary" id="dak-role-permissions-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save permissions', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</section>
</div>