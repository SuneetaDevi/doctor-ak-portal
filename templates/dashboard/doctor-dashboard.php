<?php
/**
 * Template: Doctor dashboard body for the [doctor_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var \WP_User $user                  Currently logged-in doctor.
 * @var string   $avatar_url            Doctor's profile picture URL, or '' if none set.
 * @var int      $profile_completion    Percentage 0-100.
 * @var int      $years_experience      Years of experience.
 * @var array    $specializations       Specialization slugs.
 * @var array    $specialization_labels Human-readable specialization labels.
 * @var string   $clinic_location       Doctor's primary (first) physical clinic's address/name, or '' if none.
 * @var bool     $video_consultation    Whether the doctor has an active video-consultation clinic.
 * @var string   $active_tab            'dashboard', 'profile', 'clinics' or 'settings'.
 * @var string   $profile_form_html     Pre-rendered profile/profile-form.php output when $active_tab is 'profile'.
 * @var string   $clinics_tab_html      Pre-rendered doctor-clinics-tab.php output when $active_tab is 'clinics'.
 * @var string   $services_tab_html     Pre-rendered doctor-services-tab.php output when $active_tab is 'services'.
 * @var string   $video_consultation_tab_html Pre-rendered doctor-video-consultation-tab.php output when $active_tab is 'video-consultation'.
 * @var string   $settings_tab_html     Pre-rendered dashboard-settings-tab.php output when $active_tab is 'settings'.
 * @var array    $appointment_groups    'today'|'tomorrow'|'this_week'|'later' => array of pre-rendered doctor-appointment-row.php strings.
 * @var int      $total_upcoming_appointments Total upcoming (non-cancelled) appointment count.
 * @var string   $dashboard_url         URL of this dashboard page.
 * @var string   $profile_url           Same-page URL for the Profile tab.
 * @var string   $clinics_url           Same-page URL for the Clinics tab.
 * @var string   $services_url          Same-page URL for the Services tab.
 * @var string   $video_consultation_url Same-page URL for the Video Consultation tab.
 * @var string   $settings_url          Same-page URL for the Settings tab.
 * @var string   $theme                 'light' or 'dark' — the doctor's saved dashboard theme preference.
 * @var string   $logout_url            Nonce-protected logout URL.
 * @var int      $total_patients        Count of registered patient accounts.
 * @var int      $today_appointments    Today's appointment count (0 until a booking module exists).
 * @var int      $video_consults        Completed video consult count (0 until a booking module exists).
 * @var float|null $rating              Average rating, or null if there are no reviews yet.
 * @var int      $review_count          Number of reviews.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$first_name   = $user->first_name ? $user->first_name : $user->display_name;
$display_name = trim( $user->first_name . ' ' . $user->last_name );
$display_name = '' !== $display_name ? $display_name : $user->display_name;

$dak_dash_icons = array(
	'dashboard'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="2.5" width="6.5" height="6.5" rx="1.2"/><rect x="11" y="2.5" width="6.5" height="6.5" rx="1.2"/><rect x="2.5" y="11" width="6.5" height="6.5" rx="1.2"/><rect x="11" y="11" width="6.5" height="6.5" rx="1.2"/></svg>',
	'calendar'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'users'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="2.8"/><path d="M1.8 16c0-2.9 2.3-4.8 5.2-4.8s5.2 1.9 5.2 4.8"/><path d="M13 7.2a2.6 2.6 0 1 1 3.6 2.4"/><path d="M14.5 11.3c2 .3 3.7 1.7 3.7 4"/></svg>',
	'video'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="11" height="10" rx="1.5"/><path d="M13 8.3l5-2.8v9l-5-2.8"/></svg>',
	'star'         => '<svg viewBox="0 0 20 20" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linejoin="round"><path d="M10 2.3l2.2 4.7 5 .6-3.7 3.5.9 5-4.4-2.5-4.4 2.5.9-5-3.7-3.5 5-.6z"/></svg>',
	'clock'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7"/><path d="M10 6v4l3 2"/></svg>',
	'bell'         => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8a5 5 0 0 1 10 0c0 3.2 1 4.3 1.5 5H3.5C4 12.3 5 11.2 5 8z"/><path d="M8.2 15.5a1.8 1.8 0 0 0 3.6 0"/></svg>',
	'search'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg>',
	'person'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'settings'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="2.6"/><path d="M10 2.8v2M10 15.2v2M17.2 10h-2M4.8 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/></svg>',
	'logout'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 17H4a1.5 1.5 0 0 1-1.5-1.5v-11A1.5 1.5 0 0 1 4 3h3.5"/><path d="M13 14l4-4-4-4"/><path d="M17 10H7.5"/></svg>',
	'pin'          => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
);
?>
<div class="dak-portal dak-dashboard dak-doctor-dashboard" data-role="doctor" data-theme="<?php echo esc_attr( $theme ); ?>">
	<button type="button" class="dak-dashboard-sidebar-toggle" id="dak-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle navigation', 'doctor-ak-portal' ); ?>" aria-expanded="false" aria-controls="dak-dashboard-sidebar">
		<span></span><span></span><span></span>
	</button>

	<aside class="dak-dashboard-sidebar" id="dak-dashboard-sidebar">
		<div class="dak-sidebar-doctor-card">
			<span class="dak-avatar dak-avatar-md">
				<?php if ( $avatar_url ) : ?>
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
				<?php else : ?>
					<?php echo $dak_dash_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</span>
			<span class="dak-sidebar-doctor-name"><?php echo esc_html( sprintf( 'Dr. %s', $display_name ) ); ?></span>

			<?php if ( ! empty( $specialization_labels ) ) : ?>
				<div class="dak-sidebar-doctor-block">
					<span class="dak-sidebar-doctor-block-label"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></span>
					<div class="dak-specialty-tags">
						<?php foreach ( $specialization_labels as $specialization_label ) : ?>
							<span class="dak-specialty-tag"><?php echo esc_html( $specialization_label ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $clinic_location ) : ?>
				<div class="dak-sidebar-doctor-location">
					<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_dash_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span><?php echo esc_html( $clinic_location ); ?></span>
				</div>
			<?php endif; ?>

			<span class="dak-sidebar-doctor-tagline"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></span>
			<?php if ( $review_count > 0 && null !== $rating ) : ?>
				<span class="dak-sidebar-doctor-rating">
					<span class="dak-rating-icon" aria-hidden="true"><?php echo $dak_dash_icons['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: average rating, 2: review count. */
							_n( '%1$s · %2$s review', '%1$s · %2$s reviews', $review_count, 'doctor-ak-portal' ),
							number_format_i18n( $rating, 1 ),
							number_format_i18n( $review_count )
						)
					);
					?>
				</span>
			<?php endif; ?>
		</div>

		<nav class="dak-dashboard-nav">
			<ul>
				<li class="<?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $dashboard_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['dashboard']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Dashboard', 'doctor-ak-portal' ); ?></a></li>
				<?php if ( $clinics_url ) : ?>
					<li class="<?php echo 'clinics' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $clinics_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $services_url ) : ?>
					<li class="<?php echo 'services' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $services_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['settings']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $video_consultation_url ) : ?>
					<li class="<?php echo 'video-consultation' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $video_consultation_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<li><a href="#dak-doctor-appointments"><span class="dak-nav-icon"><?php echo $dak_dash_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></a></li>
				<?php if ( $profile_url ) : ?>
					<li class="<?php echo 'profile' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $profile_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Profile', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $settings_url ) : ?>
					<li class="<?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $settings_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['settings']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</nav>

		<a class="dak-sidebar-logout" href="<?php echo esc_url( $logout_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['logout']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Logout', 'doctor-ak-portal' ); ?></a>
	</aside>

	<main class="dak-dashboard-main">
		<header class="dak-dashboard-topbar">
			<div class="dak-dashboard-search">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><?php echo $dak_dash_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<input type="search" placeholder="<?php esc_attr_e( 'Search patients, appointments…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search patients, appointments…', 'doctor-ak-portal' ); ?>">
			</div>
		</header>

		<?php if ( 'dashboard' !== $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1>
					<?php
					if ( 'clinics' === $active_tab ) {
						esc_html_e( 'Clinics', 'doctor-ak-portal' );
					} elseif ( 'services' === $active_tab ) {
						esc_html_e( 'Services', 'doctor-ak-portal' );
					} elseif ( 'video-consultation' === $active_tab ) {
						esc_html_e( 'Video Consultation', 'doctor-ak-portal' );
					} elseif ( 'settings' === $active_tab ) {
						esc_html_e( 'Settings', 'doctor-ak-portal' );
					} else {
						esc_html_e( 'Edit Profile', 'doctor-ak-portal' );
					}
					?>
				</h1>
			</div>

			<div class="dak-dashboard-card dak-dashboard-profile-form">
				<?php if ( 'clinics' === $active_tab ) : ?>
					<?php echo $clinics_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-clinics-tab.php template, which escapes its own output. ?>
				<?php elseif ( 'services' === $active_tab ) : ?>
					<?php echo $services_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-services-tab.php template, which escapes its own output. ?>
				<?php elseif ( 'video-consultation' === $active_tab ) : ?>
					<?php echo $video_consultation_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-video-consultation-tab.php template, which escapes its own output. ?>
				<?php elseif ( 'settings' === $active_tab ) : ?>
					<?php echo $settings_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own dashboard-settings-tab.php template, which escapes its own output. ?>
				<?php else : ?>
					<?php echo $profile_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own profile-form.php template, which escapes its own output. ?>
				<?php endif; ?>
			</div>

		<?php else : ?>

		<div class="dak-dashboard-greeting">
			<h1>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: doctor's first name or display name. */
						__( 'Welcome back, Dr. %s', 'doctor-ak-portal' ),
						$first_name
					)
				);
				?>
				<span aria-hidden="true">👋</span>
			</h1>
			<p><?php esc_html_e( "Here's a summary of today's schedule and activity.", 'doctor-ak-portal' ); ?></p>
		</div>

		<section class="dak-dashboard-statistics">
			<div class="dak-stat-card">
				<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_dash_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $today_appointments ) ); ?></span>
				<span class="dak-stat-label"><?php esc_html_e( "Today's Appointments", 'doctor-ak-portal' ); ?></span>
			</div>
			<div class="dak-stat-card">
				<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_dash_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $total_patients ) ); ?></span>
				<span class="dak-stat-label"><?php esc_html_e( 'Total Patients', 'doctor-ak-portal' ); ?></span>
			</div>
			<div class="dak-stat-card">
				<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_dash_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $video_consults ) ); ?></span>
				<span class="dak-stat-label"><?php esc_html_e( 'Video Consults', 'doctor-ak-portal' ); ?></span>
			</div>
			<div class="dak-stat-card">
				<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_dash_icons['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="dak-stat-value"><?php echo null !== $rating ? esc_html( number_format_i18n( $rating, 1 ) ) : '—'; ?></span>
				<span class="dak-stat-label">
					<?php
					if ( $review_count > 0 ) {
						echo esc_html( sprintf(
							/* translators: %s: review count. */
							_n( '%s Review', '%s Reviews', $review_count, 'doctor-ak-portal' ),
							number_format_i18n( $review_count )
						) );
					} else {
						esc_html_e( 'No ratings yet', 'doctor-ak-portal' );
					}
					?>
				</span>
			</div>
		</section>

		<div class="dak-dashboard-grid dak-dashboard-grid-lists">
			<section class="dak-dashboard-card dak-dashboard-appointments" id="dak-doctor-appointments">
				<h2><?php esc_html_e( 'Upcoming Appointments', 'doctor-ak-portal' ); ?></h2>
				<?php
				$dak_doctor_appt_group_labels = array(
					'today'     => __( 'Today', 'doctor-ak-portal' ),
					'tomorrow'  => __( 'Tomorrow', 'doctor-ak-portal' ),
					'this_week' => __( 'This Week', 'doctor-ak-portal' ),
					'later'     => __( 'Later', 'doctor-ak-portal' ),
				);
				?>
				<?php if ( 0 === $total_upcoming_appointments ) : ?>
					<p class="dak-empty-state"><?php esc_html_e( 'No upcoming appointments.', 'doctor-ak-portal' ); ?></p>
				<?php else : ?>
					<?php foreach ( $dak_doctor_appt_group_labels as $group_key => $group_label ) : ?>
						<?php if ( ! empty( $appointment_groups[ $group_key ] ) ) : ?>
							<div class="dak-patient-appt-group">
								<h3 class="dak-patient-appt-group-label"><?php echo esc_html( $group_label ); ?></h3>
								<?php foreach ( $appointment_groups[ $group_key ] as $appointment_row_html ) : ?>
									<?php echo $appointment_row_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-appointment-row.php partial, which escapes its own output. ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<section class="dak-dashboard-card dak-dashboard-recent-patients">
				<h2><?php esc_html_e( 'Recent Patients', 'doctor-ak-portal' ); ?></h2>
				<?php
				ob_start();
				/**
				 * Fires inside the doctor dashboard's recent-patients card.
				 *
				 * A future booking/records module can hook here to render
				 * real recent-patient data without modifying this template.
				 *
				 * @param \WP_User $user Currently viewed doctor.
				 */
				do_action( 'doctor_ak_doctor_dashboard_recent_patients', $user );
				$recent_patients_output = trim( ob_get_clean() );

				if ( '' !== $recent_patients_output ) {
					echo $recent_patients_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hooked content is responsible for its own escaping.
				} else {
					?>
					<p class="dak-empty-state"><?php esc_html_e( 'No recent patients yet.', 'doctor-ak-portal' ); ?></p>
					<?php
				}
				?>
			</section>
		</div>

		<?php if ( $profile_completion < 100 && $profile_url ) : ?>
			<p class="dak-dashboard-profile-nudge">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: profile completion percentage. */
						__( 'Your profile is %d%% complete.', 'doctor-ak-portal' ),
						$profile_completion
					)
				);
				?>
				<a class="dak-link" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Complete it now', 'doctor-ak-portal' ); ?></a>
			</p>
		<?php endif; ?>

		<?php
		ob_start();
		/**
		 * Fires at the end of the doctor dashboard's main column.
		 *
		 * @param \WP_User $user Currently viewed doctor.
		 */
		do_action( 'doctor_ak_doctor_dashboard_notifications', $user );
		$notifications_output = trim( ob_get_clean() );

		if ( '' !== $notifications_output ) :
			?>
			<section class="dak-dashboard-card dak-dashboard-notifications">
				<h2><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?></h2>
				<?php echo $notifications_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hooked content is responsible for its own escaping. ?>
			</section>
			<?php
		endif;
		?>

		<?php endif; ?>

	</main>
</div>
