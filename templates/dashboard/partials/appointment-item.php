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
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$formatted_date = $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '';
$formatted_time = ( $date && $time ) ? date_i18n( get_option( 'time_format' ), strtotime( $date . ' ' . $time ) ) : '';
$is_paid        = 'paid' === $payment_status;
?>
<div class="dak-appointment-item">
	<div class="dak-appointment-item-body">
		<span class="dak-appointment-item-name">
			<span class="dak-appointment-item-name-text"><?php echo esc_html( $name ); ?></span>
			<span class="dak-tag <?php echo 'video' === $type ? 'dak-tag-video' : 'dak-tag-clinic'; ?>"><?php echo esc_html( $note ); ?></span>
			<span class="dak-tag <?php echo $is_paid ? 'dak-tag-paid' : 'dak-tag-pending-payment'; ?>">
				<?php echo $is_paid ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?>
			</span>
		</span>
	</div>
	<div class="dak-appointment-item-meta">
		<span class="dak-appointment-item-time"><?php echo esc_html( trim( $formatted_date . ' · ' . $formatted_time, ' ·' ) ); ?></span>
	</div>
</div>
