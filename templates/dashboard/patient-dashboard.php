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
 * @var array    $recent_activity       List of { label, type, date }, most recent first.
 * @var string   $booking_url           URL of the booking page.
 * @var string   $profile_url           URL of the Edit Profile page.
 * @var string   $directory_url         URL of the [doctors_directory] page.
 * @var string   $logout_url            Nonce-protected logout URL.
 * @var string   $contact_url           Contact Us page URL for the "Contact Support" button.
 * @var string   $active_tab            'dashboard', 'profile', 'settings', 'medical-history', or 'payments'.
 * @var string   $dashboard_url         URL of this dashboard page.
 * @var string   $settings_url          Same-page URL for the Settings tab.
 * @var string   $medical_history_url   Same-page URL for the Medical History tab.
 * @var string   $payments_url          Same-page URL for the Payments tab.
 * @var string   $profile_tab_html      Pre-rendered profile/profile-form.php output when $active_tab is 'profile'.
 * @var string   $settings_tab_html     Pre-rendered dashboard-settings-tab.php output when $active_tab is 'settings'.
 * @var string   $payments_tab_html     Pre-rendered patient-payments-tab.php output when $active_tab is 'payments'.
 * @var string   $appointments_url      Same-page URL for the Appointments tab.
 * @var string   $appointments_tab_html Pre-rendered patient-appointments-list.php output when $active_tab is 'appointments'.
 * @var string   $notifications_url     Same-page URL for the Notifications tab.
 * @var string   $notifications_tab_html Pre-rendered notifications-list.php output when $active_tab is 'notifications'.
 * @var int      $unread_notifications_count Unread notification count, for the sidebar badge.
 * @var string   $medical_history_tab_html Pre-rendered patient-medical-history.php output when $active_tab is 'medical-history'.
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
	'heart'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17s-6.5-4-6.5-8.8A3.7 3.7 0 0 1 10 5.6a3.7 3.7 0 0 1 6.5 2.6C16.5 13 10 17 10 17z"/></svg>',
	'wallet'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="15" height="11" rx="1.8"/><path d="M2.5 8.5h15"/><circle cx="14.2" cy="12" r="1"/></svg>',
	'settings'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="2.6"/><path d="M10 2.8v2M10 15.2v2M17.2 10h-2M4.8 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/></svg>',
	'logout'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 17H4a1.5 1.5 0 0 1-1.5-1.5v-11A1.5 1.5 0 0 1 4 3h3.5"/><path d="M13 14l4-4-4-4"/><path d="M17 10H7.5"/></svg>',
	'plus'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4v12M4 10h12"/></svg>',
	'video'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="11" height="10" rx="1.5"/><path d="M13 8.3l5-2.8v9l-5-2.8"/></svg>',
	'shield'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5 16.5 5v5c0 4.2-2.8 6.7-6.5 7.5C6.3 16.7 3.5 14.2 3.5 10V5L10 2.5z"/></svg>',
	'headset'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 11v-1a6.5 6.5 0 0 1 13 0v1"/><rect x="2.5" y="10.5" width="3" height="4.5" rx="1"/><rect x="14.5" y="10.5" width="3" height="4.5" rx="1"/><path d="M16.5 15v.5a2.3 2.3 0 0 1-2.3 2.3H11.8"/></svg>',
	'clock'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2V10l2.8 1.8"/></svg>',
	'bell'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8a5 5 0 0 1 10 0c0 3.2 1 4.3 1.5 5H3.5C4 12.3 5 11.2 5 8z"/><path d="M8.2 15.5a1.8 1.8 0 0 0 3.6 0"/></svg>',
	'check'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l3.5 3.5L16 6"/></svg>',
	'x'         => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5l10 10M15 5L5 15"/></svg>',
	'search'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg>',
);

$dak_activity_icons = array(
	'paid'      => 'check',
	'booked'    => 'calendar',
	'cancelled' => 'x',
);

