<?php
/**
 * Settings → Footer Settings admin page.
 *
 * @package DoctorAKPortal\Admin
 */

namespace DoctorAKPortal\Admin;

use DoctorAKPortal\Frontend\Site_Footer;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Footer_Settings
 *
 * Lets an administrator fill in the site-wide footer's real content —
 * description, booking phone, social links, and the clinic's actual
 * name/address/phone — mirroring Notification_Settings' shape. The "Quick
 * Links" and "Our Services" columns are handled separately, as real
 * WordPress menus (Appearance -> Menus), not settings here.
 */
class Footer_Settings {

	/**
	 * Registers the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Footer Settings', 'doctor-ak-portal' ),
			__( 'Footer Settings', 'doctor-ak-portal' ),
			'manage_options',
			'doctor-ak-footer-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers each option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_DESCRIPTION, array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_PHONE, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_FACEBOOK_URL, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_TWITTER_URL, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_INSTAGRAM_URL, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_LINKEDIN_URL, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_CLINIC_NAME, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_CLINIC_ADDRESS, array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_CLINIC_PHONE, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'doctor_ak_footer_settings', Site_Footer::OPTION_COPYRIGHT_NAME, array( 'sanitize_callback' => 'sanitize_text_field' ) );
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
			<h1><?php esc_html_e( 'Footer Settings', 'doctor-ak-portal' ); ?></h1>
			<p>
				<?php
				esc_html_e(
					'Content for the site-wide footer shown on every page. The "Quick Links" and "Our Services" columns are managed separately under Appearance -> Menus ("Footer Quick Links" and "Footer Services").',
					'doctor-ak-portal'
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'doctor_ak_footer_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dak-footer-description"><?php esc_html_e( 'Description', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" id="dak-footer-description" name="<?php echo esc_attr( Site_Footer::OPTION_DESCRIPTION ); ?>"><?php echo esc_textarea( get_option( Site_Footer::OPTION_DESCRIPTION, '' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-footer-phone"><?php esc_html_e( 'Booking Phone Number', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="dak-footer-phone" name="<?php echo esc_attr( Site_Footer::OPTION_PHONE ); ?>" value="<?php echo esc_attr( get_option( Site_Footer::OPTION_PHONE, '' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Shown under "For Booking" in the footer.', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Social Links', 'doctor-ak-portal' ); ?></th>
						<td>
							<p>
								<label for="dak-footer-facebook"><?php esc_html_e( 'Facebook URL', 'doctor-ak-portal' ); ?></label><br>
								<input type="url" class="regular-text" id="dak-footer-facebook" name="<?php echo esc_attr( Site_Footer::OPTION_FACEBOOK_URL ); ?>" value="<?php echo esc_attr( get_option( Site_Footer::OPTION_FACEBOOK_URL, '' ) ); ?>">
							</p>
							<p>
								<label for="dak-footer-twitter"><?php esc_html_e( 'X (Twitter) URL', 'doctor-ak-portal' ); ?></label><br>
								<input type="url" class="regular-text" id="dak-footer-twitter" name="<?php echo esc_attr( Site_Footer::OPTION_TWITTER_URL ); ?>" value="<?php echo esc_attr( get_option( Site_Footer::OPTION_TWITTER_URL, '' ) ); ?>">
							</p>
							<p>
								<label for="dak-footer-instagram"><?php esc_html_e( 'Instagram URL', 'doctor-ak-portal' ); ?></label><br>
								<input type="url" class="regular-text" id="dak-footer-instagram" name="<?php echo esc_attr( Site_Footer::OPTION_INSTAGRAM_URL ); ?>" value="<?php echo esc_attr( get_option( Site_Footer::OPTION_INSTAGRAM_URL, '' ) ); ?>">
							</p>
							<p>
								<label for="dak-footer-linkedin"><?php esc_html_e( 'LinkedIn URL', 'doctor-ak-portal' ); ?></label><br>
								<input type="url" class="regular-text" id="dak-footer-linkedin" name="<?php echo esc_attr( Site_Footer::OPTION_LINKEDIN_URL ); ?>" value="<?php echo esc_attr( get_option( Site_Footer::OPTION_LINKEDIN_URL, '' ) ); ?>">
							</p>
							<p class="description"><?php esc_html_e( 'Leave any of these blank to hide that icon — no dead links are shown.', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-footer-clinic-name"><?php esc_html_e( 'Clinic Name', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="dak-footer-clinic-name" name="<?php echo esc_attr( Site_Footer::OPTION_CLINIC_NAME ); ?>" value="<?php echo esc_attr( get_option( Site_Footer::OPTION_CLINIC_NAME, '' ) ); ?>" placeholder="<?php esc_attr_e( 'Main Clinic', 'doctor-ak-portal' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-footer-clinic-address"><?php esc_html_e( 'Clinic Address', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="2" id="dak-footer-clinic-address" name="<?php echo esc_attr( Site_Footer::OPTION_CLINIC_ADDRESS ); ?>"><?php echo esc_textarea( get_option( Site_Footer::OPTION_CLINIC_ADDRESS, '' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-footer-clinic-phone"><?php esc_html_e( 'Clinic Contact Phone', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="dak-footer-clinic-phone" name="<?php echo esc_attr( Site_Footer::OPTION_CLINIC_PHONE ); ?>" value="<?php echo esc_attr( get_option( Site_Footer::OPTION_CLINIC_PHONE, '' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-footer-copyright-name"><?php esc_html_e( 'Copyright Name', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="dak-footer-copyright-name" name="<?php echo esc_attr( Site_Footer::OPTION_COPYRIGHT_NAME ); ?>" value="<?php echo esc_attr( get_option( Site_Footer::OPTION_COPYRIGHT_NAME, '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'blogname' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Shown as "Copyright © [year] [name]. All Rights Reserved." Defaults to the site name when left blank.', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
