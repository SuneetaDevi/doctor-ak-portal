<?php
/**
 * Settings → Roles & Permissions admin page.
 *
 * @package DoctorAKPortal\Admin
 */

namespace DoctorAKPortal\Admin;

use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Role_Permissions_Settings
 *
 * Lets an administrator turn dashboard tabs on/off per role (doctor/
 * patient) — e.g. unchecking "Profile" for Doctor means doctors no longer
 * see or can reach the Edit Profile page. The "Dashboard" overview tab
 * itself isn't listed here since it's always available.
 */
class Role_Permissions_Settings {

	/**
	 * Registers the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Roles & Permissions', 'doctor-ak-portal' ),
			__( 'Roles & Permissions', 'doctor-ak-portal' ),
			'manage_options',
			'doctor-ak-role-permissions',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'doctor_ak_role_permissions',
			Role_Permissions::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * @param mixed $value Raw posted value.
	 * @return array
	 */
	public function sanitize( $value ) {
		return Role_Permissions::sanitize_from_request( is_array( $value ) ? $value : array() );
	}

	/**
	 * Renders one role's block of tab checkboxes.
	 *
	 * @param string $role_label  Displayed role name, e.g. "Doctor".
	 * @param string $role        Roles::DOCTOR_ROLE or Roles::PATIENT_ROLE.
	 * @param array  $tabs        Tab slug => label.
	 * @param array  $saved       This role's saved slug => bool map.
	 * @return void
	 */
	private static function render_role_block( $role_label, $role, array $tabs, array $saved ) {
		?>
		<h2><?php echo esc_html( $role_label ); ?></h2>
		<table class="form-table" role="presentation">
			<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $tab_label ); ?></th>
					<td>
						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( Role_Permissions::OPTION_KEY ); ?>[<?php echo esc_attr( $role ); ?>][<?php echo esc_attr( $tab_slug ); ?>]"
								value="1"
								<?php checked( ! isset( $saved[ $tab_slug ] ) || $saved[ $tab_slug ] ); ?>
							>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: role name (Doctor/Patient), 2: tab name (e.g. Profile). */
									__( '%1$ss can see and use %2$s', 'doctor-ak-portal' ),
									$role_label,
									$tab_label
								)
							);
							?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Renders the settings form.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved = Role_Permissions::get_all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Roles & Permissions', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Choose which dashboard pages doctors and patients can see. Turning a page off hides its menu link and blocks direct access to it — the Dashboard overview itself is always available.', 'doctor-ak-portal' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'doctor_ak_role_permissions' ); ?>

				<?php
				self::render_role_block(
					__( 'Doctor', 'doctor-ak-portal' ),
					Roles::DOCTOR_ROLE,
					Role_Permissions::doctor_tabs(),
					isset( $saved[ Roles::DOCTOR_ROLE ] ) ? $saved[ Roles::DOCTOR_ROLE ] : array()
				);

				self::render_role_block(
					__( 'Patient', 'doctor-ak-portal' ),
					Roles::PATIENT_ROLE,
					Role_Permissions::patient_tabs(),
					isset( $saved[ Roles::PATIENT_ROLE ] ) ? $saved[ Roles::PATIENT_ROLE ] : array()
				);
				?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
