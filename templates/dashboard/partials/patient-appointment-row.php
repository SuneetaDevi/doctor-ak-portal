<?php
/**
 * Template: A single upcoming-appointment row on the patient dashboard —
 * doctor avatar/specialty, a countdown badge, status/payment pills, and
 * Pay Now / Cancel actions.
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
	'cancel' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5l10 10M15 5L5 15"/></svg>',
	'kebab'  => '<svg viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="4.5" r="1.4"/><circle cx="10" cy="10" r="1.4"/><circle cx="10" cy="15.5" r="1.4"/></svg>',
);

$date_timestamp = strtotime( $appointment['date'] );
$time_timestamp = strtotime( $appointment['time'] );
$date_label     = $date_timestamp ? date_i18n( 'D, M j', $date_timestamp ) : $appointment['date'];
$time_label     = $time_timestamp ? date_i18n( 'g:i A', $time_timestamp ) : $appointment['time'];

$is_cancellable = ! in_array( $appointment['status'], array( 'cancelled', 'completed' ), true );
$can_pay_now     = ! $appointment['is_paid'] && (float) $appointment['charge'] > 0;
?>
<div class="dak-patient-appt-row" data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>">
	<div class="dak-patient-appt-row-top">
		<div class="dak-patient-appt-row-doctor">
			<span class="dak-patient-appt-avatar">
				<?php if ( $appointment['doctor_avatar_url'] ) : ?>
					<img src="<?php echo esc_url( $appointment['doctor_avatar_url'] ); ?>" alt="">
				<?php else : ?>
					<?php echo esc_html( dak_patient_appt_initials( $appointment['doctor_name'] ) ); ?>
				<?php endif; ?>
			</span>
			<span class="dak-patient-appt-doctor-info">
				<strong><?php echo esc_html( sprintf( 'Dr. %s', $appointment['doctor_name'] ) ); ?></strong>
				<span class="dak-patient-appt-specialty"><?php echo esc_html( '' !== $appointment['doctor_specialization'] ? $appointment['doctor_specialization'] : $appointment['type_label'] ); ?></span>
			</span>
		</div>

		<div class="dak-patient-appt-row-meta">
			<span class="dak-patient-appt-countdown"><?php echo esc_html( $appointment['countdown_label'] ); ?></span>
			<span class="dak-patient-appt-datetime"><?php echo esc_html( $date_label ); ?> &middot; <strong><?php echo esc_html( $time_label ); ?></strong></span>
		</div>
	</div>

	<div class="dak-patient-appt-row-bottom">
		<div class="dak-patient-appt-row-tags">
			<span class="dak-status-pill dak-status-pill-outline dak-status-pill-<?php echo esc_attr( $appointment['status_badge_class'] ); ?>"><?php echo esc_html( $appointment['status_label'] ); ?></span>
			<?php if ( ! empty( $appointment['video_call']['applicable'] ) && ! $appointment['video_call']['can_join'] && '' !== $appointment['video_call']['hint'] ) : ?>
				<span class="dak-status-pill dak-status-pill-disabled" title="<?php echo esc_attr( $appointment['video_call']['hint'] ); ?>"><?php echo esc_html( $appointment['video_call']['hint'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $appointment['is_instant'] ) && (float) $appointment['surcharge'] > 0 ) : ?>
				<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-pending" title="<?php esc_attr_e( 'Booked inside the instant-booking window', 'doctor-ak-portal' ); ?>">
					<?php echo esc_html( sprintf( /* translators: %s: surcharge amount. */ __( 'Instant · +PKR%s', 'doctor-ak-portal' ), number_format( (float) $appointment['surcharge'], 0 ) ) ); ?>
				</span>
			<?php endif; ?>
		</div>

		<div class="dak-patient-appt-row-actions">
			<?php if ( ! empty( $appointment['video_call']['can_join'] ) ) : ?>
				<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $appointment['video_call']['room_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
			<?php if ( $can_pay_now ) : ?>
				<button type="button" class="dak-status-pill dak-status-pill-action" data-pay-now data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>">
					<?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay PKR%s', 'doctor-ak-portal' ), number_format( (float) $appointment['charge'], 0 ) ) ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $is_cancellable ) : ?>
				<div class="dak-patient-appt-kebab">
					<button
						type="button"
						class="dak-icon-button"
						data-kebab-toggle
						title="<?php esc_attr_e( 'More actions', 'doctor-ak-portal' ); ?>"
						aria-label="<?php esc_attr_e( 'More actions', 'doctor-ak-portal' ); ?>"
						aria-haspopup="true"
						aria-expanded="false"
					><?php echo $dak_patient_appt_icons['kebab']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					<div class="dak-patient-appt-kebab-menu" data-kebab-menu>
						<button
							type="button"
							class="dak-patient-appt-kebab-item is-danger"
							data-cancel-appointment
							data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"
							data-refund-eligible="<?php echo esc_attr( $appointment['refund_eligible'] ? '1' : '0' ); ?>"
						><?php echo $dak_patient_appt_icons['cancel']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Cancel Appointment', 'doctor-ak-portal' ); ?></button>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
