<?php
/**
 * Template: A single upcoming-appointment row on the patient dashboard —
 * styled the same as the admin portal's appointment rows (accent-bar card,
 * avatar + info + meta + tags + actions), with the patient-facing actions
 * (Pay Now / Join Call / Reschedule / Cancel / Request Refund) as the row's
 * action buttons.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $appointment Row from Appointments::patient_dashboard_data()['groups'][...].
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dak_patient_appt_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for the avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_patient_appt_initials( $name ) {
		$words    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			if ( '' !== $word ) {
				$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
			}
		}

		return '' !== $initials ? $initials : '?';
	}
endif;

$dak_patient_appt_icons = array(
	'reschedule' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10a6 6 0 1 1 1.8 4.3"/><path d="M4 14v-3.5H7.5"/><path d="M10 6.5v4l2.5 1.5"/></svg>',
	'cancel'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5l10 10M15 5L5 15"/></svg>',
);

$date_timestamp = strtotime( $appointment['date'] );
$time_timestamp = strtotime( $appointment['time'] );
$date_label     = $date_timestamp ? date_i18n( 'D, M j', $date_timestamp ) : $appointment['date'];
$time_label     = $time_timestamp ? date_i18n( 'g:i A', $time_timestamp ) : $appointment['time'];

$is_cancellable      = ! in_array( $appointment['status'], array( 'cancelled', 'completed' ), true );
$can_pay_now         = ! $appointment['is_paid'] && (float) $appointment['charge'] > 0;
$can_request_refund  = 'cancelled' === $appointment['status'] && $appointment['is_paid'] && 'online' === $appointment['payment_mode'] && '' === $appointment['refund_status'];
?>
<div class="dak-admin-record-row" data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>">
	<div class="dak-admin-record-row-main">
		<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
			<?php if ( $appointment['doctor_avatar_url'] ) : ?>
				<img src="<?php echo esc_url( $appointment['doctor_avatar_url'] ); ?>" alt="">
			<?php else : ?>
				<?php echo esc_html( dak_patient_appt_initials( $appointment['doctor_name'] ) ); ?>
			<?php endif; ?>
		</span>

		<span class="dak-admin-record-row-info">
			<strong><?php echo esc_html( sprintf( 'Dr. %s', $appointment['doctor_name'] ) ); ?></strong>
			<span class="dak-admin-record-row-id"><?php echo esc_html( '' !== $appointment['doctor_specialization'] ? $appointment['doctor_specialization'] : $appointment['type_label'] ); ?></span>
		</span>

		<span class="dak-admin-record-row-meta">
			<span class="dak-patient-appt-countdown"><?php echo esc_html( $appointment['countdown_label'] ); ?></span><br>
			<span class="dak-clinic-card-meta"><?php echo esc_html( $date_label ); ?> &middot; <?php echo esc_html( $time_label ); ?></span>
		</span>

		<span class="dak-admin-record-row-tags">
			<span class="dak-status-pill dak-status-pill-outline dak-status-pill-<?php echo esc_attr( $appointment['status_badge_class'] ); ?>"><?php echo esc_html( $appointment['status_label'] ); ?></span>
			<?php if ( ! empty( $appointment['video_call']['applicable'] ) && ! $appointment['video_call']['can_join'] && '' !== $appointment['video_call']['hint'] ) : ?>
				<span class="dak-status-pill dak-status-pill-disabled" title="<?php echo esc_attr( $appointment['video_call']['hint'] ); ?>"><?php echo esc_html( $appointment['video_call']['hint'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $appointment['is_instant'] ) && (float) $appointment['surcharge'] > 0 ) : ?>
				<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-pending" title="<?php esc_attr_e( 'Booked inside the instant-booking window', 'doctor-ak-portal' ); ?>">
					<?php echo esc_html( sprintf( /* translators: %s: surcharge amount. */ __( 'Instant · +PKR%s', 'doctor-ak-portal' ), number_format( (float) $appointment['surcharge'], 0 ) ) ); ?>
				</span>
			<?php endif; ?>
			<?php if ( 'requested' === $appointment['refund_status'] ) : ?>
				<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-pending"><?php esc_html_e( 'Refund Requested', 'doctor-ak-portal' ); ?></span>
			<?php elseif ( 'processed' === $appointment['refund_status'] ) : ?>
				<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Refund Processed', 'doctor-ak-portal' ); ?></span>
			<?php endif; ?>
		</span>

		<span class="dak-admin-record-row-actions">
			<?php if ( ! empty( $appointment['video_call']['can_join'] ) ) : ?>
				<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $appointment['video_call']['room_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
			<?php if ( $can_request_refund ) : ?>
				<button type="button" class="dak-status-pill dak-status-pill-action" data-request-refund data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"><?php esc_html_e( 'Request Refund', 'doctor-ak-portal' ); ?></button>
			<?php endif; ?>
			<?php if ( $can_pay_now ) : ?>
				<button type="button" class="dak-status-pill dak-status-pill-action" data-pay-now data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>">
					<?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay PKR%s', 'doctor-ak-portal' ), number_format( (float) $appointment['charge'], 0 ) ) ); ?>
				</button>
			<?php endif; ?>
			<?php if ( ! empty( $appointment['reschedulable'] ) ) : ?>
				<button
					type="button"
					class="dak-icon-button"
					data-reschedule-appointment
					data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"
					data-date="<?php echo esc_attr( $appointment['date'] ); ?>"
					data-time="<?php echo esc_attr( $appointment['time'] ); ?>"
					title="<?php esc_attr_e( 'Reschedule', 'doctor-ak-portal' ); ?>"
					aria-label="<?php esc_attr_e( 'Reschedule', 'doctor-ak-portal' ); ?>"
				><?php echo $dak_patient_appt_icons['reschedule']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<?php endif; ?>
			<?php if ( $is_cancellable ) : ?>
				<button
					type="button"
					class="dak-icon-button dak-icon-button-danger"
					data-cancel-appointment
					data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"
					data-refund-eligible="<?php echo esc_attr( $appointment['refund_eligible'] ? '1' : '0' ); ?>"
					title="<?php esc_attr_e( 'Cancel', 'doctor-ak-portal' ); ?>"
					aria-label="<?php esc_attr_e( 'Cancel', 'doctor-ak-portal' ); ?>"
				><?php echo $dak_patient_appt_icons['cancel']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<?php endif; ?>
		</span>
	</div>

	<?php if ( ! empty( $appointment['video_call']['can_join'] ) ) : ?>
		<div class="dak-admin-record-row-secondary">
			<span class="dak-admin-record-row-secondary-empty"><?php esc_html_e( "If it says waiting for the host, please wait a moment — your doctor needs to start the call first.", 'doctor-ak-portal' ); ?></span>
		</div>
	<?php endif; ?>
</div>
