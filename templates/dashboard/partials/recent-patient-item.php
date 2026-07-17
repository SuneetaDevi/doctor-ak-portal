<?php
/**
 * Template: Single "recent patient" row for the doctor dashboard.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $name       Patient's (or guest's) display name.
 * @var string $last_visit 'YYYY-MM-DD' of their most recent appointment.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$formatted_date = $last_visit ? date_i18n( get_option( 'date_format' ), strtotime( $last_visit ) ) : '';
?>
<div class="dak-patient-item">
	<div class="dak-patient-item-body">
		<span class="dak-patient-item-name"><?php echo esc_html( $name ); ?></span>
		<?php if ( $formatted_date ) : ?>
			<span class="dak-patient-item-note">
				<?php
				/* translators: %s: date of the most recent appointment. */
				echo esc_html( sprintf( __( 'Last visit: %s', 'doctor-ak-portal' ), $formatted_date ) );
				?>
			</span>
		<?php endif; ?>
	</div>
</div>