$has_any_upcoming = ! empty( array_filter( $appointment_groups ) );

$appointment_group_labels = array(
	'today'     => __( 'Today', 'doctor-ak-portal' ),
	'tomorrow'  => __( 'Tomorrow', 'doctor-ak-portal' ),
	'this_week' => __( 'This Week', 'doctor-ak-portal' ),
	'later'     => __( 'Later', 'doctor-ak-portal' ),
);
?>
<div class="dak-portal dak-dashboard dak-patient-dashboard" data-role="patient">
	<button type="button" class="dak-dashboard-sidebar-toggle" id="dak-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle navigation', 'doctor-ak-portal' ); ?>" aria-expanded="false" aria-controls="dak-dashboard-sidebar">
		<span></span><span></span><span></span>
	</button>

	<aside class="dak-dashboard-sidebar" id="dak-dashboard-sidebar">
		<div class="dak-sidebar-top-row">
			<button type="button" class="dak-sidebar-collapse-toggle" id="dak-sidebar-collapse-toggle" aria-label="<?php esc_attr_e( 'Collapse sidebar', 'doctor-ak-portal' ); ?>" aria-expanded="true" aria-controls="dak-dashboard-sidebar">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 5 7 10l5.5 5"/></svg>
			</button>
		</div>

		<a class="dak-sidebar-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php esc_attr_e( 'Go to home page', 'doctor-ak-portal' ); ?>">
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
				<span><?php esc_html_e( 'Patient portal', 'doctor-ak-portal' ); ?></span>
			</span>
		</a>

		<?php
		echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
			'dashboard/partials/sidebar-identity.php',
			array(
				'name'       => $display_name,
				'role_label' => __( 'Patient', 'doctor-ak-portal' ),
				'avatar_url' => $avatar_url,
			)
		);
		?>

		<nav class="dak-dashboard-nav">
			<ul>
				<li class="<?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>">
					<a href="<?php echo esc_url( $dashboard_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['dashboard']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Dashboard', 'doctor-ak-portal' ); ?></a>
				</li>
				<?php if ( $appointments_url ) : ?>
					<li class="<?php echo 'appointments' === $active_tab ? 'is-active' : ''; ?>">
						<a href="<?php echo esc_url( $appointments_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?><?php if ( $total_upcoming_count > 0 ) : ?><span class="dak-nav-badge"><?php echo esc_html( $total_upcoming_count ); ?></span><?php endif; ?></a>
					</li>
				<?php endif; ?>
				<?php if ( $directory_url ) : ?>
					<li><a href="<?php echo esc_url( $directory_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $notifications_url ) : ?>
					<li class="<?php echo 'notifications' === $active_tab ? 'is-active' : ''; ?>">
						<a href="<?php echo esc_url( $notifications_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['bell']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?><?php if ( $unread_notifications_count > 0 ) : ?><?php echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( 'dashboard/partials/unread-badge.php', array( 'count' => $unread_notifications_count, 'class' => 'dak-nav-badge', 'id' => 'dak-notifications-badge' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output. ?><?php endif; ?></a>
					</li>
				<?php endif; ?>
				<?php if ( $profile_url ) : ?>
					<li class="<?php echo 'profile' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $profile_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Profile', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $medical_history_url ) : ?>
					<li class="<?php echo 'medical-history' === $active_tab ? 'is-active' : ''; ?>">
						<a href="<?php echo esc_url( $medical_history_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['heart']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Medical History', 'doctor-ak-portal' ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( $payments_url ) : ?>
					<li class="<?php echo 'payments' === $active_tab ? 'is-active' : ''; ?>">
						<a href="<?php echo esc_url( $payments_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['wallet']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Payments', 'doctor-ak-portal' ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( $settings_url ) : ?>
					<li class="<?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>"><a href="<?php echo esc_url( $settings_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_patient_icons['settings']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</nav>

		<?php echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( 'dashboard/partials/sidebar-footer.php', array( 'logout_url' => $logout_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output. ?>
	</aside>

	<main class="dak-dashboard-main">
		<header class="dak-dashboard-topbar">
			<?php if ( 'dashboard' === $active_tab ) : ?>
				<div class="dak-dashboard-search">
					<span class="dak-dashboard-search-icon" aria-hidden="true"><?php echo $dak_patient_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<input
						type="search"
						id="dak-dashboard-topbar-search"
						data-live-search="doctor_ak_patient_dashboard_search"
						data-live-search-nonce="dakPatientDashboard"
						data-live-search-groups="doctors,appointments"
						placeholder="<?php esc_attr_e( 'Search doctors, appointments…', 'doctor-ak-portal' ); ?>"
						aria-label="<?php esc_attr_e( 'Search doctors, appointments…', 'doctor-ak-portal' ); ?>"
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

		<?php if ( 'profile' === $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1><?php esc_html_e( 'Edit Profile', 'doctor-ak-portal' ); ?></h1>
				<a class="dak-link" href="<?php echo esc_url( $dashboard_url ); ?>">&larr; <?php esc_html_e( 'Back to Dashboard', 'doctor-ak-portal' ); ?></a>
			</div>

			<div class="dak-dashboard-card dak-dashboard-profile-form">
				<?php echo $profile_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own profile-form.php template, which escapes its own output. ?>
			</div>

		<?php elseif ( 'settings' === $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1><?php esc_html_e( 'Settings', 'doctor-ak-portal' ); ?></h1>
			</div>

			<div class="dak-dashboard-card">
				<?php echo $settings_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own dashboard-settings-tab.php template, which escapes its own output. ?>
			</div>

		<?php elseif ( 'appointments' === $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></h1>
			</div>

			<div id="dak-patient-appointments-tab-content">
				<?php echo $appointments_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own patient-appointments-list.php template, which escapes its own output. ?>
			</div>

		<?php elseif ( 'notifications' === $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?></h1>
			</div>

			<?php echo $notifications_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own notifications-list.php template, which escapes its own output. ?>

		<?php elseif ( 'payments' === $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1><?php esc_html_e( 'Payments', 'doctor-ak-portal' ); ?></h1>
				<p><?php esc_html_e( 'Every payment you\'ve made while booking an appointment.', 'doctor-ak-portal' ); ?></p>
			</div>

			<?php echo $payments_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own patient-payments-tab.php template, which escapes its own output. ?>

		<?php elseif ( 'medical-history' === $active_tab ) : ?>

			<div class="dak-dashboard-greeting">
				<h1><?php esc_html_e( 'Medical History', 'doctor-ak-portal' ); ?></h1>
				<p><?php esc_html_e( 'Visit notes your doctors have added after completed appointments.', 'doctor-ak-portal' ); ?></p>
			</div>

			<?php echo $medical_history_tab_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own patient-medical-history.php template, which escapes its own output. ?>

		<?php else : ?>

		<div class="dak-page-head">
			<div>
				<h1><?php echo esc_html( sprintf( /* translators: %s: patient's first name or display name. */ __( 'Hello, %s', 'doctor-ak-portal' ), $first_name ) ); ?></h1>
				<p><?php esc_html_e( 'Your appointments, payments and visit records in one place.', 'doctor-ak-portal' ); ?></p>
			</div>
			<button type="button" class="dak-button dak-button-primary dak-patient-book-cta" data-dak-book-appointment>
				<?php esc_html_e( 'Book appointment', 'doctor-ak-portal' ); ?>
			</button>
		</div>

		<?php if ( $next_appointment ) : ?>
			<section class="dak-next-appointment" aria-labelledby="dak-next-appointment-title">
				<div class="dak-next-appointment-when">
					<span class="dak-next-appointment-eyebrow" id="dak-next-appointment-title"><?php esc_html_e( 'Next appointment', 'doctor-ak-portal' ); ?></span>
					<strong class="dak-next-appointment-date"><?php echo esc_html( $next_appointment['datetime_label'] ); ?></strong>
					<span class="dak-next-appointment-countdown"><?php echo esc_html( $next_appointment['countdown_label'] ); ?> &middot; <?php echo esc_html( wp_timezone_string() ); ?></span>
				</div>
				<div class="dak-next-appointment-who">
					<strong><?php echo esc_html( sprintf( 'Dr. %s', $next_appointment['doctor_name'] ) ); ?></strong>
					<?php if ( '' !== $next_appointment['doctor_specialization'] ) : ?>
						<span><?php echo esc_html( $next_appointment['doctor_specialization'] ); ?></span>
					<?php endif; ?>
					<span>
						<?php
						echo esc_html(
							'video' === $next_appointment['type']
								? __( 'Video visit', 'doctor-ak-portal' )
								: ( '' !== $next_appointment['clinic_name'] ? $next_appointment['clinic_name'] : $next_appointment['type_label'] )
						);
						?>
					</span>
				</div>
				<div class="dak-next-appointment-status">
					<span class="dak-status-pill dak-status-pill-outline dak-status-pill-<?php echo esc_attr( $next_appointment['status_badge_class'] ); ?>"><?php echo esc_html( $next_appointment['status_label'] ); ?></span>
					<?php if ( ! empty( $next_appointment['video_call']['can_join'] ) ) : ?>
						<button type="button" class="dak-button dak-button-primary dak-button-sm" data-join-video-call data-room-url="<?php echo esc_url( $next_appointment['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join video visit', 'doctor-ak-portal' ); ?></button>
					<?php elseif ( $appointments_url ) : ?>
						<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'View details', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $unpaid_count > 0 ) : ?>
			<div class="dak-inline-notice is-warning" role="status">
				<span>
					<strong><?php echo esc_html( sprintf( /* translators: %s: amount due, e.g. "PKR 2,500". */ __( 'Amount due: %s', 'doctor-ak-portal' ), 'PKR ' . number_format_i18n( $unpaid_total ) ) ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of unpaid appointments. */
							_n( '%d appointment is awaiting payment.', '%d appointments are awaiting payment.', $unpaid_count, 'doctor-ak-portal' ),
							$unpaid_count
						)
					);
					?>
				</span>
				<?php if ( $payments_url ) : ?>
					<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $payments_url ); ?>"><?php esc_html_e( 'View payments', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="dak-patient-dashboard-grid">
			<div class="dak-patient-dashboard-main-col">
				<section class="dak-dashboard-card dak-dashboard-appointments" id="dak-patient-appointments">
					<div class="dak-dashboard-card-header">
						<h2><?php esc_html_e( 'Upcoming Appointments', 'doctor-ak-portal' ); ?></h2>
						<a class="dak-link" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'View all appointments', 'doctor-ak-portal' ); ?> &rarr;</a>
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

						<div class="dak-patient-appt-reminder">
							<span class="dak-patient-appt-reminder-icon"><?php echo $dak_patient_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<div class="dak-patient-appt-reminder-body">
								<strong><?php esc_html_e( "Don't miss your appointments", 'doctor-ak-portal' ); ?></strong>
								<p><?php esc_html_e( 'Stay on track with your health. Book or manage your appointments easily.', 'doctor-ak-portal' ); ?></p>
							</div>
							<button type="button" class="dak-button dak-button-secondary" data-dak-book-appointment><?php esc_html_e( 'Book Now', 'doctor-ak-portal' ); ?></button>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<div class="dak-patient-dashboard-side-col">
				<?php if ( ! empty( $required_profile_items ) ) : ?>
					<section class="dak-dashboard-card dak-patient-profile-summary">
						<div class="dak-dashboard-card-header">
							<h2><?php esc_html_e( 'Complete your details', 'doctor-ak-portal' ); ?></h2>
						</div>
						<p class="dak-field-hint"><?php esc_html_e( 'The clinic uses these to confirm and contact you about appointments.', 'doctor-ak-portal' ); ?></p>
						<ul class="dak-patient-missing-list">
							<?php foreach ( $required_profile_items as $dak_required_item ) : ?>
								<li><?php echo esc_html( $dak_required_item ); ?></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $profile_url ) : ?>
							<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Update profile', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<section class="dak-dashboard-card dak-dashboard-notifications">
					<div class="dak-dashboard-card-header">
						<h2><?php esc_html_e( 'Recent Activity', 'doctor-ak-portal' ); ?></h2>
					</div>
					<?php if ( ! empty( $recent_activity ) ) : ?>
						<ul class="dak-patient-activity-list">
							<?php foreach ( $recent_activity as $activity_entry ) : ?>
								<li>
									<span class="dak-patient-activity-icon is-<?php echo esc_attr( $activity_entry['type'] ); ?>">
										<?php echo $dak_patient_icons[ isset( $dak_activity_icons[ $activity_entry['type'] ] ) ? $dak_activity_icons[ $activity_entry['type'] ] : 'calendar' ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</span>
									<span class="dak-patient-activity-text">
										<span class="dak-patient-activity-label"><?php echo esc_html( $activity_entry['label'] ); ?></span>
										<span class="dak-patient-activity-date"><?php echo esc_html( $activity_entry['date'] ); ?></span>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="dak-empty-state"><?php esc_html_e( 'You have no recent activity yet.', 'doctor-ak-portal' ); ?></p>
					<?php endif; ?>
				</section>

				<section class="dak-dashboard-card dak-patient-quick-actions">
					<div class="dak-dashboard-card-header">
						<h2><?php esc_html_e( 'Quick Actions', 'doctor-ak-portal' ); ?></h2>
					</div>
					<div class="dak-patient-quick-actions-grid">
						<button type="button" class="dak-patient-quick-action" data-dak-book-appointment>
							<span class="dak-nav-icon"><?php echo $dak_patient_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
						</button>
						<button type="button" class="dak-patient-quick-action" data-dak-book-appointment data-booking-type="video">
							<span class="dak-nav-icon"><?php echo $dak_patient_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Video Consult', 'doctor-ak-portal' ); ?>
						</button>
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

	<div class="dak-portal dak-modal" id="dak-request-refund-modal" aria-hidden="true">
		<div class="dak-modal-overlay" data-dak-request-refund-close></div>

		<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-request-refund-title">
			<button type="button" class="dak-modal-close" data-dak-request-refund-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

			<div class="dak-modal-header">
				<h2 id="dak-request-refund-title"><?php esc_html_e( 'Request Refund', 'doctor-ak-portal' ); ?></h2>
			</div>

			<div class="dak-alert dak-alert-error dak-hidden" id="dak-request-refund-error" role="alert"></div>

			<input type="hidden" id="dak-request-refund-appointment-id" value="0">

			<div class="dak-field">
				<label for="dak-request-refund-reason"><?php esc_html_e( 'Reason', 'doctor-ak-portal' ); ?></label>
				<textarea id="dak-request-refund-reason" rows="4" maxlength="500" placeholder="<?php esc_attr_e( 'Tell us why you\'re requesting a refund…', 'doctor-ak-portal' ); ?>"></textarea>
			</div>

			<p class="dak-field-hint"><?php esc_html_e( 'Our team will review your request and process the refund to your original payment method.', 'doctor-ak-portal' ); ?></p>

			<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-request-refund-save">
				<span class="dak-button-label"><?php esc_html_e( 'Submit Request', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	</div>

	<?php if ( 'dashboard' === $active_tab ) : ?>
		<nav class="dak-patient-bottom-nav">
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="is-active"><?php echo $dak_patient_icons['dashboard']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Dashboard', 'doctor-ak-portal' ); ?></span></a>
			<?php if ( $appointments_url ) : ?>
				<a href="<?php echo esc_url( $appointments_url ); ?>"><?php echo $dak_patient_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></span></a>
			<?php endif; ?>
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
