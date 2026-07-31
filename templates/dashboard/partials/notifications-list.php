<?php
/**
 * Template: "Notifications" tab body — shared by the doctor, patient, and
 * admin dashboards, since the list/mark-read/grouping/filter behaviour is
 * identical for all three; only which rows they see (their own
 * recipient_id) differs. Grouped Facebook/Instagram-style into Today /
 * Yesterday / Earlier sections, with a date filter above them.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $notification_groups 'today'|'yesterday'|'earlier' => array of rows, see Notification_Center::group_by_recency().
 * @var string $appointments_url    Same-page URL of this viewer's own Appointments tab/section — clicking a notification tied to an appointment jumps straight to it there (via a #dak-appointment-{id} anchor). '' if this viewer has no Appointments tab.
 * @var string $selected_date       'YYYY-MM-DD', or '' if unfiltered.
 * @var string $filter_field_name   Hidden field name the filter form must resubmit to stay on this tab/section — 'tab' (doctor/patient) or 'section' (admin).
 * @var string $filter_field_value  Hidden field value — always 'notifications'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_notification_icons = array(
	'booked'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'cancelled' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5l10 10M15 5L5 15"/></svg>',
	'paid'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l3.5 3.5L16 6"/></svg>',
	'completed' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l3.5 3.5L16 6"/></svg>',
	'doctor_registered' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="7" r="3"/><path d="M2.5 17c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><path d="M15.5 7.5v4M13.5 9.5h4"/></svg>',
	'doctor_approved'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M6.8 10.2l2.2 2.2 4.2-4.6"/></svg>',
	'refund_requested'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10a6 6 0 1 1 1.8 4.3"/><path d="M4 14v-3.5H7.5"/><path d="M10 6.5v4l2.5 1.5"/></svg>',
	'refund_processed'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10a6 6 0 1 1 1.8 4.3"/><path d="M4 14v-3.5H7.5"/><path d="M8 10l1.5 1.5L12.5 8"/></svg>',
	'rescheduled'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2V10l2.8 1.8"/></svg>',
);

/**
 * Renders one notification's <li>, linking to its appointment (if any) via
 * a #dak-appointment-{id} anchor on the viewer's own Appointments tab.
 *
 * @param array  $notification    Row from Notification_Center::for_user().
 * @param string $appointments_url See file docblock.
 * @param array  $icons           Type slug => inline SVG markup.
 * @return void
 */
if ( ! function_exists( 'dak_render_notification_row' ) ) :
	function dak_render_notification_row( array $notification, $appointments_url, array $icons ) {
		$target = ( $notification['appointment_id'] > 0 && ! empty( $appointments_url ) )
			? $appointments_url . '#dak-appointment-' . $notification['appointment_id']
			: '';
		$tag    = '' !== $target ? 'a' : 'div';
		?>
		<li
			class="dak-notification-row <?php echo $notification['is_read'] ? '' : 'is-unread'; ?>"
			data-notification-id="<?php echo esc_attr( $notification['id'] ); ?>"
			<?php echo $notification['is_read'] ? '' : 'data-mark-read'; ?>
		>
			<<?php echo esc_html( $tag ); ?>
				class="dak-notification-row-link"
				<?php echo '' !== $target ? 'href="' . esc_url( $target ) . '"' : ''; ?>
			>
				<span class="dak-notification-icon is-<?php echo esc_attr( $notification['type'] ); ?>">
					<?php echo isset( $icons[ $notification['type'] ] ) ? $icons[ $notification['type'] ] : $icons['booked']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="dak-notification-body">
					<span class="dak-notification-message"><?php echo esc_html( $notification['message'] ); ?></span>
					<span class="dak-notification-date"><?php echo esc_html( $notification['date'] ); ?></span>
				</span>
				<?php if ( ! $notification['is_read'] ) : ?>
					<span class="dak-notification-dot" aria-hidden="true"></span>
				<?php endif; ?>
			</<?php echo esc_html( $tag ); ?>>
		</li>
		<?php
	}
endif;

$dak_notification_section_labels = array(
	'today'     => __( 'Today', 'doctor-ak-portal' ),
	'yesterday' => __( 'Yesterday', 'doctor-ak-portal' ),
	'earlier'   => __( 'Earlier', 'doctor-ak-portal' ),
);

$dak_total_count = count( $notification_groups['today'] ) + count( $notification_groups['yesterday'] ) + count( $notification_groups['earlier'] );

$dak_has_unread = false;

foreach ( $notification_groups as $dak_group_rows ) {
	foreach ( $dak_group_rows as $dak_row ) {
		if ( ! $dak_row['is_read'] ) {
			$dak_has_unread = true;
			break 2;
		}
	}
}
?>
<section class="dak-dashboard-card dak-appt-filters-card">
	<form method="get" class="dak-appt-filters-form">
		<input type="hidden" name="<?php echo esc_attr( $filter_field_name ); ?>" value="<?php echo esc_attr( $filter_field_value ); ?>">
		<div class="dak-field">
			<label for="dak-notifications-filter-date"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-notifications-filter-date" name="date" value="<?php echo esc_attr( $selected_date ); ?>">
		</div>
		<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
		<?php if ( '' !== $selected_date ) : ?>
			<a class="dak-link" href="?<?php echo esc_attr( $filter_field_name ); ?>=<?php echo esc_attr( $filter_field_value ); ?>"><?php esc_html_e( 'Clear filter', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</form>
</section>

<section class="dak-dashboard-card dak-notifications-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?></h2>
		<?php if ( $dak_has_unread ) : ?>
			<button type="button" class="dak-link-button" id="dak-notifications-mark-all-read"><?php esc_html_e( 'Mark all as read', 'doctor-ak-portal' ); ?></button>
		<?php endif; ?>
	</div>

	<?php if ( 0 === $dak_total_count ) : ?>
		<p class="dak-empty-state">
			<?php
			echo '' !== $selected_date
				? esc_html__( 'No notifications on this date.', 'doctor-ak-portal' )
				: esc_html__( 'No notifications yet.', 'doctor-ak-portal' );
			?>
		</p>
	<?php else : ?>
		<?php foreach ( $dak_notification_section_labels as $dak_group_key => $dak_group_label ) : ?>
			<?php if ( ! empty( $notification_groups[ $dak_group_key ] ) ) : ?>
				<div class="dak-notifications-group">
					<h3 class="dak-notifications-group-label"><?php echo esc_html( $dak_group_label ); ?></h3>
					<ul class="dak-notifications-list">
						<?php foreach ( $notification_groups[ $dak_group_key ] as $dak_notification ) : ?>
							<?php dak_render_notification_row( $dak_notification, $appointments_url, $dak_notification_icons ); ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
