<?php
/**
 * Template: A single upcoming-appointment row on the doctor dashboard —
 * patient avatar/name, a countdown badge, status/payment pills, and a
 * Join Call action for video appointments. Mirrors
 * patient-appointment-row.php but shows the patient (not the doctor) and
 * has no Pay Now / Cancel actions — those belong to the patient.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $appointment Row from Appointments::doctor_dashboard_data()['groups'][...].
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dak_doctor_appt_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for the avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_doctor_appt_initials( $name ) {
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

$date_timestamp = strtotime( $appointment['date'] );
$time_timestamp = strtotime( $appointment['time'] );
$date_label     = $date_timestamp ? date_i18n( 'D, M j', $date_timestamp ) : $appointment['date'];
$time_label     = $time_timestamp ? date_i18n( 'g:i A', $time_timestamp ) : $appointment['time'];
?>
<div class="dak-patient-appt-row" data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>">
	<div class="dak-patient-appt-row-top">
		<div class="dak-patient-appt-row-doctor">
			<span class="dak-patient-appt-avatar">
				<?php if ( $appointment['patient_avatar_url'] ) : ?>
					<img src="<?php echo esc_url( $appointment['patient_avatar_url'] ); ?>" alt="">
				<?php else : ?>
					<?php echo esc_html( dak_doctor_appt_initials( $appointment['patient_name'] ) ); ?>
				<?php endif; ?>
			</span>
			<span class="dak-patient-appt-doctor-info">
				<strong><?php echo esc_html( $appointment['patient_name'] ); ?></strong>
				<span class="dak-patient-appt-specialty">
					<?php echo esc_html( $appointment['is_guest'] ? __( 'Guest booking', 'doctor-ak-portal' ) : $appointment['type_label'] ); ?>
				</span>
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
			<span class="dak-status-pill dak-status-pill-outline <?php echo $appointment['is_paid'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-pending'; ?>">
				<?php echo $appointment['is_paid'] ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?>
			</span>
			<?php if ( '' !== $appointment['service_name'] ) : ?>
				<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $appointment['service_name'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $appointment['video_call']['applicable'] ) && ! $appointment['video_call']['can_join'] && '' !== $appointment['video_call']['hint'] ) : ?>
				<span class="dak-status-pill dak-status-pill-disabled" title="<?php echo esc_attr( $appointment['video_call']['hint'] ); ?>"><?php echo esc_html( $appointment['video_call']['hint'] ); ?></span>
			<?php endif; ?>
		</div>

		<div class="dak-patient-appt-row-actions">
			<?php if ( ! empty( $appointment['video_call']['can_join'] ) ) : ?>
				<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $appointment['video_call']['room_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
