<?php
/**
 * Settings → Email Notifications admin page.
 *
 * @package DoctorAKPortal\Admin
 */

namespace DoctorAKPortal\Admin;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Notifications;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notification_Settings
 *
 * Lets an administrator turn each appointment email type on/off and set a
 * custom From name/email, mirroring Swich_Settings' shape.
 */
class Notification_Settings {

	/**
	 * Registers the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Email Notifications', 'doctor-ak-portal' ),
			__( 'Email Notifications', 'doctor-ak-portal' ),
			'manage_options',
			'doctor-ak-notification-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers each option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		foreach ( array( Notifications::OPTION_NOTIFY_BOOKING, Notifications::OPTION_NOTIFY_CANCELLED, Notifications::OPTION_NOTIFY_PAID, Notifications::OPTION_NOTIFY_REMINDER, Notifications::OPTION_NOTIFY_VIDEO_LINK, Notifications::OPTION_NOTIFY_DOCTOR_REGISTRATION, Notifications::OPTION_NOTIFY_REFUND, Notifications::OPTION_NOTIFY_RESCHEDULED, Notifications::OPTION_NOTIFY_PATIENT_WELCOME ) as $option ) {
			register_setting(
				'doctor_ak_notification_settings',
				$option,
				array(
					'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
					'default'           => '1',
				)
			);
		}

		register_setting( 'doctor_ak_notification_settings', Notifications::OPTION_FROM_NAME, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'doctor_ak_notification_settings', Notifications::OPTION_FROM_EMAIL, array( 'sanitize_callback' => 'sanitize_email' ) );
	}

	/**
	 * @param mixed $value Raw posted value.
	 * @return string
	 */
	public function sanitize_checkbox( $value ) {
		return ! empty( $value ) ? '1' : '0';
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
			<h1><?php esc_html_e( 'Email Notification Settings', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Control which appointment emails are sent to patients and doctors.', 'doctor-ak-portal' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'doctor_ak_notification_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Confirmed', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_BOOKING ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_BOOKING, '1' ) ); ?>>
								<?php esc_html_e( 'Email the patient and doctor when a new appointment is booked.', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Appointment Cancelled', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_CANCELLED ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_CANCELLED, '1' ) ); ?>>
								<?php esc_html_e( 'Email the patient and doctor when an appointment is cancelled.', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Payment Received', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_PAID ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_PAID, '1' ) ); ?>>
								<?php esc_html_e( 'Email the patient (receipt) and doctor once an online payment is confirmed.', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reminder', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_REMINDER ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_REMINDER, '1' ) ); ?>>
								<?php esc_html_e( 'Email the patient and doctor the day before an upcoming appointment.', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Video Link Ready', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_VIDEO_LINK ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_VIDEO_LINK, '1' ) ); ?>>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: minutes before the appointment. */
										__( 'Email the patient and doctor the video consultation join link about %d minutes before it starts.', 'doctor-ak-portal' ),
										Appointments::VIDEO_JOIN_WINDOW_BEFORE_MINUTES
									)
								);
								?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Doctor Registration', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_DOCTOR_REGISTRATION ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_DOCTOR_REGISTRATION, '1' ) ); ?>>
								<?php esc_html_e( 'Email every administrator when a doctor registers, and email the doctor once their account is approved.', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Patient Welcome', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_PATIENT_WELCOME ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_PATIENT_WELCOME, '1' ) ); ?>>
								<?php esc_html_e( 'Email a new patient a welcome message with a link to log in, whether they registered themselves or were added by an admin/doctor.', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Appointment Rescheduled', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_RESCHEDULED ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_RESCHEDULED, '1' ) ); ?>>
								<?php esc_html_e( 'Email the patient and doctor when an appointment is rescheduled to a new date/time.', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Refund Requested/Processed', 'doctor-ak-portal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Notifications::OPTION_NOTIFY_REFUND ); ?>" value="1" <?php checked( '1', get_option( Notifications::OPTION_NOTIFY_REFUND, '1' ) ); ?>>
								<?php esc_html_e( 'Email every administrator when a patient requests a refund, and email the patient once an admin processes it.', 'doctor-ak-portal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-notify-from-name"><?php esc_html_e( 'From Name', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="dak-notify-from-name" name="<?php echo esc_attr( Notifications::OPTION_FROM_NAME ); ?>" value="<?php echo esc_attr( get_option( Notifications::OPTION_FROM_NAME, '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'blogname' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Defaults to the site name when left blank.', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dak-notify-from-email"><?php esc_html_e( 'From Email', 'doctor-ak-portal' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="dak-notify-from-email" name="<?php echo esc_attr( Notifications::OPTION_FROM_EMAIL ); ?>" value="<?php echo esc_attr( get_option( Notifications::OPTION_FROM_EMAIL, '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Defaults to the site admin email when left blank.', 'doctor-ak-portal' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
