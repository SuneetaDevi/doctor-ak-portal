<?php
/**
 * Template: "Notifications" tab body — shared by the doctor, patient, and
 * admin dashboards, since the list/mark-read behaviour is identical for
 * all three; only which rows they see (their own recipient_id) differs.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $notifications List of rows, see Notification_Center::for_user().
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
);

$dak_has_unread = ! empty(
	array_filter(
		$notifications,
		function ( $notification ) {
			return ! $notification['is_read'];
		}
	)
);
?>
<section class="dak-dashboard-card dak-notifications-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Notifications', 'doctor-ak-portal' ); ?></h2>
		<?php if ( $dak_has_unread ) : ?>
			<button type="button" class="dak-link-button" id="dak-notifications-mark-all-read"><?php esc_html_e( 'Mark all as read', 'doctor-ak-portal' ); ?></button>
		<?php endif; ?>
	</div>

	<?php if ( empty( $notifications ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No notifications yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<ul class="dak-notifications-list">
			<?php foreach ( $notifications as $notification ) : ?>
				<li
					class="dak-notification-row <?php echo $notification['is_read'] ? '' : 'is-unread'; ?>"
					data-notification-id="<?php echo esc_attr( $notification['id'] ); ?>"
					<?php echo $notification['is_read'] ? '' : 'data-mark-read'; ?>
				>
					<span class="dak-notification-icon is-<?php echo esc_attr( $notification['type'] ); ?>">
						<?php echo isset( $dak_notification_icons[ $notification['type'] ] ) ? $dak_notification_icons[ $notification['type'] ] : $dak_notification_icons['booked']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<span class="dak-notification-body">
						<span class="dak-notification-message"><?php echo esc_html( $notification['message'] ); ?></span>
						<span class="dak-notification-date"><?php echo esc_html( $notification['date'] ); ?></span>
					</span>
					<?php if ( ! $notification['is_read'] ) : ?>
						<span class="dak-notification-dot" aria-hidden="true"></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
