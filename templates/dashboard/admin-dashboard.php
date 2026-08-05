<?php
/**
 * Template: Administrator dashboard body for the [admin_dashboard] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string    $section          Active section slug (e.g. 'dashboard', 'doctors', 'patients', 'appointments'…).
 * @var string    $section_label    Human-readable label for the active section.
 * @var array     $nav_groups       Sidebar groups: [ [ 'label' => 'Main', 'items' => [ [ 'slug', 'label', 'url', 'is_active', 'badge' ], … ] ], … ].
 * @var string    $dashboard_url    URL of this dashboard page.
 * @var string    $logout_url       Nonce-protected logout URL.
 * @var \WP_User  $current_user     Currently logged-in administrator.
 * @var string    $content_html      Pre-rendered main-column content for the active section.
 * @var string    $modal_html        Pre-rendered Add/Edit modal (Doctor Sessions/Services/Video Consultation/Appointments sections only).
 * @var string    $role              Roles::DOCTOR_ROLE, Roles::PATIENT_ROLE, or Roles::RECEPTIONIST_ROLE (only for the Users sections).
 * @var bool      $is_users_section  Whether the active section is 'doctors', 'patients', or 'receptionist'.
 * @var bool      $is_user_form_view Whether `?view=form` is active (full-screen Add/Edit form instead of the table).
 * @var bool      $is_receptionist   Whether the logged-in viewer is a Receptionist (vs. a full Administrator) — gets a cut-down, mostly read-only view of this same dashboard.
 * @var string    $theme             'light' or 'dark' — the admin's saved dashboard theme preference.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_add_button_labels = array(
	'doctors'      => __( '+ Add Doctor', 'doctor-ak-portal' ),
	'patients'     => __( '+ Add Patient', 'doctor-ak-portal' ),
	'receptionist' => __( '+ Add Receptionist', 'doctor-ak-portal' ),
);
$add_button_label = isset( $dak_add_button_labels[ $section ] ) ? $dak_add_button_labels[ $section ] : __( '+ Add', 'doctor-ak-portal' );
$dak_add_user_url  = $dashboard_url ? add_query_arg( array( 'section' => $section, 'view' => 'form' ), $dashboard_url ) : '';
// A receptionist only ever has read access to the Doctors/Patients tables
// (they can never reach the 'receptionist' staff-account tab itself, see
// Admin_Dashboard::RECEPTIONIST_ALLOWED_SECTIONS) — so hide the Add button.
$dak_show_add_button = $is_users_section && ! $is_receptionist;

$dak_admin_icons = array(
	'dashboard'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="2.5" width="6.5" height="6.5" rx="1.2"/><rect x="11" y="2.5" width="6.5" height="6.5" rx="1.2"/><rect x="2.5" y="11" width="6.5" height="6.5" rx="1.2"/><rect x="11" y="11" width="6.5" height="6.5" rx="1.2"/></svg>',
	'calendar'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'clock'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7"/><path d="M10 6v4l3 2"/></svg>',
	'users'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="2.8"/><path d="M1.8 16c0-2.9 2.3-4.8 5.2-4.8s5.2 1.9 5.2 4.8"/><path d="M13 7.2a2.6 2.6 0 1 1 3.6 2.4"/><path d="M14.5 11.3c2 .3 3.7 1.7 3.7 4"/></svg>',
	'person'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'headset'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v-1a7 7 0 0 1 14 0v1"/><rect x="2.3" y="10.5" width="3.4" height="5" rx="1.2"/><rect x="14.3" y="10.5" width="3.4" height="5" rx="1.2"/><path d="M16 15.5v.5a2.5 2.5 0 0 1-2.5 2.5H11"/></svg>',
	'pin'         => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'settings'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="2.6"/><path d="M10 2.8v2M10 15.2v2M17.2 10h-2M4.8 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/></svg>',
	'logout'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 17H4a1.5 1.5 0 0 1-1.5-1.5v-11A1.5 1.5 0 0 1 4 3h3.5"/><path d="M13 14l4-4-4-4"/><path d="M17 10H7.5"/></svg>',
	'bell'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8a5 5 0 0 1 10 0c0 3.2 1 4.3 1.5 5H3.5C4 12.3 5 11.2 5 8z"/><path d="M8.2 15.5a1.8 1.8 0 0 0 3.6 0"/></svg>',
	'person-plus' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="7" r="3"/><path d="M2.5 17c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><path d="M15.5 7.5v4M13.5 9.5h4"/></svg>',
	'money'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2v7.6M12.2 8.1c0-1-1-1.6-2.2-1.6s-2.2.6-2.2 1.5c0 2.2 4.4 1 4.4 3.2 0 .9-1 1.5-2.2 1.5s-2.2-.6-2.2-1.6"/></svg>',
	'search'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg>',
);

$dak_admin_section_icons = array(
	'dashboard'       => 'dashboard',
	'appointments'    => 'calendar',
	'billing'         => 'money',
	'encounters'      => 'clock',
	'notifications'   => 'bell',
	'doctor-requests' => 'person-plus',
	'patients'        => 'users',
	'doctors'         => 'person',
	'receptionist'    => 'headset',
	'clinic'          => 'pin',
	'services'        => 'settings',
	'doctor-sessions'  => 'clock',
	'role-permissions' => 'settings',
	'locations'        => 'pin',
	'settings'         => 'settings',
);
?>
<div class="dak-portal dak-dashboard dak-admin-dashboard" data-role="<?php echo esc_attr( $is_receptionist ? 'receptionist' : 'administrator' ); ?>" data-theme="<?php echo esc_attr( $theme ); ?>">
	<button type="button" class="dak-dashboard-sidebar-toggle" id="dak-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle navigation', 'doctor-ak-portal' ); ?>" aria-expanded="false" aria-controls="dak-dashboard-sidebar">
		<span></span><span></span><span></span>
	</button>

	<aside class="dak-dashboard-sidebar" id="dak-dashboard-sidebar">
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
				<span><?php esc_html_e( 'Admin portal', 'doctor-ak-portal' ); ?></span>
			</span>
		</div>

		<div class="dak-sidebar-doctor-card">
			<span class="dak-avatar dak-avatar-md">
				<?php echo $dak_admin_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<span class="dak-sidebar-doctor-name"><?php echo esc_html( $current_user->display_name ); ?></span>
			<span class="dak-specialty-tag dak-sidebar-doctor-tagline"><?php echo esc_html( $is_receptionist ? __( 'Receptionist', 'doctor-ak-portal' ) : __( 'Clinic Admin', 'doctor-ak-portal' ) ); ?></span>
		</div>

		<nav class="dak-dashboard-nav">
			<?php foreach ( $nav_groups as $group ) : ?>
				<div class="dak-nav-group">
					<span class="dak-nav-group-label"><?php echo esc_html( strtoupper( $group['label'] ) ); ?></span>
					<ul>
						<?php foreach ( $group['items'] as $item ) : ?>
							<?php $icon = isset( $dak_admin_section_icons[ $item['slug'] ] ) ? $dak_admin_section_icons[ $item['slug'] ] : 'dashboard'; ?>
							<li class="<?php echo $item['is_active'] ? 'is-active' : ''; ?>">
								<a href="<?php echo esc_url( $item['url'] ); ?>">
									<span class="dak-nav-icon"><?php echo $dak_admin_icons[ $icon ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php echo esc_html( $item['label'] ); ?>
									<?php if ( ! empty( $item['badge'] ) ) : ?><span class="dak-nav-badge"<?php echo 'notifications' === $item['slug'] ? ' id="dak-notifications-badge"' : ''; ?>><?php echo esc_html( $item['badge'] ); ?></span><?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</nav>

		<a class="dak-sidebar-logout" href="<?php echo esc_url( $logout_url ); ?>"><span class="dak-nav-icon"><?php echo $dak_admin_icons['logout']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Logout', 'doctor-ak-portal' ); ?></a>
	</aside>

	<main class="dak-dashboard-main">
		<?php if ( ! $is_user_form_view ) : ?>
			<header class="dak-dashboard-topbar">
				<div class="dak-dashboard-search">
					<span class="dak-dashboard-search-icon" aria-hidden="true"><?php echo $dak_admin_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<input type="search" placeholder="<?php esc_attr_e( 'Search doctors, patients, appointments…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search doctors, patients, appointments…', 'doctor-ak-portal' ); ?>">
				</div>
				<?php
				echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own topbar-actions.php template, which escapes its own output.
					'dashboard/partials/topbar-actions.php',
					array(
						'notifications_url'          => $notifications_url,
						'unread_notifications_count' => $unread_notifications_count,
					)
				);
				?>
			</header>
		<?php endif; ?>

		<?php if ( $is_users_section && ! $is_user_form_view ) : ?>
			<div class="dak-dashboard-greeting dak-admin-users-header">
				<div>
					<h1><?php echo esc_html( $section_label ); ?></h1>
					<p>
						<?php
						echo esc_html(
							$dak_show_add_button
								? __( 'Add, edit, deactivate or delete accounts.', 'doctor-ak-portal' )
								: __( 'Read-only directory.', 'doctor-ak-portal' )
						);
						?>
					</p>
				</div>
				<?php if ( $dak_show_add_button ) : ?>
					<a class="dak-button dak-button-primary" href="<?php echo esc_url( $dak_add_user_url ); ?>"><?php echo esc_html( $add_button_label ); ?></a>
				<?php endif; ?>
			</div>

			<section class="dak-dashboard-card dak-admin-users-card">
				<?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own admin-user-table.php template, which escapes its own output. ?>
			</section>
		<?php elseif ( $is_user_form_view ) : ?>
			<?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own admin-user-form-screen.php template, which escapes its own output. ?>
		<?php else : ?>
			<?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own admin-overview.php/admin-placeholder.php templates, which escape their own output. ?>
		<?php endif; ?>
	</main>
</div>

<?php echo $modal_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own admin-*-modal.php templates, which escape their own output. ?>
