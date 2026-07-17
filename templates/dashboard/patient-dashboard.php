<?php
/**
 * Template: Patient dashboard body for the [patient_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var \WP_User $user               Currently logged-in patient.
 * @var int      $profile_completion Percentage 0-100.
 * @var string   $phone_number       Patient's phone number, if provided.
 * @var string   $profile_url        URL of the Edit Profile page.
 * @var string   $directory_url      URL of the [doctors_directory] page.
 * @var string   $logout_url         Nonce-protected logout URL.
 * @var string   $theme              'light' or 'dark' — the patient's saved dashboard theme preference.
 * @var string   $active_tab         'dashboard' or 'settings'.
 * @var string   $dashboard_url      URL of this dashboard page.
 * @var string   $settings_url       Same-page URL for the Settings tab.
 * @var string   $settings_tab_html  Pre-rendered dashboard-settings-tab.php output when $active_tab is 'settings'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$first_name = $user->first_name ? $user->first_name : $user->display_name;
?>
<div class="dak-portal dak-dashboard" data-role="patient" data-theme="<?php echo esc_attr( $theme ); ?>">
	<button type="button" class="dak-dashboard-sidebar-toggle" id="dak-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle navigation', 'doctor-ak-portal' ); ?>" aria-expanded="false" aria-controls="dak-dashboard-sidebar">
		<span></span><span></span><span></span>
	</button>

	<aside class="dak-dashboard-sidebar" id="dak-dashboard-sidebar">
		<nav class="dak-dashboard-nav">
			<ul>
				<li class="<?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Dashboard', 'doctor-ak-portal' ); ?></a></li>
				<?php if ( $directory_url ) : ?>
					<li><a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<li><a href="#dak-patient-appointments"><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></a></li>
				<li class="is-disabled"><span><?php esc_html_e( 'Medical History', 'doctor-ak-portal' ); ?> <em><?php esc_html_e( '(Coming Soon)', 'doctor-ak-portal' ); ?></em></span></li>
				<?php if ( $profile_url ) : ?>
					<li><a href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Profile', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $settings_url ) : ?>
					<li class="<?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<li><a href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Logout', 'doctor-ak-portal' ); ?></a></li>
			</ul>
		</nav>
	</aside>

	<main class="dak-dashboard-main">
		<?php if ( 'settings' === $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></h1>
			</div>

			<div class="dak-dashboard-card">
				<?php echo $settings_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own dashboard-settings-tab.php template, which escapes its own output. ?>
			</div>

		<?php else : ?>

		<header class="dak-dashboard-header">
			<h5>
				<?php
				/* translators: %s: patient's first name or display name. */
				echo esc_html( sprintf( __( 'Welcome, %s', 'doctor-ak-portal' ), $first_name ) );
				?>
			</h5>
		</header>

		<section class="dak-dashboard-statistics">
			<div class="dak-stat-card">
				<span class="dak-stat-value"><?php echo esc_html( $profile_completion ); ?>%</span>
				<span class="dak-stat-label"><?php esc_html_e( 'Profile Completion', 'doctor-ak-portal' ); ?></span>
			</div>
			<div class="dak-stat-card">
				<span class="dak-stat-value"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ); ?></span>
				<span class="dak-stat-label"><?php esc_html_e( 'Member Since', 'doctor-ak-portal' ); ?></span>
			</div>
		</section>

		<div class="dak-dashboard-grid">
			<section class="dak-dashboard-card dak-dashboard-appointments" id="dak-patient-appointments">
				<div class="dak-dashboard-card-header">
					<h2><?php esc_html_e( 'Upcoming Appointments', 'doctor-ak-portal' ); ?></h2>
					<button type="button" class="dak-button dak-button-primary dak-button-small" data-dak-book-appointment>
						<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
					</button>
				</div>
				<?php
				ob_start();
				/**
				 * Fires inside the patient dashboard's upcoming-appointments card.
				 *
				 * A future booking/appointment module can hook here to render
				 * real appointment data without modifying this template.
				 *
				 * @param \WP_User $user Currently viewed patient.
				 */
				do_action( 'doctor_ak_patient_dashboard_appointments', $user );
				$appointments_output = trim( ob_get_clean() );

				if ( '' !== $appointments_output ) {
					echo $appointments_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hooked content is responsible for its own escaping.
				} else {
					?>
					<p class="dak-empty-state"><?php esc_html_e( 'No upcoming appointments yet.', 'doctor-ak-portal' ); ?></p>
					<?php
				}
				?>
			</section>

			<section class="dak-dashboard-card dak-dashboard-profile-completion">
				<h2><?php esc_html_e( 'Profile Completion', 'doctor-ak-portal' ); ?></h2>
				<div class="dak-progress-bar">
					<div class="dak-progress-bar-fill" style="width: <?php echo esc_attr( $profile_completion ); ?>%;"></div>
				</div>
				<p>
					<?php
					/* translators: %d: profile completion percentage. */
					echo esc_html( sprintf( __( '%d%% complete', 'doctor-ak-portal' ), $profile_completion ) );
					?>
				</p>
				<?php if ( $profile_completion < 100 && $profile_url ) : ?>
					<a class="dak-link" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Complete your profile', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</section>

			<section class="dak-dashboard-card dak-dashboard-notifications">
				<h2><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?></h2>
				<?php
				ob_start();
				/**
				 * Fires inside the patient dashboard's notifications card.
				 *
				 * @param \WP_User $user Currently viewed patient.
				 */
				do_action( 'doctor_ak_patient_dashboard_notifications', $user );
				$notifications_output = trim( ob_get_clean() );

				if ( '' !== $notifications_output ) {
					echo $notifications_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hooked content is responsible for its own escaping.
				} else {
					?>
					<p class="dak-empty-state"><?php esc_html_e( 'You have no new notifications.', 'doctor-ak-portal' ); ?></p>
					<?php
				}
				?>
			</section>
		</div>

		<?php endif; ?>
	</main>
</div>
