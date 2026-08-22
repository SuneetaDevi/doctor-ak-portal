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
 * @var string   $appointments_tab_html Pre-rendered doctor-appointments-list.php output when $active_tab is 'appointments'.
 * @var string   $appointments_url      Same-page URL for the Appointments tab.
 * @var string   $patients_tab_html     Pre-rendered doctor-patients-tab.php output when $active_tab is 'patients'.
 * @var string   $patients_url          Same-page URL for the Patients tab.
 * @var string   $earnings_tab_html     Pre-rendered doctor-earnings-tab.php output when $active_tab is 'earnings'.
 * @var string   $earnings_url          Same-page URL for the Earnings tab.
 * @var string   $encounters_tab_html   Pre-rendered doctor-encounters-tab.php output when $active_tab is 'encounters' — this doctor's own encounters only.
 * @var string   $encounters_url        Same-page URL for the Encounters tab.
 * @var string   $encounter_tab_html    Pre-rendered doctor-encounter.php output when $active_tab is 'encounter' (not a permanent nav tab — reached via "Open Encounter" on an appointment row).
 * @var array    $doctor_clinics        Doctor's clinics, see Clinics::get_for_doctor() — populates the Add/Edit Patient modal's clinic dropdown.
 * @var string   $notifications_tab_html Pre-rendered notifications-list.php output when $active_tab is 'notifications'.
 * @var string   $notifications_url      Same-page URL for the Notifications tab.
 * @var int      $unread_notifications_count Unread notification count, for the sidebar badge.
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
	'money'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2v7.6M12.2 8.1c0-1-1-1.6-2.2-1.6s-2.2.6-2.2 1.5c0 2.2 4.4 1 4.4 3.2 0 .9-1 1.5-2.2 1.5s-2.2-.6-2.2-1.6"/></svg>',
	'clipboard'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.5h6a1 1 0 0 1 1 1V16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M8 2.5h4v2H8z"/><path d="M7.5 9h5M7.5 12h5M7.5 15h3"/></svg>',
);
?>
<div class="dak-portal dak-dashboard dak-doctor-dashboard" data-role="doctor" data-theme="<?php echo esc_attr( $theme ); ?>">
	<button type="button" class="dak-dashboard-sidebar-toggle" id="dak-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle navigation', 'doctor-ak-portal' ); ?>" aria-expanded="false" aria-controls="dak-dashboard-sidebar">
		<span></span><span></span><span></span>
	</button>

	<aside class="dak-dashboard-sidebar" id="dak-dashboard-sidebar">
		<div class="dak-sidebar-top-row">
			<button type="button" class="dak-sidebar-collapse-toggle" id="dak-sidebar-collapse-toggle" aria-label="<?php esc_attr_e( 'Collapse sidebar', 'doctor-ak-portal' ); ?>" aria-expanded="true" aria-controls="dak-dashboard-sidebar">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 5 7 10l5.5 5"/></svg>
			</button>
		</div>

		<div class="dak-sidebar-brand">
			<span class="dak-sidebar-brand-logo">
				<?php
				$dak_brand_logo_url = \DoctorAKPortal\Frontend\Site_Footer::bundled_logo_url();
				$dak_brand_initials = mb_strtoupper( mb_substr( get_bloginfo( 'name' ), 0, 2 ) );
				?>
				<?php if ( '' !== $dak_brand_logo_url ) : ?>
					<img src="<?php echo esc_url( $dak_brand_logo_url ); ?>" alt="">
				<?php else : ?>
					<?php echo esc_html( '' !== $dak_brand_initials ? $dak_brand_initials : 'AK' ); ?>
				<?php endif; ?>
			</span>
			<span class="dak-sidebar-brand-text">
				<strong><?php esc_html_e( 'Doctor AK Portal', 'doctor-ak-portal' ); ?></strong>
				<span><?php esc_html_e( 'Doctor portal', 'doctor-ak-portal' ); ?></span>
			</span>
		</div>

		<div class="dak-sidebar-doctor-card">
			<span class="dak-avatar dak-avatar-md">
				<?php if ( $avatar_url ) : ?>
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
				<?php else : ?>
					<?php echo $dak_dash_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</span>
			<span class="dak-sidebar-doctor-name"><?php echo esc_html( sprintf( 'Dr. %s', $display_name ) ); ?></span>

			<?php if ( $clinic_location ) : ?>
				<div class="dak-sidebar-doctor-location">
					<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_dash_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span><?php echo esc_html( $clinic_location ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $specialization_labels ) ) : ?>
				<div class="dak-specialty-tags dak-sidebar-doctor-tags" data-specialty-tags>
					<?php foreach ( $specialization_labels as $dak_spec_index => $specialization_label ) : ?>
						<span class="dak-specialty-tag<?php echo $dak_spec_index >= 2 ? ' dak-specialty-tag-extra dak-hidden' : ''; ?>"><?php echo esc_html( $specialization_label ); ?></span>
					<?php endforeach; ?>
					<?php if ( count( $specialization_labels ) > 2 ) : ?>
						<button
							type="button"
							class="dak-specialty-tag dak-specialty-tag-more"
							data-specialty-toggle
							data-more-label="<?php echo esc_attr( sprintf( '+%d', count( $specialization_labels ) - 2 ) ); ?>"
							data-less-label="<?php esc_attr_e( 'Show less', 'doctor-ak-portal' ); ?>"
						><?php echo esc_html( sprintf( '+%d', count( $specialization_labels ) - 2 ) ); ?></button>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="dak-sidebar-doctor-status-row">
				<?php if ( $review_count > 0 && null !== $rating ) : ?>
					<span class="dak-sidebar-doctor-rating">
						<span class="dak-rating-icon" aria-hidden="true"><?php echo $dak_dash_icons['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: average rating, 2: review count. */
								_n( '%1$s (%2$s review)', '%1$s (%2$s reviews)', $review_count, 'doctor-ak-portal' ),
								number_format_i18n( $rating, 1 ),
								number_format_i18n( $review_count )
							)
						);
						?>
					</span>
				<?php endif; ?>
				<span class="dak-specialty-tag dak-sidebar-doctor-status"><?php esc_html_e( 'Active', 'doctor-ak-portal' ); ?></span>
			</div>
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
				<?php if ( $appointments_url ) : ?>
					<li class="<?php echo 'appointments' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $appointments_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $patients_url ) : ?>
					<li class="<?php echo 'patients' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $patients_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Patients', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $earnings_url ) : ?>
					<li class="<?php echo 'earnings' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $earnings_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['money']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Earnings', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $encounters_url ) : ?>
					<li class="<?php echo 'encounters' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $encounters_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['clipboard']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Encounters', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $notifications_url ) : ?>
					<li class="<?php echo 'notifications' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $notifications_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_dash_icons['bell']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?><?php if ( $unread_notifications_count > 0 ) : ?><span class="dak-nav-badge" id="dak-notifications-badge"><?php echo esc_html( $unread_notifications_count ); ?></span><?php endif; ?></a></li>
				<?php endif; ?>
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
			<?php if ( 'dashboard' === $active_tab ) : ?>
				<div class="dak-dashboard-search">
					<span class="dak-dashboard-search-icon" aria-hidden="true"><?php echo $dak_dash_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<input
						type="search"
						id="dak-dashboard-topbar-search"
						data-live-search="doctor_ak_doctor_dashboard_search"
						data-live-search-nonce="dakDoctorAppointments"
						data-live-search-groups="patients,appointments,services,clinics"
						placeholder="<?php esc_attr_e( 'Search patients, appointments, services…', 'doctor-ak-portal' ); ?>"
						aria-label="<?php esc_attr_e( 'Search patients, appointments, services…', 'doctor-ak-portal' ); ?>"
						autocomplete="off"
					>
					<div class="dak-search-results dak-hidden" id="dak-dashboard-topbar-search-results"></div>
				</div>
			<?php endif; ?>
			<?php
			echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own topbar-actions.php template, which escapes its own output.
				'dashboard/partials/topbar-actions.php',
				array(
					'notifications_url'          => $notifications_url,
					'unread_notifications_count' => $unread_notifications_count,
					'user'                       => $user,
					'avatar_url'                 => $avatar_url,
					'profile_url'                => $profile_url,
					'logout_url'                 => $logout_url,
				)
			);
			?>
		</header>

		<?php if ( 'appointments' === $active_tab ) : ?>

			<div id="dak-doctor-appointments-tab-content">
				<?php echo $appointments_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-appointments-list.php template, which escapes its own output (including its own page header). ?>
			</div>

		<?php elseif ( 'notifications' === $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?></h1>
			</div>

			<?php echo $notifications_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own notifications-list.php template, which escapes its own output. ?>

		<?php elseif ( 'patients' === $active_tab ) : ?>

			<?php echo $patients_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-patients-tab.php template, which escapes its own output (including its own page header). ?>

		<?php elseif ( 'earnings' === $active_tab ) : ?>

			<?php echo $earnings_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-earnings-tab.php template, which escapes its own output (including its own page header). ?>

		<?php elseif ( 'encounters' === $active_tab ) : ?>

			<?php echo $encounters_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-encounters-tab.php template, which escapes its own output (including its own page header). ?>

		<?php elseif ( 'encounter' === $active_tab ) : ?>

			<?php echo $encounter_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-encounter.php template, which escapes its own output (including its own page header). ?>

		<?php elseif ( 'video-consultation' === $active_tab ) : ?>

			<?php echo $video_consultation_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-video-consultation-tab.php template, which escapes its own output (including its own page header). ?>

		<?php elseif ( 'clinics' === $active_tab ) : ?>

			<?php echo $clinics_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-clinics-tab.php template, which escapes its own output (including its own page header). ?>

		<?php elseif ( 'services' === $active_tab ) : ?>

			<?php echo $services_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-services-tab.php template, which escapes its own output (including its own page header). ?>

		<?php elseif ( 'dashboard' !== $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1>
					<?php
					if ( 'settings' === $active_tab ) {
						esc_html_e( 'Settings', 'doctor-ak-portal' );
					} else {
						esc_html_e( 'Edit Profile', 'doctor-ak-portal' );
					}
					?>
				</h1>
			</div>

			<div class="dak-dashboard-card dak-dashboard-profile-form">
				<?php if ( 'settings' === $active_tab ) : ?>
					<?php echo $settings_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own dashboard-settings-tab.php template, which escapes its own output. ?>
				<?php else : ?>
					<?php echo $profile_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own profile-form.php template, which escapes its own output. ?>
				<?php endif; ?>
			</div>

		<?php else : ?>

		<div class="dak-dashboard-greeting dak-hero-banner">
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
				<div class="dak-dashboard-card-header">
					<h2><?php esc_html_e( 'Recent Patients', 'doctor-ak-portal' ); ?></h2>
					<button type="button" class="dak-button dak-button-secondary dak-button-sm" id="dak-doctor-add-patient-open"><?php esc_html_e( '+ Add Patient', 'doctor-ak-portal' ); ?></button>
				</div>
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

		<?php if ( in_array( $active_tab, array( 'dashboard', 'patients' ), true ) ) : ?>
		<div class="dak-portal dak-modal" id="dak-doctor-add-patient-modal" aria-hidden="true">
			<div class="dak-modal-overlay" data-dak-add-patient-close></div>

			<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-doctor-add-patient-title">
				<button type="button" class="dak-modal-close" data-dak-add-patient-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

				<div class="dak-modal-header">
					<h2 id="dak-doctor-add-patient-title"><span id="dak-doctor-add-patient-title-text"><?php esc_html_e( 'Add Patient', 'doctor-ak-portal' ); ?></span></h2>
				</div>

				<div class="dak-alert dak-alert-error dak-hidden" id="dak-doctor-add-patient-general-error" role="alert"></div>

				<input type="hidden" id="dak-doctor-add-patient-id" value="0">

				<div class="dak-field-row">
					<div class="dak-field">
						<label for="dak-doctor-add-patient-first-name"><?php esc_html_e( 'First Name', 'doctor-ak-portal' ); ?></label>
						<input type="text" id="dak-doctor-add-patient-first-name">
						<span class="dak-field-error" data-field="first_name"></span>
					</div>
					<div class="dak-field">
						<label for="dak-doctor-add-patient-last-name"><?php esc_html_e( 'Last Name', 'doctor-ak-portal' ); ?></label>
						<input type="text" id="dak-doctor-add-patient-last-name">
						<span class="dak-field-error" data-field="last_name"></span>
					</div>
				</div>

				<div class="dak-field">
					<label for="dak-doctor-add-patient-email"><?php esc_html_e( 'Email Address', 'doctor-ak-portal' ); ?></label>
					<input type="email" id="dak-doctor-add-patient-email">
					<span class="dak-field-error" data-field="email"></span>
				</div>

				<div class="dak-field">
					<label for="dak-doctor-add-patient-phone-code"><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
					<?php
					echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
						'partials/phone-field.php',
						array(
							'id_prefix' => 'dak-doctor-add-patient-phone',
							'dial_code' => \DoctorAKPortal\Includes\Phone::DEFAULT_DIAL_CODE,
							'number'    => '',
							'required'  => true,
						)
					);
					?>
					<span class="dak-field-error" data-field="phone_number"></span>
				</div>

				<?php if ( ! empty( $doctor_clinics ) ) : ?>
					<div class="dak-field" id="dak-doctor-add-patient-clinic-field">
						<label for="dak-doctor-add-patient-clinic"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></label>
						<select id="dak-doctor-add-patient-clinic">
							<option value="0"><?php esc_html_e( '— Select clinic (optional) —', 'doctor-ak-portal' ); ?></option>
							<?php foreach ( $doctor_clinics as $dak_add_patient_clinic ) : ?>
								<option value="<?php echo esc_attr( $dak_add_patient_clinic['id'] ); ?>"><?php echo esc_html( $dak_add_patient_clinic['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="dak-field-error" data-field="clinic_id"></span>
					</div>
				<?php endif; ?>

				<div class="dak-field" id="dak-doctor-add-patient-home-clinic-field">
					<label for="dak-doctor-add-patient-home-clinic"><?php esc_html_e( 'Home Clinic', 'doctor-ak-portal' ); ?></label>
					<select id="dak-doctor-add-patient-home-clinic">
						<option value=""><?php esc_html_e( 'Select a clinic…', 'doctor-ak-portal' ); ?></option>
						<?php foreach ( $clinic_locations as $dak_add_patient_clinic_location ) : ?>
							<option value="<?php echo esc_attr( $dak_add_patient_clinic_location['id'] ); ?>">
								<?php echo esc_html( sprintf( '%1$s — %2$s, %3$s', $dak_add_patient_clinic_location['name'], $dak_add_patient_clinic_location['area_label'], $dak_add_patient_clinic_location['city_label'] ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="dak-field-hint"><?php esc_html_e( 'The clinic this patient is registered under. Does not apply to video consultations.', 'doctor-ak-portal' ); ?></p>
					<span class="dak-field-error" data-field="clinic_location_id"></span>
				</div>

				<p class="dak-field-hint" id="dak-doctor-add-patient-hint"><?php esc_html_e( 'A password will be generated automatically and the patient will get an email to set their own.', 'doctor-ak-portal' ); ?></p>

				<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-doctor-add-patient-save">
					<span class="dak-button-label" id="dak-doctor-add-patient-save-label"><?php esc_html_e( 'Add Patient', 'doctor-ak-portal' ); ?></span>
				</button>
			</div>
		</div>
		<?php endif; ?>

		<div class="dak-portal dak-modal" id="dak-reschedule-appointment-modal" aria-hidden="true">
			<div class="dak-modal-overlay" data-dak-reschedule-close></div>

			<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-reschedule-appointment-title">
				<button type="button" class="dak-modal-close" data-dak-reschedule-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

				<div class="dak-modal-header">
					<h2 id="dak-reschedule-appointment-title"><?php esc_html_e( 'Reschedule Appointment', 'doctor-ak-portal' ); ?></h2>
				</div>

				<div class="dak-alert dak-alert-error dak-hidden" id="dak-reschedule-appointment-error" role="alert"></div>

				<input type="hidden" id="dak-reschedule-appointment-id" value="0">

				<div class="dak-field-row">
					<div class="dak-field">
						<label for="dak-reschedule-appointment-date"><?php esc_html_e( 'New Date', 'doctor-ak-portal' ); ?></label>
						<input type="date" id="dak-reschedule-appointment-date" min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
					</div>
					<div class="dak-field">
						<label for="dak-reschedule-appointment-time"><?php esc_html_e( 'New Time', 'doctor-ak-portal' ); ?></label>
						<input type="time" id="dak-reschedule-appointment-time">
					</div>
				</div>

				<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-reschedule-appointment-save">
					<span class="dak-button-label"><?php esc_html_e( 'Save New Time', 'doctor-ak-portal' ); ?></span>
				</button>
			</div>
		</div>

	</main>
</div>
