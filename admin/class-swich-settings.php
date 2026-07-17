<?php
/**
 * Settings → Swich Payments admin page.
 *
 * @package DoctorAKPortal\Admin
 */

namespace DoctorAKPortal\Admin;

use DoctorAKPortal\Includes\Swich_Payment;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Swich_Settings
 *
 * Lets an administrator enter the Swich PayIn gateway credentials and the
 * flat video-consultation fee through wp-admin, rather than hardcoding
 * secrets into the plugin's source. Any setting also has a matching
 * DOCTOR_AK_SWICH_* wp-config.php constant that takes precedence, for
 * sites that prefer to keep secrets out of the database entirely.
 */
class Swich_Settings {

	/**
	 * Registers the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Swich Payments', 'doctor-ak-portal' ),
			__( 'Swich Payments', 'doctor-ak-portal' ),
			'manage_options',
			'doctor-ak-swich-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers each option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'doctor_ak_swich_settings',
			Swich_Payment::OPTION_ENVIRONMENT,
			array(
				'sanitize_callback' => array( $this, 'sanitize_environment' ),
				'default'           => 'sandbox',
			)
		);
		register_setting( 'doctor_ak_swich_settings', Swich_Payment::OPTION_CLIENT_ID, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'doctor_ak_swich_settings', Swich_Payment::OPTION_CLIENT_SECRET, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'doctor_ak_swich_settings', Swich_Payment::OPTION_PWA_SECRET_KEY, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting(
			'doctor_ak_swich_settings',
			Swich_Payment::OPTION_FEE,
			array(
				'sanitize_callback' => array( $this, 'sanitize_fee' ),
				'default'           => '500',
			)
		);
	}

	/**
	 * @param string $value Raw posted value.
	 * @return string
	 */
	public function sanitize_environment( $value ) {
		return 'production' === $value ? 'production' : 'sandbox';
	}

	/**
	 * @param string $value Raw posted value.
	 * @return string
	 */
	public function sanitize_fee( $value ) {
		$fee = (float) $value;

		return number_format( max( 10, min( 50000, $fee ) ), 2, '.', '' );
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Swich Payment Settings', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Credentials for the Swich PayIn gateway, used to charge patients for online video consultations before they are confirmed.', 'doctor-ak-portal' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'doctor_ak_swich_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dak-swich-environment"><?php esc_html_e( 'Environment', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<select id="dak-swich-environment" name="<?php echo esc_attr( Swich_Payment::OPTION_ENVIRONMENT ); ?>">
								<option value="sandbox" <?php selected( get_option( Swich_Payment::OPTION_ENVIRONMENT, 'sandbox' ), 'sandbox' ); ?>><?php esc_html_e( 'Sandbox', 'doctor-ak-portal' ); ?></option>
								<option value="production" <?php selected( get_option( Swich_Payment::OPTION_ENVIRONMENT, 'sandbox' ), 'production' ); ?>><?php esc_html_e( 'Production', 'doctor-ak-portal' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-swich-client-id"><?php esc_html_e( 'Client ID', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="dak-swich-client-id" name="<?php echo esc_attr( Swich_Payment::OPTION_CLIENT_ID ); ?>" value="<?php echo esc_attr( get_option( Swich_Payment::OPTION_CLIENT_ID, '' ) ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Same Client ID Swich issued for both the API and the PWA.', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-swich-client-secret"><?php esc_html_e( 'API Client Secret', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="dak-swich-client-secret" name="<?php echo esc_attr( Swich_Payment::OPTION_CLIENT_SECRET ); ?>" value="<?php echo esc_attr( get_option( Swich_Payment::OPTION_CLIENT_SECRET, '' ) ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Used to obtain a bearer token for server-to-server API calls (e.g. transaction inquiry).', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-swich-pwa-secret"><?php esc_html_e( 'PWA Secret Key', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="dak-swich-pwa-secret" name="<?php echo esc_attr( Swich_Payment::OPTION_PWA_SECRET_KEY ); ?>" value="<?php echo esc_attr( get_option( Swich_Payment::OPTION_PWA_SECRET_KEY, '' ) ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Used to sign the payment page checksum and verify Swich\'s callback.', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-swich-fee"><?php esc_html_e( 'Video Consultation Fee (PKR)', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="number" min="10" max="50000" step="0.01" id="dak-swich-fee" name="<?php echo esc_attr( Swich_Payment::OPTION_FEE ); ?>" value="<?php echo esc_attr( get_option( Swich_Payment::OPTION_FEE, '500' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Flat fee charged for every online video consultation (10.00–50000.00 PKR).', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p class="description">
				<?php
				printf(
					/* translators: %s: admin-ajax.php callback URL. */
					esc_html__( 'In the Swich merchant portal, set your PayIn callback URL to: %s', 'doctor-ak-portal' ),
					'<code>' . esc_html( admin_url( 'admin-ajax.php?action=doctor_ak_swich_callback' ) ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
