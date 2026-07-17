<?php
/**
 * Template: Patient dashboard body for the [patient_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var \WP_User $user                  Currently logged-in patient.
 * @var string   $avatar_url            Patient's profile picture URL, or '' if none set.
 * @var int      $profile_completion    Percentage 0-100.
 * @var array    $missing_profile_items Human labels for incomplete profile checks.
 * @var string   $phone_number          Patient's phone number, if provided.
 * @var array|null $next_appointment    Nearest upcoming appointment row, or null.
 * @var int      $unpaid_count          Unpaid upcoming appointment count.
 * @var float    $unpaid_total          Sum of unpaid upcoming charges.
 * @var array    $appointment_groups    'today'|'tomorrow'|'this_week'|'later' => array of appointment rows.
 * @var int      $total_upcoming_count  Total upcoming (non-cancelled) appointment count.
 * @var array    $recent_activity       List of { label, date }, most recent first.
 * @var string   $booking_url           URL of the booking page.
 * @var string   $video_booking_url     Booking page URL pre-set to the Video type.
 * @var string   $profile_url           URL of the Edit Profile page.
 * @var string   $directory_url         URL of the [doctors_directory] page.
 * @var string   $logout_url            Nonce-protected logout URL.
 * @var string   $theme                 'light' or 'dark' — the patient's saved dashboard theme preference.
 * @var string   $active_tab            'dashboard' or 'settings'.
 * @var string   $dashboard_url         URL of this dashboard page.
 * @var string   $settings_url          Same-page URL for the Settings tab.
 * @var string   $settings_tab_html     Pre-rendered dashboard-settings-tab.php output when $active_tab is 'settings'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$first_name   = $user->first_name ? $user->first_name : $user->display_name;
$display_name = trim( $user->first_name . ' ' . $user->last_name );
$display_name = '' !== $display_name ? $display_name : $user->display_name;

$dak_patient_icons = array(
	'dashboard' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="2.5" width="6.5" height="6.5" rx="1.2"/><rect x="11" y="2.5" width="6.5" height="6.5" rx="1.2"/><rect x="2.5" y="11" width="6.5" height="6.5" rx="1.2"/><rect x="11" y="11" width="6.5" height="6.5" rx="1.2"/></svg>',
	'calendar'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'users'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="2.8"/><path d="M1.8 16c0-2.9 2.3-4.8 5.2-4.8s5.2 1.9 5.2 4.8"/><path d="M13 7.2a2.6 2.6 0 1 1 3.6 2.4"/><path d="M14.5 11.3c2 .3 3.7 1.7 3.7 4"/></svg>',
	'person'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'settings'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="2.6"/><path d="M10 2.8v2M10 15.2v2M17.2 10h-2M4.8 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/></svg>',
	'logout'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 17H4a1.5 1.5 0 0 1-1.5-1.5v-11A1.5 1.5 0 0 1 4 3h3.5"/><path d="M13 14l4-4-4-4"/><path d="M17 10H7.5"/></svg>',
	'plus'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4v12M4 10h12"/></svg>',
	'video'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="11" height="10" rx="1.5"/><path d="M13 8.3l5-2.8v9l-5-2.8"/></svg>',
);

$has_any_upcoming = ! empty( array_filter( $appointment_groups ) );

$appointment_group_labels = array(
	'today'     => __( 'Today', 'doctor-ak-portal' ),
	'tomorrow'  => __( 'Tomorrow', 'doctor-ak-portal' ),
	'this_week' => __( 'This Week', 'doctor-ak-portal' ),
	'later'     => __( 'Later', 'doctor-ak-portal' ),
);
?>
<div class="dak-portal dak-dashboard dak-patient-dashboard" data-role="patient" data-theme="<?php echo esc_attr( $theme ); ?>">
	<button type="button" class="dak-dashboard-sidebar-toggle" id="dak-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle navigation', 'doctor-ak-portal' ); ?>" aria-expanded="false" aria-controls="dak-dashboard-sidebar">
		<span></span><span></span><span></span>
	</button>

	<aside class="dak-dashboard-sidebar" id="dak-dashboard-sidebar">
		<div class="dak-sidebar-patient-card">
			<span class="dak-avatar dak-avatar-md">
				<?php if ( $avatar_url ) : ?>
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
				<?php else : ?>
					<?php echo $dak_patient_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</span>
			<span class="dak-sidebar-patient-name"><?php echo esc_html( $display_name ); ?></span>
		</div>

		<nav class="dak-dashboard-nav">
			<ul>
				<li class="<?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>">
					<a href="<?php echo esc_url( $dashboard_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['dashboard']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Dashboard', 'doctor-ak-portal' ); ?></a>
				</li>
				<li>
					<a href="#dak-patient-appointments"><span class="dak-nav-icon"><?php echo $dak_patient_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?><?php if ( $total_upcoming_count > 0 ) : ?><span class="dak-nav-badge"><?php echo esc_html( $total_upcoming_count ); ?></span><?php endif; ?></a>
				</li>
				<?php if ( $directory_url ) : ?>
					<li><a href="<?php echo esc_url( $directory_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $profile_url ) : ?>
					<li><a href="<?php echo esc_url( $profile_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Profile', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $settings_url ) : ?>
					<li class="<?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $settings_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['settings']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<li><a href="<?php echo esc_url( $logout_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['logout']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Logout', 'doctor-ak-portal' ); ?></a></li>
			</ul>

			<div class="dak-sidebar-subtle-item">
				<?php esc_html_e( 'Medical History', 'doctor-ak-portal' ); ?> <em><?php esc_html_e( '(Coming Soon)', 'doctor-ak-portal' ); ?></em>
			</div>
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

		<header class="dak-dashboard-header dak-patient-dashboard-header">
			<h5>
				<?php
				/* translators: %s: patient's first name or display name. */
				echo esc_html( sprintf( __( 'Welcome, %s', 'doctor-ak-portal' ), $first_name ) );
				?>
			</h5>
			<button type="button" class="dak-button dak-button-primary dak-patient-book-cta" data-dak-book-appointment>
				<span class="dak-nav-icon"><?php echo $dak_patient_icons['plus']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
			</button>
		</header>

		<section class="dak-dashboard-statistics">
			<div class="dak-stat-card">
				<?php if ( $next_appointment ) : ?>
					<span class="dak-stat-value"><?php echo esc_html( $next_appointment['countdown_label'] ); ?></span>
					<span class="dak-stat-label"><?php echo esc_html( sprintf( /* translators: %s: doctor's name. */ __( 'Next: Dr. %s', 'doctor-ak-portal' ), $next_appointment['doctor_name'] ) ); ?></span>
				<?php else : ?>
					<span class="dak-stat-value">&mdash;</span>
					<span class="dak-stat-label"><?php esc_html_e( 'No upcoming appointments', 'doctor-ak-portal' ); ?></span>
				<?php endif; ?>
			</div>

			<div class="dak-stat-card">
				<?php if ( $unpaid_count > 0 ) : ?>
					<span class="dak-stat-value"><?php echo esc_html( 'PKR' . number_format( $unpaid_total, 0 ) ); ?></span>
					<span class="dak-stat-label">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of unpaid appointments. */
								_n( '%d unpaid bill', '%d unpaid bills', $unpaid_count, 'doctor-ak-portal' ),
								$unpaid_count
							)
						);
						?>
					</span>
				<?php else : ?>
					<span class="dak-stat-value dak-stat-value-positive">&#10003;</span>
					<span class="dak-stat-label"><?php esc_html_e( 'All paid up', 'doctor-ak-portal' ); ?></span>
				<?php endif; ?>
			</div>

			<div class="dak-stat-card">
				<?php if ( $profile_completion < 100 ) : ?>
					<span class="dak-stat-value"><?php echo esc_html( $profile_completion ); ?>%</span>
					<span class="dak-stat-label"><?php esc_html_e( 'Profile Completion', 'doctor-ak-portal' ); ?></span>
				<?php else : ?>
					<span class="dak-stat-value"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ); ?></span>
					<span class="dak-stat-label"><?php esc_html_e( 'Member Since', 'doctor-ak-portal' ); ?></span>
				<?php endif; ?>
			</div>
		</section>

		<div class="dak-patient-dashboard-grid">
			<div class="dak-patient-dashboard-main-col">
				<section class="dak-dashboard-card dak-dashboard-appointments" id="dak-patient-appointments">
					<div class="dak-dashboard-card-header">
						<h2><?php esc_html_e( 'Upcoming Appointments', 'doctor-ak-portal' ); ?></h2>
					</div>

					<?php if ( ! $has_any_upcoming ) : ?>
						<div class="dak-patient-empty-appointments">
							<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="10" y="14" width="44" height="40" rx="4"/><path d="M10 24h44"/><path d="M20 8v10M44 8v10"/><path d="M24 34h4M24 42h4M32 34h4M32 42h4M40 34h4M40 42h4"/></svg>
							<p><?php esc_html_e( "You haven't booked any appointments yet.", 'doctor-ak-portal' ); ?></p>
							<button type="button" class="dak-button dak-button-primary" data-dak-book-appointment><?php esc_html_e( 'Book your first appointment', 'doctor-ak-portal' ); ?></button>
						</div>
					<?php else : ?>
						<?php foreach ( $appointment_group_labels as $group_key => $group_label ) : ?>
							<?php if ( ! empty( $appointment_groups[ $group_key ] ) ) : ?>
								<div class="dak-patient-appt-group">
									<h3 class="dak-patient-appt-group-label"><?php echo esc_html( $group_label ); ?></h3>
									<?php foreach ( $appointment_groups[ $group_key ] as $appointment_row_html ) : ?>
										<?php echo $appointment_row_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own patient-appointment-row.php partial, which escapes its own output. ?>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</section>
			</div>

			<div class="dak-patient-dashboard-side-col">
				<section class="dak-dashboard-card dak-patient-profile-summary">
					<h2><?php esc_html_e( 'Profile', 'doctor-ak-portal' ); ?></h2>
					<div class="dak-patient-profile-summary-body">
						<span class="dak-avatar dak-avatar-md">
							<?php if ( $avatar_url ) : ?>
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
							<?php else : ?>
								<?php echo $dak_patient_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</span>
						<div>
							<strong><?php echo esc_html( $display_name ); ?></strong><br>
							<span class="dak-patient-profile-detail"><?php echo esc_html( $user->user_email ); ?></span><br>
							<?php if ( $phone_number ) : ?>
								<span class="dak-patient-profile-detail"><?php echo esc_html( $phone_number ); ?></span><br>
							<?php endif; ?>
							<span class="dak-patient-profile-detail"><?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Member since %s', 'doctor-ak-portal' ), mysql2date( get_option( 'date_format' ), $user->user_registered ) ) ); ?></span>
						</div>
					</div>

					<?php if ( $profile_completion < 100 ) : ?>
						<div class="dak-progress-bar">
							<div class="dak-progress-bar-fill" style="width: <?php echo esc_attr( $profile_completion ); ?>%;"></div>
						</div>
						<ul class="dak-patient-missing-list">
							<?php foreach ( $missing_profile_items as $missing_item ) : ?>
								<li><?php echo esc_html( $missing_item ); ?></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $profile_url ) : ?>
							<a class="dak-link" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Complete your profile', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
					<?php else : ?>
						<p class="dak-patient-profile-complete"><?php esc_html_e( 'Profile complete', 'doctor-ak-portal' ); ?></p>
						<?php if ( $profile_url ) : ?>
							<a class="dak-link" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'View profile', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</section>

				<section class="dak-dashboard-card dak-dashboard-notifications">
					<h2><?php esc_html_e( 'Recent Activity', 'doctor-ak-portal' ); ?></h2>
					<?php if ( ! empty( $recent_activity ) ) : ?>
						<ul class="dak-patient-activity-list">
							<?php foreach ( $recent_activity as $activity_entry ) : ?>
								<li>
									<span class="dak-patient-activity-label"><?php echo esc_html( $activity_entry['label'] ); ?></span>
									<span class="dak-patient-activity-date"><?php echo esc_html( $activity_entry['date'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="dak-empty-state"><?php esc_html_e( 'You have no recent activity yet.', 'doctor-ak-portal' ); ?></p>
					<?php endif; ?>
				</section>

				<section class="dak-dashboard-card dak-patient-quick-actions">
					<h2><?php esc_html_e( 'Quick Actions', 'doctor-ak-portal' ); ?></h2>
					<div class="dak-patient-quick-actions-row">
						<button type="button" class="dak-patient-quick-action" data-dak-book-appointment>
							<span class="dak-nav-icon"><?php echo $dak_patient_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
						</button>
						<?php if ( $video_booking_url ) : ?>
							<a class="dak-patient-quick-action" href="<?php echo esc_url( $video_booking_url ); ?>">
								<span class="dak-nav-icon"><?php echo $dak_patient_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php esc_html_e( 'Video Consult', 'doctor-ak-portal' ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $directory_url ) : ?>
							<a class="dak-patient-quick-action" href="<?php echo esc_url( $directory_url ); ?>">
								<span class="dak-nav-icon"><?php echo $dak_patient_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php esc_html_e( 'Browse Doctors', 'doctor-ak-portal' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</section>
			</div>
		</div>

		<?php endif; ?>
	</main>

	<?php if ( 'settings' !== $active_tab ) : ?>
		<nav class="dak-patient-bottom-nav">
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="<?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>"><?php echo $dak_patient_icons['dashboard']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Dashboard', 'doctor-ak-portal' ); ?></span></a>
			<a href="#dak-patient-appointments"><?php echo $dak_patient_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></span></a>
			<?php if ( $directory_url ) : ?>
				<a href="<?php echo esc_url( $directory_url ); ?>"><?php echo $dak_patient_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></span></a>
			<?php endif; ?>
			<?php if ( $profile_url ) : ?>
				<a href="<?php echo esc_url( $profile_url ); ?>"><?php echo $dak_patient_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Profile', 'doctor-ak-portal' ); ?></span></a>
			<?php endif; ?>
		</nav>

		<button type="button" class="dak-patient-fab" data-dak-book-appointment aria-label="<?php esc_attr_e( 'Book Appointment', 'doctor-ak-portal' ); ?>">
			<?php echo $dak_patient_icons['plus']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
	<?php endif; ?>
</div>
