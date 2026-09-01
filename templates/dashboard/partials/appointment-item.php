<?php
/**
 * Template: Single appointment row, shared by the patient and doctor
 * dashboards' appointment cards.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $name           Patient's or doctor's display name (whichever side is viewing).
 * @var string $note           Secondary label — the appointment type, human-readable.
 * @var string $type           'clinic' or 'video'.
 * @var string $date           'YYYY-MM-DD'.
 * @var string $time           'HH:MM'.
 * @var string $payment_status 'paid' or 'pending'.
 * @var array  $video_call     See Appointments::video_call_info().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$formatted_datetime = ( $date && $time ) ? date_i18n( 'd/m/Y h:i A', strtotime( $date . ' ' . $time ) ) : trim( $date . ' ' . $time );
$is_paid            = 'paid' === $payment_status;
?>
<div class="dak-appointment-item">
	<div class="dak-appointment-item-body">
		<span class="dak-appointment-item-name">
			<span class="dak-appointment-item-name-text"><?php echo esc_html( $name ); ?></span>
			<span class="dak-tag <?php echo 'video' === $type ? 'dak-tag-video' : 'dak-tag-clinic'; ?>"><?php echo esc_html( $note ); ?></span>
			<span class="dak-tag <?php echo $is_paid ? 'dak-tag-paid' : 'dak-tag-pending-payment'; ?>">
				<?php echo $is_paid ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?>
			</span>
			<?php if ( ! empty( $video_call['applicable'] ) ) : ?>
				<?php if ( $video_call['can_join'] ) : ?>
					<button type="button" class="dak-tag dak-tag-join-call" data-join-video-call data-room-url="<?php echo esc_url( $video_call['room_url'] ); ?>"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></button>
				<?php else : ?>
					<span class="dak-tag dak-tag-join-call is-disabled" title="<?php echo esc_attr( $video_call['hint'] ); ?>"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></span>
				<?php endif; ?>
			<?php endif; ?>
		</span>
	</div>
	<div class="dak-appointment-item-meta">
		<span class="dak-appointment-item-time"><?php echo esc_html( $formatted_datetime ); ?></span>
	</div>
</div>
