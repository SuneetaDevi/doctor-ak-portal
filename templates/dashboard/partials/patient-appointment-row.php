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

$datetime_timestamp = strtotime( $appointment['date'] . ' ' . $appointment['time'] );

$is_cancellable     = ! in_array( $appointment['status'], array( 'cancelled', 'completed' ), true );
$can_pay_now        = ! $appointment['is_paid'] && (float) $appointment['charge'] > 0;
$can_request_refund = 'cancelled' === $appointment['status'] && $appointment['is_paid'] && 'online' === $appointment['payment_mode'] && '' === $appointment['refund_status'];
$dak_can_join       = ! empty( $appointment['video_call']['can_join'] );
$dak_pay            = $appointment['is_paid'] ? array( __( 'Paid', 'doctor-ak-portal' ), 'dak-status-pill-is-active' ) : ( (float) $appointment['charge'] > 0 ? array( __( 'Pending', 'doctor-ak-portal' ), 'dak-status-pill-is-pending' ) : array( __( 'Nothing to pay', 'doctor-ak-portal' ), 'dak-status-pill-is-neutral' ) );
$dak_has_more       = ( $dak_can_join && $can_pay_now ) || $can_request_refund || ! empty( $appointment['reschedulable'] ) || $is_cancellable;
?>
<tr data-row data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>">
	<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
		<span class="dak-cell-stack">
			<span class="dak-cell-primary"><?php echo esc_html( sprintf( 'Dr. %s', $appointment['doctor_name'] ) ); ?></span>
			<span class="dak-cell-sub"><?php echo esc_html( '' !== $appointment['doctor_specialization'] ? $appointment['doctor_specialization'] : $appointment['type_label'] ); ?></span>
		</span>
	</td>
	<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'When', 'doctor-ak-portal' ); ?>">
		<span class="dak-cell-stack">
			<span class="dak-cell-strong is-tabular"><?php echo esc_html( $datetime_timestamp ? date_i18n( 'd M Y, h:i A', $datetime_timestamp ) : trim( $appointment['date'] . ' ' . $appointment['time'] ) ); ?></span>
			<span class="dak-cell-sub"><?php echo esc_html( $appointment['countdown_label'] ); ?></span>
		</span>
	</td>
	<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
		<span class="dak-cell-stack">
			<span class="dak-status-pill <?php echo esc_attr( \DoctorAKPortal\Includes\Dashboard_Format::status_class( $appointment['status'] ) ); ?>"><?php echo esc_html( $appointment['status_label'] ); ?></span>
			<?php if ( ! empty( $appointment['video_call']['applicable'] ) && ! $appointment['video_call']['can_join'] && '' !== $appointment['video_call']['hint'] ) : ?>
				<span class="dak-cell-note"><?php echo esc_html( $appointment['video_call']['hint'] ); ?></span>
			<?php endif; ?>
			<?php if ( $dak_can_join ) : ?>
				<span class="dak-cell-note"><?php esc_html_e( 'If it says waiting for the host, please wait — your doctor starts the call.', 'doctor-ak-portal' ); ?></span>
			<?php endif; ?>
			<?php if ( 'requested' === $appointment['refund_status'] ) : ?>
				<span class="dak-cell-note"><?php esc_html_e( 'Refund requested', 'doctor-ak-portal' ); ?></span>
			<?php elseif ( 'processed' === $appointment['refund_status'] ) : ?>
				<span class="dak-cell-note"><?php esc_html_e( 'Refund processed', 'doctor-ak-portal' ); ?></span>
			<?php endif; ?>
		</span>
	</td>
	<td data-label="<?php esc_attr_e( 'Payment', 'doctor-ak-portal' ); ?>">
		<span class="dak-cell-stack">
			<span class="dak-cell-strong is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $appointment['charge'], __( 'Free', 'doctor-ak-portal' ) ) ); ?></span>
			<span class="dak-pay-status <?php echo esc_attr( $dak_pay[1] ); ?>"><?php echo esc_html( $dak_pay[0] ); ?></span>
			<?php if ( ! empty( $appointment['is_instant'] ) && (float) $appointment['surcharge'] > 0 ) : ?>
				<span class="dak-cell-note"><?php echo esc_html( sprintf( /* translators: %s: surcharge amount. */ __( 'Includes %s instant booking fee', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $appointment['surcharge'] ) ) ); ?></span>
			<?php endif; ?>
		</span>
	</td>
	<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
		<div class="dak-row-actions">
			<?php if ( $dak_can_join ) : ?>
				<button type="button" class="dak-button dak-button-primary dak-button-sm" data-join-video-call data-room-url="<?php echo esc_url( $appointment['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join call', 'doctor-ak-portal' ); ?></button>
			<?php elseif ( $can_pay_now ) : ?>
				<button type="button" class="dak-button dak-button-primary dak-button-sm" data-pay-now data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay %s', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $appointment['charge'] ) ) ); ?></button>
			<?php endif; ?>
			<?php if ( $dak_has_more ) : ?>
				<details class="dak-row-menu">
					<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: doctor name. */ __( 'More actions for your appointment with Dr. %s', 'doctor-ak-portal' ), $appointment['doctor_name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
					<div class="dak-row-menu-panel" role="menu">
						<?php if ( $dak_can_join && $can_pay_now ) : ?>
							<button type="button" class="dak-row-menu-item" role="menuitem" data-pay-now data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay %s', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $appointment['charge'] ) ) ); ?></button>
						<?php endif; ?>
						<?php if ( ! empty( $appointment['reschedulable'] ) ) : ?>
							<button type="button" class="dak-row-menu-item" role="menuitem" data-reschedule-appointment data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>" data-date="<?php echo esc_attr( $appointment['date'] ); ?>" data-time="<?php echo esc_attr( $appointment['time'] ); ?>"><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
						<?php if ( $can_request_refund ) : ?>
							<button type="button" class="dak-row-menu-item" role="menuitem" data-request-refund data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"><?php esc_html_e( 'Request refund', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
						<?php if ( $is_cancellable ) : ?>
							<hr class="dak-row-menu-sep">
							<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-cancel-appointment data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>" data-refund-eligible="<?php echo esc_attr( $appointment['refund_eligible'] ? '1' : '0' ); ?>"><?php esc_html_e( 'Cancel appointment', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>
	</td>
</tr>