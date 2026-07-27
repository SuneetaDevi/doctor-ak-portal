<?php
/**
 * Template: Admin dashboard "Roles & Permissions" section — lets an admin
 * turn dashboard tabs on/off per role (doctor/patient), the front-end
 * equivalent of wp-admin's Settings → Roles & Permissions page.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_tabs  Tab slug => label, see Role_Permissions::doctor_tabs().
 * @var array $patient_tabs Tab slug => label, see Role_Permissions::patient_tabs().
 * @var array $saved        role => tab slug => bool, see Role_Permissions::get_all().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders one role's block of tab checkboxes.
 *
 * @param string $role_label Displayed role name, e.g. "Doctor".
 * @param string $role       Role slug, used in the checkbox name attribute.
 * @param array  $tabs       Tab slug => label.
 * @param array  $saved_role This role's saved slug => bool map.
 * @return void
 */
if ( ! function_exists( 'dak_render_role_permissions_block' ) ) :
	function dak_render_role_permissions_block( $role_label, $role, array $tabs, array $saved_role ) {
		?>
		<h3><?php echo esc_html( $role_label ); ?></h3>
		<table class="dak-admin-users-table">
			<tbody>
				<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
					<tr>
						<td><?php echo esc_html( $tab_label ); ?></td>
						<td>
							<label class="dak-checkbox-label">
								<input
									type="checkbox"
									name="permissions[<?php echo esc_attr( $role ); ?>][<?php echo esc_attr( $tab_slug ); ?>]"
									value="1"
									<?php checked( ! isset( $saved_role[ $tab_slug ] ) || $saved_role[ $tab_slug ] ); ?>
								>
								<?php esc_html_e( 'Visible', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
endif;
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Roles & Permissions', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Choose which dashboard pages doctors and patients can see. Turning a page off hides its menu link and blocks direct access to it — the Dashboard overview itself is always available.', 'doctor-ak-portal' ); ?></p>
</div>

<section class="dak-dashboard-card dak-admin-users-card" id="dak-role-permissions-form">
	<div class="dak-alert dak-alert-error dak-hidden" id="dak-role-permissions-error" role="alert"></div>
	<div class="dak-alert dak-alert-success dak-hidden" id="dak-role-permissions-success" role="status"></div>

	<?php
	dak_render_role_permissions_block(
		__( 'Doctor', 'doctor-ak-portal' ),
		'doctor',
		$doctor_tabs,
		isset( $saved['doctor'] ) ? $saved['doctor'] : array()
	);

	dak_render_role_permissions_block(
		__( 'Patient', 'doctor-ak-portal' ),
		'patient',
		$patient_tabs,
		isset( $saved['patient'] ) ? $saved['patient'] : array()
	);
	?>

	<button type="button" class="dak-button dak-button-primary" id="dak-role-permissions-save">
		<span class="dak-button-label"><?php esc_html_e( 'Save Permissions', 'doctor-ak-portal' ); ?></span>
	</button>
</section>
