<?php
/**
 * Template: Patient dashboard "Medical History" tab — visit notes doctors
 * have added after this patient's completed appointments.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $visits Completed-appointment rows from Appointments::all_for_admin(), scoped to this patient.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="dak-dashboard-card">
	<?php if ( empty( $visits ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'You have no completed visits yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<ul class="dak-patient-payments-list">
			<?php foreach ( $visits as $visit ) : ?>
				<li id="dak-visit-<?php echo esc_attr( $visit['id'] ); ?>" class="dak-patient-payment-row">
					<span class="dak-patient-appt-avatar">
						<?php if ( $visit['doctor_avatar_url'] ) : ?>
							<img src="<?php echo esc_url( $visit['doctor_avatar_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo esc_html( mb_strtoupper( mb_substr( $visit['doctor_name'], 0, 1 ) ) ); ?>
						<?php endif; ?>
					</span>

					<div class="dak-patient-payment-info">
						<strong><?php echo esc_html( sprintf( 'Dr. %s', $visit['doctor_name'] ) ); ?></strong>
						<span class="dak-patient-payment-meta">
							<?php
							$visit_date_timestamp = strtotime( $visit['date'] );
							$visit_time_timestamp = strtotime( $visit['date'] . ' ' . $visit['time'] );
							echo esc_html( $visit_date_timestamp ? date_i18n( 'D, M j, Y', $visit_date_timestamp ) : $visit['date'] );
							echo ' &middot; ';
							echo esc_html( $visit_time_timestamp ? date_i18n( 'g:i A', $visit_time_timestamp ) : $visit['time'] );
							echo ' &middot; ';
							echo esc_html( '' !== $visit['service_name'] ? $visit['service_name'] : $visit['type_label'] );
							?>
						</span>

						<div class="dak-medical-history-note">
							<span class="dak-field-label"><?php esc_html_e( 'Visit note', 'doctor-ak-portal' ); ?></span>
							<?php if ( '' !== trim( (string) $visit['encounter_notes'] ) ) : ?>
								<p><?php echo esc_html( $visit['encounter_notes'] ); ?></p>
							<?php else : ?>
								<p class="dak-medical-history-note-empty"><?php esc_html_e( 'No note recorded for this visit yet.', 'doctor-ak-portal' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
